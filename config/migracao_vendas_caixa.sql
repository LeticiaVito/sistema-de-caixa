USE cantina_db;

ALTER TABLE vendas
    ADD COLUMN caixa_id INT NULL AFTER id,
    ADD INDEX idx_vendas_caixa_id (caixa_id),
    ADD CONSTRAINT fk_vendas_caixa
        FOREIGN KEY (caixa_id) REFERENCES caixas(id);
