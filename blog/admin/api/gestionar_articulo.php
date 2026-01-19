<?php
/**
 * TERRApp Blog - API para gestionar artículos (eliminar, despublicar)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

if (!verificarAccesoAPI()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$accion = $input['accion'] ?? '';

if (!$id || !$accion) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit;
}

try {
    switch ($accion) {
        case 'eliminar':
            $result = eliminarArticulo($id);
            $mensaje = $result ? 'Artículo eliminado correctamente' : 'Error al eliminar';
            break;

        case 'despublicar':
            $result = despublicarArticulo($id);
            $mensaje = $result ? 'Artículo despublicado (volvió a borradores)' : 'Error al despublicar';
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            exit;
    }

    echo json_encode([
        'success' => $result,
        'message' => $mensaje
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
