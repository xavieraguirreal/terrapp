<?php
/**
 * TERRApp Blog - API de Búsqueda Semántica
 * Busca artículos por similitud semántica usando embeddings
 *
 * GET /api/buscar_semantico.php?q=plantas+para+balcon&limit=10
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config/config.php';
require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/../admin/includes/EmbeddingsClient.php';

try {
    $query = trim($_GET['q'] ?? '');
    $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
    $threshold = (float)($_GET['threshold'] ?? 0.3); // Similitud mínima

    if (empty($query)) {
        throw new Exception("Se requiere parámetro 'q' con la búsqueda");
    }

    if (mb_strlen($query) < 2) {
        throw new Exception("La búsqueda debe tener al menos 2 caracteres");
    }

    $pdo = getConnection();

    // Verificar cache de búsqueda
    $queryHash = hash('sha256', strtolower($query));
    $cached = buscarEnCache($pdo, $queryHash);

    if ($cached) {
        echo json_encode([
            'success' => true,
            'query' => $query,
            'resultados' => $cached,
            'total' => count($cached),
            'cached' => true
        ]);
        exit;
    }

    // Generar embedding de la búsqueda
    $embeddings = new EmbeddingsClient(OPENAI_API_KEY);
    $queryEmbedding = $embeddings->generateEmbedding($query);

    // Obtener todos los embeddings de artículos publicados
    $stmt = $pdo->query("
        SELECT e.articulo_id, e.embedding,
               a.titulo, a.slug, a.categoria, a.imagen_url,
               a.fecha_publicacion, a.tiempo_lectura, a.vistas
        FROM blog_embeddings e
        INNER JOIN blog_articulos a ON e.articulo_id = a.id
        WHERE a.estado = 'publicado'
    ");

    $articulosConEmbedding = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($articulosConEmbedding)) {
        echo json_encode([
            'success' => true,
            'query' => $query,
            'resultados' => [],
            'total' => 0,
            'mensaje' => 'No hay artículos con embeddings. Ejecutá /admin/api/generar_embedding.php?all=1'
        ]);
        exit;
    }

    // Calcular similitud con cada artículo
    $resultados = [];
    foreach ($articulosConEmbedding as $art) {
        $artEmbedding = json_decode($art['embedding'], true);
        $similitud = EmbeddingsClient::cosineSimilarity($queryEmbedding['embedding'], $artEmbedding);

        if ($similitud >= $threshold) {
            $resultados[] = [
                'id' => (int)$art['articulo_id'],
                'titulo' => $art['titulo'],
                'slug' => $art['slug'],
                'categoria' => $art['categoria'],
                'imagen_url' => $art['imagen_url'],
                'fecha_publicacion' => $art['fecha_publicacion'],
                'tiempo_lectura' => (int)$art['tiempo_lectura'],
                'vistas' => (int)$art['vistas'],
                'similitud' => round($similitud, 4),
                'similitud_porcentaje' => round($similitud * 100, 1) . '%'
            ];
        }
    }

    // Ordenar por similitud descendente
    usort($resultados, fn($a, $b) => $b['similitud'] <=> $a['similitud']);

    // Limitar resultados
    $resultados = array_slice($resultados, 0, $limit);

    // Guardar en cache
    guardarEnCache($pdo, $queryHash, $query, $queryEmbedding['embedding'], $resultados);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'resultados' => $resultados,
        'total' => count($resultados),
        'tokens_usados' => $queryEmbedding['tokens'],
        'cached' => false
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Busca resultados en cache
 */
function buscarEnCache(PDO $pdo, string $queryHash): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT resultados
            FROM blog_search_cache
            WHERE query_hash = ? AND fecha_expiracion > NOW()
        ");
        $stmt->execute([$queryHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Incrementar hits
            $pdo->prepare("UPDATE blog_search_cache SET hits = hits + 1 WHERE query_hash = ?")
                ->execute([$queryHash]);

            return json_decode($row['resultados'], true);
        }
    } catch (Exception $e) {
        // Si falla el cache, continuar sin él
    }

    return null;
}

/**
 * Guarda resultados en cache
 */
function guardarEnCache(PDO $pdo, string $queryHash, string $queryText, array $embedding, array $resultados): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO blog_search_cache (query_hash, query_text, embedding, resultados)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                resultados = VALUES(resultados),
                hits = hits + 1,
                fecha_expiracion = NOW() + INTERVAL 24 HOUR
        ");
        $stmt->execute([
            $queryHash,
            mb_substr($queryText, 0, 500),
            json_encode($embedding),
            json_encode($resultados)
        ]);
    } catch (Exception $e) {
        // Si falla el cache, no es crítico
        error_log("Error guardando cache de búsqueda: " . $e->getMessage());
    }
}
