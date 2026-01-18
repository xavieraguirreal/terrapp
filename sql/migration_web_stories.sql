-- =====================================================
-- Migración: Web Stories para Google Discover
-- Ejecutar en: verumax_terrapp
-- =====================================================

CREATE TABLE IF NOT EXISTS blog_web_stories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    articulo_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    poster_url VARCHAR(500) DEFAULT NULL COMMENT 'Imagen de portada de la story',
    slides JSON NOT NULL COMMENT 'Array de slides con texto e imagen',
    estado ENUM('borrador', 'publicado') DEFAULT 'borrador',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_publicacion DATETIME DEFAULT NULL,
    vistas INT DEFAULT 0,
    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE,
    INDEX idx_estado (estado),
    INDEX idx_articulo (articulo_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
