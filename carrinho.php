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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ff4f1f">
    <title>Carrinho - Pizzaria do Bairro</title>
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
        /* Estilos só do carrinho (o resto vem do assets/css/style.css) */

        /* Avisos (cupom, adicional, erro, carrinho vazio): ficam no meio da caixa,
           em vez de esticar de uma borda à outra */
        .carrinho-aviso {
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        /* "Ver cardápio" não parte no meio quando a frase quebra de linha no celular */
        .carrinho-aviso a {
            white-space: nowrap;
        }

        /* Duas colunas no computador: itens e adicionais | cupom, resumo e pagamento.
           A grade em si (.menu-colunas) vem do style.css; aqui só o que muda. */
        .carrinho-colunas {
            align-items: start;
            max-width: 1010px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Sem isso um texto comprido (endereço, nome da pizza) empurraria a coluna para fora */
        .carrinho-colunas > * {
            min-width: 0;
        }

        /* Frase de apoio logo abaixo do título da seção (na referência: o texto embaixo de "WINGS") */
        .carrinho-descricao {
            margin: -.75rem 0 1.5rem;
            text-align: center;     /* a grade das colunas alinha tudo à esquerda; esta frase fica no meio */
            text-wrap: balance;     /* no celular quebra em duas linhas parelhas */
        }

        /* Item do carrinho: "Quantidade [campo]" na esquerda e "Remover" na ponta direita */
        .carrinho-item .menu-item__acoes label {
            font-size: .8rem;
            font-weight: 700;
        }

        .carrinho-item .link-perigo {
            margin-left: auto;
            padding: .5rem 0 .5rem .5rem;   /* área de toque maior no celular */
        }

        /* Cupom: campo e botão na mesma linha */
        .carrinho-cupom {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .carrinho-cupom .form-control {
            flex: 1;
            min-width: 0;
        }

        /* O botão acompanha a altura do campo ao lado (48px), em vez de ficar mais baixo */
        .carrinho-cupom .btn {
            align-self: stretch;
        }

        /* Cupom já aplicado: faixa creme (.bloco-creme do style.css), só mais baixa */
        .carrinho-cupom-aplicado {
            margin: 1rem 0 0;
            padding: .75rem 1rem;
            text-wrap: balance;
        }

        /* "Remover cupom" fica embaixo da faixa, sobre o branco: o vermelho de erro
           em cima do creme não tem contraste para ler (3,8:1; no branco dá 4,5:1).
           Numa linha só dele, sempre inteiro, e com a mesma área de toque do
           "Remover" dos itens */
        .carrinho-remover-cupom {
            display: block;
            width: fit-content;
            margin: .25rem auto 0;
            padding: .5rem 0;
        }

        /* Linha pequena que conta de onde veio o cálculo do frete */
        .carrinho-nota {
            margin: .4rem 0 0;
            font-size: .8rem;
            text-align: right;
            opacity: .75;
        }

        .carrinho-finalizar {
            margin-top: 1.5rem;
        }

        /* Abaixo de 1280px o .menu-colunas (style.css) vira uma coluna só:
           aqui ela fica com largura confortável de leitura */
        @media (max-width: 1279px) {
            .carrinho-colunas {
                max-width: 640px;
            }
        }

        /* Computador estreito (769 a 800px): o menu lateral deixa a caixa com
           largura de celular, e o título no tamanho padrão partia "CARRINHO" no meio */
        @media (min-width: 769px) and (max-width: 800px) {
            #carrinho-titulo {
                font-size: 2.5rem;
            }
        }

        /* Celular: botões ocupam a linha toda e o cupom empilha (campo em cima, botão embaixo) */
        @media (max-width: 768px) {
            .carrinho-atualizar .btn {
                display: block;
                width: 100%;
            }

            .carrinho-cupom {
                flex-direction: column;
                align-items: stretch;
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
                        <li><a href="carrinho.php" class="is-active" aria-current="page" aria-label="Ver carrinho de compras">Carrinho</a></li>
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
        <!-- Carrinho vazio: a caixa ganha a placa balançando no canto (caixa--placa), como na home.
             Sem "revealable" (surgir ao rolar): Atualizar, Remover, Adicionar e Cupom recarregam
             a página, e a caixa inteira piscaria a cada clique (a pizza-dupla.php evita isso também) -->
        <section class="caixa<?= empty($_SESSION['carrinho']) ? ' caixa--placa' : '' ?>" aria-labelledby="carrinho-titulo">
            <h1 id="carrinho-titulo">Meu Carrinho</h1>

            <?php if(isset($_SESSION['mensagem_cupom'])): ?>
                <!-- A mesma mensagem serve para cupom aceito e recusado: quando o texto é o de
                     cupom inválido, o aviso usa o estilo de erro (só muda a aparência) -->
                <div class="alert <?= str_contains($_SESSION['mensagem_cupom'], 'inválido') ? 'alert-danger' : 'alert-success' ?> carrinho-aviso" role="status"><?php echo e($_SESSION['mensagem_cupom']); unset($_SESSION['mensagem_cupom']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['mensagem_extra'])): ?>
                <div class="alert alert-success carrinho-aviso" role="status"><?php echo e($_SESSION['mensagem_extra']); unset($_SESSION['mensagem_extra']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['pedido_erro'])): ?>
                <div class="alert alert-danger carrinho-aviso" role="alert"><?php echo htmlspecialchars($_SESSION['pedido_erro']); unset($_SESSION['pedido_erro']); ?></div>
            <?php endif; ?>

            <?php if(empty($_SESSION['carrinho'])): ?>
                <div class="alert alert-info carrinho-aviso">Seu carrinho está vazio! <a href="cardapio.php">Ver cardápio</a></div>
            <?php else: ?>
                <div class="menu-colunas carrinho-colunas">

                    <!-- COLUNA 1: itens do carrinho e adicionais -->
                    <div>
                        <section class="menu-secao" aria-labelledby="itens-titulo">
                            <div class="titulo-linhas"><h2 id="itens-titulo">Itens</h2></div>

                            <!-- Um formulário só para todas as quantidades (botão "Atualizar Carrinho") -->
                            <form method="POST">
                                <?= campo_csrf() ?>
                                <ul class="menu-lista">
                                    <?php foreach($_SESSION['carrinho'] as $index => $item): ?>
                                    <?php
                                    // Só exibição: a pizza meia a meia entra no carrinho com um emoji no começo
                                    // do nome. O visual novo não usa emoji, então ele some na hora de mostrar
                                    // (o nome guardado no carrinho continua o mesmo).
                                    $nome_item = trim(str_replace('🍕', '', $item['nome']));
                                    // Nome no formato "Título: detalhes" (ex.: "Pizza Meia a Meia: Calabresa + Margherita"):
                                    // o título fica na linha do preço e os detalhes descem para a linha de baixo,
                                    // como a descrição do cardápio. Inteiro, o nome ocupava 5 linhas no celular.
                                    $partes_nome = explode(': ', $nome_item, 2);
                                    $titulo_item = $partes_nome[0];
                                    $detalhe_item = $partes_nome[1] ?? '';
                                    ?>
                                    <li class="menu-item carrinho-item">
                                        <div class="menu-item__cabecalho">
                                            <h3 class="menu-item__nome"><?php echo htmlspecialchars($titulo_item); ?></h3>
                                            <span class="menu-item__preco"><span class="sr-only">Subtotal: </span>R$ <?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></span>
                                        </div>
                                        <?php if($detalhe_item !== ''): ?>
                                        <p class="menu-item__descricao"><?php echo htmlspecialchars($detalhe_item); ?></p>
                                        <?php endif; ?>
                                        <p class="menu-item__descricao">Preço unitário: R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></p>
                                        <div class="menu-item__acoes">
                                            <!-- O nome do item vai escondido no rótulo: o leitor de tela diz "Quantidade de Calabresa" -->
                                            <label for="quantidade-<?= (int) $index ?>">Quantidade<span class="sr-only"> de <?= e($nome_item) ?></span></label>
                                            <input type="number" id="quantidade-<?= (int) $index ?>" name="quantidade[<?= (int) $index ?>]" value="<?= (int) $item['quantidade'] ?>" min="0" class="campo-qtd">
                                            <a href="?remove=<?= (int) $index ?>&token=<?= e(csrf_token()) ?>" class="link-perigo" aria-label="Remover <?= e($nome_item) ?> do carrinho">Remover</a>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="carrinho-atualizar">
                                    <button type="submit" name="atualizar" class="btn btn-contorno">Atualizar Carrinho</button>
                                </div>
                            </form>
                        </section>

                        <!-- SEÇÃO DE ADICIONAIS (BEBIDAS + PORÇÃO): cada um tem o seu formulário -->
                        <?php if(!empty($extras)): ?>
                        <section class="menu-secao" aria-labelledby="extras-titulo">
                            <!-- Título curto para caber numa linha (como ITENS, CUPOM e RESUMO);
                                 o que a seção oferece vai na frase de baixo -->
                            <div class="titulo-linhas"><h2 id="extras-titulo">Adicionais</h2></div>
                            <p class="carrinho-descricao">Bebidas ou porção para acompanhar o pedido</p>
                            <ul class="menu-lista">
                                <?php foreach($extras as $extra): ?>
                                <li class="menu-item">
                                    <div class="menu-item__cabecalho">
                                        <h3 class="menu-item__nome"><?php echo htmlspecialchars($extra['nome']); ?></h3>
                                        <span class="menu-item__preco">R$ <?php echo number_format($extra['preco'], 2, ',', '.'); ?></span>
                                    </div>
                                    <form method="POST" class="menu-item__acoes">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="extra_id" value="<?php echo $extra['id']; ?>">
                                        <button type="submit" name="add_extra" class="btn btn-pequeno" aria-label="Adicionar <?= e($extra['nome']) ?> ao carrinho">Adicionar</button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <?php endif; ?>
                    </div>

                    <!-- COLUNA 2: cupom, resumo e pagamento -->
                    <div>
                        <!-- CUPOM DE DESCONTO -->
                        <section class="menu-secao" aria-labelledby="cupom-titulo">
                            <div class="titulo-linhas"><h2 id="cupom-titulo">Cupom</h2></div>
                            <form method="POST" class="carrinho-cupom">
                                <?= campo_csrf() ?>
                                <label for="codigo_cupom" class="sr-only">Código do cupom</label>
                                <input type="text" id="codigo_cupom" name="codigo_cupom" placeholder="Digite seu cupom" class="form-control" autocomplete="off">
                                <button type="submit" name="aplicar_cupom" class="btn btn-contorno">Aplicar Cupom</button>
                            </form>
                            <?php if($cupom_aplicado): ?>
                                <!-- &nbsp; = espaço que não quebra: o traço fica colado no código e o "R$" no valor -->
                                <p class="bloco-creme carrinho-cupom-aplicado">Cupom aplicado: <strong><?= e($cupom_aplicado['codigo']) ?></strong>&nbsp;- Desconto de R$&nbsp;<?php echo number_format($desconto_cupom, 2, ',', '.'); ?></p>
                                <!-- Fora da faixa creme de propósito (contraste do vermelho; ver o <style>) -->
                                <a href="?remover_cupom=1&token=<?= e(csrf_token()) ?>" class="link-perigo carrinho-remover-cupom">Remover cupom</a>
                            <?php endif; ?>
                        </section>

                        <!-- RESUMO DO PEDIDO -->
                        <section class="menu-secao carrinho-resumo" aria-labelledby="resumo-titulo">
                            <div class="titulo-linhas"><h2 id="resumo-titulo">Resumo</h2></div>

                            <div class="linha-dado"><span>Endereço</span><span><?php echo htmlspecialchars($usuario['endereco']); ?></span></div>
                            <div class="linha-dado"><span>Distância</span><span><?= e($distancia) ?> km</span></div>
                            <div class="linha-dado"><span>Taxa de entrega</span><span>R$ <?php echo number_format($taxa_entrega, 2, ',', '.'); ?></span></div>
                            <?php /* Linha discreta: mostra de onde veio o cálculo (útil na demonstração) */ ?>
                            <p class="carrinho-nota">
                                <?php if($entrega['origem'] === 'microservico'): ?>
                                    <i class="far fa-clock" aria-hidden="true"></i> Tempo estimado: ~<?php echo (int) $entrega['tempo_estimado_min']; ?> min · calculado pelo serviço de entrega
                                <?php elseif($entrega['origem'] === 'sem_coordenadas'): ?>
                                    Endereço sem coordenadas · frete padrão de <?= e(ENTREGA_DISTANCIA_PADRAO_KM) ?> km
                                <?php else: ?>
                                    Serviço de entrega indisponível · frete calculado localmente
                                <?php endif; ?>
                            </p>
                            <?php if(!$entrega['dentro_area']): ?>
                            <div class="alert alert-danger mt-1" role="alert"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Seu endereço está fora da nossa área de entrega.</div>
                            <?php endif; ?>
                            <div class="linha-dado"><span>Subtotal</span><span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                            <?php if($desconto_cupom > 0): ?>
                            <div class="linha-dado"><span>Desconto cupom</span><span>- R$ <?php echo number_format($desconto_cupom, 2, ',', '.'); ?></span></div>
                            <?php endif; ?>
                            <div class="linha-dado linha-dado--total"><span>Total</span><span>R$ <?php echo number_format($total, 2, ',', '.'); ?></span></div>

                            <!-- Fechar o pedido: forma de pagamento (e troco, se for dinheiro) -->
                            <form method="POST" action="finalizar-pedido.php" class="carrinho-finalizar">
                                <?= campo_csrf() ?>
                                <div class="form-group">
                                    <label for="forma_pagamento">Forma de pagamento</label>
                                    <select id="forma_pagamento" name="forma_pagamento" required><option value="Dinheiro">Dinheiro</option><option value="Cartão Crédito">Cartão Crédito</option><option value="Cartão Débito">Cartão Débito</option><option value="PIX">PIX</option></select>
                                </div>
                                <!-- O JavaScript do fim da página mostra/esconde este campo pelo style.display -->
                                <div class="form-group" id="trocoGroup" style="display:none;">
                                    <label for="troco_para">Troco para <em>- opcional</em></label>
                                    <input type="number" step="0.01" id="troco_para" name="troco_para">
                                </div>
                                <input type="hidden" name="distancia" value="<?php echo $distancia; ?>">
                                <input type="hidden" name="taxa_entrega" value="<?php echo $taxa_entrega; ?>">
                                <input type="hidden" name="total" value="<?php echo $total; ?>">
                                <input type="hidden" name="desconto_cupom" value="<?php echo $desconto_cupom; ?>">
                                <?php if($cupom_aplicado): ?>
                                <input type="hidden" name="cupom_id" value="<?php echo $cupom_aplicado['id']; ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-bloco" <?php echo $entrega['dentro_area'] ? '' : 'disabled'; ?>>Finalizar Pedido</button>
                            </form>
                        </section>
                    </div>

                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
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