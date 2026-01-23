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
    $debug['etapas'] = []; // Para tracking detallado de cada etapa

    $tavily = new TavilyClient(TAVILY_API_KEY);
    $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);
    $emailNotifier = new EmailNotifier();

    $articulosGenerados = 0;
    $errores = [];

    // Verificar si hay pendientes en cache
    $pendientesDisponibles = contarPendientes();
    $debug['pendientes_inicial'] = $pendientesDisponibles;

    // ETAPA 1: Decidir si buscar en Tavily
    if ($pendientesDisponibles >= 5) {
        $debug['tavily_ejecutado'] = false;
        $debug['tavily_razon'] = "No se ejecutó Tavily porque ya hay {$pendientesDisponibles} pendientes en cache (umbral: 5)";
        $debug['etapas'][] = [
            'etapa' => '1. Búsqueda Tavily',
            'estado' => 'omitida',
            'detalle' => "Ya hay suficientes pendientes ({$pendientesDisponibles}). Tavily solo busca cuando hay menos de 5."
        ];
    } else {
        $debug['tavily_ejecutado'] = true;
        $debug['tavily_razon'] = "Se ejecutó Tavily porque solo hay {$pendientesDisponibles} pendientes (menos de 5)";
        $debug['etapas'][] = [
            'etapa' => '1. Búsqueda Tavily',
            'estado' => 'ejecutada',
            'detalle' => "Buscando noticias nuevas..."
        ];

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
                $resultados = $tavily->searchWithPreferredSites($topic, 12, $dominiosPreferidos);
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
            // Debug COMPLETO: mostrar TODAS las candidatas con razón de filtrado
            $debugCandidatas = [];
            $estadisticas = [
                'total' => count($todasLasCandidatas),
                'ok' => 0,
                'url_vacia' => 0,
                'url_procesada' => 0,
                'titulo_similar' => 0
            ];

            foreach ($todasLasCandidatas as $c) {
                $url = $c['url'] ?? '';
                $titulo = $c['title'] ?? '';
                $fuente = parse_url($url, PHP_URL_HOST) ?? 'desconocido';
                $razon = 'OK';
                $estado = 'aceptada';

                if (empty($url)) {
                    $razon = 'URL vacía';
                    $estado = 'rechazada';
                    $estadisticas['url_vacia']++;
                } elseif (urlYaProcesada($url)) {
                    $razon = 'URL ya procesada anteriormente';
                    $estado = 'rechazada';
                    $estadisticas['url_procesada']++;
                } elseif (!empty($titulo) && tituloEsSimilar($titulo)) {
                    $razon = 'Título similar a artículo existente';
                    $estado = 'rechazada';
                    $estadisticas['titulo_similar']++;
                } else {
                    $estadisticas['ok']++;
                }

                $debugCandidatas[] = [
                    'titulo' => mb_substr($titulo, 0, 80),
                    'url' => $url,
                    'fuente' => $fuente,
                    'preferido' => $c['_preferido'] ?? false,
                    'estado' => $estado,
                    'razon' => $razon,
                    'contenido_chars' => strlen($c['content'] ?? $c['raw_content'] ?? '')
                ];
            }

            $debug['candidatas_estadisticas'] = $estadisticas;
            $debug['candidatas_detalle'] = $debugCandidatas;

            $guardadas = guardarCandidatasPendientes($todasLasCandidatas);
            $debug['candidatas_guardadas'] = $guardadas;
            $pendientesDisponibles = contarPendientes();
            $debug['pendientes_despues_guardar'] = $pendientesDisponibles;

            // Actualizar etapa con resultados
            $debug['etapas'][0]['detalle'] = "Tavily trajo {$debug['total_candidatas']} candidatas. Después de filtrar: {$guardadas} guardadas en cache.";
        }
    } // Fin del else de Tavily

    // ETAPA 2: Procesar pendientes
    $debug['etapas'][] = [
        'etapa' => '2. Procesar Pendientes',
        'estado' => 'ejecutada',
        'detalle' => "Procesando hasta 10 artículos de los {$pendientesDisponibles} pendientes..."
    ];

    // Procesar hasta 10 pendientes
    $procesadas = 0;
    $maxProcesar = 10;
    $debug['procesamiento'] = [];

    while ($procesadas < $maxProcesar) {
        $pendiente = obtenerPendiente();

        if (!$pendiente) {
            $debug['procesamiento'][] = 'No hay más pendientes';
            break;
        }

        $debugItem = [
            'url' => $pendiente['url'],
            'titulo_original' => $pendiente['titulo'] ?? '(sin título)',
            'fuente' => parse_url($pendiente['url'], PHP_URL_HOST),
            'origen' => $pendiente['fuente'] ?? 'importación manual'
        ];

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

            // Verificar duplicados contra artículos existentes (todos los estados)
            $titulo = $pendiente['titulo'] ?? '';
            $debugItem['titulo'] = mb_substr($titulo, 0, 50);

            $duplicado = verificarDuplicado($titulo, $pendiente['url']);
            if ($duplicado) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                $debugItem['resultado'] = "Duplicado por {$duplicado['tipo']}: '{$duplicado['titulo_existente']}' ({$duplicado['estado']})";
                if (isset($duplicado['similitud'])) {
                    $debugItem['resultado'] .= " - Similitud: {$duplicado['similitud']}";
                }
                $debug['procesamiento'][] = $debugItem;
                continue;
            }
            $debugItem['duplicado'] = 'No es duplicado';

            // Detectar región
            $regionInfo = $openai->detectarRegionYPais($pendiente['url'], $contenido);

            // Generar artículo con OpenAI
            $fuenteNombre = $pendiente['fuente'] ?? parse_url($pendiente['url'], PHP_URL_HOST);
            $articuloGenerado = $openai->generarArticulo($contenido, $fuenteNombre, $pendiente['url']);

            // Obtener imagen: priorizar og:image del artículo original
            $imagenUrl = null;

            // 1. Primero intentar obtener og:image del artículo original (más confiable)
            $imagenOG = $tavily->obtenerImagenOG($pendiente['url']);
            if (!empty($imagenOG)) {
                $imagenUrl = $imagenOG;
                $debugItem['imagen'] = 'og:image del original';
            }
            // 2. Fallback: usar imagen de Tavily (puede no corresponder al artículo)
            elseif (!empty($pendiente['imagen_url'])) {
                $imagenUrl = $pendiente['imagen_url'];
                $debugItem['imagen'] = 'fallback Tavily';
            }

            // Preparar datos para guardar
            $datosArticulo = [
                'titulo' => $articuloGenerado['titulo'],
                'titulo_original' => $pendiente['titulo'] ?? '', // Título original de la fuente
                'contenido' => $articuloGenerado['contenido'],
                'opinion_editorial' => $articuloGenerado['opinion_editorial'] ?? '',
                'tips' => $articuloGenerado['tips'] ?? [],
                'contenido_original' => mb_substr($contenido, 0, 5000),
                'fuente_nombre' => $fuenteNombre,
                'fuente_url' => $pendiente['url'],
                'imagen_url' => $imagenUrl,
                'region' => $regionInfo['region'],
                'pais_origen' => $regionInfo['pais'],
                'categoria' => $articuloGenerado['categoria'] ?? 'noticias',
                'tags' => $articuloGenerado['tags'] ?? []
            ];

            // Guardar artículo
            $articuloId = guardarArticulo($datosArticulo);

            // Agregar info del artículo generado al debug
            $debugItem['titulo_generado'] = $articuloGenerado['titulo'];
            $debugItem['region'] = $regionInfo['region'];
            $debugItem['pais'] = $regionInfo['pais'];
            $debugItem['categoria'] = $articuloGenerado['categoria'] ?? 'noticias';
            $debugItem['contenido_chars'] = strlen($contenido);
            $debugItem['openai_usado'] = true;

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
    $pendientesFinales = contarPendientes();

    // Actualizar etapa de procesamiento con resultados
    $debug['etapas'][1]['detalle'] = "Se procesaron {$procesadas} pendientes. Generados: {$articulosGenerados} artículos.";

    // Resumen final
    $debug['resumen'] = [
        'tavily_ejecutado' => $debug['tavily_ejecutado'] ?? false,
        'candidatas_tavily' => $debug['total_candidatas'] ?? 0,
        'candidatas_filtradas' => ($debug['candidatas_estadisticas']['total'] ?? 0) - ($debug['candidatas_estadisticas']['ok'] ?? 0),
        'candidatas_guardadas' => $debug['candidatas_guardadas'] ?? 0,
        'pendientes_inicio' => $debug['pendientes_inicial'],
        'pendientes_fin' => $pendientesFinales,
        'articulos_procesados' => $procesadas,
        'articulos_generados' => $articulosGenerados,
        'articulos_fallidos' => $procesadas - $articulosGenerados,
        'openai_llamadas' => $articulosGenerados // Cada artículo generado = 1 llamada a OpenAI
    ];

    $mensaje = "Se generaron {$articulosGenerados} artículo(s).";
    if ($pendientesFinales > 0) {
        $mensaje .= " Quedan {$pendientesFinales} noticias en cache.";
    }
    if (!empty($errores)) {
        $mensaje .= " Errores: " . count($errores);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'articulos_generados' => $articulosGenerados,
        'pendientes_restantes' => $pendientesFinales,
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
