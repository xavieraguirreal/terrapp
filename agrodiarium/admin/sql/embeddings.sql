-- TERRApp Blog - Schema para Embeddings (Búsqueda Semántica)
-- Ejecutar en la base de datos verumax_terrapp

-- Tabla para almacenar embeddings de artículos
CREATE TABLE IF NOT EXISTS blog_embeddings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo_id INT NOT NULL,
    embedding JSON NOT NULL COMMENT 'Vector de 1536 dimensiones (text-embedding-3-small)',
    texto_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 del texto para detectar cambios',
    modelo VARCHAR(50) DEFAULT 'text-embedding-3-small',
    tokens_usados INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY idx_articulo (articulo_id),
    INDEX idx_fecha (fecha_creacion),

    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para cache de búsquedas frecuentes (opcional, mejora performance)
CREATE TABLE IF NOT EXISTS blog_search_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 de la query',
    query_text VARCHAR(500) NOT NULL,
    embedding JSON NOT NULL,
    resultados JSON COMMENT 'IDs de artículos ordenados por similitud',
    hits INT DEFAULT 1 COMMENT 'Veces que se usó este cache',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),

    UNIQUE KEY idx_query_hash (query_hash),
    INDEX idx_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procedimiento para limpiar cache expirado (ejecutar con cron)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS limpiar_cache_busqueda()
BEGIN
    DELETE FROM blog_search_cache WHERE fecha_expiracion < NOW();
END //
DELIMITER ;
