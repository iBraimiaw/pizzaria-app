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
 *   7. "Não sou um robô"         -> exigir_recaptcha_configurado(), campo_recaptcha(), verificar_recaptcha()
 *   8. Log de auditoria          -> registrar_evento_seguranca()
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
 * bloqueia scripts vindos de outros domínios (a única exceção é o Google,
 * para o "Não sou um robô"), plugins (<object>), e impede que outro site
 * coloque a pizzaria num <iframe>.
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
        // e do Google, para o "Não sou um robô" (reCAPTCHA)
        "script-src 'self' 'unsafe-inline' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/",
        // estilos: próprio site, inline, Google Fonts e Font Awesome (cdnjs)
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        // arquivos de fonte usados por esses CSS
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
        // imagens dos produtos vêm do Unsplash/placeholder (qualquer https)
        "img-src 'self' data: https:",
        // fetch(): o próprio site, a busca de coordenadas no cadastro (OpenStreetMap)
        // e o Google, porque o script do "Não sou um robô" faz chamadas para /recaptcha/
        "connect-src 'self' https://nominatim.openstreetmap.org https://www.google.com/recaptcha/",
        // iframes: só a caixa do "Não sou um robô" (o reCAPTCHA roda num iframe do Google)
        "frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/",
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
// 7. NÃO SOU UM ROBÔ (Google reCAPTCHA v2)
//
// Caixa "Não sou um robô" no login, no cadastro e no "esqueci a senha".
// Atrapalha robôs que testam milhares de senhas ou criam contas em massa.
// A caixa no navegador sozinha não prova nada: dá para enviar o formulário
// sem ela (com curl, por exemplo). Quem decide é o SERVIDOR: ele manda a
// resposta da caixa para o Google e só continua se o Google aprovar.
// Chaves e liga/desliga ficam no includes/config.php (RECAPTCHA_*).
// A API (/api/v1) não usa esta seção.
// ===========================================================================

const RECAPTCHA_URL_VERIFICACAO = 'https://www.google.com/recaptcha/api/siteverify';
const RECAPTCHA_URL_SCRIPT      = 'https://www.google.com/recaptcha/api.js?hl=pt-BR'; // hl=pt-BR: caixa em português
const RECAPTCHA_CAMPO_RESPOSTA  = 'g-recaptcha-response'; // campo que o script do Google cria no <form>

const RECAPTCHA_TIMEOUT_CONEXAO_SEG = 3;    // até 3 s para conectar no Google...
const RECAPTCHA_TIMEOUT_SEG         = 5;    // ...e até 5 s no total (a página não fica "travada")

// Uma resposta de verdade cabe com folga neste limite. Texto maior é lixo ou
// ataque: recusamos sem nem chamar o Google.
const RECAPTCHA_RESPOSTA_TAMANHO_MAXIMO = 4000;

// No log, o e-mail digitado entra limpo e cortado neste tamanho (o mesmo de
// usuarios.email). Sem isso, um robô gravaria textos gigantes no log a cada
// envio, e um byte inválido faria o banco recusar a linha do log.
const RECAPTCHA_LOG_EMAIL_TAMANHO_MAXIMO = 150;

const MENSAGEM_RECAPTCHA_FALTOU      = 'Confirme que você não é um robô.';
const MENSAGEM_RECAPTCHA_INDISPONIVEL = "Não foi possível confirmar o 'Não sou um robô'. Tente de novo.";

/** O "Não sou um robô" está ligado? (RECAPTCHA_ATIVO=0 no .env desliga) */
function recaptcha_ativo(): bool
{
    return RECAPTCHA_ATIVO;
}

/** Alguma das duas chaves ainda é a chave de TESTE do Google? */
function recaptcha_usa_chave_de_teste(): bool
{
    return RECAPTCHA_SITE_KEY === RECAPTCHA_CHAVE_SITE_TESTE
        || RECAPTCHA_SECRET_KEY === RECAPTCHA_CHAVE_SECRETA_TESTE;
}

/**
 * Falha dura: fora de dev, com o "Não sou um robô" ligado e a chave de TESTE,
 * a página não abre (HTTP 500).
 *
 * A chave de teste aprova qualquer resposta. Em produção a caixa viraria
 * enfeite e nenhum robô seria barrado. A API e o microserviço fazem o mesmo
 * com os segredos de dev.
 * Chamar no topo da página, antes de qualquer saída (HTML).
 */
function exigir_recaptcha_configurado(): void
{
    if (recaptcha_ativo() && APP_ENV !== 'dev' && recaptcha_usa_chave_de_teste()) {
        error_log('[recaptcha] BLOQUEADO: chave de teste do Google fora de dev (aprova qualquer resposta). Defina RECAPTCHA_SITE_KEY e RECAPTCHA_SECRET_KEY no .env');
        http_response_code(500);
        exit('Serviço indisponível: configuração de segurança inválida.');
    }
}

/**
 * HTML da caixa "Não sou um robô". Use dentro do <form>, antes do botão:
 *     <?= campo_recaptcha() ?>
 * O script do Google desenha a caixa dentro da <div class="g-recaptcha">.
 * Quando a pessoa marca, ele põe a resposta num campo escondido do formulário
 * (g-recaptcha-response), que o servidor confere em verificar_recaptcha().
 * Desligado (RECAPTCHA_ATIVO=0): devolve '' e a página fica sem a caixa.
 */
function campo_recaptcha(): string
{
    if (!recaptcha_ativo()) {
        return '';
    }

    // Estilo inline (a CSP permite): espaço em cima e embaixo, caixa centralizada
    return '<div style="display: flex; justify-content: center; margin: 20px 0;">'
         . '<div class="g-recaptcha" data-sitekey="' . e(RECAPTCHA_SITE_KEY) . '"></div>'
         . '</div>'
         . '<script src="' . e(RECAPTCHA_URL_SCRIPT) . '" async defer></script>';
}

/**
 * Confere, no servidor, o "Não sou um robô" do formulário enviado (POST).
 *
 * Retorna null se passou, ou a mensagem para mostrar na tela.
 * Cada recusa vira um evento no log de segurança:
 *   captcha_invalido     -> não marcou a caixa, ou o Google recusou a resposta
 *   captcha_indisponivel -> o Google não respondeu (sem internet, lento, fora do ar)
 * Falhar aqui NÃO conta como tentativa de senha (não grava em tentativas_login):
 * a senha nem chegou a ser testada.
 *
 * @param PDO    $pdo   conexão com o banco (para o log de segurança)
 * @param string $email e-mail digitado no formulário, só para o log (pode ser vazio)
 */
function verificar_recaptcha(PDO $pdo, string $email = ''): ?string
{
    if (!recaptcha_ativo()) {
        return null;
    }

    // Detalhes do log: a página e, quando houver, o e-mail
    $detalhes = 'Página: ' . ($_SERVER['SCRIPT_NAME'] ?? '?');
    if ($email !== '') {
        // mb_scrub troca bytes inválidos por "?"; mb_substr corta no tamanho máximo
        $emailLog = mb_substr(mb_scrub($email, 'UTF-8'), 0, RECAPTCHA_LOG_EMAIL_TAMANHO_MAXIMO, 'UTF-8');
        $detalhes .= ' · e-mail: ' . $emailLog;
    }

    // 1) Sem resposta: não marcou a caixa, ou o POST não veio do nosso formulário.
    //    Resposta que não é texto ou que é grande demais também para aqui.
    //    Nesses casos nem perguntamos ao Google.
    $resposta = $_POST[RECAPTCHA_CAMPO_RESPOSTA] ?? null;
    if (!is_string($resposta) || $resposta === '' || strlen($resposta) > RECAPTCHA_RESPOSTA_TAMANHO_MAXIMO) {
        registrar_evento_seguranca($pdo, 'captcha_invalido', null, $detalhes);
        return MENSAGEM_RECAPTCHA_FALTOU;
    }

    // 2) Pergunta ao Google se a resposta vale
    $resultado = consultar_google_recaptcha($resposta);

    if ($resultado === null) {
        // O Google não respondeu direito (o motivo já foi para o error_log)
        registrar_evento_seguranca($pdo, 'captcha_indisponivel', null, $detalhes);
        return MENSAGEM_RECAPTCHA_INDISPONIVEL;
    }

    if (($resultado['success'] ?? false) !== true) {
        // error-codes: motivos do Google (ex.: timeout-or-duplicate = resposta vencida ou já usada)
        $codigos = array_filter((array) ($resultado['error-codes'] ?? []), 'is_string');
        $detalhes .= ' · erros do Google: ' . ($codigos ? implode(', ', $codigos) : 'nenhum');
        registrar_evento_seguranca($pdo, 'captcha_invalido', null, $detalhes);
        return MENSAGEM_RECAPTCHA_FALTOU;
    }

    return null;
}

/**
 * Pergunta ao Google se a resposta da caixa é válida: POST na siteverify com a
 * chave secreta e a resposta. O IP do visitante (remoteip) é opcional e não vai.
 *
 * Devolve o JSON do Google como array ('success' e, na recusa, 'error-codes')
 * ou null quando não deu para saber: falha de rede, demora, HTTP diferente de
 * 200 ou resposta que não é JSON. O motivo vai para o error_log
 * ("docker compose logs web").
 */
function consultar_google_recaptcha(string $resposta): ?array
{
    $curl = curl_init(RECAPTCHA_URL_VERIFICACAO);
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        // formato de formulário (secret=...&response=...), como o Google pede
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $resposta,
        ]),
        CURLOPT_RETURNTRANSFER => true,               // devolve a resposta em vez de imprimir
        CURLOPT_CONNECTTIMEOUT => RECAPTCHA_TIMEOUT_CONEXAO_SEG,
        CURLOPT_TIMEOUT        => RECAPTCHA_TIMEOUT_SEG,
        // HTTPS conferido de verdade. Sem isso, alguém no meio da rede poderia
        // se passar pelo Google e aprovar qualquer robô.
        CURLOPT_SSL_VERIFYPEER => true,               // certificado assinado por autoridade confiável
        CURLOPT_SSL_VERIFYHOST => 2,                  // 2 = o certificado tem que ser do próprio www.google.com
    ]);

    $corpo = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($curl);
    curl_close($curl);

    if ($corpo === false) {
        error_log("[recaptcha] Google nao respondeu ($erroCurl)");
        return null;
    }
    if ($status !== 200) {
        error_log("[recaptcha] resposta inesperada do Google: HTTP $status");
        return null;
    }

    $dados = json_decode($corpo, true);
    if (!is_array($dados)) {
        error_log('[recaptcha] resposta do Google nao e JSON valido');
        return null;
    }
    return $dados;
}

// ===========================================================================
// 8. LOG DE SEGURANÇA (tabela logs_seguranca)
//
// Eventos usados: login_ok, login_falha, bloqueio, csrf_invalido,
// captcha_invalido, captcha_indisponivel, senha_migrada, senha_redefinida,
// recuperacao_solicitada, logout.
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
