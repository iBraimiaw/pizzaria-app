<?php
/**
 * Microserviço de ENTREGA — front controller.
 *
 * "Front controller" = TODA requisição entra por este arquivo (o Apache manda
 * tudo para cá via FallbackResource). Aqui fica só a parte HTTP:
 *   1. registrar log;  2. conferir a chave do serviço;  3. rotear;  4. validar entrada.
 * A regra de negócio (distância, frete, tempo) mora em src/.
 *
 * Endpoints:
 *   GET  /health                         -> está vivo? (público, usado pelo healthcheck)
 *   GET  /v1/entrega/regras              -> faixas de frete e raio máximo
 *   POST /v1/entrega/calcular            -> {latitude, longitude} => distância, taxa, tempo
 *   GET  /v1/entrega/status/{pedido_id}?criado_em=...  -> status simulado pelo tempo
 */

require __DIR__ . '/../src/Http.php';
require __DIR__ . '/../src/CalculadoraEntrega.php';
require __DIR__ . '/../src/SimuladorStatus.php';

const VERSAO = '1.0.0';

// --- Configuração vinda de variáveis de ambiente (docker-compose / .env) ---
// Nada de segredo escrito no código: a chave muda por ambiente sem mexer aqui.
$chaveEsperada = getenv('ENTREGA_SERVICE_KEY') ?: '';

// Chave padrão do repositório (ou curta demais) fora de 'dev' = qualquer um
// que leu o código usa o serviço. Nesse caso o serviço se recusa a atender
// (falha dura) até alguém definir uma chave forte (ex.: openssl rand -hex 32).
const CHAVE_PADRAO_DEV = 'chave-dev-entrega';
const CHAVE_TAMANHO_MINIMO = 32;
$ambiente = getenv('APP_ENV') ?: 'dev';
$chaveFraca = $chaveEsperada === CHAVE_PADRAO_DEV || strlen($chaveEsperada) < CHAVE_TAMANHO_MINIMO;
$latPizzaria   = (float) (getenv('PIZZARIA_LAT') ?: -20.4628);
$lngPizzaria   = (float) (getenv('PIZZARIA_LNG') ?: -54.6152);
$raioMaximoKm  = (float) (getenv('RAIO_MAXIMO_KM') ?: 15);

$metodo  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$caminho = rtrim($caminho, '/') ?: '/';   // "/health/" vira "/health"
$inicio  = microtime(true);

// --- 1. Log de TODA requisição (vai para o stdout do container) ---
// register_shutdown_function roda no fim do script, mesmo depois de um exit,
// então conseguimos registrar o status HTTP final e quanto tempo levou.
register_shutdown_function(function () use ($metodo, $caminho, $inicio) {
    $ms = (int) round((microtime(true) - $inicio) * 1000);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    error_log(sprintf('[entrega] %s %s -> %d (%d ms) ip=%s', $metodo, $caminho, http_response_code(), $ms, $ip));
});

// --- 2. Rota pública: /health (não exige chave, o Docker precisa consultar) ---
if ($caminho === '/health') {
    if ($metodo !== 'GET') {
        Http::erro(405, 'Método não permitido. Use GET.');
    }
    $iniciadoEm = (int) @file_get_contents('/tmp/iniciado_em');
    Http::json(200, [
        'status'       => 'ok',
        'servico'      => 'entrega',
        'versao'       => VERSAO,
        'timestamp'    => date('c'),
        'uptime_seg'   => $iniciadoEm > 0 ? time() - $iniciadoEm : null,
    ]);
}

// --- 3. Segurança entre serviços: chave compartilhada no header X-Service-Key ---
// Só quem conhece a chave (o site) pode usar a API. hash_equals compara em
// tempo constante: evita "timing attack" (descobrir a chave medindo o tempo
// que a comparação demora, letra por letra).
if ($ambiente !== 'dev' && $chaveFraca) {
    error_log('[entrega] BLOQUEADO: ENTREGA_SERVICE_KEY fraca ou padrao fora de dev.');
    Http::erro(500, 'Serviço indisponível: configuração de segurança inválida.');
}
$chaveRecebida = Http::cabecalho('X-Service-Key');
if ($chaveEsperada === '' || $chaveRecebida === '' || !hash_equals($chaveEsperada, $chaveRecebida)) {
    Http::erro(401, 'Não autorizado: header X-Service-Key ausente ou inválido.');
}

$calculadora = new CalculadoraEntrega($latPizzaria, $lngPizzaria, $raioMaximoKm);

// --- 4. Roteamento simples: método + caminho ---
if ($caminho === '/v1/entrega/regras') {
    if ($metodo !== 'GET') {
        Http::erro(405, 'Método não permitido. Use GET.');
    }
    Http::json(200, $calculadora->regras());
}

if ($caminho === '/v1/entrega/calcular') {
    if ($metodo !== 'POST') {
        Http::erro(405, 'Método não permitido. Use POST.');
    }

    $dados = Http::corpoJson();
    if ($dados === null) {
        Http::erro(400, 'Corpo inválido: envie um JSON, ex.: {"latitude": -20.45, "longitude": -54.59}.');
    }

    // Aceita {latitude, longitude} ou {destino: {latitude, longitude}}.
    $destino = isset($dados['destino']) && is_array($dados['destino']) ? $dados['destino'] : $dados;
    $lat = $destino['latitude'] ?? null;
    $lng = $destino['longitude'] ?? null;

    // Validação: nunca confiar na entrada. Tem que ser número e estar no intervalo do planeta.
    if (!is_int($lat) && !is_float($lat)) {
        Http::erro(400, 'Campo "latitude" é obrigatório e deve ser numérico.');
    }
    if (!is_int($lng) && !is_float($lng)) {
        Http::erro(400, 'Campo "longitude" é obrigatório e deve ser numérico.');
    }
    if ($lat < -90 || $lat > 90) {
        Http::erro(400, 'Campo "latitude" deve estar entre -90 e 90.');
    }
    if ($lng < -180 || $lng > 180) {
        Http::erro(400, 'Campo "longitude" deve estar entre -180 e 180.');
    }

    $resultado = $calculadora->calcular((float) $lat, (float) $lng);

    // 422 = "entendi o pedido, ele é válido, mas não consigo atender" (fora da área).
    if (!$resultado['dentro_area']) {
        Http::json(422, ['erro' => 'Endereço fora da área de entrega.'] + $resultado);
    }
    Http::json(200, $resultado);
}

// /v1/entrega/status/{pedido_id} — o {pedido_id} é capturado pela regex.
if (preg_match('#^/v1/entrega/status/(\d+)$#', $caminho, $partes)) {
    if ($metodo !== 'GET') {
        Http::erro(405, 'Método não permitido. Use GET.');
    }

    // criado_em pode ser timestamp (1700000000) ou data (2026-10-18 19:30:00).
    $criadoEm = $_GET['criado_em'] ?? '';
    $timestamp = ctype_digit($criadoEm) ? (int) $criadoEm : strtotime($criadoEm);
    if ($criadoEm === '' || $timestamp === false) {
        Http::erro(400, 'Parâmetro "criado_em" é obrigatório (timestamp ou data, ex.: 2026-10-18 19:30:00).');
    }

    $simulador = new SimuladorStatus();
    Http::json(200, ['pedido_id' => (int) $partes[1]] + $simulador->status($timestamp, time()));
}

Http::erro(404, 'Rota não encontrada.');
