<?php
/**
 * TERRApp Blog - Panel de Administración
 */

require_once __DIR__ . '/includes/auth.php';

// Verificar acceso
if (!verificarAcceso()) {
    mostrarAccesoDenegado();
}

require_once __DIR__ . '/includes/functions.php';

// Obtener datos
$estadisticas = obtenerEstadisticas();
$borradores = obtenerArticulos('borrador', 20);
$publicados = obtenerArticulos('publicado', 10);
$contadorRegional = obtenerContadorRegional();
$pendientes = contarPendientes();
$regionSugerida = sugerirRegion();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - TERRApp Blog</title>
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
                            200: '#e0ccb0',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .card { @apply bg-white rounded-xl shadow-md p-6 }
        .btn-primary { @apply bg-forest-600 hover:bg-forest-700 text-white font-semibold py-2 px-4 rounded-lg transition }
        .btn-secondary { @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg transition }
        .btn-danger { @apply bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition }
    </style>
</head>
<body class="bg-earth-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-forest-600 to-forest-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🌱</span>
                    <div>
                        <h1 class="text-xl font-bold">TERRApp Blog</h1>
                        <p class="text-sm text-green-200">Panel de Administración</p>
                    </div>
                </div>
                <nav class="flex gap-4">
                    <a href="sitios.php" class="hover:text-green-200 transition">Sitios Preferidos</a>
                    <a href="../" class="hover:text-green-200 transition">Ver Blog</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="card">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">📝</span>
                    <div>
                        <p class="text-2xl font-bold text-forest-600"><?= $estadisticas['total_borradores'] ?? 0 ?></p>
                        <p class="text-sm text-gray-500">Borradores</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">✅</span>
                    <div>
                        <p class="text-2xl font-bold text-forest-600"><?= $estadisticas['total_publicados'] ?? 0 ?></p>
                        <p class="text-sm text-gray-500">Publicados</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">👁️</span>
                    <div>
                        <p class="text-2xl font-bold text-forest-600"><?= number_format($estadisticas['total_vistas'] ?? 0) ?></p>
                        <p class="text-sm text-gray-500">Vistas totales</p>
                        <p class="text-xs text-gray-400">(<?= number_format($estadisticas['total_vistas_unicas'] ?? 0) ?> únicas)</p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">📦</span>
                    <div>
                        <p class="text-2xl font-bold text-forest-600"><?= $pendientes ?></p>
                        <p class="text-sm text-gray-500">Pendientes (cache)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ratio Regional -->
        <div class="card mb-8">
            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                🌎 Balance Regional
                <span class="text-sm font-normal text-gray-500">(Objetivo: <?= $contadorRegional['ratio_objetivo'] ?>:1 Sudamérica:Internacional)</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-3xl font-bold text-green-600"><?= $contadorRegional['contador_sudamerica'] ?></p>
                    <p class="text-sm text-gray-600">🌎 Sudamérica</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-3xl font-bold text-blue-600"><?= $contadorRegional['contador_internacional'] ?></p>
                    <p class="text-sm text-gray-600">🌐 Internacional</p>
                </div>
                <div class="text-center p-4 <?= $contadorRegional['ratio_actual'] >= $contadorRegional['ratio_objetivo'] ? 'bg-green-100' : 'bg-yellow-100' ?> rounded-lg">
                    <p class="text-3xl font-bold <?= $contadorRegional['ratio_actual'] >= $contadorRegional['ratio_objetivo'] ? 'text-green-600' : 'text-yellow-600' ?>"><?= $contadorRegional['ratio_actual'] ?>:1</p>
                    <p class="text-sm text-gray-600">Ratio actual</p>
                </div>
            </div>
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-sm">
                    <strong>Sugerencia:</strong>
                    <?php if ($regionSugerida === 'sudamerica'): ?>
                        <span class="text-green-600">🌎 Priorizar noticias de Sudamérica para mantener el balance</span>
                    <?php else: ?>
                        <span class="text-blue-600">🌐 Puedes publicar noticias internacionales</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Acciones -->
        <div class="card mb-8">
            <h2 class="text-lg font-bold mb-4">⚡ Acciones Rápidas</h2>
            <div class="flex flex-wrap gap-3">
                <button onclick="generarArticulos()" class="btn-primary" id="btnGenerar">
                    🔄 Buscar y Generar Artículos
                </button>
                <button onclick="exportarJSON()" class="btn-secondary">
                    📤 Exportar JSON
                </button>
                <button onclick="generarRSS()" class="btn-secondary">
                    📡 Generar RSS
                </button>
            </div>
            <div id="resultado" class="mt-4 hidden p-4 rounded-lg"></div>
        </div>

        <!-- Borradores pendientes -->
        <div class="card mb-8">
            <h2 class="text-lg font-bold mb-4">📝 Borradores pendientes de revisión</h2>
            <?php if (empty($borradores)): ?>
                <p class="text-gray-500 text-center py-8">No hay artículos pendientes de revisión</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-3">Título</th>
                                <th class="text-left py-2 px-3">Región</th>
                                <th class="text-left py-2 px-3">Categoría</th>
                                <th class="text-left py-2 px-3">Fecha</th>
                                <th class="text-right py-2 px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borradores as $art): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-3">
                                    <a href="revisar.php?id=<?= $art['id'] ?>" class="text-forest-600 hover:underline font-medium">
                                        <?= htmlspecialchars(mb_substr($art['titulo'], 0, 60)) ?><?= mb_strlen($art['titulo']) > 60 ? '...' : '' ?>
                                    </a>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($art['fuente_nombre'] ?? 'Sin fuente') ?></p>
                                </td>
                                <td class="py-3 px-3">
                                    <?= $art['region'] === 'sudamerica' ? '🌎' : '🌐' ?>
                                    <span class="text-sm"><?= htmlspecialchars($art['pais_origen'] ?? ucfirst($art['region'])) ?></span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="text-sm bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($art['categoria']) ?></span>
                                </td>
                                <td class="py-3 px-3 text-sm text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($art['fecha_creacion'])) ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <a href="revisar.php?id=<?= $art['id'] ?>" class="text-forest-600 hover:text-forest-700 mr-2">✏️</a>
                                    <button onclick="aprobar(<?= $art['id'] ?>)" class="text-green-600 hover:text-green-700 mr-2">✅</button>
                                    <button onclick="rechazar(<?= $art['id'] ?>)" class="text-red-600 hover:text-red-700">❌</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Últimos publicados -->
        <div class="card">
            <h2 class="text-lg font-bold mb-4">✅ Últimos publicados</h2>
            <?php if (empty($publicados)): ?>
                <p class="text-gray-500 text-center py-8">No hay artículos publicados aún</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($publicados as $art): ?>
                    <div class="flex gap-3 p-3 bg-gray-50 rounded-lg">
                        <?php if (!empty($art['imagen_url'])): ?>
                        <img src="<?= htmlspecialchars($art['imagen_url']) ?>" alt="" class="w-20 h-20 object-cover rounded-lg">
                        <?php else: ?>
                        <div class="w-20 h-20 bg-forest-100 rounded-lg flex items-center justify-center text-2xl">🌱</div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <h3 class="font-medium text-sm"><?= htmlspecialchars(mb_substr($art['titulo'], 0, 50)) ?>...</h3>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= $art['region'] === 'sudamerica' ? '🌎' : '🌐' ?>
                                <?= date('d/m/Y', strtotime($art['fecha_publicacion'])) ?>
                                • 👁️ <?= $art['vistas'] ?> (<?= $art['vistas_unicas'] ?? 0 ?> únicas)
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        async function generarArticulos() {
            const btn = document.getElementById('btnGenerar');
            const resultado = document.getElementById('resultado');

            btn.disabled = true;
            btn.innerHTML = '⏳ Generando...';
            resultado.classList.remove('hidden', 'bg-green-100', 'bg-red-100');

            try {
                console.log('🚀 Iniciando generación de artículos...');
                const response = await fetch('api/generar_articulos.php', {
                    method: 'POST'
                });
                const data = await response.json();

                // Debug en consola
                console.log('📊 Respuesta completa:', data);
                if (data.debug) {
                    console.log('🔍 DEBUG INFO:');
                    console.log('  - Inicio:', data.debug.inicio);
                    console.log('  - Pendientes inicial:', data.debug.pendientes_inicial);
                    console.log('  - Topics buscados:', data.debug.topics_buscados);
                    console.log('  - Total candidatas:', data.debug.total_candidatas);
                    console.log('  - Candidatas guardadas:', data.debug.candidatas_guardadas);
                    console.log('  - Pendientes después:', data.debug.pendientes_despues_guardar);
                    console.log('  - Procesamiento:', data.debug.procesamiento);
                    console.log('  - Fin:', data.debug.fin);
                }
                if (data.errores && data.errores.length > 0) {
                    console.log('❌ Errores:', data.errores);
                }

                resultado.classList.add(data.success ? 'bg-green-100' : 'bg-red-100');

                // Mostrar mensaje más detallado
                let html = data.message || data.error || 'Operación completada';
                if (data.debug && data.debug.procesamiento && data.debug.procesamiento.length > 0) {
                    html += '<br><br><strong>Detalle:</strong><ul class="text-sm mt-2 text-left">';
                    data.debug.procesamiento.forEach(p => {
                        html += `<li>• ${p.url}... → ${p.resultado}</li>`;
                    });
                    html += '</ul>';
                }
                resultado.innerHTML = html;
                resultado.classList.remove('hidden');

                if (data.success && data.articulos_generados > 0) {
                    setTimeout(() => location.reload(), 3000);
                }
            } catch (error) {
                console.error('💥 Error:', error);
                resultado.classList.add('bg-red-100');
                resultado.innerHTML = 'Error de conexión: ' + error.message;
                resultado.classList.remove('hidden');
            }

            btn.disabled = false;
            btn.innerHTML = '🔄 Buscar y Generar Artículos';
        }

        async function cambiarEstado(id, estado, genTraducciones = true) {
            // Mostrar indicador de carga
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'loadingOverlay';
            loadingDiv.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
            loadingDiv.innerHTML = `
                <div class="bg-white rounded-lg p-6 text-center shadow-xl">
                    <div class="animate-spin text-4xl mb-3">🌱</div>
                    <p class="font-medium">${estado === 'publicado' ? 'Aprobando y generando traducciones...' : 'Cambiando estado...'}</p>
                    <p class="text-sm text-gray-500 mt-1">Esto puede demorar unos segundos</p>
                </div>
            `;
            document.body.appendChild(loadingDiv);

            try {
                const response = await fetch('api/cambiar_estado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, estado, generar_traducciones: genTraducciones })
                });
                const data = await response.json();

                loadingDiv.remove();

                if (data.success) {
                    let msg = data.message;
                    if (data.traducciones_generadas > 0) {
                        msg += `\n\nTraducciones generadas: ${data.traducciones_generadas}`;
                    }
                    if (data.errores_traducciones && data.errores_traducciones.length > 0) {
                        msg += '\n\nAlgunos errores:\n' + data.errores_traducciones.join('\n');
                    }
                    alert(msg);
                    location.reload();
                } else {
                    alert(data.error || 'Error al cambiar estado');
                }
            } catch (error) {
                loadingDiv.remove();
                alert('Error de conexión: ' + error.message);
            }
        }

        function aprobar(id) {
            if (confirm('¿Aprobar y publicar este artículo?\n\nSe generarán traducciones a PT, EN, FR, NL automáticamente.')) {
                cambiarEstado(id, 'publicado');
            }
        }

        function rechazar(id) {
            if (confirm('¿Rechazar este artículo?')) {
                cambiarEstado(id, 'rechazado', false);
            }
        }

        async function exportarJSON() {
            try {
                const response = await fetch('api/exportar_json.php');
                const data = await response.json();
                alert(data.message || data.error);
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function generarRSS() {
            try {
                const response = await fetch('api/generar_rss.php');
                const data = await response.json();
                alert(data.message || data.error);
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
