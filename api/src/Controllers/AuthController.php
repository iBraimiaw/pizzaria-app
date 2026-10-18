<?php
/**
 * Rotas de autenticação:
 *   POST /api/v1/auth/login  -> troca e-mail + senha por um token JWT
 *   GET  /api/v1/auth/me     -> quem é o dono deste token?
 *
 * A lógica de senha NÃO é reescrita aqui: reaproveitamos as funções do
 * includes/security.php (as mesmas do login.php do site). Assim, site e API
 * têm exatamente as mesmas regras: bcrypt, migração do MD5, bloqueio por
 * força bruta e log de auditoria.
 */
class AuthController
{
    /** Limite de tamanho só para não processar textos gigantes (DoS). */
    private const EMAIL_TAMANHO_MAXIMO = 150;
    private const SENHA_TAMANHO_MAXIMO = 200;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Jwt $jwt,
        private readonly Autenticacao $auth
    ) {
    }

    public function login(): void
    {
        $corpo = Requisicao::corpoJson(API_CORPO_MAXIMO_BYTES);

        $email = $corpo['email'] ?? null;
        $senha = $corpo['senha'] ?? null;
        if (!is_string($email) || !is_string($senha) || trim($email) === '' || $senha === '') {
            throw new ErroApi(422, 'validacao', 'Informe "email" e "senha" (textos).');
        }
        if (strlen($email) > self::EMAIL_TAMANHO_MAXIMO || strlen($senha) > self::SENHA_TAMANHO_MAXIMO) {
            throw new ErroApi(422, 'validacao', 'E-mail ou senha longos demais.');
        }

        $email = strtolower(trim($email));
        $ip = obter_ip_cliente();

        // 1) Força bruta: o MESMO contador do site (e-mail + IP). Quem foi
        //    bloqueado no site também está bloqueado na API, e vice-versa.
        $segundos = verificar_bloqueio($this->pdo, $email, $ip);
        if ($segundos > 0) {
            registrar_evento_seguranca($this->pdo, 'bloqueio', null, "API: login bloqueado para $email");
            throw self::erroBloqueio($segundos);
        }

        // 2) Confere a senha (bcrypt; se for MD5 antigo, migra na hora)
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $senhaOk = verificar_senha_e_migrar($this->pdo, $usuario, $senha);
        } else {
            simular_verificacao_senha($senha);   // mesmo tempo de resposta: não revela se o e-mail existe
            $senhaOk = false;
        }

        // 3) Falhou: anota a tentativa e responde 401 com mensagem genérica
        if (!$senhaOk) {
            registrar_tentativa_falha($this->pdo, $email, $ip);
            registrar_evento_seguranca($this->pdo, 'login_falha', $usuario ? (int) $usuario['id'] : null, "API: e-mail $email");

            $segundos = verificar_bloqueio($this->pdo, $email, $ip);
            if ($segundos > 0) {
                registrar_evento_seguranca($this->pdo, 'bloqueio', null, "API: login bloqueado para $email");
                throw self::erroBloqueio($segundos);
            }
            throw new ErroApi(401, 'credenciais_invalidas', 'E-mail ou senha incorretos.');
        }

        // 4) Sucesso: zera as falhas e emite o token
        limpar_tentativas($this->pdo, $email, $ip);
        registrar_evento_seguranca($this->pdo, 'login_ok', (int) $usuario['id'], 'API (JWT)');

        // "sub" (subject) = de quem é o token. Só o necessário: o token pode ser lido por qualquer um.
        $token = $this->jwt->gerar(['sub' => (string) $usuario['id']], JWT_VALIDADE_SEG);

        Resposta::json([
            'token'      => $token,
            'tipo'       => 'Bearer',
            'expira_em'  => date(DATE_ATOM, time() + JWT_VALIDADE_SEG),
            'expira_em_segundos' => JWT_VALIDADE_SEG,
            'usuario'    => Autenticacao::formatarUsuario($usuario),
        ]);
    }

    public function me(): void
    {
        $usuario = $this->auth->usuarioLogado();
        Resposta::json(['usuario' => Autenticacao::formatarUsuario($usuario)]);
    }

    /** 429 = "Too Many Requests". Retry-After diz em quantos segundos tentar de novo. */
    private static function erroBloqueio(int $segundos): ErroApi
    {
        return new ErroApi(
            429,
            'muitas_tentativas',
            'Muitas tentativas erradas. Tente novamente em ' . formatar_tempo_bloqueio($segundos) . '.',
            ['Retry-After' => (string) $segundos]
        );
    }
}
