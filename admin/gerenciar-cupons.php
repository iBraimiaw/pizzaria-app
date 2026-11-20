<?php
require_once __DIR__ . '/../includes/functions.php';

if(!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Adicionar/Editar cupom
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['adicionar'])) {
        $codigo = strtoupper($_POST['codigo']);
        $descricao = $_POST['descricao'];
        $tipo_desconto = $_POST['tipo_desconto'];
        $valor_desconto = $_POST['valor_desconto'];
        $valor_minimo_pedido = $_POST['valor_minimo_pedido'] ?: 0;
        $quantidade_max_uso = $_POST['quantidade_max_uso'] ?: 0;
        $data_validade = $_POST['data_validade'];
        
        $stmt = $pdo->prepare("
            INSERT INTO cupons (codigo, descricao, tipo_desconto, valor_desconto, valor_minimo_pedido, quantidade_max_uso, data_validade) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$codigo, $descricao, $tipo_desconto, $valor_desconto, $valor_minimo_pedido, $quantidade_max_uso, $data_validade]);
        header('Location: gerenciar-cupons.php');
        exit;
    }
    
    if(isset($_POST['editar'])) {
        $id = $_POST['id'];
        $codigo = strtoupper($_POST['codigo']);
        $descricao = $_POST['descricao'];
        $tipo_desconto = $_POST['tipo_desconto'];
        $valor_desconto = $_POST['valor_desconto'];
        $valor_minimo_pedido = $_POST['valor_minimo_pedido'] ?: 0;
        $quantidade_max_uso = $_POST['quantidade_max_uso'] ?: 0;
        $data_validade = $_POST['data_validade'];
        $ativo = isset($_POST['ativo']) ? true : false;
        
        $stmt = $pdo->prepare("
            UPDATE cupons SET codigo=?, descricao=?, tipo_desconto=?, valor_desconto=?, valor_minimo_pedido=?, quantidade_max_uso=?, data_validade=?, ativo=? 
            WHERE id=?
        ");
        $stmt->execute([$codigo, $descricao, $tipo_desconto, $valor_desconto, $valor_minimo_pedido, $quantidade_max_uso, $data_validade, $ativo, $id]);
        header('Location: gerenciar-cupons.php');
        exit;
    }
    
    if(isset($_GET['excluir'])) {
        $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = ?");
        $stmt->execute([$_GET['excluir']]);
        header('Location: gerenciar-cupons.php');
        exit;
    }
}

// Buscar cupons
$cupons = $pdo->query("SELECT * FROM cupons ORDER BY id DESC")->fetchAll();

// Buscar cupom para edição
$cupom_editar = null;
if(isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $cupom_editar = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gerenciar Cupons</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f0f2f5; }
        .header { background: #2c3e50; }
        .logo, .logo span { color: white !important; }
        .nav-links a { color: white !important; }
        .nav-links a:hover { color: #f39c12 !important; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .main-content { padding: 20px; }
        .form-container { background: white; padding: 20px; margin-bottom: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 5px; text-decoration: none; background: #3498db; color: white; border: none; cursor: pointer; }
        .btn-success { background: #27ae60; }
        .btn-danger { background: #e74c3c; }
        .btn-warning { background: #f39c12; }
        .table { width: 100%; background: white; border-radius: 10px; overflow-x: auto; display: block; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; max-width: 1400px; margin: 0 auto; }
        .nav-links { display: flex; list-style: none; gap: 20px; }
        .nav-links a { text-decoration: none; }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-inactive { color: #e74c3c; }
        @media (max-width: 768px) { .navbar { flex-direction: column; } .table { font-size: 12px; } }
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
            <h1><i class="fas fa-ticket-alt"></i> Gerenciar Cupons</h1>
            <button onclick="document.getElementById('formAdicionar').style.display='block'" class="btn">+ Adicionar Cupom</button>
            
            <div id="formAdicionar" style="display: none;" class="form-container">
                <h3>Adicionar Cupom</h3>
                <form method="POST">
                    <div class="form-group"><label>Código:</label><input type="text" name="codigo" placeholder="Ex: PIZZA10" required></div>
                    <div class="form-group"><label>Descrição:</label><input type="text" name="descricao" placeholder="Descrição do cupom" required></div>
                    <div class="form-group">
                        <label>Tipo de Desconto:</label>
                        <select name="tipo_desconto" required>
                            <option value="percentual">Percentual (%)</option>
                            <option value="fixo">Valor Fixo (R$)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Valor do Desconto:</label><input type="number" step="0.01" name="valor_desconto" required></div>
                    <div class="form-group"><label>Valor Mínimo do Pedido (R$):</label><input type="number" step="0.01" name="valor_minimo_pedido" value="0"></div>
                    <div class="form-group"><label>Quantidade Máxima de Usos:</label><input type="number" name="quantidade_max_uso" value="0" placeholder="0 = ilimitado"></div>
                    <div class="form-group"><label>Data de Validade:</label><input type="date" name="data_validade" required></div>
                    <button type="submit" name="adicionar" class="btn btn-success">Salvar</button>
                    <button type="button" onclick="document.getElementById('formAdicionar').style.display='none'" class="btn">Cancelar</button>
                </form>
            </div>
            
            <?php if($cupom_editar): ?>
            <div class="form-container">
                <h3>Editar Cupom</h3>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $cupom_editar['id']; ?>">
                    <div class="form-group"><label>Código:</label><input type="text" name="codigo" value="<?php echo $cupom_editar['codigo']; ?>" required></div>
                    <div class="form-group"><label>Descrição:</label><input type="text" name="descricao" value="<?php echo $cupom_editar['descricao']; ?>" required></div>
                    <div class="form-group">
                        <label>Tipo:</label>
                        <select name="tipo_desconto">
                            <option value="percentual" <?php echo $cupom_editar['tipo_desconto'] == 'percentual' ? 'selected' : ''; ?>>Percentual</option>
                            <option value="fixo" <?php echo $cupom_editar['tipo_desconto'] == 'fixo' ? 'selected' : ''; ?>>Valor Fixo</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Valor:</label><input type="number" step="0.01" name="valor_desconto" value="<?php echo $cupom_editar['valor_desconto']; ?>" required></div>
                    <div class="form-group"><label>Valor Mínimo:</label><input type="number" step="0.01" name="valor_minimo_pedido" value="<?php echo $cupom_editar['valor_minimo_pedido']; ?>"></div>
                    <div class="form-group"><label>Máximo de Usos:</label><input type="number" name="quantidade_max_uso" value="<?php echo $cupom_editar['quantidade_max_uso']; ?>"></div>
                    <div class="form-group"><label>Validade:</label><input type="date" name="data_validade" value="<?php echo $cupom_editar['data_validade']; ?>" required></div>
                    <div class="form-group"><label><input type="checkbox" name="ativo" <?php echo $cupom_editar['ativo'] ? 'checked' : ''; ?>> Ativo</label></div>
                    <button type="submit" name="editar" class="btn btn-success">Atualizar</button>
                    <a href="gerenciar-cupons.php" class="btn">Cancelar</a>
                </form>
            </div>
            <?php endif; ?>
            
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Código</th><th>Descrição</th><th>Tipo</th><th>Valor</th><th>Mínimo</th><th>Usos</th><th>Validade</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($cupons as $cupom): ?>
                        <tr>
                            <td><?php echo $cupom['id']; ?></td>
                            <td><?php echo $cupom['codigo']; ?></td>
                            <td><?php echo $cupom['descricao']; ?></td>
                            <td><?php echo $cupom['tipo_desconto'] == 'percentual' ? '%' : 'R$'; ?></td>
                            <td><?php echo $cupom['tipo_desconto'] == 'percentual' ? $cupom['valor_desconto'].'%' : 'R$ '.number_format($cupom['valor_desconto'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($cupom['valor_minimo_pedido'], 2, ',', '.'); ?></td>
                            <td><?php echo $cupom['quantidade_usado'].' / '.($cupom['quantidade_max_uso'] == 0 ? '∞' : $cupom['quantidade_max_uso']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($cupom['data_validade'])); ?></td>
                            <td><?php echo $cupom['ativo'] ? '<span class="status-active">Ativo</span>' : '<span class="status-inactive">Inativo</span>'; ?></td>
                            <td>
                                <a href="?editar=<?php echo $cupom['id']; ?>" class="btn btn-warning">Editar</a>
                                <a href="?excluir=<?php echo $cupom['id']; ?>" class="btn btn-danger" onclick="return confirm('Excluir cupom?')">Excluir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>