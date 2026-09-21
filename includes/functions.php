<?php

// ================================================================
// 1. CONFIGURAÇÕES
// ================================================================

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'pizzaria');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Localização da pizzaria
defined('PIZZARIA_LAT')      || define('PIZZARIA_LAT', -20.4697);
defined('PIZZARIA_LNG')      || define('PIZZARIA_LNG', -54.6201);
defined('PIZZARIA_ENDERECO') || define(
    'PIZZARIA_ENDERECO',
    'Rua Exemplo, 123 - Centro, Campo Grande - MS'
);

// Regras de entrega
defined('TAXA_ENTREGA_BASE') || define('TAXA_ENTREGA_BASE', 5.00);
defined('KM_INCLUSOS')       || define('KM_INCLUSOS', 3.0);
defined('TAXA_KM_EXTRA')     || define('TAXA_KM_EXTRA', 1.50);


// ================================================================
// 2. SESSÃO SEGURA
// ================================================================

if (session_status() === PHP_SESSION_NONE) {

    $https = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


// ================================================================
// 3. CABEÇALHOS DE SEGURANÇA
// ================================================================

if (!headers_sent()) {

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: https: http:; "
        . "connect-src 'self' https://nominatim.openstreetmap.org; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'"
    );
}


// ================================================================
// 4. CONEXÃO COM O BANCO
// ================================================================

if (!isset($pdo) || !($pdo instanceof PDO)) {

    try {

        $pdo = new PDO(
            'mysql:host=' . DB_HOST .
            ';dbname=' . DB_NAME .
            ';charset=utf8mb4',

            DB_USER,
            DB_PASS,

            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]
        );

    } catch (PDOException $ex) {

        error_log(
            'Falha na conexão com o banco: ' .
            $ex->getMessage()
        );

        http_response_code(500);

        exit('Erro ao conectar ao banco de dados.');
    }
}


// ================================================================
// 5. FUNÇÕES DE APOIO
// ================================================================

/**
 * Escapa valores para utilização em HTML.
 */
if (!function_exists('e')) {

    function e($valor): string
    {
        return htmlspecialchars(
            (string)($valor ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}


/**
 * Valida uma URL antes de utilizá-la em src/href.
 */
function url_segura($url, string $padrao = ''): string
{
    $url = trim((string)$url);

    $limpa = preg_replace(
        '/[\x00-\x20\x7F]+/',
        '',
        $url
    );

    if ($limpa === '' || $limpa === null) {
        return $padrao;
    }

    // Bloqueia javascript:, data:, vbscript:, etc.
    if (preg_match(
        '#^[a-z][a-z0-9+.\-]*:#i',
        $limpa
    )) {

        return preg_match(
            '#^https?://#i',
            $limpa
        ) ? $url : $padrao;
    }

    return $url;
}


/**
 * Valida token hexadecimal de recuperação de senha.
 */
function token_hex_valido($token): bool
{
    return is_string($token)
        && preg_match(
            '/^[a-f0-9]{64}$/',
            $token
        ) === 1;
}


/**
 * Retorna o host da requisição de forma segura.
 */
function host_seguro(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return preg_match(
        '/^[A-Za-z0-9.\-]+(:\d{1,5})?$/',
        $host
    )
        ? $host
        : 'localhost';
}


/**
 * JSON seguro para utilização em HTML.
 */
function json_seguro($dados): string
{
    $json = json_encode(
        $dados,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    return $json === false ? '{}' : $json;
}


// ================================================================
// 6. AUTENTICAÇÃO
// ================================================================

function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}


function isAdmin(): bool
{
    return isLoggedIn()
        && (($_SESSION['tipo_usuario'] ?? '') === 'ADMIN');
}


/**
 * Busca endereço do usuário.
 */
function getUsuarioEndereco($usuario_id): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT endereco, latitude, longitude
         FROM usuarios
         WHERE id = ?'
    );

    $stmt->execute([
        (int)$usuario_id
    ]);

    $usuario = $stmt->fetch();

    return $usuario ?: [
        'endereco'  => '',
        'latitude'  => null,
        'longitude' => null
    ];
}


// ================================================================
// 7. CARRINHO
// ================================================================

/**
 * Adiciona produto ao carrinho.
 *
 * O nome e preço são buscados no banco.
 * Os valores enviados pelo navegador são ignorados.
 */
function addToCart(
    $produto_id,
    $nome = null,
    $preco = null,
    $quantidade = 1
): bool {

    global $pdo;

    $produto_id = (int)$produto_id;

    $quantidade = max(
        1,
        min(99, (int)$quantidade)
    );

    if ($produto_id <= 0) {
        return false;
    }

    // Busca o produto diretamente no banco
    $stmt = $pdo->prepare(
        'SELECT id, nome, preco
         FROM produtos
         WHERE id = ?
         AND disponivel = true'
    );

    $stmt->execute([
        $produto_id
    ]);

    $produto = $stmt->fetch();

    if (!$produto) {
        return false;
    }

    // Inicializa carrinho
    if (
        !isset($_SESSION['carrinho']) ||
        !is_array($_SESSION['carrinho'])
    ) {

        $_SESSION['carrinho'] = [];
    }

    // Verifica se já existe
    foreach (
        $_SESSION['carrinho']
        as $i => $item
    ) {

        if (
            ($item['tipo'] ?? '') !== 'personalizada' &&
            (int)($item['produto_id'] ?? 0) === $produto_id
        ) {

            $_SESSION['carrinho'][$i]['quantidade'] =
                min(
                    99,
                    (int)$item['quantidade'] + $quantidade
                );

            return true;
        }
    }

    // Adiciona novo produto
    $_SESSION['carrinho'][] = [

        'tipo'       => 'normal',
        'produto_id' => (int)$produto['id'],
        'nome'       => $produto['nome'],
        'preco'      => (float)$produto['preco'],
        'quantidade' => $quantidade
    ];

    return true;
}


/**
 * Calcula o total dos produtos no carrinho.
 */
function calcularTotalCarrinho(): float
{
    $total = 0.0;

    foreach (
        ($_SESSION['carrinho'] ?? [])
        as $item
    ) {

        $total +=
            (float)($item['preco'] ?? 0)
            *
            (int)($item['quantidade'] ?? 0);
    }

    return round($total, 2);
}


// ================================================================
// 8. DISTÂNCIA E TAXA DE ENTREGA
// ================================================================

/**
 * Calcula distância em quilômetros utilizando Haversine.
 */
function calcularDistancia(
    $lat1,
    $lon1,
    $lat2,
    $lon2
): float {

    $raioTerra = 6371.0;

    $dLat = deg2rad(
        (float)$lat2 - (float)$lat1
    );

    $dLon = deg2rad(
        (float)$lon2 - (float)$lon1
    );

    $a =
        sin($dLat / 2) ** 2
        +
        cos(deg2rad((float)$lat1))
        *
        cos(deg2rad((float)$lat2))
        *
        sin($dLon / 2) ** 2;

    return round(
        $raioTerra *
        2 *
        atan2(
            sqrt($a),
            sqrt(1 - $a)
        ),
        2
    );
}


/**
 * Calcula taxa de entrega.
 *
 * Até KM_INCLUSOS:
 *     TAXA_ENTREGA_BASE
 *
 * Depois:
 *     cada KM adicional acrescenta TAXA_KM_EXTRA
 */
function calcularTaxaEntrega($km): float
{
    $km = max(
        0.0,
        (float)$km
    );

    $taxa = TAXA_ENTREGA_BASE;

    if ($km > KM_INCLUSOS) {

        $taxa +=
            ceil($km - KM_INCLUSOS)
            *
            TAXA_KM_EXTRA;
    }

    return round($taxa, 2);
}


// ================================================================
// 9. CUPONS
// ================================================================

/**
 * Valida um cupom.
 *
 * Estrutura esperada:
 *
 * cupons
 * - id
 * - codigo
 * - tipo_desconto
 * - valor_desconto
 * - valor_minimo_pedido
 * - data_validade
 * - quantidade_max_uso
 * - quantidade_usado
 * - ativo
 */
function validarCupom(
    $codigo,
    $subtotal
): array|false {

    global $pdo;

    $codigo = strtoupper(
        trim((string)$codigo)
    );

    if ($codigo === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM cupons
         WHERE codigo = ?
         AND ativo = TRUE
         AND data_validade >= CURRENT_DATE
         AND (
             quantidade_max_uso = 0
             OR quantidade_usado < quantidade_max_uso
         )'
    );

    $stmt->execute([
        $codigo
    ]);

    $cupom = $stmt->fetch();

    if (!$cupom) {
        return false;
    }

    // Verifica valor mínimo do pedido
    if (
        (float)$subtotal <
        (float)$cupom['valor_minimo_pedido']
    ) {

        return false;
    }

    return $cupom;
}


/**
 * Calcula o desconto de um cupom.
 */
function calcularDescontoCupom(
    $cupom,
    $subtotal,
    $frete = 0
): float {

    $tipo = strtolower(
        (string)$cupom['tipo_desconto']
    );

    $valor = (float)$cupom['valor_desconto'];

    // ============================================================
    // CUPOM PERCENTUAL
    // ============================================================

    if ($tipo === 'percentual') {

        /*
         * Cupom FRETE:
         *
         * Exemplo:
         * FRETEGRATIS = 100%
         *
         * O desconto será o valor inteiro do frete.
         */
        if (
            $valor == 100 &&
            strpos(
                strtoupper($cupom['codigo']),
                'FRETE'
            ) !== false
        ) {

            return round(
                (float)$frete,
                2
            );
        }

        $desconto =
            (float)$subtotal
            *
            ($valor / 100);

        return round(
            min(
                $desconto,
                (float)$subtotal
            ),
            2
        );
    }


    // ============================================================
    // CUPOM VALOR FIXO
    // ============================================================

    $desconto = min(
        $valor,
        (float)$subtotal
    );

    return round(
        $desconto,
        2
    );
}


/**
 * Aplica cupom e salva na sessão.
 */
function aplicarCupom(
    $codigo,
    $subtotal,
    $frete
): bool {

    $cupom = validarCupom(
        $codigo,
        $subtotal
    );

    if (!$cupom) {
        return false;
    }

    $desconto = calcularDescontoCupom(
        $cupom,
        $subtotal,
        $frete
    );

    $_SESSION['cupom'] = [

        'id' => (int)$cupom['id'],

        'codigo' => $cupom['codigo'],

        'tipo' => $cupom['tipo_desconto'],

        'valor' => (float)$cupom['valor_desconto'],

        'desconto_aplicado' => $desconto
    ];

    return true;
}


/**
 * Remove cupom da sessão.
 */
function removerCupom(): void
{
    unset(
        $_SESSION['cupom']
    );
}


/**
 * Registra utilização do cupom no pedido.
 *
 * Também incrementa quantidade_usado.
 */
function registrarUsoCupom(
    $pedido_id,
    $cupom_id,
    $desconto_aplicado
): bool {

    global $pdo;

    try {

        // Registra o uso
        $stmt = $pdo->prepare(
            'INSERT INTO pedido_cupom
            (
                pedido_id,
                cupom_id,
                desconto_aplicado
            )
            VALUES (?, ?, ?)'
        );

        $stmt->execute([
            (int)$pedido_id,
            (int)$cupom_id,
            (float)$desconto_aplicado
        ]);


        // Incrementa contador
        $stmt = $pdo->prepare(
            'UPDATE cupons
             SET quantidade_usado =
                 quantidade_usado + 1
             WHERE id = ?'
        );

        $stmt->execute([
            (int)$cupom_id
        ]);

        return true;

    } catch (PDOException $ex) {

        error_log(
            'registrarUsoCupom: ' .
            $ex->getMessage()
        );

        return false;
    }
}