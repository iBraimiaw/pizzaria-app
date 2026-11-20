<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pizzaria do Bairro - A melhor pizza de Campo Grande MS">
    <meta name="theme-color" content="#ff4f1f">
    <title>Pizzaria do Bairro - A melhor pizza de Campo Grande MS</title>
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
        /* Estilos só da página inicial (o resto vem do assets/css/style.css) */

        /* Boas-vindas: o h1 usa o tamanho de h2 (2.5rem). No tamanho padrão (3rem) ele
           chegava a três linhas numa tela de 1024px */
        .boas-vindas h1 {
            font-size: 2.5rem;
        }

        /* Frase de apoio com cara de h3, mas num <p>: não é título de seção, e um h3
           logo depois do h1 pularia o nível h2 na lista de títulos do leitor de tela */
        .boas-vindas .h3 {
            margin-bottom: .5rem;
        }

        /* Cartão inclinado: ele ocupa 35% da largura. Entre 1161px (abaixo disso o split
           empilha) e ~1350px, o "ESCOLHER" do título em 2.5rem não cabia na linha e era
           partido no meio. Aqui o título acompanha a largura da tela (no mínimo 1.75rem)
           e volta ao tamanho normal, 2.5rem, a partir de ~1400px. */
        @media (min-width: 1161px) {
            .split__cartao h2 {
                font-size: clamp(1.75rem, 4.8vw - 27px, 2.5rem);
            }
        }

        /* Mais pedidas: título e polaroids sobre o fundo creme */
        .mais-pedidas {
            padding-bottom: 4rem;
        }

        .mais-pedidas .btn {
            margin-top: 2rem;
        }

        /* No rodapé a placa fica mais para dentro, senão o botão do WhatsApp
           (fixo no canto da tela) passa por cima dela no fim da página */
        .faixa .caixa--placa::after {
            right: 7rem;
        }

        /* Mais para dentro, a placa ficava na altura da linha de copyright e o poste dela
           riscava o texto (de ~800 a ~1366px). Ela sobe ~126px a partir da borda de baixo
           da caixa: com 9rem (144px) de espaço embaixo, fica toda abaixo do texto. */
        @media (min-width: 769px) {
            .faixa .caixa--placa {
                padding-bottom: 9rem;
            }
        }

        @media (max-width: 768px) {
            .boas-vindas h1 {
                font-size: clamp(1.7rem, 8vw, 2.25rem);
            }

            .faixa .caixa--placa::after {
                right: 5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Skip Link para acessibilidade -->
    <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
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
            <nav class="site-nav" aria-label="Menu principal">
                <ul class="site-nav__menu">
                    <li><a href="index.php" class="is-active" aria-current="page">Início</a></li>
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

        <!-- Topo: fotos em tela cheia que se alternam -->
        <section class="hero hero--galeria" aria-roledescription="carrossel" aria-label="Fotos das nossas pizzas">
            <div class="hero__slides">
                <div class="hero__slide is-active" role="img" aria-label="Pizza margherita com manjericão fresco"
                     style="background-image: url('https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=1600&q=80&auto=format&fit=crop');"></div>
                <div class="hero__slide" role="img" aria-label="Pizza saindo do forno"
                     data-bg="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1600&q=80&auto=format&fit=crop"></div>
                <div class="hero__slide" role="img" aria-label="Pizza de pepperoni"
                     data-bg="https://images.unsplash.com/photo-1628840042765-356cda07504e?w=1600&q=80&auto=format&fit=crop"></div>
                <div class="hero__slide" role="img" aria-label="Pizza coberta de queijo e tomate"
                     data-bg="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=1600&q=80&auto=format&fit=crop"></div>
            </div>
            <button type="button" class="hero__seta hero__seta--anterior" aria-label="Foto anterior">
                <i class="fas fa-angle-left" aria-hidden="true"></i>
            </button>
            <button type="button" class="hero__seta hero__seta--proxima" aria-label="Próxima foto">
                <i class="fas fa-angle-right" aria-hidden="true"></i>
            </button>
            <button type="button" class="hero__pausa" aria-label="Pausar a troca de fotos" aria-pressed="false">
                <i class="fas fa-pause" aria-hidden="true"></i>
            </button>
        </section>

        <!-- Boas-vindas -->
        <section class="container boas-vindas revealable" aria-labelledby="hero-title">
            <h1 id="hero-title">A Melhor Pizza de Campo Grande - MS</h1>
            <p class="h3">Ingredientes frescos, massa artesanal e entrega rápida</p>
            <p>Peça agora mesmo!</p>
        </section>

        <!-- Características: cartão inclinado + foto, sobre o xadrez -->
        <section class="split revealable" aria-labelledby="features-title">
            <div class="split__cartao">
                <div class="split__cartao-interno">
                    <h2 id="features-title">Por que escolher a gente?</h2>
                    <p>Qualidade e sabor que fazem a diferença</p>
                    <ul class="lista-vantagens">
                        <li class="feature-item">
                            <h3>Ingredientes Frescos</h3>
                            <p>Selecionamos os melhores ingredientes para sua pizza</p>
                        </li>
                        <li class="feature-item">
                            <h3>Entrega Rápida</h3>
                            <p>Entregamos com agilidade e qualidade</p>
                        </li>
                        <li class="feature-item">
                            <h3>Qualidade Garantida</h3>
                            <p>Mais de 10 anos de experiência</p>
                        </li>
                        <li class="feature-item">
                            <h3>Horário Especial</h3>
                            <p>Atendemos até meia-noite</p>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="split__foto" role="img" aria-label="Pizza recém-saída do forno"
                 style="background-image: url('https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1400&q=80&auto=format&fit=crop');"></div>
        </section>

        <!-- Destaques -->
        <section class="faixa faixa--creme" aria-labelledby="destaques-title">
            <div class="container mais-pedidas revealable">
                <h2 id="destaques-title">Pizzas Mais Pedidas</h2>
                <p>Os sabores que fazem sucesso entre nossos clientes</p>
                <ul class="polaroids">
                    <?php
                    // Buscar produtos em destaque (mais recentes)
                    $stmt = $pdo->query("SELECT * FROM produtos WHERE disponivel = true ORDER BY id DESC LIMIT 3");
                    $destaques = $stmt->fetchAll();
                    foreach($destaques as $destaque):
                    ?>
                    <li class="polaroid">
                        <?php /* Sem aria-label no link: ele trocava todo o conteúdo do cartão e o leitor
                                 de tela não lia a descrição nem o preço. O nome do link agora é o texto
                                 do cartão, com "Ver no cardápio:" só para o leitor de tela. A foto tem
                                 alt vazio porque o nome já está no título logo abaixo (senão seria lido
                                 duas vezes). */ ?>
                        <a href="cardapio.php" class="polaroid__moldura destaque-card">
                            <span class="sr-only">Ver no cardápio:</span>
                            <img src="<?= e($destaque['imagem_url']) ?>" alt="" class="polaroid__foto" loading="lazy" onerror="this.onerror=null;this.src='assets/img/sem-foto.svg'">
                            <h3 class="polaroid__titulo"><?= e($destaque['nome']) ?></h3>
                            <p class="polaroid__texto"><?= e(mb_substr($destaque['descricao'], 0, 80)) ?>...</p>
                            <p class="polaroid__preco">
                                R$ <?php echo number_format($destaque['preco'], 2, ',', '.'); ?>
                            </p>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="cardapio.php" class="btn" aria-label="Ver cardápio completo">Ver Cardápio</a>
            </div>
        </section>

        <!-- Chamada para pedir -->
        <section class="tout revealable" aria-labelledby="pedir-title"
                 style="background-image: url('https://images.unsplash.com/photo-1594007654729-407eedc4be65?w=1600&q=80&auto=format&fit=crop');">
            <div class="tout__cartao">
                <h2 id="pedir-title">Peça Online</h2>
                <p>Bateu a fome? Pizza é sempre uma boa ideia.</p>
                <a href="cardapio.php" class="btn">Peça agora</a>
            </div>
        </section>

        <!-- Rodapé: endereço, horário e pagamento. Fica dentro do <main> (a faixa xadrez
             vai até a borda da tela passando por baixo do menu), então é um rodapé de seção:
             sem role="contentinfo", que só vale para o rodapé de fora de todas as regiões -->
        <footer class="faixa faixa--xadrez">
            <div class="caixa caixa--placa revealable">
                <h2>Pizzaria do Bairro</h2>
                <p>A melhor pizza de Campo Grande - MS, feita com amor e ingredientes frescos.</p>
                <div class="info-colunas mt-2">
                    <div class="footer-section">
                        <h3>Endereço</h3>
                        <p><?= e(PIZZARIA_ENDERECO) ?></p>
                        <p><i class="fas fa-phone" aria-hidden="true"></i> (67) 99999-9999</p>
                        <p><i class="fas fa-envelope" aria-hidden="true"></i> contato@pizzariadobairro.com</p>
                    </div>
                    <div class="footer-section">
                        <h3>Horário de Funcionamento</h3>
                        <p>Terça a Domingo: 18h às 23h</p>
                        <p>Segunda: Fechado</p>
                    </div>
                    <div class="footer-section">
                        <h3>Formas de Pagamento</h3>
                        <p>Dinheiro, Cartão, PIX</p>
                        <p>Aceitamos todas as bandeiras</p>
                    </div>
                </div>
                <p class="rodape-linha">&copy; 2024 Pizzaria do Bairro - Todos os direitos reservados &middot; Campo Grande - MS</p>
            </div>
        </footer>
    </main>

    <!-- Botão WhatsApp Flutuante -->
    <a href="https://wa.me/5567999999999?text=Olá! Gostaria de fazer um pedido!"
       class="whatsapp-float"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="Fale conosco no WhatsApp">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
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
        // (links "#" não trocam de página, então não mostram o loading)
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('javascript:') && !this.target && !this.classList.contains('whatsapp-float') && this.getAttribute('href').charAt(0) !== '#') {
                    showLoading();
                }
            });
        });

        // Esconder loading quando a página carregar
        window.addEventListener('load', () => {
            hideLoading();
            announce('Página inicial carregada. Pizzaria do Bairro, a melhor pizza de Campo Grande.');
        });

        // Voltou pelo botão "voltar" do navegador: a página vem da memória e o
        // evento "load" não roda de novo, então escondemos o loading aqui também
        window.addEventListener('pageshow', hideLoading);
    </script>
</body>
</html>
