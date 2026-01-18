<?php
/**
 * TERRApp Blog - Revisar/Editar Artículo
 */

require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$articulo = $id > 0 ? obtenerArticulo($id) : null;

if (!$articulo) {
    header('Location: index.php');
    exit;
}

$contadorRegional = obtenerContadorRegional();
$regionSugerida = sugerirRegion();
$categorias = obtenerCategorias();

// Procesar formulario de edición
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $datos = [
        'titulo' => $_POST['titulo'] ?? '',
        'contenido' => $_POST['contenido'] ?? '',
        'opinion_editorial' => $_POST['opinion_editorial'] ?? '',
        'tips' => array_filter(array_map('trim', explode("\n", $_POST['tips'] ?? ''))),
        'categoria' => $_POST['categoria'] ?? 'noticias',
        'tags' => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))
    ];

    if (actualizarArticulo($id, $datos)) {
        $mensaje = 'Artículo guardado correctamente';
        $articulo = obtenerArticulo($id); // Recargar
    } else {
        $mensaje = 'Error al guardar el artículo';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Artículo - TERRApp Blog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'forest': {
                            500: '#3d9268',
                            600: '#2d7553',
                            700: '#265e44',
                        },
                        'earth': {
                            50: '#faf6f1',
                            100: '#f0e6d8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-earth-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-forest-600 to-forest-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="text-green-200 hover:text-white">← Volver</a>
                    <span class="text-gray-300">|</span>
                    <h1 class="text-lg font-bold">Revisar Artículo #<?= $id ?></h1>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-sm <?= $articulo['estado'] === 'borrador' ? 'bg-yellow-500' : ($articulo['estado'] === 'publicado' ? 'bg-green-500' : 'bg-red-500') ?>">
                        <?= ucfirst($articulo['estado']) ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php if ($mensaje): ?>
        <div class="bg-green-100 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna principal - Editor -->
            <div class="lg:col-span-2">
                <form method="POST" class="bg-white rounded-xl shadow-md p-6">
                    <!-- Título -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($articulo['titulo']) ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500">
                    </div>

                    <!-- Contenido -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contenido</label>
                        <textarea name="contenido" rows="10"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500"><?= htmlspecialchars($articulo['contenido']) ?></textarea>
                    </div>

                    <!-- Opinión Editorial -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            🌱 Opinión Editorial TERRApp
                        </label>
                        <textarea name="opinion_editorial" rows="5"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500"><?= htmlspecialchars($articulo['opinion_editorial'] ?? '') ?></textarea>
                    </div>

                    <!-- Tips -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            💡 Tips para tu huerta (uno por línea)
                        </label>
                        <textarea name="tips" rows="4"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500"><?= htmlspecialchars(implode("\n", $articulo['tips'] ?? [])) ?></textarea>
                    </div>

                    <!-- Categoría y Tags -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                            <select name="categoria" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500">
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['slug'] ?>" <?= $articulo['categoria'] === $cat['slug'] ? 'selected' : '' ?>>
                                    <?= $cat['icono'] ?> <?= htmlspecialchars($cat['nombre_es']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags (separados por coma)</label>
                            <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', $articulo['tags'] ?? [])) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-forest-500 focus:border-forest-500">
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" name="guardar" class="bg-forest-600 hover:bg-forest-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            💾 Guardar cambios
                        </button>
                        <?php if ($articulo['estado'] === 'borrador'): ?>
                        <button type="button" onclick="aprobar()" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            ✅ Aprobar y publicar
                        </button>
                        <button type="button" onclick="aprobarSaltear()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            ⏭️ Publicar (omitir criterio)
                        </button>
                        <button type="button" onclick="rechazar()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            ❌ Rechazar
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Columna lateral - Info -->
            <div class="space-y-6">
                <!-- Región -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold mb-4">🌍 Información de Región</h3>
                    <div class="space-y-3">
                        <p>
                            <strong>Región:</strong>
                            <?= $articulo['region'] === 'sudamerica' ? '🌎 Sudamérica' : '🌐 Internacional' ?>
                        </p>
                        <?php if ($articulo['pais_origen']): ?>
                        <p><strong>País:</strong> <?= htmlspecialchars($articulo['pais_origen']) ?></p>
                        <?php endif; ?>
                        <hr>
                        <p class="text-sm text-gray-600">
                            <strong>Ratio actual:</strong> <?= $contadorRegional['ratio_actual'] ?>:1
                            (objetivo: <?= $contadorRegional['ratio_objetivo'] ?>:1)
                        </p>
                        <p class="text-sm <?= $regionSugerida === $articulo['region'] ? 'text-green-600' : 'text-yellow-600' ?>">
                            <?php if ($regionSugerida === $articulo['region']): ?>
                                ✅ Esta noticia cumple con el criterio regional sugerido
                            <?php else: ?>
                                ⚠️ Se sugiere priorizar noticias de <?= $regionSugerida === 'sudamerica' ? 'Sudamérica' : 'Internacional' ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Fuente -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold mb-4">📰 Fuente Original</h3>
                    <p class="text-sm mb-2"><strong><?= htmlspecialchars($articulo['fuente_nombre'] ?? 'Desconocida') ?></strong></p>
                    <?php if ($articulo['fuente_url']): ?>
                    <a href="<?= htmlspecialchars($articulo['fuente_url']) ?>" target="_blank"
                       class="text-forest-600 hover:underline text-sm break-all">
                        Ver fuente original →
                    </a>
                    <?php endif; ?>

                    <?php if ($articulo['imagen_url']): ?>
                    <div class="mt-4">
                        <img src="<?= htmlspecialchars($articulo['imagen_url']) ?>" alt="Imagen del artículo"
                             class="w-full rounded-lg">
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Contenido Original -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold mb-4">📄 Contenido Original</h3>
                    <div class="text-sm text-gray-600 max-h-64 overflow-y-auto">
                        <?= nl2br(htmlspecialchars(mb_substr($articulo['contenido_original'] ?? 'No disponible', 0, 1500))) ?>
                        <?= mb_strlen($articulo['contenido_original'] ?? '') > 1500 ? '...' : '' ?>
                    </div>
                </div>

                <!-- Metadatos -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="font-bold mb-4">📊 Metadatos</h3>
                    <div class="space-y-2 text-sm">
                        <p><strong>ID:</strong> <?= $articulo['id'] ?></p>
                        <p><strong>Slug:</strong> <?= htmlspecialchars($articulo['slug']) ?></p>
                        <p><strong>Tiempo lectura:</strong> <?= $articulo['tiempo_lectura'] ?> min</p>
                        <p><strong>Creado:</strong> <?= date('d/m/Y H:i', strtotime($articulo['fecha_creacion'])) ?></p>
                        <?php if ($articulo['fecha_publicacion']): ?>
                        <p><strong>Publicado:</strong> <?= date('d/m/Y H:i', strtotime($articulo['fecha_publicacion'])) ?></p>
                        <?php endif; ?>
                        <p><strong>Vistas:</strong> <?= $articulo['vistas'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const articuloId = <?= $id ?>;

        async function cambiarEstado(estado, saltear = false) {
            try {
                const response = await fetch('api/cambiar_estado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: articuloId,
                        estado: estado,
                        saltear_criterio: saltear
                    })
                });
                const data = await response.json();

                if (data.success) {
                    alert('Estado actualizado correctamente');
                    location.href = 'index.php';
                } else {
                    alert(data.error || 'Error al cambiar estado');
                }
            } catch (error) {
                alert('Error de conexión: ' + error.message);
            }
        }

        function aprobar() {
            if (confirm('¿Aprobar y publicar este artículo?')) {
                cambiarEstado('publicado', false);
            }
        }

        function aprobarSaltear() {
            if (confirm('¿Publicar omitiendo el criterio regional?')) {
                cambiarEstado('publicado', true);
            }
        }

        function rechazar() {
            if (confirm('¿Rechazar este artículo?')) {
                cambiarEstado('rechazado', false);
            }
        }
    </script>
</body>
</html>
