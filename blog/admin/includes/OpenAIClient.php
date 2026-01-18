<?php
/**
 * TERRApp Blog - Cliente para la API de OpenAI
 * Adaptado para agricultura urbana y huertos
 */

class OpenAIClient {
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    // Temas válidos para filtrar relevancia
    private array $temasValidos = [
        'agricultura urbana',
        'huertos urbanos',
        'huerta',
        'cultivo en balcón',
        'cultivo en terraza',
        'hidroponía',
        'compostaje',
        'compost',
        'plantas aromáticas',
        'plantas medicinales',
        'hortalizas',
        'vegetales',
        'jardinería',
        'permacultura',
        'agroecología',
        'cultivo orgánico',
        'cultivo ecológico',
        'semillas',
        'riego',
        'fertilizante natural',
        'biopreparados',
        'huertos comunitarios',
        'agricultura vertical',
        'microgreens',
        'germinados'
    ];

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini') {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    // Variable para guardar última respuesta de validación (debug)
    public ?string $ultimaRespuestaValidacion = null;

    /**
     * Valida si una noticia es relevante para agricultura urbana
     * Usa búsqueda de palabras clave + OpenAI como fallback
     */
    public function validarRelevancia(string $titulo, string $contenido): bool {
        $texto = strtolower($titulo . ' ' . mb_substr($contenido, 0, 1000));

        // Palabras clave que indican relevancia
        $keywords = [
            'plant', 'garden', 'farm', 'crop', 'seed', 'vegetable', 'fruit',
            'grow', 'harvest', 'soil', 'compost', 'organic', 'cultivation',
            'agricultura', 'huerto', 'huerta', 'jardín', 'jardin', 'cultivo',
            'sembrar', 'cosecha', 'planta', 'semilla', 'vegetal', 'hortaliza',
            'compostaje', 'riego', 'fertiliz', 'urban farm', 'balcony', 'terrace',
            'hydroponic', 'hidropon', 'permaculture', 'permacultura', 'agroecol',
            'horticultur', 'greenhouse', 'invernadero', 'urban agricult',
            'vertical garden', 'rooftop', 'indoor plant', 'herb', 'aromatic'
        ];

        // Si contiene palabras clave, es relevante
        foreach ($keywords as $keyword) {
            if (strpos($texto, $keyword) !== false) {
                $this->ultimaRespuestaValidacion = "KEYWORD: $keyword";
                return true;
            }
        }

        // Palabras clave que indican NO relevante
        $excludeKeywords = [
            'movie', 'film', 'actor', 'actress', 'hollywood', 'netflix',
            'football', 'soccer', 'basketball', 'tennis', 'celebrity',
            'murder', 'crime', 'prison', 'court case', 'lawsuit'
        ];

        foreach ($excludeKeywords as $keyword) {
            if (strpos($texto, $keyword) !== false) {
                $this->ultimaRespuestaValidacion = "EXCLUDED: $keyword";
                return false;
            }
        }

        // Si no hay keywords claros, aceptar (ser inclusivo)
        $this->ultimaRespuestaValidacion = "NO_KEYWORD_FOUND_BUT_ACCEPTED";
        return true;
    }

    /**
     * Detecta y valida la región de origen de la noticia
     */
    public function detectarRegionYPais(string $url, string $contenido): array {
        // Primero intentar por dominio
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $dominiosSudamerica = DOMINIOS_SUDAMERICA ?? ['.ar', '.br', '.cl', '.co', '.pe', '.ec', '.uy', '.py', '.bo', '.ve'];

        $regionDetectada = 'internacional';
        $paisDetectado = null;

        foreach ($dominiosSudamerica as $tld) {
            if (str_ends_with($host, $tld)) {
                $regionDetectada = 'sudamerica';
                $paisDetectado = $this->tldAPais($tld);
                break;
            }
        }

        // Si no se detectó por dominio, usar IA para validar
        if ($regionDetectada === 'internacional' && !empty($contenido)) {
            $prompt = "Analiza este contenido y determina si la noticia es de SUDAMÉRICA o INTERNACIONAL.

CONTENIDO (primeros 500 chars):
" . mb_substr($contenido, 0, 500) . "

Responde en formato JSON:
{\"region\": \"sudamerica\" o \"internacional\", \"pais\": \"nombre del país o null\"}

Solo responde el JSON, nada más.";

            $response = $this->chatSimple($prompt, 100);
            $json = $this->extractJson($response);

            if ($json) {
                $regionDetectada = $json['region'] ?? 'internacional';
                $paisDetectado = $json['pais'] ?? null;
            }
        }

        return [
            'region' => $regionDetectada,
            'pais' => $paisDetectado
        ];
    }

    /**
     * Convierte TLD a nombre de país
     */
    private function tldAPais(string $tld): string {
        $mapa = [
            '.ar' => 'Argentina',
            '.br' => 'Brasil',
            '.cl' => 'Chile',
            '.co' => 'Colombia',
            '.pe' => 'Perú',
            '.ec' => 'Ecuador',
            '.uy' => 'Uruguay',
            '.py' => 'Paraguay',
            '.bo' => 'Bolivia',
            '.ve' => 'Venezuela',
            '.gf' => 'Guayana Francesa',
            '.gy' => 'Guyana',
            '.sr' => 'Surinam'
        ];
        return $mapa[$tld] ?? 'Sudamérica';
    }

    /**
     * Genera un artículo a partir del contenido original
     * Incluye opinión editorial y tips opcionales
     */
    public function generarArticulo(string $contenidoOriginal, string $fuenteNombre, string $fuenteUrl): array {
        $systemPrompt = <<<PROMPT
Eres un editor de contenidos para TERRApp, una app de agricultura urbana para Sudamérica.

REGLAS DE REDACCIÓN:
1. TÍTULO: Mantené fidelidad al título original. Máximo 100 caracteres. Atractivo y claro.

2. CONTENIDO: Mantené el sentido original de la noticia. No inventes datos.
   Reescribí solo lo necesario para fluidez, sin cambiar hechos.
   Entre 200-400 palabras. Usa párrafos cortos.

3. OPINIÓN EDITORIAL: Analizá desde la perspectiva de la agricultura urbana
   y huertos en ciudades. ¿Cómo afecta o beneficia a huerteros urbanos?
   2-3 párrafos máximo.

4. TIPS (opcional): Solo si la noticia permite extraer consejos prácticos
   aplicables en huertos urbanos, balcones o espacios reducidos.
   Máximo 3 tips concretos. Array vacío si no aplica.

5. CATEGORÍA: Asigná UNA categoría de esta lista:
   - huertos-urbanos (cultivo en ciudad, balcones, terrazas)
   - compostaje (compost, residuos orgánicos, vermicompost)
   - riego (sistemas de riego, ahorro de agua)
   - plantas (aromáticas, medicinales, hortalizas específicas)
   - tecnologia (apps, sensores, innovación)
   - recetas (cocina con productos de huerta)
   - comunidad (huertos comunitarios, proyectos sociales)
   - noticias (actualidad, políticas, eventos)

6. TAGS: Generá 3-5 hashtags relevantes (sin el #).

7. IDIOMA: Español neutro formal latinoamericano. Sin regionalismos
   (no uses "vos/tú" específicos, ni modismos locales).

FORMATO JSON OBLIGATORIO:
{
    "titulo": "Título fiel al original",
    "contenido": "Noticia reescrita manteniendo fidelidad...",
    "opinion_editorial": "Desde TERRApp vemos que...",
    "tips": ["Tip 1...", "Tip 2..."],
    "categoria": "huertos-urbanos",
    "tags": ["huerto", "balcon", "cultivo"]
}

Responde SOLO con el JSON, sin texto adicional.
PROMPT;

        $userPrompt = <<<PROMPT
Fuente original: {$fuenteNombre}
URL: {$fuenteUrl}

Contenido a procesar:
{$contenidoOriginal}
PROMPT;

        $response = $this->chat($systemPrompt, $userPrompt);
        $json = $this->extractJson($response);

        if (!$json) {
            throw new Exception("No se pudo parsear la respuesta de OpenAI");
        }

        // Validar campos requeridos
        $requeridos = ['titulo', 'contenido', 'opinion_editorial'];
        foreach ($requeridos as $campo) {
            if (empty($json[$campo])) {
                throw new Exception("Falta el campo requerido: {$campo}");
            }
        }

        // Asegurar que tips sea array
        if (!isset($json['tips']) || !is_array($json['tips'])) {
            $json['tips'] = [];
        }

        // Validar categoría
        $categoriasValidas = CATEGORIAS_VALIDAS ?? ['huertos-urbanos', 'compostaje', 'riego', 'plantas', 'tecnologia', 'recetas', 'comunidad', 'noticias'];
        if (empty($json['categoria']) || !in_array($json['categoria'], $categoriasValidas)) {
            $json['categoria'] = 'noticias';
        }

        // Asegurar que tags sea array
        if (!isset($json['tags']) || !is_array($json['tags'])) {
            $json['tags'] = [];
        }

        return $json;
    }

    /**
     * Chat simple para validaciones rápidas
     */
    private function chatSimple(string $prompt, int $maxTokens = 10): string {
        $url = $this->baseUrl . '/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => $maxTokens
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
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'NO';
    }

    /**
     * Realiza una llamada al endpoint de chat
     */
    public function chat(string $systemPrompt, string $userPrompt): string {
        $url = $this->baseUrl . '/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000
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
            throw new Exception("Error de API OpenAI: HTTP " . $httpCode . " - " . $response);
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Respuesta inesperada de OpenAI");
        }

        return $result['choices'][0]['message']['content'];
    }

    /**
     * Extrae JSON de una respuesta que puede tener texto adicional
     */
    private function extractJson(string $text): ?array {
        // Intentar parsear directamente
        $json = json_decode($text, true);
        if ($json !== null) {
            return $json;
        }

        // Buscar JSON en el texto
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }
}
