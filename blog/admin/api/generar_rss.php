<?php
/**
 * TERRApp Blog - API para generar RSS Feed
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    $resultado = generarRSSFeed();

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'RSS Feed generado correctamente'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Error al generar RSS Feed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
