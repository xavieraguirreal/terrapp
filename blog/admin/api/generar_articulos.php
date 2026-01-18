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
    $debug = [];
    $debug['inicio'] = date('H:i:s');

    $tavily = new TavilyClient(TAVILY_API_KEY);
    $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);
    $emailNotifier = new EmailNotifier();

    $articulosGenerados = 0;
    $errores = [];

    // Verificar si hay pendientes en cache
    $pendientesDisponibles = contarPendientes();
    $debug['pendientes_inicial'] = $pendientesDisponibles;

    if ($pendientesDisponibles < 5) {
        // Buscar nuevas noticias con Tavily
        $topics = SEARCH_TOPICS;
        shuffle($topics);
        $topicsSeleccionados = array_slice($topics, 0, 3);
        $debug['topics_buscados'] = $topicsSeleccionados;

        // Obtener sitios preferidos
        $sitiosPreferidos = obtenerSitiosPreferidos(true); // Solo activos
        $dominiosPreferidos = array_column($sitiosPreferidos, 'dominio');
        $debug['sitios_preferidos'] = $dominiosPreferidos;

        $todasLasCandidatas = [];

        foreach ($topicsSeleccionados as $topic) {
            try {
                // Usar búsqueda combinada: sitios preferidos + general
                $resultados = $tavily->searchWithPreferredSites($topic, 5, $dominiosPreferidos);
                $debug['tavily_' . substr($topic, 0, 20)] = count($resultados) . ' resultados';

                // Contar cuántos son de sitios preferidos
                $dePreferidos = count(array_filter($resultados, fn($r) => $r['_preferido'] ?? false));
                if ($dePreferidos > 0) {
                    $debug['tavily_' . substr($topic, 0, 20) . '_preferidos'] = $dePreferidos;
                }

                foreach ($resultados as $r) {
                    $todasLasCandidatas[] = $r;
                }
            } catch (Exception $e) {
                $errores[] = "Error buscando '{$topic}': " . $e->getMessage();
                $debug['tavily_error_' . substr($topic, 0, 20)] = $e->getMessage();
            }
        }

        $debug['total_candidatas'] = count($todasLasCandidatas);

        // Guardar candidatas en cache
        if (!empty($todasLasCandidatas)) {
            // Debug: mostrar por qué no se guardan
            $debugCandidatas = [];
            foreach (array_slice($todasLasCandidatas, 0, 5) as $c) {
                $url = $c['url'] ?? '';
                $titulo = $c['title'] ?? '';
                $razon = 'OK';
                if (empty($url)) $razon = 'URL vacía';
                elseif (urlYaProcesada($url)) $razon = 'URL ya procesada';
                elseif (!empty($titulo) && tituloEsSimilar($titulo)) $razon = 'Título similar';
                $debugCandidatas[] = ['url' => substr($url, 0, 40), 'razon' => $razon];
            }
            $debug['candidatas_detalle'] = $debugCandidatas;

            $guardadas = guardarCandidatasPendientes($todasLasCandidatas);
            $debug['candidatas_guardadas'] = $guardadas;
            $pendientesDisponibles = contarPendientes();
            $debug['pendientes_despues_guardar'] = $pendientesDisponibles;
        }
    }

    // Procesar hasta 3 pendientes
    $procesadas = 0;
    $maxProcesar = 3;
    $debug['procesamiento'] = [];

    while ($procesadas < $maxProcesar) {
        $pendiente = obtenerPendiente();

        if (!$pendiente) {
            $debug['procesamiento'][] = 'No hay más pendientes';
            break;
        }

        $debugItem = ['url' => substr($pendiente['url'], 0, 50)];

        try {
            // Verificar URL no procesada
            if (urlYaProcesada($pendiente['url'])) {
                marcarPendienteUsada($pendiente['id']);
                $debugItem['resultado'] = 'URL ya procesada';
                $debug['procesamiento'][] = $debugItem;
                continue;
            }

            // Verificar título no duplicado
            if (!empty($pendiente['titulo']) && tituloEsSimilar($pendiente['titulo'])) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                $debugItem['resultado'] = 'Título similar existente';
                $debug['procesamiento'][] = $debugItem;
                continue;
            }

            // Obtener contenido completo si es necesario
            $contenido = $pendiente['contenido'];
            $debugItem['contenido_inicial'] = strlen($contenido ?? '') . ' chars';

            if (empty($contenido) || strlen($contenido) < 500) {
                try {
                    $extracted = $tavily->extract($pendiente['url']);
                    if ($extracted && !empty($extracted['raw_content'])) {
                        $contenido = $extracted['raw_content'];
                        $debugItem['contenido_extraido'] = strlen($contenido) . ' chars';
                    }
                } catch (Exception $e) {
                    $debugItem['extract_error'] = $e->getMessage();
                }
            }

            if (empty($contenido) || strlen($contenido) < 200) {
                marcarPendienteUsada($pendiente['id']);
                $debugItem['resultado'] = 'Contenido muy corto: ' . strlen($contenido ?? '');
                $debug['procesamiento'][] = $debugItem;
                continue;
            }

            // Validar relevancia con OpenAI
            $titulo = $pendiente['titulo'] ?? '';
            $debugItem['titulo'] = mb_substr($titulo, 0, 50);
            $esRelevante = $openai->validarRelevancia($titulo, $contenido);
            $debugItem['openai_respuesta'] = $openai->ultimaRespuestaValidacion;

            if (!$esRelevante) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                $debugItem['resultado'] = 'No relevante (OpenAI dijo: ' . $openai->ultimaRespuestaValidacion . ')';
                $debug['procesamiento'][] = $debugItem;
                continue;
            }
            $debugItem['relevancia'] = 'OK';

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
            $debugItem['resultado'] = 'GUARDADO OK - ID: ' . $articuloId;
            $debug['procesamiento'][] = $debugItem;

        } catch (Exception $e) {
            marcarPendienteUsada($pendiente['id']);
            $errores[] = "Error procesando noticia: " . $e->getMessage();
            $debugItem['resultado'] = 'ERROR: ' . $e->getMessage();
            $debug['procesamiento'][] = $debugItem;
        }
    }

    $debug['fin'] = date('H:i:s');

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
        'errores' => $errores,
        'debug' => $debug
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
