<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Buscar dados do pedido
$stmt = $pdo->prepare("
    SELECT p.*, u.nome as cliente_nome, u.endereco 
    FROM pedidos p 
    JOIN usuarios u ON p.usuario_id = u.id 
    WHERE p.id = ? AND p.usuario_id = ?
");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch();

if(!$pedido) {
    header('Location: meus-pedidos.php');
    exit;
}

// Buscar itens do pedido
$stmt = $pdo->prepare("
    SELECT ip.*, 
           CASE 
               WHEN ip.is_personalizada = TRUE THEN 
                   (SELECT CONCAT(pr1.nome, ' + ', pr2.nome) 
                    FROM pizzas_personalizadas pp 
                    JOIN produtos pr1 ON pp.sabor1_id = pr1.id 
                    JOIN produtos pr2 ON pp.sabor2_id = pr2.id 
                    WHERE pp.id = ip.pizza_personalizada_id)
               ELSE pr.nome
           END as produto_nome
    FROM itens_pedido ip
    LEFT JOIN produtos pr ON ip.produto_id = pr.id
    WHERE ip.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado - Pizzaria do Bairro</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .card-confirmacao {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-top: 100px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .card-header {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .check-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .card-body {
            padding: 30px;
        }

        .pedido-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .itens-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .itens-table th,
        .itens-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .itens-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .total-final {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .btn-primary {
            background: #e74c3c;
            color: white;
        }

        .btn-secondary {
            background: #3498db;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #ffc107;
            color: #000;
        }

        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card-confirmacao">
            <div class="card-header">
                <div class="check-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Pedido Confirmado!</h1>
                <p>Seu pedido foi recebido com sucesso</p>
            </div>
            <div class="card-body">
                <div class="pedido-info">
                    <div class="info-row">
                        <span class="info-label">Número do Pedido:</span>
                        <span class="info-value"><strong>#<?php echo str_pad((int)$pedido['id'], 6, '0', STR_PAD_LEFT); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Data do Pedido:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge">⏳ PENDENTE</span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Cliente:</span>
                        <span class="info-value"><?= e($pedido['cliente_nome']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Endereço de Entrega:</span>
                        <span class="info-value"><?= e($pedido['endereco_entrega']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Forma de Pagamento:</span>
                        <span class="info-value"><?= e($pedido['forma_pagamento']) ?></span>
                    </div>
                    <?php if($pedido['troco_para']): ?>
                    <div class="info-row">
                        <span class="info-label">Troco para:</span>
                        <span class="info-value">R$ <?php echo number_format($pedido['troco_para'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <h3>Itens do Pedido:</h3>
                <table class="itens-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Preço</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($itens as $item): ?>
                        <tr>
                            <td>
                                <?php 
                                if(strpos((string)$item['produto_nome'], '+') !== false): 
                                ?>
                                    <i class="fas fa-pizza-slice"></i> <?= e($item['produto_nome']) ?>
                                <?php else: ?>
                                    <i class="fas fa-utensils"></i> <?= e($item['produto_nome']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$item['quantidade'] ?></td>
                            <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-final">
                    <span>Total do Pedido:</span>
                    <span>R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></span>
                </div>

                <div class="btn-group">
                    <a href="meus-pedidos.php" class="btn btn-primary">
                        <i class="fas fa-receipt"></i> Ver Meus Pedidos
                    </a>
                    <a href="cardapio.php" class="btn btn-secondary">
                        <i class="fas fa-pizza-slice"></i> Fazer Novo Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mostrar notificação de sucesso (se suportado)
        if (Notification.permission === "granted") {
            new Notification("Pedido Confirmado!", {
                body: "Seu pedido foi recebido e está sendo preparado!",
                icon: "/favicon.ico"
            });
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    </script>
</body>
</html>