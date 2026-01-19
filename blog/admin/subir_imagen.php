<?php
/**
 * TERRApp Blog - Subir imagen manualmente a un artículo
 * Para casos donde la descarga automática falla (ej: Cloudflare)
 */

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
    $articuloSeleccionado = obtenerArticuloPorId((int)$_GET['id']);
}

// Procesar subida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir'])) {
    $articuloId = (int)($_POST['articulo_id'] ?? 0);

    if ($articuloId <= 0) {
        $error = 'Selecciona un artículo válido';
    } elseif (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Extensión PHP bloqueó la subida'
        ];
        $errorCode = $_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $uploadErrors[$errorCode] ?? 'Error desconocido al subir';
    } else {
        // Validar tipo de archivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $error = 'Tipo de archivo no permitido. Solo JPG, PNG, GIF o WebP';
        } else {
            // Determinar extensión
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            $extension = $extensions[$mimeType];

            // Crear directorio si no existe
            $uploadDir = __DIR__ . '/../uploads/articulos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Nombre único
            $filename = "art_{$articuloId}_" . time() . ".{$extension}";
            $filepath = $uploadDir . $filename;

            // Mover archivo
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
                // Actualizar en BD
                $relativePath = "../uploads/articulos/{$filename}";
                $pdo = getConnection();
                $stmt = $pdo->prepare("UPDATE blog_articulos SET imagen_url = ? WHERE id = ?");
                $stmt->execute([$relativePath, $articuloId]);

                // Regenerar JSON si está publicado
                $articulo = obtenerArticuloPorId($articuloId);
                if ($articulo && $articulo['estado'] === 'publicado') {
                    exportarArticulosJSON();
                }

                $mensaje = "Imagen subida correctamente para el artículo #{$articuloId}";
                $articuloSeleccionado = obtenerArticuloPorId($articuloId);
            } else {
                $error = 'Error al guardar el archivo en el servidor';
            }
        }
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
            <?= htmlspecialchars($error) ?>
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
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-forest-500"
                           onchange="previewNueva(this)">
                    <p class="text-xs text-gray-500 mt-1">Formatos: JPG, PNG, GIF, WebP. Máx: 5MB</p>
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
