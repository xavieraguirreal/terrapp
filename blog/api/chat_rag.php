<?php
/**
 * TERRApp Blog - API de Chat RAG
 * Responde preguntas basándose en el contenido del blog
 *
 * POST /api/chat_rag.php
 * Body: { "question": "¿Cómo hago compost?", "history": [] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../admin/config/config.php';
require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/../admin/includes/EmbeddingsClient.php';
require_once __DIR__ . '/../admin/includes/ChatRAGClient.php';

try {
    // Solo aceptar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido. Usar POST.");
    }

    // Parsear input
    $input = json_decode(file_get_contents('php://input'), true);
    $question = trim($input['question'] ?? '');
    $history = $input['history'] ?? [];

    // Validar pregunta
    if (empty($question)) {
        throw new Exception("Se requiere el campo 'question'");
    }

    if (mb_strlen($question) < 3) {
        throw new Exception("La pregunta debe tener al menos 3 caracteres");
    }

    if (mb_strlen($question) > 500) {
        throw new Exception("La pregunta es demasiado larga (máximo 500 caracteres)");
    }

    // Validar historial (máximo 10 mensajes para no exceder contexto)
    if (count($history) > 10) {
        $history = array_slice($history, -10);
    }

    $pdo = getConnection();

    // 1. Buscar artículos relevantes usando embeddings
    $embeddings = new EmbeddingsClient(OPENAI_API_KEY);
    $queryEmbedding = $embeddings->generateEmbedding($question);

    // Obtener artículos con embeddings
    $stmt = $pdo->query("
        SELECT e.articulo_id, e.embedding,
               a.id, a.titulo, a.slug, a.categoria, a.contenido,
               a.opinion_editorial, a.tips
        FROM blog_embeddings e
        INNER JOIN blog_articulos a ON e.articulo_id = a.id
        WHERE a.estado = 'publicado'
    ");

    $articlesWithEmbeddings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($articlesWithEmbeddings)) {
        throw new Exception("No hay artículos disponibles para consultar");
    }

    // 2. Calcular similitud y obtener los más relevantes
    $scored = [];
    foreach ($articlesWithEmbeddings as $art) {
        $artEmbedding = json_decode($art['embedding'], true);
        $similarity = EmbeddingsClient::cosineSimilarity($queryEmbedding['embedding'], $artEmbedding);

        // Solo considerar artículos con similitud > 0.25
        if ($similarity >= 0.25) {
            $scored[] = [
                'id' => (int)$art['articulo_id'],
                'titulo' => $art['titulo'],
                'slug' => $art['slug'],
                'categoria' => $art['categoria'],
                'contenido' => $art['contenido'],
                'opinion_editorial' => $art['opinion_editorial'],
                'tips' => $art['tips'],
                'similitud' => $similarity
            ];
        }
    }

    // Ordenar por similitud
    usort($scored, fn($a, $b) => $b['similitud'] <=> $a['similitud']);

    // Tomar los 3 más relevantes
    $relevantArticles = array_slice($scored, 0, 3);

    // 3. Generar respuesta con RAG
    $chat = new ChatRAGClient(OPENAI_API_KEY, 'gpt-4o-mini');

    if (empty($relevantArticles)) {
        // No hay artículos relevantes - responder honestamente
        $response = [
            'success' => true,
            'response' => "No encontré artículos en el blog que respondan directamente a tu pregunta. ¿Podrías reformularla o preguntar sobre temas como huertos urbanos, compostaje, riego o plantas?",
            'sources' => [],
            'tokens_embedding' => $queryEmbedding['tokens'],
            'tokens_chat' => 0
        ];
    } else {
        $result = $chat->generateResponse($question, $relevantArticles, $history);

        $response = [
            'success' => true,
            'response' => $result['response'],
            'sources' => $result['sources'],
            'tokens_embedding' => $queryEmbedding['tokens'],
            'tokens_chat' => $result['tokens']
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
