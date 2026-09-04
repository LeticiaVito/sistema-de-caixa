USE cantina_db;

CREATE TABLE IF NOT EXISTS caixa_denominacoes (
    caixa_id INT NOT NULL,
    valor_centavos INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    PRIMARY KEY (caixa_id, valor_centavos),
    CONSTRAINT fk_denominacoes_caixa FOREIGN KEY (caixa_id) REFERENCES caixas(id)
);

CREATE TABLE IF NOT EXISTS venda_dinheiro_detalhes (
    venda_id INT NOT NULL,
    tipo ENUM('recebido','troco') NOT NULL,
    valor_centavos INT NOT NULL,
    quantidade INT NOT NULL,
    PRIMARY KEY (venda_id, tipo, valor_centavos),
    CONSTRAINT fk_dinheiro_venda FOREIGN KEY (venda_id) REFERENCES vendas(id)
);

CREATE TABLE IF NOT EXISTS caixa_contagem_fechamento (
    caixa_id INT NOT NULL,
    valor_centavos INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    PRIMARY KEY (caixa_id, valor_centavos),
    CONSTRAINT fk_contagem_caixa FOREIGN KEY (caixa_id) REFERENCES caixas(id)
);
