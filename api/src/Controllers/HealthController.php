<?php
/**
 * GET /api/v1/health — "a API está viva?"
 * Usado por monitoramento. Público (sem token) e não revela detalhes internos.
 */
class HealthController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function verificar(): void
    {
        // Testa o banco com a consulta mais barata possível
        try {
            $this->pdo->query('SELECT 1');
            $banco = 'ok';
        } catch (Throwable $erro) {
            error_log('[api] health: banco indisponivel - ' . $erro->getMessage());
            $banco = 'indisponivel';
        }

        $status = $banco === 'ok' ? 200 : 503;   // 503 = "serviço indisponível"
        Resposta::json([
            'status'    => $banco === 'ok' ? 'ok' : 'degradado',
            'versao'    => API_VERSAO,
            'banco'     => $banco,
            'timestamp' => date(DATE_ATOM),
        ], $status);
    }
}
