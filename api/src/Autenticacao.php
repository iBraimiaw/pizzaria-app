<?php
/**
 * Autenticacao — "porteiro" das rotas protegidas.
 *
 * Toda rota que precisa de login chama $auth->usuarioLogado(). Ele:
 *   1. lê o header Authorization: Bearer <token>   (sem header -> 401)
 *   2. valida o JWT (assinatura, expiração...)     (inválido   -> 401)
 *   3. busca o usuário no banco pelo "sub" do token (apagado    -> 401)
 *
 * O id do usuário vem SEMPRE do token (assinado pelo servidor), nunca de um
 * parâmetro que o cliente escolhe. É isso que impede ver pedido dos outros.
 */
class Autenticacao
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Jwt $jwt
    ) {
    }

    /** Devolve a linha do usuário dono do token, ou lança ErroApi 401. */
    public function usuarioLogado(): array
    {
        $token = Requisicao::tokenBearer();
        if ($token === null) {
            throw new ErroApi(401, 'nao_autenticado', 'Envie o header "Authorization: Bearer <token>". Faça login em POST /api/v1/auth/login.', ['WWW-Authenticate' => 'Bearer']);
        }

        $conteudo = $this->jwt->validar($token);

        $stmt = $this->pdo->prepare('SELECT id, nome, email, endereco, telefone, latitude, longitude, tipo_usuario FROM usuarios WHERE id = ?');
        $stmt->execute([(int) ($conteudo['sub'] ?? 0)]);
        $usuario = $stmt->fetch();

        // Token válido, mas a conta foi apagada depois que ele foi emitido
        if (!$usuario) {
            throw new ErroApi(401, 'token_invalido', 'Usuário do token não existe mais.', ['WWW-Authenticate' => 'Bearer']);
        }
        return $usuario;
    }

    /** Dados públicos do usuário (o que pode aparecer numa resposta). Nunca a senha. */
    public static function formatarUsuario(array $usuario): array
    {
        return [
            'id'           => (int) $usuario['id'],
            'nome'         => $usuario['nome'],
            'email'        => $usuario['email'],
            'tipo_usuario' => $usuario['tipo_usuario'],
        ];
    }
}
