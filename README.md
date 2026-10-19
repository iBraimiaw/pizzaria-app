# Pizzaria do Bairro: segurança, API e microserviço

Site de pizzaria em **PHP 8.2 puro** (sem framework e sem Composer), com MySQL.
Este projeto mostra a implementação de três módulos:

1. **Segurança**: senhas com bcrypt (e migração automática do MD5 antigo), CSRF, bloqueio por força bruta, sessão segura, headers HTTP, proteção contra XSS e IDOR, e log de auditoria.
2. **API REST** (`/api/v1`): login com **JWT** escrito à mão, rotas protegidas, cardápio e pedidos em JSON, com documentação OpenAPI.
3. **Microserviço de entrega**: um container separado que calcula a distância, o frete e o tempo de entrega. Se ele cair, o site usa um cálculo local (fallback).

Todo o código foi escrito à mão, com comentários curtos em português que explicam o **porquê** de cada coisa.

---

## Arquitetura

```
                         ┌──────────────────────────── docker compose ────────────────────────────┐
                         │                                                                         │
  Navegador ──HTTP:8080──┼──► web (PHP 8.2 + Apache)                                              │
  (cliente do site)      │      ├─ páginas .php (cardápio, carrinho, login...)                     │
                         │      ├─ includes/security.php  (módulo de segurança)                   │
                         │      ├─ api/  (API REST /api/v1, JWT)                                   │
                         │      │                                                                 │
  App / Postman / curl ──┼──►   └─ /api/v1/...  (mesmo container, sem sessão: só token JWT)        │
  (cliente da API)       │             │                         │                                 │
                         │             │ SQL (PDO)               │ HTTP + X-Service-Key            │
                         │             ▼                         ▼                                 │
                         │      db (MySQL 8)              entrega (microserviço, porta 8081)       │
                         │      11 tabelas                 calcula distância, frete, tempo        │
                         │             ▲                   NÃO tem acesso ao banco                 │
                         │             │                                                           │
  Navegador ──HTTP:8082──┼──► phpmyadmin (ver o banco)                                            │
                         └─────────────────────────────────────────────────────────────────────────┘
```

Resumo do fluxo:

- **Navegador → web → MySQL**: o site PHP lê e grava no banco com prepared statements (PDO).
- **web → entrega**: para saber o frete, o site faz um `POST http://entrega/v1/entrega/calcular` mandando a chave `X-Service-Key`. O microserviço **não conhece o banco**: recebe coordenadas e devolve números.
- **Cliente → API `/api/v1`**: outro programa (app, Postman, curl) faz login, recebe um token JWT e usa esse token nas próximas chamadas.

---

## Como rodar

Pré-requisito: **Docker Desktop** ligado. Não é preciso instalar PHP nem MySQL.

```bash
cd C:\Users\Leeonam\pizzaria-app
cp .env.example .env          # opcional: os valores padrão já funcionam em dev
docker compose up -d --build
docker compose ps             # espere db e entrega aparecerem como (healthy)
```

| Serviço | URL | Para que serve |
|---|---|---|
| Site | http://localhost:8080 | a pizzaria |
| API REST | http://localhost:8080/api/v1/health | API em JSON |
| Documentação da API | http://localhost:8080/api/openapi.yaml | cole em https://editor.swagger.io |
| Microserviço de entrega | http://localhost:8081/health | serviço de frete |
| phpMyAdmin | http://localhost:8082 | ver o banco (servidor `db`, usuário `pizzaria`, senha `pizzaria123`) |

**Usuário demo:** `demo@pizzaria.com` / `Demo@123`.
Ele começa com a senha em **MD5** de propósito, para mostrar a migração para bcrypt no primeiro login.

**Comandos úteis:**

```bash
bash scripts/smoke-test.sh               # 62 testes automáticos (OK/FALHOU), ~10 s
bash scripts/smoke-test.sh --fallback    # também derruba o microserviço e mostra o plano B
bash scripts/smoke-test.sh --reset-demo  # volta a senha do demo para MD5
docker compose logs -f web entrega       # ver os logs
docker compose down -v && docker compose up -d --build   # zera o banco e recria tudo
```

> Os comandos `bash` e `curl` deste README foram feitos para o **Git Bash**.

---

## Estrutura de pastas

```
pizzaria-app/
├── *.php                  páginas do site (index, cardapio, carrinho, login, cadastro...)
├── .htaccess              bloqueia na web: .git, .env, includes/, database/, docs/, scripts/, Dockerfile...
├── Dockerfile             imagem do site (PHP 8.2 + Apache, sem expor versões)
├── docker-compose.yml     sobe web, db, entrega e phpmyadmin
├── .env.example           modelo das variáveis (segredos ficam no .env, fora do Git)
├── includes/
│   ├── config.php         lê as variáveis de ambiente (env()) e define as constantes
│   ├── functions.php      conexão PDO, carrinho, cupom, cálculo local de frete
│   ├── security.php       MÓDULO DE SEGURANÇA
│   └── entrega.php        cliente HTTP do microserviço (com fallback e disjuntor)
├── api/                   MÓDULO API
│   ├── .htaccess          manda toda URL para o index.php (front controller)
│   ├── index.php          a "esteira" de toda requisição da API
│   ├── openapi.yaml       documentação OpenAPI 3
│   ├── src/               Router, Jwt, Autenticacao, Resposta, Requisicao, Controllers/ (bloqueado na web)
│   └── bin/gerar-token.php   gera token expirado para a demonstração (só linha de comando)
├── services/entrega/      MÓDULO MICROSERVIÇO (container próprio)
│   ├── Dockerfile, apache.conf
│   ├── public/index.php   front controller: log, chave, rotas, validação
│   └── src/               CalculadoraEntrega, SimuladorStatus, Http
├── database/schema.sql    cria as 11 tabelas e os dados iniciais (roda sozinho no 1º up)
├── docs/
│   ├── APRESENTACAO.md    roteiro da apresentação
│   ├── api.http           requisições prontas para o REST Client do VS Code
│   └── pizzaria-api.postman_collection.json
└── scripts/smoke-test.sh  bateria de testes rápida
```

---

## Módulo 1: Segurança

Quase tudo está em `includes/security.php`. O `includes/functions.php` carrega esse arquivo, e por isso **toda página já sai protegida**.

| Proteção | O que faz | Onde |
|---|---|---|
| **Senha com bcrypt** | `password_hash` / `password_verify` no lugar do MD5 | `security.php: gerar_hash_senha()`, `cadastro.php`, `redefinir-senha.php` |
| **Migração MD5 → bcrypt** | no login, se o hash salvo é MD5 e a senha bate, troca por bcrypt na hora, sem o usuário perceber | `security.php: hash_eh_md5_legado()`, `verificar_senha_e_migrar()`; usada em `login.php` e `api/src/Controllers/AuthController.php: login()` |
| **Senha forte no servidor** | mínimo de 8 caracteres, com letra maiúscula, letra minúscula, número e caractere especial; o JavaScript da tela pode ser burlado, o servidor não | `security.php: validar_forca_senha()`, `mensagem_senha_fraca()` |
| **Mesmo tempo de resposta** | quando o e-mail não existe, roda um `password_verify` de mentira, para ninguém descobrir pelo tempo quem tem conta | `security.php: simular_verificacao_senha()` |
| **CSRF** | token aleatório na sessão + campo escondido em todo formulário POST; sem o token a resposta é **403** | `security.php: csrf_token()`, `campo_csrf()`, `csrf_valido()`, `verificar_csrf()`; links GET (remover item, logout) levam `?token=` |
| **Força bruta** | 5 falhas (e-mail + IP) em 15 min bloqueiam o login; na recuperação de senha, o limite é 3 pedidos em 15 min | `security.php: verificar_bloqueio()`, `registrar_tentativa_falha()`, `limpar_tentativas()`; tabela `tentativas_login` |
| **Sessão segura** | cookie HttpOnly + SameSite=Lax (Secure quando há HTTPS), strict mode, novo ID a cada login, expira após 30 min parada | `security.php: iniciar_sessao_segura()`, `renovar_sessao_apos_login()`, `encerrar_sessao()`; `logout.php` |
| **Headers HTTP** | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy; sem `X-Powered-By` e com `Server: Apache` sem versão | `security.php: enviar_headers_seguranca()`, `Dockerfile` (expose_php, ServerTokens) |
| **XSS** | todo dado vindo do banco ou do usuário passa por `e()` (htmlspecialchars) antes de ir para o HTML | `security.php: e()`; `meus-pedidos.php` (JS `escaparHtml()`) |
| **Validação de entrada** | IDs e quantidades viram inteiros (`filter_var`), e-mail, telefone e coordenadas são validados | `carrinho.php`, `cadastro.php`, `pizza-dupla.php`, `cardapio.php` |
| **Preço vem do banco** | o servidor ignora preço, total, frete e desconto enviados pelo navegador e recalcula tudo | `carrinho.php`, `finalizar-pedido.php`, `functions.php: calcularDescontoCupom()` |
| **IDOR** | todo SQL de pedido tem `AND usuario_id = ?`; pedido de outra pessoa dá 404 | `buscar-detalhes-pedido.php`, `pedido-confirmado.php`, `PedidoController.php: buscarPedidoDoUsuario()` |
| **Recuperação de senha** | token de 32 bytes aleatórios, só o hash sha256 vai para o banco, vale 1 h, uso único, mesma mensagem exista ou não o e-mail | `esqueceu-senha.php`, `redefinir-senha.php` |
| **Log de auditoria** | grava login_ok, login_falha, bloqueio, csrf_invalido, senha_migrada, senha_redefinida, recuperacao_solicitada e logout | `security.php: registrar_evento_seguranca()`; tabela `logs_seguranca` |
| **Erros escondidos** | erros só aparecem na tela com `APP_ENV=dev`; em produção, vão só para o log | `includes/config.php`, `Dockerfile` |
| **Arquivos internos bloqueados** | `.git`, `.env`, `schema.sql`, `docker-compose.yml` e `includes/` respondem 403 | `.htaccess` da raiz, `api/src/.htaccess`, `services/.htaccess` |

---

## Módulo 2: API REST (`/api/v1`)

Toda requisição entra por `api/index.php` (front controller), que segue sempre esta ordem: configura → headers → OPTIONS → Router → erro em JSON.

**Endpoints**

| Método | Rota | Precisa de token? | Controller |
|---|---|---|---|
| GET | `/v1/health` | não | `HealthController::verificar()` |
| POST | `/v1/auth/login` | não | `AuthController::login()` |
| GET | `/v1/auth/me` | **sim** | `AuthController::me()` |
| GET | `/v1/categorias` | não | `CategoriaController::listar()` |
| GET | `/v1/produtos?categoria=&busca=&pagina=&limite=` | não | `ProdutoController::listar()` |
| GET | `/v1/produtos/{id}` | não | `ProdutoController::detalhar()` |
| GET | `/v1/pedidos` | **sim** | `PedidoController::listar()` |
| GET | `/v1/pedidos/{id}` | **sim** | `PedidoController::detalhar()` |
| POST | `/v1/pedidos` | **sim** | `PedidoController::criar()` |

**O que foi implementado, e onde**

| Item | Onde |
|---|---|
| **JWT HS256 escrito à mão**: base64url + HMAC-SHA256, comparação com `hash_equals`; recusa `alg: none`, assinatura errada, emissor errado e token expirado | `api/src/Jwt.php: gerar()`, `validar()` |
| Ler o `Authorization: Bearer` e buscar o usuário pelo `sub` do token (o id do usuário **vem só do token**) | `api/src/Autenticacao.php: usuarioLogado()`, `api/src/Requisicao.php: tokenBearer()` |
| API **stateless**: sem sessão e sem cookie | `api/index.php` (`MODO_API`), `security.php` (não inicia sessão no MODO_API) |
| Login reaproveita o módulo de segurança: migração de senha, bloqueio por força bruta (**429** + `Retry-After`) e log | `AuthController.php: login()` |
| Pedido com preço do banco, itens repetidos somados, limites de quantidade, forma de pagamento validada, frete do microserviço e gravação em **transação** | `PedidoController.php: criar()`, `validarItens()`, `buscarProdutosDisponiveis()`, `validarTroco()` |
| Anti-IDOR: pedido de outra pessoa responde 404 | `PedidoController.php: buscarPedidoDoUsuario()` |
| Roteamento com `{id}`, 404 para rota inexistente e 405 com header `Allow` | `api/src/Router.php: despachar()` |
| Erro padrão `{"erro":{"codigo","mensagem"}}`, nunca mostra stack trace | `api/src/Resposta.php: erro()`, `api/src/ErroApi.php`, bloco `catch` de `api/index.php` |
| Corpo JSON com limite de 16 KB (respostas 400, 413 e 415) | `api/src/Requisicao.php: corpoJson()` |
| Paginação com teto de 50 itens; busca com o `%` do LIKE escapado | `api/src/Paginacao.php`, `ProdutoController.php: listar()` |
| Headers de segurança e CORS configurável (`CORS_ORIGIN`) | `Resposta.php: enviarHeadersSeguranca()`, `enviarHeadersCors()` |
| Fora de `dev`, recusa subir com o `JWT_SECRET` padrão ou com menos de 32 caracteres | `api/index.php` |
| Token expirado para a demonstração | `api/bin/gerar-token.php` |
| Documentação | `api/openapi.yaml`, `docs/api.http`, `docs/pizzaria-api.postman_collection.json` |

Exemplo rápido:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@pizzaria.com","senha":"Demo@123"}' | sed -E 's/.*"token":"([^"]+)".*/\1/')
curl -s http://localhost:8080/api/v1/auth/me -H "Authorization: Bearer $TOKEN"
```

---

## Módulo 3: Microserviço de entrega (`services/entrega`, porta 8081)

É um **programa separado**, com container, código e deploy próprios. Ele tem uma única responsabilidade: calcular a entrega. Não tem acesso ao banco.

**Endpoints**

| Método | Rota | Chave? | O que devolve |
|---|---|---|---|
| GET | `/health` | não | status, versão e uptime (usado pelo healthcheck do Docker) |
| POST | `/v1/entrega/calcular` | **sim** | `{latitude, longitude}` → distância, taxa, tempo e `dentro_area` (200), ou 422 se fora da área |
| GET | `/v1/entrega/regras` | **sim** | faixas de frete, raio máximo e tempos |
| GET | `/v1/entrega/status/{id}?criado_em=` | **sim** | status simulado pelo tempo que passou |

**O que foi implementado, e onde**

| Item | Onde |
|---|---|
| Front controller: log de toda requisição, conferência da chave, rotas e validação da entrada (400, 401, 404, 405, 422) | `services/entrega/public/index.php` |
| Autenticação entre serviços: header `X-Service-Key` comparado com `hash_equals`; fora de `dev`, recusa a chave padrão | `services/entrega/public/index.php` |
| Regra de negócio sem HTTP: Haversine, faixas de frete (até 3 km R$5, 6 km R$8, 10 km R$12, 15 km R$18), raio máximo e tempo = 20 min + 3 min/km | `services/entrega/src/CalculadoraEntrega.php: calcular()`, `distanciaKm()`, `taxaPorDistancia()`, `tempoEstimadoMin()` |
| Status simulado do pedido | `services/entrega/src/SimuladorStatus.php: status()` |
| Respostas JSON | `services/entrega/src/Http.php` |
| Só a pasta `public/` fica exposta na web | `services/entrega/apache.conf` |
| Healthcheck e variáveis de ambiente (chave, coordenadas, raio) | `docker-compose.yml` (serviço `entrega`) |

**Do lado do site** (`includes/entrega.php`):

| Item | Onde |
|---|---|
| Cliente HTTP com timeout curto (1 s para conectar, 2 s no total) | `consultarServicoEntrega()` |
| **Fallback**: se o serviço não responde, usa o cálculo local antigo (`calcularDistancia()`, `calcularTaxaEntrega()`) | `calcularEntregaUsuario()` (campo `origem`: `microservico`, `local` ou `sem_coordenadas`) |
| **Disjuntor (circuit breaker)**: depois de uma falha, passa 10 s sem tentar o serviço | `servicoEntregaEmPausa()`, `registrarFalhaServicoEntrega()` |
| Quem usa: carrinho (frete e tempo na tela), finalizar pedido e a API | `carrinho.php`, `finalizar-pedido.php`, `PedidoController.php: criar()` |

---

## Limitações conhecidas (e o que faria em produção)

- **HTTPS**: em dev roda em HTTP. Em produção, colocar HTTPS, que liga o cookie `Secure` sozinho, e HSTS.
- **Segredos**: o `JWT_SECRET` e a `ENTREGA_SERVICE_KEY` padrão estão no repositório e só valem em dev. Fora de dev, a API e o microserviço se recusam a responder com esses valores. Gere valores novos com `openssl rand -hex 32` e guarde no `.env`.
- **CSP com `'unsafe-inline'`**: as páginas têm `<script>` e `onclick` dentro do HTML. O próximo passo seria mover esse código para arquivos `.js` e usar nonce.
- **JWT sem revogação**: o token vale até expirar (1 h). Para ter um logout real na API, seria preciso uma lista de tokens bloqueados.
- **Força bruta**: a contagem é por e-mail + IP. Um ataque que testa vários e-mails a partir do mesmo IP não é barrado; dá para acrescentar um limite só por IP.
- **Cadastro**: a mensagem "Email já cadastrado!" revela se um e-mail tem conta.
- **Fallback**: o cálculo local usa a fórmula antiga e não tem raio máximo.
- **Assets**: os arquivos `assets/css/*.css` e `assets/js/acessibilidade.js` não existem no repositório (dão 404 no navegador, mas não quebram nada).
