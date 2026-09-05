USE cantina_db;

CREATE TABLE IF NOT EXISTS movimentacao_dinheiro_detalhes (
    movimentacao_id INT NOT NULL,
    valor_centavos INT NOT NULL,
    quantidade INT NOT NULL,
    PRIMARY KEY (movimentacao_id, valor_centavos),
    CONSTRAINT fk_dinheiro_movimentacao
        FOREIGN KEY (movimentacao_id) REFERENCES movimentacoes_caixa(id)
);
