-- =====================================================
-- Migración: Agregar sistema de vistas únicas
-- Ejecutar en: verumax_terrapp
-- =====================================================

-- 1. Agregar columna vistas_unicas a blog_articulos
ALTER TABLE blog_articulos
ADD COLUMN vistas_unicas INT DEFAULT 0 COMMENT 'Vistas únicas por IP (para admin)'
AFTER vistas;

-- 2. Crear tabla para trackear IPs (con hash para privacidad)
CREATE TABLE IF NOT EXISTS blog_vistas_unicas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    articulo_id INT NOT NULL,
    ip_hash VARCHAR(64) NOT NULL COMMENT 'Hash SHA256 de la IP',
    fecha_vista DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_articulo_ip (articulo_id, ip_hash),
    INDEX idx_articulo (articulo_id),
    INDEX idx_fecha (fecha_vista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Inicializar vistas_unicas con el valor actual de vistas
UPDATE blog_articulos SET vistas_unicas = vistas WHERE vistas_unicas = 0;

-- 4. Actualizar la vista de estadísticas para incluir vistas únicas
CREATE OR REPLACE VIEW v_blog_stats AS
SELECT
    (SELECT COUNT(*) FROM blog_articulos WHERE estado = 'publicado') as total_publicados,
    (SELECT COUNT(*) FROM blog_articulos WHERE estado = 'borrador') as total_borradores,
    (SELECT SUM(vistas) FROM blog_articulos WHERE estado = 'publicado') as total_vistas,
    (SELECT SUM(vistas_unicas) FROM blog_articulos WHERE estado = 'publicado') as total_vistas_unicas,
    (SELECT SUM(reaccion_interesante + reaccion_encanta + reaccion_importante) FROM blog_articulos) as total_reacciones,
    c.contador_sudamerica,
    c.contador_internacional,
    CASE
        WHEN c.contador_internacional > 0
        THEN ROUND(c.contador_sudamerica / c.contador_internacional, 1)
        ELSE c.contador_sudamerica
    END as ratio_actual,
    c.ratio_objetivo
FROM blog_contador_regional c
WHERE c.id = 1;
