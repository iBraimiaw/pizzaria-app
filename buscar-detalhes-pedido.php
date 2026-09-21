<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['usuario_id'])) {
    echo json_seguro(['error' => 'Não autorizado']);
    exit;
}

$pedido_id = (int)($_GET['pedido_id'] ?? 0);

// Verificar se o pedido pertence ao usuário
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch();

if(!$pedido) {
    echo json_seguro(['error' => 'Pedido não encontrado']);
    exit;
}

// Buscar itens com nome correto para pizza personalizada
$stmt = $pdo->prepare("
    SELECT 
        ip.*,
        CASE 
            WHEN ip.is_personalizada = TRUE THEN 
                (SELECT CONCAT('Pizza Meia a Meia: ', pr1.nome, ' + ', pr2.nome)
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

echo json_seguro([
    'pedido' => $pedido,
    'itens' => $itens
]);
?>