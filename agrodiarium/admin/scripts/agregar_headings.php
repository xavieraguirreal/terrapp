<?php
/**
 * Script temporal para agregar estructura de headings a artículos existentes
 * Uso: php agregar_headings.php
 * O acceder desde navegador: /agrodiarium/admin/scripts/agregar_headings.php
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Forzar output inmediato
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (@ob_end_flush());
ob_implicit_flush(true);

// Aumentar tiempo de ejecución
set_time_limit(300); // 5 minutos

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';

// Configuración
$SOLO_PREVIEW = isset($_GET['preview']) || (isset($argv[1]) && $argv[1] === '--preview');
$LIMITE = isset($_GET['limite']) ? (int)$_GET['limite'] : (isset($argv[2]) ? (int)$argv[2] : 5);
$START_TIME = microtime(true);

// Output como HTML si es navegador, texto plano si es CLI
$esWeb = php_sapi_name() !== 'cli';
if ($esWeb) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Accel-Buffering: no'); // Para nginx
    header('Cache-Control: no-cache');
    echo "<!DOCTYPE html><html><head><title>Agregar Headings</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #e0e0e0; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .info { color: #60a5fa; }
        .warning { color: #fbbf24; }
        .time { color: #a78bfa; font-size: 0.85em; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        h1 { color: #4ade80; }
        hr { border-color: #444; margin: 20px 0; }
        .spinner { display: inline-block; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .progress-bar { background: #374151; border-radius: 4px; height: 20px; margin: 10px 0; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #4ade80, #22c55e); height: 100%; transition: width 0.3s; }
    </style>
    </head><body>";
    echo "<h1>🌱 Agregar Headings a Artículos</h1>";
    echo "<p class='info'>Modo: " . ($SOLO_PREVIEW ? "PREVIEW (no guarda cambios)" : "EJECUCIÓN (guardará cambios)") . "</p>";
    echo "<p class='info'>Límite: $LIMITE artículos</p>";
    echo "<div id='progress-container'></div><hr>";
    echo "<p class='info'>⏳ Iniciando proceso...</p>";
    // Padding grande para forzar flush (nginx/php-fpm buffean ~4KB)
    echo str_repeat(' ', 8192);
    echo "<!-- flush padding -->";
    if (ob_get_level() > 0) @ob_flush();
    flush();

    // Test inmediato
    echo "<script>console.log('Script iniciado correctamente');</script>";
    if (ob_get_level() > 0) @ob_flush();
    flush();
} else {
    echo "=== Agregar Headings a Artículos ===\n";
    echo "Modo: " . ($SOLO_PREVIEW ? "PREVIEW" : "EJECUCIÓN") . "\n";
    echo "Límite: $LIMITE artículos\n\n";
}

function getElapsedTime() {
    global $START_TIME;
    $elapsed = microtime(true) - $START_TIME;
    return sprintf("%.1fs", $elapsed);
}

function output($msg, $class = '') {
    global $esWeb;
    $time = getElapsedTime();
    $plainMsg = strip_tags($msg);

    if ($esWeb) {
        echo "<p class='$class'><span class='time'>[{$time}]</span> $msg</p>";
        // Debug en consola del navegador
        $escapedMsg = addslashes($plainMsg);
        echo "<script>console.log('[{$time}] {$escapedMsg}');</script>";
    } else {
        echo "[{$time}] " . $plainMsg . "\n";
    }

    // Forzar flush agresivo
    if (ob_get_level() > 0) @ob_flush();
    flush();
}

function outputPre($content) {
    global $esWeb;
    if ($esWeb) {
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
        echo "<script>console.log('Content preview:', " . json_encode(mb_substr($content, 0, 200)) . ");</script>";
    } else {
        echo $content . "\n";
    }
    if (ob_get_level() > 0) @ob_flush();
    flush();
}

function showSpinner($id, $text) {
    global $esWeb;
    if ($esWeb) {
        echo "<p id='spinner-{$id}' class='info'><span class='spinner'>⏳</span> {$text}...</p>";
        echo "<script>console.log('Started: {$text}');</script>";
        if (ob_get_level() > 0) @ob_flush();
        flush();
    }
}

function hideSpinner($id) {
    global $esWeb;
    if ($esWeb) {
        echo "<script>document.getElementById('spinner-{$id}')?.remove();</script>";
        if (ob_get_level() > 0) @ob_flush();
        flush();
    }
}

try {
    $pdo = getConnection();
    $openai = new OpenAIClient(OPENAI_API_KEY, 'gpt-4o-mini');

    // Buscar artículos publicados que NO tengan headings (## )
    $sql = "SELECT id, titulo, contenido
            FROM blog_articulos
            WHERE estado = 'publicado'
            AND contenido NOT LIKE '%## %'
            ORDER BY fecha_publicacion DESC
            LIMIT :limite";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $LIMITE, PDO::PARAM_INT);
    $stmt->execute();
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($articulos)) {
        output("✅ No hay artículos sin headings. Todos ya tienen estructura.", "success");
        if ($esWeb) echo "</body></html>";
        exit;
    }

    output("📝 Encontrados " . count($articulos) . " artículos sin headings", "info");
    echo $esWeb ? "<hr>" : "\n";

    $procesados = 0;
    $errores = 0;

    foreach ($articulos as $articulo) {
        output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", "info");
        output("📄 Procesando: <strong>{$articulo['titulo']}</strong> (ID: {$articulo['id']})", "info");

        // Prompt para reestructurar
        $promptRestructurar = <<<PROMPT
Reestructurá este contenido agregando títulos de sección con formato markdown (## Título).

REGLAS:
1. NO cambies el contenido, solo agregá estructura
2. Dividí el texto en 2-4 secciones lógicas
3. Usá ## para secciones principales
4. Podés usar ### para subsecciones si tiene sentido
5. Mantené TODO el texto original, no elimines nada
6. Los títulos de sección deben ser descriptivos (ej: ## El proyecto, ## Beneficios, ## Contexto)

CONTENIDO ORIGINAL:
{$articulo['contenido']}

Respondé SOLO con el contenido reestructurado, sin explicaciones.
PROMPT;

        try {
            $spinnerId = "art-{$articulo['id']}";
            showSpinner($spinnerId, "Enviando artículo a OpenAI");

            $contenidoNuevo = $openai->chat(
                "Eres un editor que estructura textos agregando títulos de sección markdown. Solo reestructurás, no modificás el contenido.",
                $promptRestructurar
            );

            hideSpinner($spinnerId);

            // Verificar que tenga headings
            if (strpos($contenidoNuevo, '## ') === false) {
                output("⚠️ OpenAI no agregó headings, saltando...", "warning");
                $errores++;
                continue;
            }

            output("✅ Contenido reestructurado correctamente", "success");
            outputPre(mb_substr($contenidoNuevo, 0, 500) . "...");

            // Buscar traducciones en tabla separada
            $stmtTrad = $pdo->prepare("
                SELECT idioma, contenido
                FROM blog_articulos_traducciones
                WHERE articulo_id = ? AND contenido IS NOT NULL AND contenido != ''
            ");
            $stmtTrad->execute([$articulo['id']]);
            $traducciones = $stmtTrad->fetchAll(PDO::FETCH_ASSOC);

            if (!$SOLO_PREVIEW) {
                // Guardar contenido principal
                $stmtUpdate = $pdo->prepare("UPDATE blog_articulos SET contenido = ? WHERE id = ?");
                $stmtUpdate->execute([$contenidoNuevo, $articulo['id']]);
                output("💾 Contenido principal guardado", "success");
            }

            // Procesar traducciones si existen
            if (!empty($traducciones)) {
                output("🌍 Procesando " . count($traducciones) . " traducciones...", "info");

                foreach ($traducciones as $trad) {
                    $idioma = $trad['idioma'];

                    // Saltar si la traducción ya tiene headings
                    if (strpos($trad['contenido'], '## ') !== false) {
                        output("  ⏭️ {$idioma} ya tiene headings, saltando", "info");
                        continue;
                    }

                    $nombresIdiomas = [
                        'pt' => 'portugués brasileño',
                        'en' => 'inglés',
                        'fr' => 'francés',
                        'nl' => 'neerlandés'
                    ];
                    $nombreIdioma = $nombresIdiomas[$idioma] ?? $idioma;

                    $promptTrad = <<<PROMPT
Reestructurá este contenido en {$nombreIdioma} agregando títulos de sección con formato markdown (## Título).

REGLAS:
1. NO cambies el contenido, solo agregá estructura
2. Dividí el texto en 2-4 secciones lógicas
3. Usá ## para secciones principales
4. Los títulos deben estar en {$nombreIdioma}

CONTENIDO:
{$trad['contenido']}

Respondé SOLO con el contenido reestructurado.
PROMPT;

                    try {
                        $spinnerTradId = "trad-{$articulo['id']}-{$idioma}";
                        showSpinner($spinnerTradId, "Procesando traducción {$idioma}");

                        $contenidoTrad = $openai->chat(
                            "Eres un editor que estructura textos agregando títulos de sección markdown.",
                            $promptTrad
                        );

                        hideSpinner($spinnerTradId);

                        if (strpos($contenidoTrad, '## ') !== false) {
                            if (!$SOLO_PREVIEW) {
                                $stmtUpdateTrad = $pdo->prepare("
                                    UPDATE blog_articulos_traducciones
                                    SET contenido = ?
                                    WHERE articulo_id = ? AND idioma = ?
                                ");
                                $stmtUpdateTrad->execute([$contenidoTrad, $articulo['id'], $idioma]);
                            }
                            output("  ✅ {$idioma} procesado", "success");
                        } else {
                            output("  ⚠️ {$idioma}: OpenAI no agregó headings", "warning");
                        }
                    } catch (Exception $e) {
                        output("  ⚠️ Error en {$idioma}: " . $e->getMessage(), "warning");
                    }

                    // Pequeña pausa para no saturar API
                    usleep(500000); // 0.5 segundos
                }
            }

            if ($SOLO_PREVIEW) {
                output("👁️ PREVIEW: No se guardaron cambios", "warning");
            }

            $procesados++;

        } catch (Exception $e) {
            output("❌ Error: " . $e->getMessage(), "error");
            $errores++;
        }

        // Pausa entre artículos
        sleep(1);
    }

    echo $esWeb ? "<hr>" : "\n";
    output("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", "info");
    output("📊 RESUMEN:", "info");
    output("  ✅ Procesados: $procesados", "success");
    output("  ❌ Errores: $errores", $errores > 0 ? "error" : "info");

    if ($SOLO_PREVIEW) {
        output("", "");
        output("💡 Para aplicar cambios, ejecutá sin ?preview o sin --preview", "warning");
        if ($esWeb) {
            $urlEjecutar = strtok($_SERVER['REQUEST_URI'], '?') . "?limite=$LIMITE";
            echo "<p><a href='$urlEjecutar' style='color: #4ade80; font-size: 1.2em;'>▶️ Ejecutar y guardar cambios</a></p>";
        }
    }

} catch (Exception $e) {
    output("❌ Error fatal: " . $e->getMessage(), "error");
}

if ($esWeb) {
    echo "<hr><p class='info'>
        <strong>Uso:</strong><br>
        - Preview: <code>?preview&limite=5</code><br>
        - Ejecutar: <code>?limite=10</code><br>
        - CLI: <code>php agregar_headings.php --preview 5</code>
    </p>";
    echo "</body></html>";
}
