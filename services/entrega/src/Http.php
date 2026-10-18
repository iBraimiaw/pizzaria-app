<?php
/**
 * Http — pequenas funções de requisição/resposta, escritas à mão (sem framework).
 */
class Http
{
    /**
     * Envia uma resposta JSON e encerra o script.
     * Sempre o mesmo Content-Type: quem consome a API sabe o que esperar.
     */
    public static function json(int $status, array $corpo): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    /** Atalho para erros: sempre no formato {"erro": "..."}. */
    public static function erro(int $status, string $mensagem): never
    {
        self::json($status, ['erro' => $mensagem]);
    }

    /**
     * Lê o corpo da requisição como JSON.
     * Devolve null se o corpo não for um objeto JSON válido.
     */
    public static function corpoJson(): ?array
    {
        $bruto = file_get_contents('php://input');
        $dados = json_decode($bruto, true);
        return is_array($dados) ? $dados : null;
    }

    /** Lê um cabeçalho da requisição (o PHP guarda como HTTP_NOME_DO_HEADER). */
    public static function cabecalho(string $nome): string
    {
        $chave = 'HTTP_' . strtoupper(str_replace('-', '_', $nome));
        return (string) ($_SERVER[$chave] ?? '');
    }
}
