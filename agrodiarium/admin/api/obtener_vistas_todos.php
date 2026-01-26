<?php
/**
 * AGRODiarium - API para obtener vistas de todos los artículos
 * Devuelve las vistas actualizadas desde la BD para mostrar en la portada
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getConnection();

    // Obtener vistas de todos los artículos publicados
    $stmt = $pdo->query("
        SELECT id, vistas, reaccion_interesante, reaccion_encanta, reaccion_importante, reaccion_noconvence
        FROM blog_articulos
        WHERE estado = 'publicado'
    ");

    $vistas = [];
    while ($row = $stmt->fetch()) {
        $vistas[$row['id']] = [
            'vistas' => (int)$row['vistas'],
            'reaccion_interesante' => (int)$row['reaccion_interesante'],
            'reaccion_encanta' => (int)$row['reaccion_encanta'],
            'reaccion_importante' => (int)$row['reaccion_importante'],
            'reaccion_noconvence' => (int)$row['reaccion_noconvence']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $vistas
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener vistas'
    ]);
}
