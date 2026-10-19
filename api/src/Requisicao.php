<?php
/**
 * Requisicao — lê com segurança o que o cliente mandou (corpo JSON e token).
 */
class Requisicao
{
    /**
     * Lê o corpo da requisição como JSON e devolve um array.
     *
     * Validações (cada uma com seu código HTTP):
     *   413 -> corpo maior que o limite (evita alguém mandar 1 GB e travar o servidor)
     *   415 -> Content-Type não é application/json
     *   400 -> não é um JSON válido, ou não é um objeto { ... }
     */
    public static function corpoJson(int $tamanhoMaximoBytes): array
    {
        $tamanhoDeclarado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($tamanhoDeclarado > $tamanhoMaximoBytes) {
            throw new ErroApi(413, 'corpo_muito_grande', "O corpo da requisição passa do limite de $tamanhoMaximoBytes bytes.");
        }

        $tipo = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_starts_with($tipo, 'application/json')) {
            throw new ErroApi(415, 'tipo_nao_suportado', 'Envie o corpo como JSON (Content-Type: application/json).');
        }

        // Lê no máximo limite+1 bytes: se vier mais, o cliente mentiu no Content-Length
        $corpo = file_get_contents('php://input', false, null, 0, $tamanhoMaximoBytes + 1);
        if ($corpo === false || strlen($corpo) > $tamanhoMaximoBytes) {
            throw new ErroApi(413, 'corpo_muito_grande', "O corpo da requisição passa do limite de $tamanhoMaximoBytes bytes.");
        }

        try {
            // profundidade 10: um JSON "aninhado" demais também é recusado
            $dados = json_decode($corpo, true, 10, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErroApi(400, 'json_invalido', 'O corpo da requisição não é um JSON válido.');
        }

        // Precisa ser um objeto { ... }. Um número ou uma lista solta não serve.
        if (!is_array($dados) || (array_is_list($dados) && $dados !== [])) {
            throw new ErroApi(400, 'json_invalido', 'O corpo precisa ser um objeto JSON, ex.: {"email": "..."}');
        }
        return $dados;
    }

    /**
     * Pega o token do header "Authorization: Bearer <token>".
     * Devolve null se o header não veio ou não está nesse formato.
     */
    public static function tokenBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']   // nome que o Apache usa depois do RewriteRule
            ?? '';

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $partes)) {
            return $partes[1];
        }
        return null;
    }

    /** Lê um parâmetro inteiro da URL (?pagina=2). Devolve $padrao se não veio. */
    public static function inteiroDaQuery(string $nome, ?int $padrao = null): ?int
    {
        if (!isset($_GET[$nome]) || $_GET[$nome] === '') {
            return $padrao;
        }
        $valor = filter_var($_GET[$nome], FILTER_VALIDATE_INT);
        if ($valor === false) {
            throw new ErroApi(400, 'parametro_invalido', "O parâmetro '$nome' precisa ser um número inteiro.");
        }
        return $valor;
    }
}
