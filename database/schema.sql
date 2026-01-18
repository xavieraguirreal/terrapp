-- =====================================================
-- TERRApp Database Schema
-- Base de datos: verumax_terrapp
-- =====================================================

-- Tabla de suscriptores (interesados en la app)
CREATE TABLE IF NOT EXISTS subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    nombre VARCHAR(100) DEFAULT NULL,
    pais_codigo VARCHAR(10) DEFAULT 'es_AR' COMMENT 'Código de idioma/país detectado',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    random_multiplier TINYINT NOT NULL DEFAULT 3 COMMENT 'Multiplicador aleatorio 3-10 para el contador',
    confirmado BOOLEAN DEFAULT FALSE COMMENT 'Email confirmado via link',
    token_confirmacion VARCHAR(64) DEFAULT NULL,
    comentario TEXT DEFAULT NULL COMMENT 'Sugerencia o comentario opcional del usuario',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    unsubscribed_at DATETIME DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_created (created_at),
    INDEX idx_pais (pais_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de contador global (cache del contador para no recalcular siempre)
CREATE TABLE IF NOT EXISTS counter_cache (
    id INT PRIMARY KEY DEFAULT 1,
    total_real INT DEFAULT 0 COMMENT 'Cantidad real de suscriptores',
    total_display INT DEFAULT 0 COMMENT 'Cantidad para mostrar (con multiplicadores)',
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar registro inicial del contador
INSERT INTO counter_cache (id, total_real, total_display) VALUES (1, 0, 0)
ON DUPLICATE KEY UPDATE id = id;

-- Tabla de configuración general
CREATE TABLE IF NOT EXISTS config (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value TEXT,
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuraciones iniciales
INSERT INTO config (config_key, config_value, description) VALUES
('progress_percent', '35', 'Porcentaje de progreso del desarrollo'),
('progress_phase', 'Diseño y Planificación', 'Fase actual del desarrollo'),
('launch_date', '2026-06-01', 'Fecha estimada de lanzamiento'),
('sendgrid_from_email', 'terrapp@verumax.com', 'Email remitente para notificaciones'),
('sendgrid_from_name', 'TERRApp', 'Nombre remitente para notificaciones')
ON DUPLICATE KEY UPDATE config_key = config_key;

-- Tabla de logs de emails enviados
CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT DEFAULT NULL,
    email_to VARCHAR(255) NOT NULL,
    email_type VARCHAR(50) NOT NULL COMMENT 'confirmation, welcome, newsletter',
    subject VARCHAR(255),
    status ENUM('pending', 'sent', 'failed', 'opened', 'clicked') DEFAULT 'pending',
    sendgrid_message_id VARCHAR(100) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME DEFAULT NULL,
    opened_at DATETIME DEFAULT NULL,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE SET NULL,
    INDEX idx_email_type (email_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de FAQs
CREATE TABLE IF NOT EXISTS faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pregunta_es TEXT NOT NULL,
    respuesta_es TEXT NOT NULL,
    pregunta_pt TEXT DEFAULT NULL,
    respuesta_pt TEXT DEFAULT NULL,
    pregunta_en TEXT DEFAULT NULL,
    respuesta_en TEXT DEFAULT NULL,
    pregunta_fr TEXT DEFAULT NULL,
    respuesta_fr TEXT DEFAULT NULL,
    pregunta_nl TEXT DEFAULT NULL,
    respuesta_nl TEXT DEFAULT NULL,
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FAQs iniciales
INSERT INTO faqs (pregunta_es, respuesta_es, pregunta_pt, respuesta_pt, pregunta_en, respuesta_en, orden) VALUES
('¿TERRApp es gratis?',
 'TERRApp tendrá una versión gratuita con todas las funcionalidades básicas. Estamos evaluando opciones premium para funcionalidades avanzadas, pero nuestro compromiso es que la herramienta sea accesible para todas las personas.',
 'O TERRApp é gratuito?',
 'O TERRApp terá uma versão gratuita com todas as funcionalidades básicas. Estamos avaliando opções premium para funcionalidades avançadas, mas nosso compromisso é que a ferramenta seja acessível para todas as pessoas.',
 'Is TERRApp free?',
 'TERRApp will have a free version with all basic features. We are evaluating premium options for advanced features, but our commitment is that the tool be accessible to everyone.',
 1),

('¿Necesito experiencia previa en agricultura?',
 'No, TERRApp está diseñado tanto para personas principiantes como para huerteros y huerteras con experiencia. Las instrucciones son paso a paso y sin tecnicismos innecesarios.',
 'Preciso de experiência prévia em agricultura?',
 'Não, o TERRApp é projetado tanto para iniciantes quanto para horticultores experientes. As instruções são passo a passo e sem jargões desnecessários.',
 'Do I need prior farming experience?',
 'No, TERRApp is designed for both beginners and experienced gardeners. Instructions are step-by-step and without unnecessary jargon.',
 2),

('¿Funciona en mi país?',
 'TERRApp está diseñado específicamente para Sudamérica. Detecta automáticamente tu ubicación y adapta los calendarios de siembra, consejos y recomendaciones a tu clima y región específica.',
 'Funciona no meu país?',
 'O TERRApp é projetado especificamente para a América do Sul. Detecta automaticamente sua localização e adapta os calendários de plantio, dicas e recomendações ao seu clima e região específica.',
 'Does it work in my country?',
 'TERRApp is specifically designed for South America. It automatically detects your location and adapts planting calendars, tips, and recommendations to your specific climate and region.',
 3),

('¿Qué pasa con mis datos personales?',
 'Respetamos tu privacidad. Solo recopilamos los datos necesarios para brindarte el servicio. No vendemos ni compartimos tu información con terceros. Podés solicitar la eliminación de tus datos en cualquier momento.',
 'O que acontece com meus dados pessoais?',
 'Respeitamos sua privacidade. Coletamos apenas os dados necessários para fornecer o serviço. Não vendemos nem compartilhamos suas informações com terceiros. Você pode solicitar a exclusão de seus dados a qualquer momento.',
 'What happens with my personal data?',
 'We respect your privacy. We only collect data necessary to provide the service. We do not sell or share your information with third parties. You can request deletion of your data at any time.',
 4),

('¿Puedo usar TERRApp sin internet?',
 'Estamos trabajando para que las funcionalidades principales estén disponibles offline. Podrás consultar fichas de cultivo, tu calendario y tus registros sin conexión. La sincronización se realizará cuando vuelvas a conectarte.',
 'Posso usar o TERRApp sem internet?',
 'Estamos trabalhando para que as funcionalidades principais estejam disponíveis offline. Você poderá consultar fichas de cultivo, seu calendário e seus registros sem conexão. A sincronização será feita quando você se reconectar.',
 'Can I use TERRApp without internet?',
 'We are working to make main features available offline. You will be able to check crop sheets, your calendar, and your records without connection. Synchronization will occur when you reconnect.',
 5),

('¿Cuándo estará disponible?',
 'Estamos trabajando intensamente para lanzar la versión beta durante 2026. Si dejás tu email, serás de las primeras personas en probarla.',
 'Quando estará disponível?',
 'Estamos trabalhando intensamente para lançar a versão beta durante 2026. Se deixar seu email, você será uma das primeiras pessoas a testá-la.',
 'When will it be available?',
 'We are working hard to launch the beta version during 2026. If you leave your email, you will be among the first to try it.',
 6);

-- Tabla de testimonios
CREATE TABLE IF NOT EXISTS testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    rol VARCHAR(100) NOT NULL COMMENT 'Ej: Huertero urbano, Técnico agrónomo',
    ubicacion VARCHAR(100) DEFAULT NULL COMMENT 'Ej: Córdoba, Argentina',
    texto_es TEXT NOT NULL,
    texto_pt TEXT DEFAULT NULL,
    texto_en TEXT DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    destacado BOOLEAN DEFAULT FALSE,
    orden INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expectativas iniciales (qué esperan de TERRApp)
INSERT INTO testimonials (nombre, rol, ubicacion, texto_es, texto_pt, texto_en, destacado, orden) VALUES
('Mauricio Navarro',
 'Técnico Agrónomo',
 'Argentina',
 'Sueño con una herramienta que ponga el conocimiento agroecológico al alcance de todas las personas, respetando los saberes locales. Eso es TERRApp.',
 'Sonho com uma ferramenta que coloque o conhecimento agroecológico ao alcance de todas as pessoas, respeitando os saberes locais. Isso é o TERRApp.',
 'I dream of a tool that puts agroecological knowledge within everyone''s reach, respecting local knowledge. That''s TERRApp.',
 TRUE, 1),

('Carmen Rodríguez',
 'Huertera urbana',
 'Montevideo, Uruguay',
 'Hace años que cultivo en mi balcón pero siempre me costó encontrar información adaptada a nuestra realidad. Los calendarios de internet están pensados para el hemisferio norte.',
 'Há anos que cultivo na minha varanda mas sempre me custou encontrar informação adaptada à nossa realidade. Os calendários da internet são pensados para o hemisfério norte.',
 'I have been growing on my balcony for years but it was always hard to find information adapted to our reality. Internet calendars are designed for the northern hemisphere.',
 FALSE, 2),

('Roberto Fernández',
 'Pequeño productor',
 'Salta, Argentina',
 'Espero que TERRApp entienda nuestros pisos térmicos. Acá en el norte argentino el clima es muy diferente al de Buenos Aires, y las apps de afuera no tienen idea de eso.',
 'Espero que o TERRApp entenda nossos pisos térmicos. Aqui no norte argentino o clima é muito diferente do de Buenos Aires, e os apps de fora não fazem ideia disso.',
 'I hope TERRApp understands our thermal floors. Here in northern Argentina the climate is very different from Buenos Aires, and foreign apps have no idea about that.',
 FALSE, 3);

-- Tabla de comentarios/sugerencias de suscriptores
CREATE TABLE IF NOT EXISTS subscriber_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    comentario TEXT NOT NULL,
    source VARCHAR(50) DEFAULT 'form' COMMENT 'Origen: form, exit_popup, etc.',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de resultados de quiz (puede ser anónimo o vinculado a suscriptor)
CREATE TABLE IF NOT EXISTS quiz_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT DEFAULT NULL COMMENT 'NULL si es anónimo',
    session_id VARCHAR(64) DEFAULT NULL COMMENT 'Para identificar sesión anónima',
    perfil VARCHAR(50) NOT NULL COMMENT 'beginner_small, beginner_large, intermediate, experienced',
    respuesta_espacio VARCHAR(20) NOT NULL COMMENT 'yard, balcony, indoor',
    respuesta_experiencia VARCHAR(20) NOT NULL COMMENT 'none, some, experienced',
    respuesta_cultivos VARCHAR(20) NOT NULL COMMENT 'vegetables, herbs, mixed',
    respuesta_tiempo VARCHAR(20) NOT NULL COMMENT 'little, moderate, plenty',
    pais_codigo VARCHAR(10) DEFAULT 'es_AR',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    email_captured BOOLEAN DEFAULT FALSE COMMENT 'Si luego dejó su email',
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE SET NULL,
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_session (session_id),
    INDEX idx_perfil (perfil),
    INDEX idx_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para obtener el contador con formato
CREATE OR REPLACE VIEW v_subscriber_count AS
SELECT
    total_real,
    total_display,
    CASE
        WHEN total_display >= 1000 THEN CONCAT(FLOOR(total_display / 1000), '.', FLOOR((total_display % 1000) / 100), 'k')
        ELSE total_display
    END as total_formatted,
    last_updated
FROM counter_cache
WHERE id = 1;
