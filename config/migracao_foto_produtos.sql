USE cantina_db;

ALTER TABLE produtos
    ADD COLUMN foto VARCHAR(255) NULL AFTER codigo;
