<?php
/**
 * TERRApp API - Endpoint de datos (FAQs, testimonios, progreso)
 *
 * GET /api/data.php?type=faqs&lang=es
 * GET /api/data.php?type=testimonials&lang=es
 * GET /api/data.php?type=progress
 * GET /api/data.php?type=all&lang=es
 */

define('TERRAPP_API', true);
require_once __DIR__ . '/config.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$type = $_GET['type'] ?? 'all';
$lang = $_GET['lang'] ?? 'es';

// Normalizar idioma
$lang = substr($lang, 0, 2);
if (!in_array($lang, ['es', 'pt', 'en', 'fr', 'nl'])) {
    $lang = 'es';
}

try {
    $pdo = getDB();
    $response = [];

    // FAQs
    if ($type === 'faqs' || $type === 'all') {
        $response['faqs'] = getFaqs($pdo, $lang);
    }

    // Testimoniales
    if ($type === 'testimonials' || $type === 'all') {
        $response['testimonials'] = getTestimonials($pdo, $lang);
    }

    // Progreso
    if ($type === 'progress' || $type === 'all') {
        $response['progress'] = getProgress($pdo);
    }

    // Contador
    if ($type === 'counter' || $type === 'all') {
        $stmt = $pdo->query("SELECT * FROM v_subscriber_count");
        $data = $stmt->fetch();
        $response['counter'] = [
            'real' => (int)($data['total_real'] ?? 0),
            'display' => (int)($data['total_display'] ?? 0),
            'formatted' => $data['total_formatted'] ?? '0'
        ];
    }

    jsonResponse($response);

} catch (PDOException $e) {
    logError('Data error', ['error' => $e->getMessage()]);
    jsonResponse(['error' => 'Error al obtener datos'], 500);
}

/**
 * Obtiene FAQs en el idioma especificado
 */
function getFaqs(PDO $pdo, string $lang): array {
    $stmt = $pdo->query("
        SELECT id, orden,
               pregunta_es, respuesta_es,
               pregunta_pt, respuesta_pt,
               pregunta_en, respuesta_en,
               pregunta_fr, respuesta_fr,
               pregunta_nl, respuesta_nl
        FROM faqs
        WHERE activo = 1
        ORDER BY orden ASC
    ");

    $faqs = [];
    while ($row = $stmt->fetch()) {
        // Obtener pregunta/respuesta en el idioma solicitado, fallback a español
        $pregunta = $row["pregunta_{$lang}"] ?: $row['pregunta_es'];
        $respuesta = $row["respuesta_{$lang}"] ?: $row['respuesta_es'];

        $faqs[] = [
            'id' => (int)$row['id'],
            'pregunta' => $pregunta,
            'respuesta' => $respuesta
        ];
    }

    return $faqs;
}

/**
 * Obtiene testimonios en el idioma especificado
 */
function getTestimonials(PDO $pdo, string $lang): array {
    $stmt = $pdo->query("
        SELECT id, nombre, rol, ubicacion, avatar_url, destacado, orden,
               texto_es, texto_pt, texto_en
        FROM testimonials
        WHERE activo = 1
        ORDER BY destacado DESC, orden ASC
    ");

    $testimonials = [];
    while ($row = $stmt->fetch()) {
        // Obtener texto en el idioma solicitado, fallback a español
        $texto = $row["texto_{$lang}"] ?: $row['texto_es'];

        $testimonials[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'rol' => $row['rol'],
            'ubicacion' => $row['ubicacion'],
            'texto' => $texto,
            'avatar' => $row['avatar_url'],
            'destacado' => (bool)$row['destacado']
        ];
    }

    return $testimonials;
}

/**
 * Obtiene información de progreso
 */
function getProgress(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT config_key, config_value
        FROM config
        WHERE config_key IN ('progress_percent', 'progress_phase', 'launch_date')
    ");

    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['config_key']] = $row['config_value'];
    }

    return [
        'percent' => (int)($config['progress_percent'] ?? 35),
        'phase' => $config['progress_phase'] ?? 'Diseño y Planificación',
        'launch_date' => $config['launch_date'] ?? '2026-06-01'
    ];
}
