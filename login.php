<?php
require_once 'includes/functions.php';

if(isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';
$modo_recuperar = isset($_GET['recuperar']) ? true : false;
$token_valido = false;

// Processar solicitação de recuperação de senha (DESATIVADO - mensagem informativa)
if(isset($_POST['recuperar_senha'])) {
    // Função temporariamente desativada - apenas exibe mensagem
    $erro = '⚠️ A função de recuperação de senha estará disponível apenas quando o sistema for hospedado em um servidor externo. Em breve será implementada!';
}

// Processar redefinição de senha via token (desativado)
if(isset($_POST['redefinir_senha'])) {
    $erro = '⚠️ A função de redefinição de senha estará disponível apenas quando o sistema for hospedado em um servidor externo. Em breve será implementada!';
}

// Verificar token na URL (desativado)
if(isset($_GET['token']) && !isset($_POST['redefinir_senha'])) {
    $erro = '⚠️ A função de recuperação de senha estará disponível apenas quando o sistema for hospedado em um servidor externo. Em breve será implementada!';
    $modo_recuperar = false; // Volta para o login normal
}

// Processar login normal
if(isset($_POST['login'])) {
    $email = $_POST['email'];
    $senha = md5($_POST['senha']);
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND senha = ?");
    $stmt->execute([$email, $senha]);
    $usuario = $stmt->fetch();
    
    if($usuario) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
        
        header('Location: index.php');
        exit;
    } else {
        $erro = 'Email ou senha incorretos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pizzaria do Bairro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .form-container {
            max-width: 450px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .form-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
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
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #e74c3c;
        }
        .btn-login {
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
        .btn-login:hover {
            transform: translateY(-2px);
        }
        .btn-enviar {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-enviar:hover {
            background: #2980b9;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
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
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .link-cadastro {
            text-align: center;
            margin-top: 20px;
        }
        .link-cadastro a {
            color: #e74c3c;
            text-decoration: none;
        }
        .link-cadastro a:hover {
            text-decoration: underline;
        }
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        .forgot-password a {
            color: #666;
            font-size: 12px;
            text-decoration: none;
        }
        .forgot-password a:hover {
            color: #e74c3c;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if($modo_recuperar): ?>
            <!-- Formulário de solicitação de recuperação (informativo) -->
            <h2><i class="fas fa-key"></i> Recuperar Senha</h2>
            
            <?php if($erro): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Funcionalidade em desenvolvimento!</strong><br>
                A recuperação de senha estará disponível apenas quando o sistema for hospedado em um servidor externo (com envio de e-mail configurado).<br>
                Em breve esta função será implementada.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> E-mail:</label>
                    <input type="email" name="email" placeholder="seuemail@exemplo.com">
                </div>
                <button type="submit" name="recuperar_senha" class="btn-enviar" disabled style="background: #ccc; cursor: not-allowed;">
                    <i class="fas fa-paper-plane"></i> Enviar Link (Em breve)
                </button>
            </form>
            <div class="back-to-login">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar para o login</a>
            </div>
            
        <?php else: ?>
            <!-- Formulário de login normal -->
            <h2><i class="fas fa-pizza-slice"></i> Login</h2>
            
            <?php if($erro): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email:</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Senha:</label>
                    <input type="password" name="senha" required>
                </div>
                
                <div class="forgot-password">
                    <a href="?recuperar=1"><i class="fas fa-question-circle"></i> Esqueceu a senha?</a>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="link-cadastro">
                Não tem conta? <a href="cadastro.php">Cadastre-se aqui</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>