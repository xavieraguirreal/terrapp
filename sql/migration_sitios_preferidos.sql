-- =====================================================
-- Migración: Sitios preferidos para búsqueda con Tavily
-- Ejecutar en: verumax_terrapp
-- =====================================================

CREATE TABLE IF NOT EXISTS blog_sitios_preferidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dominio VARCHAR(255) NOT NULL COMMENT 'Dominio sin https:// (ej: gba.gob.ar)',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo',
    activo BOOLEAN DEFAULT TRUE,
    prioridad INT DEFAULT 1 COMMENT 'Mayor número = más prioridad',
    fecha_agregado DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dominio (dominio),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar algunos sitios de ejemplo
INSERT INTO blog_sitios_preferidos (dominio, nombre, prioridad) VALUES
('gba.gob.ar', 'Gobierno Provincia Buenos Aires', 3),
('argentina.gob.ar', 'Gobierno Argentina', 3),
('inta.gob.ar', 'INTA Argentina', 3)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
