<?php
/**
 * TERRApp Blog - Cron Job para exportar JSON cada hora
 *
 * Ejecutar con cron:
 * 0 * * * * php /path/to/agrodiarium/admin/cron/exportar_json.php >> /path/to/logs/exportar.log 2>&1
 */

// Establecer límites para ejecución como cron
set_time_limit(60);
ini_set('memory_limit', '128M');

// Log de inicio
$inicio = date('Y-m-d H:i:s');
echo "[{$inicio}] Iniciando exportación de JSON...\n";

require_once __DIR__ . '/../includes/functions.php';

try {
    // Exportar artículos a JSON
    $resultado = exportarArticulosJSON();

    if ($resultado) {
        echo "  ✓ Artículos exportados a JSON\n";

        // También generar RSS
        generarRSSFeed();
        echo "  ✓ RSS Feed generado\n";

        // Generar Sitemap para SEO
        generarSitemap();
        echo "  ✓ Sitemap XML generado\n";
    } else {
        echo "  ✗ Error al exportar artículos\n";
    }

    $fin = date('Y-m-d H:i:s');
    echo "[{$fin}] Exportación completada\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
