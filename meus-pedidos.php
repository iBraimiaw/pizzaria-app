<?php
require_once 'includes/functions.php';

if(!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar pedidos do usuário
$stmt = $pdo->prepare("
    SELECT p.*, 
           COUNT(i.id) as total_itens 
    FROM pedidos p 
    LEFT JOIN itens_pedido i ON p.id = i.pedido_id 
    WHERE p.usuario_id = ? 
    GROUP BY p.id 
    ORDER BY p.data_pedido DESC
");
$stmt->execute([$usuario_id]);
$pedidos = $stmt->fetchAll();

// Mapeamento de status
$status_map = [
    'PENDENTE' => ['badge' => 'warning', 'texto' => '⏳ Pendente', 'cor' => '#ffc107'],
    'PREPARANDO' => ['badge' => 'info', 'texto' => '🍕 Preparando', 'cor' => '#17a2b8'],
    'SAIU PARA ENTREGA' => ['badge' => 'primary', 'texto' => '🚚 Saiu para entrega', 'cor' => '#007bff'],
    'ENTREGUE' => ['badge' => 'success', 'texto' => '✅ Entregue', 'cor' => '#28a745'],
    'CANCELADO' => ['badge' => 'danger', 'texto' => '❌ Cancelado', 'cor' => '#dc3545']
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ff4f1f">
    <title>Meus Pedidos - Pizzaria do Bairro</title>
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

        /* ----- Lista de pedidos ----- */

        /* Uma coluna no celular e no tablet; duas no computador (mais embaixo),
           como as colunas do cardápio do site de referência */
        .pedidos {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 2.5rem 60px;
            max-width: 640px;
            margin: 2.5rem auto 0;      /* respiro entre o título e o primeiro pedido */
        }

        /* Quem separa um pedido do outro é o "gap" da grade, não a margem do item.
           Em coluna (flex) para o rodapé descer até o fim do bloco: lado a lado,
           dois pedidos ficam com a etiqueta e o botão na mesma altura */
        .pedido {
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }

        /* Linhas "rótulo ..... valor" (endereço, distância...).
           Usadas em cada pedido e também dentro da janela de detalhes */
        .dados {
            margin: 0;
        }

        /* (o desenho do <dt> e do <dd> de cada .linha-dado vem do style.css) */

        /* Etiqueta do status de um lado, botão "Ver detalhes" do outro */
        .pedido__rodape {
            justify-content: space-between;
            margin-top: auto;
            padding-top: 1rem;
        }

        /* Celular com etiqueta comprida ("Saiu para entrega"): se o botão cair
           para a linha de baixo, ele continua encostado na direita */
        .pedido__rodape .btn {
            margin-left: auto;
        }

        /* ----- Janela de detalhes (o JavaScript monta o conteúdo) ----- */

        /* Janela aberta = página de trás parada. O JavaScript abre a janela pondo
           style="display: block" nela, e é isso que o seletor abaixo "enxerga" */
        html:has(#detalhesModal[style*="display: block"]) {
            overflow: hidden;
        }

        /* A janela surge descendo um pouquinho */
        #detalhesModal .modal-content {
            animation: janela-surge .3s ease-out;
        }

        /* "×" com área de toque confortável para o dedo */
        #detalhesModal .close {
            flex: none;
            width: 44px;
            height: 44px;
            margin: -.5rem -.5rem 0 0;
            padding: 0;
        }

        /* Título "Itens do Pedido": menor que os títulos de seção do cardápio */
        #detalhesConteudo .titulo-linhas {
            margin: 2rem 0 .5rem;
        }

        #detalhesConteudo .titulo-linhas > * {
            font-size: clamp(1rem, 4.6vw, 1.25rem);
        }

        /* Tabela alinhada com as linhas de cima: sem recuo nas duas pontas */
        #detalhesConteudo .tabela th:first-child,
        #detalhesConteudo .tabela td:first-child {
            padding-left: 0;
            overflow-wrap: anywhere;
        }

        #detalhesConteudo .tabela th:last-child,
        #detalhesConteudo .tabela td:last-child {
            padding-right: 0;
        }

        /* "2 × R$ 44.90" embaixo do nome do produto: só aparece no celular */
        .item-conta {
            display: none;
        }

        /* Progresso do pedido: uma bolinha numerada para cada etapa */
        .progresso-pedido {
            margin: 2rem 0 0;
        }

        .progresso-passos {
            display: flex;
            margin: 1.25rem 0 0;
            padding: 0;
            list-style: none;
        }

        .passo {
            position: relative;
            flex: 1;
        }

        .passo .circulo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin: 0 auto .5rem;
            font-weight: 700;
            color: var(--carvao);
            background: var(--branco);
            border: 2px solid var(--borda-campo);
            border-radius: 50%;
        }

        /* Etapa que já passou: escura, com o "check" */
        .passo.completo .circulo {
            color: var(--branco);
            background: var(--carvao);
            border-color: var(--carvao);
        }

        /* Etapa atual: laranja e pulsando. Número em carvão: branco sobre o
           laranja fica sem contraste para ler (3,3:1; o mínimo é 4,5:1) */
        .passo.ativo .circulo {
            color: var(--carvao);
            background: var(--laranja);
            border-color: var(--laranja);
            animation: pulsar 1.6s ease-in-out infinite;
        }

        /* Risco que liga uma bolinha à próxima: pontilhado até a etapa passar */
        .passo .linha {
            position: absolute;
            top: 19px;
            left: calc(50% + 26px);
            width: calc(100% - 52px);
            border: 0 dotted var(--borda-campo);
            border-top-width: 2px;
        }

        .passo.completo .linha {
            border-style: solid;
            border-color: var(--carvao);
        }

        .passo:last-child .linha {
            display: none;
        }

        .passo-texto {
            padding: 0 .25rem;
            font-size: .75rem;
            font-weight: 700;
            font-stretch: var(--largura-rotulo);
            line-height: 1.3;
            letter-spacing: 1px;
        }

        @keyframes janela-surge {
            from { opacity: 0; transform: translateY(-20px); }
        }

        @keyframes pulsar {
            50% { transform: scale(1.12); }
        }

        /* Computador: duas colunas de pedidos */
        @media (min-width: 1280px) {
            .pedidos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                max-width: none;
            }

            /* Um pedido só: fica no meio em vez de deixar metade da caixa vazia */
            .pedido:only-child {
                grid-column: 1 / -1;
                justify-self: center;
                width: 100%;
                max-width: 640px;
            }
        }

        /* Celular: tela estreita demais para o endereço ao lado do rótulo e, na
           janela de detalhes, para 4 colunas e 4 bolinhas lado a lado */
        @media (max-width: 600px) {
            /* Endereço: rótulo em cima e valor embaixo (igual ao comprovante do
               pedido), senão ele fica espremido em 3 linhas alinhadas à direita */
            .dados .linha-longa {
                display: block;
            }

            .dados .linha-longa dd {
                margin-top: .15rem;
                text-align: left;
            }

            /* Quantidade e preço saem das colunas e viram a linha embaixo do nome */
            .tabela .col-extra {
                display: none;
            }

            .item-conta {
                display: block;
                font-size: .875rem;
            }

            /* Etapas uma embaixo da outra: bolinha à esquerda, nome à direita */
            .progresso-passos {
                flex-direction: column;
                width: fit-content;
                margin-left: auto;
                margin-right: auto;
            }

            .passo {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding-bottom: 1.25rem;
                text-align: left;
            }

            .passo:last-child {
                padding-bottom: 0;
            }

            .passo .circulo {
                flex: none;
                margin: 0;
            }

            .passo .linha {
                top: 42px;
                left: 19px;
                width: 0;
                height: calc(100% - 44px);
                border-top-width: 0;
                border-left-width: 2px;
            }

            .passo-texto {
                padding: 0;
                font-size: .8rem;
            }
        }

        /* Celular estreito, e computador estreito (769 a 820px, onde o menu lateral
           deixa a caixa com largura de celular): "PEDIDO #000012 ..... R$ 108,80"
           continua numa linha só */
        @media (max-width: 380px), (min-width: 769px) and (max-width: 820px) {
            .pedido .menu-item__cabecalho {
                font-size: .95rem;
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
                        <li><a href="meus-pedidos.php" class="is-active" aria-current="page">Meus Pedidos</a></li>
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
        <!-- Caixa branca sobre o xadrez. Sem nenhum pedido ela ganha a placa
             balançando no canto (caixa--placa), para não ficar vazia. -->
        <section class="caixa<?= empty($pedidos) ? ' caixa--placa' : '' ?>" aria-labelledby="pedidos-titulo">
            <h1 id="pedidos-titulo">Meus Pedidos</h1>

            <?php if(empty($pedidos)): ?>
                <p>Você ainda não fez nenhum pedido!</p>
                <a href="cardapio.php" class="btn">Fazer primeiro pedido</a>
            <?php else: ?>
                <!-- Cada pedido é um item no estilo do cardápio:
                     NÚMERO ........ TOTAL, com a data e os dados embaixo -->
                <ul class="menu-lista pedidos">
                    <?php foreach($pedidos as $pedido): ?>
                        <?php
                        // Só exibição: o texto do status vem do $status_map com um emoji
                        // na frente (ex.: ampulheta + "Pendente"). O visual novo não usa
                        // emoji, então aqui sai tudo o que vem antes da primeira letra
                        // (\p{L} = "qualquer letra"). O array lá em cima continua igual.
                        $status_texto = preg_replace('/^[^\p{L}]+/u', '', $status_map[$pedido['status']]['texto']);
                        ?>
                        <li class="menu-item pedido revealable">
                            <div class="menu-item__cabecalho">
                                <h2 class="menu-item__nome">Pedido #<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?></h2>
                                <span class="menu-item__preco">R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></span>
                            </div>
                            <p class="menu-item__descricao">
                                <?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?>
                                às <?php echo date('H:i', strtotime($pedido['data_pedido'])); ?>
                            </p>

                            <dl class="dados">
                                <div class="linha-dado linha-longa">
                                    <dt>Endereço</dt>
                                    <dd><?= e($pedido['endereco_entrega']) ?></dd>
                                </div>
                                <div class="linha-dado">
                                    <dt>Distância</dt>
                                    <dd><?= e($pedido['distancia_km']) ?> km</dd>
                                </div>
                                <div class="linha-dado">
                                    <dt>Taxa entrega</dt>
                                    <dd>R$ <?php echo number_format($pedido['taxa_entrega'], 2, ',', '.'); ?></dd>
                                </div>
                                <div class="linha-dado">
                                    <dt>Pagamento</dt>
                                    <dd><?= e($pedido['forma_pagamento']) ?></dd>
                                </div>
                            </dl>

                            <div class="menu-item__acoes pedido__rodape">
                                <span class="status-badge status-badge--<?= e($status_map[$pedido['status']]['badge']) ?>"><span class="sr-only">Situação: </span><?= e($status_texto) ?></span>
                                <button type="button" class="btn btn-pequeno" aria-haspopup="dialog" aria-label="Ver detalhes do pedido #<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?>" onclick="verDetalhes(<?= (int) $pedido['id'] ?>)">Ver detalhes</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal de detalhes (o JavaScript lá embaixo abre, preenche e fecha) -->
    <div id="detalhesModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="detalhesTitulo">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="detalhesTitulo">Detalhes do Pedido</h2>
                <button type="button" class="close" aria-label="Fechar" onclick="fecharModal()">&times;</button>
            </div>
            <div id="detalhesConteudo">
                <p class="texto-centro mb-0"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Carregando...</p>
            </div>
        </div>
    </div>

    <script src="assets/js/site.js" defer></script>
    <script src="assets/js/acessibilidade.js" defer></script>
    <script>
        // Segurança (XSS): texto vindo do banco é "escapado" antes de entrar no innerHTML,
        // assim um endereço como <img onerror=...> aparece como texto e não executa.
        function escaparHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto == null ? '' : String(texto);
            return div.innerHTML;
        }

        // Acessibilidade: guarda o botão que abriu a janela para devolver o foco
        // a ele quando a janela fechar (quem usa teclado continua de onde parou)
        let focoAntesDaJanela = null;

        // Acessibilidade: com a janela aberta, o que fica atrás dela (skip link, menu e
        // conteúdo) vira "inerte": o Tab pula, o clique não pega e o leitor de tela não lê.
        // Sem isso o Tab saía da janela para os botões escondidos atrás do fundo escuro.
        // Fica de fora o aviso para leitor de tela (#liveStatus), que precisa continuar falando.
        function definirFundoInerte(inerte) {
            Array.from(document.body.children).forEach(function (parte) {
                if (parte.id !== 'detalhesModal' && parte.id !== 'liveStatus' && parte.tagName !== 'SCRIPT') {
                    parte.inert = inerte;
                }
            });
        }

        function verDetalhes(pedidoId) {
            const modal = document.getElementById('detalhesModal');
            const conteudo = document.getElementById('detalhesConteudo');

            focoAntesDaJanela = document.activeElement;
            modal.style.display = 'block';
            definirFundoInerte(true);
            // O foco entra na janela (no "×"), senão o leitor de tela nem percebe que ela abriu
            modal.querySelector('.close').focus();
            conteudo.innerHTML = '<p class="texto-centro mb-0"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Carregando...</p>';

            fetch(`buscar-detalhes-pedido.php?pedido_id=${pedidoId}`)
                .then(response => response.json())
                .then(data => {
                    // Dados do pedido: uma linha "rótulo ..... valor" para cada informação
                    let html = `
                        <dl class="dados">
                            <div class="linha-dado"><dt>Data</dt><dd>${new Date(data.pedido.data_pedido).toLocaleString('pt-BR')}</dd></div>
                            <div class="linha-dado linha-longa"><dt>Endereço</dt><dd>${escaparHtml(data.pedido.endereco_entrega)}</dd></div>
                            <div class="linha-dado"><dt>Distância</dt><dd>${escaparHtml(data.pedido.distancia_km)} km</dd></div>
                            <div class="linha-dado"><dt>Taxa entrega</dt><dd>R$ ${parseFloat(data.pedido.taxa_entrega).toFixed(2)}</dd></div>
                            <div class="linha-dado"><dt>Pagamento</dt><dd>${escaparHtml(data.pedido.forma_pagamento)}</dd></div>
                        </dl>

                        <div class="titulo-linhas"><h3 id="detalhesItensTitulo">Itens do Pedido</h3></div>
                        <table class="tabela" aria-labelledby="detalhesItensTitulo">
                            <thead>
                                <tr>
                                    <th scope="col">Produto</th>
                                    <th scope="col" class="num col-extra">Qtd</th>
                                    <th scope="col" class="num col-extra">Preço</th>
                                    <th scope="col" class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    // No celular as colunas "Qtd" e "Preço" (col-extra) somem e a mesma
                    // informação aparece embaixo do nome, no <span class="item-conta">
                    data.itens.forEach(item => {
                        html += `
                            <tr>
                                <td>
                                    ${escaparHtml(item.produto_nome)}
                                    <span class="item-conta">${escaparHtml(item.quantidade)} &times; R$ ${parseFloat(item.preco_unitario).toFixed(2)}</span>
                                </td>
                                <td class="num col-extra">${escaparHtml(item.quantidade)}</td>
                                <td class="num col-extra">R$ ${parseFloat(item.preco_unitario).toFixed(2)}</td>
                                <td class="num">R$ ${(item.quantidade * item.preco_unitario).toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    html += `
                            </tbody>
                        </table>
                        <div class="linha-dado linha-dado--total">
                            <span>Total</span>
                            <span>R$ ${parseFloat(data.pedido.total).toFixed(2)}</span>
                        </div>
                    `;

                    // Adicionar progresso do pedido
                    const passos = ['PENDENTE', 'PREPARANDO', 'SAIU PARA ENTREGA', 'ENTREGUE'];
                    const statusAtual = passos.indexOf(data.pedido.status);

                    html += `
                        <div class="progresso-pedido bloco-creme">
                            <h3>Progresso do pedido</h3>
                            <ol class="progresso-passos">
                    `;

                    passos.forEach((passo, index) => {
                        let statusClasse = '';
                        if(index < statusAtual) statusClasse = 'completo';
                        else if(index == statusAtual) statusClasse = 'ativo';

                        let icone = '';
                        if(index < statusAtual) icone = '<i class="fas fa-check" role="img" aria-label="Concluído"></i>';
                        else if(index == statusAtual) icone = index + 1;
                        else icone = index + 1;

                        html += `
                            <li class="passo ${statusClasse}"${statusClasse == 'ativo' ? ' aria-current="step"' : ''}>
                                <div class="circulo">${icone}</div>
                                <div class="linha"></div>
                                <div class="passo-texto">${passo.replace('_', ' ')}</div>
                            </li>
                        `;
                    });

                    html += `
                            </ol>
                        </div>
                    `;

                    conteudo.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro:', error);
                    conteudo.innerHTML = '<div class="alert alert-danger mb-0" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Erro ao carregar detalhes</div>';
                });
        }

        function fecharModal() {
            document.getElementById('detalhesModal').style.display = 'none';
            // Primeiro "solta" a página de trás: elemento inerte não aceita foco
            definirFundoInerte(false);
            if (focoAntesDaJanela) {
                focoAntesDaJanela.focus();
                focoAntesDaJanela = null;
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('detalhesModal');
            if (event.target == modal) {
                fecharModal();
            }
        }

        // Esc: o acessibilidade.js já esconde a janela; aqui passamos antes por
        // fecharModal() para o foco também voltar ao botão "Ver detalhes".
        // (Este script roda antes do acessibilidade.js, que é "defer".)
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && document.getElementById('detalhesModal').style.display === 'block') {
                fecharModal();
            }
        });
    </script>
</body>
</html>
