<?php
/**
 * logout.php — Sai da conta.
 *
 * Exige o token CSRF (no link: logout.php?token=... ou num POST). Sem isso,
 * qualquer site poderia deslogar o cliente só colocando
 * <img src="http://pizzaria/logout.php"> na página dele.
 */
require_once 'includes/functions.php';

$token = (string) ($_POST['csrf_token'] ?? ($_GET['token'] ?? ''));

if (!csrf_valido($token)) {
    registrar_evento_seguranca($pdo, 'csrf_invalido', $_SESSION['usuario_id'] ?? null, 'Página: logout.php');
    header('Location: index.php'); // não desloga, só volta para o início
    exit;
}

if (isset($_SESSION['usuario_id'])) {
    registrar_evento_seguranca($pdo, 'logout', (int) $_SESSION['usuario_id']);
}

encerrar_sessao(); // limpa dados, apaga o cookie e destrói a sessão no servidor

header('Location: index.php');
exit;
