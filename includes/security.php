<?php
/**
 * security.php — MÓDULO DE SEGURANÇA da Pizzaria.
 *
 * É carregado automaticamente pelo includes/functions.php (antes da sessão e
 * do banco). Reúne, num lugar só, as proteções do site:
 *
 *   1. Headers de segurança      -> enviar_headers_seguranca()
 *   2. Sessão segura + timeout   -> iniciar_sessao_segura()
 *   3. Escape contra XSS         -> e()
 *   4. Token CSRF                -> csrf_token(), campo_csrf(), verificar_csrf()
 *   5. Senhas (bcrypt + migração do MD5) -> validar_forca_senha(), verificar_senha_e_migrar()
 *   6. Força bruta (rate limit)  -> verificar_bloqueio(), registrar_tentativa_falha(), limpar_tentativas()
 *   7. Log de auditoria          -> registrar_evento_seguranca()
 *
 * As funções que recebem $pdo também são usadas pela API (não dependem de página).
 */

// ---------------------------------------------------------------------------
// Constantes (números com nome, nada "mágico")
// ---------------------------------------------------------------------------
const SESSAO_TEMPO_INATIVIDADE = 30 * 60;   // 30 min parado -> sessão expira

const LOGIN_MAX_FALHAS          = 5;        // 5 senhas erradas...
const LOGIN_JANELA_MINUTOS      = 15;       // ...em 15 minutos = bloqueio

const RECUPERACAO_MAX_PEDIDOS   = 3;        // 3 pedidos de "esqueci a senha"...
const RECUPERACAO_JANELA_MINUTOS = 15;      // ...em 15 minutos = bloqueio
const RECUPERACAO_VALIDADE_HORAS = 1;       // link de redefinição vale 1 hora

const SENHA_TAMANHO_MINIMO      = 8;

// ===========================================================================
// 1. HEADERS DE SEGURANÇA
// ===========================================================================

/**
 * Envia os cabeçalhos HTTP que mandam o NAVEGADOR se proteger.
 *
 * Sobre o 'unsafe-inline' na CSP: as páginas deste projeto têm <style> e
 * <script> escritos dentro do HTML e atributos como onclick="...". Sem
 * 'unsafe-inline' tudo isso pararia de funcionar. Mesmo assim a CSP ajuda:
 * bloqueia scripts vindos de QUALQUER outro domínio, plugins (<object>),
 * e impede que outro site coloque a pizzaria num <iframe>.
 * Próximo passo (melhoria futura): mover os scripts para arquivos .js e
 * tirar o 'unsafe-inline' de script-src.
 */
function enviar_headers_seguranca(): void
{
    if (headers_sent()) {
        return;
    }

    // Esconde a versão do PHP (atacante não descobre falhas conhecidas da versão)
    header_remove('X-Powered-By');

    $csp = implode('; ', [
        "default-src 'self'",
        // scripts: só do próprio site (+ inline, ver comentário acima)
        "script-src 'self' 'unsafe-inline'",
        // estilos: próprio site, inline, Google Fonts e Font Awesome (cdnjs)
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        // arquivos de fonte usados por esses CSS
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
        // imagens dos produtos vêm do Unsplash/placeholder (qualquer https)
        "img-src 'self' data: https:",
        // fetch(): o próprio site + busca de coordenadas no cadastro (OpenStreetMap)
        "connect-src 'self' https://nominatim.openstreetmap.org",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",   // ninguém pode nos colocar num iframe
    ]);

    header('Content-Security-Policy: ' . $csp);
    header('X-Frame-Options: DENY');                 // anti-clickjacking (navegadores antigos)
    header('X-Content-Type-Options: nosniff');       // não "adivinhar" tipo de arquivo
    header('Referrer-Policy: strict-origin-when-cross-origin'); // não vaza URL completa (com token) para outros sites
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
}

// ===========================================================================
// 2. SESSÃO SEGURA
// ===========================================================================

/** O site está sendo acessado por HTTPS? */
function requisicao_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Configura o cookie da sessão e inicia a sessão.
 * - HttpOnly: JavaScript não lê o cookie (um XSS não rouba a sessão)
 * - SameSite=Lax: o cookie não vai em POST vindo de outro site (ajuda contra CSRF)
 * - Secure: só trafega por HTTPS (ligado automaticamente quando há HTTPS)
 * - use_strict_mode: o PHP recusa IDs de sessão inventados (anti "session fixation")
 * Também derruba a sessão depois de SESSAO_TEMPO_INATIVIDADE sem uso.
 */
function iniciar_sessao_segura(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');   // ID da sessão nunca vai na URL

    session_set_cookie_params([
        'lifetime' => 0,                 // cookie some ao fechar o navegador
        'path'     => '/',
        'secure'   => requisicao_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Timeout por inatividade
    $agora = time();
    $ultimoAcesso = $_SESSION['ultimo_acesso'] ?? $agora;

    if (isset($_SESSION['usuario_id']) && ($agora - $ultimoAcesso) > SESSAO_TEMPO_INATIVIDADE) {
        $_SESSION = [];                  // apaga login, carrinho, token...
        session_regenerate_id(true);     // e troca o ID da sessão
        $_SESSION['aviso_sessao'] = 'Sua sessão expirou por inatividade. Faça login novamente.';
    }

    $_SESSION['ultimo_acesso'] = $agora;
}

/**
 * Chamar logo depois de um login com sucesso.
 * Gera um ID de sessão NOVO (o antigo, que alguém poderia conhecer, morre)
 * e um token CSRF novo.
 */
function renovar_sessao_apos_login(): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

/** Logout completo: limpa os dados, apaga o cookie e destrói a sessão. */
function encerrar_sessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        // Cookie com data no passado = navegador apaga
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $cookie['path'],
            'domain'   => $cookie['domain'],
            'secure'   => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
}

// ===========================================================================
// 3. XSS — ESCAPAR TUDO QUE VAI PARA O HTML
// ===========================================================================

/**
 * "Escape": transforma < > " ' & em texto inofensivo.
 * Ex.: e('<script>') vira '&lt;script&gt;' — aparece na tela, mas não executa.
 * Use em TODO dado que veio do usuário ou do banco: <?= e($produto['nome']) ?>
 */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** IP de quem está acessando (usado no rate limit e no log). */
function obter_ip_cliente(): string
{
    // Usamos só REMOTE_ADDR: cabeçalhos como X-Forwarded-For podem ser forjados.
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// ===========================================================================
// 4. CSRF — "o formulário veio mesmo do nosso site?"
//
// Cada sessão recebe um token aleatório. Todo formulário manda esse token
// escondido; um site malicioso não consegue lê-lo, então não consegue forjar
// um POST válido em nome do usuário.
// ===========================================================================

/** Devolve o token CSRF da sessão (cria na primeira vez). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 caracteres imprevisíveis
    }
    return $_SESSION['csrf_token'];
}

/**
 * HTML do campo escondido com o token. Use dentro de todo <form method="POST">:
 *     <?= campo_csrf() ?>
 */
function campo_csrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Compara o token recebido com o da sessão. */
function csrf_valido(?string $tokenRecebido): bool
{
    $tokenSessao = $_SESSION['csrf_token'] ?? '';
    // hash_equals compara em tempo constante (não dá pista pelo tempo de resposta)
    return $tokenSessao !== '' && is_string($tokenRecebido) && hash_equals($tokenSessao, $tokenRecebido);
}

/**
 * Barra a requisição (HTTP 403) se o token CSRF não bater.
 *
 * - Em POST: lê o campo "csrf_token" do formulário OU o header "X-CSRF-Token"
 *   (usado pelos fetch()/AJAX).
 * - Em links GET que alteram algo (ex.: remover item): passe o token da URL:
 *       verificar_csrf($_GET['token'] ?? '');
 * - Em GET comum, sem token informado, não faz nada.
 */
function verificar_csrf(?string $token = null): void
{
    if ($token === null) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    if (csrf_valido($token)) {
        return;
    }

    if (isset($GLOBALS['pdo'])) {
        registrar_evento_seguranca(
            $GLOBALS['pdo'],
            'csrf_invalido',
            $_SESSION['usuario_id'] ?? null,
            'Página: ' . ($_SERVER['SCRIPT_NAME'] ?? '?')
        );
    }

    http_response_code(403);
    exit('Requisição bloqueada (token CSRF inválido). Volte, recarregue a página e tente novamente.');
}

// ===========================================================================
// 5. SENHAS
// ===========================================================================

/**
 * Política de senha forte, conferida NO SERVIDOR (o JavaScript pode ser
 * desligado ou burlado; o servidor não).
 * Retorna a lista de problemas encontrados; lista vazia = senha aprovada.
 */
function validar_forca_senha(string $senha): array
{
    $problemas = [];

    if (mb_strlen($senha) < SENHA_TAMANHO_MINIMO) {
        $problemas[] = 'ter no mínimo ' . SENHA_TAMANHO_MINIMO . ' caracteres';
    }
    if (!preg_match('/[A-Z]/', $senha)) {
        $problemas[] = 'ter uma letra MAIÚSCULA';
    }
    if (!preg_match('/[a-z]/', $senha)) {
        $problemas[] = 'ter uma letra minúscula';
    }
    if (!preg_match('/[0-9]/', $senha)) {
        $problemas[] = 'ter um número';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
        $problemas[] = 'ter um caractere especial (ex.: @ # ! %)';
    }

    return $problemas;
}

/** Junta os problemas numa frase só, para mostrar na tela. */
function mensagem_senha_fraca(array $problemas): string
{
    return 'Senha fraca. A senha precisa ' . implode(', ', $problemas) . '.';
}

/** Gera o hash seguro da senha (bcrypt hoje; o PHP escolhe o melhor algoritmo). */
function gerar_hash_senha(string $senha): string
{
    return password_hash($senha, PASSWORD_DEFAULT);
}

/** O hash salvo é do formato antigo (MD5 = 32 caracteres hexadecimais)? */
function hash_eh_md5_legado(string $hash): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
}

/**
 * Confere a senha digitada e, se estiver certa, MODERNIZA o hash salvo.
 *
 * Migração transparente: usuários antigos têm a senha em MD5 (inseguro: dá
 * para quebrar por tabela pronta). Não sabemos a senha deles... até o
 * próximo login! Nesse momento o usuário digita a senha, conferimos com o
 * MD5 e, se bater, salvamos no lugar um hash bcrypt. O usuário nem percebe.
 *
 * @param PDO   $pdo     conexão com o banco
 * @param array $usuario linha da tabela usuarios (precisa de 'id' e 'senha')
 * @param string $senha  senha digitada
 * @return bool true se a senha está correta
 */
function verificar_senha_e_migrar(PDO $pdo, array $usuario, string $senha): bool
{
    $hashSalvo = (string) ($usuario['senha'] ?? '');

    // Caso 1: hash antigo em MD5
    if (hash_eh_md5_legado($hashSalvo)) {
        if (!hash_equals(strtolower($hashSalvo), md5($senha))) {
            return false;
        }
        atualizar_hash_senha($pdo, (int) $usuario['id'], $senha);
        registrar_evento_seguranca($pdo, 'senha_migrada', (int) $usuario['id'], 'MD5 -> bcrypt');
        return true;
    }

    // Caso 2: hash moderno (bcrypt): o password_verify faz a comparação certa
    if (!password_verify($senha, $hashSalvo)) {
        return false;
    }

    // Se o PHP passou a recomendar um algoritmo/custo melhor, atualiza também
    if (password_needs_rehash($hashSalvo, PASSWORD_DEFAULT)) {
        atualizar_hash_senha($pdo, (int) $usuario['id'], $senha);
    }
    return true;
}

/** Salva um novo hash para a senha do usuário. */
function atualizar_hash_senha(PDO $pdo, int $usuario_id, string $senha): void
{
    $stmt = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
    $stmt->execute([gerar_hash_senha($senha), $usuario_id]);
}

/**
 * Quando o e-mail NÃO existe, rodamos um password_verify "de mentira" mesmo
 * assim. Sem isso, a resposta para e-mail inexistente seria mais rápida e
 * um atacante descobriria quais e-mails têm conta só medindo o tempo.
 */
function simular_verificacao_senha(string $senha): void
{
    password_verify($senha, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
}

// ===========================================================================
// 6. FORÇA BRUTA — limite de tentativas (tabela tentativas_login)
//
// Cada falha vira uma linha (identificador + IP + horário). Se houver muitas
// linhas recentes, bloqueia. "identificador" é o e-mail no login; na
// recuperação de senha usamos 'recuperacao:<email>' para separar as contagens.
// ===========================================================================

/**
 * Está bloqueado? Retorna quantos SEGUNDOS faltam para liberar (0 = liberado).
 */
function verificar_bloqueio(
    PDO $pdo,
    string $identificador,
    string $ip,
    int $maxFalhas = LOGIN_MAX_FALHAS,
    int $janelaMinutos = LOGIN_JANELA_MINUTOS
): int {
    // As datas são calculadas pelo próprio MySQL (NOW()), evitando
    // diferença de fuso horário entre o PHP e o banco.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS falhas,
                TIMESTAMPDIFF(SECOND, NOW(), MIN(criado_em) + INTERVAL ? MINUTE) AS segundos_restantes
           FROM tentativas_login
          WHERE identificador = ? AND ip = ?
            AND criado_em > NOW() - INTERVAL ? MINUTE'
    );
    $stmt->execute([$janelaMinutos, $identificador, $ip, $janelaMinutos]);
    $linha = $stmt->fetch();

    if ((int) $linha['falhas'] < $maxFalhas) {
        return 0;
    }
    return max(1, (int) $linha['segundos_restantes']);
}

/** Anota uma tentativa que falhou. */
function registrar_tentativa_falha(PDO $pdo, string $identificador, string $ip): void
{
    $stmt = $pdo->prepare('INSERT INTO tentativas_login (identificador, ip) VALUES (?, ?)');
    $stmt->execute([$identificador, $ip]);
}

/** Zera as falhas (chamado depois de um login com sucesso). */
function limpar_tentativas(PDO $pdo, string $identificador, string $ip): void
{
    $stmt = $pdo->prepare('DELETE FROM tentativas_login WHERE identificador = ? AND ip = ?');
    $stmt->execute([$identificador, $ip]);
}

/** Converte segundos em texto amigável: 125 -> "3 minuto(s)". */
function formatar_tempo_bloqueio(int $segundos): string
{
    return (int) ceil($segundos / 60) . ' minuto(s)';
}

// ===========================================================================
// 7. LOG DE SEGURANÇA (tabela logs_seguranca)
//
// Eventos usados: login_ok, login_falha, bloqueio, csrf_invalido,
// senha_migrada, senha_redefinida, recuperacao_solicitada, logout.
// ===========================================================================

/**
 * Grava um evento de segurança. Se o banco falhar, NÃO derruba a página:
 * só escreve no error_log (o log nunca pode quebrar o login do cliente).
 */
function registrar_evento_seguranca(PDO $pdo, string $evento, ?int $usuario_id = null, string $detalhes = ''): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs_seguranca (evento, usuario_id, ip, detalhes) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$evento, $usuario_id, obter_ip_cliente(), $detalhes]);
    } catch (Throwable $erro) {
        error_log("Falha ao gravar log de segurança ($evento): " . $erro->getMessage());
    }
}

// ===========================================================================
// Executa ao carregar o arquivo: toda página já sai protegida.
// ===========================================================================
enviar_headers_seguranca();

// A API REST (api/index.php define MODO_API) é "stateless": quem prova a
// identidade é o token JWT de cada requisição, não um cookie de sessão.
if (!defined('MODO_API')) {
    iniciar_sessao_segura();
}
