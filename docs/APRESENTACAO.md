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
2. **Antes do `up`**, criar o `.env` com as chaves de verdade do "Não sou um robô". No Git Bash:

   ```bash
   cd /c/Users/Leeonam/pizzaria-app
   cp .env.example .env
   ```

   Abrir o `.env`, **com o projetor desligado**, e preencher `RECAPTCHA_SITE_KEY` e `RECAPTCHA_SECRET_KEY` com as chaves cadastradas em https://www.google.com/recaptcha/admin (reCAPTCHA v2, caixa de seleção "Não sou um robô", domínio `localhost`).
   - Preencha as **duas**, do mesmo cadastro. Com só a chave do site, a secreta vira a de teste: a caixa aparece sem aviso vermelho, mas o Google aprova qualquer resposta.
   - A chave secreta nunca vai para o Git. O `.env` já está no `.gitignore`. Ela também não pode aparecer na tela. Se aparecer, crie chaves novas no painel do reCAPTCHA.
   - Se o `.env` já existe com as chaves, pule o `cp`: ele troca o arquivo pelo modelo e as chaves somem.
   - A sala precisa de internet. O quadradinho vem do Google.

3. Subir tudo e preparar o banco:

   ```bash
   docker compose up -d --build
   docker compose ps                       # db e entrega precisam estar (healthy)
   bash scripts/smoke-test.sh              # tem que terminar com "0 FALHOU" e a 1ª linha falar das chaves de verdade
   bash scripts/smoke-test.sh --reset-demo # senha do demo volta para MD5
   ```

   Conferir a chave secreta (o smoke test não consegue): abrir http://localhost:8080/login.php, marcar o "Não sou um robô" e entrar com `ataque@pizzaria.com` e a senha `errada`.
   - "Email ou senha incorretos!": certo, o Google aprovou a caixa.
   - "Confirme que você não é um robô.": a chave secreta do `.env` está errada.
   - "Não foi possível confirmar...": o container web não fala com o Google.

   Depois, abrir o `login.php` de novo pela barra de endereço. Por último, limpar as tentativas e o log (inclusive os desse teste):

   ```bash
   docker compose exec db mysql -upizzaria -ppizzaria123 pizzaria -e "DELETE FROM tentativas_login; DELETE FROM logs_seguranca"
   ```

   Alternativa para zerar tudo do zero: `docker compose down -v && docker compose up -d --build`. Leva cerca de 1 minuto.

   Se o `.env` mudar com a stack no ar, rode `docker compose up -d`. Ele recria o web com as chaves novas.

4. Deixar abertos:
   - **Aba 1:** http://localhost:8080/login.php (o site, **sem estar logado**). O quadradinho "Não sou um robô" tem que aparecer **sem aviso vermelho**.
   - **Aba 2:** http://localhost:8082 (phpMyAdmin, servidor `db`, usuário `pizzaria`, senha `pizzaria123`), já na tabela `usuarios` do banco `pizzaria`.
   - **Uma janela anônima** (Ctrl+Shift+N), na tela de login, para os passos 2.3 ("Não sou um robô") e 2.4 (força bruta).
   - **Git Bash** com fonte grande, já na pasta do projeto.
   - **VS Code** com o `README.md` aberto, para mostrar o diagrama.
5. Teste rápido: `curl -s http://localhost:8080/api/v1/health` deve responder `"status":"ok"`.

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

### 2.1 Migração de senha MD5 → bcrypt, ao vivo (50 s)

**Faça:**
1. **Aba 2 (phpMyAdmin)**: tabela `usuarios`, mostrar a coluna `senha` do demo: `e14c05f0dc27e6be...`
2. **Aba 1 (site)**: preencher `demo@pizzaria.com` / `Demo@123`, marcar o **"Não sou um robô"** e clicar em Entrar.
3. Voltar ao phpMyAdmin e recarregar (F5).

**Fale:**
> "Antes, as senhas eram guardadas em MD5. O MD5 é rápido demais e já existem tabelas prontas que desfazem esse hash. Mas não dá para converter sem saber a senha. Então, no próximo login, o sistema confere a senha com o MD5 e, se ela estiver certa, já salva no lugar um hash **bcrypt**. O usuário nem percebe."

**Mostre:** a senha virou `$2y$10$...`. O código está em `includes/security.php`, função `verificar_senha_e_migrar()`.

### 2.2 CSRF bloqueado com 403 (30 s)

**Faça:**

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST -d "email=demo@pizzaria.com&senha=Demo@123&login=1" http://localhost:8080/login.php
```

**Mostre:** `403`. Depois, no navegador, F12 → Elements → procurar `csrf_token`: o campo escondido aparece dentro do formulário. Se o Console mostrar erros 404 de `assets/`, ignore: esses arquivos não vieram no projeto original.

**Fale:**
> "Todo formulário leva um token aleatório que fica guardado na sessão. Um site malicioso não consegue ler esse token, então não consegue mandar um formulário em nome do usuário. Sem o token, o servidor responde 403 Proibido."

### 2.3 "Não sou um robô" (30 s)

**Faça:** na **janela anônima**, digitar o e-mail `robo@pizzaria.com` e a senha `errada`. **Sem marcar** o quadradinho, clicar em Entrar. Depois, no phpMyAdmin, abrir a tabela `logs_seguranca`.

> Não marque o quadradinho neste passo. O botão Entrar fica sempre ativo de propósito: quem decide é o servidor.

**Mostre:** a mensagem **"Confirme que você não é um robô."** No phpMyAdmin, a linha `captcha_invalido` com `Página: /login.php · e-mail: robo@pizzaria.com`.

**Fale:**
> "Antes de olhar a senha, o servidor confere com o Google se uma pessoa marcou o quadradinho. O código que o Google dá ao navegador vale uma vez só e por pouco tempo. Um robô que manda o formulário direto, sem marcar, é barrado antes de chegar na senha."

### 2.4 Bloqueio por força bruta (1 min)

**Faça:** na **janela anônima**, tentar entrar 5 vezes com `ataque@pizzaria.com` e a senha `errada`, **marcando o "Não sou um robô" a cada tentativa**. Depois de cada erro, o quadradinho volta desmarcado e o campo de e-mail volta vazio: digite o e-mail de novo. Se o Google pedir para clicar em imagens, resolva e siga.

> Use outro e-mail, não o do demo. Se você bloquear o demo, ele fica travado por 15 minutos.
>
> Sem marcar o quadradinho, aparece "Confirme que você não é um robô." e a tentativa não conta.

**Mostre:** nas 4 primeiras tentativas aparece "Email ou senha incorretos!". Na 5ª aparece **"Muitas tentativas erradas. Tente novamente em 15 minuto(s)."** No phpMyAdmin, a tabela `tentativas_login` tem 5 linhas.

**Fale:**
> "Cada erro fica registrado com o e-mail e o IP. Com 5 erros em 15 minutos, o login trava por um tempo, até para a senha certa. Isso inviabiliza o robô que fica testando milhares de senhas. E, no site, ele ainda teria que passar pelo 'Não sou um robô' a cada tentativa."

### 2.5 Headers de segurança (20 s)

**Faça:**

```bash
curl -I http://localhost:8080/index.php
```

**Mostre:** `Content-Security-Policy` (com os endereços do Google em `script-src`, `frame-src` e `connect-src`, por causa do "Não sou um robô"), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, o cookie com `HttpOnly; SameSite=Lax` e `Server: Apache` sem versão (e sem `X-Powered-By`).

**Fale:**
> "Esses cabeçalhos mandam o navegador se proteger. A CSP limita de onde podem vir os scripts: o nosso site e o Google, por causa do 'Não sou um robô'. O X-Frame-Options impede que outro site coloque o nosso dentro de um iframe, o que evita clickjacking. O HttpOnly impede que o JavaScript leia o cookie da sessão. E o servidor não diz a versão do PHP nem a do Apache."

### 2.6 Log de segurança (20 s)

**Faça:** no phpMyAdmin, abrir a tabela `logs_seguranca`.

**Mostre:** os eventos `senha_migrada`, `login_ok`, `csrf_invalido`, `captcha_invalido`, `login_falha` e `bloqueio`, com IP e horário.

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

**Mostre:** no começo, uma linha `INFO` com o modo do "Não sou um robô" (na apresentação: `"Não sou um robô" ligado, com as chaves de verdade do .env.`). Ela não conta como teste. Depois, 68 linhas verdes com `OK` e, no fim, `Resultado: 68 OK, 0 FALHOU`. Leva alguns segundos.

O que muda com as chaves:

| Modo | Verificações | Diferença |
|---|---|---|
| Chaves de verdade (`.env` preenchido, o modo da apresentação) | 68 | O script é um robô e não consegue um código válido do Google, então a força bruta pelo site não é testada. No lugar entra "login com resposta FALSA do 'Não sou um robô' -> recusado". Ele só passa se o próprio Google recusar a resposta falsa. Sem internet no container web, ele falha. |
| Chaves de teste (`.env` sem as chaves) | 68 | O Google aprova qualquer resposta, então o teste de força bruta pelo site roda. Ele precisa de internet no container web. |
| Desligado (`RECAPTCHA_ATIVO=0`, o plano B) | 62 | Os 7 testes do "Não sou um robô" são pulados e a força bruta do site é testada sem a caixa. Um `AVISO` amarelo aparece no meio e se repete na última linha. |

**Fale:**
> "Para não depender de testar tudo na mão, escrevi um script que testa o microserviço, a segurança do site e a API: JWT adulterado, IDOR, força bruta, fraude de preço, CORS, o 'Não sou um robô'... Se alguma mudança quebrar uma proteção, aparece em vermelho."

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
| O quadradinho "Não sou um robô" não aparece | falta internet (o quadradinho vem do Google). Plano B, sem abrir o `.env` (ele mostraria a chave secreta no projetor): rodar `RECAPTCHA_ATIVO=0 docker compose up -d`. A variável escrita no comando vale mais que a do `.env`. O quadradinho some e o login funciona sem ele. Abrir o `login.php` de novo pela barra de endereço (o web reinicia e esquece as sessões) e pular o 2.3. Para religar: `docker compose up -d` |
| "Não foi possível confirmar o 'Não sou um robô'. Tente de novo." | o site não conseguiu falar com o Google: internet ou Google fora do ar. Tentar de novo. Se repetir, usar o plano B da linha acima |
| Aviso vermelho no quadradinho, dizendo que é só para teste | o `.env` com as chaves não foi lido, e o site está com as chaves de teste. Conferir o `.env` na pasta do projeto, rodar `docker compose up -d` e abrir o `login.php` de novo |
| O Google pediu para clicar em imagens | normal, ainda mais depois de várias tentativas seguidas. Resolver e seguir |
| "Confirme que você não é um robô." quando era para entrar | faltou marcar, ou o quadradinho venceu (ele desmarca sozinho depois de uns 2 minutos). Marcar de novo e clicar em Entrar. Se continuar mesmo marcando, a chave secreta do `.env` está errada: no phpMyAdmin, a linha `captcha_invalido` mostra `erros do Google: invalid-input-response`. Conferir as duas chaves no `.env`, fora do projetor, e rodar `docker compose up -d` |

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

**Como o servidor sabe que a pessoa marcou o "Não sou um robô"?**
Quando a pessoa marca, o Google entrega um código ao navegador, e o formulário leva esse código junto. O servidor confere o código com o Google usando a **chave secreta**. O código vale uma vez só e por cerca de 2 minutos.

**Por que o do Google, e não um feito à mão?**
O Google analisa o comportamento de quem marca a caixa para separar pessoa de robô, e é o padrão de mercado. O custo é depender de internet e mandar dados ao Google.

**E a API, por que não tem o "Não sou um robô"?**
A API é para programas (app, Postman, outros sistemas), e programa não marca quadradinho. Ela tem o limite de 5 tentativas erradas por e-mail + IP: a partir da 5ª, responde 429. Mas um robô que testa um e-mail diferente a cada tentativa não é barrado pela API. O "Não sou um robô" protege só os formulários do site. O próximo passo seria um limite só por IP na API.

**E a privacidade?**
O quadradinho mostra os links de Privacidade e Termos do Google. Numa loja de verdade, isso entra na política de privacidade do site.

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
