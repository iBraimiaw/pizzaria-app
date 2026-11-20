<?php
require_once __DIR__ . '/../includes/functions.php';

if(!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Atualizar status do pedido
if(isset($_POST['atualizar_status'])) {
    $pedido_id = $_POST['pedido_id'];
    $novo_status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
    $stmt->execute([$novo_status, $pedido_id]);
    
    header('Location: gerenciar-pedidos.php');
    exit;
}

// Buscar pedidos com filtros
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$data_filter = isset($_GET['data']) ? $_GET['data'] : '';

$sql = "SELECT p.*, u.nome as cliente_nome, u.telefone 
        FROM pedidos p 
        JOIN usuarios u ON p.usuario_id = u.id 
        WHERE 1=1";

$params = [];

if($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if($data_filter) {
    $sql .= " AND DATE(p.data_pedido) = ?";
    $params[] = $data_filter;
}

$sql .= " ORDER BY p.data_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Buscar detalhes do pedido (incluindo nome correto para pizza personalizada)
$detalhes_pedido = null;
$itens_pedido = null;
if(isset($_GET['detalhes'])) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$_GET['detalhes']]);
    $detalhes_pedido = $stmt->fetch();
    
    // Query corrigida para exibir nome da pizza de dois sabores
    $stmt = $pdo->prepare("
        SELECT 
            ip.*,
            CASE 
                WHEN ip.is_personalizada = TRUE THEN 
                    (SELECT CONCAT('🍕 Pizza Meia a Meia: ', pr1.nome, ' + ', pr2.nome)
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
    $stmt->execute([$_GET['detalhes']]);
    $itens_pedido = $stmt->fetchAll();
}

$status_list = ['PENDENTE', 'PREPARANDO', 'SAIU PARA ENTREGA', 'ENTREGUE', 'CANCELADO'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gerenciar Pedidos | Pizzaria do Bairro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f0f2f5;
        }
        /* === ESTILO DO HEADER (MESMO DAS ABAS CUPONS/PRODUTOS) === */
        .header {
            background: #2c3e50;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .logo, .logo span {
            color: white !important;
        }
        .nav-links a {
            color: white !important;
            font-weight: 500;
        }
        .nav-links a:hover {
            color: #f39c12 !important;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            max-width: 1400px;
            margin: 0 auto;
        }
        .nav-links {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        .nav-links a {
            text-decoration: none;
        }
        /* === FIM DO ESTILO DO HEADER === */
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .main-content {
            padding: 20px;
        }
        .filtros {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .pedido-detalhes {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pendente { background: #ffc107; color: #000; }
        .status-preparando { background: #17a2b8; color: #fff; }
        .status-saiu_para_entrega { background: #007bff; color: #fff; }
        .status-entregue { background: #28a745; color: #fff; }
        .status-cancelado { background: #dc3545; color: #fff; }
        .table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow-x: auto;
            display: block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-primary {
            background: #3498db;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
        }
        .status-select {
            padding: 6px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .form-group {
            display: inline-block;
            margin-right: 15px;
        }
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 15px; }
            .form-group { display: block; margin-bottom: 10px; }
            .table { font-size: 12px; }
            .table th, .table td { padding: 8px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">👑 Admin - Pizzaria do <span>Bairro</span></div>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="gerenciar-produtos.php">Produtos</a></li>
                <li><a href="gerenciar-pedidos.php">Pedidos</a></li>
                <li><a href="gerenciar-cupons.php">Cupons</a></li>
                <li><a href="../index.php">Site</a></li>
                <li><a href="../logout.php">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1><i class="fas fa-clipboard-list"></i> Gerenciar Pedidos</h1>
            
            <div class="filtros">
                <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" class="status-select">
                            <option value="">Todos</option>
                            <?php foreach($status_list as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $status_filter == $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Data:</label>
                        <input type="date" name="data" value="<?php echo $data_filter; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="gerenciar-pedidos.php" class="btn">Limpar</a>
                    </div>
                </form>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Telefone</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Distância</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pedidos as $pedido): ?>
                        <tr>
                            <td>#<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($pedido['cliente_nome']); ?></td>
                            <td><?php echo htmlspecialchars($pedido['telefone']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                            <td>R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></td>
                            <td><?php echo $pedido['distancia_km']; ?> km</td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <?php foreach($status_list as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $pedido['status'] == $status ? 'selected' : ''; ?>>
                                                <?php echo $status; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="atualizar_status" value="1">
                                </form>
                            </td>
                            <td>
                                <a href="?detalhes=<?php echo $pedido['id']; ?>" class="btn btn-primary btn-sm">Detalhes</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($pedidos)): ?>
                        <tr><td colspan="8" style="text-align: center;">Nenhum pedido encontrado</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($detalhes_pedido && $itens_pedido): ?>
            <div class="pedido-detalhes">
                <h2>Detalhes do Pedido #<?php echo str_pad($detalhes_pedido['id'], 6, '0', STR_PAD_LEFT); ?></h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h3>Informações do Cliente</h3>
                        <?php 
                        $stmtCliente = $pdo->prepare("SELECT nome, telefone, endereco FROM usuarios WHERE id = ?");
                        $stmtCliente->execute([$detalhes_pedido['usuario_id']]);
                        $cliente = $stmtCliente->fetch();
                        ?>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['nome'] ?? 'N/A'); ?></p>
                        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($cliente['telefone'] ?? 'N/A'); ?></p>
                        <p><strong>Endereço:</strong> <?php echo htmlspecialchars($detalhes_pedido['endereco_entrega']); ?></p>
                    </div>
                    <div>
                        <h3>Informações do Pedido</h3>
                        <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($detalhes_pedido['data_pedido'])); ?></p>
                        <p><strong>Distância:</strong> <?php echo $detalhes_pedido['distancia_km']; ?> km</p>
                        <p><strong>Taxa entrega:</strong> R$ <?php echo number_format($detalhes_pedido['taxa_entrega'], 2, ',', '.'); ?></p>
                        <p><strong>Pagamento:</strong> <?php echo $detalhes_pedido['forma_pagamento']; ?></p>
                        <?php if($detalhes_pedido['troco_para']): ?>
                            <p><strong>Troco para:</strong> R$ <?php echo number_format($detalhes_pedido['troco_para'], 2, ',', '.'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h3>Itens do Pedido</h3>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr><th>Produto</th><th>Quantidade</th><th>Preço Unit.</th><th>Subtotal</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($itens_pedido as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['produto_nome']); ?></td>
                                <td><?php echo $item['quantidade']; ?></td>
                                <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="3" style="text-align: right;">Total: </td>
                                <td>R$ <?php echo number_format($detalhes_pedido['total'], 2, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div style="margin-top: 20px;">
                    <a href="gerenciar-pedidos.php" class="btn btn-primary">Voltar</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>