<?php
/**
 * ErroApi — exceção que já sabe virar uma resposta HTTP.
 *
 * POR QUE uma exceção? Em qualquer ponto (controller, JWT, validação) basta
 * fazer `throw new ErroApi(404, 'nao_encontrado', 'Produto não encontrado.')`.
 * O index.php captura e devolve SEMPRE o mesmo formato:
 *     { "erro": { "codigo": "nao_encontrado", "mensagem": "Produto não encontrado." } }
 * Assim o cliente da API (app, site, Postman) trata todos os erros do mesmo jeito.
 */
class ErroApi extends Exception
{
    /** @param array $headers headers extras (ex.: Allow no 405, Retry-After no 429) */
    public function __construct(
        public readonly int $status,
        public readonly string $codigo,
        string $mensagem,
        public readonly array $headers = []
    ) {
        parent::__construct($mensagem);
    }
}
