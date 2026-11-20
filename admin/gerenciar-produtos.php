<?php
require_once __DIR__ . '/../includes/functions.php';

if(!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Adicionar produto
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $preco = $_POST['preco'];
    $categoria_id = $_POST['categoria_id'];
    $imagem_url = $_POST['imagem_url'];
    
    $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, categoria_id, imagem_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nome, $descricao, $preco, $categoria_id, $imagem_url]);
    
    header('Location: gerenciar-produtos.php');
    exit;
}

// Editar produto
if(isset($_POST['editar'])) {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $preco = $_POST['preco'];
    $categoria_id = $_POST['categoria_id'];
    $imagem_url = $_POST['imagem_url'];
    
    $stmt = $pdo->prepare("UPDATE produtos SET nome=?, descricao=?, preco=?, categoria_id=?, imagem_url=? WHERE id=?");
    $stmt->execute([$nome, $descricao, $preco, $categoria_id, $imagem_url, $id]);
    
    header('Location: gerenciar-produtos.php');
    exit;
}

// Excluir produto
if(isset($_GET['excluir'])) {
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt->execute([$_GET['excluir']]);
    header('Location: gerenciar-produtos.php');
    exit;
}

$produtos = $pdo->query("SELECT p.*, c.nome as categoria_nome FROM produtos p JOIN categorias c ON p.categoria_id = c.id ORDER BY p.id DESC")->fetchAll();
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();

// Buscar produto para edição
$produto_editar = null;
if(isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $produto_editar = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gerenciar Produtos | Pizzaria do Bairro</title>
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
        .form-container {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            background: #3498db;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #3498db;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-editar {
            background: #f39c12;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-excluir {
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
        }
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
        .produto-imagem-preview {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 15px; }
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
            <h1><i class="fas fa-pizza-slice"></i> Gerenciar Produtos</h1>
            
            <button onclick="document.getElementById('formAdicionar').style.display='block'" class="btn btn-primary">
                + Adicionar Produto
            </button>
            
            <!-- Formulário de adicionar -->
            <div id="formAdicionar" style="display: none;" class="form-container">
                <h3>Adicionar Novo Produto</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Nome:</label>
                        <input type="text" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label>Descrição:</label>
                        <textarea name="descricao" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Preço (R$):</label>
                        <input type="number" step="0.01" name="preco" required>
                    </div>
                    <div class="form-group">
                        <label>Categoria:</label>
                        <select name="categoria_id" required>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>URL da Imagem:</label>
                        <input type="url" name="imagem_url" placeholder="https://exemplo.com/imagem.jpg" required>
                        <small>Use links de imagens do Unsplash, Imgur, ou qualquer URL pública</small>
                    </div>
                    <button type="submit" name="adicionar" class="btn btn-success">Salvar</button>
                    <button type="button" onclick="document.getElementById('formAdicionar').style.display='none'" class="btn">Cancelar</button>
                </form>
            </div>
            
            <!-- Formulário de edição -->
            <?php if($produto_editar): ?>
            <div class="form-container">
                <h3>Editar Produto</h3>
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $produto_editar['id']; ?>">
                    <div class="form-group">
                        <label>Nome:</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($produto_editar['nome']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Descrição:</label>
                        <textarea name="descricao" rows="3" required><?php echo htmlspecialchars($produto_editar['descricao']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Preço (R$):</label>
                        <input type="number" step="0.01" name="preco" value="<?php echo $produto_editar['preco']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Categoria:</label>
                        <select name="categoria_id" required>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $produto_editar['categoria_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>URL da Imagem:</label>
                        <input type="url" name="imagem_url" value="<?php echo htmlspecialchars($produto_editar['imagem_url']); ?>" required>
                        <br>
                        <img src="<?php echo htmlspecialchars($produto_editar['imagem_url']); ?>" class="produto-imagem-preview" onerror="this.style.display='none'">
                    </div>
                    <button type="submit" name="editar" class="btn btn-success">Atualizar</button>
                    <a href="gerenciar-produtos.php" class="btn">Cancelar</a>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Lista de produtos -->
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($produtos as $produto): ?>
                        <tr>
                            <td><?php echo $produto['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" 
                                     class="produto-imagem-preview" 
                                     onerror="this.src='https://via.placeholder.com/100/FF6B6B/FFFFFF?text=Erro'">
                            </td>
                            <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                            <td><?php echo htmlspecialchars($produto['categoria_nome']); ?></td>
                            <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                            <td>
                                <a href="?editar=<?php echo $produto['id']; ?>" class="btn-editar">✏️ Editar</a>
                                <a href="?excluir=<?php echo $produto['id']; ?>" class="btn-excluir" onclick="return confirm('Tem certeza que deseja excluir este produto?')">🗑️ Excluir</a>
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