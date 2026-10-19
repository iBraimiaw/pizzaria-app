<?php
require_once 'includes/functions.php';

$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));
$erro = '';
$sucesso = '';
$recuperacao = null;

/**
 * Procura um pedido de recuperação VÁLIDO para o token do link:
 * não usado, não expirado. O banco guarda só o hash sha256 do token,
 * então calculamos o hash do que veio na URL e comparamos.
 */
function buscar_recuperacao_valida(PDO $pdo, string $token): ?array
{
    // Token legítimo = 64 caracteres hexadecimais; qualquer outra coisa nem vai ao banco
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT rs.*, u.id as usuario_id, u.nome 
        FROM recuperacao_senha rs
        JOIN usuarios u ON rs.usuario_id = u.id
        WHERE rs.token = ? AND rs.usado = FALSE AND rs.expiracao > NOW()
    ");
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

// Verificar se o token é válido
if($token) {
    $recuperacao = buscar_recuperacao_valida($pdo, $token);
    
    if(!$recuperacao) {
        $erro = "Link inválido ou expirado. Solicite uma nova recuperação.";
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redefinir'])) {
    verificar_csrf();

    $nova_senha = (string) ($_POST['nova_senha'] ?? '');
    $confirmar_senha = (string) ($_POST['confirmar_senha'] ?? '');
    $problemas_senha = validar_forca_senha($nova_senha);
    
    if(!$recuperacao) {
        $erro = "Link inválido ou expirado. Solicite uma nova recuperação.";
    } elseif($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem!";
    } elseif($problemas_senha) {
        $erro = mensagem_senha_fraca($problemas_senha); // política forte conferida no servidor
    } else {
        // Atualizar senha do usuário (bcrypt)
        atualizar_hash_senha($pdo, (int) $recuperacao['usuario_id'], $nova_senha);
        
        // Uso único: marca este e qualquer outro link pendente do usuário como usado
        $stmt = $pdo->prepare("UPDATE recuperacao_senha SET usado = TRUE WHERE usuario_id = ?");
        $stmt->execute([$recuperacao['usuario_id']]);

        registrar_evento_seguranca($pdo, 'senha_redefinida', (int) $recuperacao['usuario_id']);
        
        $sucesso = "Senha redefinida com sucesso! Você já pode fazer login.";
        // Redirecionar após 3 segundos
        header("refresh:3;url=login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Pizzaria do Bairro</title>
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
        }
        h1 {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
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
        .btn-redefinir {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-redefinir:hover {
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
        .senha-requisitos {
            font-size: 12px;
            margin-top: 5px;
        }
        .valid { color: #27ae60; }
        .invalid { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-lock"></i> Redefinir Senha</h1>
        <p class="subtitle">Crie uma nova senha para sua conta</p>
        
        <?php if($erro): ?>
            <div class="alert alert-danger"><?= e($erro) ?></div>
        <?php endif; ?>
        
        <?php if($sucesso): ?>
            <div class="alert alert-success"><?= e($sucesso) ?></div>
        <?php endif; ?>
        
        <?php if(!$sucesso && $recuperacao): /* token válido: mostra o form (mesmo se a senha digitada foi recusada) */ ?>
            <form method="POST">
                <?= campo_csrf() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Nova Senha:</label>
                    <input type="password" name="nova_senha" id="nova_senha" required onkeyup="validarForcaSenha()">
                    <div class="senha-requisitos" id="reqMaiuscula">❌ Pelo menos uma letra MAIÚSCULA</div>
                    <div class="senha-requisitos" id="reqMinuscula">❌ Pelo menos uma letra minúscula</div>
                    <div class="senha-requisitos" id="reqTamanho">❌ Mínimo 8 caracteres</div>
                    <div class="senha-requisitos" id="reqEspecial">❌ Pelo menos um número e um caractere especial</div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirmar Senha:</label>
                    <input type="password" name="confirmar_senha" id="confirmar_senha" required onkeyup="validarConfirmacao()">
                    <div class="senha-requisitos" id="reqConfirmacao">✓ As senhas devem coincidir</div>
                </div>
                <button type="submit" name="redefinir" class="btn-redefinir">Redefinir Senha</button>
            </form>
        <?php elseif(!$token): ?>
            <div class="alert alert-danger">Token de recuperação não informado.</div>
        <?php elseif($erro && !$sucesso): ?>
            <a href="esqueceu-senha.php" class="btn-redefinir" style="display: block; text-align: center; text-decoration: none; background: #e74c3c;">Solicitar novo link</a>
        <?php endif; ?>
    </div>
    
    <script>
        function validarForcaSenha() {
            const senha = document.getElementById('nova_senha').value;
            const temMaiuscula = /[A-Z]/.test(senha);
            const temMinuscula = /[a-z]/.test(senha);
            const tamanhoValido = senha.length >= 8;
            const temNumeroEEspecial = /[0-9]/.test(senha) && /[^A-Za-z0-9]/.test(senha);
            document.getElementById('reqEspecial').innerHTML = (temNumeroEEspecial ? '✅' : '❌') + ' Pelo menos um número e um caractere especial';
            document.getElementById('reqEspecial').className = 'senha-requisitos ' + (temNumeroEEspecial ? 'valid' : 'invalid');
            
            document.getElementById('reqMaiuscula').innerHTML = (temMaiuscula ? '✅' : '❌') + ' Pelo menos uma letra MAIÚSCULA';
            document.getElementById('reqMaiuscula').className = 'senha-requisitos ' + (temMaiuscula ? 'valid' : 'invalid');
            
            document.getElementById('reqMinuscula').innerHTML = (temMinuscula ? '✅' : '❌') + ' Pelo menos uma letra minúscula';
            document.getElementById('reqMinuscula').className = 'senha-requisitos ' + (temMinuscula ? 'valid' : 'invalid');
            
            document.getElementById('reqTamanho').innerHTML = (tamanhoValido ? '✅' : '❌') + ' Mínimo 8 caracteres';
            document.getElementById('reqTamanho').className = 'senha-requisitos ' + (tamanhoValido ? 'valid' : 'invalid');
            
            validarConfirmacao();
        }
        
        function validarConfirmacao() {
            const senha = document.getElementById('nova_senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const reqConfirmacao = document.getElementById('reqConfirmacao');
            
            if(confirmar === '') {
                reqConfirmacao.innerHTML = '✓ As senhas devem coincidir';
                reqConfirmacao.className = 'senha-requisitos';
            } else if(senha === confirmar) {
                reqConfirmacao.innerHTML = '✅ Senhas coincidem';
                reqConfirmacao.className = 'senha-requisitos valid';
            } else {
                reqConfirmacao.innerHTML = '❌ As senhas NÃO coincidem';
                reqConfirmacao.className = 'senha-requisitos invalid';
            }
        }
    </script>
</body>
</html>