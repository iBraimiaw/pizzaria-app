<?php
require_once 'includes/functions.php';

// "Não sou um robô": fora de dev, a página não abre com as chaves de teste do Google
exigir_recaptcha_configurado();

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

    // "Não sou um robô": é o primeiro teste da lista abaixo.
    // Se falhar, este pedido não conta no limite de recuperação.
    $erro_robo = verificar_recaptcha($pdo, $email);

    // Rate limit: no máximo RECUPERACAO_MAX_PEDIDOS pedidos a cada 15 min
    $segundos_bloqueio = verificar_bloqueio($pdo, $identificador, $ip, RECUPERACAO_MAX_PEDIDOS, RECUPERACAO_JANELA_MINUTOS);

    if ($erro_robo !== null) {
        $erro = $erro_robo;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    <meta name="theme-color" content="#ff4f1f">
    <title>Esqueceu a Senha - Pizzaria do Bairro</title>
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
        /* Estilos só desta página (o resto vem do assets/css/style.css; a caixa do
           "Não sou um robô" e o botão largo no celular também vêm de lá) */

        /* Celular estreito: o texto do botão desta página é comprido. Com a letra
           um pouco menor ele cabe numa linha só e o botão continua uma pílula. */
        @media (max-width: 380px) {
            .pagina-form .form-acoes .btn {
                padding-left: 1rem;
                padding-right: 1rem;
                font-size: .8rem;
            }
        }
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
            <h1 id="titulo-pagina">Esqueceu a Senha?</h1>
            <p>Digite seu email para receber o link de recuperação</p>

            <?php if($mensagem): ?>
                <div class="alert alert-success" role="status"><?= e($mensagem) ?></div>
            <?php endif; ?>

            <?php if($link_demonstracao): ?>
                <!-- Só aparece em APP_ENV=dev: simula o e-mail que o cliente receberia -->
                <div class="alert alert-info">
                    <strong>Modo demonstração (sem servidor de e-mail):</strong>
                    <a href="<?= e($link_demonstracao) ?>">clique aqui para redefinir a senha</a>
                </div>
            <?php endif; ?>

            <?php if($erro): ?>
                <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
            <?php endif; ?>

            <!-- form-ui: campo, caixa do robô e botão alinhados à esquerda (só o título fica no centro) -->
            <form method="POST" class="form-ui">
                <?= campo_csrf() ?>
                <div class="form-group">
                    <label for="email">E-mail <em>- obrigatório</em></label>
                    <input type="email" name="email" id="email" required placeholder="seuemail@exemplo.com" autocomplete="email">
                </div>

                <div class="caixa-robo">
                    <?= campo_recaptcha() ?>
                </div>

                <div class="form-acoes">
                    <button type="submit" class="btn">Enviar Link de Recuperação</button>
                </div>
            </form>

            <p class="pagina-form__links">
                <a href="login.php">Voltar para o login</a>
            </p>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
</body>
</html>
