<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar pedidos do usuário
$stmt = $pdo->prepare("
    SELECT p.*, 
           COUNT(i.id) as total_itens 
    FROM pedidos p 
    LEFT JOIN itens_pedido i ON p.id = i.pedido_id 
    WHERE p.usuario_id = ? 
    GROUP BY p.id 
    ORDER BY p.data_pedido DESC
");
$stmt->execute([$usuario_id]);
$pedidos = $stmt->fetchAll();

// Mapeamento de status
$status_map = [
    'PENDENTE' => ['badge' => 'warning', 'texto' => '⏳ Pendente', 'cor' => '#ffc107'],
    'PREPARANDO' => ['badge' => 'info', 'texto' => '🍕 Preparando', 'cor' => '#17a2b8'],
    'SAIU PARA ENTREGA' => ['badge' => 'primary', 'texto' => '🚚 Saiu para entrega', 'cor' => '#007bff'],
    'ENTREGUE' => ['badge' => 'success', 'texto' => '✅ Entregue', 'cor' => '#28a745'],
    'CANCELADO' => ['badge' => 'danger', 'texto' => '❌ Cancelado', 'cor' => '#dc3545']
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - Pizzaria do Bairro</title>
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

        .main-content {
            margin-top: 80px;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            margin-bottom: 30px;
            color: #333;
        }

        .pedido-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .pedido-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .pedido-numero {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
        }

        .pedido-data {
            color: #666;
            font-size: 14px;
        }

        .pedido-total {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
        }

        .pedido-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 14px;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-detalhes {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-detalhes:hover {
            background: #2980b9;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #e74c3c;
        }

        .progresso-pedido {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .progresso-passos {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            position: relative;
        }

        .passo {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .passo .circulo {
            width: 40px;
            height: 40px;
            background: #ddd;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
        }

        .passo.ativo .circulo {
            background: #e74c3c;
            color: white;
            animation: pulse 1s infinite;
        }

        .passo.completo .circulo {
            background: #27ae60;
            color: white;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .passo .linha {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 3px;
            background: #ddd;
            z-index: -1;
        }

        .passo:last-child .linha {
            display: none;
        }

        .passo.completo .linha,
        .passo.ativo .linha {
            background: #27ae60;
        }

        .passo-texto {
            font-size: 12px;
            font-weight: 500;
        }

        .alert {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
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
            
            .pedido-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .progresso-passos {
                flex-direction: column;
                gap: 15px;
            }
            
            .passo .linha {
                display: none;
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
                <li><a href="carrinho.php">
                    <i class="fas fa-shopping-cart"></i> Carrinho
                </a></li>
                <?php if(isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'ADMIN'): ?>
                    <li><a href="admin/">👑 Admin</a></li>
                <?php endif; ?>
                <li class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo $_SESSION['usuario_nome']; ?></span>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>📦 Meus Pedidos</h1>
            
            <?php if(empty($pedidos)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Você ainda não fez nenhum pedido!<br><br>
                    <a href="cardapio.php" class="btn" style="background: #e74c3c; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                        🍕 Fazer primeiro pedido
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($pedidos as $pedido): ?>
                    <div class="pedido-card">
                        <div class="pedido-header">
                            <div>
                                <div class="pedido-numero">
                                    <i class="fas fa-receipt"></i> Pedido #<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="pedido-data">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?>
                                    às <?php echo date('H:i', strtotime($pedido['data_pedido'])); ?>
                                </div>
                            </div>
                            <div class="pedido-total">
                                R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        
                        <div class="pedido-info">
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-map-marker-alt"></i> Endereço</div>
                                <div class="info-value"><?php echo $pedido['endereco_entrega']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-tachometer-alt"></i> Distância</div>
                                <div class="info-value"><?php echo $pedido['distancia_km']; ?> km</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-truck"></i> Taxa entrega</div>
                                <div class="info-value">R$ <?php echo number_format($pedido['taxa_entrega'], 2, ',', '.'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-credit-card"></i> Pagamento</div>
                                <div class="info-value"><?php echo $pedido['forma_pagamento']; ?></div>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span class="status-badge" style="background: <?php echo $status_map[$pedido['status']]['cor']; ?>20; color: <?php echo $status_map[$pedido['status']]['cor']; ?>; border: 1px solid <?php echo $status_map[$pedido['status']]['cor']; ?>">
                                <?php echo $status_map[$pedido['status']]['texto']; ?>
                            </span>
                            <button class="btn-detalhes" onclick="verDetalhes(<?php echo $pedido['id']; ?>)">
                                <i class="fas fa-eye"></i> Ver detalhes
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de detalhes -->
    <div id="detalhesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-receipt"></i> Detalhes do Pedido</h2>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div id="detalhesConteudo">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>
    </div>

    <script>
        function verDetalhes(pedidoId) {
            const modal = document.getElementById('detalhesModal');
            const conteudo = document.getElementById('detalhesConteudo');
            
            modal.style.display = 'block';
            conteudo.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
            
            fetch(`buscar-detalhes-pedido.php?pedido_id=${pedidoId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>📅 Data:</strong> ${new Date(data.pedido.data_pedido).toLocaleString('pt-BR')}</p>
                            <p><strong>📍 Endereço:</strong> ${data.pedido.endereco_entrega}</p>
                            <p><strong>📏 Distância:</strong> ${data.pedido.distancia_km} km</p>
                            <p><strong>🚚 Taxa entrega:</strong> R$ ${parseFloat(data.pedido.taxa_entrega).toFixed(2)}</p>
                            <p><strong>💳 Pagamento:</strong> ${data.pedido.forma_pagamento}</p>
                        </div>
                        
                        <h3>Itens do Pedido:</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 10px; text-align: left;">Produto</th>
                                    <th style="padding: 10px; text-align: center;">Qtd</th>
                                    <th style="padding: 10px; text-align: right;">Preço</th>
                                    <th style="padding: 10px; text-align: right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    data.itens.forEach(item => {
                        html += `
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;">${item.produto_nome}</td>
                                <td style="padding: 10px; text-align: center;">${item.quantidade}</td>
                                <td style="padding: 10px; text-align: right;">R$ ${parseFloat(item.preco_unitario).toFixed(2)}</td>
                                <td style="padding: 10px; text-align: right;">R$ ${(item.quantidade * item.preco_unitario).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="3" style="padding: 10px; text-align: right;">Total:</td>
                                    <td style="padding: 10px; text-align: right; color: #e74c3c;">R$ ${parseFloat(data.pedido.total).toFixed(2)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    `;
                    
                    // Adicionar progresso do pedido
                    const passos = ['PENDENTE', 'PREPARANDO', 'SAIU PARA ENTREGA', 'ENTREGUE'];
                    const statusAtual = passos.indexOf(data.pedido.status);
                    
                    html += `
                        <div class="progresso-pedido">
                            <h4>Progresso do pedido:</h4>
                            <div class="progresso-passos">
                    `;
                    
                    passos.forEach((passo, index) => {
                        let statusClasse = '';
                        if(index < statusAtual) statusClasse = 'completo';
                        else if(index == statusAtual) statusClasse = 'ativo';
                        
                        let icone = '';
                        if(index < statusAtual) icone = '✓';
                        else if(index == statusAtual) icone = index + 1;
                        else icone = index + 1;
                        
                        html += `
                            <div class="passo ${statusClasse}">
                                <div class="circulo">${icone}</div>
                                <div class="linha"></div>
                                <div class="passo-texto">${passo.replace('_', ' ')}</div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                    
                    conteudo.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro:', error);
                    conteudo.innerHTML = '<div style="text-align: center; padding: 20px; color: red;"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar detalhes</div>';
                });
        }
        
        function fecharModal() {
            document.getElementById('detalhesModal').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('detalhesModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>