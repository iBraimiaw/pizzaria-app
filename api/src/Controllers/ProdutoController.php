<?php
/**
 * Produtos do cardápio (rotas públicas, sem token):
 *   GET /api/v1/produtos?categoria=1&busca=calabresa&pagina=1&limite=10
 *   GET /api/v1/produtos/{id}
 *
 * Só aparecem produtos com disponivel = TRUE.
 */
class ProdutoController
{
    /** Busca com mais de 100 letras não faz sentido: recusa (evita abuso). */
    private const BUSCA_TAMANHO_MAXIMO = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(): void
    {
        // Monta o WHERE aos poucos; os VALORES vão sempre em $parametros
        // (prepared statement), nunca colados no texto do SQL.
        $condicoes = ['p.disponivel = TRUE'];
        $parametros = [];

        $categoria = Requisicao::inteiroDaQuery('categoria');
        if ($categoria !== null) {
            $condicoes[] = 'p.categoria_id = ?';
            $parametros[] = $categoria;
        }

        $busca = trim((string) ($_GET['busca'] ?? ''));
        if ($busca !== '') {
            if (mb_strlen($busca) > self::BUSCA_TAMANHO_MAXIMO) {
                throw new ErroApi(400, 'parametro_invalido', "O parâmetro 'busca' aceita no máximo " . self::BUSCA_TAMANHO_MAXIMO . ' caracteres.');
            }
            // % e _ são "curingas" do LIKE: escapamos para buscar o texto literal
            $condicoes[] = '(p.nome LIKE ? OR p.descricao LIKE ?)';
            $termo = '%' . addcslashes($busca, '%_\\') . '%';
            $parametros[] = $termo;
            $parametros[] = $termo;
        }

        $where = 'WHERE ' . implode(' AND ', $condicoes);
        [$pagina, $limite, $deslocamento] = Paginacao::lerDaQuery();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM produtos p $where");
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        // LIMIT/OFFSET também vão como parâmetro (inteiros já validados)
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.categoria_id, c.nome AS categoria, p.nome, p.descricao, p.preco, p.imagem_url
               FROM produtos p
               JOIN categorias c ON c.id = p.categoria_id
             $where
              ORDER BY p.categoria_id, p.nome
              LIMIT ? OFFSET ?"
        );
        $stmt->execute([...$parametros, $limite, $deslocamento]);

        Resposta::json([
            'dados'     => array_map([self::class, 'formatarProduto'], $stmt->fetchAll()),
            'paginacao' => Paginacao::resumo($pagina, $limite, $total),
        ]);
    }

    public function detalhar(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.categoria_id, c.nome AS categoria, p.nome, p.descricao, p.preco, p.imagem_url
               FROM produtos p
               JOIN categorias c ON c.id = p.categoria_id
              WHERE p.id = ? AND p.disponivel = TRUE'
        );
        $stmt->execute([$id]);
        $produto = $stmt->fetch();

        if (!$produto) {
            throw new ErroApi(404, 'nao_encontrado', 'Produto não encontrado.');
        }
        Resposta::json(['dados' => self::formatarProduto($produto)]);
    }

    /** O MySQL devolve números como texto ("42.90"); a API devolve como número. */
    private static function formatarProduto(array $produto): array
    {
        return [
            'id'           => (int) $produto['id'],
            'categoria_id' => (int) $produto['categoria_id'],
            'categoria'    => $produto['categoria'],
            'nome'         => $produto['nome'],
            'descricao'    => $produto['descricao'],
            'preco'        => (float) $produto['preco'],
            'imagem_url'   => $produto['imagem_url'],
        ];
    }
}
