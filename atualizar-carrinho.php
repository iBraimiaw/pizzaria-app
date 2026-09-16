<?php
require_once 'includes/functions.php';

if(!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

if(isset($_POST['produto_id']) && isset($_POST['quantidade'])) {
    $produto_id = $_POST['produto_id'];
    $quantidade = $_POST['quantidade'];
    
    if($quantidade <= 0) {
        unset($_SESSION['carrinho'][$produto_id]);
    } else {
        $_SESSION['carrinho'][$produto_id]['quantidade'] = $quantidade;
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>