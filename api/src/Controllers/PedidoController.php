<?php
/**
 * Pedidos do usuário logado (todas as rotas exigem token):
 *   GET  /api/v1/pedidos        -> lista SÓ os pedidos do dono do token
 *   GET  /api/v1/pedidos/{id}   -> detalhe; 404 se o pedido for de outra pessoa
 *   POST /api/v1/pedidos        -> cria um pedido novo
 *
 * Regras de segurança aplicadas aqui:
 *   - Anti-IDOR: todo SELECT tem "AND usuario_id = <id do token>".
 *   - Preço SEMPRE do banco: o cliente manda só produto_id + quantidade.
 *     Se mandar "preco", o campo é simplesmente ignorado.
 *   - Frete calculado pelo microserviço de entrega (com fallback local).
 */
class PedidoController
{
    /** Mesmas opções da tela do carrinho (lista branca). */
    private const FORMAS_PAGAMENTO = ['Dinheiro', 'Cartão Crédito', 'Cartão Débito', 'PIX'];

    private const QUANTIDADE_MINIMA = 1;
    private const QUANTIDADE_MAXIMA = 50;      // mesmo limite do site, por produto
    private const ITENS_MAXIMOS = 20;          // produtos diferentes num pedido

    public function __construct(
        private readonly PDO $pdo,
        private readonly Autenticacao $auth
    ) {
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/pedidos
    // -----------------------------------------------------------------------
    public function listar(): void
    {
        $usuario = $this->auth->usuarioLogado();
        [$pagina, $limite, $deslocamento] = Paginacao::lerDaQuery();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = ?');
        $stmt->execute([$usuario['id']]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM pedidos
              WHERE usuario_id = ?            -- só os pedidos do dono do token
              ORDER BY data_pedido DESC, id DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$usuario['id'], $limite, $deslocamento]);

        Resposta::json([
            'dados'     => array_map([self::class, 'formatarPedido'], $stmt->fetchAll()),
            'paginacao' => Paginacao::resumo($pagina, $limite, $total),
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/pedidos/{id}
    // -----------------------------------------------------------------------
    public function detalhar(int $id): void
    {
        $usuario = $this->auth->usuarioLogado();
        $pedido = $this->buscarPedidoDoUsuario($id, (int) $usuario['id']);

        // 404 (e não 403) de propósito: nem confirmamos que o pedido existe.
        // Um 403 diria "esse número existe, só não é seu".
        if ($pedido === null) {
            throw new ErroApi(404, 'nao_encontrado', 'Pedido não encontrado.');
        }
        Resposta::json(['dados' => $pedido]);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/pedidos
    // Corpo: {"itens":[{"produto_id":1,"quantidade":2}], "forma_pagamento":"PIX", "troco_para":100}
    // -----------------------------------------------------------------------
    public function criar(): void
    {
        $usuario = $this->auth->usuarioLogado();
        $corpo = Requisicao::corpoJson(API_CORPO_MAXIMO_BYTES);

        // 1) Valida a entrada
        $quantidades = $this->validarItens($corpo['itens'] ?? null);   // [produto_id => quantidade]

        $formaPagamento = $corpo['forma_pagamento'] ?? null;
        if (!is_string($formaPagamento) || !in_array($formaPagamento, self::FORMAS_PAGAMENTO, true)) {
            throw new ErroApi(422, 'validacao', 'forma_pagamento inválida. Use: ' . implode(', ', self::FORMAS_PAGAMENTO) . '.');
        }

        // 2) Busca nome e PREÇO no banco (nunca do cliente)
        $produtos = $this->buscarProdutosDisponiveis(array_keys($quantidades));

        $subtotal = 0.0;
        foreach ($quantidades as $produtoId => $quantidade) {
            $subtotal += (float) $produtos[$produtoId]['preco'] * $quantidade;
        }

        // 3) Frete: pergunta ao microserviço (includes/entrega.php já faz o fallback)
        $entrega = calcularEntregaUsuario((int) $usuario['id']);
        if (!$entrega['dentro_area']) {
            throw new ErroApi(422, 'fora_da_area', 'Endereço fora da área de entrega.');
        }

        $total = round($subtotal + $entrega['taxa_entrega'], 2);
        $trocoPara = $this->validarTroco($formaPagamento, $corpo['troco_para'] ?? null, $total);

        // 4) Grava tudo numa TRANSAÇÃO: ou entra o pedido inteiro, ou nada.
        //    (Sem transação, uma falha no meio deixaria um pedido sem itens.)
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO pedidos (usuario_id, total, endereco_entrega, distancia_km, taxa_entrega, forma_pagamento, troco_para)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $usuario['id'], $total, $usuario['endereco'],
                $entrega['distancia_km'], $entrega['taxa_entrega'], $formaPagamento, $trocoPara,
            ]);
            $pedidoId = (int) $this->pdo->lastInsertId();

            // Mesmo formato que o finalizar-pedido.php grava (o site enxerga o pedido)
            $stmt = $this->pdo->prepare(
                'INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)'
            );
            foreach ($quantidades as $produtoId => $quantidade) {
                $stmt->execute([$pedidoId, $produtoId, $quantidade, $produtos[$produtoId]['preco']]);
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            $this->pdo->rollBack();
            throw $erro;   // o index.php registra no log e responde 500 genérico
        }

        $pedido = $this->buscarPedidoDoUsuario($pedidoId, (int) $usuario['id']);
        $pedido['entrega'] = [
            'origem'             => $entrega['origem'],   // 'microservico', 'local' (fallback) ou 'sem_coordenadas'
            'tempo_estimado_min' => $entrega['tempo_estimado_min'],
        ];

        // 201 = "Created". O header Location diz onde consultar o recurso novo.
        Resposta::json(['dados' => $pedido], 201, ['Location' => "/api/v1/pedidos/$pedidoId"]);
    }

    // -----------------------------------------------------------------------
    // Funções de apoio
    // -----------------------------------------------------------------------

    /**
     * Confere a lista de itens e devolve [produto_id => quantidade total].
     * Se o mesmo produto vier duas vezes, as quantidades são somadas.
     */
    private function validarItens(mixed $itens): array
    {
        if (!is_array($itens) || $itens === [] || !array_is_list($itens)) {
            throw new ErroApi(422, 'validacao', 'Envie "itens" como uma lista não vazia, ex.: [{"produto_id": 1, "quantidade": 2}].');
        }
        if (count($itens) > self::ITENS_MAXIMOS) {
            throw new ErroApi(422, 'validacao', 'Um pedido aceita no máximo ' . self::ITENS_MAXIMOS . ' itens.');
        }

        $quantidades = [];
        foreach ($itens as $posicao => $item) {
            $produtoId = is_array($item) ? ($item['produto_id'] ?? null) : null;
            $quantidade = is_array($item) ? ($item['quantidade'] ?? null) : null;

            // is_int: "2" (texto), 2.5 ou true não passam
            if (!is_int($produtoId) || $produtoId < 1) {
                throw new ErroApi(422, 'validacao', "itens[$posicao].produto_id precisa ser um número inteiro positivo.");
            }
            if (!is_int($quantidade) || $quantidade < self::QUANTIDADE_MINIMA || $quantidade > self::QUANTIDADE_MAXIMA) {
                throw new ErroApi(422, 'validacao', "itens[$posicao].quantidade precisa ser um inteiro de " . self::QUANTIDADE_MINIMA . ' a ' . self::QUANTIDADE_MAXIMA . '.');
            }

            $quantidades[$produtoId] = ($quantidades[$produtoId] ?? 0) + $quantidade;
            if ($quantidades[$produtoId] > self::QUANTIDADE_MAXIMA) {
                throw new ErroApi(422, 'validacao', "Quantidade total do produto $produtoId passa de " . self::QUANTIDADE_MAXIMA . '.');
            }
        }
        return $quantidades;
    }

    /** Busca os produtos no banco. Algum não existe ou está indisponível -> 422. */
    private function buscarProdutosDisponiveis(array $ids): array
    {
        // Um "?" para cada id: IN (?, ?, ?) — continua sendo prepared statement
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, nome, preco FROM produtos WHERE disponivel = TRUE AND id IN ($marcadores)");
        $stmt->execute($ids);

        $produtos = [];
        foreach ($stmt->fetchAll() as $produto) {
            $produtos[(int) $produto['id']] = $produto;
        }

        $faltando = array_diff($ids, array_keys($produtos));
        if ($faltando !== []) {
            throw new ErroApi(422, 'produto_indisponivel', 'Produto(s) inexistente(s) ou indisponível(is): ' . implode(', ', $faltando) . '.');
        }
        return $produtos;
    }

    /** Troco só existe em dinheiro e precisa ser >= total (mesma regra do site). */
    private function validarTroco(string $formaPagamento, mixed $trocoPara, float $total): ?float
    {
        if ($formaPagamento !== 'Dinheiro' || $trocoPara === null) {
            return null;
        }
        if (!is_int($trocoPara) && !is_float($trocoPara)) {
            throw new ErroApi(422, 'validacao', 'troco_para precisa ser um número.');
        }
        if ($trocoPara < $total) {
            throw new ErroApi(422, 'validacao', 'troco_para precisa ser maior ou igual ao total do pedido (R$ ' . number_format($total, 2, ',', '.') . ').');
        }
        return round((float) $trocoPara, 2);
    }

    /** Pedido + itens, SOMENTE se pertencer ao usuário. Senão, null. */
    private function buscarPedidoDoUsuario(int $pedidoId, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$pedidoId, $usuarioId]);
        $pedido = $stmt->fetch();
        if (!$pedido) {
            return null;
        }

        // Mesma consulta do buscar-detalhes-pedido.php (inclui pizza meia a meia feita no site)
        $stmt = $this->pdo->prepare(
            "SELECT ip.produto_id, ip.quantidade, ip.preco_unitario, ip.is_personalizada,
                    CASE WHEN ip.is_personalizada = TRUE THEN
                        (SELECT CONCAT('Pizza Meia a Meia: ', pr1.nome, ' + ', pr2.nome)
                           FROM pizzas_personalizadas pp
                           JOIN produtos pr1 ON pp.sabor1_id = pr1.id
                           JOIN produtos pr2 ON pp.sabor2_id = pr2.id
                          WHERE pp.id = ip.pizza_personalizada_id)
                    ELSE pr.nome END AS produto_nome
               FROM itens_pedido ip
               LEFT JOIN produtos pr ON ip.produto_id = pr.id
              WHERE ip.pedido_id = ?"
        );
        $stmt->execute([$pedidoId]);

        $resultado = self::formatarPedido($pedido);
        $resultado['itens'] = array_map(fn (array $item): array => [
            'produto_id'       => $item['produto_id'] === null ? null : (int) $item['produto_id'],
            'nome'             => $item['produto_nome'],
            'quantidade'       => (int) $item['quantidade'],
            'preco_unitario'   => (float) $item['preco_unitario'],
            'subtotal'         => round((float) $item['preco_unitario'] * (int) $item['quantidade'], 2),
            'is_personalizada' => (bool) $item['is_personalizada'],
        ], $stmt->fetchAll());

        return $resultado;
    }

    /** Converte a linha do banco nos tipos certos (número como número). */
    private static function formatarPedido(array $pedido): array
    {
        return [
            'id'               => (int) $pedido['id'],
            'status'           => $pedido['status'],
            'total'            => (float) $pedido['total'],
            'taxa_entrega'     => (float) $pedido['taxa_entrega'],
            'distancia_km'     => (float) $pedido['distancia_km'],
            'forma_pagamento'  => $pedido['forma_pagamento'],
            'troco_para'       => $pedido['troco_para'] === null ? null : (float) $pedido['troco_para'],
            'endereco_entrega' => $pedido['endereco_entrega'],
            'data_pedido'      => $pedido['data_pedido'],
        ];
    }
}
