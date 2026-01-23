-- TERRApp Blog - Schema para Sistema de Comentarios
-- Ejecutar en la base de datos verumax_terrapp

-- Tabla de comentarios
CREATE TABLE IF NOT EXISTS blog_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo_id INT NOT NULL,
    parent_id INT NULL COMMENT 'ID del comentario padre (para respuestas)',

    -- Datos del autor
    email VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,

    -- Contenido
    contenido TEXT NOT NULL,

    -- Métricas
    likes INT DEFAULT 0,

    -- Estado: pendiente (moderación), aprobado, rechazado
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'aprobado',

    -- Admin puede responder sin ser suscriptor
    es_admin TINYINT(1) DEFAULT 0,

    -- Timestamps
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_articulo (articulo_id),
    INDEX idx_parent (parent_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion),

    -- Foreign keys
    FOREIGN KEY (articulo_id) REFERENCES blog_articulos(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES blog_comentarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para likes de comentarios (evitar duplicados)
CREATE TABLE IF NOT EXISTS blog_comentarios_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comentario_id INT NOT NULL,
    ip_hash VARCHAR(64) NOT NULL COMMENT 'Hash del IP para evitar duplicados',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY idx_comentario_ip (comentario_id, ip_hash),
    FOREIGN KEY (comentario_id) REFERENCES blog_comentarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar contador de comentarios a artículos
-- Nota: Si la columna ya existe, ignorar el error "Duplicate column name"
ALTER TABLE blog_articulos
ADD COLUMN total_comentarios INT DEFAULT 0;
