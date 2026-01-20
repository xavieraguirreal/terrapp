<?php
/**
 * TERRApp Blog - API para generar embedding de un artículo
 * POST: genera embedding para un artículo específico
 * GET con ?all=1: genera embeddings para todos los artículos sin embedding
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/EmbeddingsClient.php';

try {
    $pdo = getConnection();
    $embeddings = new EmbeddingsClient(OPENAI_API_KEY);

    // Modo batch: generar para todos los artículos sin embedding
    if (isset($_GET['all'])) {
        $resultado = generarEmbeddingsTodos($pdo, $embeddings);
        echo json_encode($resultado);
        exit;
    }

    // Modo individual: generar para un artículo específico
    $input = json_decode(file_get_contents('php://input'), true);
    $articuloId = $input['articulo_id'] ?? $_GET['id'] ?? null;

    if (!$articuloId) {
        throw new Exception("Se requiere articulo_id");
    }

    $resultado = generarEmbeddingArticulo($pdo, $embeddings, (int)$articuloId);

    echo json_encode([
        'success' => true,
        'articulo_id' => $articuloId,
        'tokens_usados' => $resultado['tokens'],
        'mensaje' => 'Embedding generado correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Genera embedding para un artículo específico
 */
function generarEmbeddingArticulo(PDO $pdo, EmbeddingsClient $embeddings, int $articuloId): array {
    // Obtener artículo
    $stmt = $pdo->prepare("SELECT * FROM blog_articulos WHERE id = ?");
    $stmt->execute([$articuloId]);
    $articulo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$articulo) {
        throw new Exception("Artículo no encontrado: $articuloId");
    }

    // Preparar texto para embedding
    $texto = EmbeddingsClient::prepareArticleText($articulo);
    $textoHash = EmbeddingsClient::textHash($texto);

    // Verificar si ya existe y no cambió
    $stmt = $pdo->prepare("SELECT texto_hash FROM blog_embeddings WHERE articulo_id = ?");
    $stmt->execute([$articuloId]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente && $existente['texto_hash'] === $textoHash) {
        return ['tokens' => 0, 'cached' => true];
    }

    // Generar embedding
    $resultado = $embeddings->generateEmbedding($texto);

    // Guardar o actualizar en BD
    $stmt = $pdo->prepare("
        INSERT INTO blog_embeddings (articulo_id, embedding, texto_hash, tokens_usados)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            embedding = VALUES(embedding),
            texto_hash = VALUES(texto_hash),
            tokens_usados = VALUES(tokens_usados),
            fecha_actualizacion = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $articuloId,
        json_encode($resultado['embedding']),
        $textoHash,
        $resultado['tokens']
    ]);

    return ['tokens' => $resultado['tokens'], 'cached' => false];
}

/**
 * Genera embeddings para todos los artículos publicados que no tienen
 */
function generarEmbeddingsTodos(PDO $pdo, EmbeddingsClient $embeddings): array {
    // Obtener artículos publicados sin embedding
    // La función generarEmbeddingArticulo ya verifica via texto_hash si cambió el contenido
    $stmt = $pdo->query("
        SELECT a.*
        FROM blog_articulos a
        LEFT JOIN blog_embeddings e ON a.id = e.articulo_id
        WHERE a.estado = 'publicado'
        AND e.id IS NULL
        ORDER BY a.fecha_publicacion DESC
        LIMIT 50
    ");

    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($articulos)) {
        return [
            'success' => true,
            'procesados' => 0,
            'mensaje' => 'Todos los artículos ya tienen embeddings actualizados'
        ];
    }

    $procesados = 0;
    $errores = 0;
    $tokensTotal = 0;

    foreach ($articulos as $articulo) {
        try {
            $resultado = generarEmbeddingArticulo($pdo, $embeddings, $articulo['id']);
            $tokensTotal += $resultado['tokens'];
            $procesados++;

            // Pequeña pausa para no saturar la API
            usleep(100000); // 0.1 segundos
        } catch (Exception $e) {
            $errores++;
            error_log("Error generando embedding para artículo {$articulo['id']}: " . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'procesados' => $procesados,
        'errores' => $errores,
        'tokens_total' => $tokensTotal,
        'costo_estimado' => '$' . number_format($tokensTotal * 0.00000002, 6)
    ];
}
