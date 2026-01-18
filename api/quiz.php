<?php
/**
 * TERRApp API - Endpoint de Quiz
 *
 * POST /api/quiz.php
 * Body: {
 *   "answers": { "1": "yard", "2": "none", "3": "vegetables", "4": "little" },
 *   "profile": "beginner_large",
 *   "lang": "es_AR",
 *   "session_id": "abc123" (opcional, para tracking)
 * }
 *
 * Guarda el resultado del quiz (anónimo o vinculado si hay email después)
 */

define('TERRAPP_API', true);
require_once __DIR__ . '/config.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['error' => 'Datos inválidos'], 400);
}

$answers = $input['answers'] ?? [];
$profile = trim($input['profile'] ?? '');
$lang = trim($input['lang'] ?? 'es_AR');
$sessionId = trim($input['session_id'] ?? '');

// Validar que tenemos las respuestas necesarias
if (empty($answers) || empty($profile)) {
    jsonResponse(['error' => 'Datos del quiz incompletos'], 400);
}

// Validar perfil
$validProfiles = ['beginner_small', 'beginner_large', 'intermediate', 'experienced'];
if (!in_array($profile, $validProfiles)) {
    jsonResponse(['error' => 'Perfil inválido'], 400);
}

// Sanitizar respuestas
$espacio = $answers['1'] ?? $answers[1] ?? '';
$experiencia = $answers['2'] ?? $answers[2] ?? '';
$cultivos = $answers['3'] ?? $answers[3] ?? '';
$tiempo = $answers['4'] ?? $answers[4] ?? '';

// Validar respuestas
$validEspacio = ['yard', 'balcony', 'indoor'];
$validExperiencia = ['none', 'some', 'experienced'];
$validCultivos = ['vegetables', 'herbs', 'mixed'];
$validTiempo = ['little', 'moderate', 'plenty'];

if (!in_array($espacio, $validEspacio) ||
    !in_array($experiencia, $validExperiencia) ||
    !in_array($cultivos, $validCultivos) ||
    !in_array($tiempo, $validTiempo)) {
    jsonResponse(['error' => 'Respuestas inválidas'], 400);
}

// Generar session_id si no viene
if (empty($sessionId)) {
    $sessionId = bin2hex(random_bytes(16));
}

// Sanitizar lang
$lang = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang) ? $lang : 'es_AR';

try {
    $pdo = getDB();

    // Insertar resultado del quiz
    $stmt = $pdo->prepare("
        INSERT INTO quiz_results (
            session_id, perfil, respuesta_espacio, respuesta_experiencia,
            respuesta_cultivos, respuesta_tiempo, pais_codigo, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $sessionId,
        $profile,
        $espacio,
        $experiencia,
        $cultivos,
        $tiempo,
        $lang,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $quizId = $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'quiz_id' => $quizId,
        'session_id' => $sessionId,
        'profile' => $profile
    ]);

} catch (PDOException $e) {
    logError('Quiz save error', ['error' => $e->getMessage()]);
    jsonResponse(['error' => 'Error al guardar el quiz'], 500);
}
