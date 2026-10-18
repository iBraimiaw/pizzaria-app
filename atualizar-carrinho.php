<?php
/**
 * atualizar-carrinho.php — endpoint AJAX para mudar a quantidade de um item.
 *
 * Exemplo de chamada com fetch() (o token CSRF vai no header):
 *   fetch('atualizar-carrinho.php', {
 *     method: 'POST',
 *     headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
 *     body: new URLSearchParams({ indice: 0, quantidade: 2 })
 *   });
 *
 * "indice" é a posição do item na lista $_SESSION['carrinho'] (0, 1, 2...),
 * o mesmo formato usado pelo carrinho.php.
 */
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if(!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405); // só aceita POST
    echo json_encode(['success' => false]);
    exit;
}

const QUANTIDADE_MAXIMA_ITEM = 50; // mesmo limite do carrinho.php

verificar_csrf(); // aceita o campo csrf_token OU o header X-CSRF-Token

$indice = filter_var($_POST['indice'] ?? '', FILTER_VALIDATE_INT);
$quantidade = filter_var($_POST['quantidade'] ?? '', FILTER_VALIDATE_INT);

if($indice === false || $quantidade === false || !isset($_SESSION['carrinho'][$indice])) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

if($quantidade <= 0) {
    unset($_SESSION['carrinho'][$indice]);
    $_SESSION['carrinho'] = array_values($_SESSION['carrinho']); // mantém a lista 0,1,2...
} else {
    $_SESSION['carrinho'][$indice]['quantidade'] = min($quantidade, QUANTIDADE_MAXIMA_ITEM);
}

echo json_encode(['success' => true, 'total' => calcularTotalCarrinho()]);
