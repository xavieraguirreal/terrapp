-- =====================================================
-- TERRApp Blog Database Schema
-- Base de datos: verumax_terrapp (tablas adicionales para el blog)
-- =====================================================

-- Tabla de artículos del blog
CREATE TABLE IF NOT EXISTS blog_articulos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    contenido TEXT NOT NULL,
    opinion_editorial TEXT DEFAULT NULL,
    tips JSON DEFAULT NULL COMMENT 'Array de tips prácticos',
    contenido_original TEXT DEFAULT NULL COMMENT 'Contenido original de la fuente',

    -- Fuente
    fuente_nombre VARCHAR(255) DEFAULT NULL,
    fuente_url VARCHAR(500) DEFAULT NULL,
    imagen_url VARCHAR(500) DEFAULT NULL,

    -- Región y país
    region ENUM('sudamerica', 'internacional') DEFAULT 'internacional',
    pais_origen VARCHAR(50) DEFAULT NULL COMMENT 'País de origen de la noticia',

    -- Categorización
    categoria VARCHAR(100) DEFAULT 'general' COMMENT 'Categoría principal',
    tags JSON DEFAULT NULL COMMENT 'Array de tags/hashtags',

    -- Estado y fechas
    estado ENUM('borrador', 'publicado', 'rechazado', 'programado') DEFAULT 'borrador',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_publicacion DATETIME DEFAULT NULL,
    fecha_programada DATETIME DEFAULT NULL COMMENT 'Para publicación programada',

    -- Métricas
    vistas INT DEFAULT 0,
    tiempo_lectura INT DEFAULT 3 COMMENT 'Minutos estimados de lectura',

    -- Reacciones (emoji counters)
    reaccion_interesante INT DEFAULT 0 COMMENT 'Emoji: planta',
    reaccion_encanta INT DEFAULT 0 COMMENT 'Emoji: corazón verde',
    reaccion_importante INT DEFAULT 0 COMMENT 'Emoji: fuego',

    -- Compartidos
    shares_whatsapp INT DEFAULT 0,
    shares_facebook INT DEFAULT 0,
    shares_twitter INT DEFAULT 0,
    shares_linkedin INT DEFAULT 0,
    shares_copy INT DEFAULT 0,

    -- Índices
    INDEX idx_estado (estado),
    INDEX idx_region (region),
    INDEX idx_fecha_pub (fecha_publicacion),
    INDEX idx_categoria (categoria),
    INDEX idx_slug (slug),
    FULLTEXT idx_busqueda (titulo, contenido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de URLs ya procesadas (evitar duplicados)
CREATE TABLE IF NOT EXISTS blog_urls_procesadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    url VARCHAR(500) NOT NULL UNIQUE,
    fecha_procesada DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_url (url(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de noticias pendientes (cache de Tavily)
CREATE TABLE IF NOT EXISTS blog_noticias_pendientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    url VARCHAR(500) NOT NULL,
    titulo VARCHAR(255) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    contenido TEXT DEFAULT NULL,
    imagen_url VARCHAR(500) DEFAULT NULL,
    fuente VARCHAR(255) DEFAULT NULL,
    region ENUM('sudamerica', 'internacional') DEFAULT 'internacional',
    usado BOOLEAN DEFAULT FALSE,
    fecha_obtenida DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_url (url(255)),
    INDEX idx_usado (usado),
    INDEX idx_fecha (fecha_obtenida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de contador regional (ratio sudamerica:internacional)
CREATE TABLE IF NOT EXISTS blog_contador_regional (
    id INT PRIMARY KEY DEFAULT 1,
    contador_sudamerica INT DEFAULT 0,
    contador_internacional INT DEFAULT 0,
    ratio_objetivo DECIMAL(3,1) DEFAULT 3.5 COMMENT 'Ratio objetivo sudamerica:internacional',
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar registro inicial del contador regional
INSERT INTO blog_contador_regional (id, contador_sudamerica, contador_internacional, ratio_objetivo)
VALUES (1, 0, 0, 3.5)
ON DUPLICATE KEY UPDATE id = id;

-- Tabla de tokens para acciones desde email
CREATE TABLE IF NOT EXISTS blog_tokens_accion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL UNIQUE,
    articulo_id INT NOT NULL,
    accion VARCHAR(50) NOT NULL COMMENT 'aprobar, rechazar, saltear',
    usado BOOLEAN DEFAULT FALSE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_uso DATETIME DEFAULT NULL,
    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_articulo (articulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de categorías (Topic Clusters)
CREATE TABLE IF NOT EXISTS blog_categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL UNIQUE,
    nombre_es VARCHAR(100) NOT NULL,
    nombre_pt VARCHAR(100) DEFAULT NULL,
    nombre_en VARCHAR(100) DEFAULT NULL,
    nombre_fr VARCHAR(100) DEFAULT NULL,
    nombre_nl VARCHAR(100) DEFAULT NULL,
    descripcion_es TEXT DEFAULT NULL,
    descripcion_pt TEXT DEFAULT NULL,
    descripcion_en TEXT DEFAULT NULL,
    descripcion_fr TEXT DEFAULT NULL,
    descripcion_nl TEXT DEFAULT NULL,
    icono VARCHAR(10) DEFAULT NULL COMMENT 'Emoji del tema',
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar categorías iniciales
INSERT INTO blog_categorias (slug, nombre_es, nombre_pt, nombre_en, nombre_fr, nombre_nl, icono, orden) VALUES
('huertos-urbanos', 'Huertos Urbanos', 'Hortas Urbanas', 'Urban Gardens', 'Jardins Urbains', 'Stadstuinen', '🌱', 1),
('compostaje', 'Compostaje', 'Compostagem', 'Composting', 'Compostage', 'Composteren', '🌿', 2),
('riego', 'Riego y Agua', 'Irrigação e Água', 'Irrigation & Water', 'Irrigation et Eau', 'Irrigatie en Water', '💧', 3),
('plantas', 'Plantas y Cultivos', 'Plantas e Cultivos', 'Plants & Crops', 'Plantes et Cultures', 'Planten en Gewassen', '🌻', 4),
('tecnologia', 'Tecnología', 'Tecnologia', 'Technology', 'Technologie', 'Technologie', '📱', 5),
('recetas', 'Recetas y Cocina', 'Receitas e Cozinha', 'Recipes & Cooking', 'Recettes et Cuisine', 'Recepten en Koken', '🍳', 6),
('comunidad', 'Comunidad', 'Comunidade', 'Community', 'Communauté', 'Gemeenschap', '🤝', 7),
('noticias', 'Noticias', 'Notícias', 'News', 'Actualités', 'Nieuws', '📰', 8)
ON DUPLICATE KEY UPDATE slug = slug;

-- Tabla de lista de lectura (guardados)
CREATE TABLE IF NOT EXISTS blog_lista_lectura (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(64) NOT NULL COMMENT 'ID de sesión del navegador',
    articulo_id INT NOT NULL,
    fecha_guardado DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_session_articulo (session_id, articulo_id),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para obtener estadísticas del blog
CREATE OR REPLACE VIEW v_blog_stats AS
SELECT
    (SELECT COUNT(*) FROM blog_articulos WHERE estado = 'publicado') as total_publicados,
    (SELECT COUNT(*) FROM blog_articulos WHERE estado = 'borrador') as total_borradores,
    (SELECT SUM(vistas) FROM blog_articulos WHERE estado = 'publicado') as total_vistas,
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

-- Vista para artículos publicados (para exportar a JSON)
CREATE OR REPLACE VIEW v_blog_articulos_publicados AS
SELECT
    a.id,
    a.titulo,
    a.slug,
    a.contenido,
    a.opinion_editorial,
    a.tips,
    a.fuente_nombre,
    a.fuente_url,
    a.imagen_url,
    a.region,
    a.pais_origen,
    a.categoria,
    a.tags,
    a.fecha_publicacion,
    a.vistas,
    a.tiempo_lectura,
    a.reaccion_interesante,
    a.reaccion_encanta,
    a.reaccion_importante,
    (a.shares_whatsapp + a.shares_facebook + a.shares_twitter + a.shares_linkedin + a.shares_copy) as total_shares
FROM blog_articulos a
WHERE a.estado = 'publicado'
  AND (a.fecha_programada IS NULL OR a.fecha_programada <= NOW())
ORDER BY a.fecha_publicacion DESC;
