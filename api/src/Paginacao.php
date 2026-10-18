<?php
/**
 * Paginacao — lê ?pagina= e ?limite= e devolve o resumo para a resposta.
 *
 * O "teto" (API_LIMITE_MAXIMO) existe para ninguém pedir ?limite=1000000 e
 * fazer o servidor montar uma resposta gigante. Pediu mais? Recebe o teto.
 */
class Paginacao
{
    /** @return array{0:int,1:int,2:int} [pagina, limite, deslocamento para o OFFSET] */
    public static function lerDaQuery(): array
    {
        $pagina = Requisicao::inteiroDaQuery('pagina', 1);
        $limite = Requisicao::inteiroDaQuery('limite', API_LIMITE_PADRAO);

        if ($pagina < 1 || $limite < 1) {
            throw new ErroApi(400, 'parametro_invalido', "Os parâmetros 'pagina' e 'limite' precisam ser maiores que zero.");
        }
        $limite = min($limite, API_LIMITE_MAXIMO);

        return [$pagina, $limite, ($pagina - 1) * $limite];
    }

    public static function resumo(int $pagina, int $limite, int $total): array
    {
        return [
            'pagina'        => $pagina,
            'limite'        => $limite,
            'total'         => $total,
            'total_paginas' => (int) ceil($total / $limite),
        ];
    }
}
