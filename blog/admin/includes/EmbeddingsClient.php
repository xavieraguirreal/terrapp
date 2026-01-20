<?php
/**
 * TERRApp Blog - Cliente para OpenAI Embeddings API
 * Genera y gestiona embeddings para búsqueda semántica
 */

class EmbeddingsClient {
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';
    private int $dimensions;

    public function __construct(string $apiKey, string $model = 'text-embedding-3-small') {
        $this->apiKey = $apiKey;
        $this->model = $model;
        // text-embedding-3-small tiene 1536 dimensiones por defecto
        $this->dimensions = 1536;
    }

    /**
     * Genera embedding para un texto
     * @param string $text Texto a convertir en embedding
     * @return array ['embedding' => [...], 'tokens' => int]
     */
    public function generateEmbedding(string $text): array {
        // Limpiar y truncar texto (máximo ~8000 tokens ≈ 6000 palabras)
        $text = $this->prepareText($text);

        $url = $this->baseUrl . '/embeddings';

        $data = [
            'model' => $this->model,
            'input' => $text,
            'encoding_format' => 'float'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error de cURL: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Error de API OpenAI Embeddings: HTTP " . $httpCode . " - " . $response);
        }

        $result = json_decode($response, true);

        if (!isset($result['data'][0]['embedding'])) {
            throw new Exception("Respuesta inesperada de OpenAI Embeddings");
        }

        return [
            'embedding' => $result['data'][0]['embedding'],
            'tokens' => $result['usage']['total_tokens'] ?? 0
        ];
    }

    /**
     * Genera embeddings para múltiples textos en una sola llamada (más eficiente)
     * @param array $texts Array de textos
     * @return array Array de embeddings en el mismo orden
     */
    public function generateBatchEmbeddings(array $texts): array {
        $preparedTexts = array_map([$this, 'prepareText'], $texts);

        $url = $this->baseUrl . '/embeddings';

        $data = [
            'model' => $this->model,
            'input' => $preparedTexts,
            'encoding_format' => 'float'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Error de API OpenAI Embeddings: HTTP " . $httpCode);
        }

        $result = json_decode($response, true);

        $embeddings = [];
        $totalTokens = $result['usage']['total_tokens'] ?? 0;

        foreach ($result['data'] as $item) {
            $embeddings[] = [
                'embedding' => $item['embedding'],
                'index' => $item['index']
            ];
        }

        // Ordenar por índice original
        usort($embeddings, fn($a, $b) => $a['index'] - $b['index']);

        return [
            'embeddings' => array_column($embeddings, 'embedding'),
            'tokens' => $totalTokens
        ];
    }

    /**
     * Calcula la similitud de coseno entre dos embeddings
     * @return float Valor entre -1 y 1 (1 = idénticos)
     */
    public static function cosineSimilarity(array $a, array $b): float {
        if (count($a) !== count($b)) {
            throw new Exception("Los embeddings deben tener la misma dimensión");
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Prepara el texto para generar embedding
     */
    private function prepareText(string $text): string {
        // Quitar HTML
        $text = strip_tags($text);

        // Normalizar espacios
        $text = preg_replace('/\s+/', ' ', $text);

        // Truncar a ~6000 palabras (para estar seguros con el límite de tokens)
        $words = explode(' ', $text);
        if (count($words) > 6000) {
            $words = array_slice($words, 0, 6000);
            $text = implode(' ', $words);
        }

        return trim($text);
    }

    /**
     * Genera el texto combinado de un artículo para embedding
     * Incluye título, contenido, tags y categoría para mejor contexto
     */
    public static function prepareArticleText(array $articulo): string {
        $parts = [];

        // Título (peso alto - se repite)
        if (!empty($articulo['titulo'])) {
            $parts[] = "Título: " . $articulo['titulo'];
        }

        // Categoría
        if (!empty($articulo['categoria'])) {
            $parts[] = "Categoría: " . $articulo['categoria'];
        }

        // Tags
        if (!empty($articulo['tags'])) {
            $tags = is_array($articulo['tags']) ? $articulo['tags'] : json_decode($articulo['tags'], true);
            if ($tags) {
                $parts[] = "Tags: " . implode(', ', $tags);
            }
        }

        // Contenido
        if (!empty($articulo['contenido'])) {
            $parts[] = "Contenido: " . $articulo['contenido'];
        }

        // Opinión editorial (si existe)
        if (!empty($articulo['opinion_editorial'])) {
            $parts[] = "Opinión: " . $articulo['opinion_editorial'];
        }

        return implode("\n\n", $parts);
    }

    /**
     * Genera hash del texto para detectar si cambió
     */
    public static function textHash(string $text): string {
        return hash('sha256', $text);
    }
}
