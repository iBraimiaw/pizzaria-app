<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id']) || empty($_SESSION['carrinho'])) {
    header('Location: index.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $total = $_POST['total'];
    $distancia = $_POST['distancia'];
    $taxa_entrega = $_POST['taxa_entrega'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $troco_para = !empty($_POST['troco_para']) ? $_POST['troco_para'] : null;
    $desconto_cupom = isset($_POST['desconto_cupom']) ? $_POST['desconto_cupom'] : 0;
    $cupom_id = isset($_POST['cupom_id']) ? $_POST['cupom_id'] : null;
    
    $usuario = getUsuarioEndereco($usuario_id);
    $endereco_entrega = $usuario['endereco'];
    
    try {
        $pdo->beginTransaction();
        
        // Inserir pedido
        $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, total, endereco_entrega, distancia_km, taxa_entrega, forma_pagamento, troco_para) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $total, $endereco_entrega, $distancia, $taxa_entrega, $forma_pagamento, $troco_para]);
        $pedido_id = $pdo->lastInsertId();
        
        // Inserir itens do pedido
        foreach($_SESSION['carrinho'] as $item) {
            if(isset($item['tipo']) && $item['tipo'] == 'personalizada') {
                // Pizza personalizada
                $stmt = $pdo->prepare("INSERT INTO pizzas_personalizadas (pedido_id, sabor1_id, sabor2_id, preco_total, quantidade) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$pedido_id, $item['sabor1_id'], $item['sabor2_id'], $item['preco'], $item['quantidade']]);
                $pp_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, is_personalizada, pizza_personalizada_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pedido_id, null, $item['quantidade'], $item['preco'], true, $pp_id]);
            } else {
                // Produto normal
                $stmt = $pdo->prepare("SELECT id FROM produtos WHERE nome = ?");
                $stmt->execute([$item['nome']]);
                $produto = $stmt->fetch();
                $stmt = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pedido_id, $produto['id'], $item['quantidade'], $item['preco']]);
            }
        }
        
        // Registrar uso do cupom, se houver
        if($cupom_id && $desconto_cupom > 0) {
            registrarUsoCupom($pedido_id, $cupom_id, $desconto_cupom);
        }
        
        $pdo->commit();
        
        // Limpar carrinho e cupom
        unset($_SESSION['carrinho']);
        removerCupom();
        
        $_SESSION['pedido_sucesso'] = "Pedido #" . str_pad($pedido_id, 6, '0', STR_PAD_LEFT) . " realizado com sucesso!";
        header('Location: pedido-confirmado.php?id=' . $pedido_id);
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['pedido_erro'] = "Erro ao finalizar pedido: " . $e->getMessage();
        header('Location: carrinho.php');
        exit;
    }
} else {
    header('Location: carrinho.php');
    exit;
}
?>