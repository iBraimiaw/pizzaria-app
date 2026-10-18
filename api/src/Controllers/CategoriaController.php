<?php
/**
 * GET /api/v1/categorias — categorias do cardápio (rota pública).
 */
class CategoriaController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(): void
    {
        $categorias = $this->pdo->query('SELECT id, nome FROM categorias ORDER BY id')->fetchAll();

        Resposta::json(['dados' => array_map(
            fn (array $categoria): array => ['id' => (int) $categoria['id'], 'nome' => $categoria['nome']],
            $categorias
        )]);
    }
}
