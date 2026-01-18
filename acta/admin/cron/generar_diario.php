<?php
/**
 * TERRApp Blog - Cron Job para generación diaria de artículos
 *
 * Ejecutar con cron:
 * 0 6 * * * php /path/to/acta/admin/cron/generar_diario.php >> /path/to/logs/cron.log 2>&1
 */

// Establecer límites para ejecución como cron
set_time_limit(300); // 5 minutos máximo
ini_set('memory_limit', '256M');

// Log de inicio
$inicio = date('Y-m-d H:i:s');
echo "[{$inicio}] Iniciando generación diaria de artículos...\n";

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

    // Verificar si hay suficientes pendientes
    $pendientesDisponibles = contarPendientes();
    echo "Pendientes disponibles: {$pendientesDisponibles}\n";

    // Si hay menos de 10, buscar más
    if ($pendientesDisponibles < 10) {
        echo "Buscando nuevas noticias con Tavily...\n";

        $topics = SEARCH_TOPICS;
        shuffle($topics);
        $topicsSeleccionados = array_slice($topics, 0, 5);

        $todasLasCandidatas = [];

        foreach ($topicsSeleccionados as $topic) {
            try {
                echo "  Buscando: {$topic}\n";
                $resultados = $tavily->search($topic, 5);
                foreach ($resultados as $r) {
                    $todasLasCandidatas[] = $r;
                }
                // Pequeña pausa entre búsquedas
                usleep(500000); // 0.5 segundos
            } catch (Exception $e) {
                $errores[] = "Error buscando '{$topic}': " . $e->getMessage();
                echo "  Error: " . $e->getMessage() . "\n";
            }
        }

        // Guardar candidatas en cache
        if (!empty($todasLasCandidatas)) {
            $guardadas = guardarCandidatasPendientes($todasLasCandidatas);
            echo "Guardadas {$guardadas} nuevas candidatas en cache\n";
        }

        $pendientesDisponibles = contarPendientes();
    }

    // Procesar hasta 5 pendientes
    $maxProcesar = 5;
    echo "Procesando hasta {$maxProcesar} noticias...\n";

    for ($i = 0; $i < $maxProcesar; $i++) {
        $pendiente = obtenerPendiente();

        if (!$pendiente) {
            echo "No hay más pendientes para procesar\n";
            break;
        }

        echo "\nProcesando: " . mb_substr($pendiente['titulo'] ?? $pendiente['url'], 0, 50) . "...\n";

        try {
            // Verificar URL no procesada
            if (urlYaProcesada($pendiente['url'])) {
                marcarPendienteUsada($pendiente['id']);
                echo "  URL ya procesada, saltando\n";
                continue;
            }

            // Verificar título no duplicado
            if (!empty($pendiente['titulo']) && tituloEsSimilar($pendiente['titulo'])) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                echo "  Título similar existente, saltando\n";
                continue;
            }

            // Obtener contenido completo
            $contenido = $pendiente['contenido'];
            if (empty($contenido) || strlen($contenido) < 500) {
                try {
                    echo "  Extrayendo contenido completo...\n";
                    $extracted = $tavily->extract($pendiente['url']);
                    if ($extracted && !empty($extracted['raw_content'])) {
                        $contenido = $extracted['raw_content'];
                    }
                } catch (Exception $e) {
                    echo "  No se pudo extraer contenido: " . $e->getMessage() . "\n";
                }
            }

            if (empty($contenido) || strlen($contenido) < 200) {
                marcarPendienteUsada($pendiente['id']);
                echo "  Contenido insuficiente, saltando\n";
                continue;
            }

            // Validar relevancia
            echo "  Validando relevancia con OpenAI...\n";
            $titulo = $pendiente['titulo'] ?? '';
            if (!$openai->validarRelevancia($titulo, $contenido)) {
                marcarPendienteUsada($pendiente['id']);
                registrarUrl($pendiente['url']);
                echo "  No relevante para agricultura urbana, saltando\n";
                continue;
            }

            // Detectar región
            $regionInfo = $openai->detectarRegionYPais($pendiente['url'], $contenido);
            echo "  Región detectada: {$regionInfo['region']}" . ($regionInfo['pais'] ? " ({$regionInfo['pais']})" : "") . "\n";

            // Generar artículo
            echo "  Generando artículo con OpenAI...\n";
            $fuenteNombre = $pendiente['fuente'] ?? parse_url($pendiente['url'], PHP_URL_HOST);
            $articuloGenerado = $openai->generarArticulo($contenido, $fuenteNombre, $pendiente['url']);

            // Preparar datos
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

            // Intentar obtener imagen si no hay
            if (empty($datosArticulo['imagen_url'])) {
                $datosArticulo['imagen_url'] = $tavily->obtenerImagenOG($pendiente['url']);
            }

            // Guardar
            $articuloId = guardarArticulo($datosArticulo);
            echo "  Artículo guardado con ID: {$articuloId}\n";

            // Marcar como procesada
            marcarPendienteUsada($pendiente['id']);
            registrarUrl($pendiente['url']);

            // Enviar email
            $datosArticulo['id'] = $articuloId;
            try {
                $emailNotifier->notificarNuevoArticulo($datosArticulo);
                echo "  Email de notificación enviado\n";
            } catch (Exception $e) {
                echo "  Error enviando email: " . $e->getMessage() . "\n";
                $errores[] = "Error email: " . $e->getMessage();
            }

            $articulosGenerados++;

            // Pausa entre generaciones
            sleep(2);

        } catch (Exception $e) {
            marcarPendienteUsada($pendiente['id']);
            echo "  Error: " . $e->getMessage() . "\n";
            $errores[] = $e->getMessage();
        }
    }

    // Resumen
    $fin = date('Y-m-d H:i:s');
    echo "\n========================================\n";
    echo "Resumen de ejecución:\n";
    echo "  Inicio: {$inicio}\n";
    echo "  Fin: {$fin}\n";
    echo "  Artículos generados: {$articulosGenerados}\n";
    echo "  Pendientes restantes: " . contarPendientes() . "\n";
    echo "  Errores: " . count($errores) . "\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "ERROR FATAL: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
