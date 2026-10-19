<?php
/**
 * api/index.php — FRONT CONTROLLER da API REST.
 *
 * O api/.htaccess manda TODA requisição /api/... para este arquivo. Aqui a
 * requisição passa por uma "esteira", sempre na mesma ordem:
 *
 *   1. Configura a API (sem sessão, erros fora da tela)
 *   2. Envia headers de segurança e CORS
 *   3. Responde o OPTIONS (pré-checagem do navegador)
 *   4. O Router descobre qual controller chamar
 *   5. Qualquer erro vira JSON {erro:{codigo, mensagem}} com o código HTTP certo
 */

// --- 1. Configuração ---------------------------------------------------------

// Avisa o includes/ que esta requisição é da API: não abre sessão nem cookie.
// Quem identifica o usuário aqui é o token JWT.
define('MODO_API', true);

require_once __DIR__ . '/../includes/functions.php';   // $pdo, config e security.php
require_once __DIR__ . '/../includes/entrega.php';     // cliente do microserviço de entrega

// Na API, erro NUNCA aparece na resposta (quebraria o JSON e vazaria detalhes
// internos). Ele vai só para o log: docker compose logs web
ini_set('display_errors', '0');

const API_VERSAO = '1.0.0';
const JWT_VALIDADE_SEG = 60 * 60;            // token vale 1 hora
const JWT_EMISSOR = 'pizzaria-api';           // "iss" gravado e conferido no token
const API_CORPO_MAXIMO_BYTES = 16 * 1024;     // 16 KB: um pedido normal tem bem menos
const API_LIMITE_PADRAO = 10;                 // itens por página
const API_LIMITE_MAXIMO = 50;                 // teto da paginação

require_once __DIR__ . '/src/ErroApi.php';
require_once __DIR__ . '/src/Resposta.php';
require_once __DIR__ . '/src/Requisicao.php';
require_once __DIR__ . '/src/Paginacao.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/Jwt.php';
require_once __DIR__ . '/src/Autenticacao.php';
require_once __DIR__ . '/src/Controllers/HealthController.php';
require_once __DIR__ . '/src/Controllers/AuthController.php';
require_once __DIR__ . '/src/Controllers/CategoriaController.php';
require_once __DIR__ . '/src/Controllers/ProdutoController.php';
require_once __DIR__ . '/src/Controllers/PedidoController.php';

// Segredo fraco em produção = qualquer um forja tokens: o segredo padrão está
// no repositório, então quem o lê assina um token com o "sub" que quiser.
// Fora de 'dev' isso é FALHA DURA: a API se recusa a responder (500 genérico)
// até alguém definir um JWT_SECRET forte (ex.: openssl rand -hex 32).
// Em 'dev' o padrão é aceito só para o projeto rodar "de primeira".
const JWT_SEGREDO_PADRAO = 'segredo-dev-troque-em-producao';
const JWT_SEGREDO_TAMANHO_MINIMO = 32;
if (APP_ENV !== 'dev' && (JWT_SECRET === JWT_SEGREDO_PADRAO || strlen(JWT_SECRET) < JWT_SEGREDO_TAMANHO_MINIMO)) {
    error_log('[api] BLOQUEADO: JWT_SECRET fraco ou padrao fora de dev. Defina um segredo longo e aleatorio no .env');
    Resposta::erro(500, 'erro_configuracao', 'API indisponível: configuração de segurança inválida.');
}

// --- 2. Headers --------------------------------------------------------------
Resposta::enviarHeadersSeguranca();
Resposta::enviarHeadersCors(env('CORS_ORIGIN', ''));

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Caminho da URL sem o prefixo da pasta: "/api/v1/produtos/3?x=1" -> "/v1/produtos/3"
$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pastaDaApi = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');   // "/api"
if ($pastaDaApi !== '' && str_starts_with($caminho, $pastaDaApi)) {
    $caminho = substr($caminho, strlen($pastaDaApi));
}
$caminho = '/' . trim($caminho, '/');   // "/v1/produtos/" e "/v1/produtos" são a mesma rota

// --- 3. OPTIONS --------------------------------------------------------------
// Antes de um POST com JSON vindo de outro site, o navegador pergunta
// "posso?" com um OPTIONS. Os headers CORS acima já são a resposta.
if ($metodo === 'OPTIONS') {
    Resposta::json(null, 204, ['Allow' => 'GET, POST, OPTIONS']);
}

// --- 4. Rotas ----------------------------------------------------------------
try {
    $jwt = new Jwt(JWT_SECRET, JWT_EMISSOR);
    $auth = new Autenticacao($pdo, $jwt);

    $health    = new HealthController($pdo);
    $login     = new AuthController($pdo, $jwt, $auth);
    $categoria = new CategoriaController($pdo);
    $produto   = new ProdutoController($pdo);
    $pedido    = new PedidoController($pdo, $auth);

    $router = new Router();
    // Públicas
    $router->get('/v1/health', [$health, 'verificar']);
    $router->post('/v1/auth/login', [$login, 'login']);
    $router->get('/v1/categorias', [$categoria, 'listar']);
    $router->get('/v1/produtos', [$produto, 'listar']);
    $router->get('/v1/produtos/{id}', [$produto, 'detalhar']);
    // Protegidas (Bearer token) — a checagem fica dentro de cada controller
    $router->get('/v1/auth/me', [$login, 'me']);
    $router->get('/v1/pedidos', [$pedido, 'listar']);
    $router->get('/v1/pedidos/{id}', [$pedido, 'detalhar']);
    $router->post('/v1/pedidos', [$pedido, 'criar']);

    $router->despachar($metodo, $caminho);

// --- 5. Erros ----------------------------------------------------------------
} catch (ErroApi $erro) {
    // Erro "previsto" (validação, 401, 404...): a mensagem é segura para o cliente
    Resposta::erro($erro->status, $erro->codigo, $erro->getMessage(), $erro->headers);
} catch (Throwable $erro) {
    // Erro inesperado (bug, banco caiu): detalhe SÓ no log, cliente recebe mensagem genérica.
    // Nunca devolvemos o stack trace: ele mostra caminhos de arquivo e SQL.
    error_log('[api] erro interno em ' . $metodo . ' ' . $caminho . ': ' . $erro);
    Resposta::erro(500, 'erro_interno', 'Erro interno. Tente novamente em instantes.');
}
