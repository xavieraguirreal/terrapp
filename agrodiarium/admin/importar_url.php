<?php
/**
 * TERRApp Blog - Importar artículo desde URL manual
 * Alternativa a Tavily: permite ingresar URLs directamente
 */

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/TavilyClient.php';
require_once __DIR__ . '/includes/OpenAIClient.php';

$mensaje = '';
$error = '';
$resultado = null;

// Procesar importación de URL directa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_url'])) {
    $url = trim($_POST['url'] ?? '');

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Ingresa una URL válida';
    } elseif (urlYaProcesada($url)) {
        $error = 'Esta URL ya fue procesada anteriormente';
    } else {
        try {
            $tavily = new TavilyClient(TAVILY_API_KEY);
            $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);

            // Extraer contenido
            $extracted = $tavily->extract($url);

            if (!$extracted || empty($extracted['raw_content'])) {
                throw new Exception('No se pudo extraer el contenido de la URL');
            }

            $contenido = $extracted['raw_content'];

            if (strlen($contenido) < 200) {
                throw new Exception('El contenido extraído es muy corto (' . strlen($contenido) . ' caracteres)');
            }

            // Detectar región
            $regionInfo = $openai->detectarRegionYPais($url, $contenido);

            // Generar artículo
            $fuenteNombre = parse_url($url, PHP_URL_HOST);
            $articuloGenerado = $openai->generarArticulo($contenido, $fuenteNombre, $url);

            // Obtener imagen
            $imagenUrl = $tavily->obtenerImagenOG($url);

            // Preparar datos
            $datosArticulo = [
                'titulo' => $articuloGenerado['titulo'],
                'contenido' => $articuloGenerado['contenido'],
                'opinion_editorial' => $articuloGenerado['opinion_editorial'] ?? '',
                'tips' => $articuloGenerado['tips'] ?? [],
                'contenido_original' => mb_substr($contenido, 0, 5000),
                'fuente_nombre' => $fuenteNombre,
                'fuente_url' => $url,
                'imagen_url' => $imagenUrl,
                'region' => $regionInfo['region'],
                'pais_origen' => $regionInfo['pais'],
                'categoria' => $articuloGenerado['categoria'] ?? 'noticias',
                'tags' => $articuloGenerado['tags'] ?? []
            ];

            // Guardar
            $articuloId = guardarArticulo($datosArticulo);
            registrarUrl($url);

            $mensaje = "Artículo importado correctamente con ID: {$articuloId}";
            $resultado = [
                'id' => $articuloId,
                'titulo' => $articuloGenerado['titulo'],
                'contenido_chars' => strlen($contenido),
                'region' => $regionInfo['region'],
                'pais' => $regionInfo['pais']
            ];

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Procesar escaneo de portal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['escanear_portal'])) {
    $portalUrl = trim($_POST['portal_url'] ?? '');
    $selector = trim($_POST['selector'] ?? 'a');

    if (empty($portalUrl) || !filter_var($portalUrl, FILTER_VALIDATE_URL)) {
        $error = 'Ingresa una URL de portal válida';
    } else {
        try {
            // Obtener HTML del portal
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $html = @file_get_contents($portalUrl, false, $context);

            if (!$html) {
                throw new Exception('No se pudo acceder al portal');
            }

            // Extraer enlaces de noticias
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $enlaces = [];
            $baseDomain = parse_url($portalUrl, PHP_URL_SCHEME) . '://' . parse_url($portalUrl, PHP_URL_HOST);

            // Buscar enlaces que parezcan artículos
            $links = $xpath->query('//a[@href]');

            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $texto = trim($link->textContent);

                // Filtrar enlaces válidos
                if (empty($texto) || strlen($texto) < 20 || strlen($texto) > 200) continue;

                // Convertir URLs relativas a absolutas
                if (strpos($href, '/') === 0) {
                    $href = $baseDomain . $href;
                } elseif (strpos($href, 'http') !== 0) {
                    continue;
                }

                // Filtrar URLs que no parecen artículos
                if (preg_match('/(\.pdf|\.jpg|\.png|\.gif|#|javascript:|mailto:|facebook|twitter|instagram|youtube|whatsapp)/i', $href)) {
                    continue;
                }

                // Verificar si ya está procesada
                $yaProcessada = urlYaProcesada($href);

                $enlaces[] = [
                    'url' => $href,
                    'titulo' => $texto,
                    'procesada' => $yaProcessada
                ];
            }

            // Eliminar duplicados por URL
            $enlacesUnicos = [];
            $urlsVistas = [];
            foreach ($enlaces as $e) {
                if (!isset($urlsVistas[$e['url']])) {
                    $enlacesUnicos[] = $e;
                    $urlsVistas[$e['url']] = true;
                }
            }

            $resultado = [
                'tipo' => 'escaneo',
                'portal' => $portalUrl,
                'enlaces' => array_slice($enlacesUnicos, 0, 50) // Limitar a 50
            ];

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Procesar importación múltiple desde escaneo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_seleccionados'])) {
    $urlsSeleccionadas = $_POST['urls'] ?? [];
    $titulosSeleccionados = $_POST['titulos'] ?? [];

    if (empty($urlsSeleccionadas)) {
        $error = 'Selecciona al menos una URL para importar';
    } else {
        $importados = 0;
        $rechazados = [];
        $erroresImport = [];

        foreach ($urlsSeleccionadas as $index => $url) {
            $titulo = $titulosSeleccionados[$index] ?? parse_url($url, PHP_URL_HOST);

            // Verificar manualmente cada condición para dar feedback
            if (urlYaProcesada($url)) {
                $rechazados[] = "❌ URL ya procesada: " . mb_substr($titulo, 0, 50);
                continue;
            }

            if (tituloEsSimilar($titulo)) {
                $rechazados[] = "❌ Título similar existente: " . mb_substr($titulo, 0, 50);
                continue;
            }

            try {
                // Guardar como pendiente para procesar después
                $guardadas = guardarCandidatasPendientes([[
                    'url' => $url,
                    'title' => $titulo,
                    'content' => '',
                    'raw_content' => ''
                ]]);

                if ($guardadas > 0) {
                    $importados++;
                } else {
                    $rechazados[] = "❌ Filtrado (duplicado en pendientes): " . mb_substr($titulo, 0, 50);
                }
            } catch (Exception $e) {
                $erroresImport[] = "Error con {$url}: " . $e->getMessage();
            }
        }

        if ($importados > 0) {
            $mensaje = "✅ Se agregaron {$importados} URLs a la cola de pendientes. Ejecuta 'Buscar y Generar' para procesarlas.";
        } else {
            $mensaje = "⚠️ No se agregó ninguna URL (todas fueron filtradas por duplicados).";
        }

        if (!empty($rechazados)) {
            $mensaje .= "\n\nRechazados:\n" . implode("\n", $rechazados);
        }

        if (!empty($erroresImport)) {
            $error = implode("\n", $erroresImport);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar URL - TERRApp Blog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'forest': { 500: '#3d9268', 600: '#2d7553', 700: '#265e44' },
                        'earth': { 50: '#faf6f1' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-earth-50 min-h-screen">
    <header class="bg-gradient-to-r from-forest-600 to-forest-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🔗</span>
                    <div>
                        <h1 class="text-xl font-bold">Importar desde URL</h1>
                        <p class="text-sm text-green-200">Alternativa manual a Tavily</p>
                    </div>
                </div>
                <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 whitespace-pre-wrap">
            <?= nl2br(htmlspecialchars($mensaje)) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= nl2br(htmlspecialchars($error)) ?>
        </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Opción 1: URL directa -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <span>📰</span> Importar URL directa
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    Ingresa la URL de un artículo específico y se generará automáticamente.
                </p>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL del artículo</label>
                        <input type="url" name="url" required
                               placeholder="https://ejemplo.com/noticia-agricultura"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-forest-500">
                    </div>
                    <button type="submit" name="importar_url" value="1"
                            class="w-full bg-forest-600 hover:bg-forest-700 text-white font-bold py-2 px-4 rounded-lg transition">
                        🚀 Importar y Generar Artículo
                    </button>
                </form>
            </div>

            <!-- Opción 2: Escanear portal -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <span>🌐</span> Escanear portal de noticias
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    Ingresa la URL de un portal y extraerá los titulares para que elijas cuáles importar.
                </p>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL del portal</label>
                        <input type="url" name="portal_url" required
                               placeholder="https://ejemplo.com/seccion-agricultura"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-forest-500">
                    </div>
                    <button type="submit" name="escanear_portal" value="1"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition">
                        🔍 Escanear Portal
                    </button>
                </form>
            </div>
        </div>

        <?php if ($resultado && isset($resultado['tipo']) && $resultado['tipo'] === 'escaneo'): ?>
        <!-- Resultados del escaneo -->
        <div class="bg-white rounded-xl shadow-md p-6 mt-6">
            <h2 class="text-lg font-bold mb-4">
                📋 Enlaces encontrados en <?= htmlspecialchars(parse_url($resultado['portal'], PHP_URL_HOST)) ?>
                <span class="text-sm font-normal text-gray-500">(<?= count($resultado['enlaces']) ?> enlaces)</span>
            </h2>

            <?php if (empty($resultado['enlaces'])): ?>
            <p class="text-gray-500">No se encontraron enlaces de artículos en este portal.</p>
            <?php else: ?>
            <form method="POST">
                <div class="mb-4 flex gap-2">
                    <button type="button" onclick="seleccionarTodos()" class="text-sm text-blue-600 hover:underline">
                        Seleccionar todos los nuevos
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" onclick="deseleccionarTodos()" class="text-sm text-gray-600 hover:underline">
                        Deseleccionar todos
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto border rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="p-2 w-10"></th>
                                <th class="p-2 text-left">Título</th>
                                <th class="p-2 text-left w-24">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultado['enlaces'] as $i => $enlace): ?>
                            <tr class="border-b <?= $enlace['procesada'] ? 'bg-gray-50 opacity-50' : 'hover:bg-green-50' ?>">
                                <td class="p-2 text-center">
                                    <?php if (!$enlace['procesada']): ?>
                                    <input type="checkbox" name="urls[<?= $i ?>]" value="<?= htmlspecialchars($enlace['url']) ?>" class="url-checkbox">
                                    <input type="hidden" name="titulos[<?= $i ?>]" value="<?= htmlspecialchars($enlace['titulo']) ?>">
                                    <?php else: ?>
                                    <span class="text-gray-400">✓</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2">
                                    <a href="<?= htmlspecialchars($enlace['url']) ?>" target="_blank"
                                       class="text-blue-600 hover:underline" title="<?= htmlspecialchars($enlace['url']) ?>">
                                        <?= htmlspecialchars($enlace['titulo']) ?>
                                    </a>
                                </td>
                                <td class="p-2">
                                    <?php if ($enlace['procesada']): ?>
                                    <span class="text-gray-500 text-xs">Ya procesada</span>
                                    <?php else: ?>
                                    <span class="text-green-600 text-xs">Nueva</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="importar_seleccionados" value="1"
                            class="bg-forest-600 hover:bg-forest-700 text-white font-bold py-2 px-6 rounded-lg transition">
                        📥 Agregar seleccionados a la cola
                    </button>
                    <p class="text-xs text-gray-500 mt-2">
                        Los enlaces se agregarán a "pendientes". Luego ejecuta "Buscar y Generar" para procesarlos.
                    </p>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($resultado && isset($resultado['id'])): ?>
        <!-- Resultado de importación directa -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 mt-6">
            <h2 class="text-lg font-bold text-green-700 mb-2">✅ Artículo importado correctamente</h2>
            <ul class="text-sm text-green-700 space-y-1">
                <li><strong>ID:</strong> <?= $resultado['id'] ?></li>
                <li><strong>Título:</strong> <?= htmlspecialchars($resultado['titulo']) ?></li>
                <li><strong>Contenido:</strong> <?= number_format($resultado['contenido_chars']) ?> caracteres</li>
                <li><strong>Región:</strong> <?= $resultado['region'] ?> <?= $resultado['pais'] ? "({$resultado['pais']})" : '' ?></li>
            </ul>
            <a href="revisar.php?id=<?= $resultado['id'] ?>" class="inline-block mt-3 text-forest-600 hover:underline">
                → Revisar artículo
            </a>
        </div>
        <?php endif; ?>

        <!-- Ayuda -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mt-6">
            <h3 class="font-bold text-blue-800 mb-2">💡 ¿Cuándo usar esto?</h3>
            <ul class="text-sm text-blue-700 space-y-1">
                <li><strong>URL directa:</strong> Cuando encontraste una noticia específica que querés importar.</li>
                <li><strong>Escanear portal:</strong> Para explorar un sitio de noticias y elegir varios artículos.</li>
            </ul>
            <p class="text-sm text-blue-600 mt-3">
                <strong>Nota:</strong> Esta herramienta usa Tavily para extraer el contenido y OpenAI para generar el artículo,
                igual que la búsqueda automática, pero vos elegís las URLs.
            </p>
        </div>
    </main>

    <script>
        function seleccionarTodos() {
            document.querySelectorAll('.url-checkbox').forEach(cb => cb.checked = true);
        }
        function deseleccionarTodos() {
            document.querySelectorAll('.url-checkbox').forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>
