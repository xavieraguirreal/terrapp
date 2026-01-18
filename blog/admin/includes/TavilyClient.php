<?php
/**
 * TERRApp Blog - Cliente para la API de Tavily
 * Documentación: https://docs.tavily.com/
 */

class TavilyClient {
    private string $apiKey;
    private string $baseUrl = 'https://api.tavily.com';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Busca noticias recientes sobre un tema
     * @param array $includeDomains Dominios específicos donde buscar (opcional)
     */
    public function search(string $query, int $maxResults = 5, array $includeDomains = []): array {
        $url = $this->baseUrl . '/search';

        $data = [
            'api_key' => $this->apiKey,
            'query' => $query,
            'search_depth' => 'advanced',
            'include_answer' => false,
            'include_raw_content' => true,
            'include_images' => true,
            'max_results' => $maxResults,
            'topic' => 'news'
        ];

        // Si hay dominios específicos, agregarlos
        if (!empty($includeDomains)) {
            $data['include_domains'] = $includeDomains;
        }

        $response = $this->makeRequest($url, $data);

        // Las imágenes vienen en un array separado
        $images = $response['images'] ?? [];
        $results = $response['results'] ?? [];

        // Asignar imagen a cada resultado si no la tiene
        foreach ($results as $i => &$result) {
            if (empty($result['image']) && isset($images[$i])) {
                $result['image'] = $images[$i];
            }
        }

        return $results;
    }

    /**
     * Busca noticias combinando búsqueda general + sitios preferidos
     * @param array $sitiosPreferidos Array de dominios preferidos
     * @return array Resultados combinados, sitios preferidos primero
     */
    public function searchWithPreferredSites(string $query, int $maxResults = 5, array $sitiosPreferidos = []): array {
        $resultados = [];
        $urlsVistas = [];

        // 1. Primero buscar en sitios preferidos (si hay)
        if (!empty($sitiosPreferidos)) {
            try {
                $resultadosPreferidos = $this->search($query, 3, $sitiosPreferidos);
                foreach ($resultadosPreferidos as $r) {
                    $r['_preferido'] = true; // Marcar como de sitio preferido
                    $resultados[] = $r;
                    $urlsVistas[$r['url']] = true;
                }
            } catch (Exception $e) {
                // Si falla, continuar con búsqueda general
                error_log("Error buscando en sitios preferidos: " . $e->getMessage());
            }
        }

        // 2. Búsqueda general (sin restricción de dominio)
        try {
            $resultadosGenerales = $this->search($query, $maxResults);
            foreach ($resultadosGenerales as $r) {
                // Evitar duplicados
                if (!isset($urlsVistas[$r['url']])) {
                    $r['_preferido'] = false;
                    $resultados[] = $r;
                    $urlsVistas[$r['url']] = true;
                }
            }
        } catch (Exception $e) {
            error_log("Error en búsqueda general: " . $e->getMessage());
        }

        return $resultados;
    }

    /**
     * Extrae el contenido completo de una URL
     */
    public function extract(string $url): ?array {
        $endpoint = $this->baseUrl . '/extract';

        $data = [
            'api_key' => $this->apiKey,
            'urls' => [$url]
        ];

        $response = $this->makeRequest($endpoint, $data);

        if (isset($response['results'][0])) {
            return $response['results'][0];
        }

        return null;
    }

    /**
     * Intenta obtener la imagen og:image de una URL (fallback)
     */
    public function obtenerImagenOG(string $url): ?string {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; TERRAppBot/1.0)'
                ]
            ]);

            $html = @file_get_contents($url, false, $context);
            if (!$html) return null;

            // Buscar og:image
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $matches)) {
                return $matches[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $html, $matches)) {
                return $matches[1];
            }

            // Buscar twitter:image como fallback
            if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Realiza una petición POST a la API
     */
    private function makeRequest(string $url, array $data): array {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
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
            throw new Exception("Error de API Tavily: HTTP " . $httpCode . " - " . $response);
        }

        return json_decode($response, true) ?? [];
    }
}
