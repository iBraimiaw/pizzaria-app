<?php
require_once 'includes/functions.php';

$categoria_id = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
        JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.disponivel = true";

if($categoria_id) {
    $sql .= " AND p.categoria_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria_id]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$produtos = $stmt->fetchAll();

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardapio - Pizzaria do Bairro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFF5F0 0%, #FFE8E0 100%);
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
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
        }

        .logo span {
            color: #e74c3c;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            transition: color 0.3s;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #e74c3c;
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

        .btn-carrinho {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white !important;
            padding: 10px 20px !important;
            border-radius: 25px !important;
        }

        .main-content {
            margin-top: 80px;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .btn-duplo-sabor {
            display: block;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            text-align: center;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 30px;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }

        .btn-duplo-sabor:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        }

        .btn-duplo-sabor i {
            margin-right: 10px;
            font-size: 24px;
        }

        .btn-duplo-sabor small {
            font-size: 12px;
            display: block;
            margin-top: 5px;
            opacity: 0.9;
        }

        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .produto-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .imagem-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }

        .produto-imagem {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .produto-card:hover .produto-imagem {
            transform: scale(1.1);
        }

        .badge-categoria {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }

        .produto-info {
            padding: 15px;
        }

        .produto-nome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        .produto-descricao {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .produto-preco {
            font-size: 22px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .btn-adicionar {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-adicionar:hover {
            background: #c0392b;
        }

        .quantidade-input {
            width: 60px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }

        .form-carrinho {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filtros {
            text-align: center;
            margin: 30px 0;
        }

        .btn-filtro {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-filtro.active {
            background: #e74c3c;
            color: white;
        }

        .btn-filtro:hover {
            background: #c0392b;
            color: white;
        }

        h1 {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }

        .subtitulo {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }

        .promo-banner {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            text-align: center;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .promo-banner i {
            font-size: 24px;
            margin-right: 10px;
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
            
            .produtos-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .btn-duplo-sabor {
                font-size: 14px;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">Pizzaria do <span>Bairro</span></div>
            <ul class="nav-links">
                <li><a href="index.php">Inicio</a></li>
                <li><a href="cardapio.php">Cardapio</a></li>
                <?php if(isset($_SESSION['usuario_id'])): ?>
                    <li><a href="meus-pedidos.php">Meus Pedidos</a></li>
                    <li><a href="carrinho.php" class="btn-carrinho">
                        Carrinho
                    </a></li>
                    <?php if(isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'ADMIN'): ?>
                        <li><a href="admin/">Admin</a></li>
                    <?php endif; ?>
                    <li class="user-info">
                        <span><?= e($_SESSION['usuario_nome']) ?></span>
                        <a href="logout.php" class="logout-btn">Sair</a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php">Entrar</a></li>
                    <li><a href="cadastro.php">Cadastrar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>Nosso Cardapio</h1>
            <p class="subtitulo">As melhores pizzas de Campo Grande - MS</p>
            
            <div class="promo-banner">
                <strong>PROMOCAO ESPECIAL!</strong> Pizza Dois Sabores pelo PRECO MEDIO! Escolha dois sabores e pague a media!
            </div>
            
            <a href="pizza-dupla.php" class="btn-duplo-sabor">
                MONTE SUA PIZZA COM DOIS SABORES!
                <small>Escolha dois sabores diferentes e pague apenas a media dos precos!</small>
            </a>
            
            <div class="filtros">
                <a href="cardapio.php" class="btn-filtro <?php echo !$categoria_id ? 'active' : ''; ?>">Todos</a>
                <?php foreach($categorias as $cat): ?>
                    <a href="?categoria=<?= (int)$cat['id'] ?>" class="btn-filtro <?php echo $categoria_id == $cat['id'] ? 'active' : ''; ?>">
                        <?= e($cat['nome']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="produtos-grid">
                <?php foreach($produtos as $produto): ?>
                    <div class="produto-card">
                        <div class="imagem-container">
                            <img src="<?= e(url_segura($produto['imagem_url'])) ?>" 
                                 alt="<?= e($produto['nome']) ?>"
                                 class="produto-imagem"
                                 loading="lazy"
                                 onerror="this.src='https://via.placeholder.com/400x300/FF6B6B/FFFFFF?text=Pizza'">
                            <span class="badge-categoria"><?= e($produto['categoria_nome']) ?></span>
                        </div>
                        <div class="produto-info">
                            <div class="produto-nome"><?= e($produto['nome']) ?></div>
                            <div class="produto-descricao"><?= e($produto['descricao']) ?></div>
                            <div class="produto-preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                            
                            <?php if(isset($_SESSION['usuario_id'])): ?>
                                <form method="POST" action="carrinho.php" class="form-carrinho">
                                    <input type="hidden" name="produto_id" value="<?= (int)$produto['id'] ?>">
                                    <input type="number" name="quantidade" value="1" min="1" class="quantidade-input">
                                    <button type="submit" name="add_carrinho" class="btn-adicionar">
                                        Adicionar
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="login.php" class="btn-adicionar" style="display: block; text-align: center; text-decoration: none;">
                                    Fazer login para pedir
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>