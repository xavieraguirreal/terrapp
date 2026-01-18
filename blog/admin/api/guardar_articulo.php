<?php
/**
 * TERRApp Blog - API para guardar/actualizar artículo
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID de artículo inválido');
    }

    $datos = [
        'titulo' => $input['titulo'] ?? '',
        'contenido' => $input['contenido'] ?? '',
        'opinion_editorial' => $input['opinion_editorial'] ?? '',
        'tips' => $input['tips'] ?? [],
        'categoria' => $input['categoria'] ?? 'noticias',
        'tags' => $input['tags'] ?? []
    ];

    // Validar campos requeridos
    if (empty($datos['titulo']) || empty($datos['contenido'])) {
        throw new Exception('Título y contenido son requeridos');
    }

    $resultado = actualizarArticulo($id, $datos);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Artículo guardado correctamente'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('No se pudo guardar el artículo');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
