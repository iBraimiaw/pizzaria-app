-- =====================================================================
-- schema.sql — Estrutura do banco da Pizzaria do Bairro + dados iniciais
-- Roda automaticamente na PRIMEIRA subida do container MySQL
-- (pasta /docker-entrypoint-initdb.d). Para recriar: docker compose down -v
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS pizzaria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pizzaria;

-- ---------------------------------------------------------------------
-- Usuários (clientes e administradores)
-- senha VARCHAR(255): cabe tanto MD5 (32) quanto bcrypt (60) e futuros algoritmos
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    senha         VARCHAR(255) NOT NULL,
    endereco      VARCHAR(255) NOT NULL,
    telefone      VARCHAR(20),
    latitude      DECIMAL(10,7) NULL,
    longitude     DECIMAL(10,7) NULL,
    tipo_usuario  ENUM('CLIENTE','ADMIN') NOT NULL DEFAULT 'CLIENTE',
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cardápio
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    nome  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE produtos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id  INT NOT NULL,
    nome          VARCHAR(100) NOT NULL,
    descricao     TEXT,
    preco         DECIMAL(10,2) NOT NULL,
    imagem_url    VARCHAR(500),
    disponivel    BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    INDEX idx_produtos_nome (nome),
    INDEX idx_produtos_disponivel (disponivel)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Pedidos
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id        INT NOT NULL,
    total             DECIMAL(10,2) NOT NULL,
    endereco_entrega  VARCHAR(255) NOT NULL,
    distancia_km      DECIMAL(6,2) NOT NULL DEFAULT 0,
    taxa_entrega      DECIMAL(10,2) NOT NULL DEFAULT 0,
    forma_pagamento   VARCHAR(30) NOT NULL,
    troco_para        DECIMAL(10,2) NULL,
    status            ENUM('PENDENTE','PREPARANDO','SAIU PARA ENTREGA','ENTREGUE','CANCELADO')
                      NOT NULL DEFAULT 'PENDENTE',
    data_pedido       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_pedidos_usuario_data (usuario_id, data_pedido)
) ENGINE=InnoDB;

-- Pizza meia a meia (dois sabores) — criada no momento do pedido
CREATE TABLE pizzas_personalizadas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id    INT NOT NULL,
    sabor1_id    INT NOT NULL,
    sabor2_id    INT NOT NULL,
    preco_total  DECIMAL(10,2) NOT NULL,
    quantidade   INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_pp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pp_sabor1 FOREIGN KEY (sabor1_id) REFERENCES produtos(id),
    CONSTRAINT fk_pp_sabor2 FOREIGN KEY (sabor2_id) REFERENCES produtos(id)
) ENGINE=InnoDB;

-- Itens do pedido: OU um produto normal (produto_id) OU uma pizza personalizada
CREATE TABLE itens_pedido (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id               INT NOT NULL,
    produto_id              INT NULL,
    quantidade              INT NOT NULL,
    preco_unitario          DECIMAL(10,2) NOT NULL,
    is_personalizada        BOOLEAN NOT NULL DEFAULT FALSE,
    pizza_personalizada_id  INT NULL,
    CONSTRAINT fk_itens_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id),
    CONSTRAINT fk_itens_pp FOREIGN KEY (pizza_personalizada_id) REFERENCES pizzas_personalizadas(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cupons de desconto
-- tipo 'percentual' (valor = %) ou 'fixo' (valor = R$)
-- ---------------------------------------------------------------------
CREATE TABLE cupons (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    codigo        VARCHAR(30) NOT NULL UNIQUE,
    tipo          ENUM('percentual','fixo') NOT NULL,
    valor         DECIMAL(10,2) NOT NULL,
    valor_minimo  DECIMAL(10,2) NOT NULL DEFAULT 0,
    validade      DATE NULL,
    ativo         BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE cupons_uso (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id  INT NOT NULL,
    cupom_id   INT NOT NULL,
    desconto   DECIMAL(10,2) NOT NULL,
    usado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cu_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_cu_cupom FOREIGN KEY (cupom_id) REFERENCES cupons(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Recuperação de senha (token de uso único com validade)
-- ---------------------------------------------------------------------
CREATE TABLE recuperacao_senha (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    token       VARCHAR(64) NOT NULL,      -- HASH sha256 do token (o token "puro" só vai no link do e-mail)
    expiracao   DATETIME NOT NULL,
    usado       BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_rs_token (token)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MÓDULO DE SEGURANÇA
-- ---------------------------------------------------------------------

-- Cada login que FALHOU vira uma linha. Contando as linhas recentes por
-- e-mail/IP dá para bloquear ataques de força bruta (rate limit).
CREATE TABLE tentativas_login (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    identificador  VARCHAR(150) NOT NULL,     -- normalmente o e-mail digitado
    ip             VARCHAR(45) NOT NULL,      -- 45 caracteres cabe IPv6
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tentativas_ident_data (identificador, criado_em),
    INDEX idx_tentativas_ip_data (ip, criado_em)
) ENGINE=InnoDB;

-- Trilha de auditoria: login, logout, bloqueio, migração de hash, etc.
CREATE TABLE logs_seguranca (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    evento      VARCHAR(50) NOT NULL,
    usuario_id  INT NULL,
    ip          VARCHAR(45) NULL,
    detalhes    TEXT NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_logs_evento_data (evento, criado_em)
) ENGINE=InnoDB;

-- =====================================================================
-- DADOS INICIAIS (seed)
-- =====================================================================

INSERT INTO categorias (id, nome) VALUES
    (1, 'Pizzas Salgadas'),
    (2, 'Pizzas Doces'),
    (3, 'Bebidas'),
    (4, 'Porções');

INSERT INTO produtos (categoria_id, nome, descricao, preco, imagem_url) VALUES
    (1, 'Margherita',      'Molho de tomate, mussarela, tomate fatiado e manjericão fresco.', 42.90, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600'),
    (1, 'Calabresa',       'Mussarela, calabresa fatiada, cebola e azeitonas pretas.',        44.90, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600'),
    (1, 'Frango com Catupiry', 'Frango desfiado temperado com o legítimo Catupiry.',           47.90, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600'),
    (1, 'Portuguesa',      'Presunto, ovos, cebola, ervilha, mussarela e azeitonas.',         48.90, 'https://images.unsplash.com/photo-1594007654729-407eedc4be65?w=600'),
    (1, 'Quatro Queijos',  'Mussarela, provolone, parmesão e gorgonzola.',                    52.90, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600'),
    (1, 'Pepperoni',       'Mussarela coberta com fatias generosas de pepperoni.',            54.90, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600'),
    (1, 'Pantaneira',      'Carne seca desfiada, mandioca, cebola roxa e queijo coalho.',     56.90, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600'),
    (2, 'Chocolate com Morango', 'Chocolate ao leite derretido com morangos frescos.',        46.90, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600'),
    (2, 'Romeu e Julieta', 'Mussarela com goiabada cremosa.',                                 44.90, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600'),
    (2, 'Banana com Canela', 'Banana fatiada, açúcar, canela e leite condensado.',            42.90, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600'),
    (3, 'Coca-Cola 2L',    'Refrigerante Coca-Cola garrafa 2 litros.',                        14.00, 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=600'),
    (3, 'Guaraná 2L',      'Refrigerante de guaraná garrafa 2 litros.',                       12.00, 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=600'),
    (4, 'Batata Frita',    'Porção de batata frita crocante (400g).',                         22.00, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600');

-- Cupons de exemplo
INSERT INTO cupons (codigo, tipo, valor, valor_minimo, validade) VALUES
    ('PIZZA10',     'percentual', 10.00,  0.00, NULL),  -- 10% de desconto
    ('DESCONTO5',   'fixo',        5.00, 30.00, NULL),  -- R$ 5 em pedidos acima de R$ 30
    ('FRETEGRATIS', 'percentual', 100.00, 0.00, NULL);  -- frete grátis

-- Usuário de teste: demo@pizzaria.com / Demo@123
-- A senha está em MD5 DE PROPÓSITO (formato antigo e inseguro). O módulo de
-- segurança demonstra a migração automática para bcrypt no primeiro login.
-- Depois de um ensaio, volte o MD5 com: bash scripts/smoke-test.sh --reset-demo
INSERT INTO usuarios (nome, email, senha, endereco, telefone, latitude, longitude) VALUES
    ('Cliente Demo', 'demo@pizzaria.com', MD5('Demo@123'),
     'Av. Afonso Pena, 3000 - Centro, Campo Grande - MS', '(67) 99999-0000',
     -20.4590, -54.5960);
