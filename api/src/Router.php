<?php
/**
 * Router — liga "MÉTODO + CAMINHO" a uma função (o controller).
 *
 * Exemplo de uso (no index.php):
 *     $router->get('/v1/produtos/{id}', [ProdutoController::class, 'detalhar']);
 *
 * "{id}" é um pedaço variável da URL: aceita só dígitos e chega ao
 * controller como parâmetro. /v1/produtos/7 -> detalhar(7).
 */
class Router
{
    /** Lista de rotas: cada uma é ['metodo', 'regex', 'acao'] */
    private array $rotas = [];

    public function get(string $caminho, callable|array $acao): void
    {
        $this->adicionar('GET', $caminho, $acao);
    }

    public function post(string $caminho, callable|array $acao): void
    {
        $this->adicionar('POST', $caminho, $acao);
    }

    private function adicionar(string $metodo, string $caminho, callable|array $acao): void
    {
        // '/v1/produtos/{id}' vira a expressão regular '#^/v1/produtos/([0-9]+)$#'
        $regex = '#^' . preg_replace('/\{[a-z_]+\}/', '([0-9]+)', $caminho) . '$#';
        $this->rotas[] = ['metodo' => $metodo, 'regex' => $regex, 'acao' => $acao];
    }

    /**
     * Encontra a rota e executa.
     *   - caminho existe e método bate  -> chama o controller
     *   - caminho existe, método errado -> 405 + header Allow (diz quais métodos valem)
     *   - caminho não existe            -> 404
     */
    public function despachar(string $metodo, string $caminho): void
    {
        $metodosDoCaminho = [];

        foreach ($this->rotas as $rota) {
            if (!preg_match($rota['regex'], $caminho, $partes)) {
                continue;
            }
            if ($rota['metodo'] !== $metodo) {
                $metodosDoCaminho[] = $rota['metodo'];
                continue;
            }

            array_shift($partes);                       // tira o "match completo"
            $parametros = array_map('intval', $partes); // {id} sempre chega como int
            call_user_func_array($rota['acao'], $parametros);
            return;
        }

        if ($metodosDoCaminho !== []) {
            $permitidos = implode(', ', array_unique($metodosDoCaminho));
            throw new ErroApi(405, 'metodo_nao_permitido', "Método $metodo não é aceito nesta rota. Use: $permitidos.", ['Allow' => $permitidos]);
        }

        throw new ErroApi(404, 'rota_nao_encontrada', "A rota $caminho não existe.");
    }
}
