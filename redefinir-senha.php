<?php
require_once 'includes/functions.php';

$token = (isset($_GET['token']) && token_hex_valido($_GET['token'])) ? $_GET['token'] : '';
$erro = '';
$sucesso = '';

// Verificar se o token é válido
if($token) {
    $stmt = $pdo->prepare("
        SELECT rs.*, u.id as usuario_id, u.nome 
        FROM recuperacao_senha rs
        JOIN usuarios u ON rs.usuario_id = u.id
        WHERE rs.token = ? AND rs.usado = FALSE AND rs.expiracao > NOW()
    ");
    $stmt->execute([$token]);
    $recuperacao = $stmt->fetch();
    
    if(!$recuperacao) {
        $erro = "Link inválido ou expirado. Solicite uma nova recuperação.";
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redefinir'])) {
    $token = (isset($_POST['token']) && token_hex_valido($_POST['token'])) ? $_POST['token'] : '';
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    // Validar token novamente
    $stmt = $pdo->prepare("
        SELECT rs.*, u.id as usuario_id 
        FROM recuperacao_senha rs
        JOIN usuarios u ON rs.usuario_id = u.id
        WHERE rs.token = ? AND rs.usado = FALSE AND rs.expiracao > NOW()
    ");
    $stmt->execute([$token]);
    $recuperacao = $stmt->fetch();
    
    if(!$recuperacao) {
        $erro = "Link inválido ou expirado. Solicite uma nova recuperação.";
    } elseif($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não coincidem!";
    } elseif(!preg_match('/[A-Z]/', $nova_senha) || !preg_match('/[a-z]/', $nova_senha)) {
        $erro = "A senha deve conter pelo menos uma letra maiúscula e uma letra minúscula!";
    } elseif(strlen($nova_senha) < 6) {
        $erro = "A senha deve ter no mínimo 6 caracteres!";
    } else {
        // Atualizar senha do usuário
        $senha_hash = md5($nova_senha);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$senha_hash, $recuperacao['usuario_id']]);
        
        // Marcar token como usado
        $stmt = $pdo->prepare("UPDATE recuperacao_senha SET usado = TRUE WHERE id = ?");
        $stmt->execute([$recuperacao['id']]);
        
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
        
        <?php if(!$erro && !$sucesso && $token && $recuperacao): ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Nova Senha:</label>
                    <input type="password" name="nova_senha" id="nova_senha" required onkeyup="validarForcaSenha()">
                    <div class="senha-requisitos" id="reqMaiuscula">❌ Pelo menos uma letra MAIÚSCULA</div>
                    <div class="senha-requisitos" id="reqMinuscula">❌ Pelo menos uma letra minúscula</div>
                    <div class="senha-requisitos" id="reqTamanho">❌ Mínimo 6 caracteres</div>
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
            const tamanhoValido = senha.length >= 6;
            
            document.getElementById('reqMaiuscula').innerHTML = (temMaiuscula ? '✅' : '❌') + ' Pelo menos uma letra MAIÚSCULA';
            document.getElementById('reqMaiuscula').className = 'senha-requisitos ' + (temMaiuscula ? 'valid' : 'invalid');
            
            document.getElementById('reqMinuscula').innerHTML = (temMinuscula ? '✅' : '❌') + ' Pelo menos uma letra minúscula';
            document.getElementById('reqMinuscula').className = 'senha-requisitos ' + (temMinuscula ? 'valid' : 'invalid');
            
            document.getElementById('reqTamanho').innerHTML = (tamanhoValido ? '✅' : '❌') + ' Mínimo 6 caracteres';
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