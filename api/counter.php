<?php
/**
 * TERRApp API - Endpoint del contador
 *
 * GET /api/counter.php
 *
 * Respuesta:
 * {
 *   "real": 150,
 *   "display": 823,
 *   "formatted": "823"
 * }
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

try {
    $pdo = getDB();

    $stmt = $pdo->query("SELECT * FROM v_subscriber_count");
    $data = $stmt->fetch();

    if (!$data) {
        // No hay datos, devolver ceros
        jsonResponse([
            'real' => 0,
            'display' => 0,
            'formatted' => '0'
        ]);
    }

    jsonResponse([
        'real' => (int)$data['total_real'],
        'display' => (int)$data['total_display'],
        'formatted' => $data['total_formatted']
    ]);

} catch (PDOException $e) {
    logError('Counter error', ['error' => $e->getMessage()]);
    jsonResponse(['error' => 'Error al obtener contador'], 500);
}
