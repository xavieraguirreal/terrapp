<?php
/**
 * TERRApp Blog - Gestión de Sitios Preferidos
 * Sitios donde Tavily buscará además de la búsqueda general
 */

require_once __DIR__ . '/includes/auth.php';

if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';

$mensaje = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $dominio = trim($_POST['dominio'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $prioridad = (int)($_POST['prioridad'] ?? 1);

        // Limpiar dominio (quitar https://, http://, www., trailing /)
        $dominio = preg_replace('#^https?://#', '', $dominio);
        $dominio = preg_replace('#^www\.#', '', $dominio);
        $dominio = rtrim($dominio, '/');

        if ($dominio && $nombre) {
            if (agregarSitioPreferido($dominio, $nombre, $prioridad)) {
                $mensaje = "Sitio agregado correctamente";
            } else {
                $error = "Error al agregar el sitio (puede que ya exista)";
            }
        } else {
            $error = "Dominio y nombre son requeridos";
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && eliminarSitioPreferido($id)) {
            $mensaje = "Sitio eliminado";
        } else {
            $error = "Error al eliminar";
        }
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && toggleSitioPreferido($id)) {
            $mensaje = "Estado actualizado";
        }
    }
}

$sitios = obtenerSitiosPreferidos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitios Preferidos - TERRApp Blog</title>
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
                    <span class="text-3xl">🔗</span>
                    <div>
                        <h1 class="text-xl font-bold">Sitios Preferidos</h1>
                        <p class="text-sm text-green-200">Fuentes prioritarias para noticias</p>
                    </div>
                </div>
                <nav class="flex gap-4">
                    <a href="index.php" class="hover:text-green-200 transition">← Volver al Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
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
            <h3 class="font-semibold text-blue-800 mb-2">¿Cómo funciona?</h3>
            <p class="text-blue-700 text-sm">
                Tavily buscará noticias en toda la web normalmente, pero <strong>además</strong> hará búsquedas específicas
                en los sitios que agregues aquí. Los resultados de sitios preferidos tienen prioridad al generar artículos.
            </p>
        </div>

        <!-- Formulario agregar -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Agregar Sitio</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="accion" value="agregar">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dominio</label>
                    <input type="text" name="dominio" placeholder="gba.gob.ar" required
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-forest-500 focus:outline-none">
                    <p class="text-xs text-gray-500 mt-1">Sin https:// ni www.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="nombre" placeholder="Gobierno PBA" required
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-forest-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
                    <select name="prioridad" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-forest-500 focus:outline-none">
                        <option value="1">Normal (1)</option>
                        <option value="2">Media (2)</option>
                        <option value="3" selected>Alta (3)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-forest-600 hover:bg-forest-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                        Agregar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de sitios -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-semibold mb-4">Sitios Configurados (<?= count($sitios) ?>)</h2>

            <?php if (empty($sitios)): ?>
            <p class="text-gray-500 text-center py-8">No hay sitios preferidos configurados</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($sitios as $sitio): ?>
                <div class="flex items-center justify-between p-4 border rounded-lg <?= $sitio['activo'] ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' ?>">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium"><?= htmlspecialchars($sitio['nombre']) ?></span>
                            <?php if (!$sitio['activo']): ?>
                            <span class="text-xs bg-gray-300 text-gray-600 px-2 py-0.5 rounded">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($sitio['dominio']) ?></p>
                        <p class="text-xs text-gray-400">Prioridad: <?= $sitio['prioridad'] ?> | Agregado: <?= date('d/m/Y', strtotime($sitio['fecha_agregado'])) ?></p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" class="inline">
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= $sitio['id'] ?>">
                            <button type="submit" class="px-3 py-1 text-sm rounded <?= $sitio['activo'] ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-700' : 'bg-green-100 hover:bg-green-200 text-green-700' ?>">
                                <?= $sitio['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este sitio?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $sitio['id'] ?>">
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
