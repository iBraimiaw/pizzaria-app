<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Buscar apenas pizzas salgadas para os sabores
$stmt = $pdo->prepare("
    SELECT p.* FROM produtos p 
    JOIN categorias c ON p.categoria_id = c.id 
    WHERE c.nome = 'Pizzas Salgadas' AND p.disponivel = true
    ORDER BY p.nome
");
$stmt->execute();
$pizzas = $stmt->fetchAll();

$sabor1_id = isset($_GET['sabor1']) ? $_GET['sabor1'] : null;
$sabor2_id = isset($_GET['sabor2']) ? $_GET['sabor2'] : null;
$preco_calculado = 0;

if($sabor1_id && $sabor2_id) {
    // Buscar preços dos sabores
    $stmt = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE id IN (?, ?)");
    $stmt->execute([$sabor1_id, $sabor2_id]);
    $sabores = $stmt->fetchAll();
    
    if(count($sabores) == 2) {
        // Calcular média dos preços
        $preco_calculado = ($sabores[0]['preco'] + $sabores[1]['preco']) / 2;
    }
}

// Adicionar ao carrinho
if(isset($_POST['adicionar_carrinho'])) {
    $sabor1 = $_POST['sabor1'];
    $sabor2 = $_POST['sabor2'];
    $quantidade = $_POST['quantidade'];
    $preco_total = $_POST['preco_total'];
    
    // Buscar nomes dos sabores
    $stmt = $pdo->prepare("SELECT nome FROM produtos WHERE id = ?");
    $stmt->execute([$sabor1]);
    $nome1 = $stmt->fetch()['nome'];
    
    $stmt->execute([$sabor2]);
    $nome2 = $stmt->fetch()['nome'];
    
    $nome_personalizado = "🍕 Pizza Meia a Meia: $nome1 + $nome2";
    
    // Salvar na sessão com informações especiais
    $item_carrinho = [
        'tipo' => 'personalizada',
        'sabor1_id' => $sabor1,
        'sabor2_id' => $sabor2,
        'sabor1_nome' => $nome1,
        'sabor2_nome' => $nome2,
        'nome' => $nome_personalizado,
        'preco' => $preco_total,
        'quantidade' => $quantidade
    ];
    
    if (!isset($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }
    
    $_SESSION['carrinho'][] = $item_carrinho;
    
    header('Location: carrinho.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizza Dois Sabores - Pizzaria do Bairro</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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

        .main-content {
            margin-top: 100px;
            padding: 20px;
        }

        .card-duplo {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            margin: 0 auto;
        }

        .card-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .card-body {
            padding: 30px;
        }

        .pizza-visual {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            position: relative;
        }

        .pizza-circle {
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: conic-gradient(from 0deg, #f39c12 0deg 180deg, #e74c3c 180deg 360deg);
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pizza-label {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            white-space: nowrap;
        }

        .label-left {
            left: -100px;
        }

        .label-right {
            right: -100px;
        }

        .selecao-sabores {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .sabor-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .sabor-card.selected {
            border-color: #e74c3c;
            background: #fff5f0;
            box-shadow: 0 5px 15px rgba(231,76,60,0.2);
        }

        .sabor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .sabor-nome {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .sabor-preco {
            color: #e74c3c;
            font-weight: bold;
            font-size: 20px;
        }

        .info-preco {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .preco-calculado {
            font-size: 36px;
            font-weight: bold;
            color: #27ae60;
        }

        .btn-adicionar {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .btn-adicionar:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        .btn-adicionar:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .quantidade {
            margin: 20px 0;
            text-align: center;
        }

        .quantidade input {
            width: 80px;
            padding: 10px;
            text-align: center;
            font-size: 18px;
            border: 2px solid #ddd;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .selecao-sabores {
                grid-template-columns: 1fr;
            }
            
            .pizza-visual {
                flex-direction: column;
                align-items: center;
            }
            
            .label-left, .label-right {
                position: static;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">🍕 Pizzaria do <span>Bairro</span></div>
            <ul class="nav-links">
                <li><a href="index.php">Início</a></li>
                <li><a href="cardapio.php">Cardápio</a></li>
                <li><a href="meus-pedidos.php">Meus Pedidos</a></li>
                <li><a href="carrinho.php">🛒 Carrinho</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="card-duplo">
                <div class="card-header">
                    <h1><i class="fas fa-pizza-slice"></i> Pizza Dois Sabores</h1>
                    <p>Escolha dois sabores e monte sua pizza perfeita!</p>
                </div>
                <div class="card-body">
                    <div class="pizza-visual">
                        <div class="pizza-circle"></div>
                        <div class="pizza-label label-left" id="labelSabor1">
                            <?php echo $sabor1_id ? htmlspecialchars($pizzas[array_search($sabor1_id, array_column($pizzas, 'id'))]['nome'] ?? 'Sabor 1') : '???'; ?>
                        </div>
                        <div class="pizza-label label-right" id="labelSabor2">
                            <?php echo $sabor2_id ? htmlspecialchars($pizzas[array_search($sabor2_id, array_column($pizzas, 'id'))]['nome'] ?? 'Sabor 2') : '???'; ?>
                        </div>
                    </div>

                    <form method="GET" id="formSabores">
                        <div class="selecao-sabores">
                            <div>
                                <h3><i class="fas fa-arrow-left"></i> Primeiro Sabor</h3>
                                <div id="sabores1">
                                    <?php foreach($pizzas as $pizza): ?>
                                        <div class="sabor-card <?php echo $sabor1_id == $pizza['id'] ? 'selected' : ''; ?>" 
                                             onclick="selecionarSabor(1, <?php echo $pizza['id']; ?>, '<?php echo addslashes($pizza['nome']); ?>')">
                                            <div class="sabor-nome"><?php echo $pizza['nome']; ?></div>
                                            <div class="sabor-preco">R$ <?php echo number_format($pizza['preco'], 2, ',', '.'); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <h3>Segundo Sabor <i class="fas fa-arrow-right"></i></h3>
                                <div id="sabores2">
                                    <?php foreach($pizzas as $pizza): ?>
                                        <div class="sabor-card <?php echo $sabor2_id == $pizza['id'] ? 'selected' : ''; ?>" 
                                             onclick="selecionarSabor(2, <?php echo $pizza['id']; ?>, '<?php echo addslashes($pizza['nome']); ?>')">
                                            <div class="sabor-nome"><?php echo $pizza['nome']; ?></div>
                                            <div class="sabor-preco">R$ <?php echo number_format($pizza['preco'], 2, ',', '.'); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="sabor1" id="sabor1" value="<?php echo $sabor1_id; ?>">
                        <input type="hidden" name="sabor2" id="sabor2" value="<?php echo $sabor2_id; ?>">
                    </form>

                    <?php if($sabor1_id && $sabor2_id): ?>
                        <?php if($sabor1_id == $sabor2_id): ?>
                            <div class="info-preco" style="background: #fee; color: #e74c3c;">
                                ⚠️ Por favor, escolha dois sabores DIFERENTES!
                            </div>
                        <?php else: ?>
                            <div class="info-preco">
                                <p><strong>Preço Original:</strong> R$ <?php 
                                    $preco1 = $pizzas[array_search($sabor1_id, array_column($pizzas, 'id'))]['preco'];
                                    $preco2 = $pizzas[array_search($sabor2_id, array_column($pizzas, 'id'))]['preco'];
                                    echo number_format($preco1, 2, ',', '.') . ' + ' . number_format($preco2, 2, ',', '.');
                                ?></p>
                                <p><strong>Você paga apenas a MÉDIA:</strong></p>
                                <div class="preco-calculado">
                                    R$ <?php echo number_format($preco_calculado, 2, ',', '.'); ?>
                                </div>
                                <small><i class="fas fa-info-circle"></i> Promoção: Pizza dois sabores pelo preço médio!</small>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="sabor1" value="<?php echo $sabor1_id; ?>">
                                <input type="hidden" name="sabor2" value="<?php echo $sabor2_id; ?>">
                                <input type="hidden" name="preco_total" value="<?php echo $preco_calculado; ?>">
                                
                                <div class="quantidade">
                                    <label>Quantidade:</label>
                                    <input type="number" name="quantidade" value="1" min="1" max="10">
                                </div>
                                
                                <button type="submit" name="adicionar_carrinho" class="btn-adicionar">
                                    <i class="fas fa-cart-plus"></i> Adicionar ao Carrinho - R$ <?php echo number_format($preco_calculado, 2, ',', '.'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="info-preco">
                            <i class="fas fa-hand-pointer"></i> Clique em um sabor de cada lado para montar sua pizza!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function selecionarSabor(lado, id, nome) {
            if(lado === 1) {
                document.getElementById('sabor1').value = id;
                document.getElementById('labelSabor1').textContent = nome;
                // Remover seleção anterior
                document.querySelectorAll('#sabores1 .sabor-card').forEach(card => {
                    card.classList.remove('selected');
                });
                // Adicionar seleção no clicado
                event.currentTarget.classList.add('selected');
            } else {
                document.getElementById('sabor2').value = id;
                document.getElementById('labelSabor2').textContent = nome;
                // Remover seleção anterior
                document.querySelectorAll('#sabores2 .sabor-card').forEach(card => {
                    card.classList.remove('selected');
                });
                // Adicionar seleção no clicado
                event.currentTarget.classList.add('selected');
            }
            
            // Submeter o formulário para atualizar o preço
            document.getElementById('formSabores').submit();
        }
    </script>
</body>
</html>