<?php
/**
 * TERRApp Blog - Configuración
 *
 * IMPORTANTE: Este archivo contiene credenciales sensibles.
 * En producción, usar variables de entorno.
 */

// Prevenir acceso directo
if (!defined('TERRAPP_BLOG')) {
    define('TERRAPP_BLOG', true);
}

// Base de datos (usa la misma de TERRApp)
define('DB_HOST', 'localhost');
define('DB_NAME', 'verumax_terrapp');
define('DB_USER', 'verumax_admin');
define('DB_PASS', '9BD121wk36210270');
define('DB_CHARSET', 'utf8mb4');

// API Keys
define('TAVILY_API_KEY', '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');
define('OPENAI_API_KEY', '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');
define('OPENAI_MODEL', 'gpt-4o-mini');

// SendGrid (usa la misma config de TERRApp)
define('SENDGRID_API_KEY', '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');
define('SENDGRID_FROM_EMAIL', 'terrapp@verumax.com');
define('SENDGRID_FROM_NAME', 'TERRApp Blog');

// URLs
define('BLOG_URL', 'https://terrapp.verumax.com/blog/');
define('BLOG_ADMIN_URL', 'https://terrapp.verumax.com/blog/admin/');
define('LANDING_URL', 'https://terrapp.verumax.com/landing/');

// Email del admin para notificaciones
define('ADMIN_EMAIL', 'xagustinijms@gmail.com');

// Ratio regional objetivo (sudamerica:internacional)
define('RATIO_REGIONAL_OBJETIVO', 3.5);

// Temas de búsqueda para Tavily
define('SEARCH_TOPICS', [
    // Sudamérica específico - Español
    'huertos urbanos argentina noticias',
    'agricultura urbana buenos aires',
    'huerta balcón chile santiago',
    'cultivo urbano colombia bogota medellín',
    'huertos comunitarios perú lima',
    'agricultura urbana ecuador quito guayaquil',
    'huertos urbanos uruguay montevideo',
    'agricultura familiar paraguay',
    'cultivos urbanos bolivia la paz',
    'huertos venezuela caracas',

    // Brasil
    'horta urbana brasil notícias',
    'agricultura urbana são paulo',
    'cultivo em apartamento brasil',
    'horta comunitária rio de janeiro',

    // General en español
    'huertos urbanos balcón terraza',
    'agricultura urbana vertical',
    'cultivo hidropónico casero',
    'compostaje urbano doméstico',
    'plantas aromáticas balcón',
    'permacultura urbana ciudad',
    'huerto ecológico orgánico',
    'semillas criollas nativas',

    // General en inglés (traerá internacionales)
    'urban farming news 2026',
    'vertical gardening innovation',
    'balcony vegetables growing',
    'rooftop garden city',
    'sustainable urban agriculture',
    'community garden project',
    'indoor farming technology',
    'home hydroponics system'
]);

// Dominios de Sudamérica para detectar región
define('DOMINIOS_SUDAMERICA', [
    '.ar',  // Argentina
    '.br',  // Brasil
    '.cl',  // Chile
    '.co',  // Colombia
    '.pe',  // Perú
    '.ec',  // Ecuador
    '.uy',  // Uruguay
    '.py',  // Paraguay
    '.bo',  // Bolivia
    '.ve',  // Venezuela
    '.gf',  // Guayana Francesa
    '.gy',  // Guyana
    '.sr'   // Surinam
]);

// Categorías válidas
define('CATEGORIAS_VALIDAS', [
    'huertos-urbanos',
    'compostaje',
    'riego',
    'plantas',
    'tecnologia',
    'recetas',
    'comunidad',
    'noticias'
]);

// Modo debug
define('DEBUG_MODE', false);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');
