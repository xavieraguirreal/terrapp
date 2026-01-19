<?php
/**
 * TERRApp Blog - Migrar imágenes externas a servidor local
 * Ejecutar una sola vez para migrar artículos existentes
 */

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';

$mensaje = '';
$resultados = [];
$totalMigradas = 0;
$totalFallidas = 0;

// Procesar migración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrar'])) {
    $pdo = getConnection();

    // Obtener artículos con imágenes externas (no locales)
    $stmt = $pdo->query("
        SELECT id, titulo, imagen_url
        FROM blog_articulos
        WHERE imagen_url IS NOT NULL
          AND imagen_url != ''
          AND imagen_url NOT LIKE '%/uploads/%'
          AND imagen_url NOT LIKE '../uploads/%'
        ORDER BY id DESC
    ");

    $articulos = $stmt->fetchAll();

    foreach ($articulos as $art) {
        $resultado = [
            'id' => $art['id'],
            'titulo' => mb_substr($art['titulo'], 0, 50) . '...',
            'url_original' => $art['imagen_url'],
            'estado' => 'pendiente'
        ];

        // Intentar descargar
        $localPath = descargarImagenArticulo($art['imagen_url'], $art['id']);

        if ($localPath) {
            // Actualizar en BD
            $updateStmt = $pdo->prepare("UPDATE blog_articulos SET imagen_url = ? WHERE id = ?");
            $updateStmt->execute([$localPath, $art['id']]);

            $resultado['estado'] = 'migrada';
            $resultado['url_local'] = $localPath;
            $totalMigradas++;
        } else {
            $resultado['estado'] = 'fallida';
            $totalFallidas++;
        }

        $resultados[] = $resultado;
    }

    // Regenerar JSON
    if ($totalMigradas > 0) {
        exportarArticulosJSON();
    }

    $mensaje = "Migración completada: {$totalMigradas} imágenes migradas, {$totalFallidas} fallidas";
}

// Contar pendientes
$pdo = getConnection();
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM blog_articulos
    WHERE imagen_url IS NOT NULL
      AND imagen_url != ''
      AND imagen_url NOT LIKE '%/uploads/%'
      AND imagen_url NOT LIKE '../uploads/%'
");
$pendientes = $stmt->fetch()['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrar Imágenes - TERRApp Blog</title>
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
                    <span class="text-3xl">🖼️</span>
                    <div>
                        <h1 class="text-xl font-bold">Migrar Imágenes</h1>
                        <p class="text-sm text-green-200">Descargar imágenes externas al servidor local</p>
                    </div>
                </div>
                <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Estado de Imágenes</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-3xl font-bold text-yellow-600"><?= $pendientes ?></p>
                    <p class="text-sm text-yellow-700">Imágenes externas (pendientes de migrar)</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-3xl font-bold text-green-600">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_articulos WHERE imagen_url LIKE '%/uploads/%' OR imagen_url LIKE '../uploads/%'");
                        echo $stmt->fetchColumn();
                        ?>
                    </p>
                    <p class="text-sm text-green-700">Imágenes locales (ya migradas)</p>
                </div>
            </div>

            <?php if ($pendientes > 0): ?>
            <form method="POST" onsubmit="return confirm('¿Iniciar migración de <?= $pendientes ?> imágenes?\n\nEsto puede demorar varios minutos.')">
                <input type="hidden" name="migrar" value="1">
                <button type="submit"
                        class="w-full bg-forest-600 hover:bg-forest-700 text-white font-bold py-3 px-6 rounded-lg transition">
                    🚀 Migrar <?= $pendientes ?> imágenes al servidor local
                </button>
            </form>
            <p class="text-sm text-gray-500 mt-2 text-center">
                Las imágenes se descargarán a <code>/blog/uploads/articulos/</code>
            </p>
            <?php else: ?>
            <div class="bg-green-100 border border-green-300 rounded-lg p-4 text-center">
                <p class="text-green-700 font-medium">✅ Todas las imágenes ya están en el servidor local</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($resultados)): ?>
        <!-- Resultados -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-bold mb-4">Resultados de Migración</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">Título</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $r): ?>
                        <tr class="border-b <?= $r['estado'] === 'migrada' ? 'bg-green-50' : 'bg-red-50' ?>">
                            <td class="px-3 py-2"><?= $r['id'] ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($r['titulo']) ?></td>
                            <td class="px-3 py-2">
                                <?php if ($r['estado'] === 'migrada'): ?>
                                    <span class="text-green-600">✅ Migrada</span>
                                <?php else: ?>
                                    <span class="text-red-600">❌ Fallida</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
