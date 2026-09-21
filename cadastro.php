<?php
require_once 'includes/functions.php';

if(isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $confirmar_senha = (string)($_POST['confirmar_senha'] ?? '');
    $endereco = trim((string)($_POST['endereco'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    
    // Validação de entrada por lista de caracteres permitidos (primeira barreira contra XSS armazenado;
    // a defesa principal continua sendo escapar com e() ao exibir os dados)
    if(!preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’-]{1,99}$/u', $nome)) {
        $erro = 'Nome inválido! Use apenas letras, espaços, ponto, apóstrofo e hífen (2 a 100 caracteres).';
    }
    elseif(strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido!';
    }
    elseif(!preg_match('/^[0-9()\s\-+]{10,20}$/', $telefone) || strlen(preg_replace('/\D/', '', $telefone)) < 10 || strlen(preg_replace('/\D/', '', $telefone)) > 11) {
        $erro = 'Telefone inválido! Informe DDD e número.';
    }
    elseif(!preg_match('/^[\p{L}\p{M}\p{N}\s.,\-\/#º°ª\'()]{5,255}$/u', $endereco)) {
        $erro = 'Endereço inválido! Use letras, números e os símbolos . , - / # ( ) (5 a 255 caracteres).';
    }
    // Validar se as senhas coincidem
    elseif($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem!';
    }
    // Validar força da senha: pelo menos uma letra maiúscula e uma minúscula
    elseif(!preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha)) {
        $erro = 'A senha deve conter pelo menos uma letra maiúscula e uma letra minúscula!';
    }
    // Validar tamanho mínimo (opcional, mas recomendado)
    elseif(strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres!';
    }
    else {
        $senha_hash = md5($senha); // Mantendo o padrão do sistema
        $latitude = (isset($_POST['latitude']) && is_numeric($_POST['latitude']) && abs((float)$_POST['latitude']) <= 90) ? (float)$_POST['latitude'] : null;
        $longitude = (isset($_POST['longitude']) && is_numeric($_POST['longitude']) && abs((float)$_POST['longitude']) <= 180) ? (float)$_POST['longitude'] : null;
        
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->fetch()) {
            $erro = 'Email já cadastrado!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, endereco, telefone, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if($stmt->execute([$nome, $email, $senha_hash, $endereco, $telefone, $latitude, $longitude])) {
                $sucesso = 'Cadastro realizado com sucesso! Faça login para continuar.';
                // Limpar campos após sucesso
                $_POST = array();
            } else {
                $erro = 'Erro ao cadastrar! Tente novamente.';
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
    <title>Cadastro - Pizzaria do Bairro</title>
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
            max-width: 550px;
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
            font-size: 28px;
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
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
        }
        .btn-cadastrar {
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
        .btn-cadastrar:hover {
            transform: translateY(-2px);
        }
        .btn-buscar {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 5px;
            font-size: 12px;
        }
        .btn-buscar:hover {
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
        .link-login {
            text-align: center;
            margin-top: 20px;
        }
        .link-login a {
            color: #e74c3c;
            text-decoration: none;
        }
        .link-login a:hover {
            text-decoration: underline;
        }
        .info-coordenadas {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .senha-requisitos {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .senha-requisitos i {
            margin-right: 5px;
        }
        .senha-requisitos.valid {
            color: #27ae60;
        }
        .senha-requisitos.invalid {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>
            <i class="fas fa-user-plus"></i> 
            Criar Conta
        </h2>
        
        <?php if($erro): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= e($erro) ?>
            </div>
        <?php endif; ?>
        
        <?php if($sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= e($sucesso) ?>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>
        <?php endif; ?>
        
        <form method="POST" id="cadastroForm" onsubmit="return validarSenha()">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nome completo:</label>
                <input type="text" name="nome" value="<?php echo isset($_POST['nome']) ? e($_POST['nome']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Senha:</label>
                <input type="password" name="senha" id="senha" required onkeyup="validarForcaSenha()">
                <div class="senha-requisitos" id="requisitosMaiuscula">🔤 Pelo menos uma letra MAIÚSCULA</div>
                <div class="senha-requisitos" id="requisitosMinuscula">🔤 Pelo menos uma letra minúscula</div>
                <div class="senha-requisitos" id="requisitosTamanho">📏 Mínimo 6 caracteres</div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirmar Senha:</label>
                <input type="password" name="confirmar_senha" id="confirmar_senha" required onkeyup="validarConfirmacaoSenha()">
                <div class="senha-requisitos" id="requisitosConfirmacao">✓ As senhas devem coincidir</div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telefone:</label>
                <input type="tel" name="telefone" id="telefone" value="<?php echo isset($_POST['telefone']) ? e($_POST['telefone']) : ''; ?>" placeholder="(67) 99999-9999" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Endereço:</label>
                <input type="text" name="endereco" id="endereco" value="<?php echo isset($_POST['endereco']) ? e($_POST['endereco']) : ''; ?>" placeholder="Rua, número, bairro, Campo Grande - MS" required>
                <button type="button" onclick="obterCoordenadas()" class="btn-buscar">
                    <i class="fas fa-search"></i> Buscar coordenadas
                </button>
            </div>
            
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <div class="info-coordenadas" id="infoCoordenadas"></div>
            
            <button type="submit" class="btn-cadastrar">
                <i class="fas fa-user-plus"></i> Cadastrar
            </button>
        </form>
        
        <div class="link-login">
            Já tem conta? <a href="login.php">Faça login aqui</a>
        </div>
    </div>
    
    <script>
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });
        
        // Validação de força da senha (maiúscula, minúscula, tamanho)
        function validarForcaSenha() {
            const senha = document.getElementById('senha').value;
            
            const temMaiuscula = /[A-Z]/.test(senha);
            const temMinuscula = /[a-z]/.test(senha);
            const tamanhoValido = senha.length >= 6;
            
            const reqMaiuscula = document.getElementById('requisitosMaiuscula');
            const reqMinuscula = document.getElementById('requisitosMinuscula');
            const reqTamanho = document.getElementById('requisitosTamanho');
            
            reqMaiuscula.className = 'senha-requisitos ' + (temMaiuscula ? 'valid' : 'invalid');
            reqMinuscula.className = 'senha-requisitos ' + (temMinuscula ? 'valid' : 'invalid');
            reqTamanho.className = 'senha-requisitos ' + (tamanhoValido ? 'valid' : 'invalid');
            
            // Atualizar ícones
            reqMaiuscula.innerHTML = (temMaiuscula ? '✅' : '❌') + ' Pelo menos uma letra MAIÚSCULA';
            reqMinuscula.innerHTML = (temMinuscula ? '✅' : '❌') + ' Pelo menos uma letra minúscula';
            reqTamanho.innerHTML = (tamanhoValido ? '✅' : '❌') + ' Mínimo 6 caracteres';
            
            validarConfirmacaoSenha();
        }
        
        // Validação de confirmação de senha
        function validarConfirmacaoSenha() {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const reqConfirmacao = document.getElementById('requisitosConfirmacao');
            
            if (confirmar === '') {
                reqConfirmacao.innerHTML = '✓ As senhas devem coincidir';
                reqConfirmacao.className = 'senha-requisitos';
            } else if (senha === confirmar) {
                reqConfirmacao.innerHTML = '✅ Senhas coincidem';
                reqConfirmacao.className = 'senha-requisitos valid';
            } else {
                reqConfirmacao.innerHTML = '❌ As senhas NÃO coincidem';
                reqConfirmacao.className = 'senha-requisitos invalid';
            }
        }
        
        // Validação final antes de enviar o formulário
        function validarSenha() {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            
            if (senha !== confirmar) {
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (!/[A-Z]/.test(senha)) {
                alert('A senha deve conter pelo menos uma letra MAIÚSCULA!');
                return false;
            }
            
            if (!/[a-z]/.test(senha)) {
                alert('A senha deve conter pelo menos uma letra minúscula!');
                return false;
            }
            
            if (senha.length < 6) {
                alert('A senha deve ter no mínimo 6 caracteres!');
                return false;
            }
            
            return true;
        }
        
        // Buscar coordenadas do endereço
        function obterCoordenadas() {
            var endereco = document.getElementById('endereco').value;
            var infoDiv = document.getElementById('infoCoordenadas');
            
            if(!endereco) {
                alert('Digite o endereço primeiro!');
                return;
            }
            
            infoDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando coordenadas...';
            infoDiv.style.color = '#3498db';
            
            if (!endereco.toLowerCase().includes('campo grande')) {
                endereco = endereco + ', Campo Grande, MS';
            }
            
            fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(endereco)}&format=json&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if(data.length > 0) {
                        var lat = parseFloat(data[0].lat);
                        var lon = parseFloat(data[0].lon);
                        
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lon;
                        
                        infoDiv.innerHTML = '<i class="fas fa-check-circle"></i> Coordenadas obtidas com sucesso! Lat: ' + lat.toFixed(6) + ', Lon: ' + lon.toFixed(6);
                        infoDiv.style.color = '#27ae60';
                    } else {
                        infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Não foi possível encontrar o endereço. A entrega será calculada manualmente.';
                        infoDiv.style.color = '#e74c3c';
                        document.getElementById('latitude').value = '';
                        document.getElementById('longitude').value = '';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao buscar coordenadas. A entrega será calculada manualmente.';
                    infoDiv.style.color = '#e74c3c';
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                });
        }
        
        // Validação do telefone
        document.getElementById('cadastroForm').addEventListener('submit', function(e) {
            var telefone = document.getElementById('telefone').value;
            if(telefone.replace(/\D/g, '').length < 10) {
                e.preventDefault();
                alert('Por favor, digite um telefone válido!');
                return false;
            }
        });
        
        // Inicializar validação
        document.getElementById('senha').addEventListener('keyup', validarForcaSenha);
        document.getElementById('confirmar_senha').addEventListener('keyup', validarConfirmacaoSenha);
    </script>
</body>
</html>