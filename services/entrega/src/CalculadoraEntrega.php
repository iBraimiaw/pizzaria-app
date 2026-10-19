<?php
/**
 * CalculadoraEntrega — a REGRA DE NEGÓCIO do microserviço.
 *
 * Esta classe não sabe nada de HTTP, JSON ou banco de dados: recebe números
 * e devolve números. Separar assim deixa a regra fácil de ler e de testar,
 * e o index.php (camada HTTP) fica só cuidando de requisição/resposta.
 */
class CalculadoraEntrega
{
    /** Raio médio da Terra em km (usado na fórmula de Haversine). */
    private const RAIO_TERRA_KM = 6371;

    /** Tempo fixo de preparo da pizza, em minutos. */
    private const TEMPO_PREPARO_MIN = 20;

    /** Tempo de deslocamento do motoboy por km, em minutos. */
    private const MINUTOS_POR_KM = 3;

    /**
     * Faixas de frete: "até X km, cobra R$ Y".
     * Ficam numa lista (e não em vários if) para a regra ser lida como uma tabela
     * e para o endpoint /v1/entrega/regras devolver exatamente o que é aplicado.
     */
    private const FAIXAS = [
        ['ate_km' => 3,  'taxa' => 5.00],
        ['ate_km' => 6,  'taxa' => 8.00],
        ['ate_km' => 10, 'taxa' => 12.00],
        ['ate_km' => 15, 'taxa' => 18.00],
    ];

    public function __construct(
        private float $latPizzaria,
        private float $lngPizzaria,
        private float $raioMaximoKm
    ) {
    }

    /**
     * Calcula tudo sobre a entrega até o destino informado.
     * Se o destino estiver além do raio máximo, "dentro_area" volta false
     * e a taxa fica null (quem decide o código HTTP 422 é o index.php).
     */
    public function calcular(float $latDestino, float $lngDestino): array
    {
        $distancia = $this->distanciaKm($this->latPizzaria, $this->lngPizzaria, $latDestino, $lngDestino);
        $dentroArea = $distancia <= $this->raioMaximoKm;

        return [
            'distancia_km'       => $distancia,
            'taxa_entrega'       => $dentroArea ? $this->taxaPorDistancia($distancia) : null,
            'tempo_estimado_min' => $dentroArea ? $this->tempoEstimadoMin($distancia) : null,
            'dentro_area'        => $dentroArea,
            'raio_maximo_km'     => $this->raioMaximoKm,
        ];
    }

    /** Devolve as regras atuais (faixas, raio, tempos) para quem quiser consultar. */
    public function regras(): array
    {
        $faixas = [];
        $inicio = 0;
        foreach (self::FAIXAS as $faixa) {
            // Faixas acima do raio máximo não valem: não entregamos lá.
            if ($inicio >= $this->raioMaximoKm) {
                break;
            }
            $faixas[] = [
                'de_km'  => $inicio,
                'ate_km' => min($faixa['ate_km'], $this->raioMaximoKm),
                'taxa'   => $faixa['taxa'],
            ];
            $inicio = $faixa['ate_km'];
        }

        return [
            'pizzaria'          => ['latitude' => $this->latPizzaria, 'longitude' => $this->lngPizzaria],
            'raio_maximo_km'    => $this->raioMaximoKm,
            'faixas'            => $faixas,
            'tempo_preparo_min' => self::TEMPO_PREPARO_MIN,
            'minutos_por_km'    => self::MINUTOS_POR_KM,
        ];
    }

    /**
     * Fórmula de Haversine: distância "em linha reta" entre dois pontos
     * na superfície da Terra (considerando que ela é uma esfera).
     */
    public function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round(self::RAIO_TERRA_KM * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /** Procura a primeira faixa em que a distância cabe. */
    private function taxaPorDistancia(float $distancia): float
    {
        foreach (self::FAIXAS as $faixa) {
            if ($distancia <= $faixa['ate_km']) {
                return $faixa['taxa'];
            }
        }
        // Só chega aqui se o raio máximo for maior que a última faixa:
        // nesse caso cobra o valor da última faixa.
        return self::FAIXAS[count(self::FAIXAS) - 1]['taxa'];
    }

    /** Preparo + deslocamento, arredondado para cima (melhor prometer a mais). */
    private function tempoEstimadoMin(float $distancia): int
    {
        return (int) ceil(self::TEMPO_PREPARO_MIN + $distancia * self::MINUTOS_POR_KM);
    }
}
