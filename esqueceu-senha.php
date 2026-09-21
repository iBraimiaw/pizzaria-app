<?php
require_once 'includes/functions.php';

$mensagem = '';
$erro = '';
$link_teste = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    
    // Verificar se email existe
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if($usuario) {
        // Gerar token único
        $token = bin2hex(random_bytes(32));
        $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Salvar token no banco
        $stmt = $pdo->prepare("INSERT INTO recuperacao_senha (usuario_id, token, expiracao) VALUES (?, ?, ?)");
        $stmt->execute([$usuario['id'], $token, $expiracao]);
        
        // Link de redefinição
        $link = "http://" . host_seguro() . "/pizzaria-unica/redefinir-senha.php?token=" . $token;
        $link_html = e($link);
        $nome_email = e($usuario['nome']);
        
        // Enviar email (simulação - em produção use PHPMailer ou similar)
        $assunto = "Recuperação de Senha - Pizzaria do Bairro";
        $mensagem_email = "
        <html>
        <head><title>Recuperação de Senha</title></head>
        <body>
            <h2>Olá, {$nome_email}!</h2>
            <p>Você solicitou a recuperação de senha da sua conta na Pizzaria do Bairro.</p>
            <p>Clique no link abaixo para redefinir sua senha:</p>
            <p><a href='{$link_html}'>Redefinir Senha</a></p>
            <p>Este link é válido por 1 hora.</p>
            <p>Se você não solicitou essa alteração, ignore este email.</p>
            <br>
            <p>Atenciosamente,<br>Equipe Pizzaria do Bairro</p>
        </body>
        </html>
        ";
        
        // Em ambiente de teste, exibir o link na tela (já que pode não ter servidor de email configurado)
        $_SESSION['link_recuperacao'] = $link;
        $mensagem = "Um link de recuperação foi enviado para seu email.";
        $link_teste = $link; // exibido só em ambiente de teste, sempre escapado no HTML
        
        // Em produção, descomente a linha abaixo e configure o envio de email
        // mail($email, $assunto, $mensagem_email, "Content-Type: text/html; charset=UTF-8");
        
    } else {
        $erro = "Email não encontrado no sistema.";
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
            <div class="alert alert-success"><?= e($mensagem) ?>
                <?php if($link_teste): ?>
                    (Em ambiente de teste, <a href="<?= e($link_teste) ?>" target="_blank" rel="noopener noreferrer">clique aqui</a> para redefinir sua senha)
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
        <?php endif; ?>
        
        <form method="POST">
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