<?php
/**
 * gerar-token.php — ferramenta de DEMONSTRAÇÃO (só linha de comando).
 *
 * Gera um JWT com a validade que você quiser, inclusive NEGATIVA, para mostrar
 * na apresentação que um token expirado é recusado com 401.
 *
 * Uso (dentro do container web):
 *   docker compose exec web php api/bin/gerar-token.php <usuario_id> <validade_em_segundos>
 *   docker compose exec web php api/bin/gerar-token.php 1 -60     -> token que venceu há 1 minuto
 *
 * Usa o MESMO segredo e o MESMO emissor da API, então a assinatura é válida:
 * o único motivo da recusa será a data de expiração.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);   // nunca roda pelo navegador
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../src/ErroApi.php';
require_once __DIR__ . '/../src/Jwt.php';

const JWT_EMISSOR = 'pizzaria-api';   // precisa ser igual ao do api/index.php

$usuarioId = (int) ($argv[1] ?? 1);
$validade = (int) ($argv[2] ?? -60);

$jwt = new Jwt(JWT_SECRET, JWT_EMISSOR);

// Para um token "já vencido", o iat (criação) também fica no passado
if ($validade < 0) {
    // gerar() usa time() como iat; aqui montamos um token criado 1h antes de vencer
    $agora = time();
    $cabecalho = Jwt::base64UrlCodificar(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $conteudo = Jwt::base64UrlCodificar(json_encode([
        'sub' => (string) $usuarioId,
        'iss' => JWT_EMISSOR,
        'iat' => $agora + $validade - 3600,
        'exp' => $agora + $validade,
    ]));
    $assinatura = Jwt::base64UrlCodificar(hash_hmac('sha256', "$cabecalho.$conteudo", JWT_SECRET, true));
    echo "$cabecalho.$conteudo.$assinatura" . PHP_EOL;
    exit;
}

echo $jwt->gerar(['sub' => (string) $usuarioId], $validade) . PHP_EOL;
