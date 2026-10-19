<?php
/**
 * SimuladorStatus — finge acompanhar um pedido pelo tempo que passou.
 *
 * O microserviço NÃO acessa o banco do site (isolamento). Por isso quem chama
 * informa quando o pedido foi criado, e aqui só olhamos o relógio.
 */
class SimuladorStatus
{
    /** "Até X minutos depois do pedido, o status é Y" (em ordem). */
    private const ETAPAS = [
        ['ate_min' => 5,  'status' => 'recebido'],
        ['ate_min' => 25, 'status' => 'em_preparo'],
        ['ate_min' => 45, 'status' => 'saiu_para_entrega'],
    ];

    /** Depois da última etapa, o pedido já foi entregue. */
    private const STATUS_FINAL = 'entregue';

    public function status(int $criadoEmTimestamp, int $agora): array
    {
        $minutos = (int) floor(max(0, $agora - $criadoEmTimestamp) / 60);

        $status = self::STATUS_FINAL;
        foreach (self::ETAPAS as $etapa) {
            if ($minutos < $etapa['ate_min']) {
                $status = $etapa['status'];
                break;
            }
        }

        return [
            'status'            => $status,
            'minutos_decorridos' => $minutos,
        ];
    }
}
