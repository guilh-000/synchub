-- Sample catalog: 12 part types x 25 sizes = 300 products, generated so the
-- data is the same on every machine. A few rows carry the usual legacy
-- quirks on purpose (see the end of the file).
SET NAMES utf8mb4;

CREATE TEMPORARY TABLE TIPO (
    ID INT, PREFIXO VARCHAR(6), NOME VARCHAR(60), PRECO_BASE DECIMAL(12,2), PESO_BASE DECIMAL(10,3)
);
INSERT INTO TIPO VALUES
    (1,  'ROL',  'Rolamento rígido de esferas',  18.90, 0.120),
    (2,  'RET',  'Retentor de óleo',             9.50,  0.030),
    (3,  'COR',  'Correia em V',                 32.00, 0.250),
    (4,  'FIL',  'Filtro de óleo',               27.40, 0.400),
    (5,  'ANE',  'Anel o-ring',                  1.80,  0.005),
    (6,  'PAR',  'Parafuso sextavado',           0.90,  0.020),
    (7,  'ARR',  'Arruela lisa',                 0.25,  0.004),
    (8,  'MAN',  'Mangueira hidráulica',         54.00, 0.800),
    (9,  'ACO',  'Acoplamento elástico',         89.00, 1.100),
    (10, 'BUC',  'Bucha de bronze',              22.30, 0.180),
    (11, 'ENG',  'Engrenagem de dentes retos',   140.00, 1.900),
    (12, 'VAL',  'Válvula de retenção',          76.50, 0.650);

CREATE TEMPORARY TABLE N (I INT);
INSERT INTO N VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),
    (16),(17),(18),(19),(20),(21),(22),(23),(24),(25);

INSERT INTO PRODUTO (REFERENCIA, DESCRICAO, UNIDADE, PRECO_VENDA, PESO_KG, ATIVO, DT_ALT)
SELECT
    CONCAT(T.PREFIXO, '-', LPAD(T.ID * 100 + N.I, 5, '0')),
    CONCAT(T.NOME, ' ', N.I * 5, 'mm'),
    'UN',
    ROUND(T.PRECO_BASE * (1 + N.I * 0.08), 2),
    ROUND(T.PESO_BASE * (1 + N.I * 0.10), 3),
    'S',
    '2026-01-02 08:00:00' + INTERVAL (T.ID * 25 + N.I) HOUR
FROM TIPO T CROSS JOIN N
ORDER BY T.ID, N.I;

-- Two warehouses; the second one only stocks part of the catalog.
INSERT INTO ESTOQUE (CODPROD, CODLOCAL, QTD, DT_ALT)
SELECT CODPROD, 1, (CODPROD * 7) % 120, DT_ALT FROM PRODUTO;

INSERT INTO ESTOQUE (CODPROD, CODLOCAL, QTD, DT_ALT)
SELECT CODPROD, 2, (CODPROD * 3) % 40, DT_ALT FROM PRODUTO WHERE CODPROD % 3 = 0;

-- Legacy quirks the sync has to survive:
-- inactive products (must be hidden in the store, never deleted)
UPDATE PRODUTO SET ATIVO = 'N' WHERE CODPROD % 37 = 0;
-- descriptions with trailing spaces and inconsistent case
UPDATE PRODUTO SET DESCRICAO = CONCAT(UPPER(DESCRICAO), '   ') WHERE CODPROD % 29 = 0;
-- a product without weight
UPDATE PRODUTO SET PESO_KG = NULL WHERE CODPROD % 53 = 0;
-- negative stock (it happens)
UPDATE ESTOQUE SET QTD = -2 WHERE CODPROD = 41 AND CODLOCAL = 1;

-- A couple of counter orders that never came from the store.
INSERT INTO PEDIDO (ORIGEM, CLIENTE_NOME, CLIENTE_DOC, DT_EMISSAO, VL_TOTAL, SITUACAO)
VALUES ('BALCAO', 'Oficina Exemplo Ltda', '00.000.000/0001-00', '2026-03-10 10:15:00', 113.40, 'F'),
       ('BALCAO', 'Consumidor final',     NULL,                 '2026-03-11 16:40:00', 27.40,  'F');

INSERT INTO PEDIDO_ITEM (NUMPED, SEQ, CODPROD, QTD, VL_UNIT)
VALUES (1, 1, 1, 6, 18.90),
       (2, 1, 76, 1, 27.40);
