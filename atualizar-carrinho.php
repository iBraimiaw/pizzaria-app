<?php
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if(!isLoggedIn()) {
    echo json_seguro(['success' => false]);
    exit;
}

// "produto_id" aqui é o índice do item dentro do carrinho; ambos os valores precisam ser inteiros
$indice = isset($_POST['produto_id']) ? filter_var($_POST['produto_id'], FILTER_VALIDATE_INT) : false;
$quantidade = isset($_POST['quantidade']) ? filter_var($_POST['quantidade'], FILTER_VALIDATE_INT) : false;

if($indice === false || $quantidade === false || !isset($_SESSION['carrinho'][$indice])) {
    echo json_seguro(['success' => false]);
    exit;
}

if($quantidade <= 0) {
    unset($_SESSION['carrinho'][$indice]);
} else {
    $_SESSION['carrinho'][$indice]['quantidade'] = min($quantidade, 99);
}

echo json_seguro(['success' => true]);
?>