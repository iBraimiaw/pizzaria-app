<?php
/**
 * entrega.php — CLIENTE do microserviço de entrega.
 *
 * O site não calcula mais frete sozinho: ele PERGUNTA ao microserviço
 * (container "entrega") por HTTP. Se o serviço estiver fora do ar, usamos o
 * cálculo local antigo (calcularDistancia/calcularTaxaEntrega) como plano B.
 * Isso se chama FALLBACK: o cliente consegue fechar o pedido mesmo com o
 * microserviço caído.
 *
 * Depende de includes/functions.php (precisa das funções de cálculo local).
 */

/** Tempo máximo esperando o microserviço. Curto para a página não "travar". */
const ENTREGA_TIMEOUT_SEG = 2;
const ENTREGA_TIMEOUT_CONEXAO_SEG = 1;

/** Distância usada quando o usuário não tem coordenadas cadastradas. */
const ENTREGA_DISTANCIA_PADRAO_KM = 2.5;

/**
 * "Disjuntor" (circuit breaker): depois de uma falha, passamos ENTREGA_PAUSA_SEG
 * segundos sem tentar o microserviço e vamos direto para o cálculo local.
 * Sem isso, TODA página esperaria o timeout de um serviço que já sabemos que caiu.
 * A marca fica num arquivo temporário, compartilhado por todos os visitantes.
 */
const ENTREGA_PAUSA_SEG = 10;
define('ENTREGA_ARQUIVO_DISJUNTOR', sys_get_temp_dir() . '/entrega_indisponivel_ate');

function servicoEntregaEmPausa(): bool
{
    $ate = (int) @file_get_contents(ENTREGA_ARQUIVO_DISJUNTOR);
    return $ate > time();
}

function registrarFalhaServicoEntrega(string $motivo): void
{
    // error_log vai para o log do Apache -> "docker compose logs web"
    error_log("[entrega] microservico indisponivel ($motivo) - usando calculo local por " . ENTREGA_PAUSA_SEG . 's');
    @file_put_contents(ENTREGA_ARQUIVO_DISJUNTOR, (string) (time() + ENTREGA_PAUSA_SEG));
}

/**
 * Chama POST /v1/entrega/calcular no microserviço.
 *
 * Devolve o array da resposta (distancia_km, taxa_entrega, tempo_estimado_min,
 * dentro_area) ou null se o serviço não respondeu direito (fora do ar, timeout,
 * chave errada...). Quem chama decide o que fazer com o null (fallback).
 */
function consultarServicoEntrega(float $latitude, float $longitude): ?array
{
    if (servicoEntregaEmPausa()) {
        return null;   // falhou há pouco: nem tenta, vai direto para o fallback
    }

    $url = rtrim(ENTREGA_SERVICE_URL, '/') . '/v1/entrega/calcular';
    $corpo = json_encode(['latitude' => $latitude, 'longitude' => $longitude]);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $corpo,
        CURLOPT_RETURNTRANSFER => true,               // devolve a resposta em vez de imprimir
        CURLOPT_CONNECTTIMEOUT => ENTREGA_TIMEOUT_CONEXAO_SEG,
        CURLOPT_TIMEOUT        => ENTREGA_TIMEOUT_SEG,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Service-Key: ' . ENTREGA_SERVICE_KEY,  // "senha" entre os serviços
        ],
    ]);

    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($curl);
    curl_close($curl);

    if ($resposta === false) {
        registrarFalhaServicoEntrega($erroCurl);
        return null;
    }

    $dados = json_decode($resposta, true);

    // 200 = dentro da área; 422 = fora da área. Os dois são respostas válidas.
    if (($status === 200 || $status === 422) && is_array($dados) && isset($dados['distancia_km'])) {
        return $dados;
    }

    registrarFalhaServicoEntrega("resposta inesperada HTTP $status");
    return null;
}

/**
 * Calcula a entrega para um usuário: tenta o microserviço, senão o cálculo local.
 *
 * Sempre devolve o mesmo formato, venha de onde vier:
 *   distancia_km, taxa_entrega, tempo_estimado_min (null no fallback),
 *   dentro_area, origem:
 *     'microservico'    -> o serviço respondeu
 *     'local'           -> o serviço NÃO respondeu (fallback)
 *     'sem_coordenadas' -> endereço sem latitude/longitude: nem dá para
 *                          perguntar ao serviço, usamos a distância padrão.
 *                          É diferente de "serviço fora do ar".
 */
function calcularEntregaUsuario(int $usuarioId): array
{
    $usuario = getUsuarioEndereco($usuarioId);
    $temCoordenadas = $usuario && $usuario['latitude'] && $usuario['longitude'];

    if ($temCoordenadas) {
        $dados = consultarServicoEntrega((float) $usuario['latitude'], (float) $usuario['longitude']);
        if ($dados !== null) {
            return [
                'distancia_km'       => (float) $dados['distancia_km'],
                'taxa_entrega'       => (float) ($dados['taxa_entrega'] ?? 0),
                'tempo_estimado_min' => $dados['tempo_estimado_min'] ?? null,
                'dentro_area'        => (bool) $dados['dentro_area'],
                'origem'             => 'microservico',
            ];
        }
        $distancia = calcularDistancia($usuario['latitude'], $usuario['longitude'], PIZZARIA_LAT, PIZZARIA_LNG);
        $origem = 'local';             // serviço fora do ar: plano B
    } else {
        // Sem coordenadas não dá para perguntar ao serviço: usa distância padrão.
        $distancia = ENTREGA_DISTANCIA_PADRAO_KM;
        $origem = 'sem_coordenadas';   // o serviço pode estar no ar; o problema é o endereço
    }

    // Regra antiga, calculada aqui mesmo no site.
    return [
        'distancia_km'       => $distancia,
        'taxa_entrega'       => calcularTaxaEntrega($distancia),
        'tempo_estimado_min' => null,
        'dentro_area'        => true,   // o cálculo local não tem limite de raio
        'origem'             => $origem,
    ];
}
