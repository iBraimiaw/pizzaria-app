<?php
require_once __DIR__ . '/../includes/functions.php';

if(!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Estatísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = CURRENT_DATE");
$pedidos_hoje = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE DATE(data_pedido) = CURRENT_DATE");
$faturamento_hoje = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'CLIENTE'");
$total_clientes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos WHERE disponivel = true");
$total_produtos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'PENDENTE'");
$pedidos_pendentes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM pedidos GROUP BY status");
$pedidos_status = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT p.*, u.nome as cliente_nome 
    FROM pedidos p 
    JOIN usuarios u ON p.usuario_id = u.id 
    ORDER BY p.data_pedido DESC 
    LIMIT 5
");
$ultimos_pedidos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard | Pizzaria do Bairro</title>
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
        /* === ESTILO DO HEADER (MESMO DA ABA CUPONS) === */
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
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #e74c3c;
        }
        .stat-label {
            color: #666;
            margin-top: 10px;
        }
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .recent-orders {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
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
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
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
            <h1>📊 Dashboard Administrativo</h1>
            <p>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</p>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-number"><?php echo $pedidos_hoje; ?></div>
                    <div class="stat-label">Pedidos Hoje</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">R$ <?php echo number_format($faturamento_hoje, 2, ',', '.'); ?></div>
                    <div class="stat-label">Faturamento Hoje</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $total_clientes; ?></div>
                    <div class="stat-label">Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🍕</div>
                    <div class="stat-number"><?php echo $total_produtos; ?></div>
                    <div class="stat-label">Produtos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?php echo $pedidos_pendentes; ?></div>
                    <div class="stat-label">Pedidos Pendentes</div>
                </div>
            </div>
            
            <div class="chart-container">
                <h3>📈 Pedidos por Status</h3>
                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
            
            <div class="recent-orders">
                <h3>📋 Últimos Pedidos</h3>
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Cliente</th><th>Data</th><th>Total</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimos_pedidos as $pedido): ?>
                        <tr>
                            <td>#<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $pedido['cliente_nome']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                            <td>R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></td>
                            <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $pedido['status'])); ?>"><?php echo $pedido['status']; ?></span></td>
                            <td><a href="gerenciar-pedidos.php?detalhes=<?php echo $pedido['id']; ?>" class="btn">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php 
            $labels = []; $data = [];
            foreach($pedidos_status as $s) { $labels[] = $s['status']; $data[] = $s['total']; }
            echo json_encode(['labels' => $labels, 'data' => $data]);
        ?>;
        new Chart(ctx, {
            type: 'bar',
            data: { labels: statusData.labels, datasets: [{ label: 'Quantidade de Pedidos', data: statusData.data, backgroundColor: '#e74c3c' }] },
            options: { scales: { y: { beginAtZero: true, stepSize: 1 } } }
        });
    </script>
</body>
</html>