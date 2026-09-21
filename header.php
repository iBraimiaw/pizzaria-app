<?php
// header.php - Header completo com acessibilidade
// Deve ser incluído em todas as páginas

// Garante o helper de escape mesmo que a página não tenha carregado includes/functions.php
if (!function_exists('e')) {
    function e($valor): string {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pizzaria do Bairro - A melhor pizza de Campo Grande - MS">
    <meta name="theme-color" content="#e74c3c">
    <title><?php echo isset($page_title) ? e($page_title) . ' - ' : ''; ?>Pizzaria do Bairro</title>
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- CSS de Acessibilidade -->
    <link rel="stylesheet" href="assets/css/acessibilidade.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos adicionais do header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo span {
            color: #e74c3c;
        }

        .logo-img {
            font-size: 32px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 20px;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-links li {
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            transition: color 0.3s;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .nav-links a:hover {
            color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }

        .nav-links a:focus-visible {
            outline: 3px solid #e74c3c;
            outline-offset: 2px;
        }

        .user-info {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            padding: 8px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .user-info span {
            font-weight: 600;
        }

        .logout-btn {
            color: white !important;
            background: rgba(255,255,255,0.2);
            padding: 5px 10px !important;
            border-radius: 20px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white !important;
        }

        .btn-carrinho {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white !important;
            padding: 10px 20px !important;
            border-radius: 25px !important;
        }

        .btn-carrinho:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231,76,60,0.3);
        }

        /* Indicador de carregamento */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #e74c3c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Anúncio de leitor de tela */
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

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .user-info {
                padding: 5px 15px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Skip Link para acessibilidade -->
    <a href="#main-content" class="skip-link" aria-label="Pular para o conteúdo principal">
        Pular para o conteúdo principal
    </a>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header Principal -->
    <header class="header" role="banner" aria-label="Cabeçalho principal">
        <nav class="navbar" role="navigation" aria-label="Menu principal">
            <a href="index.php" class="logo" aria-label="Página inicial">
                <span class="logo-img" aria-hidden="true">🍕</span>
                <span>Pizzaria do <span>Bairro</span></span>
            </a>
            
            <ul class="nav-links" role="menubar">
                <li role="none"><a href="index.php" role="menuitem">Início</a></li>
                <li role="none"><a href="cardapio.php" role="menuitem">Cardápio</a></li>
                
                <?php if(isset($_SESSION['usuario_id'])): ?>
                    <li role="none"><a href="meus-pedidos.php" role="menuitem">Meus Pedidos</a></li>
                    <li role="none">
                        <a href="carrinho.php" class="btn-carrinho" role="menuitem" aria-label="Ver carrinho">
                            🛒 Carrinho
                        </a>
                    </li>
                    
                    <?php if(isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'ADMIN'): ?>
                        <li role="none"><a href="admin/" role="menuitem" aria-label="Painel administrativo">👑 Admin</a></li>
                    <?php endif; ?>
                    
                    <li class="user-info" role="none">
                        <span aria-label="Usuário logado: <?= e($_SESSION['usuario_nome']) ?>">
                            👤 <?= e($_SESSION['usuario_nome']) ?>
                        </span>
                        <a href="logout.php" class="logout-btn" role="menuitem" aria-label="Sair do sistema">
                            Sair
                        </a>
                    </li>
                <?php else: ?>
                    <li role="none"><a href="login.php" role="menuitem">Entrar</a></li>
                    <li role="none"><a href="cadastro.php" role="menuitem">Cadastrar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Anúncio para leitores de tela -->
    <div class="sr-only" role="status" aria-live="polite" id="liveStatus"></div>

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
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('javascript:') && !this.target) {
                    showLoading();
                }
            });
        });
        
        // Esconder loading quando a página carregar
        window.addEventListener('load', () => {
            hideLoading();
        });
    </script>