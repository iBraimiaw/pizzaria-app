<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Buscar apenas pizzas salgadas para os sabores
$stmt = $pdo->prepare("
    SELECT p.* FROM produtos p 
    JOIN categorias c ON p.categoria_id = c.id 
    WHERE c.nome = 'Pizzas Salgadas' AND p.disponivel = true
    ORDER BY p.nome
");
$stmt->execute();
$pizzas = $stmt->fetchAll();

// IDs dos sabores: só números inteiros (qualquer outra coisa vira null)
$sabor1_id = filter_var($_GET['sabor1'] ?? '', FILTER_VALIDATE_INT) ?: null;
$sabor2_id = filter_var($_GET['sabor2'] ?? '', FILTER_VALIDATE_INT) ?: null;
$preco_calculado = 0;

/** Procura o sabor na lista de pizzas salgadas disponíveis (carregada do banco acima). */
function buscarSabor(array $pizzas, $sabor_id): ?array
{
    foreach ($pizzas as $pizza) {
        if ((int) $pizza['id'] === (int) $sabor_id) {
            return $pizza;
        }
    }
    return null;
}

if($sabor1_id && $sabor2_id) {
    $sabor1 = buscarSabor($pizzas, $sabor1_id);
    $sabor2 = buscarSabor($pizzas, $sabor2_id);
    
    if($sabor1 && $sabor2) {
        // Calcular média dos preços
        $preco_calculado = ($sabor1['preco'] + $sabor2['preco']) / 2;
    }
}

// Adicionar ao carrinho
if(isset($_POST['adicionar_carrinho'])) {
    verificar_csrf();

    $sabor1 = buscarSabor($pizzas, $_POST['sabor1'] ?? 0);
    $sabor2 = buscarSabor($pizzas, $_POST['sabor2'] ?? 0);
    $quantidade = filter_var($_POST['quantidade'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);

    // Sabores precisam existir, ser diferentes, e a quantidade ser válida
    if(!$sabor1 || !$sabor2 || $sabor1['id'] == $sabor2['id'] || !$quantidade) {
        header('Location: pizza-dupla.php');
        exit;
    }

    $nome1 = $sabor1['nome'];
    $nome2 = $sabor2['nome'];
    $nome_personalizado = "🍕 Pizza Meia a Meia: $nome1 + $nome2";
    
    // Salvar na sessão com informações especiais
    $item_carrinho = [
        'tipo' => 'personalizada',
        'sabor1_id' => (int) $sabor1['id'],
        'sabor2_id' => (int) $sabor2['id'],
        'sabor1_nome' => $nome1,
        'sabor2_nome' => $nome2,
        'nome' => $nome_personalizado,
        // Preço calculado NO SERVIDOR (média dos preços do banco).
        // O campo "preco_total" do formulário é ignorado: o usuário poderia alterá-lo.
        'preco' => round(($sabor1['preco'] + $sabor2['preco']) / 2, 2),
        'quantidade' => $quantidade
    ];
    
    if (!isset($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }
    
    $_SESSION['carrinho'][] = $item_carrinho;
    
    header('Location: carrinho.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ff4f1f">
    <title>Pizza Dois Sabores - Pizzaria do Bairro</title>
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

        /* Desenho da pizza: rótulo do 1º sabor | pizza | rótulo do 2º sabor */
        .pizza-visual {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 1.5rem;
            margin: 2rem 0;
        }

        /* A pizza: borda escura, um anel de "massa" (o padding, em creme forte) e, dentro,
           as duas metades: ::before (laranja, 1º sabor) e ::after (creme, 2º sabor).
           Cor chapada, sem gradiente. */
        .pizza-circle {
            display: flex;
            width: 11rem;
            height: 11rem;
            padding: 9px;
            background: var(--creme-forte);
            border: 4px solid var(--carvao);
            border-radius: 50%;
        }

        .pizza-circle::before,
        .pizza-circle::after {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.25rem;
            font-weight: 900;
            font-stretch: var(--largura-titulo);
            line-height: 1;
            border: 3px solid var(--carvao);
        }

        /* O raio "100% / 50%" arredonda só o lado de fora: cada metade vira um meio círculo.
           As bordas de dentro (2px + 2px) formam o "corte" no meio da pizza. */
        .pizza-circle::before {
            content: '1';
            color: var(--branco);
            background: var(--laranja);
            border-right-width: 2px;
            border-radius: 100% 0 0 100% / 50% 0 0 50%;
        }

        .pizza-circle::after {
            content: '2';
            color: var(--carvao);
            background: var(--creme);
            border-left-width: 2px;
            border-radius: 0 100% 100% 0 / 0 50% 50% 0;
        }

        /* Rótulo com o sabor de cada metade. É informação, não botão: por isso é um
           "carimbo" de canto reto e levemente torto (como a etiqueta de situação dos
           pedidos), e não uma pílula escura igual ao botão "Adicionar ao carrinho" */
        .pizza-label {
            min-width: 6.5rem;
            max-width: 100%;
            padding: .4rem 1.1rem .35rem;
            font-size: .8rem;
            font-weight: 700;
            font-stretch: var(--largura-rotulo);
            line-height: 1.3;
            letter-spacing: 1px;
            text-align: center;
            text-transform: uppercase;
            overflow-wrap: anywhere;
            color: var(--carvao);
            background: var(--branco);
            border: 2px solid var(--carvao);
        }

        .label-left { justify-self: end; transform: rotate(-2deg); }
        .label-right { justify-self: start; transform: rotate(2deg); }

        /* Metade que ainda não tem sabor: carimbo vazado, de borda tracejada */
        .pizza-label--vazio {
            color: var(--carvao);
            background: transparent;
            border-style: dashed;
        }

        /* Aviso de sabores iguais: centralizado como o resto da página
           (o .alert do style.css é alinhado à esquerda) */
        .info-preco.alert {
            text-align: center;
        }

        /* Bloco do preço: o valor final é o maior texto do bloco */
        .info-preco p {
            margin-bottom: .25rem;
        }

        /* Texto pequeno em duas linhas (celular) não fica com as linhas muito afastadas */
        .info-preco small {
            display: inline-block;
            line-height: 1.4;
        }

        .preco-calculado {
            margin-bottom: .25rem;
            font-size: 2.75rem;
            font-weight: 900;
            font-stretch: var(--largura-titulo);
            line-height: 1.2;
            color: var(--tinta);
        }

        /* Quantidade e botão lado a lado; se não couber, o botão desce */
        .form-adicionar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem 1.5rem;
            margin-top: 1.25rem;
        }

        .quantidade {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .quantidade label {
            font-size: .8rem;
            font-weight: 700;
        }

        /* (o botão "Adicionar ao Carrinho - R$ X" quebra em duas linhas parelhas em
           telas estreitas: regra do .btn no style.css) */

        /* Títulos das duas colunas: um pouco mais perto da lista de sabores */
        .selecao-sabores .titulo-linhas {
            margin-bottom: 1rem;
        }

        /* Cada sabor é uma linha clicável com cara de item de cardápio.
           A margem negativa deixa o fundo "vazar" um pouco para os lados, e assim
           o pontilhado continua alinhado com as linhas laranja do título. */
        .sabor-card {
            margin: 0 calc(-.75rem - 2px);
            padding: .45rem .75rem;
            border: 2px solid transparent;
            transition: var(--transicao);
        }

        .sabor-card .menu-item__cabecalho {
            font-size: 1.1rem;
        }

        /* Bolinha de marcar no começo da linha (lembra um botão de rádio) */
        .sabor-card .menu-item__cabecalho::before {
            content: '';
            flex: none;
            align-self: flex-start;
            width: 1.15rem;
            height: 1.15rem;
            margin: .25rem -.35rem 0 0;
            font-size: .65rem;
            line-height: calc(1.15rem - 4px);
            text-align: center;
            color: var(--branco);
            background: var(--branco);
            border: 2px solid var(--carvao);
            border-radius: 50%;
        }

        /* O nome ocupa o espaço que sobrar e empurra o preço para a direita */
        .sabor-nome {
            flex: 1;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .sabor-card:hover {
            background: var(--creme);
        }

        .sabor-card:active {
            background: var(--creme-forte);
        }

        /* Sabor escolhido: fundo creme, moldura escura e bolinha marcada */
        .sabor-card.selected {
            background: var(--creme);
            border-color: var(--carvao);
        }

        .sabor-card.selected .menu-item__cabecalho::before {
            content: '\f00c'; /* ícone "check" do Font Awesome */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            background: var(--carvao);
        }

        /* Abaixo de 1280px o .menu-colunas (style.css) vira uma coluna só, para os
           nomes não quebrarem no meio da palavra ("CALABRE-SA"). Aqui essa coluna
           fica centralizada e com largura de cardápio. */
        @media (max-width: 1279px) {
            .selecao-sabores.menu-colunas {
                max-width: 30rem;
                margin: 0 auto;
            }
        }

        /* Tablet e celular: a pizza fica em cima e cada rótulo embaixo da sua metade */
        @media (max-width: 1024px) {
            /* A largura máxima segura os rótulos perto da pizza em tela mais larga (tablet) */
            .pizza-visual {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 1rem .75rem;
                max-width: 24rem;
                margin-left: auto;
                margin-right: auto;
            }

            .pizza-circle {
                grid-row: 1;
                grid-column: 1 / -1;
                justify-self: center;
            }

            .pizza-label {
                grid-row: 2;
                justify-self: center;
                text-align: center;
            }
        }

        /* Celular e janela estreita com o menu lateral (até 820px): a coluna fica estreita
           e o título dela diminui para continuar numa linha só, com as linhas dos lados */
        @media (max-width: 820px) {
            .selecao-sabores .titulo-linhas > * {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .pizza-visual {
                gap: 1rem .5rem;
                margin-top: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .pizza-circle {
                width: 9rem;
                height: 9rem;
                padding: 7px;
            }

            .pizza-circle::before,
            .pizza-circle::after {
                font-size: 2.6rem;
            }

            /* Rótulo mais compacto: um nome comprido quebra em 2 linhas, e não em 3 */
            .pizza-label {
                padding: .4rem .85rem .35rem;
                font-size: .7rem;
            }

            .preco-calculado {
                font-size: 2.25rem;
            }

            /* A caixa tem menos respiro no celular: o fundo da linha vaza menos */
            .sabor-card {
                margin: 0 calc(-.5rem - 2px);
                padding: .45rem .5rem;
            }

            .sabor-card .menu-item__cabecalho {
                font-size: 1rem;
            }

            /* No celular o botão ocupa a linha toda para ficar fácil de tocar.
               A largura máxima evita uma pílula enorme no tablet em pé. */
            .btn-adicionar {
                width: 100%;
                max-width: 24rem;
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
                    <!-- Esta página fica dentro de "Cardápio": o grupo já nasce aberto -->
                    <li class="site-nav__grupo is-open">
                        <button type="button" class="site-nav__alternar" aria-expanded="true" aria-controls="subCardapio">
                            Cardápio <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="site-nav__sub" id="subCardapio">
                            <ul>
                                <li><a href="cardapio.php">Todos os sabores</a></li>
                                <li><a href="pizza-dupla.php" class="is-active" aria-current="page">Pizza dois sabores</a></li>
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

        <!-- A caixa só "surge" (revealable) na primeira visita. Escolher um sabor recarrega
             a página, e repetir a animação a cada clique deixaria a escolha lenta. -->
        <section class="caixa<?= ($sabor1_id || $sabor2_id) ? '' : ' revealable' ?>" aria-labelledby="tituloPagina">
            <h1 id="tituloPagina">Pizza Dois Sabores</h1>
            <p>Escolha dois sabores e monte sua pizza perfeita!</p>

            <!-- Desenho da pizza. O JavaScript troca o texto dos rótulos pelo id
                 (labelSabor1 / labelSabor2), então dentro deles só pode ter o nome do sabor. -->
            <div class="pizza-visual">
                <span class="sr-only">Primeiro sabor:</span>
                <div class="pizza-label label-left<?= $sabor1_id ? '' : ' pizza-label--vazio' ?>" id="labelSabor1">
                    <?= $sabor1_id ? e(buscarSabor($pizzas, $sabor1_id)['nome'] ?? 'Sabor 1') : '???' ?>
                </div>
                <div class="pizza-circle" aria-hidden="true"></div>
                <span class="sr-only">Segundo sabor:</span>
                <div class="pizza-label label-right<?= $sabor2_id ? '' : ' pizza-label--vazio' ?>" id="labelSabor2">
                    <?= $sabor2_id ? e(buscarSabor($pizzas, $sabor2_id)['nome'] ?? 'Sabor 2') : '???' ?>
                </div>
            </div>

            <!-- Resultado da escolha. Fica logo abaixo da pizza (antes das listas) porque a página
                 volta para o topo a cada sabor escolhido: assim o preço e o botão aparecem sem rolar. -->
            <?php if($sabor1_id && $sabor2_id): ?>
                <?php if($sabor1_id == $sabor2_id): ?>
                    <div class="info-preco alert alert-danger" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Por favor, escolha dois sabores DIFERENTES!
                    </div>
                <?php else: ?>
                    <div class="info-preco bloco-creme">
                        <p><strong>Preço Original:</strong> R$ <?php
                            $preco1 = buscarSabor($pizzas, $sabor1_id)['preco'] ?? 0;
                            $preco2 = buscarSabor($pizzas, $sabor2_id)['preco'] ?? 0;
                            echo number_format($preco1, 2, ',', '.') . ' + ' . number_format($preco2, 2, ',', '.');
                        ?></p>
                        <p><strong>Você paga apenas a MÉDIA:</strong></p>
                        <div class="preco-calculado">
                            R$ <?php echo number_format($preco_calculado, 2, ',', '.'); ?>
                        </div>
                        <p><small><i class="fas fa-info-circle" aria-hidden="true"></i> Promoção: Pizza dois sabores pelo preço médio!</small></p>

                        <!-- O preço que vale é o que o servidor calcula de novo ao receber este formulário -->
                        <form method="POST" class="form-adicionar">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="sabor1" value="<?= (int) $sabor1_id ?>">
                            <input type="hidden" name="sabor2" value="<?= (int) $sabor2_id ?>">
                            <input type="hidden" name="preco_total" value="<?php echo $preco_calculado; ?>">

                            <div class="quantidade">
                                <label for="quantidade">Quantidade:</label>
                                <input type="number" name="quantidade" id="quantidade" class="campo-qtd" value="1" min="1" max="10">
                            </div>

                            <button type="submit" name="adicionar_carrinho" class="btn btn-adicionar">
                                Adicionar ao Carrinho - R$ <?php echo number_format($preco_calculado, 2, ',', '.'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-preco bloco-creme">
                    <p><i class="fas fa-hand-pointer" aria-hidden="true"></i> Clique em um sabor de cada lado para montar sua pizza!</p>
                </div>
            <?php endif; ?>

            <!-- Escolha dos sabores. Clicar numa linha chama selecionarSabor(), que guarda o id
                 nos campos escondidos e envia este formulário (GET) para recalcular o preço. -->
            <form method="GET" id="formSabores">
                <div class="selecao-sabores menu-colunas">
                    <div class="menu-secao">
                        <div class="titulo-linhas"><h2 id="tituloSabor1">Primeiro Sabor</h2></div>
                        <div id="sabores1" role="group" aria-labelledby="tituloSabor1">
                            <?php foreach($pizzas as $pizza): ?>
                                <div class="sabor-card <?php echo $sabor1_id == $pizza['id'] ? 'selected' : ''; ?>"
                                     role="button" tabindex="0" aria-pressed="<?= $sabor1_id == $pizza['id'] ? 'true' : 'false' ?>"
                                     onclick="selecionarSabor(1, <?= (int) $pizza['id'] ?>, <?= e(json_encode($pizza['nome'])) ?>)">
                                    <div class="menu-item__cabecalho">
                                        <span class="sabor-nome menu-item__nome"><?= e($pizza['nome']) ?></span>
                                        <span class="sabor-preco menu-item__preco">R$ <?php echo number_format($pizza['preco'], 2, ',', '.'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="menu-secao">
                        <div class="titulo-linhas"><h2 id="tituloSabor2">Segundo Sabor</h2></div>
                        <div id="sabores2" role="group" aria-labelledby="tituloSabor2">
                            <?php foreach($pizzas as $pizza): ?>
                                <div class="sabor-card <?php echo $sabor2_id == $pizza['id'] ? 'selected' : ''; ?>"
                                     role="button" tabindex="0" aria-pressed="<?= $sabor2_id == $pizza['id'] ? 'true' : 'false' ?>"
                                     onclick="selecionarSabor(2, <?= (int) $pizza['id'] ?>, <?= e(json_encode($pizza['nome'])) ?>)">
                                    <div class="menu-item__cabecalho">
                                        <span class="sabor-nome menu-item__nome"><?= e($pizza['nome']) ?></span>
                                        <span class="sabor-preco menu-item__preco">R$ <?php echo number_format($pizza['preco'], 2, ',', '.'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="sabor1" id="sabor1" value="<?= $sabor1_id ? (int) $sabor1_id : '' ?>">
                <input type="hidden" name="sabor2" id="sabor2" value="<?= $sabor2_id ? (int) $sabor2_id : '' ?>">
            </form>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
    <script>
        // Chamada pelo onclick de cada linha de sabor (.sabor-card).
        // "event.currentTarget" é a própria linha clicada: por isso a chamada
        // precisa continuar no onclick da linha, e não em outro elemento.
        function selecionarSabor(lado, id, nome) {
            if(lado === 1) {
                document.getElementById('sabor1').value = id;
                document.getElementById('labelSabor1').textContent = nome;
                // Remover seleção anterior
                document.querySelectorAll('#sabores1 .sabor-card').forEach(card => {
                    card.classList.remove('selected');
                });
                // Adicionar seleção no clicado
                event.currentTarget.classList.add('selected');
            } else {
                document.getElementById('sabor2').value = id;
                document.getElementById('labelSabor2').textContent = nome;
                // Remover seleção anterior
                document.querySelectorAll('#sabores2 .sabor-card').forEach(card => {
                    card.classList.remove('selected');
                });
                // Adicionar seleção no clicado
                event.currentTarget.classList.add('selected');
            }

            // Submeter o formulário para atualizar o preço
            document.getElementById('formSabores').submit();
        }

        // Teclado: escolher um sabor recarrega a página e o foco voltaria para o topo
        // (link de pular, logo, menu...). Quando a escolha veio do teclado (Enter ou
        // Espaço viram um clique com "detail" 0; o do mouse tem 1), guardamos um aviso
        // e, na volta, o foco vai direto para o próximo passo. Com o mouse nada muda.
        var AVISO_TECLADO = 'pizzaDuplaEscolhaTeclado';

        // "true" = escuta antes do onclick da linha, que já envia o formulário
        document.getElementById('formSabores').addEventListener('click', function (evento) {
            if (evento.detail === 0 && evento.target.closest('.sabor-card')) {
                try { sessionStorage.setItem(AVISO_TECLADO, '1'); } catch (erro) { /* sem sessionStorage: só não move o foco */ }
            }
        }, true);

        (function () {
            var veioDoTeclado = false;
            try {
                veioDoTeclado = sessionStorage.getItem(AVISO_TECLADO) === '1';
                sessionStorage.removeItem(AVISO_TECLADO);
            } catch (erro) { /* navegador sem sessionStorage */ }
            if (!veioDoTeclado) { return; }

            var sabor1 = document.getElementById('sabor1').value;
            var sabor2 = document.getElementById('sabor2').value;
            var proximo;
            if (sabor1 && sabor2) {
                // Os dois escolhidos: botão de adicionar (com sabores iguais ele não
                // existe, então volta para o 2º sabor, que precisa ser trocado)
                proximo = document.querySelector('.btn-adicionar') || document.querySelector('#sabores2 .sabor-card.selected');
            } else if (sabor1) {
                proximo = document.querySelector('#sabores2 .sabor-card');
            } else {
                proximo = document.querySelector('#sabores1 .sabor-card');
            }
            if (proximo) { proximo.focus(); }
        })();
    </script>
</body>
</html>
