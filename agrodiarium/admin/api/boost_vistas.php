<?php
/**
 * AGRODiarium - API para boost global de vistas
 * Incrementa las vistas de TODOS los artículos cuando se abre cualquier página
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

try {
    $resultado = boostVistasGlobal();
    echo json_encode([
        'success' => true,
        'data' => $resultado
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno'
    ]);
}
