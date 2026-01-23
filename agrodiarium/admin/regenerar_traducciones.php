<?php
/**
 * TERRApp Blog - Regenerar traducciones de artículos
 *
 * Script para regenerar traducciones de artículos publicados que no las tienen
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/OpenAIClient.php';
require_once __DIR__ . '/includes/auth.php';

// Verificar autenticación
if (!verificarAcceso()) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$errores = [];
$traduccionesGeneradas = 0;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerar'])) {
    $pdo = getConnection();
    $openai = new OpenAIClient(OPENAI_API_KEY, OPENAI_MODEL);
    $idiomas = ['pt', 'en', 'fr', 'nl'];

    // Obtener IDs seleccionados o todos los que faltan
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];

    if (empty($ids) && isset($_POST['todos'])) {
        // Obtener artículos publicados sin traducciones completas
        $stmt = $pdo->query("
            SELECT DISTINCT a.id
            FROM blog_articulos a
            LEFT JOIN blog_articulos_traducciones t ON a.id = t.articulo_id
            WHERE a.estado IN ('publicado', 'programado')
            GROUP BY a.id
            HAVING COUNT(DISTINCT t.idioma) < 4
            ORDER BY a.fecha_publicacion DESC
            LIMIT 50
        ");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    foreach ($ids as $id) {
        $articulo = obtenerArticulo($id);
        if (!$articulo) continue;

        $traduccionesExistentes = obtenerTraducciones($id);

        foreach ($idiomas as $idioma) {
            if (!isset($traduccionesExistentes[$idioma])) {
                try {
                    $traduccion = $openai->traducirArticulo($articulo, $idioma);
                    if (guardarTraduccion($id, $idioma, $traduccion)) {
                        $traduccionesGeneradas++;
                    }
                } catch (Exception $e) {
                    $errores[] = "Art. {$id} ({$idioma}): " . $e->getMessage();
                }
            }
        }
    }

    $mensaje = "Se generaron {$traduccionesGeneradas} traducciones.";

    // Exportar JSON actualizado
    if ($traduccionesGeneradas > 0) {
        exportarArticulosJSON();
        $mensaje .= " JSON exportado.";
    }
}

// Obtener artículos sin traducciones completas
$pdo = getConnection();
$stmt = $pdo->query("
    SELECT
        a.id,
        a.titulo,
        a.estado,
        a.fecha_publicacion,
        COUNT(DISTINCT t.idioma) as traducciones_count,
        GROUP_CONCAT(DISTINCT t.idioma) as idiomas_existentes
    FROM blog_articulos a
    LEFT JOIN blog_articulos_traducciones t ON a.id = t.articulo_id
    WHERE a.estado IN ('publicado', 'programado')
    GROUP BY a.id
    HAVING traducciones_count < 4
    ORDER BY a.fecha_publicacion DESC
    LIMIT 100
");
$articulosSinTraducciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerar Traducciones - AGRODiarium Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <a href="index.php" class="text-blue-600 hover:underline">← Volver al panel</a>
        </div>

        <h1 class="text-3xl font-bold mb-6">Regenerar Traducciones</h1>

        <?php if ($mensaje): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Errores:</strong>
                <ul class="list-disc list-inside mt-2">
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($articulosSinTraducciones)): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                ✅ Todos los artículos tienen traducciones completas (PT, EN, FR, NL)
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="mb-4 text-gray-600">
                    Se encontraron <strong><?= count($articulosSinTraducciones) ?></strong> artículos sin traducciones completas.
                </p>

                <form method="POST">
                    <div class="mb-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="todos" value="1" checked class="w-4 h-4">
                            <span>Regenerar traducciones faltantes de todos los artículos listados</span>
                        </label>
                    </div>

                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sel.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Idiomas</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Faltan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($articulosSinTraducciones as $art):
                                    $existentes = $art['idiomas_existentes'] ? explode(',', $art['idiomas_existentes']) : [];
                                    $faltantes = array_diff(['pt', 'en', 'fr', 'nl'], $existentes);
                                ?>
                                    <tr>
                                        <td class="px-4 py-2">
                                            <input type="checkbox" name="ids[]" value="<?= $art['id'] ?>" class="w-4 h-4">
                                        </td>
                                        <td class="px-4 py-2 text-sm"><?= $art['id'] ?></td>
                                        <td class="px-4 py-2 text-sm"><?= htmlspecialchars(mb_substr($art['titulo'], 0, 50)) ?>...</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span class="px-2 py-1 rounded text-xs <?= $art['estado'] === 'publicado' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                <?= $art['estado'] ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <?php foreach (['pt', 'en', 'fr', 'nl'] as $lang): ?>
                                                <span class="<?= in_array($lang, $existentes) ? 'text-green-600' : 'text-gray-300' ?>">
                                                    <?= strtoupper($lang) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-red-600 font-medium">
                                            <?= implode(', ', array_map('strtoupper', $faltantes)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" name="regenerar" value="1"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                                onclick="return confirm('¿Regenerar traducciones? Esto puede tardar varios minutos y consumir créditos de OpenAI.')">
                            🔄 Regenerar Traducciones
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
