<?php
require_once 'includes/functions.php';

if(isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// "Não sou um robô": fora de dev, a página não abre com as chaves de teste do Google
exigir_recaptcha_configurado();

$erro = '';
$sucesso = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificar_csrf(); // formulário precisa ter vindo do nosso site

    // Lê e "limpa" cada campo (nunca confiar no que vem do navegador)
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmar_senha = (string) ($_POST['confirmar_senha'] ?? '');
    $endereco = trim((string) ($_POST['endereco'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $telefone_digitos = preg_replace('/\D/', '', $telefone); // só os números
    $problemas_senha = validar_forca_senha($senha);

    // "Não sou um robô": é o primeiro teste da lista abaixo. Se falhar, não
    // cadastra e o formulário volta com os campos preenchidos.
    $erro_robo = verificar_recaptcha($pdo, $email);

    if($erro_robo !== null) {
        $erro = $erro_robo;
    }
    elseif($nome === '' || mb_strlen($nome) > 100) {
        $erro = 'Informe seu nome (até 100 caracteres).';
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $erro = 'Informe um e-mail válido.';
    }
    elseif(strlen($telefone_digitos) < 10 || strlen($telefone_digitos) > 11) {
        $erro = 'Informe um telefone válido com DDD.';
    }
    elseif($endereco === '' || mb_strlen($endereco) > 255) {
        $erro = 'Informe seu endereço (até 255 caracteres).';
    }
    // Validar se as senhas coincidem
    elseif($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem!';
    }
    // Política de senha forte conferida NO SERVIDOR (o JS pode ser burlado)
    elseif($problemas_senha) {
        $erro = mensagem_senha_fraca($problemas_senha);
    }
    else {
        $senha_hash = gerar_hash_senha($senha); // bcrypt (nunca MD5)

        // Coordenadas: só aceita número dentro do intervalo válido do planeta
        $latitude = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => -90, 'max_range' => 90]]);
        $longitude = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => -180, 'max_range' => 180]]);
        $latitude = $latitude === false ? null : $latitude;
        $longitude = $longitude === false ? null : $longitude;

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
    <meta name="theme-color" content="#ff4f1f">
    <title>Cadastro - Pizzaria do Bairro</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,100..900;1,62..125,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/acessibilidade.css">
    <script>
        // Liga as animações de entrada. Se o site.js não carregar, a classe "js"
        // sai de novo e nenhum bloco fica invisível esperando a animação.
        document.documentElement.classList.add('js');
        window.addEventListener('load', function () {
            if (!window.__siteJs) { document.documentElement.classList.remove('js'); }
        });
    </script>
    <style>
        /* Estilos só desta página (o resto vem do assets/css/style.css) */

        /* Recado embaixo do botão "Buscar coordenadas" (o JavaScript escreve o
           texto e escolhe a cor). O ícone fica numa coluna só dele, então o texto
           continua alinhado quando quebra em duas linhas no celular (mesmo desenho
           das linhas de requisito da senha, que vem do style.css). Vazio ele não tem
           altura, então não deixa buraco; não usamos display:none para o leitor de
           tela continuar "ouvindo" a área. */
        .info-coordenadas {
            display: flex;
            align-items: baseline;
            gap: .4em;
            padding-left: 3px;
            font-size: .8rem;
        }

        .info-coordenadas:not(:empty) {
            margin-top: .5rem;
        }

        /* O ícone não encolhe quando o texto da linha é comprido */
        .info-coordenadas i {
            flex: none;
        }

        /* Erro na busca: o texto fica escuro (o JavaScript pinta com var(--tinta)) e
           só o triângulo fica vermelho. Vermelho em letra pequena sobre o creme não
           dá contraste para ler (3,85:1, o mínimo é 4,5:1); para ícone bastam 3:1. */
        .info-coordenadas .fa-exclamation-triangle {
            color: var(--erro);
        }

        /* A caixa do "Não sou um robô" (.caixa-robo), o botão largo no celular e a
           dica do campo terminando em "..." vêm do style.css (iguais nas páginas de formulário) */
    </style>
</head>
<body class="fundo-creme">
    <!-- Skip Link para acessibilidade -->
    <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

    <!-- Anúncio para leitores de tela -->
    <div class="sr-only" role="status" aria-live="polite" id="liveStatus"></div>

    <!-- Menu: coluna fixa na esquerda (computador) ou barra laranja no topo (celular) -->
    <header class="site-header" id="siteHeader">
        <div class="site-header__bar">
            <a class="site-logo" href="index.php" aria-label="Pizzaria do Bairro - página inicial">
                <img class="site-logo__pilha" src="assets/img/logo-pilha.svg" alt="Pizzaria do Bairro" width="255" height="325">
                <img class="site-logo__faixa" src="assets/img/logo-faixa.svg" alt="" aria-hidden="true" width="147" height="50">
            </a>
            <button type="button" class="nav-toggle" aria-controls="siteMenu" aria-expanded="false">
                <span class="sr-only">Abrir ou fechar o menu</span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
            </button>
        </div>
        <div class="site-header__panel" id="siteMenu">
            <div class="site-header__acao">
                <a href="cardapio.php" class="btn btn-bloco">Peça online</a>
            </div>
            <nav class="site-nav" aria-label="Menu principal">
                <ul class="site-nav__menu">
                    <li><a href="index.php">Início</a></li>
                    <li class="site-nav__grupo">
                        <button type="button" class="site-nav__alternar" aria-expanded="false" aria-controls="subCardapio">
                            Cardápio <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="site-nav__sub" id="subCardapio">
                            <ul>
                                <li><a href="cardapio.php">Todos os sabores</a></li>
                                <li><a href="pizza-dupla.php">Pizza dois sabores</a></li>
                            </ul>
                        </div>
                    </li>
                    <?php if(isLoggedIn()): ?>
                        <li><a href="meus-pedidos.php">Meus Pedidos</a></li>
                        <li><a href="carrinho.php" aria-label="Ver carrinho de compras">Carrinho</a></li>
                        <?php if(isAdmin()): ?>
                            <li><a href="admin/" aria-label="Painel administrativo">Admin</a></li>
                        <?php endif; ?>
                        <li class="site-nav__usuario">
                            <!-- "Usuário:" só para o leitor de tela (aria-label num <span> é ignorado) -->
                            <i class="fas fa-user" aria-hidden="true"></i> <span class="sr-only">Usuário: </span><?= e($_SESSION['usuario_nome']) ?>
                        </li>
                        <li><a href="logout.php?token=<?= e(csrf_token()) ?>" aria-label="Sair do sistema">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Entrar</a></li>
                        <li><a href="cadastro.php" class="is-active" aria-current="page">Cadastrar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="site-social">
                <ul>
                    <li><a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a></li>
                    <li><a href="#" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true"></i></a></li>
                    <li><a href="#" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a></li>
                </ul>
            </div>
            <p class="site-header__rodape">&copy; 2024 Pizzaria do Bairro</p>
        </div>
    </header>

    <main class="site-content" id="main-content">
        <!-- Enfeite: placa balançando no canto (só aparece em telas largas) -->
        <div class="enfeite-placa" aria-hidden="true"></div>

        <!-- Formulário de cadastro: título e campos sobre o fundo creme -->
        <section class="container-sm pagina-form revealable" aria-labelledby="titulo-pagina">
            <h1 id="titulo-pagina">Criar Conta</h1>

            <?php if($erro): ?>
                <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
            <?php endif; ?>

            <?php if($sucesso): ?>
                <div class="alert alert-success" role="status"><?= e($sucesso) ?></div>
                <!-- Depois do aviso, leva para o login sozinho em 2 segundos -->
                <script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>
            <?php endif; ?>

            <!-- form-ui: campos, caixa do robô e botão alinhados à esquerda (só o título fica no centro) -->
            <form method="POST" class="form-ui" id="cadastroForm" onsubmit="return validarSenha()">
                <?= campo_csrf() ?>
                <div class="form-group">
                    <label for="nome">Nome completo <em>- obrigatório</em></label>
                    <input type="text" name="nome" id="nome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail <em>- obrigatório</em></label>
                    <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="senha">Senha <em>- obrigatório</em></label>
                    <input type="password" name="senha" id="senha" autocomplete="new-password" aria-describedby="requisitosMaiuscula requisitosMinuscula requisitosTamanho requisitosEspecial" required onkeyup="validarForcaSenha()">
                    <!-- Enquanto a pessoa digita, o JavaScript troca a classe (valid/invalid) e o ícone de cada linha -->
                    <div class="senha-requisitos" id="requisitosMaiuscula"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos uma letra MAIÚSCULA</div>
                    <div class="senha-requisitos" id="requisitosMinuscula"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos uma letra minúscula</div>
                    <div class="senha-requisitos" id="requisitosTamanho"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Mínimo 8 caracteres</div>
                    <div class="senha-requisitos" id="requisitosEspecial"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos um número e um caractere especial</div>
                </div>

                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha <em>- obrigatório</em></label>
                    <input type="password" name="confirmar_senha" id="confirmar_senha" autocomplete="new-password" aria-describedby="requisitosConfirmacao" required onkeyup="validarConfirmacaoSenha()">
                    <div class="senha-requisitos" id="requisitosConfirmacao"><i class="far fa-circle fa-fw" aria-hidden="true"></i> As senhas devem coincidir</div>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone <em>- obrigatório</em></label>
                    <input type="tel" name="telefone" id="telefone" value="<?php echo isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : ''; ?>" placeholder="(67) 99999-9999" autocomplete="tel" required>
                </div>

                <div class="form-group">
                    <label for="endereco">Endereço <em>- obrigatório</em></label>
                    <!-- autocomplete "address-line1": o "street-address" só vale em campo de várias linhas (textarea) -->
                    <input type="text" name="endereco" id="endereco" value="<?php echo isset($_POST['endereco']) ? htmlspecialchars($_POST['endereco']) : ''; ?>" placeholder="Rua, número, bairro, Campo Grande - MS" autocomplete="address-line1" required>
                    <button type="button" onclick="obterCoordenadas()" class="btn btn-pequeno btn-contorno">Buscar coordenadas</button>
                    <!-- O resultado da busca aparece aqui, logo abaixo do botão -->
                    <div class="info-coordenadas" id="infoCoordenadas" role="status" aria-live="polite"></div>
                </div>

                <!-- Se o formulário voltar com erro, as coordenadas já buscadas continuam aqui -->
                <input type="hidden" name="latitude" id="latitude" value="<?= e($_POST['latitude'] ?? '') ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= e($_POST['longitude'] ?? '') ?>">

                <div class="caixa-robo">
                    <?= campo_recaptcha() ?>
                </div>

                <div class="form-acoes">
                    <button type="submit" class="btn">Cadastrar</button>
                </div>
            </form>

            <p class="pagina-form__links">
                Já tem conta? <a href="login.php">Faça login aqui</a>
            </p>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
    <script>
        // Marca no começo de cada linha de requisito da senha (a mesma do
        // redefinir-senha.php). Antes eram emojis, cujas cores fogem da paleta;
        // agora é um ícone que herda a cor da linha (classes valid/invalid do
        // style.css) + um texto escondido (.sr-only) para o leitor de tela.
        const MARCA_OK = '<i class="fas fa-circle-check fa-fw" aria-hidden="true"></i><span class="sr-only">Atendido:</span>';
        const MARCA_FALTA = '<i class="fas fa-circle-xmark fa-fw" aria-hidden="true"></i><span class="sr-only">Não atendido:</span>';
        const MARCA_NEUTRA = '<i class="far fa-circle fa-fw" aria-hidden="true"></i>';

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
            const tamanhoValido = senha.length >= 8;
            const temNumeroEEspecial = /[0-9]/.test(senha) && /[^A-Za-z0-9]/.test(senha);
            const reqEspecial = document.getElementById('requisitosEspecial');
            reqEspecial.className = 'senha-requisitos ' + (temNumeroEEspecial ? 'valid' : 'invalid');
            reqEspecial.innerHTML = (temNumeroEEspecial ? MARCA_OK : MARCA_FALTA) + ' Pelo menos um número e um caractere especial';
            
            const reqMaiuscula = document.getElementById('requisitosMaiuscula');
            const reqMinuscula = document.getElementById('requisitosMinuscula');
            const reqTamanho = document.getElementById('requisitosTamanho');
            
            reqMaiuscula.className = 'senha-requisitos ' + (temMaiuscula ? 'valid' : 'invalid');
            reqMinuscula.className = 'senha-requisitos ' + (temMinuscula ? 'valid' : 'invalid');
            reqTamanho.className = 'senha-requisitos ' + (tamanhoValido ? 'valid' : 'invalid');
            
            // Atualizar ícones
            reqMaiuscula.innerHTML = (temMaiuscula ? MARCA_OK : MARCA_FALTA) + ' Pelo menos uma letra MAIÚSCULA';
            reqMinuscula.innerHTML = (temMinuscula ? MARCA_OK : MARCA_FALTA) + ' Pelo menos uma letra minúscula';
            reqTamanho.innerHTML = (tamanhoValido ? MARCA_OK : MARCA_FALTA) + ' Mínimo 8 caracteres';
            
            validarConfirmacaoSenha();
        }
        
        // Validação de confirmação de senha
        function validarConfirmacaoSenha() {
            const senha = document.getElementById('senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const reqConfirmacao = document.getElementById('requisitosConfirmacao');
            
            if (confirmar === '') {
                reqConfirmacao.innerHTML = MARCA_NEUTRA + ' As senhas devem coincidir';
                reqConfirmacao.className = 'senha-requisitos';
            } else if (senha === confirmar) {
                reqConfirmacao.innerHTML = MARCA_OK + ' Senhas coincidem';
                reqConfirmacao.className = 'senha-requisitos valid';
            } else {
                reqConfirmacao.innerHTML = MARCA_FALTA + ' As senhas NÃO coincidem';
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
            
            if (senha.length < 8) {
                alert('A senha deve ter no mínimo 8 caracteres!');
                return false;
            }

            if (!/[0-9]/.test(senha) || !/[^A-Za-z0-9]/.test(senha)) {
                alert('A senha deve ter pelo menos um número e um caractere especial!');
                return false;
            }

            // Obs.: esta checagem é só para ajudar o usuário. A regra "de verdade"
            // é conferida de novo no servidor (validar_forca_senha no PHP).
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
            
            // As cores vêm da paleta do site (variáveis do style.css): carvão para
            // "buscando" e "deu certo". No erro o texto fica na cor do texto (tinta)
            // e só o ícone fica vermelho (regra no <style> desta página): vermelho
            // em letra pequena sobre o creme fica difícil de ler
            infoDiv.innerHTML = '<i class="fas fa-spinner fa-spin fa-fw" aria-hidden="true"></i> Buscando coordenadas...';
            infoDiv.style.color = 'var(--carvao)';
            
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
                        
                        infoDiv.innerHTML = '<i class="fas fa-check-circle fa-fw" aria-hidden="true"></i> Coordenadas obtidas com sucesso! Lat: ' + lat.toFixed(6) + ', Lon: ' + lon.toFixed(6);
                        infoDiv.style.color = 'var(--carvao)';
                    } else {
                        infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle fa-fw" aria-hidden="true"></i> Não foi possível encontrar o endereço. A entrega será calculada manualmente.';
                        infoDiv.style.color = 'var(--tinta)';
                        document.getElementById('latitude').value = '';
                        document.getElementById('longitude').value = '';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle fa-fw" aria-hidden="true"></i> Erro ao buscar coordenadas. A entrega será calculada manualmente.';
                    infoDiv.style.color = 'var(--tinta)';
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