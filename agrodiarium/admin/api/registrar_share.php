<?php
/**
 * TERRApp Blog - API para registrar compartido de artículo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$red = $_GET['red'] ?? '';

$redesValidas = ['whatsapp', 'facebook', 'twitter', 'linkedin', 'copy'];

if ($id > 0 && in_array($red, $redesValidas)) {
    $resultado = registrarShare($id, $red);
    echo json_encode(['success' => $resultado]);
} else {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
}
