<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// ID só numérico; e a consulta abaixo filtra pelo usuário logado (anti-IDOR:
// trocar ?id=5 por ?id=6 na URL não mostra o pedido de outra pessoa)
$pedido_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

// Buscar dados do pedido
$stmt = $pdo->prepare("
    SELECT p.*, u.nome as cliente_nome, u.endereco 
    FROM pedidos p 
    JOIN usuarios u ON p.usuario_id = u.id 
    WHERE p.id = ? AND p.usuario_id = ?
");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch();

if(!$pedido) {
    header('Location: meus-pedidos.php');
    exit;
}

// Buscar itens do pedido
$stmt = $pdo->prepare("
    SELECT ip.*, 
           CASE 
               WHEN ip.is_personalizada = TRUE THEN 
                   (SELECT CONCAT(pr1.nome, ' + ', pr2.nome) 
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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ff4f1f">
    <title>Pedido Confirmado - Pizzaria do Bairro</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,100..900;1,62..125,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/acessibilidade.css">
    <script>
        // Liga as animações de entrada. Se o site.js não carregar, a classe "js"
        // sai de novo e nenhum bloco fica invisível esperando a animação.
        document.documentElement.classList.add('js');
        window.addEventListener('load', function () {
            if (!window.__siteJs) { document.documentElement.classList.remove('js'); }
        });
    </script>
    <style>
        /* Estilos só desta página (o resto vem do assets/css/style.css) */

        @media (min-width: 769px) {
            /* Na caixa estreita o título no tamanho padrão (3rem) encosta nas bordas.
               (As margens da caixa e o espaço para a placa vêm do style.css.) */
            .comprovante h1 {
                font-size: 2.5rem;
            }
        }

        .comprovante__frase {
            margin-bottom: 2rem;
        }

        /* Dados do pedido: uma lista <dl> em que cada par rótulo/valor é uma .linha-dado
           (o desenho do <dt> e do <dd> vem do style.css, igual ao carrinho e a Meus Pedidos) */
        .comprovante__dados {
            margin: 0 0 2.5rem;
        }

        /* Itens: a primeira e a última coluna encostam nas laterais, na mesma
           linha vertical dos dados de cima e do total de baixo */
        .comprovante__itens th:first-child {
            padding-left: 0;
        }

        .comprovante__itens th:last-child,
        .comprovante__itens td:last-child {
            padding-right: 0;
        }

        /* Nome do produto com ícone: se o nome quebrar de linha, a segunda linha
           começa embaixo do texto (e não embaixo do ícone) */
        .comprovante__itens .item-nome {
            padding-left: 1.75em;
            text-indent: -1.75em;
            overflow-wrap: anywhere;
        }

        .comprovante__itens .item-nome i {
            width: 1.25em;
            margin-right: .25em;
            text-indent: 0;
            text-align: center;
            color: var(--laranja);
        }

        /* A conta "2 × R$ 44,90" embaixo do nome só aparece no celular (ver abaixo) */
        .item-conta {
            display: none;
            text-indent: 0;
            font-size: .8rem;
        }

        .comprovante__total {
            padding-top: 1rem;
        }

        .comprovante__total > :last-child {
            white-space: nowrap;
        }

        /* Os dois botões com a mesma largura, lado a lado ou um embaixo do outro */
        .comprovante .grupo-botoes .btn {
            min-width: 15.5rem;
        }

        /* Computador estreito (769 a 860px): com o menu lateral, sobra para o
           comprovante quase a largura de um celular. O título diminui para
           "CONFIRMADO!" não partir no meio da palavra */
        @media (min-width: 769px) and (max-width: 860px) {
            .comprovante h1 {
                font-size: 2rem;
            }
        }

        /* Celular, e também o computador estreito (769 a 860px) pelo mesmo motivo:
           com quatro colunas o nome do produto ficava espremido e partia no meio */
        @media (max-width: 600px), (min-width: 769px) and (max-width: 860px) {
            .comprovante .titulo-linhas > * {
                font-size: 1.25rem;
            }

            /* Endereço: rótulo em cima e valor embaixo, para não espremer os dois */
            .comprovante__dados .linha-longa {
                display: block;
            }

            .comprovante__dados .linha-longa dd {
                text-align: left;
            }

            /* Quatro colunas não cabem: "Qtd" e "Preço" somem e os dois valores
               aparecem juntos embaixo do nome do produto (.item-conta).
               O total também encurta: "Total do Pedido" vira "Total". */
            .some-no-celular {
                display: none;
            }

            .item-conta {
                display: block;
            }

            .comprovante .grupo-botoes .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="fundo-xadrez">
    <!-- Skip Link para acessibilidade -->
    <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

    <!-- Anúncio para leitores de tela -->
    <div class="sr-only" role="status" aria-live="polite" id="liveStatus"></div>

    <!-- Menu: coluna fixa na esquerda (computador) ou barra laranja no topo (celular) -->
    <header class="site-header" id="siteHeader">
        <div class="site-header__bar">
            <a class="site-logo" href="index.php" aria-label="Pizzaria do Bairro - página inicial">
                <img class="site-logo__pilha" src="assets/img/logo-pilha.svg" alt="Pizzaria do Bairro" width="255" height="325">
                <img class="site-logo__faixa" src="assets/img/logo-faixa.svg" alt="" aria-hidden="true" width="147" height="50">
            </a>
            <button type="button" class="nav-toggle" aria-controls="siteMenu" aria-expanded="false">
                <span class="sr-only">Abrir ou fechar o menu</span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
                <span class="nav-toggle__linha" aria-hidden="true"></span>
            </button>
        </div>
        <div class="site-header__panel" id="siteMenu">
            <div class="site-header__acao">
                <a href="cardapio.php" class="btn btn-bloco">Peça online</a>
            </div>
            <nav class="site-nav" aria-label="Menu principal">
                <ul class="site-nav__menu">
                    <li><a href="index.php">Início</a></li>
                    <li class="site-nav__grupo">
                        <button type="button" class="site-nav__alternar" aria-expanded="false" aria-controls="subCardapio">
                            Cardápio <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="site-nav__sub" id="subCardapio">
                            <ul>
                                <li><a href="cardapio.php">Todos os sabores</a></li>
                                <li><a href="pizza-dupla.php">Pizza dois sabores</a></li>
                            </ul>
                        </div>
                    </li>
                    <?php if(isLoggedIn()): ?>
                        <li><a href="meus-pedidos.php">Meus Pedidos</a></li>
                        <li><a href="carrinho.php" aria-label="Ver carrinho de compras">Carrinho</a></li>
                        <?php if(isAdmin()): ?>
                            <li><a href="admin/" aria-label="Painel administrativo">Admin</a></li>
                        <?php endif; ?>
                        <li class="site-nav__usuario">
                            <!-- "Usuário:" só para o leitor de tela (aria-label num <span> é ignorado) -->
                            <i class="fas fa-user" aria-hidden="true"></i> <span class="sr-only">Usuário: </span><?= e($_SESSION['usuario_nome']) ?>
                        </li>
                        <li><a href="logout.php?token=<?= e(csrf_token()) ?>" aria-label="Sair do sistema">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Entrar</a></li>
                        <li><a href="cadastro.php">Cadastrar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="site-social">
                <ul>
                    <li><a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a></li>
                    <li><a href="#" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true"></i></a></li>
                    <li><a href="#" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a></li>
                </ul>
            </div>
            <p class="site-header__rodape">&copy; 2024 Pizzaria do Bairro</p>
        </div>
    </header>

    <main class="site-content" id="main-content">

        <!-- Comprovante do pedido: caixa branca estreita sobre o xadrez -->
        <section class="caixa caixa--estreita caixa--placa comprovante revealable" aria-labelledby="pedido-titulo">
            <i class="fas fa-check-circle icone-destaque" aria-hidden="true"></i>
            <h1 id="pedido-titulo">Pedido Confirmado!</h1>
            <p class="comprovante__frase">Seu pedido foi recebido com sucesso</p>

            <!-- Dados do pedido: rótulo à esquerda, valor à direita.
                 Rótulos sem dois-pontos, como no carrinho e em Meus Pedidos -->
            <dl class="comprovante__dados">
                <div class="linha-dado">
                    <dt>Número do Pedido</dt>
                    <dd><strong>#<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?></strong></dd>
                </div>
                <div class="linha-dado">
                    <dt>Data do Pedido</dt>
                    <dd><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></dd>
                </div>
                <div class="linha-dado">
                    <dt>Status</dt>
                    <dd><span class="status-badge status-badge--warning">Pendente</span></dd>
                </div>
                <div class="linha-dado">
                    <dt>Cliente</dt>
                    <dd><?= e($pedido['cliente_nome']) ?></dd>
                </div>
                <div class="linha-dado linha-longa">
                    <dt>Endereço de Entrega</dt>
                    <dd><?= e($pedido['endereco_entrega']) ?></dd>
                </div>
                <div class="linha-dado">
                    <dt>Forma de Pagamento</dt>
                    <dd><?= e($pedido['forma_pagamento']) ?></dd>
                </div>
                <?php if($pedido['troco_para']): ?>
                <div class="linha-dado">
                    <dt>Troco para</dt>
                    <dd>R$ <?php echo number_format($pedido['troco_para'], 2, ',', '.'); ?></dd>
                </div>
                <?php endif; ?>
            </dl>

            <div class="titulo-linhas">
                <h2 id="itens-titulo">Itens do Pedido</h2>
            </div>

            <!-- No celular as colunas .some-no-celular somem e a .item-conta aparece (ver o <style>) -->
            <table class="tabela comprovante__itens" aria-labelledby="itens-titulo">
                <thead>
                    <tr>
                        <th scope="col">Produto</th>
                        <th scope="col" class="num some-no-celular">Qtd</th>
                        <th scope="col" class="num some-no-celular">Preço</th>
                        <th scope="col" class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($itens as $item): ?>
                    <tr>
                        <td class="item-nome">
                            <?php if(strpos($item['produto_nome'], '+') !== false): ?>
                                <i class="fas fa-pizza-slice" aria-hidden="true"></i> <?= e($item['produto_nome']) ?>
                            <?php else: ?>
                                <i class="fas fa-utensils" aria-hidden="true"></i> <?= e($item['produto_nome']) ?>
                            <?php endif; ?>
                            <span class="item-conta"><?= (int) $item['quantidade'] ?> &times; R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></span>
                        </td>
                        <td class="num some-no-celular"><?= (int) $item['quantidade'] ?></td>
                        <td class="num some-no-celular">R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                        <td class="num">R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="linha-dado linha-dado--total comprovante__total">
                <span>Total<span class="some-no-celular"> do Pedido</span></span>
                <span>R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></span>
            </div>

            <div class="grupo-botoes">
                <a href="meus-pedidos.php" class="btn">Ver Meus Pedidos</a>
                <a href="cardapio.php" class="btn btn-contorno">Fazer Novo Pedido</a>
            </div>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
    <script>
        // Mostrar notificação de sucesso (se suportado)
        if (Notification.permission === "granted") {
            new Notification("Pedido Confirmado!", {
                body: "Seu pedido foi recebido e está sendo preparado!",
                icon: "/favicon.ico"
            });
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    </script>
</body>
</html>