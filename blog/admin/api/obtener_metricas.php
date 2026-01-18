<?php
/**
 * TERRApp Blog - API para obtener métricas actualizadas de un artículo
 * Devuelve vistas, reacciones y shares directamente de la BD
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT
        vistas,
        reaccion_interesante,
        reaccion_encanta,
        reaccion_importante,
        (shares_whatsapp + shares_facebook + shares_twitter + shares_linkedin + shares_copy) as total_shares
    FROM blog_articulos
    WHERE id = ?
");
$stmt->execute([$id]);
$metricas = $stmt->fetch();

if ($metricas) {
    echo json_encode([
        'success' => true,
        'metricas' => [
            'vistas' => (int)$metricas['vistas'],
            'reaccion_interesante' => (int)$metricas['reaccion_interesante'],
            'reaccion_encanta' => (int)$metricas['reaccion_encanta'],
            'reaccion_importante' => (int)$metricas['reaccion_importante'],
            'total_shares' => (int)$metricas['total_shares']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Artículo no encontrado']);
}
