<?php
/**
 * TERRApp Blog - API para exportar artículos a JSON
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
verificarAccesoAPI();

require_once __DIR__ . '/../includes/functions.php';

try {
    $resultado = exportarArticulosJSON();

    if ($resultado) {
        // También generar RSS
        generarRSSFeed();

        echo json_encode([
            'success' => true,
            'message' => 'Artículos exportados a JSON y RSS generado'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Error al exportar artículos');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
