<?php
/**
 * TERRApp Blog - API para cambiar estado de artículo
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
verificarAccesoAPI();

require_once __DIR__ . '/../includes/functions.php';

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $estado = $input['estado'] ?? '';
    $saltearCriterio = isset($input['saltear_criterio']) ? (bool)$input['saltear_criterio'] : false;

    // Validar
    if ($id <= 0) {
        throw new Exception('ID de artículo inválido');
    }

    $estadosValidos = ['borrador', 'publicado', 'rechazado', 'programado'];
    if (!in_array($estado, $estadosValidos)) {
        throw new Exception('Estado inválido');
    }

    // Cambiar estado
    $resultado = cambiarEstadoArticulo($id, $estado, $saltearCriterio);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "Estado cambiado a '{$estado}'"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('No se pudo cambiar el estado');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
