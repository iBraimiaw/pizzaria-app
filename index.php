<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pizzaria do Bairro - A melhor pizza de Campo Grande MS">
    <title>Pizzaria do Bairro - A melhor pizza de Campo Grande MS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/acessibilidade.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos existentes... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFF5F0 0%, #FFE8E0 100%);
            overflow-x: hidden;
        }

        /* Header Moderno - CORRIGIDO para não cortar */
        .header-moderno {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar-moderno {
            display: flex;
            flex-wrap: wrap;          /* Permite quebra de linha */
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-moderno {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;          /* Evita que o logo encolha demais */
        }

        .logo-icon {
            font-size: 35px;
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo-texto {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #E63946 0%, #F4A261 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-sub {
            font-size: 12px;
            color: #666;
            letter-spacing: 2px;
        }

        .nav-links-moderno {
            display: flex;
            flex-wrap: wrap;          /* Links quebram para a linha seguinte */
            justify-content: center;
            align-items: center;
            gap: 20px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links-moderno a {
            text-decoration: none;
            color: #2C3E50;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            white-space: nowrap;      /* Evita que o texto do link quebre internamente */
        }

        .nav-links-moderno a:before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #E63946, #F4A261);
            transition: width 0.3s;
        }

        .nav-links-moderno a:hover:before {
            width: 100%;
        }

        .nav-links-moderno a:focus-visible {
            outline: 3px solid #E63946;
            outline-offset: 3px;
            border-radius: 5px;
        }

        /* Menu do usuário logado - corrigido */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #E63946, #F4A261);
            padding: 6px 15px;
            border-radius: 50px;
            color: white;
            flex-wrap: wrap;
            justify-content: center;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #E63946;
            font-weight: bold;
            font-size: 18px;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-name i {
            margin-right: 5px;
        }

        .btn-carrinho {
            background: linear-gradient(135deg, #E63946, #F4A261);
            color: white !important;
            padding: 8px 18px !important;
            border-radius: 25px !important;
            white-space: nowrap;
        }

        .btn-carrinho:before {
            display: none;
        }

        /* Hero Section */
        .hero {
            margin-top: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
            padding: 80px 5%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            animation: fadeInUp 1s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-hero {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: #E63946;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            animation: fadeInUp 1.2s ease;
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .btn-hero:focus-visible {
            outline: 3px solid #fff;
            outline-offset: 3px;
        }

        /* Seções */
        .section {
            padding: 80px 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(135deg, #E63946, #F4A261);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        /* Cards de Destaque */
        .destaques-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .destaque-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .destaque-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .destaque-card:focus-visible {
            outline: 3px solid #E63946;
            outline-offset: 3px;
        }

        .destaque-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .destaque-card:hover .destaque-img {
            transform: scale(1.05);
        }

        .destaque-info {
            padding: 20px;
        }

        .destaque-info h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2C3E50;
        }

        .destaque-preco {
            font-size: 28px;
            font-weight: 700;
            color: #E63946;
            margin: 15px 0;
        }

        /* Características */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .feature-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .feature-item:focus-visible {
            outline: 3px solid #E63946;
            outline-offset: 3px;
        }

        .feature-icon {
            font-size: 50px;
            background: linear-gradient(135deg, #E63946, #F4A261);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        /* Footer */
        .footer {
            background: #1a1a1a;
            color: white;
            padding: 60px 5% 30px;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        .footer-section p {
            color: #999;
            line-height: 1.8;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            color: white;
            font-size: 24px;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: #E63946;
        }

        .social-links a:focus-visible {
            outline: 3px solid #E63946;
            outline-offset: 3px;
            border-radius: 5px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #333;
            color: #999;
        }

        /* Botão flutuante */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #25D366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            z-index: 999;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            color: white;
        }

        .whatsapp-float:focus-visible {
            outline: 3px solid #fff;
            outline-offset: 3px;
        }

        /* Skip Link para acessibilidade */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: #E63946;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            z-index: 10001;
            border-radius: 0 0 5px 0;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10002;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #E63946;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Anúncio para leitores de tela */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        /* RESPONSIVIDADE CORRIGIDA */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }
            
            .navbar-moderno {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
            
            .nav-links-moderno {
                justify-content: center;
                gap: 12px;
            }
            
            .user-menu {
                padding: 5px 12px;
                gap: 8px;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }
            
            .user-name {
                font-size: 12px;
            }
        }

        /* Para telas muito pequenas (celular estreito) */
        @media (max-width: 480px) {
            .nav-links-moderno {
                gap: 8px;
            }
            
            .btn-carrinho {
                padding: 6px 12px !important;
                font-size: 12px;
            }
            
            .logo-texto {
                font-size: 22px;
            }
            
            .logo-icon {
                font-size: 28px;
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

    <!-- Header Moderno -->
    <header class="header-moderno" role="banner">
        <nav class="navbar-moderno" role="navigation" aria-label="Menu principal">
            <div class="logo-moderno">
                <div class="logo-icon" aria-hidden="true">🍕</div>
                <div>
                    <div class="logo-texto">Pizzaria do Bairro</div>
                    <div class="logo-sub">DESDE 2024</div>
                </div>
            </div>
            <ul class="nav-links-moderno" role="menubar">
                <li role="none"><a href="index.php" role="menuitem">Início</a></li>
                <li role="none"><a href="cardapio.php" role="menuitem">Cardápio</a></li>
                <?php if(isLoggedIn()): ?>
                    <li role="none"><a href="meus-pedidos.php" role="menuitem">Meus Pedidos</a></li>
                    <li role="none"><a href="carrinho.php" class="btn-carrinho" role="menuitem" aria-label="Ver carrinho de compras">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i> Carrinho
                    </a></li>
                    <?php if(isAdmin()): ?>
                        <li role="none"><a href="admin/" role="menuitem" aria-label="Painel administrativo"><i class="fas fa-crown" aria-hidden="true"></i> Admin</a></li>
                    <?php endif; ?>
                    <li class="user-menu" role="none">
                        <div class="user-avatar" aria-hidden="true">
                            <?= e(mb_strtoupper(mb_substr($_SESSION['usuario_nome'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                        </div>
                        <div class="user-name">
                            <i class="fas fa-user" aria-hidden="true"></i> <span aria-label="Usuário: <?= e($_SESSION['usuario_nome']) ?>"><?= e($_SESSION['usuario_nome']) ?></span>
                        </div>
                        <a href="logout.php" style="color: white; margin-left: 10px;" aria-label="Sair do sistema">
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li role="none"><a href="login.php" role="menuitem"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Entrar</a></li>
                    <li role="none"><a href="cadastro.php" class="btn-carrinho" role="menuitem">Cadastrar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-content">
            <h1 id="hero-title">🍕 A Melhor Pizza de Campo Grande - MS</h1>
            <p>Ingredientes frescos, massa artesanal e entrega rápida. Peça agora mesmo!</p>
            <a href="cardapio.php" class="btn-hero" aria-label="Ver cardápio completo">
                Ver Cardápio <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <!-- Destaques -->
    <section class="section" aria-labelledby="destaques-title">
        <div class="section-title">
            <h2 id="destaques-title">🍕 Pizzas Mais Pedidas</h2>
            <p>Os sabores que fazem sucesso entre nossos clientes</p>
        </div>
        <div class="destaques-grid">
            <?php
            // Buscar produtos em destaque (mais recentes)
            $stmt = $pdo->query("SELECT * FROM produtos WHERE disponivel = true ORDER BY id DESC LIMIT 3");
            $destaques = $stmt->fetchAll();
            foreach($destaques as $destaque):
            ?>
            <div class="destaque-card" 
                 onclick="window.location.href='cardapio.php'" 
                 role="button" 
                 tabindex="0"
                 aria-label="Ver <?= e($destaque['nome']) ?> no cardápio"
                 onkeypress="if(event.key === 'Enter') window.location.href='cardapio.php'">
                <img src="<?= e(url_segura($destaque['imagem_url'])) ?>" alt="<?= e($destaque['nome']) ?>" class="destaque-img" loading="lazy" onerror="this.src='https://via.placeholder.com/400x300/FF6B6B/FFFFFF?text=Pizza'">
                <div class="destaque-info">
                    <h3><?= e($destaque['nome']) ?></h3>
                    <p><?= e(mb_substr((string)$destaque['descricao'], 0, 80, 'UTF-8')) ?>...</p>
                    <div class="destaque-preco" aria-label="Preço: R$ <?php echo number_format($destaque['preco'], 2, ',', '.'); ?>">
                        R$ <?php echo number_format($destaque['preco'], 2, ',', '.'); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Características -->
    <section class="section" style="background: #f8f9fa; border-radius: 50px; margin: 20px auto;" aria-labelledby="features-title">
        <div class="section-title">
            <h2 id="features-title">⭐ Por que escolher a gente?</h2>
            <p>Qualidade e sabor que fazem a diferença</p>
        </div>
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon" aria-hidden="true"><i class="fas fa-pizza-slice"></i></div>
                <h3>Ingredientes Frescos</h3>
                <p>Selecionamos os melhores ingredientes para sua pizza</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon" aria-hidden="true"><i class="fas fa-motorcycle"></i></div>
                <h3>Entrega Rápida</h3>
                <p>Entregamos com agilidade e qualidade</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon" aria-hidden="true"><i class="fas fa-medal"></i></div>
                <h3>Qualidade Garantida</h3>
                <p>Mais de 10 anos de experiência</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon" aria-hidden="true"><i class="fas fa-clock"></i></div>
                <h3>Horário Especial</h3>
                <p>Atendemos até meia-noite</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" role="contentinfo">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-pizza-slice" aria-hidden="true"></i> Pizzaria do Bairro</h3>
                <p>A melhor pizza de Campo Grande - MS, feita com amor e ingredientes frescos.</p>
                <div class="social-links">
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>📍 Endereço</h3>
                <p><?= e(PIZZARIA_ENDERECO) ?></p>
                <p><i class="fas fa-phone" aria-hidden="true"></i> (67) 99999-9999</p>
                <p><i class="fas fa-envelope" aria-hidden="true"></i> contato@pizzariadobairro.com</p>
            </div>
            <div class="footer-section">
                <h3>🕒 Horário de Funcionamento</h3>
                <p>Terça a Domingo: 18h às 23h</p>
                <p>Segunda: Fechado</p>
            </div>
            <div class="footer-section">
                <h3>💳 Formas de Pagamento</h3>
                <p>Dinheiro, Cartão, PIX</p>
                <p>Aceitamos todas as bandeiras</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Pizzaria do Bairro - Todos os direitos reservados</p>
            <p style="margin-top: 10px;">Campo Grande - MS</p>
        </div>
    </footer>

    <!-- Botão WhatsApp Flutuante -->
    <a href="https://wa.me/5567999999999?text=Olá! Gostaria de fazer um pedido!" 
       class="whatsapp-float" 
       target="_blank" 
       rel="noopener noreferrer"
       aria-label="Fale conosco no WhatsApp">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>

    <script src="assets/js/acessibilidade.js"></script>
    <script>
        // Animação ao scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header-moderno');
            if (window.scrollY > 100) {
                header.style.background = 'white';
                header.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });

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
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('javascript:') && !this.target && !this.classList.contains('whatsapp-float')) {
                    showLoading();
                }
            });
        });
        
        // Esconder loading quando a página carregar
        window.addEventListener('load', () => {
            hideLoading();
            announce('Página inicial carregada. Pizzaria do Bairro, a melhor pizza de Campo Grande.');
        });

        // Navegação por teclado para cards
        document.querySelectorAll('.destaque-card').forEach(card => {
            card.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    window.location.href = 'cardapio.php';
                }
            });
        });
    </script>
</body>
</html>