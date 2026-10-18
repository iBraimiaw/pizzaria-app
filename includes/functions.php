<?php
/**
 * functions.php — Arquivo "base" incluído por todas as páginas.
 *
 * Faz 3 coisas:
 *   1. Carrega configurações (e o módulo de segurança, se existir);
 *   2. Inicia a sessão e abre a conexão com o banco ($pdo);
 *   3. Define as funções usadas pelas páginas (login, carrinho, frete, cupom).
 */

require_once __DIR__ . '/config.php';

// Módulo de segurança é opcional: só carrega se o arquivo existir.
// Assim o app funciona mesmo antes dele ser criado.
if (file_exists(__DIR__ . '/security.php')) {
    require_once __DIR__ . '/security.php';
}

// Inicia a sessão só se ainda não foi iniciada (o security.php pode iniciá-la
// antes, com configurações de cookie mais seguras).
// A API (MODO_API) não usa sessão: ela se identifica pelo token JWT.
if (!defined('MODO_API') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Conexão com o banco (PDO)
// ---------------------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            // Erro de SQL vira exceção (não passa despercebido)
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // fetch() devolve array associativo: $linha['nome']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements "de verdade" no MySQL: protege contra SQL Injection
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Não mostra a mensagem real ao usuário (pode conter usuário/host do banco)
    error_log('Erro de conexão com o banco: ' . $e->getMessage());
    http_response_code(500);
    if (defined('MODO_API')) {
        // A API sempre responde JSON, até no erro de banco
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['erro' => ['codigo' => 'erro_interno', 'mensagem' => 'Serviço indisponível. Tente novamente em instantes.']]));
    }
    exit('Erro ao conectar com o banco de dados. Tente novamente em instantes.');
}

// ---------------------------------------------------------------------------
// Autenticação
// ---------------------------------------------------------------------------

/** O usuário está logado? (o login grava usuario_id na sessão) */
function isLoggedIn(): bool
{
    return isset($_SESSION['usuario_id']);
}

/** O usuário logado é administrador? */
function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['tipo_usuario'] ?? '') === 'ADMIN';
}

/** Busca o endereço (e coordenadas) do usuário para a entrega. */
function getUsuarioEndereco(int $usuario_id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT endereco, latitude, longitude FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    return $usuario ?: null;
}

// ---------------------------------------------------------------------------
// Carrinho
//
// O carrinho fica em $_SESSION['carrinho'] como uma LISTA (índices 0,1,2...).
// Cada item é um array:
//   Produto normal:      ['tipo'=>'normal', 'produto_id', 'nome', 'preco', 'quantidade']
//   Pizza meia a meia:   ['tipo'=>'personalizada', 'sabor1_id', 'sabor2_id',
//                         'sabor1_nome', 'sabor2_nome', 'nome', 'preco', 'quantidade']
// A lista é reindexada com array_values() sempre que um item sai (carrinho.php).
// ---------------------------------------------------------------------------

/** Adiciona um produto ao carrinho; se já existir, só soma a quantidade. */
function addToCart($produto_id, string $nome, $preco, $quantidade): void
{
    $quantidade = max(1, (int) $quantidade);

    if (!isset($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }

    // Procura o mesmo produto já no carrinho (pizza personalizada não entra aqui)
    foreach ($_SESSION['carrinho'] as $indice => $item) {
        $ehNormal = ($item['tipo'] ?? 'normal') === 'normal';
        if ($ehNormal && (int) ($item['produto_id'] ?? 0) === (int) $produto_id) {
            $_SESSION['carrinho'][$indice]['quantidade'] += $quantidade;
            return;
        }
    }

    $_SESSION['carrinho'][] = [
        'tipo'       => 'normal',
        'produto_id' => (int) $produto_id,
        'nome'       => $nome,
        'preco'      => (float) $preco,
        'quantidade' => $quantidade,
    ];
}

/** Soma preço x quantidade de todos os itens do carrinho. */
function calcularTotalCarrinho(): float
{
    $total = 0.0;
    foreach ($_SESSION['carrinho'] ?? [] as $item) {
        $total += (float) $item['preco'] * (int) $item['quantidade'];
    }
    return round($total, 2);
}

// ---------------------------------------------------------------------------
// Entrega (distância e frete)
// ---------------------------------------------------------------------------

/**
 * Distância em km entre dois pontos (latitude/longitude) usando a fórmula de
 * Haversine — considera que a Terra é uma esfera. Ex.: 2.37
 */
function calcularDistancia($lat1, $lng1, $lat2, $lng2): float
{
    $raioTerraKm = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return round($raioTerraKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

/** Frete: valor base até X km; acima disso soma um valor por km. */
function calcularTaxaEntrega($distancia): float
{
    $kmExtras = max(0, (float) $distancia - TAXA_ENTREGA_KM_GRATIS);
    return round(TAXA_ENTREGA_BASE + $kmExtras * TAXA_ENTREGA_POR_KM, 2);
}

// ---------------------------------------------------------------------------
// Cupons de desconto
//
// O cupom aplicado fica em $_SESSION['cupom']:
//   ['id', 'codigo', 'tipo' ('percentual'|'fixo'), 'valor', 'valor_minimo']
// Segurança: a sessão guarda só as REGRAS do cupom, nunca o valor do desconto
// em reais. O desconto é recalculado sobre o subtotal ATUAL toda vez
// (calcularDescontoCupom). Se guardássemos "R$ 214,50" fixo, o cliente podia
// aplicar o cupom com 50 pizzas, tirar 49 e fechar o pedido de graça.
// Cupom de frete grátis = tipo 'percentual', valor 100 e "FRETE" no código
// (o carrinho.php zera a taxa de entrega nesse caso).
// ---------------------------------------------------------------------------

/** Busca no banco um cupom ativo e dentro da validade (ou null). */
function buscarCupomValido(string $codigo): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM cupons
          WHERE codigo = ? AND ativo = TRUE
            AND (validade IS NULL OR validade >= CURDATE())'
    );
    $stmt->execute([$codigo]);
    return $stmt->fetch() ?: null;
}

/** O cupom é de frete grátis? (regra do projeto: 100% + "FRETE" no código) */
function cupomEhFreteGratis(array $cupom): bool
{
    return $cupom['tipo'] === 'percentual'
        && (float) $cupom['valor'] == 100
        && strpos($cupom['codigo'], 'FRETE') !== false;
}

/** O cupom vale para este subtotal? (carrinho não vazio e acima do valor mínimo) */
function cupomValeParaSubtotal(array $cupom, float $subtotal): bool
{
    return $subtotal > 0 && $subtotal >= (float) ($cupom['valor_minimo'] ?? 0);
}

/**
 * Desconto em R$ sobre o subtotal ATUAL. Chamado de novo a cada exibição do
 * carrinho e no fechamento do pedido. Frete grátis devolve 0 aqui porque o
 * benefício dele é zerar a taxa de entrega, não descontar dos produtos.
 */
function calcularDescontoCupom(array $cupom, float $subtotal): float
{
    if (!cupomValeParaSubtotal($cupom, $subtotal) || cupomEhFreteGratis($cupom)) {
        return 0.0;
    }
    if ($cupom['tipo'] === 'percentual') {
        $desconto = $subtotal * (float) $cupom['valor'] / 100;
    } else {
        $desconto = (float) $cupom['valor'];
    }
    // Nunca maior que o próprio subtotal (o pedido não pode ficar negativo)
    return round(min($desconto, $subtotal), 2);
}

/** Valida o cupom e, se for válido, guarda as regras dele na sessão. Retorna true/false. */
function aplicarCupom(string $codigo, float $subtotal): bool
{
    $cupom = buscarCupomValido($codigo);

    if (!$cupom || !cupomValeParaSubtotal($cupom, $subtotal)) {
        return false;
    }

    $_SESSION['cupom'] = [
        'id'           => (int) $cupom['id'],
        'codigo'       => $cupom['codigo'],
        'tipo'         => $cupom['tipo'],
        'valor'        => (float) $cupom['valor'],
        'valor_minimo' => (float) $cupom['valor_minimo'],
    ];
    return true;
}

/** Tira o cupom da sessão. */
function removerCupom(): void
{
    unset($_SESSION['cupom']);
}

/** Grava no banco que o cupom foi usado neste pedido. */
function registrarUsoCupom($pedido_id, $cupom_id, $desconto): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO cupons_uso (pedido_id, cupom_id, desconto) VALUES (?, ?, ?)');
    $stmt->execute([$pedido_id, $cupom_id, $desconto]);
}
