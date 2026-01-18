<?php
/**
 * TERRApp Blog - API para registrar vista de artículo
 * Registra tanto vistas totales (infladas) como únicas (por IP)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $resultado = registrarVista($id);
    echo json_encode([
        'success' => true,
        'vista_unica' => $resultado['vista_unica']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
}
