<?php
/**
 * TERRApp Blog - Ver y gestionar pendientes en cache
 */

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';

$mensaje = '';
$error = '';

// Eliminar pendiente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $id = (int)$_POST['eliminar_id'];
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM blog_noticias_pendientes WHERE id = ?");
    $stmt->execute([$id]);
    $mensaje = "Pendiente #{$id} eliminado";
}

// Limpiar todos los usados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar_usados'])) {
    $pdo = getConnection();
    $stmt = $pdo->query("DELETE FROM blog_noticias_pendientes WHERE usado = 1");
    $eliminados = $stmt->rowCount();
    $mensaje = "Se eliminaron {$eliminados} pendientes ya procesados";
}

// Limpiar todos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar_todos'])) {
    $pdo = getConnection();
    $pdo->query("TRUNCATE TABLE blog_noticias_pendientes");
    $mensaje = "Se eliminaron todos los pendientes";
}

// Obtener pendientes
$pdo = getConnection();
$stmt = $pdo->query("
    SELECT *,
           CASE WHEN usado = 0 THEN 'pendiente' ELSE 'procesado' END as estado
    FROM blog_noticias_pendientes
    ORDER BY usado ASC, fecha_obtenida DESC
");
$pendientes = $stmt->fetchAll();

$totalPendientes = count(array_filter($pendientes, fn($p) => $p['usado'] == 0));
$totalProcesados = count(array_filter($pendientes, fn($p) => $p['usado'] == 1));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendientes en Cache - TERRApp Blog</title>
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
                    <span class="text-3xl">📦</span>
                    <div>
                        <h1 class="text-xl font-bold">Pendientes en Cache</h1>
                        <p class="text-sm text-green-200">URLs esperando ser procesadas</p>
                    </div>
                </div>
                <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-6xl">
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

        <!-- Resumen -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">⏳</span>
                    <div>
                        <p class="text-2xl font-bold text-orange-600"><?= $totalPendientes ?></p>
                        <p class="text-sm text-gray-500">Pendientes (sin procesar)</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">✅</span>
                    <div>
                        <p class="text-2xl font-bold text-green-600"><?= $totalProcesados ?></p>
                        <p class="text-sm text-gray-500">Ya procesados</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">📊</span>
                    <div>
                        <p class="text-2xl font-bold text-gray-600"><?= count($pendientes) ?></p>
                        <p class="text-sm text-gray-500">Total en tabla</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <div class="flex flex-wrap gap-3">
                <a href="index.php" class="bg-forest-600 hover:bg-forest-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    🔄 Ir a Generar Artículos
                </a>
                <a href="importar_url.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    🔗 Importar más URLs
                </a>
                <?php if ($totalProcesados > 0): ?>
                <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar los <?= $totalProcesados ?> pendientes ya procesados?')">
                    <button type="submit" name="limpiar_usados" value="1" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition">
                        🧹 Limpiar procesados
                    </button>
                </form>
                <?php endif; ?>
                <?php if (count($pendientes) > 0): ?>
                <form method="POST" class="inline" onsubmit="return confirm('⚠️ ¿ELIMINAR TODOS los pendientes? Esta acción no se puede deshacer.')">
                    <button type="submit" name="limpiar_todos" value="1" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg transition">
                        🗑️ Eliminar todos
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Explicación -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <h3 class="font-bold text-blue-800 mb-2">💡 ¿Qué son los pendientes?</h3>
            <ul class="text-sm text-blue-700 space-y-1">
                <li><strong>Pendientes:</strong> URLs encontradas por Tavily o importadas manualmente, esperando ser procesadas por OpenAI.</li>
                <li><strong>Procesados:</strong> URLs que ya fueron convertidas en artículos (o descartadas por alguna razón).</li>
                <li><strong>Flujo:</strong> Tavily/Importar → Pendientes → "Buscar y Generar" → Artículos borrador</li>
            </ul>
        </div>

        <!-- Tabla de pendientes -->
        <?php if (empty($pendientes)): ?>
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
            <span class="text-6xl">📭</span>
            <p class="text-gray-500 mt-4">No hay pendientes en cache</p>
            <a href="importar_url.php" class="inline-block mt-4 text-forest-600 hover:underline">
                → Importar URLs manualmente
            </a>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-left">Título</th>
                            <th class="p-3 text-left">Fuente</th>
                            <th class="p-3 text-left">Región</th>
                            <th class="p-3 text-left">Estado</th>
                            <th class="p-3 text-left">Fecha</th>
                            <th class="p-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): ?>
                        <tr class="border-b <?= $p['usado'] ? 'bg-gray-50 opacity-60' : 'hover:bg-green-50' ?>">
                            <td class="p-3"><?= $p['id'] ?></td>
                            <td class="p-3">
                                <a href="<?= htmlspecialchars($p['url']) ?>" target="_blank"
                                   class="text-blue-600 hover:underline" title="<?= htmlspecialchars($p['url']) ?>">
                                    <?= htmlspecialchars(mb_substr($p['titulo'] ?: '(sin título)', 0, 60)) ?>...
                                </a>
                            </td>
                            <td class="p-3 text-gray-600"><?= htmlspecialchars($p['fuente']) ?></td>
                            <td class="p-3">
                                <?= $p['region'] === 'sudamerica' ? '🌎' : '🌐' ?>
                                <?= htmlspecialchars($p['region']) ?>
                            </td>
                            <td class="p-3">
                                <?php if ($p['usado']): ?>
                                <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded">Procesado</span>
                                <?php else: ?>
                                <span class="bg-orange-100 text-orange-700 text-xs px-2 py-1 rounded">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-gray-500 text-xs">
                                <?= date('d/m H:i', strtotime($p['fecha_obtenida'])) ?>
                            </td>
                            <td class="p-3 text-center">
                                <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este pendiente?')">
                                    <input type="hidden" name="eliminar_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Eliminar">
                                        🗑️
                                    </button>
                                </form>
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
