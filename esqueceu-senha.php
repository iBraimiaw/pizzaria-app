<?php
require_once 'includes/functions.php';

$mensagem = '';
$erro = '';
$link_demonstracao = ''; // só preenchido em APP_ENV=dev (não há servidor de e-mail no Docker)

// Mensagem SEMPRE igual, exista ou não o e-mail: assim ninguém usa esta tela
// para descobrir quais e-mails têm conta na pizzaria.
const MENSAGEM_GENERICA_RECUPERACAO = 'Se este e-mail estiver cadastrado, você receberá um link para redefinir a senha (válido por 1 hora).';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificar_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $ip = obter_ip_cliente();
    $identificador = 'recuperacao:' . $email; // contagem separada da do login

    // Rate limit: no máximo RECUPERACAO_MAX_PEDIDOS pedidos a cada 15 min
    $segundos_bloqueio = verificar_bloqueio($pdo, $identificador, $ip, RECUPERACAO_MAX_PEDIDOS, RECUPERACAO_JANELA_MINUTOS);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif ($segundos_bloqueio > 0) {
        registrar_evento_seguranca($pdo, 'bloqueio', null, "Recuperação de senha bloqueada para $email");
        $erro = 'Muitos pedidos de recuperação. Tente novamente em ' . formatar_tempo_bloqueio($segundos_bloqueio) . '.';
    } else {
        registrar_tentativa_falha($pdo, $identificador, $ip); // conta este pedido
        $mensagem = MENSAGEM_GENERICA_RECUPERACAO;

        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if($usuario) {
            // Token: 32 bytes aleatórios (impossível de adivinhar). Ele vai SÓ no link.
            $token = bin2hex(random_bytes(32));
            // No banco guardamos o HASH (sha256) do token: se o banco vazar,
            // quem ler a tabela não consegue usar os links.
            $token_hash = hash('sha256', $token);

            // Pedido novo invalida os links antigos ainda não usados
            $stmt = $pdo->prepare("UPDATE recuperacao_senha SET usado = TRUE WHERE usuario_id = ? AND usado = FALSE");
            $stmt->execute([$usuario['id']]);

            // Expiração calculada pelo MySQL (mesmo relógio usado na conferência)
            $stmt = $pdo->prepare("INSERT INTO recuperacao_senha (usuario_id, token, expiracao) VALUES (?, ?, NOW() + INTERVAL ? HOUR)");
            $stmt->execute([$usuario['id'], $token_hash, RECUPERACAO_VALIDADE_HORAS]);

            // Link montado com APP_URL (configuração), não com o Host enviado pelo navegador
            $pasta = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $link = APP_URL . $pasta . "/redefinir-senha.php?token=" . $token;

            // Enviar email (simulação - em produção use PHPMailer ou similar)
            $assunto = "Recuperação de Senha - Pizzaria do Bairro";
            $mensagem_email = "
            <html>
            <head><title>Recuperação de Senha</title></head>
            <body>
                <h2>Olá, " . e($usuario['nome']) . "!</h2>
                <p>Você solicitou a recuperação de senha da sua conta na Pizzaria do Bairro.</p>
                <p>Clique no link abaixo para redefinir sua senha:</p>
                <p><a href='" . e($link) . "'>Redefinir Senha</a></p>
                <p>Este link é válido por 1 hora e só pode ser usado uma vez.</p>
                <p>Se você não solicitou essa alteração, ignore este email.</p>
                <br>
                <p>Atenciosamente,<br>Equipe Pizzaria do Bairro</p>
            </body>
            </html>
            ";

            registrar_evento_seguranca($pdo, 'recuperacao_solicitada', (int) $usuario['id']);

            // Em produção, descomente a linha abaixo e configure o envio de email
            // mail($email, $assunto, $mensagem_email, "Content-Type: text/html; charset=UTF-8");

            // Só em DEV mostramos o link na tela, para dar para testar sem e-mail
            if (APP_ENV === 'dev') {
                $link_demonstracao = $link;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueceu a Senha - Pizzaria do Bairro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 500px;
            width: 90%;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #e74c3c;
        }
        .btn-enviar {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-enviar:hover {
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: #e74c3c;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-key"></i> Esqueceu a Senha?</h1>
        <p class="subtitle">Digite seu email para receber o link de recuperação</p>
        
        <?php if($mensagem): ?>
            <div class="alert alert-success"><?= e($mensagem) ?></div>
        <?php endif; ?>

        <?php if($link_demonstracao): ?>
            <!-- Só aparece em APP_ENV=dev: simula o e-mail que o cliente receberia -->
            <div class="alert alert-success">
                <strong>Modo demonstração (sem servidor de e-mail):</strong>
                <a href="<?= e($link_demonstracao) ?>">clique aqui para redefinir a senha</a>
            </div>
        <?php endif; ?>

        <?php if($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= campo_csrf() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" name="email" required placeholder="seuemail@exemplo.com">
            </div>
            <button type="submit" class="btn-enviar">Enviar Link de Recuperação</button>
        </form>
        
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para o login</a>
    </div>
</body>
</html>