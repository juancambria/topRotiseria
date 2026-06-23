USE login;

CREATE TABLE IF NOT EXISTS platos (
    id VARCHAR(50) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS receta_plato (
    id INT(11) NOT NULL AUTO_INCREMENT,
    plato_id VARCHAR(50) NOT NULL,
    ingrediente_codigo VARCHAR(50) NOT NULL,
    cantidad_utilizada_gr_cc_un DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_plato (plato_id),
    KEY idx_ingrediente_codigo (ingrediente_codigo),
    UNIQUE KEY uk_plato_ingrediente (plato_id, ingrediente_codigo),
    CONSTRAINT fk_receta_plato
        FOREIGN KEY (plato_id)
        REFERENCES platos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;