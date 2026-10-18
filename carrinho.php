<?php
require_once 'includes/functions.php';
require_once 'includes/entrega.php'; // cliente do microserviço de entrega

if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Segurança: todo POST desta página precisa do token CSRF (senão: 403)
verificar_csrf();

// Limite de unidades por item (evita quantidades absurdas/negativas)
const QUANTIDADE_MAXIMA_ITEM = 50;

/** Busca um produto disponível no BANCO (nome e preço nunca vêm do formulário). */
function buscarProdutoDisponivel(PDO $pdo, int $produto_id): ?array
{
    $stmt = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE id = ? AND disponivel = TRUE");
    $stmt->execute([$produto_id]);
    return $stmt->fetch() ?: null;
}

// Adicionar produto normal (vindo do cardápio)
if(isset($_POST['add_carrinho'])) {
    $produto_id = filter_var($_POST['produto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantidade = filter_var($_POST['quantidade'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => QUANTIDADE_MAXIMA_ITEM]]);
    // Preço e nome vêm do banco: se alguém editar o HTML e mandar preco=0,01, não adianta
    $produto = $produto_id ? buscarProdutoDisponivel($pdo, $produto_id) : null;
    if($produto && $quantidade) {
        addToCart($produto['id'], $produto['nome'], $produto['preco'], $quantidade);
    }
    header('Location: cardapio.php');
    exit;
}

// Adicionar item extra (bebida ou porção) diretamente no carrinho
if(isset($_POST['add_extra'])) {
    $produto_id = filter_var($_POST['extra_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $prod = $produto_id ? buscarProdutoDisponivel($pdo, $produto_id) : null;
    if($prod) {
        addToCart($prod['id'], $prod['nome'], $prod['preco'], 1);
        $_SESSION['mensagem_extra'] = $prod['nome'] . " adicionado ao carrinho!";
    }
    header('Location: carrinho.php');
    exit;
}

// Remover item (link GET que altera dados -> também exige o token CSRF na URL)
if(isset($_GET['remove'])) {
    verificar_csrf($_GET['token'] ?? '');
    $indice = filter_var($_GET['remove'], FILTER_VALIDATE_INT);
    if($indice !== false && isset($_SESSION['carrinho'][$indice])) {
        unset($_SESSION['carrinho'][$indice]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
    }
    header('Location: carrinho.php');
    exit;
}

// Atualizar quantidades
if(isset($_POST['atualizar'])) {
    foreach((array) ($_POST['quantidade'] ?? []) as $index => $qtd) {
        $index = (int) $index;
        if(!isset($_SESSION['carrinho'][$index])) {
            continue; // índice que não existe no carrinho: ignora
        }
        $qtd = (int) $qtd;
        if($qtd <= 0) {
            unset($_SESSION['carrinho'][$index]);
        } else {
            $_SESSION['carrinho'][$index]['quantidade'] = min($qtd, QUANTIDADE_MAXIMA_ITEM);
        }
    }
    $_SESSION['carrinho'] = array_values($_SESSION['carrinho'] ?? []);
    header('Location: carrinho.php');
    exit;
}

// Processar cupom (se houver)
if(isset($_POST['aplicar_cupom'])) {
    $codigo = strtoupper(trim((string) ($_POST['codigo_cupom'] ?? '')));
    $subtotal = calcularTotalCarrinho();
    
    // Só valida e guarda as regras do cupom; o desconto em R$ é recalculado abaixo
    if(aplicarCupom($codigo, $subtotal)) {
        $_SESSION['mensagem_cupom'] = "Cupom aplicado com sucesso!";
    } else {
        $_SESSION['mensagem_cupom'] = "Cupom inválido ou não aplicável.";
    }
    header('Location: carrinho.php');
    exit;
}

if(isset($_GET['remover_cupom'])) {
    verificar_csrf($_GET['token'] ?? '');
    removerCupom();
    $_SESSION['mensagem_cupom'] = "Cupom removido.";
    header('Location: carrinho.php');
    exit;
}

// Calcular totais
$subtotal = calcularTotalCarrinho();

// Buscar endereço e frete
$stmt = $pdo->prepare("SELECT endereco, latitude, longitude FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

// Distância, taxa e tempo agora vêm do MICROSERVIÇO de entrega.
// Se ele estiver fora do ar, calcularEntregaUsuario() usa o cálculo local.
$entrega = calcularEntregaUsuario((int) $_SESSION['usuario_id']);
$distancia = $entrega['distancia_km'];
$taxa_entrega = $entrega['taxa_entrega'];

// Aplicar cupom se existir
$desconto_cupom = 0;
$cupom_aplicado = isset($_SESSION['cupom']) ? $_SESSION['cupom'] : null;
if($cupom_aplicado && !cupomValeParaSubtotal($cupom_aplicado, $subtotal)) {
    // O carrinho mudou e ficou abaixo do valor mínimo do cupom: ele sai
    removerCupom();
    $cupom_aplicado = null;
    $_SESSION['mensagem_cupom'] = "Cupom removido: o carrinho ficou abaixo do valor mínimo.";
}
if($cupom_aplicado) {
    // Segurança: recalcula sobre o subtotal ATUAL (o cliente pode ter tirado itens)
    $desconto_cupom = calcularDescontoCupom($cupom_aplicado, $subtotal);
    if(cupomEhFreteGratis($cupom_aplicado)) {
        $taxa_entrega = 0;
    }
}

$total = $subtotal + $taxa_entrega - $desconto_cupom;
if($total < 0) $total = 0;

// Buscar dados dos extras para exibir
$extras = $pdo->query("SELECT id, nome, preco FROM produtos WHERE nome IN ('Coca-Cola 2L', 'Guaraná 2L', 'Batata Frita') AND disponivel = true")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Carrinho - Pizzaria do Bairro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #FFF5F0 0%, #FFE8E0 100%); }
        .header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); position: fixed; top: 0; width: 100%; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 24px; font-weight: bold; } .logo span { color: #e74c3c; }
        .nav-links { display: flex; list-style: none; gap: 20px; align-items: center; }
        .nav-links a { text-decoration: none; color: #333; font-weight: 500; }
        .user-info { background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 8px 20px; border-radius: 50px; color: white; display: flex; align-items: center; gap: 10px; }
        .main-content { margin-top: 80px; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .table { width: 100%; background: white; border-radius: 10px; overflow-x: auto; display: block; }
        .table th, .table td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .resumo-pedido { background: white; padding: 25px; border-radius: 10px; margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .resumo-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .resumo-total { display: flex; justify-content: space-between; padding: 15px 0; font-size: 20px; font-weight: bold; color: #e74c3c; }
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; padding: 5px 10px; font-size: 12px; }
        .quantidade-input { width: 60px; padding: 5px; text-align: center; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-success { background: #d4edda; color: #155724; }
        .cupom-box { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .cupom-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .desconto { color: #27ae60; font-weight: bold; }
        .extras-box { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .extras-grid { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
        .extra-item { background: white; padding: 10px 15px; border-radius: 8px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .extra-item form { margin: 0; }
        .extra-preco { font-weight: bold; color: #e74c3c; }
        @media (max-width: 768px) { .navbar { flex-direction: column; } .extras-grid { flex-direction: column; } }
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
                <li><a href="carrinho.php">Carrinho</a></li>
                <li class="user-info">👤 <?= e($_SESSION['usuario_nome']) ?> <a href="logout.php?token=<?= e(csrf_token()) ?>" style="color:white;">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        <div class="container">
            <h1>🛒 Meu Carrinho</h1>
            
            <?php if(isset($_SESSION['mensagem_cupom'])): ?>
                <div class="alert alert-success"><?php echo e($_SESSION['mensagem_cupom']); unset($_SESSION['mensagem_cupom']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['mensagem_extra'])): ?>
                <div class="alert alert-success"><?php echo e($_SESSION['mensagem_extra']); unset($_SESSION['mensagem_extra']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['pedido_erro'])): ?>
                <div class="alert" style="background:#fdecea; color:#c0392b;"><?php echo htmlspecialchars($_SESSION['pedido_erro']); unset($_SESSION['pedido_erro']); ?></div>
            <?php endif; ?>
            
            <?php if(empty($_SESSION['carrinho'])): ?>
                <div class="alert alert-info">Seu carrinho está vazio! <a href="cardapio.php">Ver cardápio</a></div>
            <?php else: ?>
                <form method="POST">
                    <?= campo_csrf() ?>
                    <table class="table">
                        <thead><tr><th>Produto</th><th>Quantidade</th><th>Preço Unit.</th><th>Subtotal</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach($_SESSION['carrinho'] as $index => $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                <td><input type="number" name="quantidade[<?= (int) $index ?>]" value="<?= (int) $item['quantidade'] ?>" min="0" class="quantidade-input"></td>
                                <td>R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></td>
                                <td><a href="?remove=<?= (int) $index ?>&token=<?= e(csrf_token()) ?>" class="btn btn-danger">Remover</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:20px;"><button type="submit" name="atualizar" class="btn btn-primary">Atualizar Carrinho</button></div>
                </form>
                
                <!-- SEÇÃO DE ADICIONAIS (BEBIDAS + PORÇÃO) -->
                <div class="extras-box">
                    <h3><i class="fas fa-plus-circle"></i> Adicionar Bebidas ou Porção</h3>
                    <div class="extras-grid">
                        <?php foreach($extras as $extra): ?>
                        <div class="extra-item">
                            <span><strong><?php echo htmlspecialchars($extra['nome']); ?></strong></span>
                            <span class="extra-preco">R$ <?php echo number_format($extra['preco'], 2, ',', '.'); ?></span>
                            <form method="POST" style="display:inline;">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="extra_id" value="<?php echo $extra['id']; ?>">
                                <button type="submit" name="add_extra" class="btn btn-primary" style="padding: 5px 12px;">Adicionar</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- CUPOM DE DESCONTO -->
                <div class="cupom-box">
                    <form method="POST" style="display:flex; gap:10px; width:100%;">
                        <?= campo_csrf() ?>
                        <input type="text" name="codigo_cupom" placeholder="Digite seu cupom" class="cupom-input">
                        <button type="submit" name="aplicar_cupom" class="btn btn-primary">Aplicar Cupom</button>
                    </form>
                    <?php if($cupom_aplicado): ?>
                        <div>Cupom aplicado: <strong><?= e($cupom_aplicado['codigo']) ?></strong> - Desconto de R$ <?php echo number_format($desconto_cupom, 2, ',', '.'); ?> 
                        <a href="?remover_cupom=1&token=<?= e(csrf_token()) ?>" style="color:#e74c3c; margin-left:10px;">[Remover]</a></div>
                    <?php endif; ?>
                </div>
                
                <!-- RESUMO DO PEDIDO -->
                <div class="resumo-pedido">
                    <div class="resumo-item"><span>📍 Endereço:</span><span><?php echo htmlspecialchars($usuario['endereco']); ?></span></div>
                    <div class="resumo-item"><span>📏 Distância:</span><span><?= e($distancia) ?> km</span></div>
                    <div class="resumo-item"><span>🚚 Taxa de entrega:</span><span>R$ <?php echo number_format($taxa_entrega, 2, ',', '.'); ?></span></div>
                    <?php /* Linha discreta: mostra de onde veio o cálculo (útil na demonstração) */ ?>
                    <div style="font-size:12px; color:#888; text-align:right; padding:4px 0;">
                        <?php if($entrega['origem'] === 'microservico'): ?>
                            ⏱️ Tempo estimado: ~<?php echo (int) $entrega['tempo_estimado_min']; ?> min · calculado pelo serviço de entrega
                        <?php elseif($entrega['origem'] === 'sem_coordenadas'): ?>
                            Endereço sem coordenadas · frete padrão de <?= e(ENTREGA_DISTANCIA_PADRAO_KM) ?> km
                        <?php else: ?>
                            Serviço de entrega indisponível · frete calculado localmente
                        <?php endif; ?>
                    </div>
                    <?php if(!$entrega['dentro_area']): ?>
                    <div style="color:#e74c3c; padding:8px 0;">⚠️ Seu endereço está fora da nossa área de entrega.</div>
                    <?php endif; ?>
                    <div class="resumo-item"><span>🍕 Subtotal:</span><span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                    <?php if($desconto_cupom > 0): ?>
                    <div class="resumo-item"><span>🎫 Desconto cupom:</span><span class="desconto">- R$ <?php echo number_format($desconto_cupom, 2, ',', '.'); ?></span></div>
                    <?php endif; ?>
                    <div class="resumo-total"><span><strong>TOTAL:</strong></span><span><strong>R$ <?php echo number_format($total, 2, ',', '.'); ?></strong></span></div>
                    
                    <form method="POST" action="finalizar-pedido.php">
                        <?= campo_csrf() ?>
                        <div class="form-group"><label>Forma de pagamento:</label><select name="forma_pagamento" required><option value="Dinheiro">Dinheiro</option><option value="Cartão Crédito">Cartão Crédito</option><option value="Cartão Débito">Cartão Débito</option><option value="PIX">PIX</option></select></div>
                        <div id="trocoGroup" style="display:none;"><label>Troco para:</label><input type="number" step="0.01" name="troco_para"></div>
                        <input type="hidden" name="distancia" value="<?php echo $distancia; ?>">
                        <input type="hidden" name="taxa_entrega" value="<?php echo $taxa_entrega; ?>">
                        <input type="hidden" name="total" value="<?php echo $total; ?>">
                        <input type="hidden" name="desconto_cupom" value="<?php echo $desconto_cupom; ?>">
                        <?php if($cupom_aplicado): ?>
                        <input type="hidden" name="cupom_id" value="<?php echo $cupom_aplicado['id']; ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-success" style="width:100%; margin-top:20px;" <?php echo $entrega['dentro_area'] ? '' : 'disabled'; ?>>Finalizar Pedido</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        // Com o carrinho vazio o formulário não existe, então só liga o evento se o select estiver na tela
        const selectPagamento = document.querySelector('select[name="forma_pagamento"]');
        if (selectPagamento) {
            selectPagamento.addEventListener('change', function() {
                document.getElementById('trocoGroup').style.display = this.value === 'Dinheiro' ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>