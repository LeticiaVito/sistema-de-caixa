CREATE DATABASE IF NOT EXISTS cantina_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cantina_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'atendente') DEFAULT 'atendente',
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    preco_custo DECIMAL(10,2) DEFAULT 0,
    preco_venda DECIMAL(10,2) NOT NULL,
    estoque INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 5,
    validade DATE NULL,
    codigo VARCHAR(50) NULL UNIQUE,
    foto VARCHAR(255) NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS caixas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    valor_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_final DECIMAL(10,2) NULL,
    status ENUM('aberto', 'fechado') NOT NULL DEFAULT 'aberto',
    aberto_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fechado_em DATETIME NULL,
    CONSTRAINT fk_caixa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caixa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'cartao') NOT NULL,
    valor_recebido DECIMAL(10,2) NULL,
    troco DECIMAL(10,2) NULL,
    status ENUM('finalizada', 'cancelada') DEFAULT 'finalizada',
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendas_caixa FOREIGN KEY (caixa_id) REFERENCES caixas(id),
    CONSTRAINT fk_vendas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS itens_venda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_itens_venda FOREIGN KEY (venda_id) REFERENCES vendas(id),
    CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE IF NOT EXISTS movimentacoes_caixa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caixa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('entrada', 'saida', 'sangria') NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_caixa FOREIGN KEY (caixa_id) REFERENCES caixas(id),
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

INSERT INTO usuarios (nome, email, senha, tipo, ativo)
VALUES (
    'Administrador',
    'admin@cantina.local',
    '$2y$10$xF9HhiktJiX8z7EyWrGzxu9QagFnfKF18zbfF1m7rZim2Fb.A4m6e',
    'admin',
    1
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    tipo = VALUES(tipo),
    ativo = VALUES(ativo);
