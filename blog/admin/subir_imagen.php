<?php
/**
 * TERRApp Blog - Subir imagen manualmente a un artículo
 * Para casos donde la descarga automática falla (ej: Cloudflare)
 */

// Mostrar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';

$mensaje = '';
$error = '';
$articuloSeleccionado = null;

// Obtener artículo si se pasó ID
if (isset($_GET['id'])) {
    $articuloSeleccionado = obtenerArticulo((int)$_GET['id']);
}

// Procesar subida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir'])) {
    try {
        $articuloId = (int)($_POST['articulo_id'] ?? 0);

        if ($articuloId <= 0) {
            throw new Exception('Selecciona un artículo válido');
        }

        if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión PHP bloqueó la subida'
            ];
            $errorCode = $_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new Exception($uploadErrors[$errorCode] ?? "Error desconocido al subir (código: {$errorCode})");
        }

        // Validar tipo de archivo por extensión
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = $_FILES['imagen']['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Extensión no permitida: .{$extension}. Solo JPG, PNG, GIF o WebP");
        }

        // Normalizar extensión
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        // Crear directorio si no existe
        $uploadDir = __DIR__ . '/../uploads/articulos/';

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                throw new Exception("No se pudo crear el directorio de uploads. Verificar permisos.");
            }
        }

        if (!is_writable($uploadDir)) {
            throw new Exception("El directorio de uploads no tiene permisos de escritura.");
        }

        // Nombre único
        $filename = "art_{$articuloId}_" . time() . ".{$extension}";
        $filepath = $uploadDir . $filename;

        // Mover archivo
        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
            throw new Exception("Error al mover el archivo subido. Verificar permisos del servidor.");
        }

        // Actualizar en BD (ruta desde la raíz del blog)
        $relativePath = "uploads/articulos/{$filename}";
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE blog_articulos SET imagen_url = ? WHERE id = ?");
        $stmt->execute([$relativePath, $articuloId]);

        // Regenerar JSON si está publicado
        $articulo = obtenerArticulo($articuloId);
        if ($articulo && $articulo['estado'] === 'publicado') {
            exportarArticulosJSON();
        }

        $mensaje = "Imagen subida correctamente para el artículo #{$articuloId}";
        $articuloSeleccionado = obtenerArticulo($articuloId);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener todos los artículos para el selector
$pdo = getConnection();
$stmt = $pdo->query("
    SELECT id, titulo, estado, imagen_url
    FROM blog_articulos
    ORDER BY id DESC
");
$articulos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Imagen - TERRApp Blog</title>
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
                    <span class="text-3xl">📤</span>
                    <div>
                        <h1 class="text-xl font-bold">Subir Imagen</h1>
                        <p class="text-sm text-green-200">Subir imagen manualmente a un artículo</p>
                    </div>
                </div>
                <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-2xl">
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-6">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Selector de artículo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Seleccionar Artículo
                    </label>
                    <select name="articulo_id" id="articulo_id" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-forest-500 focus:border-forest-500"
                            onchange="mostrarPreview(this.value)">
                        <option value="">-- Selecciona un artículo --</option>
                        <?php foreach ($articulos as $art): ?>
                        <option value="<?= $art['id'] ?>"
                                data-imagen="<?= htmlspecialchars($art['imagen_url'] ?? '') ?>"
                                <?= ($articuloSeleccionado && $articuloSeleccionado['id'] == $art['id']) ? 'selected' : '' ?>>
                            #<?= $art['id'] ?> - <?= htmlspecialchars(mb_substr($art['titulo'], 0, 60)) ?>...
                            [<?= $art['estado'] ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Preview imagen actual -->
                <div id="preview-actual" class="<?= $articuloSeleccionado ? '' : 'hidden' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Imagen Actual
                    </label>
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <?php if ($articuloSeleccionado && !empty($articuloSeleccionado['imagen_url'])): ?>
                        <img id="img-actual"
                             src="<?= htmlspecialchars($articuloSeleccionado['imagen_url']) ?>"
                             alt="Imagen actual"
                             class="max-h-48 rounded mx-auto"
                             onerror="this.src='../assets/images/placeholder.svg'; this.classList.add('opacity-50');">
                        <p id="url-actual" class="text-xs text-gray-500 mt-2 text-center break-all">
                            <?= htmlspecialchars($articuloSeleccionado['imagen_url']) ?>
                        </p>
                        <?php else: ?>
                        <p id="sin-imagen" class="text-gray-500 text-center py-4">Sin imagen</p>
                        <img id="img-actual" src="" alt="" class="hidden max-h-48 rounded mx-auto">
                        <p id="url-actual" class="hidden text-xs text-gray-500 mt-2 text-center break-all"></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Input de archivo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nueva Imagen
                    </label>
                    <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-forest-500"
                           onchange="previewNueva(this)">
                    <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF, WebP. Máx: <?= ini_get('upload_max_filesize') ?></p>
                </div>

                <!-- Preview nueva imagen -->
                <div id="preview-nueva" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Preview Nueva Imagen
                    </label>
                    <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                        <img id="img-nueva" src="" alt="Preview" class="max-h-48 rounded mx-auto">
                    </div>
                </div>

                <!-- Botón submit -->
                <button type="submit" name="subir" value="1"
                        class="w-full bg-forest-600 hover:bg-forest-700 text-white font-bold py-3 px-6 rounded-lg transition">
                    📤 Subir Imagen
                </button>
            </form>
        </div>

        <!-- Info -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-bold text-blue-800 mb-2">💡 ¿Cuándo usar esto?</h3>
            <ul class="text-sm text-blue-700 space-y-1">
                <li>• Cuando la descarga automática falla (sitios con Cloudflare, 403, etc.)</li>
                <li>• Para reemplazar una imagen de baja calidad</li>
                <li>• Para artículos que no tienen imagen</li>
            </ul>
            <p class="text-sm text-blue-600 mt-2">
                <strong>Tip:</strong> Descarga la imagen manualmente del sitio original y súbela aquí.
            </p>
        </div>

        <!-- Debug info -->
        <div class="mt-6 bg-gray-100 border border-gray-300 rounded-lg p-4 text-xs">
            <h3 class="font-bold text-gray-700 mb-2">ℹ️ Info del servidor</h3>
            <ul class="text-gray-600 space-y-1">
                <li>• upload_max_filesize: <?= ini_get('upload_max_filesize') ?></li>
                <li>• post_max_size: <?= ini_get('post_max_size') ?></li>
                <li>• Upload dir: <?= realpath(__DIR__ . '/../uploads/articulos/') ?: __DIR__ . '/../uploads/articulos/ (no existe)' ?></li>
            </ul>
        </div>
    </main>

    <script>
        // Data de artículos para JS
        const articulos = <?= json_encode(array_map(fn($a) => [
            'id' => $a['id'],
            'imagen_url' => $a['imagen_url']
        ], $articulos)) ?>;

        function mostrarPreview(articuloId) {
            const previewDiv = document.getElementById('preview-actual');
            const imgActual = document.getElementById('img-actual');
            const urlActual = document.getElementById('url-actual');
            const sinImagen = document.getElementById('sin-imagen');

            if (!articuloId) {
                previewDiv.classList.add('hidden');
                return;
            }

            previewDiv.classList.remove('hidden');

            const articulo = articulos.find(a => a.id == articuloId);
            if (articulo && articulo.imagen_url) {
                imgActual.src = articulo.imagen_url;
                imgActual.classList.remove('hidden');
                urlActual.textContent = articulo.imagen_url;
                urlActual.classList.remove('hidden');
                if (sinImagen) sinImagen.classList.add('hidden');
            } else {
                imgActual.classList.add('hidden');
                urlActual.classList.add('hidden');
                if (sinImagen) sinImagen.classList.remove('hidden');
            }
        }

        function previewNueva(input) {
            const previewDiv = document.getElementById('preview-nueva');
            const imgNueva = document.getElementById('img-nueva');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imgNueva.src = e.target.result;
                    previewDiv.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                previewDiv.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
