<?php
require_once 'includes/functions.php';
require_once 'includes/entrega.php'; // cliente do microserviço de entrega

if(!isset($_SESSION['usuario_id']) || empty($_SESSION['carrinho'])) {
    header('Location: index.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    verificar_csrf(); // segurança: o pedido tem que vir do formulário do nosso site

    $usuario_id = $_SESSION['usuario_id'];
    // Distância e taxa são recalculadas AQUI no servidor (microserviço ou fallback),
    // em vez de confiar nos campos hidden do formulário, que o usuário pode alterar.
    $entrega = calcularEntregaUsuario((int) $usuario_id);
    if(!$entrega['dentro_area']) {
        $_SESSION['pedido_erro'] = "Endereço fora da área de entrega.";
        header('Location: carrinho.php');
        exit;
    }
    $distancia = $entrega['distancia_km'];
    $taxa_entrega = $entrega['taxa_entrega'];
    // Segurança: desconto e total também são calculados AQUI, a partir da
    // sessão (preços vieram do banco). Os campos hidden "total",
    // "desconto_cupom" e "cupom_id" do formulário são ignorados de propósito.
    $subtotal = calcularTotalCarrinho();
    $cupom_sessao = $_SESSION['cupom'] ?? null;
    if($cupom_sessao) {
        // Revalida no banco (pode ter sido desativado/vencido) e contra o
        // subtotal ATUAL (o cliente pode ter tirado itens depois de aplicar).
        $cupom_banco = buscarCupomValido($cupom_sessao['codigo']);
        if(!$cupom_banco || !cupomValeParaSubtotal($cupom_banco, $subtotal)) {
            removerCupom();
            $_SESSION['pedido_erro'] = "O cupom não vale mais para este carrinho e foi removido. Confira o total.";
            header('Location: carrinho.php');
            exit;
        }
        $cupom_sessao = $cupom_banco;
    }
    $cupom_id = $cupom_sessao ? (int) $cupom_sessao['id'] : null;
    // Desconto recalculado agora; nunca o valor que estava "congelado" na sessão
    $desconto_cupom = $cupom_sessao ? calcularDescontoCupom($cupom_sessao, $subtotal) : 0;
    // Cupom de frete grátis (mesma regra do carrinho.php) zera a taxa.
    if($cupom_sessao && cupomEhFreteGratis($cupom_sessao)) {
        $taxa_entrega = 0;
    }
    $total = max(0, round($subtotal + $taxa_entrega - $desconto_cupom, 2));

    // Forma de pagamento: só aceita uma das opções da tela (lista branca)
    $formas_permitidas = ['Dinheiro', 'Cartão Crédito', 'Cartão Débito', 'PIX'];
    $forma_pagamento = (string) ($_POST['forma_pagamento'] ?? '');
    if (!in_array($forma_pagamento, $formas_permitidas, true)) {
        $_SESSION['pedido_erro'] = 'Forma de pagamento inválida.';
        header('Location: carrinho.php');
        exit;
    }

    // Troco: só faz sentido em dinheiro e precisa ser um número >= total
    $troco_para = null;
    if ($forma_pagamento === 'Dinheiro' && !empty($_POST['troco_para'])) {
        $troco_para = filter_var($_POST['troco_para'], FILTER_VALIDATE_FLOAT);
        if ($troco_para === false || $troco_para < $total) {
            $_SESSION['pedido_erro'] = 'O valor do troco precisa ser maior ou igual ao total do pedido.';
            header('Location: carrinho.php');
            exit;
        }
    }

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
                // Produto normal (o carrinho já guarda o id do produto)
                $stmt = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pedido_id, (int) $item['produto_id'], (int) $item['quantidade'], $item['preco']]);
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
        // Detalhe técnico vai só para o log; o cliente vê mensagem genérica
        error_log('Erro ao finalizar pedido: ' . $e->getMessage());
        $_SESSION['pedido_erro'] = "Não foi possível finalizar o pedido. Tente novamente.";
        header('Location: carrinho.php');
        exit;
    }
} else {
    header('Location: carrinho.php');
    exit;
}
?>