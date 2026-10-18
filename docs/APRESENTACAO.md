# Roteiro da apresentação (21/10, cerca de 10 minutos)

**Tema:** Demonstração da implementação dos módulos de segurança, API e microserviço.

Em cada passo há:

- **Faça:** o que clicar ou rodar. Os comandos são para o **Git Bash**.
- **Fale:** a frase curta para dizer.
- **Mostre:** o resultado que deve aparecer.

| Bloco | Tempo |
|---|---|
| 0. Preparação (antes de começar) | fora do tempo |
| 1. Arquitetura | 1 min |
| 2. Segurança | 3 min 30 s |
| 3. API | 2 min 30 s |
| 4. Microserviço | 2 min |
| 5. Smoke test | 1 min |

---

## 0. Preparação (10 minutos antes, fora do tempo)

1. Abrir o **Docker Desktop** e esperar ficar verde.
2. No Git Bash:

   ```bash
   cd /c/Users/Leeonam/pizzaria-app
   docker compose up -d --build
   docker compose ps                       # db e entrega precisam estar (healthy)
   bash scripts/smoke-test.sh --reset-demo # senha do demo volta para MD5
   docker compose exec db mysql -upizzaria -ppizzaria123 pizzaria -e "DELETE FROM tentativas_login; DELETE FROM logs_seguranca"
   ```

   Alternativa para zerar tudo do zero: `docker compose down -v && docker compose up -d --build`. Leva cerca de 1 minuto.

3. Deixar abertos:
   - **Aba 1:** http://localhost:8080/login.php (o site, **sem estar logado**).
   - **Aba 2:** http://localhost:8082 (phpMyAdmin, servidor `db`, usuário `pizzaria`, senha `pizzaria123`), já na tabela `usuarios` do banco `pizzaria`.
   - **Uma janela anônima** (Ctrl+Shift+N), na tela de login, para o teste de força bruta.
   - **Git Bash** com fonte grande, já na pasta do projeto.
   - **VS Code** com o `README.md` aberto, para mostrar o diagrama.
4. Teste rápido: `curl -s http://localhost:8080/api/v1/health` deve responder `"status":"ok"`.

> **Cuidado:** não faça login com o demo antes da apresentação. O primeiro login troca o MD5 por bcrypt. Se isso acontecer, rode de novo `bash scripts/smoke-test.sh --reset-demo`.

---

## 1. Arquitetura (1 min)

**Faça:** mostrar o diagrama do `README.md` (seção "Arquitetura") e rodar:

```bash
docker compose ps
```

**Fale:**
> "São 4 containers. O **web** é o site em PHP puro, e a API REST fica nele também, em /api/v1. O **db** é o MySQL. O **entrega** é o microserviço, com código e container próprios e sem acesso ao banco. O **phpMyAdmin** é só para enxergar o banco. O site conversa com o microserviço por HTTP, usando uma chave."

**Mostre:** os 4 serviços `Up`, com db e entrega `(healthy)`.

---

## 2. Segurança (3 min 30 s)

### 2.1 Migração de senha MD5 → bcrypt, ao vivo (1 min)

**Faça:**
1. **Aba 2 (phpMyAdmin)**: tabela `usuarios`, mostrar a coluna `senha` do demo: `e14c05f0dc27e6be...`
2. **Aba 1 (site)**: entrar com `demo@pizzaria.com` / `Demo@123`.
3. Voltar ao phpMyAdmin e recarregar (F5).

**Fale:**
> "Antes, as senhas eram guardadas em MD5. O MD5 é rápido demais e já existem tabelas prontas que desfazem esse hash. Mas não dá para converter sem saber a senha. Então, no próximo login, o sistema confere a senha com o MD5 e, se ela estiver certa, já salva no lugar um hash **bcrypt**. O usuário nem percebe."

**Mostre:** a senha virou `$2y$10$...`. O código está em `includes/security.php`, função `verificar_senha_e_migrar()`.

### 2.2 CSRF bloqueado com 403 (40 s)

**Faça:**

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST -d "email=demo@pizzaria.com&senha=Demo@123&login=1" http://localhost:8080/login.php
```

**Mostre:** `403`. Depois, no navegador, F12 → Elements → procurar `csrf_token`: o campo escondido aparece dentro do formulário.

**Fale:**
> "Todo formulário leva um token aleatório que fica guardado na sessão. Um site malicioso não consegue ler esse token, então não consegue mandar um formulário em nome do usuário. Sem o token, o servidor responde 403 Proibido."

### 2.3 Bloqueio por força bruta (40 s)

**Faça:** na **janela anônima**, tentar entrar 5 vezes com `ataque@pizzaria.com` e a senha `errada`.

> Use outro e-mail, não o do demo. Se você bloquear o demo, ele fica travado por 15 minutos.

**Mostre:** nas 4 primeiras tentativas aparece "Email ou senha incorretos!". Na 5ª aparece **"Muitas tentativas erradas. Tente novamente em 15 minuto(s)."** No phpMyAdmin, a tabela `tentativas_login` tem 5 linhas.

**Fale:**
> "Cada erro fica registrado com o e-mail e o IP. Com 5 erros em 15 minutos, o login trava por um tempo, até para a senha certa. Isso inviabiliza o robô que fica testando milhares de senhas."

### 2.4 Headers de segurança (30 s)

**Faça:**

```bash
curl -I http://localhost:8080/index.php
```

**Mostre:** `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, o cookie com `HttpOnly; SameSite=Lax` e `Server: Apache` sem versão (e sem `X-Powered-By`).

**Fale:**
> "Esses cabeçalhos mandam o navegador se proteger. A CSP limita de onde podem vir os scripts. O X-Frame-Options impede que outro site coloque o nosso dentro de um iframe, o que evita clickjacking. O HttpOnly impede que o JavaScript leia o cookie da sessão. E o servidor não diz a versão do PHP nem a do Apache."

### 2.5 Log de segurança (30 s)

**Faça:** no phpMyAdmin, abrir a tabela `logs_seguranca`.

**Mostre:** os eventos `senha_migrada`, `login_ok`, `csrf_invalido`, `login_falha` e `bloqueio`, com IP e horário.

**Fale:**
> "Todo evento importante de segurança fica gravado. Se alguém atacar, temos a trilha para investigar."

---

## 3. API REST com JWT (2 min 30 s)

### 3.1 Login e token (40 s)

**Faça:**

```bash
API=http://localhost:8080/api/v1
TOKEN=$(curl -s -X POST $API/auth/login -H 'Content-Type: application/json' -d '{"email":"demo@pizzaria.com","senha":"Demo@123"}' | sed -E 's/.*"token":"([^"]+)".*/\1/')
echo $TOKEN
echo $TOKEN | cut -d. -f2 | tr '_-' '/+' | base64 -d 2>/dev/null; echo
```

**Mostre:** o token, com 3 partes separadas por ponto, e o conteúdo legível: `{"sub":"1","iss":"pizzaria-api","iat":...,"exp":...}`.

**Fale:**
> "Na API não existe sessão nem cookie. O login devolve um **JWT**. O conteúdo dele qualquer um consegue ler; por isso nunca vai senha ali dentro. O que protege o token é a **assinatura**, feita com um segredo que só o servidor conhece. Eu escrevi o JWT à mão, em `api/src/Jwt.php`."

### 3.2 Rota protegida, sem e com token (30 s)

**Faça:**

```bash
curl -i $API/auth/me
curl -s $API/auth/me -H "Authorization: Bearer $TOKEN"; echo
```

**Mostre:** sem o token, `401 Unauthorized` com `WWW-Authenticate: Bearer`. Com o token, `200` e os dados do Cliente Demo.

### 3.3 Token adulterado (40 s)

**Faça:** trocar o `sub` para `2`, como se fosse outro usuário, e manter a assinatura original:

```bash
P=$(echo -n '{"sub":"2","iss":"pizzaria-api","iat":1760000000,"exp":1999999999}' | base64 -w0 | tr '+/' '-_' | tr -d '=')
curl -s $API/auth/me -H "Authorization: Bearer $(echo $TOKEN|cut -d. -f1).$P.$(echo $TOKEN|cut -d. -f3)"; echo
```

**Mostre:** `401` com a mensagem `"Assinatura do token inválida."`

**Fale:**
> "Mudei um único campo e a assinatura deixou de bater. Sem o segredo, não dá para gerar uma assinatura nova. A API também recusa um token com `alg: none` e um token expirado."

*(Extra, se sobrar tempo: o token expirado.)*

```bash
EXP=$(docker compose exec -T web php api/bin/gerar-token.php 1 -60 | tr -d '\r\n')
curl -s $API/auth/me -H "Authorization: Bearer $EXP"; echo
```

O resultado é `token_expirado`.

### 3.4 Criar um pedido pela API, tentando fraudar o preço, e ver no site (40 s)

**Faça:**

```bash
curl -s -X POST $API/pedidos -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"itens":[{"produto_id":2,"quantidade":2,"preco":0.01},{"produto_id":11,"quantidade":1}],"forma_pagamento":"PIX"}'; echo
```

**Mostre:**
- A resposta é `201`. O total é **108.8** (2 × 44,90 + 14,00 + 5,00 de frete), e não 0,01.
- Aparece `"entrega":{"origem":"microservico","tempo_estimado_min":27}`.
- Na **aba 1**, em **Meus Pedidos**, o mesmo pedido aparece. O botão "detalhes" funciona.

**Fale:**
> "O cliente mandou preço 0,01, mas a API ignora esse campo e busca o preço no banco. O frete veio do microserviço. E o pedido aparece no site porque a API e o site usam o mesmo banco e as mesmas regras."

---

## 4. Microserviço de entrega (2 min)

### 4.1 Health e chave inválida (40 s)

**Faça:**

```bash
E=http://localhost:8081
curl -s $E/health; echo
curl -s -w " [%{http_code}]\n" -X POST $E/v1/entrega/calcular -H 'Content-Type: application/json' -H 'X-Service-Key: errada' -d '{"latitude":-20.4590,"longitude":-54.5960}'
```

**Mostre:** `"status": "ok"` com o uptime. Com a chave errada, `401`.

**Fale:**
> "O /health é público, porque o Docker usa essa rota para saber se o serviço está vivo. As outras rotas exigem a chave `X-Service-Key`, que só o site conhece. É a autenticação entre serviços."

### 4.2 Cálculo de frete (30 s)

**Faça:**

```bash
curl -s -X POST $E/v1/entrega/calcular -H 'Content-Type: application/json' -H 'X-Service-Key: chave-dev-entrega' -d '{"latitude":-20.4590,"longitude":-54.5960}'
curl -s -w " [%{http_code}]\n" -X POST $E/v1/entrega/calcular -H 'Content-Type: application/json' -H 'X-Service-Key: chave-dev-entrega' -d '{"latitude":-23.5505,"longitude":-46.6333}'
```

**Mostre:** `2.04 km`, `taxa 5.0`, `27 min`. Com a coordenada de São Paulo, `422`: "Endereço fora da área de entrega." (891 km).

### 4.3 Derrubar o microserviço e ver o fallback (50 s)

**Faça:**
1. **Aba 1**: adicionar uma pizza no **Cardápio** e abrir o **Carrinho**. Mostrar a linha "⏱️ Tempo estimado: ~27 min · calculado pelo serviço de entrega".
2. No Git Bash: `docker compose stop entrega`
3. Recarregar o carrinho (F5). A primeira carga demora cerca de 4 s.

**Mostre:** "Serviço de entrega indisponível · frete calculado localmente". O frete continua R$ 5,00 e o botão Finalizar continua funcionando. Opcional: `docker compose logs web --since 1m | grep "microservico indisponivel"` mostra o aviso no log.

**Fale:**
> "O microserviço caiu, mas a pizzaria não parou. O site percebe a falha e usa o cálculo antigo como plano B, o que se chama **fallback**. Também tem um **disjuntor**: depois de uma falha, o site passa 10 segundos sem tentar o serviço, para nenhuma página ficar esperando um serviço que já sabemos que está fora."

4. Subir de novo: `docker compose start entrega`. Esperar uns 10 s, por causa do disjuntor, e recarregar: volta "calculado pelo serviço de entrega".

---

## 5. Smoke test, a bateria automática (1 min)

**Faça:**

```bash
bash scripts/smoke-test.sh
```

**Mostre:** cerca de 62 linhas verdes com `OK` e, no fim, `Resultado: 62 OK, 0 FALHOU`. Leva uns 10 segundos.

**Fale:**
> "Para não depender de testar tudo na mão, escrevi um script que testa o microserviço, a segurança do site e a API: JWT adulterado, IDOR, força bruta, fraude de preço, CORS... Se alguma mudança quebrar uma proteção, aparece em vermelho."

**Encerramento:**
> "Resumindo: o site ficou mais seguro, ganhou uma API para outros sistemas e separou o cálculo de entrega num serviço independente que, se cair, não derruba a loja."

---

## Se algo der errado na hora

| Problema | Solução |
|---|---|
| O login do demo diz "Muitas tentativas" | `docker compose exec db mysql -upizzaria -ppizzaria123 pizzaria -e "DELETE FROM tentativas_login"` |
| A senha do demo já está em `$2y$` | `bash scripts/smoke-test.sh --reset-demo` e depois sair e entrar de novo |
| O carrinho continua "indisponível" depois do `start` | esperar 10 s (disjuntor) e checar `docker compose ps` (entrega `healthy`) |
| O site não abre | `docker compose up -d` e esperar o db ficar `(healthy)` |
| A variável `$TOKEN` está vazia | rodar de novo o comando de login do passo 3.1 (o demo pode estar bloqueado, ver a 1ª linha) |
| "403" ao enviar um formulário no navegador | recarregar a página (F5) para pegar um token CSRF novo |

---

## Perguntas prováveis do professor

**Por que bcrypt e não MD5?**
O MD5 foi feito para ser rápido: um computador testa bilhões de senhas por segundo, e já existem tabelas prontas que desfazem o hash. O bcrypt é lento **de propósito** e usa um *salt* aleatório. Assim, duas senhas iguais geram hashes diferentes e quebrar um hash custa caro demais.

**Como migrar as senhas sem saber quais são?**
No login, o usuário digita a senha. Se ela bate com o MD5 antigo, nesse momento salvamos o bcrypt no lugar. Quem nunca mais entrar continua com o MD5; em produção, essas contas teriam a senha resetada depois de um prazo.

**O que é CSRF?**
É quando um site malicioso faz o **seu navegador** enviar um formulário para outro site em que você está logado, e o cookie vai junto automaticamente. O token CSRF é um valor aleatório que só o nosso site conhece e coloca no formulário. O site atacante não consegue ler esse valor, então o pedido dele chega sem token e leva 403.

**Por que a API não precisa de token CSRF?**
Porque ela não usa cookie: a identidade vai no header `Authorization`, que o navegador **não** envia sozinho. Sem cookie automático, não existe CSRF.

**Por que JWT?**
A API é *stateless*: o servidor não guarda sessão. O token carrega quem é o usuário e até quando vale, com uma assinatura que prova que foi o nosso servidor que emitiu. Isso funciona bem para app de celular, para outros sistemas e para vários servidores ao mesmo tempo. A desvantagem é que não dá para "deslogar" um token antes de ele expirar sem criar uma lista de bloqueio. Por isso, a validade é curta (1 h).

**O JWT é criptografado?**
Não. Ele é só codificado em base64url, e qualquer um consegue ler (mostrei isso no `base64 -d`). Ele é **assinado**: dá para ler, mas não dá para alterar. Por isso, só vai no token o id do usuário.

**O que torna isso um microserviço, e não só mais um arquivo PHP?**
Ele tem **container próprio**, **código próprio**, **porta própria** e **uma responsabilidade só** (entrega). Não acessa o banco do site: conversa só por HTTP/JSON, com autenticação própria (`X-Service-Key`). Dá para atualizar, reiniciar ou escalar o microserviço sem mexer no site. E se ele cair, o site continua funcionando (fallback).

**O que é o fallback e o disjuntor (circuit breaker)?**
O fallback é o plano B: se o serviço não responde, o site usa o cálculo local. O disjuntor evita que cada página espere o timeout de um serviço que já sabemos que caiu: depois de uma falha, o site pula o serviço por 10 segundos.

**O que é IDOR?**
É trocar o número na URL (`pedido?id=5` → `id=6`) e ver o pedido de outra pessoa. Todo SQL de pedido tem `AND usuario_id = <dono>`. Na API, o pedido de outra pessoa responde **404**, e não 403, para nem confirmar que o pedido existe.

**Como vocês evitam SQL injection e XSS?**
Para SQL injection, todo SQL usa *prepared statements* (PDO com `?`): o dado nunca vira parte do comando. Para XSS, todo dado que vai para o HTML passa pela função `e()` (htmlspecialchars), então um `<script>` aparece como texto e não executa.

**O que vocês fariam diferente em produção?**
- **HTTPS** em tudo, com HSTS. O cookie da sessão já liga o `Secure` sozinho quando há HTTPS.
- **Segredos fora do repositório**: um `JWT_SECRET` e uma chave do serviço longos e aleatórios (`openssl rand -hex 32`), no `.env` ou num cofre de segredos. O código já se recusa a rodar fora de dev com os valores padrão.
- **CSP sem `'unsafe-inline'**: mover os scripts escritos no HTML para arquivos `.js`.
- O microserviço **sem porta exposta** para fora, só na rede interna do Docker.
- **Rate limit também por IP**, monitoramento dos logs e backup do banco.
- Revogação de JWT (lista de bloqueio) ou um *refresh token*.

**Por que PHP puro, sem framework?**
Para mostrar como cada proteção funciona por dentro. Num projeto real, um framework (Laravel, Symfony) já traz CSRF, hash de senha e roteamento prontos, e testados por muita gente.
