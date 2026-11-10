-- Fake legacy ERP. Deliberately looks like the systems small companies still
-- run: uppercase names, latin1, no foreign keys, flags as 'S'/'N', and a
-- DT_ALT column refreshed on every change (the only "what changed?" signal).

CREATE TABLE PRODUTO (
    CODPROD      INT           NOT NULL AUTO_INCREMENT,
    REFERENCIA   VARCHAR(30)   NOT NULL,
    DESCRICAO    VARCHAR(120)  NOT NULL,
    UNIDADE      CHAR(3)       NOT NULL DEFAULT 'UN',
    PRECO_VENDA  DECIMAL(12,2) NOT NULL DEFAULT 0,
    PESO_KG      DECIMAL(10,3)          DEFAULT NULL,
    ATIVO        CHAR(1)       NOT NULL DEFAULT 'S',
    DT_ALT       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (CODPROD),
    KEY IX_PRODUTO_DT_ALT (DT_ALT)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Stock per warehouse (CODLOCAL). The store only knows the total.
CREATE TABLE ESTOQUE (
    CODPROD   INT           NOT NULL,
    CODLOCAL  SMALLINT      NOT NULL,
    QTD       DECIMAL(12,3) NOT NULL DEFAULT 0,
    DT_ALT    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (CODPROD, CODLOCAL),
    KEY IX_ESTOQUE_DT_ALT (DT_ALT)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Orders. NUM_EXTERNO holds the store's order number and has no unique
-- constraint: nothing in the ERP stops the same order from being written
-- twice, so SyncHub has to.
CREATE TABLE PEDIDO (
    NUMPED       INT           NOT NULL AUTO_INCREMENT,
    ORIGEM       VARCHAR(10)   NOT NULL DEFAULT 'BALCAO',
    NUM_EXTERNO  VARCHAR(30)            DEFAULT NULL,
    CLIENTE_NOME VARCHAR(100)  NOT NULL,
    CLIENTE_DOC  VARCHAR(18)            DEFAULT NULL,
    DT_EMISSAO   DATETIME      NOT NULL,
    VL_TOTAL     DECIMAL(12,2) NOT NULL DEFAULT 0,
    SITUACAO     CHAR(1)       NOT NULL DEFAULT 'A',
    DT_ALT       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (NUMPED)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE PEDIDO_ITEM (
    NUMPED   INT           NOT NULL,
    SEQ      SMALLINT      NOT NULL,
    CODPROD  INT           NOT NULL,
    QTD      DECIMAL(12,3) NOT NULL,
    VL_UNIT  DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (NUMPED, SEQ)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
