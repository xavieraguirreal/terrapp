<?php
/**
 * AGRODiarium - Cron para incrementar vistas diariamente
 *
 * Ejecutar 2-3 veces al día via cron:
 * 0 8,14,20 * * * php /path/to/boost_vistas_diario.php
 *
 * Cada artículo publicado recibe entre 1 y 5 vistas aleatorias
 */

// Evitar ejecución desde navegador
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    // Permitir ejecución web solo con clave secreta
    if (!isset($_GET['cron_key']) || $_GET['cron_key'] !== 'agrodiarium_boost_2026') {
        http_response_code(403);
        die('Acceso denegado');
    }
}

require_once __DIR__ . '/../admin/config/database.php';

try {
    $pdo = getConnection();

    // Obtener todos los artículos publicados
    $stmt = $pdo->query("SELECT id, titulo FROM blog_articulos WHERE estado = 'publicado'");
    $articulos = $stmt->fetchAll();

    if (empty($articulos)) {
        echo "No hay artículos publicados.\n";
        exit(0);
    }

    $totalBoost = 0;
    $detalles = [];

    // Incrementar cada artículo con un número aleatorio diferente
    foreach ($articulos as $art) {
        $incremento = rand(1, 5);

        $stmt = $pdo->prepare("UPDATE blog_articulos SET vistas = vistas + ? WHERE id = ?");
        $stmt->execute([$incremento, $art['id']]);

        $totalBoost += $incremento;
        $detalles[] = "  - #{$art['id']}: +{$incremento} vistas";
    }

    // Registrar en log
    $fecha = date('Y-m-d H:i:s');
    $log = "[{$fecha}] Boost diario ejecutado\n";
    $log .= "  Artículos: " . count($articulos) . "\n";
    $log .= "  Total vistas agregadas: {$totalBoost}\n";
    $log .= implode("\n", $detalles) . "\n";
    $log .= "---\n";

    // Guardar log
    $logFile = __DIR__ . '/logs/boost_vistas.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, $log, FILE_APPEND);

    // Output para cron
    echo "OK - {$fecha}\n";
    echo "Artículos actualizados: " . count($articulos) . "\n";
    echo "Total vistas agregadas: {$totalBoost}\n";

} catch (Exception $e) {
    $error = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/logs/boost_vistas.log', $error, FILE_APPEND);
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
