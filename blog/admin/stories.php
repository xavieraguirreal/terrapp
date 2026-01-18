<?php
/**
 * TERRApp Blog - Gestión de Web Stories
 */

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/WebStoryGenerator.php';

$generator = new WebStoryGenerator();
$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'generar') {
            $articuloId = (int)($_POST['articulo_id'] ?? 0);
            if ($articuloId) {
                $storyId = $generator->generarDesdeArticulo($articuloId);
                $mensaje = "Story generada correctamente (ID: {$storyId})";
            }
        } elseif ($accion === 'publicar') {
            $storyId = (int)($_POST['story_id'] ?? 0);
            if ($storyId && $generator->publicar($storyId)) {
                $mensaje = "Story publicada";
            }
        } elseif ($accion === 'eliminar') {
            $storyId = (int)($_POST['story_id'] ?? 0);
            if ($storyId && $generator->eliminar($storyId)) {
                $mensaje = "Story eliminada";
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stories = $generator->obtenerStories();
$articulosSinStory = $generator->obtenerArticulosSinStory();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Stories - TERRApp Blog</title>
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
                    <span class="text-3xl">📱</span>
                    <div>
                        <h1 class="text-xl font-bold">Web Stories</h1>
                        <p class="text-sm text-green-200">Historias visuales para Google Discover</p>
                    </div>
                </div>
                <nav class="flex gap-4">
                    <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-5xl">
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-2">¿Qué son las Web Stories?</h3>
            <p class="text-blue-700 text-sm">
                Son historias visuales tipo Instagram que aparecen en Google Discover.
                Se generan automáticamente desde artículos que tienen <strong>tips</strong>.
                Cada tip se convierte en un slide de la story.
            </p>
        </div>

        <!-- Generar nueva -->
        <?php if (!empty($articulosSinStory)): ?>
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Generar Story desde Artículo</h2>
            <form method="POST" class="flex gap-4 items-end">
                <input type="hidden" name="accion" value="generar">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Artículo con tips</label>
                    <select name="articulo_id" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-forest-500">
                        <option value="">Seleccionar artículo...</option>
                        <?php foreach ($articulosSinStory as $art): ?>
                        <?php $numTips = count(json_decode($art['tips'] ?? '[]', true)); ?>
                        <option value="<?= $art['id'] ?>">
                            <?= htmlspecialchars(mb_substr($art['titulo'], 0, 60)) ?>... (<?= $numTips ?> tips)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-forest-600 hover:bg-forest-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                    Generar Story
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
            <p class="text-yellow-700 text-sm">
                No hay artículos publicados con tips que no tengan story. Generá artículos con tips para crear stories.
            </p>
        </div>
        <?php endif; ?>

        <!-- Lista de Stories -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-semibold mb-4">Stories (<?= count($stories) ?>)</h2>

            <?php if (empty($stories)): ?>
            <p class="text-gray-500 text-center py-8">No hay stories creadas</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($stories as $story): ?>
                <div class="flex items-center gap-4 p-4 border rounded-lg <?= $story['estado'] === 'publicado' ? 'bg-green-50 border-green-200' : 'bg-gray-50' ?>">
                    <!-- Poster -->
                    <div class="w-16 h-24 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                        <?php if ($story['poster_url']): ?>
                        <img src="<?= htmlspecialchars($story['poster_url']) ?>" class="w-full h-full object-cover" alt="">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-2xl">📱</div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="flex-1">
                        <h3 class="font-medium"><?= htmlspecialchars(mb_substr($story['titulo'], 0, 50)) ?>...</h3>
                        <p class="text-sm text-gray-500">
                            <?= $story['estado'] === 'publicado' ? '✅ Publicada' : '⏳ Borrador' ?>
                            • <?= date('d/m/Y', strtotime($story['fecha_creacion'])) ?>
                            • 👁️ <?= $story['vistas'] ?> vistas
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php $slides = json_decode($story['slides'], true); ?>
                            <?= count($slides) ?> slides
                        </p>
                    </div>

                    <!-- Acciones -->
                    <div class="flex gap-2 flex-shrink-0">
                        <a href="../stories/story-<?= $story['slug'] ?>.html" target="_blank"
                           class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 text-blue-700 rounded">
                            Ver
                        </a>

                        <?php if ($story['estado'] !== 'publicado'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="accion" value="publicar">
                            <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                            <button type="submit" class="px-3 py-1 text-sm bg-green-100 hover:bg-green-200 text-green-700 rounded">
                                Publicar
                            </button>
                        </form>
                        <?php endif; ?>

                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta story?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                            <button type="submit" class="px-3 py-1 text-sm bg-red-100 hover:bg-red-200 text-red-700 rounded">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
