<?php
/**
 * TERRApp Blog - API para cambiar estado de artículo
 * Genera traducciones automáticamente al aprobar
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
verificarAccesoAPI();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $estado = $input['estado'] ?? '';
    $saltearCriterio = isset($input['saltear_criterio']) ? (bool)$input['saltear_criterio'] : false;
    $generarTraducciones = isset($input['generar_traducciones']) ? (bool)$input['generar_traducciones'] : true;
    $publicarAhora = isset($input['publicar_ahora']) ? (bool)$input['publicar_ahora'] : false;

    // Validar
    if ($id <= 0) {
        throw new Exception('ID de artículo inválido');
    }

    $estadosValidos = ['borrador', 'publicado', 'rechazado', 'programado'];
    if (!in_array($estado, $estadosValidos)) {
        throw new Exception('Estado inválido');
    }

    $traduccionesGeneradas = 0;
    $erroresTraducciones = [];

    // Si se está aprobando y se deben generar traducciones
    if (($estado === 'publicado' || $estado === 'programado') && $generarTraducciones) {
        $articulo = obtenerArticulo($id);

        if ($articulo) {
            // Verificar si ya tiene traducciones
            $traduccionesExistentes = obtenerTraducciones($id);

            if (count($traduccionesExistentes) < 4) {
                // Generar traducciones faltantes
                $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);
                $idiomas = ['pt', 'en', 'fr', 'nl'];

                foreach ($idiomas as $idioma) {
                    if (!isset($traduccionesExistentes[$idioma])) {
                        try {
                            $traduccion = $openai->traducirArticulo($articulo, $idioma);
                            if (guardarTraduccion($id, $idioma, $traduccion)) {
                                $traduccionesGeneradas++;
                            }
                        } catch (Exception $e) {
                            $erroresTraducciones[] = "{$idioma}: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }

    // Cambiar estado (pasar publicarAhora para saltear la programación)
    $resultado = cambiarEstadoArticulo($id, $estado, $saltearCriterio, $publicarAhora);

    if ($resultado) {
        // Obtener el artículo actualizado para ver su estado final
        $articuloActualizado = obtenerArticulo($id);
        $estadoFinal = $articuloActualizado['estado'] ?? $estado;

        if ($estadoFinal === 'programado') {
            $fechaProg = $articuloActualizado['fecha_programada'] ?? '';
            $mensaje = "Artículo PROGRAMADO para " . date('d/m/Y H:i', strtotime($fechaProg));
        } elseif ($estadoFinal === 'publicado') {
            $mensaje = "Artículo PUBLICADO";
        } else {
            $mensaje = "Estado cambiado a '{$estadoFinal}'";
        }

        if ($traduccionesGeneradas > 0) {
            $mensaje .= ". Se generaron {$traduccionesGeneradas} traducciones.";
        }

        // Exportar JSON y RSS automáticamente
        if ($estadoFinal === 'publicado' || $estadoFinal === 'programado') {
            exportarArticulosJSON();
            generarRSSFeed();
            $mensaje .= " JSON y RSS actualizados.";
        }

        $response = [
            'success' => true,
            'message' => $mensaje,
            'traducciones_generadas' => $traduccionesGeneradas,
            'estado_final' => $estadoFinal
        ];

        if ($estadoFinal === 'programado' && isset($articuloActualizado['fecha_programada'])) {
            $response['fecha_programada'] = $articuloActualizado['fecha_programada'];
        }

        if (!empty($erroresTraducciones)) {
            $response['errores_traducciones'] = $erroresTraducciones;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('No se pudo cambiar el estado');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
