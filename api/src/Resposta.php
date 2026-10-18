<?php
/**
 * Resposta — o ÚNICO lugar que escreve a saída da API.
 *
 * Centralizar aqui garante que toda resposta tenha o mesmo Content-Type,
 * o mesmo formato de erro e os mesmos headers de segurança.
 */
class Resposta
{
    /** Envia um JSON com o código HTTP informado e encerra a requisição. */
    public static function json(mixed $dados, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $nome => $valor) {
            header("$nome: $valor");
        }

        // 204 = "No Content": por definição não tem corpo
        if ($status !== 204) {
            // JSON_UNESCAPED_UNICODE: "Pizza Calabresa" com acento legível
            echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        exit;
    }

    /** Formato padrão de erro: { "erro": { "codigo", "mensagem" } } */
    public static function erro(int $status, string $codigo, string $mensagem, array $headers = []): never
    {
        self::json(['erro' => ['codigo' => $codigo, 'mensagem' => $mensagem]], $status, $headers);
    }

    /**
     * Headers de segurança próprios de uma API JSON.
     * (O includes/security.php manda headers pensados para páginas HTML;
     *  aqui substituímos os que fazem sentido diferente para JSON.)
     */
    public static function enviarHeadersSeguranca(): void
    {
        // Uma resposta JSON não carrega script, imagem nem nada: bloqueia tudo
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');       // navegador não "adivinha" o tipo
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        // Dados pessoais (pedidos, endereço) não podem ficar em cache de proxy/navegador
        header('Cache-Control: no-store');
        header_remove('X-Powered-By');
    }

    /**
     * CORS: diz ao NAVEGADOR quais outros sites podem chamar a API via fetch().
     *
     * CORS_ORIGIN (variável de ambiente):
     *   ''                               -> ninguém de fora (padrão mais seguro)
     *   '*'                              -> qualquer site
     *   'http://localhost:3000,https://app.exemplo.com' -> só essa lista
     *
     * Sem token em cookie, o CORS não é a proteção principal (quem protege é
     * o JWT), mas evita que qualquer página da internet use a API pelo
     * navegador da vítima.
     */
    public static function enviarHeadersCors(string $origensPermitidas): void
    {
        $origemPedido = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origensPermitidas === '' || $origemPedido === '') {
            return;
        }

        if ($origensPermitidas === '*') {
            header('Access-Control-Allow-Origin: *');
        } else {
            $lista = array_map('trim', explode(',', $origensPermitidas));
            if (!in_array($origemPedido, $lista, true)) {
                return;   // origem não autorizada: o navegador bloqueia a leitura
            }
            header('Access-Control-Allow-Origin: ' . $origemPedido);
            header('Vary: Origin');   // cache: a resposta muda conforme a origem
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 600');   // navegador guarda a "pré-checagem" por 10 min
    }
}
