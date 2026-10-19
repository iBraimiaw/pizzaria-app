<?php
/**
 * Jwt — JSON Web Token (HS256), escrito à mão para mostrar como funciona.
 *
 * Um JWT tem 3 partes separadas por ponto:  CABEÇALHO.CONTEÚDO.ASSINATURA
 *   - cabeçalho: {"alg":"HS256","typ":"JWT"}          (em base64url)
 *   - conteúdo:  {"sub":"1","exp":1760000000,...}      (em base64url)
 *   - assinatura: HMAC-SHA256(cabeçalho.conteúdo, SEGREDO)
 *
 * IMPORTANTE: o conteúdo NÃO é criptografado, só codificado — qualquer um lê
 * (cole um token em jwt.io e veja). Por isso nunca colocamos senha no token.
 * O que impede a falsificação é a ASSINATURA: sem o segredo (que só o
 * servidor conhece) não dá para gerar uma assinatura válida. Se alguém mudar
 * um único caractere do conteúdo, a assinatura deixa de bater -> 401.
 */
class Jwt
{
    /** Algoritmo aceito. Fixo de propósito: ver comentário em validar(). */
    private const ALGORITMO = 'HS256';

    /** Tolerância para relógios levemente diferentes entre servidores. */
    private const TOLERANCIA_RELOGIO_SEG = 30;

    public function __construct(
        private readonly string $segredo,
        private readonly string $emissor   // "iss": quem emitiu o token (a nossa API)
    ) {
    }

    /**
     * Cria um token assinado.
     * @param array $dados        o que vai no conteúdo (ex.: ['sub' => '1'])
     * @param int   $validadeSeg  por quantos segundos o token vale
     */
    public function gerar(array $dados, int $validadeSeg): string
    {
        $agora = time();
        $cabecalho = ['alg' => self::ALGORITMO, 'typ' => 'JWT'];
        $conteudo = array_merge($dados, [
            'iss' => $this->emissor,
            'iat' => $agora,                  // "issued at": quando foi criado
            'exp' => $agora + $validadeSeg,   // "expiration": depois disso não vale mais
        ]);

        $parte1 = self::base64UrlCodificar(json_encode($cabecalho));
        $parte2 = self::base64UrlCodificar(json_encode($conteudo, JSON_UNESCAPED_UNICODE));
        $assinatura = self::base64UrlCodificar($this->assinar("$parte1.$parte2"));

        return "$parte1.$parte2.$assinatura";
    }

    /**
     * Confere o token e devolve o conteúdo (array). Qualquer problema -> ErroApi 401.
     *
     * Ordem das checagens:
     *   1. formato (3 partes)
     *   2. cabeçalho diz HS256 (recusa "alg":"none", truque clássico de ataque)
     *   3. assinatura confere (hash_equals = comparação em tempo constante)
     *   4. emissor (iss) é a nossa API
     *   5. não expirou (exp) e não foi criado "no futuro" (iat)
     */
    public function validar(string $token): array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            throw self::tokenInvalido('Formato de token inválido.');
        }
        [$parte1, $parte2, $assinaturaRecebida] = $partes;

        $cabecalho = json_decode(self::base64UrlDecodificar($parte1), true);
        // Só aceitamos o algoritmo que NÓS escolhemos. Se confiássemos no "alg"
        // que vem no token, um atacante poderia mandar "none" (sem assinatura).
        if (!is_array($cabecalho) || ($cabecalho['alg'] ?? '') !== self::ALGORITMO) {
            throw self::tokenInvalido('Algoritmo do token não é aceito.');
        }

        // Recalcula a assinatura com o NOSSO segredo e compara com a recebida
        $assinaturaEsperada = self::base64UrlCodificar($this->assinar("$parte1.$parte2"));
        if (!hash_equals($assinaturaEsperada, $assinaturaRecebida)) {
            throw self::tokenInvalido('Assinatura do token inválida.');
        }

        // Só agora (assinatura OK) confiamos no conteúdo
        $conteudo = json_decode(self::base64UrlDecodificar($parte2), true);
        if (!is_array($conteudo)) {
            throw self::tokenInvalido('Conteúdo do token inválido.');
        }

        if (($conteudo['iss'] ?? '') !== $this->emissor) {
            throw self::tokenInvalido('Token emitido por outro sistema.');
        }

        $agora = time();
        if (!isset($conteudo['exp']) || !is_int($conteudo['exp']) || $conteudo['exp'] < $agora) {
            throw new ErroApi(401, 'token_expirado', 'Token expirado. Faça login novamente.', self::headerWwwAuthenticate());
        }
        if (!isset($conteudo['iat']) || !is_int($conteudo['iat']) || $conteudo['iat'] > $agora + self::TOLERANCIA_RELOGIO_SEG) {
            throw self::tokenInvalido('Data de emissão do token inválida.');
        }

        return $conteudo;
    }

    /** HMAC-SHA256: "carimbo" que só quem tem o segredo consegue produzir. */
    private function assinar(string $texto): string
    {
        return hash_hmac('sha256', $texto, $this->segredo, true);   // true = bytes crus
    }

    /**
     * base64url = base64 "seguro para URL": troca + por -, / por _ e tira o =.
     * (O base64 comum tem caracteres que quebram URLs e headers.)
     */
    public static function base64UrlCodificar(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecodificar(string $texto): string
    {
        $base64 = strtr($texto, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);   // devolve o "=" de enchimento
        return (string) base64_decode($base64, true);
    }

    private static function tokenInvalido(string $mensagem): ErroApi
    {
        return new ErroApi(401, 'token_invalido', $mensagem, self::headerWwwAuthenticate());
    }

    /** Padrão HTTP: todo 401 diz qual tipo de autenticação é esperado. */
    private static function headerWwwAuthenticate(): array
    {
        return ['WWW-Authenticate' => 'Bearer'];
    }
}
