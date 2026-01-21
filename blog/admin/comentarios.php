<?php
/**
 * TERRApp Blog - Moderación de Comentarios
 */

require_once __DIR__ . '/includes/auth.php';

// Verificar acceso
if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/config/database.php';

$pdo = getConnection();

// Procesar acciones
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $comentarioId = (int)($_POST['comentario_id'] ?? 0);

    if ($comentarioId > 0) {
        switch ($action) {
            case 'aprobar':
                $stmt = $pdo->prepare("UPDATE blog_comentarios SET estado = 'aprobado' WHERE id = ?");
                $stmt->execute([$comentarioId]);
                $mensaje = 'Comentario aprobado';
                $tipoMensaje = 'success';
                break;

            case 'rechazar':
                $stmt = $pdo->prepare("UPDATE blog_comentarios SET estado = 'rechazado' WHERE id = ?");
                $stmt->execute([$comentarioId]);
                $mensaje = 'Comentario rechazado';
                $tipoMensaje = 'warning';
                break;

            case 'eliminar':
                // Primero eliminar likes asociados
                $pdo->prepare("DELETE FROM blog_comentarios_likes WHERE comentario_id = ?")->execute([$comentarioId]);
                // Eliminar respuestas (comentarios hijos)
                $pdo->prepare("DELETE FROM blog_comentarios WHERE parent_id = ?")->execute([$comentarioId]);
                // Eliminar el comentario
                $stmt = $pdo->prepare("DELETE FROM blog_comentarios WHERE id = ?");
                $stmt->execute([$comentarioId]);
                $mensaje = 'Comentario eliminado';
                $tipoMensaje = 'danger';
                break;

            case 'marcar_admin':
                $stmt = $pdo->prepare("UPDATE blog_comentarios SET es_admin = 1 WHERE id = ?");
                $stmt->execute([$comentarioId]);
                $mensaje = 'Marcado como respuesta oficial';
                $tipoMensaje = 'success';
                break;
        }
    }
}

// Filtros
$filtroEstado = $_GET['estado'] ?? 'todos';
$filtroArticulo = (int)($_GET['articulo'] ?? 0);

// Construir query
$whereClause = "1=1";
$params = [];

if ($filtroEstado !== 'todos') {
    $whereClause .= " AND c.estado = ?";
    $params[] = $filtroEstado;
}

if ($filtroArticulo > 0) {
    $whereClause .= " AND c.articulo_id = ?";
    $params[] = $filtroArticulo;
}

// Obtener comentarios
$sql = "
    SELECT c.*, a.titulo as articulo_titulo, a.slug as articulo_slug
    FROM blog_comentarios c
    LEFT JOIN blog_articulos a ON c.articulo_id = a.id
    WHERE {$whereClause}
    ORDER BY c.fecha_creacion DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM blog_comentarios
")->fetch(PDO::FETCH_ASSOC);

// Artículos para filtro
$articulos = $pdo->query("
    SELECT DISTINCT a.id, a.titulo
    FROM blog_articulos a
    INNER JOIN blog_comentarios c ON a.id = c.articulo_id
    ORDER BY a.titulo
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderación de Comentarios - TERRApp Blog</title>
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
    <style>
        .card { @apply bg-white rounded-xl shadow-md p-6 }
        .btn { @apply font-semibold py-1.5 px-3 rounded-lg transition text-sm }
        .btn-success { @apply bg-green-500 hover:bg-green-600 text-white }
        .btn-warning { @apply bg-yellow-500 hover:bg-yellow-600 text-white }
        .btn-danger { @apply bg-red-500 hover:bg-red-600 text-white }
        .btn-info { @apply bg-blue-500 hover:bg-blue-600 text-white }
        .badge { @apply inline-block px-2 py-0.5 rounded-full text-xs font-semibold }
    </style>
</head>
<body class="bg-earth-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-forest-600 to-forest-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">💬</span>
                    <div>
                        <h1 class="text-xl font-bold">Moderación de Comentarios</h1>
                        <p class="text-sm text-green-200">TERRApp Blog Admin</p>
                    </div>
                </div>
                <nav class="flex gap-4">
                    <a href="index.php" class="hover:text-green-200 transition">← Volver al Panel</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php if ($mensaje): ?>
        <div class="mb-6 p-4 rounded-lg <?= $tipoMensaje === 'success' ? 'bg-green-100 text-green-800' : ($tipoMensaje === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="card text-center">
                <div class="text-3xl font-bold text-gray-700"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-500">Total</div>
            </div>
            <div class="card text-center">
                <div class="text-3xl font-bold text-yellow-600"><?= $stats['pendientes'] ?></div>
                <div class="text-sm text-gray-500">Pendientes</div>
            </div>
            <div class="card text-center">
                <div class="text-3xl font-bold text-green-600"><?= $stats['aprobados'] ?></div>
                <div class="text-sm text-gray-500">Aprobados</div>
            </div>
            <div class="card text-center">
                <div class="text-3xl font-bold text-red-600"><?= $stats['rechazados'] ?></div>
                <div class="text-sm text-gray-500">Rechazados</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select name="estado" class="border rounded-lg px-3 py-2">
                        <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="aprobado" <?= $filtroEstado === 'aprobado' ? 'selected' : '' ?>>Aprobados</option>
                        <option value="rechazado" <?= $filtroEstado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Artículo</label>
                    <select name="articulo" class="border rounded-lg px-3 py-2">
                        <option value="0">Todos los artículos</option>
                        <?php foreach ($articulos as $art): ?>
                        <option value="<?= $art['id'] ?>" <?= $filtroArticulo === $art['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(mb_substr($art['titulo'], 0, 50)) ?>...
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-info">Filtrar</button>
                <a href="comentarios.php" class="btn bg-gray-200 hover:bg-gray-300 text-gray-700">Limpiar</a>
            </form>
        </div>

        <!-- Lista de comentarios -->
        <div class="space-y-4">
            <?php if (empty($comentarios)): ?>
            <div class="card text-center text-gray-500 py-12">
                <div class="text-4xl mb-4">💬</div>
                <p>No hay comentarios que mostrar</p>
            </div>
            <?php else: ?>
            <?php foreach ($comentarios as $c): ?>
            <div class="card <?= $c['estado'] === 'pendiente' ? 'border-l-4 border-yellow-500' : ($c['estado'] === 'rechazado' ? 'border-l-4 border-red-500 opacity-60' : '') ?>">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex-1">
                        <!-- Header del comentario -->
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-forest-500 text-white flex items-center justify-center font-bold">
                                <?= strtoupper(mb_substr($c['nombre'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($c['nombre']) ?>
                                    <?php if ($c['es_admin']): ?>
                                    <span class="badge bg-forest-500 text-white ml-2">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= htmlspecialchars($c['email']) ?> •
                                    <?= date('d/m/Y H:i', strtotime($c['fecha_creacion'])) ?>
                                </div>
                            </div>

                            <!-- Badge de estado -->
                            <?php if ($c['estado'] === 'pendiente'): ?>
                            <span class="badge bg-yellow-100 text-yellow-800">Pendiente</span>
                            <?php elseif ($c['estado'] === 'rechazado'): ?>
                            <span class="badge bg-red-100 text-red-800">Rechazado</span>
                            <?php else: ?>
                            <span class="badge bg-green-100 text-green-800">Aprobado</span>
                            <?php endif; ?>
                        </div>

                        <!-- Artículo -->
                        <div class="text-sm text-gray-500 mb-2">
                            En: <a href="../scriptum.php?titulus=<?= urlencode($c['articulo_slug']) ?>#comentarios"
                                   target="_blank" class="text-forest-600 hover:underline">
                                <?= htmlspecialchars(mb_substr($c['articulo_titulo'], 0, 60)) ?>...
                            </a>
                        </div>

                        <!-- Contenido -->
                        <div class="bg-gray-50 rounded-lg p-3 text-gray-700">
                            <?= nl2br(htmlspecialchars($c['contenido'])) ?>
                        </div>

                        <!-- Métricas -->
                        <div class="mt-2 text-sm text-gray-500">
                            ❤️ <?= $c['likes'] ?> likes
                            <?php if ($c['parent_id']): ?>
                            • <span class="text-blue-600">↩️ Es una respuesta</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex flex-col gap-2">
                        <?php if ($c['estado'] !== 'aprobado'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="aprobar">
                            <button type="submit" class="btn btn-success w-full">✓ Aprobar</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($c['estado'] !== 'rechazado'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="rechazar">
                            <button type="submit" class="btn btn-warning w-full">✕ Rechazar</button>
                        </form>
                        <?php endif; ?>

                        <?php if (!$c['es_admin']): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="marcar_admin">
                            <button type="submit" class="btn btn-info w-full">🌱 Oficial</button>
                        </form>
                        <?php endif; ?>

                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este comentario permanentemente?')">
                            <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="action" value="eliminar">
                            <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
            TERRApp Blog Admin &copy; 2026
        </div>
    </footer>
</body>
</html>
