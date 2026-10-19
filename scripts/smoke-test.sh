#!/usr/bin/env bash
# =====================================================================
# smoke-test.sh — testes rápidos de SEGURANÇA, API e MICROSERVIÇO
#
# O que é um "smoke test": uma bateria curta que liga o sistema e confere
# se o básico funciona (se "sai fumaça", algo quebrou). Cada linha mostra
# OK ou FALHOU, então dá para rodar AO VIVO na apresentação.
#
# Como rodar (Git Bash, Linux ou Mac), com o docker compose já no ar:
#   bash scripts/smoke-test.sh              -> testes principais (~10 s)
#   bash scripts/smoke-test.sh --fallback   -> também derruba o microserviço
#                                              para mostrar o plano B (~30 s)
#   bash scripts/smoke-test.sh --reset-demo -> só volta a senha do demo para MD5
#                                              (para repetir a demo da migração)
#
# Só usa curl + docker compose. Cria dois usuários de teste (@smoke.test)
# e APAGA tudo o que criou no final: o usuário demo não é tocado
# (a senha dele continua em MD5 para a demonstração da migração).
# =====================================================================

# Git Bash no Windows "traduz" caminhos (ex.: /var/www vira C:/Program Files/Git/var/www)
# ao chamar o docker. Por isso os comandos "docker compose exec" abaixo usam
# MSYS_NO_PATHCONV=1, que desliga essa tradução só para eles (no curl ela é necessária).

cd "$(dirname "$0")/.." || exit 1   # roda a partir da raiz do projeto

SITE=http://localhost:8080
API=$SITE/api/v1
ENTREGA=http://localhost:8081
CHAVE=${ENTREGA_SERVICE_KEY:-chave-dev-entrega}
SENHA_TESTE='Smoke@123'
EMAIL1=cliente1@smoke.test
EMAIL2=cliente2@smoke.test

TMP=$(mktemp -d)
CORPO=$TMP/corpo     # corpo da última resposta
CABECALHOS=$TMP/cab  # headers da última resposta
TOTAL_OK=0
TOTAL_FALHAS=0

# ---------------------------------------------------------------------
# Funções de apoio
# ---------------------------------------------------------------------
verde()    { printf '\033[32m%s\033[0m' "$1"; }
vermelho() { printf '\033[31m%s\033[0m' "$1"; }

# confere "descrição" "condição em bash"
confere() {
    if eval "$2"; then
        echo "$(verde 'OK     ') $1"; TOTAL_OK=$((TOTAL_OK + 1))
    else
        echo "$(vermelho 'FALHOU ') $1"; TOTAL_FALHAS=$((TOTAL_FALHAS + 1))
    fi
}

secao() { echo; echo "== $1 =="; }

# requisicao <curl args...>  -> imprime o status HTTP; corpo/headers ficam salvos
requisicao() { curl -s -o "$CORPO" -D "$CABECALHOS" -w '%{http_code}' "$@"; }

# Lê um campo simples do JSON da última resposta (sem precisar de jq)
# (aceita JSON compacto {"a":1} e formatado { "a": 1 })
campo_json() { grep -oE "\"$1\": *\"?[^,\"}]*" "$CORPO" | head -1 | sed -E "s/\"$1\": *\"?//; s/[[:space:]]+$//"; }

# compara números sem ligar para o formato (5 = 5.0 = 5.00)
mesmo_numero() { awk -v a="$1" -v b="$2" 'BEGIN { exit !(a + 0 == b + 0) }'; }

tem_header() { grep -qi "^$1" "$CABECALHOS"; }
corpo_tem()  { grep -q -- "$1" "$CORPO"; }

# Roda um SQL no container do MySQL. Usuário, senha e banco vêm das variáveis
# do PRÓPRIO container (MYSQL_USER etc., que o docker-compose preenche a partir
# do .env), então o script segue o .env sem senha escrita aqui. A senha vai por
# MYSQL_PWD (e não por -p) só para o mysql não imprimir aviso; erros reais aparecem.
sql() {
    MSYS_NO_PATHCONV=1 docker compose exec -T db sh -c \
        'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" -N -e "$0"' "$1"
}

# base64url = base64 com - e _ no lugar de + e /, e sem "=" (formato do JWT)
base64url() { printf '%s' "$1" | base64 | tr -d '\n=' | tr '+/' '-_'; }

login_api() { # login_api email senha -> imprime o token
    curl -s -X POST "$API/auth/login" -H 'Content-Type: application/json' \
         -d "{\"email\":\"$1\",\"senha\":\"$2\"}" | sed -nE 's/.*"token":"([^"]+)".*/\1/p'
}

limpar_dados_de_teste() {
    sql "DELETE p FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE '%@smoke.test';
         DELETE l FROM logs_seguranca l JOIN usuarios u ON u.id = l.usuario_id WHERE u.email LIKE '%@smoke.test';
         DELETE FROM usuarios WHERE email LIKE '%@smoke.test';
         DELETE FROM tentativas_login WHERE identificador LIKE '%smoke.test';
         DELETE FROM logs_seguranca WHERE detalhes LIKE '%smoke.test%';"
}

# ---------------------------------------------------------------------
# --reset-demo: o primeiro login do demo troca o MD5 por bcrypt (de propósito).
# Depois de um ensaio, isto devolve o MD5 para mostrar a migração de novo.
# ---------------------------------------------------------------------
if [ "$1" = "--reset-demo" ]; then
    sql "UPDATE usuarios SET senha = MD5('Demo@123') WHERE email = 'demo@pizzaria.com';
         DELETE FROM tentativas_login WHERE identificador LIKE '%demo@pizzaria.com%';" || exit 1
    echo "Senha do demo@pizzaria.com voltou para MD5: $(sql "SELECT senha FROM usuarios WHERE email='demo@pizzaria.com'")"
    exit 0
fi

# ---------------------------------------------------------------------
# Preparação: dois clientes de teste com senha em MD5 (como os antigos)
# ---------------------------------------------------------------------
if ! curl -s -o /dev/null "$SITE/index.php"; then
    echo "O site não respondeu em $SITE. Rode antes: docker compose up -d"; exit 1
fi
limpar_dados_de_teste
sql "INSERT INTO usuarios (nome, email, senha, endereco, latitude, longitude) VALUES
     ('Cliente Smoke 1', '$EMAIL1', MD5('$SENHA_TESTE'), 'Rua Teste, 1', -20.4590, -54.5960),
     ('Cliente Smoke 2', '$EMAIL2', MD5('$SENHA_TESTE'), 'Rua Teste, 2', -20.4590, -54.5960);"

# =====================================================================
secao "MICROSERVIÇO DE ENTREGA (porta 8081)"
# =====================================================================
DESTINO='{"latitude":-20.4590,"longitude":-54.5960}'

st=$(requisicao "$ENTREGA/health")
confere "GET /health é público -> 200 ($st)" "[ $st = 200 ] && corpo_tem '\"status\": *\"ok\"'"

st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -d "$DESTINO")
confere "calcular SEM X-Service-Key -> 401 ($st)" "[ $st = 401 ]"

st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H 'X-Service-Key: errada' -d "$DESTINO")
confere "calcular com chave ERRADA -> 401 ($st)" "[ $st = 401 ]"

st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H "X-Service-Key: $CHAVE" -d "$DESTINO")
confere "calcular com chave certa -> 200, 2.04 km, R\$ 5, 27 min ($st)" \
    "[ $st = 200 ] && mesmo_numero \"\$(campo_json distancia_km)\" 2.04 && mesmo_numero \"\$(campo_json taxa_entrega)\" 5 && mesmo_numero \"\$(campo_json tempo_estimado_min)\" 27"

st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H "X-Service-Key: $CHAVE" -d '{"latitude":-23.5505,"longitude":-46.6333}')
confere "endereço em São Paulo -> 422 fora da área ($st)" "[ $st = 422 ]"

st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H "X-Service-Key: $CHAVE" -d '{"latitude":"abc"}')
confere "latitude que não é número -> 400 ($st)" "[ $st = 400 ]"
st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H "X-Service-Key: $CHAVE" -d 'nao e json')
confere "corpo que não é JSON -> 400 ($st)" "[ $st = 400 ]"
st=$(requisicao -X POST "$ENTREGA/v1/entrega/calcular" -H 'Content-Type: application/json' -H "X-Service-Key: $CHAVE" -d '{"latitude":-200,"longitude":0}')
confere "latitude -200 (fora do intervalo) -> 400 ($st)" "[ $st = 400 ]"

st=$(requisicao "$ENTREGA/v1/entrega/regras" -H "X-Service-Key: $CHAVE")
confere "GET /v1/entrega/regras -> 200 ($st)" "[ $st = 200 ]"

st=$(requisicao "$ENTREGA/v1/entrega/status/42?criado_em=2026-10-18%2019:30:00" -H "X-Service-Key: $CHAVE")
confere "GET /v1/entrega/status/42 -> 200 ($st)" "[ $st = 200 ] && corpo_tem 'status'"

st=$(requisicao "$ENTREGA/v1/nao-existe" -H "X-Service-Key: $CHAVE")
confere "rota inexistente -> 404 ($st)" "[ $st = 404 ]"
st=$(requisicao "$ENTREGA/v1/entrega/calcular" -H "X-Service-Key: $CHAVE")
confere "GET em rota que só aceita POST -> 405 ($st)" "[ $st = 405 ]"

st=$(requisicao "$SITE/services/entrega/public/index.php")
confere "site (8080) NÃO expõe o código do microserviço -> 403 ($st)" "[ $st = 403 ]"

# =====================================================================
secao "SEGURANÇA DO SITE (porta 8080)"
# =====================================================================
st=$(requisicao -I "$SITE/index.php")
confere "header Content-Security-Policy"                  "tem_header 'Content-Security-Policy:'"
confere "header X-Frame-Options: DENY"                    "grep -qi '^X-Frame-Options: DENY' $CABECALHOS"
confere "header X-Content-Type-Options: nosniff"          "grep -qi '^X-Content-Type-Options: nosniff' $CABECALHOS"
confere "header Referrer-Policy e Permissions-Policy"     "tem_header 'Referrer-Policy:' && tem_header 'Permissions-Policy:'"
confere "sem X-Powered-By (não revela a versão do PHP)"   "! tem_header 'X-Powered-By'"
confere "Server: Apache sem versão"                       "grep -qiE '^Server: Apache.?$' $CABECALHOS"
confere "cookie de sessão HttpOnly + SameSite=Lax"        "grep -i '^Set-Cookie: PHPSESSID' $CABECALHOS | grep -qi 'HttpOnly' && grep -i '^Set-Cookie: PHPSESSID' $CABECALHOS | grep -qi 'SameSite=Lax'"

for arquivo in .env.example .git/config database/schema.sql includes/config.php docker-compose.yml; do
    st=$(requisicao "$SITE/$arquivo")
    confere "arquivo interno /$arquivo bloqueado -> 403 ($st)" "[ $st = 403 ]"
done

st=$(requisicao -X POST -d "email=$EMAIL1&senha=$SENHA_TESTE&login=1" "$SITE/login.php")
confere "login.php sem token CSRF -> 403 ($st)" "[ $st = 403 ]"

# Força bruta pelo formulário do site: 5 erros no mesmo e-mail bloqueiam
COOKIES=$TMP/cookies
ultima_msg=''
for tentativa in 1 2 3 4 5 6; do
    token=$(curl -s -b "$COOKIES" -c "$COOKIES" "$SITE/login.php" | sed -nE 's/.*name="csrf_token" value="([^"]+)".*/\1/p' | head -1)
    curl -s -b "$COOKIES" -c "$COOKIES" -o "$CORPO" -d "email=forca-bruta@smoke.test&senha=errada$tentativa&login=1&csrf_token=$token" "$SITE/login.php"
    corpo_tem 'Muitas tentativas' && ultima_msg="bloqueado na tentativa $tentativa" && break
done
confere "força bruta no site: bloqueia na 5ª tentativa ($ultima_msg)" "[ \"$ultima_msg\" = 'bloqueado na tentativa 5' ]"

# =====================================================================
secao "API REST (/api/v1)"
# =====================================================================
st=$(requisicao "$API/health")
confere "GET /health -> 200, sem cookie (API stateless) ($st)" "[ $st = 200 ] && ! tem_header 'Set-Cookie'"
confere "API manda CSP default-src 'none' e Cache-Control no-store" "grep -qi \"default-src 'none'\" $CABECALHOS && grep -qi '^Cache-Control: no-store' $CABECALHOS"

TOKEN=$(login_api "$EMAIL1" "$SENHA_TESTE")
confere "login -> devolve um JWT (3 partes separadas por ponto)" "[[ '$TOKEN' =~ ^[^.]+\.[^.]+\.[^.]+$ ]]"
confere "senha MD5 antiga migrou para bcrypt no login" "[[ \"\$(sql \"SELECT LEFT(senha,4) FROM usuarios WHERE email='$EMAIL1'\")\" == '\$2y\$' ]]"

st=$(requisicao "$API/auth/me" -H "Authorization: Bearer $TOKEN")
confere "GET /auth/me COM token -> 200 ($st)" "[ $st = 200 ] && corpo_tem '$EMAIL1'"
st=$(requisicao "$API/auth/me")
confere "GET /auth/me SEM token -> 401 + WWW-Authenticate ($st)" "[ $st = 401 ] && tem_header 'WWW-Authenticate: Bearer'"

# Token adulterado: troco o "sub" (id do usuário) e mantenho a assinatura antiga
CABECALHO_JWT=${TOKEN%%.*}
ASSINATURA_JWT=${TOKEN##*.}
CONTEUDO_FALSO=$(base64url '{"sub":"1","iss":"pizzaria-api","iat":1760000000,"exp":1999999999}')
st=$(requisicao "$API/auth/me" -H "Authorization: Bearer $CABECALHO_JWT.$CONTEUDO_FALSO.$ASSINATURA_JWT")
confere "token ADULTERADO (sub trocado) -> 401 ($st)" "[ $st = 401 ] && corpo_tem 'Assinatura'"

SEM_ALG=$(base64url '{"alg":"none","typ":"JWT"}')
st=$(requisicao "$API/auth/me" -H "Authorization: Bearer $SEM_ALG.$CONTEUDO_FALSO.")
confere "token com alg 'none' (sem assinatura) -> 401 ($st)" "[ $st = 401 ]"

EXPIRADO=$(MSYS_NO_PATHCONV=1 docker compose exec -T web php api/bin/gerar-token.php 1 -60 | tr -d '\r\n')
st=$(requisicao "$API/auth/me" -H "Authorization: Bearer $EXPIRADO")
confere "token EXPIRADO -> 401 token_expirado ($st)" "[ $st = 401 ] && corpo_tem 'token_expirado'"

st=$(requisicao "$API/auth/me" -H "Authorization: Bearer qualquer-coisa")
confere "texto qualquer no lugar do token -> 401 ($st)" "[ $st = 401 ]"

st=$(requisicao -X POST "$API/auth/login" -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL2\",\"senha\":\"errada\"}")
confere "login com senha errada -> 401 ($st)" "[ $st = 401 ]"
st=$(requisicao -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{email:')
confere "login com JSON inválido -> 400 ($st)" "[ $st = 400 ]"
st=$(requisicao -X POST "$API/auth/login" -d 'email=a')
confere "login sem Content-Type JSON -> 415 ($st)" "[ $st = 415 ]"
st=$(requisicao -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{"email":"a@b.com"}')
confere "login sem o campo senha -> 422 ($st)" "[ $st = 422 ]"
printf '{"email":"%s"}' "$(head -c 20000 /dev/zero | tr '\0' 'a')" > "$TMP/grande.json"
st=$(requisicao -X POST "$API/auth/login" -H 'Content-Type: application/json' --data-binary "@$TMP/grande.json")
confere "corpo de 20 KB -> 413 ($st)" "[ $st = 413 ]"

st=$(requisicao -X DELETE "$API/produtos")
confere "DELETE /produtos -> 405 + Allow: GET ($st)" "[ $st = 405 ] && grep -qi '^Allow: GET' $CABECALHOS"
st=$(requisicao "$API/rota-que-nao-existe")
confere "rota inexistente -> 404 ($st)" "[ $st = 404 ]"

st=$(requisicao "$API/categorias")
confere "GET /categorias -> 200 ($st)" "[ $st = 200 ] && corpo_tem 'Bebidas'"
st=$(requisicao "$API/produtos?categoria=3")
confere "GET /produtos?categoria=3 só bebidas ($st)" "[ $st = 200 ] && corpo_tem 'Coca-Cola' && ! corpo_tem 'Calabresa'"
st=$(requisicao "$API/produtos?limite=9999")
confere "limite=9999 é reduzido para 50 ($st)" "[ $st = 200 ] && corpo_tem '\"limite\":50'"
st=$(requisicao "$API/produtos?pagina=0")
confere "pagina=0 -> 400 ($st)" "[ $st = 400 ]"
st=$(requisicao "$API/produtos?categoria=abc")
confere "categoria=abc -> 400 ($st)" "[ $st = 400 ]"
st=$(requisicao "$API/produtos?busca=%25")
confere "busca por '%' não vira curinga do LIKE (0 resultados) ($st)" "[ $st = 200 ] && corpo_tem '\"dados\":\[\]'"
st=$(requisicao "$API/produtos/999")
confere "GET /produtos/999 -> 404 ($st)" "[ $st = 404 ]"

# Pedido tentando fraudar o preço: 2x Calabresa (44,90) "por 0,01" + 1 Coca (14,00)
# Total certo = 2 x 44,90 + 14,00 + frete 5,00 = 108,80
PEDIDO='{"itens":[{"produto_id":2,"quantidade":2,"preco":0.01},{"produto_id":11,"quantidade":1}],"forma_pagamento":"Dinheiro","troco_para":150}'
st=$(requisicao -X POST "$API/pedidos" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d "$PEDIDO")
PEDIDO_ID=$(campo_json id)
confere "POST /pedidos -> 201 + header Location ($st)" "[ $st = 201 ] && tem_header 'Location: /api/v1/pedidos/'"
confere "preço vem do BANCO: total 108.80, não 0.01" "corpo_tem '\"total\":108.8'"
confere "frete calculado pelo microserviço (origem microservico)" "corpo_tem '\"origem\":\"microservico\"'"

st=$(requisicao -X POST "$API/pedidos" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"itens":[{"produto_id":1,"quantidade":0}],"forma_pagamento":"Bitcoin"}')
confere "pedido inválido (quantidade 0, Bitcoin) -> 422 ($st)" "[ $st = 422 ]"
st=$(requisicao -X POST "$API/pedidos" -H 'Content-Type: application/json' -d "$PEDIDO")
confere "POST /pedidos sem token -> 401 ($st)" "[ $st = 401 ]"

st=$(requisicao "$API/pedidos" -H "Authorization: Bearer $TOKEN")
confere "GET /pedidos lista o pedido criado ($st)" "[ $st = 200 ] && corpo_tem '\"id\":$PEDIDO_ID'"

TOKEN2=$(login_api "$EMAIL2" "$SENHA_TESTE")
st=$(requisicao "$API/pedidos/$PEDIDO_ID" -H "Authorization: Bearer $TOKEN2")
confere "IDOR: outro cliente abrindo o pedido $PEDIDO_ID -> 404 ($st)" "[ $st = 404 ]"
st=$(requisicao "$API/pedidos" -H "Authorization: Bearer $TOKEN2")
confere "IDOR: lista do outro cliente vem vazia ($st)" "[ $st = 200 ] && corpo_tem '\"dados\":\[\]'"

codigos=''
for tentativa in 1 2 3 4 5 6; do
    st=$(requisicao -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{"email":"ataque@smoke.test","senha":"x"}')
    codigos="$codigos$st "
done
confere "rate limit: 401 x4 e depois 429 + Retry-After ($codigos)" "[ '$codigos' = '401 401 401 401 429 429 ' ] && tem_header 'Retry-After'"

st=$(requisicao -X OPTIONS "$API/pedidos" -H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: POST')
confere "CORS: origem permitida recebe Access-Control-Allow-Origin ($st)" "[ $st = 204 ] && tem_header 'Access-Control-Allow-Origin: http://localhost:3000'"
st=$(requisicao -X OPTIONS "$API/pedidos" -H 'Origin: http://malicioso.com' -H 'Access-Control-Request-Method: POST')
confere "CORS: origem desconhecida NÃO recebe header CORS ($st)" "! tem_header 'Access-Control-Allow-Origin'"

# =====================================================================
if [ "$1" = "--fallback" ]; then
    secao "PLANO B: microserviço fora do ar"
    docker compose stop entrega >/dev/null 2>&1
    st=$(requisicao -X POST "$API/pedidos" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d "$PEDIDO")
    confere "com o microserviço parado o pedido sai mesmo assim (origem local) ($st)" "[ $st = 201 ] && corpo_tem '\"origem\":\"local\"'"
    docker compose start entrega >/dev/null 2>&1
    # O disjuntor do site fica 10 s sem tentar o serviço depois de uma falha.
    # Esperamos esse tempo para a próxima execução já encontrar tudo normal.
    echo "        (microserviço religado; aguardando 11 s do disjuntor...)"
    sleep 11
fi

# ---------------------------------------------------------------------
limpar_dados_de_teste
rm -rf "$TMP"
echo
echo "Resultado: $TOTAL_OK OK, $TOTAL_FALHAS FALHOU"
[ $TOTAL_FALHAS -eq 0 ]
