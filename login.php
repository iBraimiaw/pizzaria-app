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
$modo_recuperar = isset($_GET['recuperar']) ? true : false;
$token_valido = false;

// Segurança: todo POST desta página precisa do token CSRF (senão: 403)
verificar_csrf();

// Aviso quando a sessão anterior expirou por inatividade
if (isset($_SESSION['aviso_sessao'])) {
    $erro = $_SESSION['aviso_sessao'];
    unset($_SESSION['aviso_sessao']);
}

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
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $ip = obter_ip_cliente();

    // 1) "Não sou um robô" vem antes de tudo. Se falhar, nada mais roda:
    //    nem bloqueio, nem senha, e não conta como tentativa errada.
    $erro_robo = verificar_recaptcha($pdo, $email);

    // 2) Força bruta: muitas falhas recentes (e-mail + IP) = bloqueado por um tempo
    //    (só consulta quem passou no "Não sou um robô"; 0 = liberado)
    $segundos_bloqueio = ($erro_robo === null) ? verificar_bloqueio($pdo, $email, $ip) : 0;

    if ($erro_robo !== null) {
        $erro = $erro_robo;
    } elseif ($segundos_bloqueio > 0) {
        registrar_evento_seguranca($pdo, 'bloqueio', null, "Login bloqueado para $email");
        $erro = 'Muitas tentativas erradas. Tente novamente em ' . formatar_tempo_bloqueio($segundos_bloqueio) . '.';
    } else {
        // 3) Busca só pelo e-mail; a senha é conferida no PHP (hash nunca vai no SQL)
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Confere a senha e, se for MD5 antigo, já migra para bcrypt
            $senha_ok = verificar_senha_e_migrar($pdo, $usuario, $senha);
        } else {
            simular_verificacao_senha($senha); // mesmo tempo de resposta (não revela se o e-mail existe)
            $senha_ok = false;
        }

        if ($senha_ok) {
            limpar_tentativas($pdo, $email, $ip);
            renovar_sessao_apos_login(); // novo ID de sessão (anti session fixation)

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

            registrar_evento_seguranca($pdo, 'login_ok', (int) $usuario['id']);
            header('Location: index.php');
            exit;
        }

        // 4) Falhou: anota a tentativa e avisa (mensagem igual para e-mail ou senha errados)
        registrar_tentativa_falha($pdo, $email, $ip);
        registrar_evento_seguranca($pdo, 'login_falha', $usuario ? (int) $usuario['id'] : null, "E-mail: $email");

        $segundos_bloqueio = verificar_bloqueio($pdo, $email, $ip);
        if ($segundos_bloqueio > 0) {
            registrar_evento_seguranca($pdo, 'bloqueio', null, "Login bloqueado para $email");
            $erro = 'Muitas tentativas erradas. Tente novamente em ' . formatar_tempo_bloqueio($segundos_bloqueio) . '.';
        } else {
            $erro = 'Email ou senha incorretos!';
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
    <title>Login - Pizzaria do Bairro</title>
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

        /* "Esqueceu a senha?": link pequeno, à direita, colado no campo de senha
           (a margem negativa come parte do espaço que o campo deixa embaixo) */
        .pagina-form__ajuda {
            margin: -1.25rem 0 1.25rem;
            font-size: .8rem;
            text-align: right;
        }

        /* Um pouco de área em volta do link: mais fácil de acertar com o dedo */
        .pagina-form__ajuda a {
            display: inline-block;
            padding: .25rem 0 .25rem .5rem;
        }

        /* A caixa do "Não sou um robô" (.caixa-robo) e o botão largo no celular
           vêm do style.css: são iguais em todas as páginas de formulário */
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
                        <li><a href="login.php" class="is-active" aria-current="page">Entrar</a></li>
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
            <?php if($modo_recuperar): ?>
                <!-- Formulário de solicitação de recuperação (informativo) -->
                <h1 id="titulo-pagina">Recuperar Senha</h1>

                <?php if($erro): ?>
                    <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Funcionalidade em desenvolvimento!</strong><br>
                    A recuperação de senha estará disponível apenas quando o sistema for hospedado em um servidor externo (com envio de e-mail configurado).<br>
                    Em breve esta função será implementada.
                </div>

                <!-- form-ui: campos e botão alinhados à esquerda (só o título fica no centro) -->
                <form method="POST" class="form-ui">
                    <?= campo_csrf() ?>
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" name="email" id="email" placeholder="seuemail@exemplo.com" autocomplete="email">
                    </div>
                    <div class="form-acoes">
                        <button type="submit" name="recuperar_senha" class="btn" disabled>Enviar Link (Em breve)</button>
                    </div>
                </form>

                <p class="pagina-form__links">
                    <a href="login.php">Voltar para o login</a>
                </p>

            <?php else: ?>
                <!-- Formulário de login normal -->
                <h1 id="titulo-pagina">Login</h1>

                <?php if($erro): ?>
                    <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>

                <!-- form-ui: campos, caixa do robô e botão alinhados à esquerda -->
                <form method="POST" class="form-ui">
                    <?= campo_csrf() ?>
                    <div class="form-group">
                        <label for="email">E-mail <em>- obrigatório</em></label>
                        <input type="email" name="email" id="email" required placeholder="seuemail@exemplo.com" autocomplete="email">
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha <em>- obrigatório</em></label>
                        <input type="password" name="senha" id="senha" required autocomplete="current-password">
                    </div>

                    <p class="pagina-form__ajuda">
                        <a href="esqueceu-senha.php">Esqueceu a senha?</a>
                    </p>

                    <div class="caixa-robo">
                        <?= campo_recaptcha() ?>
                    </div>

                    <div class="form-acoes">
                        <button type="submit" name="login" class="btn">Entrar</button>
                    </div>
                </form>

                <p class="pagina-form__links">
                    Não tem conta? <a href="cadastro.php">Cadastre-se aqui</a>
                </p>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
</body>
</html>
