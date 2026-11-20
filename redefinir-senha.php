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
    <meta name="theme-color" content="#ff4f1f">
    <title>Redefinir Senha - Pizzaria do Bairro</title>
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
                        <li><a href="cadastro.php">Cadastrar</a></li>
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

        <section class="container-sm pagina-form revealable" aria-labelledby="titulo-pagina">
            <h1 id="titulo-pagina">Redefinir Senha</h1>
            <p>Crie uma nova senha para sua conta</p>

            <?php if($erro): ?>
                <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
            <?php endif; ?>

            <?php if($sucesso): ?>
                <div class="alert alert-success" role="status"><?= e($sucesso) ?></div>
            <?php endif; ?>

            <?php if(!$sucesso && $recuperacao): /* token válido: mostra o form (mesmo se a senha digitada foi recusada) */ ?>
                <!-- form-ui: campos e botão alinhados à esquerda (só o título fica no centro) -->
                <form method="POST" class="form-ui">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group">
                        <label for="nova_senha">Nova Senha <em>- obrigatório</em></label>
                        <input type="password" name="nova_senha" id="nova_senha" required autocomplete="new-password" aria-describedby="reqMaiuscula reqMinuscula reqTamanho reqEspecial" onkeyup="validarForcaSenha()">
                        <!-- O círculo vazio é a marca de "ainda não conferido"; o JavaScript lá embaixo troca por certo/errado -->
                        <div class="senha-requisitos" id="reqMaiuscula"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos uma letra MAIÚSCULA</div>
                        <div class="senha-requisitos" id="reqMinuscula"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos uma letra minúscula</div>
                        <div class="senha-requisitos" id="reqTamanho"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Mínimo 8 caracteres</div>
                        <div class="senha-requisitos" id="reqEspecial"><i class="far fa-circle fa-fw" aria-hidden="true"></i> Pelo menos um número e um caractere especial</div>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha <em>- obrigatório</em></label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha" required autocomplete="new-password" aria-describedby="reqConfirmacao" onkeyup="validarConfirmacao()">
                        <div class="senha-requisitos" id="reqConfirmacao"><i class="far fa-circle fa-fw" aria-hidden="true"></i> As senhas devem coincidir</div>
                    </div>
                    <div class="form-acoes">
                        <button type="submit" name="redefinir" class="btn">Redefinir Senha</button>
                    </div>
                </form>
            <?php elseif(!$token): ?>
                <div class="alert alert-danger" role="alert">Token de recuperação não informado.</div>
            <?php elseif($erro && !$sucesso): ?>
                <div class="form-acoes">
                    <a href="esqueceu-senha.php" class="btn">Solicitar novo link</a>
                </div>
            <?php endif; ?>

            <p class="pagina-form__links">
                <a href="login.php">Voltar para o login</a>
            </p>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
    <script>
        // Marca no começo de cada linha de requisito da senha (a mesma do
        // cadastro.php). Antes eram emojis, cujas cores fogem da paleta; agora é
        // um ícone que herda a cor da linha (classes valid/invalid do style.css)
        // + um texto escondido (.sr-only) que conta o resultado para o leitor de tela.
        const MARCA_OK = '<i class="fas fa-circle-check fa-fw" aria-hidden="true"></i><span class="sr-only">Atendido:</span>';
        const MARCA_FALTA = '<i class="fas fa-circle-xmark fa-fw" aria-hidden="true"></i><span class="sr-only">Não atendido:</span>';
        const MARCA_NEUTRA = '<i class="far fa-circle fa-fw" aria-hidden="true"></i>';

        function validarForcaSenha() {
            const senha = document.getElementById('nova_senha').value;
            const temMaiuscula = /[A-Z]/.test(senha);
            const temMinuscula = /[a-z]/.test(senha);
            const tamanhoValido = senha.length >= 8;
            const temNumeroEEspecial = /[0-9]/.test(senha) && /[^A-Za-z0-9]/.test(senha);
            document.getElementById('reqEspecial').innerHTML = (temNumeroEEspecial ? MARCA_OK : MARCA_FALTA) + ' Pelo menos um número e um caractere especial';
            document.getElementById('reqEspecial').className = 'senha-requisitos ' + (temNumeroEEspecial ? 'valid' : 'invalid');

            document.getElementById('reqMaiuscula').innerHTML = (temMaiuscula ? MARCA_OK : MARCA_FALTA) + ' Pelo menos uma letra MAIÚSCULA';
            document.getElementById('reqMaiuscula').className = 'senha-requisitos ' + (temMaiuscula ? 'valid' : 'invalid');

            document.getElementById('reqMinuscula').innerHTML = (temMinuscula ? MARCA_OK : MARCA_FALTA) + ' Pelo menos uma letra minúscula';
            document.getElementById('reqMinuscula').className = 'senha-requisitos ' + (temMinuscula ? 'valid' : 'invalid');

            document.getElementById('reqTamanho').innerHTML = (tamanhoValido ? MARCA_OK : MARCA_FALTA) + ' Mínimo 8 caracteres';
            document.getElementById('reqTamanho').className = 'senha-requisitos ' + (tamanhoValido ? 'valid' : 'invalid');

            validarConfirmacao();
        }

        function validarConfirmacao() {
            const senha = document.getElementById('nova_senha').value;
            const confirmar = document.getElementById('confirmar_senha').value;
            const reqConfirmacao = document.getElementById('reqConfirmacao');

            if(confirmar === '') {
                reqConfirmacao.innerHTML = MARCA_NEUTRA + ' As senhas devem coincidir';
                reqConfirmacao.className = 'senha-requisitos';
            } else if(senha === confirmar) {
                reqConfirmacao.innerHTML = MARCA_OK + ' Senhas coincidem';
                reqConfirmacao.className = 'senha-requisitos valid';
            } else {
                reqConfirmacao.innerHTML = MARCA_FALTA + ' As senhas NÃO coincidem';
                reqConfirmacao.className = 'senha-requisitos invalid';
            }
        }
    </script>
</body>
</html>
