<?php
/**
 * config.php — Configurações da aplicação.
 *
 * POR QUE variáveis de ambiente?
 * Senhas e chaves NÃO devem ficar escritas no código (iriam parar no Git).
 * O docker-compose injeta os valores reais; aqui só existem defaults de DEV
 * para o projeto rodar "de primeira" na máquina do desenvolvedor.
 */

/**
 * Lê uma variável de ambiente; se não existir, usa o valor padrão.
 */
function env(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    return ($valor === false || $valor === '') ? $padrao : $valor;
}

// --- Ambiente: 'dev' (desenvolvimento) ou 'prod' (produção) ---
define('APP_ENV', env('APP_ENV', 'dev'));

// --- Banco de dados (MySQL) ---
define('DB_HOST', env('DB_HOST', 'db'));          // 'db' = nome do serviço no docker-compose
define('DB_NAME', env('DB_NAME', 'pizzaria'));
define('DB_USER', env('DB_USER', 'pizzaria'));
define('DB_PASS', env('DB_PASS', 'pizzaria123'));

// --- Microserviço de entrega (calcula frete/distância) ---
define('ENTREGA_SERVICE_URL', env('ENTREGA_SERVICE_URL', 'http://entrega/'));
define('ENTREGA_SERVICE_KEY', env('ENTREGA_SERVICE_KEY', 'chave-dev-entrega'));

// --- Segredo usado para assinar tokens JWT da API ---
define('JWT_SECRET', env('JWT_SECRET', 'segredo-dev-troque-em-producao'));

// --- Dados fixos da pizzaria (Campo Grande - MS) ---
define('PIZZARIA_NOME', 'Pizzaria do Bairro');
define('PIZZARIA_ENDERECO', 'Rua 14 de Julho, 1500 - Centro, Campo Grande - MS');
define('PIZZARIA_LAT', -20.4628);   // latitude da pizzaria
define('PIZZARIA_LNG', -54.6152);   // longitude da pizzaria

// --- Regras do frete (usadas pelo cálculo local) ---
define('TAXA_ENTREGA_BASE', 5.00);     // valor mínimo cobrado
define('TAXA_ENTREGA_KM_GRATIS', 3.0); // até 3 km cobra só a base
define('TAXA_ENTREGA_POR_KM', 1.50);   // cada km acima disso

// --- Endereço público do site (usado no link de "esqueci a senha") ---
// Fixo na configuração de propósito: montar o link com o header Host da
// requisição permitiria a um atacante gerar links apontando para o site dele.
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:8080'), '/'));

// --- Erros na tela: só em DEV ---
// Em produção (APP_ENV=production ou qualquer valor diferente de 'dev'),
// mostrar o erro ao usuário vaza detalhes internos (caminhos, SQL).
// O erro continua sendo registrado no log (error_log -> log do Apache/Docker).
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (APP_ENV === 'dev') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}
