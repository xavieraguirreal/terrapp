<?php
/**
 * TERRApp Blog - Cliente para Chat RAG con OpenAI
 * Genera respuestas basadas en el contenido del blog usando RAG
 */

class ChatRAGClient {
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini') {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Genera una respuesta basada en artículos relevantes
     * @param string $question Pregunta del usuario
     * @param array $articles Artículos relevantes con su contenido
     * @param array $history Historial de conversación (opcional)
     * @return array ['response' => string, 'sources' => array, 'tokens' => int]
     */
    public function generateResponse(string $question, array $articles, array $history = []): array {
        // Preparar contexto con los artículos
        $context = $this->prepareContext($articles);

        // Construir mensajes
        $messages = $this->buildMessages($question, $context, $history);

        // Llamar a la API
        $result = $this->callChatAPI($messages);

        // Extraer fuentes citadas
        $sources = $this->extractSources($articles);

        return [
            'response' => $result['content'],
            'sources' => $sources,
            'tokens' => $result['tokens']
        ];
    }

    /**
     * Prepara el contexto con los artículos relevantes
     */
    private function prepareContext(array $articles): string {
        $context = "";

        foreach ($articles as $index => $art) {
            $num = $index + 1;
            $context .= "--- ARTÍCULO {$num} ---\n";
            $context .= "Título: {$art['titulo']}\n";
            $context .= "Categoría: {$art['categoria']}\n";

            // Limitar contenido a ~1500 caracteres por artículo
            $contenido = strip_tags($art['contenido']);
            if (mb_strlen($contenido) > 1500) {
                $contenido = mb_substr($contenido, 0, 1500) . '...';
            }
            $context .= "Contenido: {$contenido}\n";

            // Incluir opinión editorial si existe
            if (!empty($art['opinion_editorial'])) {
                $opinion = strip_tags($art['opinion_editorial']);
                if (mb_strlen($opinion) > 500) {
                    $opinion = mb_substr($opinion, 0, 500) . '...';
                }
                $context .= "Opinión TERRApp: {$opinion}\n";
            }

            // Tips si existen
            if (!empty($art['tips'])) {
                $tips = is_array($art['tips']) ? $art['tips'] : json_decode($art['tips'], true);
                if ($tips) {
                    $context .= "Tips: " . implode('; ', array_slice($tips, 0, 3)) . "\n";
                }
            }

            $context .= "\n";
        }

        return $context;
    }

    /**
     * Construye los mensajes para la API de Chat
     */
    private function buildMessages(string $question, string $context, array $history): array {
        $systemPrompt = <<<PROMPT
Sos Terri, el asistente virtual de TERRApp especializado en agricultura urbana y huertos. Tu nombre viene de TERRApp y de "terra" (tierra en latín). Tu objetivo es ayudar a los usuarios con sus consultas sobre jardinería, huertos urbanos, compostaje y temas relacionados.

REGLAS IMPORTANTES:
1. Respondé ÚNICAMENTE basándote en la información de los artículos proporcionados
2. Si la pregunta no puede responderse con los artículos disponibles, decilo honestamente
3. Usá un tono amigable y accesible, como si hablaras con un vecino huertero
4. Incluí consejos prácticos cuando sea relevante
5. Si mencionás información de un artículo específico, indicá el número del artículo
6. Respondé en español latinoamericano neutro
7. Sé conciso pero completo (máximo 3-4 párrafos)
8. Si hay tips relevantes en los artículos, mencionálos
9. No te presentes en cada respuesta, solo si te preguntan quién sos

CONTEXTO DE ARTÍCULOS:
{$context}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Agregar historial si existe
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Agregar pregunta actual
        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }

    /**
     * Llama a la API de Chat de OpenAI
     */
    private function callChatAPI(array $messages): array {
        $url = $this->baseUrl . '/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 800
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error de cURL: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Error de API OpenAI Chat: HTTP " . $httpCode . " - " . $response);
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Respuesta inesperada de OpenAI Chat");
        }

        return [
            'content' => $result['choices'][0]['message']['content'],
            'tokens' => $result['usage']['total_tokens'] ?? 0
        ];
    }

    /**
     * Extrae información de fuentes para citar
     */
    private function extractSources(array $articles): array {
        return array_map(function($art) {
            return [
                'id' => $art['id'],
                'titulo' => $art['titulo'],
                'slug' => $art['slug'],
                'categoria' => $art['categoria']
            ];
        }, $articles);
    }
}
