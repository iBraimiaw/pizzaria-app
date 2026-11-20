<?php
require_once 'includes/functions.php';

// ID da categoria: só aceita número inteiro (qualquer outra coisa = "todas")
$categoria_id = filter_var($_GET['categoria'] ?? '', FILTER_VALIDATE_INT) ?: null;
$sql = "SELECT p.*, c.nome as categoria_nome FROM produtos p 
        JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.disponivel = true";

if($categoria_id) {
    $sql .= " AND p.categoria_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria_id]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$produtos = $stmt->fetchAll();

// Buscar categorias
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ff4f1f">
    <title>Cardápio - Pizzaria do Bairro</title>
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
        /* Estilos só do cardápio (o resto vem do assets/css/style.css) */

        /* ---------- Caixa de cima: título, promoção e filtros ---------- */

        /* O conteúdo fica numa coluna central, como o topo da página de referência */
        .cardapio-topo > * {
            max-width: 760px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Promoção: o "PROMOÇÃO ESPECIAL!" vira um rótulo em cima do texto */
        .cardapio-promo {
            margin-bottom: 2.5rem;
        }

        .cardapio-promo strong {
            display: block;
            margin-bottom: .25rem;
            font-size: 1.25rem;
            font-stretch: var(--largura-subtitulo);
            text-transform: uppercase;
        }

        /* (o botão comprido quebra em duas linhas parelhas em telas estreitas: regra do style.css) */
        .cardapio-promo .btn {
            margin: .25rem 0 .75rem;
        }

        /* Texto pequeno embaixo do botão, com o espaçamento de linha da letra menor */
        .cardapio-promo small {
            display: block;
        }

        /* Filtros: pílulas lado a lado, quebrando linha quando não cabem */
        .cardapio-filtros ul {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .6rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        /* Categoria escolhida = pílula preenchida (a classe "active" vem do PHP) */
        .cardapio-filtros .btn.active {
            color: var(--branco);
            background-color: var(--carvao);
        }

        /* ---------- Caixa de baixo: o cardápio ---------- */

        /* Duas colunas que "escorrem" como num jornal: a seção comprida continua
           na outra coluna e cada item fica inteiro (nunca partido ao meio) */
        .cardapio-colunas {
            column-count: 2;
            column-gap: 60px;
            text-align: left;
        }

        /* Uma coluna só, no centro, quando há uma categoria na tela (filtro ativo)
           ou quando a tela não tem largura para duas colunas confortáveis */
        .cardapio-colunas:has(> .menu-secao:only-child) {
            column-count: 1;
            max-width: 700px;
            margin: 0 auto;
        }

        @media (max-width: 1300px) {
            .cardapio-colunas {
                column-count: 1;
                max-width: 700px;
                margin: 0 auto;
            }
        }

        /* O título nunca fica sozinho no pé da coluna, longe dos seus itens */
        .cardapio-colunas .titulo-linhas {
            break-after: avoid;
        }

        /* Título da categoria um pouco menor que o padrão (2rem), para
           "Pizzas Salgadas" caber numa linha só nas colunas mais estreitas */
        .cardapio-colunas .titulo-linhas > * {
            font-size: 1.75rem;
        }

        /* Item: NOME .... PREÇO na linha inteira (como na referência) e, embaixo,
           a foto pequena ao lado da descrição e dos botões */
        .cardapio-item {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr);
            grid-template-areas:
                "cabecalho cabecalho"
                "foto      descricao"
                "foto      acoes";
            column-gap: 1.1rem;
            align-items: start;
            margin-bottom: 2rem;
            break-inside: avoid;
        }

        .cardapio-item .menu-item__cabecalho {
            grid-area: cabecalho;
            margin-bottom: .6rem;
        }

        .cardapio-item .menu-item__descricao {
            grid-area: descricao;
        }

        .cardapio-item .menu-item__acoes {
            grid-area: acoes;
        }

        /* Miniatura estilo "polaroid": moldura branca, sombra e leve inclinação */
        .cardapio-item__foto {
            grid-area: foto;
            width: 72px;
            height: 72px;
            padding: 4px;
            object-fit: cover;
            background: var(--branco);
            box-shadow: var(--sombra);
            transform: rotate(-3deg);
            transition: var(--transicao);
        }

        .cardapio-item:nth-child(even) .cardapio-item__foto {
            transform: rotate(3deg);
        }

        .cardapio-item:hover .cardapio-item__foto {
            transform: rotate(0deg) scale(1.05);
        }

        /* Campo de quantidade na altura do botão "Adicionar": os dois esticam até a
           altura do mais alto da linha (no computador, a do botão pequeno; no celular,
           os 44px de toque do acessibilidade.css), em vez de uma altura fixa no campo */
        .cardapio-item .menu-item__acoes {
            align-items: stretch;
        }

        .cardapio-item .campo-qtd {
            width: 4.5rem;
            height: auto;
            padding: .2rem .5rem;
        }

        /* Celular: títulos menores (cabem numa linha, com as linhas laranja dos lados),
           foto menor e os botões na linha de baixo, com a largura toda */
        @media (max-width: 768px) {
            .cardapio-promo strong {
                font-size: 1rem;
            }

            .cardapio-colunas .titulo-linhas > * {
                font-size: clamp(1.1rem, 5.9vw, 1.4rem);
            }

            .cardapio-item {
                grid-template-columns: 60px minmax(0, 1fr);
                grid-template-areas:
                    "cabecalho cabecalho"
                    "foto      descricao"
                    "acoes     acoes";
                column-gap: .9rem;
            }

            .cardapio-item__foto {
                width: 60px;
                height: 60px;
                padding: 3px;
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
                    <li class="site-nav__grupo is-open">
                        <button type="button" class="site-nav__alternar" aria-expanded="true" aria-controls="subCardapio">
                            Cardápio <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="site-nav__sub" id="subCardapio">
                            <ul>
                                <li><a href="cardapio.php" class="is-active" aria-current="page">Todos os sabores</a></li>
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
        <?php
        // Só exibição: o SELECT do topo não tem ORDER BY e o banco devolve as
        // categorias em ordem alfabética (Bebidas primeiro). Esta CÓPIA segue a
        // ordem de cadastro (id), para o cardápio começar pelas pizzas.
        $categorias_cardapio = $categorias;
        usort($categorias_cardapio, fn($primeira, $segunda) => $primeira['id'] <=> $segunda['id']);
        ?>

        <!-- Caixa 1: título, promoção e filtros (com a placa balançando no canto).
             Sem "revealable": ela já está na tela quando a página abre, e cada filtro ou
             "Adicionar" recarrega a página; a animação repetida deixava o filtro lento. -->
        <section class="caixa caixa--placa cardapio-topo" aria-labelledby="cardapio-titulo">
            <h1 id="cardapio-titulo">Nosso Cardápio</h1>
            <p class="h3 mb-2">As melhores pizzas de Campo Grande - MS</p>

            <div class="bloco-creme cardapio-promo">
                <p><strong>PROMOÇÃO ESPECIAL!</strong> Pizza Dois Sabores pelo PREÇO MÉDIO! Escolha dois sabores e pague a média!</p>
                <a href="pizza-dupla.php" class="btn">Monte sua pizza com dois sabores!</a>
                <small>Escolha dois sabores diferentes e pague apenas a média dos preços!</small>
            </div>

            <!-- Filtros: a pílula da categoria escolhida fica preenchida -->
            <nav class="cardapio-filtros" aria-label="Filtrar o cardápio por categoria">
                <ul>
                    <li><a href="cardapio.php" class="btn btn-pequeno btn-contorno <?php echo !$categoria_id ? 'active' : ''; ?>" <?= !$categoria_id ? 'aria-current="page"' : '' ?>>Todos</a></li>
                    <?php foreach($categorias_cardapio as $cat): ?>
                        <li>
                            <a href="?categoria=<?= (int) $cat['id'] ?>" class="btn btn-pequeno btn-contorno <?php echo $categoria_id == $cat['id'] ? 'active' : ''; ?>" <?= $categoria_id == $cat['id'] ? 'aria-current="page"' : '' ?>>
                                <?= e($cat['nome']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </section>

        <!-- Caixa 2: o cardápio separado por categoria (título com linha laranja dos lados).
             Aqui quem surge ao rolar são os títulos e os itens, não a caixa inteira: ela é
             tão alta no celular que ficaria um buraco vazio até aparecer -->
        <section class="caixa cardapio-menu" aria-label="Produtos do cardápio">
            <?php if(!$produtos): ?>
                <p>Nenhum produto disponível por aqui no momento. <a href="cardapio.php">Ver todo o cardápio</a></p>
            <?php else: ?>
                <div class="cardapio-colunas">
                    <?php foreach($categorias_cardapio as $categoria): ?>
                        <?php
                        // Só exibição: separa os produtos desta categoria (a lista veio da
                        // consulta do topo). Com filtro ativo as outras ficam vazias e somem.
                        $produtos_da_categoria = array_filter($produtos, fn($item) => $item['categoria_id'] == $categoria['id']);
                        ?>
                        <?php if($produtos_da_categoria): ?>
                        <section class="menu-secao" aria-labelledby="categoria-<?= (int) $categoria['id'] ?>">
                            <div class="titulo-linhas revealable"><h2 id="categoria-<?= (int) $categoria['id'] ?>"><?= e($categoria['nome']) ?></h2></div>
                            <ul class="menu-lista">
                                <?php foreach($produtos_da_categoria as $produto): ?>
                                <li class="menu-item cardapio-item revealable">
                                    <div class="menu-item__cabecalho">
                                        <h3 class="menu-item__nome"><?= e($produto['nome']) ?></h3>
                                        <span class="menu-item__preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></span>
                                    </div>
                                    <img src="<?= e($produto['imagem_url']) ?>"
                                         alt="<?= e($produto['nome']) ?>"
                                         class="cardapio-item__foto"
                                         width="72" height="72"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='assets/img/sem-foto.svg'">
                                    <p class="menu-item__descricao"><?= e($produto['descricao']) ?></p>

                                    <?php if(isset($_SESSION['usuario_id'])): ?>
                                        <form method="POST" action="carrinho.php" class="menu-item__acoes">
                                            <?= campo_csrf() ?>
                                            <?php /* Só o ID vai no form: nome e preço o servidor busca no banco */ ?>
                                            <input type="hidden" name="produto_id" value="<?= (int) $produto['id'] ?>">
                                            <label for="quantidade-<?= (int) $produto['id'] ?>" class="sr-only">Quantidade de <?= e($produto['nome']) ?></label>
                                            <input type="number" id="quantidade-<?= (int) $produto['id'] ?>" name="quantidade" value="1" min="1" class="campo-qtd">
                                            <button type="submit" name="add_carrinho" class="btn btn-pequeno" aria-label="Adicionar <?= e($produto['nome']) ?> ao carrinho">
                                                Adicionar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="menu-item__acoes">
                                            <a href="login.php" class="btn btn-pequeno btn-contorno">Fazer login para pedir</a>
                                        </div>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
</body>
</html>
