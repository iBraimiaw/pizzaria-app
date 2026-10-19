<?php
/**
 * buscar-detalhes-pedido.php — devolve (JSON) os detalhes de UM pedido do
 * usuário logado. Usado pelo modal "detalhes" de meus-pedidos.php.
 */
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$pedido_id = filter_var($_GET['pedido_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

// Controle de acesso (anti-IDOR): o pedido precisa ser DO usuário logado.
// Sem o "AND usuario_id = ?", bastaria trocar o número na URL para ver
// o pedido (e o endereço) de outro cliente.
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch();

if(!$pedido) {
    http_response_code(404);
    echo json_encode(['error' => 'Pedido não encontrado']);
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

echo json_encode([
    'pedido' => $pedido,
    'itens' => $itens
]);
