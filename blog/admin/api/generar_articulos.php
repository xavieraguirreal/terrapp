<?php
/**
 * TERRApp Blog - API para generar artículos
 * Busca noticias con Tavily, valida con OpenAI y guarda como borrador
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
verificarAccesoAPI();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TavilyClient.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';
require_once __DIR__ . '/../includes/EmailNotifier.php';

try {
    $tavily = new TavilyClient(TAVILY_API_KEY);
    $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);
    $emailNotifier = new EmailNotifier();

    $articulosGenerados = 0;
    $errores = [];

    // Verificar si hay pendientes en cache
    $pendientesDisponibles = contarPendientes();

    if ($pendientesDisponibles < 5) {
        // Buscar nuevas noticias con Tavily
        $topics = SEARCH_TOPICS;
        shuffle($topics);
        $topicsSeleccionados = array_slice($topics, 0, 3);

        $todasLasCandidatas = [];

        foreach ($topicsSeleccionados as $topic) {
            try {
                $resultados = $tavily->search($topic, 5);
                foreach ($resultados as $r) {
                    $todasLasCandidatas[] = $r;
                }
            } catch (Exception $e) {
                $errores[] = "Error buscando '{$topic}': " . $e->getMessage();
            }
        }

        // Guardar candidatas en cache
        if (!empty($todasLasCandidatas)) {
            $guardadas = guardarCandidatasPendientes($todasLasCandidatas);
            $pendientesDisponibles = contarPendientes();
        }
    }

    // Procesar hasta 3 pendientes
    $procesadas = 0;
    $maxProcesar = 3;

    while ($procesadas < $maxProcesar) {
        $pendiente = obtenerPendiente();

        if (!$pendiente) {
            break;
        }

        try {
            // Verificar URL no procesada
            if (urlYaProcesada($pendiente['url'])) {
                marcarPendienteUsada($pendiente['id']);
                continue;
            }

            // Verificar título no duplicado
            if (!empty($pendiente['titulo']) && tituloEsSimilar($pendiente['titulo'])) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                continue;
            }

            // Obtener contenido completo si es necesario
            $contenido = $pendiente['contenido'];
            if (empty($contenido) || strlen($contenido) < 500) {
                try {
                    $extracted = $tavily->extract($pendiente['url']);
                    if ($extracted && !empty($extracted['raw_content'])) {
                        $contenido = $extracted['raw_content'];
                    }
                } catch (Exception $e) {
                    // Usar contenido parcial
                }
            }

            if (empty($contenido) || strlen($contenido) < 200) {
                marcarPendienteUsada($pendiente['id']);
                continue;
            }

            // Validar relevancia con OpenAI
            $titulo = $pendiente['titulo'] ?? '';
            if (!$openai->validarRelevancia($titulo, $contenido)) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                continue;
            }

            // Detectar región
            $regionInfo = $openai->detectarRegionYPais($pendiente['url'], $contenido);

            // Generar artículo con OpenAI
            $fuenteNombre = $pendiente['fuente'] ?? parse_url($pendiente['url'], PHP_URL_HOST);
            $articuloGenerado = $openai->generarArticulo($contenido, $fuenteNombre, $pendiente['url']);

            // Preparar datos para guardar
            $datosArticulo = [
                'titulo' => $articuloGenerado['titulo'],
                'contenido' => $articuloGenerado['contenido'],
                'opinion_editorial' => $articuloGenerado['opinion_editorial'] ?? '',
                'tips' => $articuloGenerado['tips'] ?? [],
                'contenido_original' => mb_substr($contenido, 0, 5000),
                'fuente_nombre' => $fuenteNombre,
                'fuente_url' => $pendiente['url'],
                'imagen_url' => $pendiente['imagen_url'] ?? null,
                'region' => $regionInfo['region'],
                'pais_origen' => $regionInfo['pais'],
                'categoria' => $articuloGenerado['categoria'] ?? 'noticias',
                'tags' => $articuloGenerado['tags'] ?? []
            ];

            // Si no hay imagen, intentar obtener og:image
            if (empty($datosArticulo['imagen_url'])) {
                $datosArticulo['imagen_url'] = $tavily->obtenerImagenOG($pendiente['url']);
            }

            // Guardar artículo
            $articuloId = guardarArticulo($datosArticulo);

            // Marcar pendiente y URL como procesadas
            marcarPendienteUsada($pendiente['id']);
            registrarUrl($pendiente['url']);

            // Enviar notificación por email
            $datosArticulo['id'] = $articuloId;
            try {
                $emailNotifier->notificarNuevoArticulo($datosArticulo);
            } catch (Exception $e) {
                $errores[] = "Error enviando email: " . $e->getMessage();
            }

            $articulosGenerados++;
            $procesadas++;

        } catch (Exception $e) {
            marcarPendienteUsada($pendiente['id']);
            $errores[] = "Error procesando noticia: " . $e->getMessage();
        }
    }

    $mensaje = "Se generaron {$articulosGenerados} artículo(s).";
    if ($pendientesDisponibles > 0) {
        $mensaje .= " Quedan {$pendientesDisponibles} noticias en cache.";
    }
    if (!empty($errores)) {
        $mensaje .= " Errores: " . count($errores);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'articulos_generados' => $articulosGenerados,
        'pendientes_restantes' => contarPendientes(),
        'errores' => $errores
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
