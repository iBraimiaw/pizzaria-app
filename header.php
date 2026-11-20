<?php
// header.php - Header completo com acessibilidade
// Deve ser incluído em todas as páginas
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pizzaria do Bairro - A melhor pizza de Campo Grande - MS">
    <meta name="theme-color" content="#ff4f1f">
    <!-- Token CSRF para chamadas fetch()/AJAX: enviar no header "X-CSRF-Token" -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Pizzaria do Bairro</title>
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
<body<?= isset($body_class) ? ' class="' . e($body_class) . '"' : '' ?>>
    <!--
        Começo comum das páginas no visual novo: <head>, menu e funções de "carregando".
        Todo o CSS vem do assets/css/style.css (este arquivo não tem <style> próprio).
        Quem incluir este arquivo (depois do includes/functions.php):
          1. pode definir $page_title antes (a aba fica "Título - Pizzaria do Bairro");
          2. abre logo em seguida o <main class="site-content" id="main-content">
             (é para lá que o link "Pular para o conteúdo principal" leva);
          3. no fim do <body> carrega assets/js/site.js e assets/js/acessibilidade.js
             com defer (sem o site.js o menu do celular não abre) e fecha </body></html>;
          4. pode definir $body_class antes, com o fundo da página: 'fundo-creme'
             (formulários) ou 'fundo-xadrez' (cardápio, carrinho, pedidos). Sem ela o
             fundo fica branco. Só dá para escolher aqui: depois o <body> já foi impresso;
          5. pode definir $pagina_atual antes, com o arquivo da página (ex.: 'cardapio.php'):
             o link dela no menu fica marcado (is-active + aria-current) e, no cardápio
             e na pizza dois sabores, o submenu "Cardápio" já abre.
    -->

    <!-- Skip Link para acessibilidade -->
    <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

    <!-- Loading Overlay: o showLoading() do fim deste arquivo mostra, o hideLoading() esconde -->
    <div id="loadingOverlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner"></div>
    </div>

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
            <?php
            // Link da página atual (item 5 lá em cima), só exibição:
            // $menu_marca('cardapio.php') devolve ' class="is-active" aria-current="page"'
            // quando a página atual é o cardapio.php, e '' (nada) nas outras
            $menu_marca = fn(string $pagina): string => ($pagina_atual ?? '') === $pagina ? ' class="is-active" aria-current="page"' : '';
            $menu_cardapio_aberto = in_array($pagina_atual ?? '', ['cardapio.php', 'pizza-dupla.php'], true);
            ?>
            <nav class="site-nav" aria-label="Menu principal">
                <ul class="site-nav__menu">
                    <li><a href="index.php"<?= $menu_marca('index.php') ?>>Início</a></li>
                    <li class="site-nav__grupo<?= $menu_cardapio_aberto ? ' is-open' : '' ?>">
                        <button type="button" class="site-nav__alternar" aria-expanded="<?= $menu_cardapio_aberto ? 'true' : 'false' ?>" aria-controls="subCardapio">
                            Cardápio <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="site-nav__sub" id="subCardapio">
                            <ul>
                                <li><a href="cardapio.php"<?= $menu_marca('cardapio.php') ?>>Todos os sabores</a></li>
                                <li><a href="pizza-dupla.php"<?= $menu_marca('pizza-dupla.php') ?>>Pizza dois sabores</a></li>
                            </ul>
                        </div>
                    </li>
                    <!-- As mesmas condições de antes (valem o mesmo que isLoggedIn() e isAdmin() do includes/functions.php) -->
                    <?php if(isset($_SESSION['usuario_id'])): ?>
                        <li><a href="meus-pedidos.php"<?= $menu_marca('meus-pedidos.php') ?>>Meus Pedidos</a></li>
                        <li><a href="carrinho.php"<?= $menu_marca('carrinho.php') ?> aria-label="Ver carrinho de compras">Carrinho</a></li>
                        <?php if(isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'ADMIN'): ?>
                            <li><a href="admin/" aria-label="Painel administrativo">Admin</a></li>
                        <?php endif; ?>
                        <li class="site-nav__usuario">
                            <!-- "Usuário:" só para o leitor de tela (aria-label num <span> é ignorado) -->
                            <i class="fas fa-user" aria-hidden="true"></i> <span class="sr-only">Usuário: </span><?= e($_SESSION['usuario_nome']) ?>
                        </li>
                        <li><a href="logout.php?token=<?= e(csrf_token()) ?>" aria-label="Sair do sistema">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php"<?= $menu_marca('login.php') ?>>Entrar</a></li>
                        <li><a href="cadastro.php"<?= $menu_marca('cadastro.php') ?>>Cadastrar</a></li>
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

    <script>
        // Função para mostrar loading
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Função para anunciar para leitores de tela
        function announce(message) {
            const status = document.getElementById('liveStatus');
            status.textContent = message;
            setTimeout(() => {
                status.textContent = '';
            }, 3000);
        }

        // Adicionar evento de clique para links (mostrar loading)
        // (links "#" não trocam de página, como o "Pular para o conteúdo" e as redes
        // sociais do menu, então não mostram o loading: ele ficaria preso na tela)
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('javascript:') && !this.target && this.getAttribute('href').charAt(0) !== '#') {
                    showLoading();
                }
            });
        });

        // Esconder loading quando a página carregar
        window.addEventListener('load', () => {
            hideLoading();
        });

        // Voltou pelo botão "voltar" do navegador: a página vem da memória e o
        // evento "load" não roda de novo, então escondemos o loading aqui também
        window.addEventListener('pageshow', hideLoading);
    </script>