<?php

// ---------------------------------------------------------------
// 1) CONFIGURAÇÕES  (AJUSTE ESTES VALORES AO SEU AMBIENTE)
// ---------------------------------------------------------------
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'pizzaria');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Localização e endereço da pizzaria (usados no cálculo de distância e no rodapé)
defined('PIZZARIA_LAT')      || define('PIZZARIA_LAT', -20.4697);
defined('PIZZARIA_LNG')      || define('PIZZARIA_LNG', -54.6201);
defined('PIZZARIA_ENDERECO') || define('PIZZARIA_ENDERECO', 'Rua Exemplo, 123 - Centro, Campo Grande - MS');

// Regra de taxa de entrega: valor base até KM_INCLUSOS; cada km adicional (arredondado p/ cima) soma TAXA_KM_EXTRA
defined('TAXA_ENTREGA_BASE') || define('TAXA_ENTREGA_BASE', 5.00);
defined('KM_INCLUSOS')       || define('KM_INCLUSOS', 3.0);
defined('TAXA_KM_EXTRA')     || define('TAXA_KM_EXTRA', 1.50);

// ---------------------------------------------------------------
// 2) SESSÃO SEGURA
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------
// 3) CABEÇALHOS DE SEGURANÇA
// ---------------------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: https: http:; "
        . "connect-src 'self' https://nominatim.openstreetmap.org; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

// ---------------------------------------------------------------
// 4) CONEXÃO COM O BANCO (PDO)
// ---------------------------------------------------------------
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $ex) {
        error_log('Falha na conexão com o banco: ' . $ex->getMessage());
        http_response_code(500);
        exit('Erro ao conectar ao banco de dados.');
    }
}

// ---------------------------------------------------------------
// 5) FUNÇÕES DE APOIO
// ---------------------------------------------------------------

/**
 * Escapa qualquer valor para uso seguro em HTML (texto e atributos).
 * REGRA DO PROJETO: todo dado dinâmico impresso numa página passa por e().
 * Uso: <?= e($usuario['nome']) ?>
 */
if (!function_exists('e')) {
    function e($valor): string
    {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Valida uma URL vinda do banco (ex.: imagem_url) antes de usá-la em src/href.
 * Aceita http(s) e caminhos relativos; rejeita javascript:, data:, vbscript: etc.
 * Lembre de combinar com e():  src="<?= e(url_segura($url)) ?>"
 */
function url_segura($url, string $padrao = ''): string
{
    $url = trim((string)$url);
    $limpa = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
    if ($limpa === '' || $limpa === null) {
        return $padrao;
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $limpa)) {
        return preg_match('#^https?://#i', $limpa) ? $url : $padrao;
    }
    return $url;
}

/** Confere se o token de recuperação de senha tem o formato esperado (64 caracteres hexadecimais). */
function token_hex_valido($token): bool
{
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

/** Host da requisição validado (o cabeçalho Host é controlado pelo cliente e não deve ser confiado). */
function host_seguro(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host) ? $host : 'localhost';
}

/** json_encode que também escapa < > & ' " (impede que o JSON seja interpretado como HTML). */
function json_seguro($dados): string
{
    $json = json_encode(
        $dados,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    return $json === false ? '{}' : $json;
}

// ----------------------------- Autenticação -----------------------------

function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['tipo_usuario'] ?? '') === 'ADMIN');
}

function getUsuarioEndereco($usuario_id): array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT endereco, latitude, longitude FROM usuarios WHERE id = ?');
    $stmt->execute([(int)$usuario_id]);
    $usuario = $stmt->fetch();
    return $usuario ?: ['endereco' => '', 'latitude' => null, 'longitude' => null];
}

// ------------------------------ Carrinho -------------------------------

/**
 * Adiciona um produto ao carrinho.
 * Nome e preço são SEMPRE lidos do banco pelo id: o que veio do navegador é ignorado
 * (evita texto malicioso no nome do produto e adulteração de preço).
 * Os parâmetros $nome e $preco existem só por compatibilidade com o código antigo.
 */
function addToCart($produto_id, $nome = null, $preco = null, $quantidade = 1): bool
{
    global $pdo;

    $produto_id = (int)$produto_id;
    $quantidade = max(1, min(99, (int)$quantidade));
    if ($produto_id <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, nome, preco FROM produtos WHERE id = ? AND disponivel = true');
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();
    if (!$produto) {
        return false;
    }

    if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }

    // Se o produto já está no carrinho, soma a quantidade
    foreach ($_SESSION['carrinho'] as $i => $item) {
        if (($item['tipo'] ?? '') !== 'personalizada' && (int)($item['produto_id'] ?? 0) === $produto_id) {
            $_SESSION['carrinho'][$i]['quantidade'] = min(99, (int)$item['quantidade'] + $quantidade);
            return true;
        }
    }

    $_SESSION['carrinho'][] = [
        'produto_id' => (int)$produto['id'],
        'nome'       => $produto['nome'],
        'preco'      => (float)$produto['preco'],
        'quantidade' => $quantidade,
    ];
    return true;
}

function calcularTotalCarrinho(): float
{
    $total = 0.0;
    foreach (($_SESSION['carrinho'] ?? []) as $item) {
        $total += (float)($item['preco'] ?? 0) * (int)($item['quantidade'] ?? 0);
    }
    return $total;
}

// ------------------------ Distância e taxa de entrega -------------------

/** Distância em km entre dois pontos (fórmula de Haversine). */
function calcularDistancia($lat1, $lon1, $lat2, $lon2): float
{
    $raioTerra = 6371.0;
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLon = deg2rad((float)$lon2 - (float)$lon1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLon / 2) ** 2;
    return round($raioTerra * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

function calcularTaxaEntrega($km): float
{
    $km = max(0.0, (float)$km);
    $taxa = TAXA_ENTREGA_BASE;
    if ($km > KM_INCLUSOS) {
        $taxa += ceil($km - KM_INCLUSOS) * TAXA_KM_EXTRA;
    }
    return round($taxa, 2);
}

// -------------------------------- Cupom ---------------------------------

/**
 * Valida o cupom e guarda em $_SESSION['cupom'].
 * Supõe as tabelas:  cupons(id, codigo, tipo, valor, valor_minimo, validade, ativo)
 *                    tipo = 'percentual' ou 'fixo'
 */
function aplicarCupom($codigo, $subtotal, $frete): bool
{
    global $pdo;

    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM cupons
              WHERE codigo = ? AND ativo = TRUE
                AND (validade IS NULL OR validade >= CURDATE())'
        );
        $stmt->execute([$codigo]);
        $cupom = $stmt->fetch();
    } catch (PDOException $ex) {
        error_log('aplicarCupom: ' . $ex->getMessage());
        return false;
    }

    if (!$cupom) {
        return false;
    }
    if ((float)$subtotal < (float)($cupom['valor_minimo'] ?? 0)) {
        return false;
    }

    $tipo  = strtolower((string)($cupom['tipo'] ?? ''));
    $valor = (float)$cupom['valor'];
    // Cupons de frete incidem sobre a taxa de entrega; os demais sobre o subtotal
    $base  = (strpos($codigo, 'FRETE') !== false) ? (float)$frete : (float)$subtotal;

    $desconto = ($tipo === 'percentual') ? $base * $valor / 100 : $valor;
    $desconto = round(min($desconto, $base), 2);

    $_SESSION['cupom'] = [
        'id'                => (int)$cupom['id'],
        'codigo'            => $cupom['codigo'],
        'tipo'              => $tipo,
        'valor'             => $valor,
        'desconto_aplicado' => $desconto,
    ];
    return true;
}

function removerCupom(): void
{
    unset($_SESSION['cupom']);
}

/** Registra o uso do cupom em cupons_usados(cupom_id, pedido_id, desconto_aplicado). */
function registrarUsoCupom($pedido_id, $cupom_id, $desconto): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO cupons_usados (cupom_id, pedido_id, desconto_aplicado) VALUES (?, ?, ?)'
        );
        $stmt->execute([(int)$cupom_id, (int)$pedido_id, (float)$desconto]);
    } catch (PDOException $ex) {
        error_log('registrarUsoCupom: ' . $ex->getMessage());
    }
}
