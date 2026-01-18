<?php
/**
 * TERRApp Blog - API para registrar reacción a artículo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = $_GET['tipo'] ?? '';

$tiposValidos = ['interesante', 'encanta', 'importante'];

if ($id > 0 && in_array($tipo, $tiposValidos)) {
    $resultado = registrarReaccion($id, $tipo);
    echo json_encode(['success' => $resultado]);
} else {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
}
