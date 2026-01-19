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

// Publicar artículos programados cuya fecha ya pasó
$publicadosVencidos = publicarProgramadosVencidos();

// Obtener datos
$estadisticas = obtenerEstadisticas();
$borradores = obtenerArticulos('borrador', 20);
$programados = obtenerArticulosProgramados();
$publicados = obtenerArticulos('publicado', 10);
$contadorRegional = obtenerContadorRegional();
$pendientes = contarPendientes();
$regionSugerida = sugerirRegion();
$proximaFecha = calcularProximaFechaPublicacion(INTERVALO_PUBLICACION_HORAS);

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
                <nav class="flex flex-wrap gap-4">
                    <a href="importar_url.php" class="hover:text-green-200 transition">🔗 Importar URL</a>
                    <a href="stories.php" class="hover:text-green-200 transition">Web Stories</a>
                    <a href="sitios.php" class="hover:text-green-200 transition">Sitios</a>
                    <a href="subir_imagen.php" class="hover:text-green-200 transition">📤 Imagen</a>
                    <a href="migrar_imagenes.php" class="hover:text-green-200 transition">🖼️ Migrar</a>
                    <a href="../" class="hover:text-green-200 transition">Ver Blog</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <?php if ($publicadosVencidos > 0): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            ✅ Se publicaron automáticamente <?= $publicadosVencidos ?> artículo(s) programado(s)
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
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
                    <span class="text-3xl">⏰</span>
                    <div>
                        <p class="text-2xl font-bold text-orange-600"><?= count($programados) ?></p>
                        <p class="text-sm text-gray-500">Programados</p>
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

        <!-- Próxima publicación -->
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-3">
                <span class="text-2xl">⏰</span>
                <div>
                    <p class="text-sm text-orange-700">
                        <strong>Próxima publicación programada:</strong>
                        <?= date('d/m/Y H:i', strtotime($proximaFecha)) ?>
                        (intervalo: <?= INTERVALO_PUBLICACION_HORAS ?> horas)
                    </p>
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

        <!-- Artículos programados -->
        <?php if (!empty($programados)): ?>
        <div class="card mb-8">
            <h2 class="text-lg font-bold mb-4">⏰ Artículos programados (<?= count($programados) ?>)</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Título</th>
                            <th class="text-left py-2 px-3">Región</th>
                            <th class="text-left py-2 px-3">Fecha programada</th>
                            <th class="text-right py-2 px-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programados as $art): ?>
                        <tr class="border-b hover:bg-orange-50">
                            <td class="py-3 px-3">
                                <span class="font-medium"><?= htmlspecialchars(mb_substr($art['titulo'], 0, 50)) ?>...</span>
                            </td>
                            <td class="py-3 px-3">
                                <?= $art['region'] === 'sudamerica' ? '🌎' : '🌐' ?>
                            </td>
                            <td class="py-3 px-3">
                                <span class="text-sm text-orange-600 font-medium">
                                    <?= date('d/m/Y H:i', strtotime($art['fecha_programada'])) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <button onclick="publicarAhora(<?= $art['id'] ?>)" class="text-green-600 hover:text-green-700 text-sm" title="Publicar ahora">
                                    🚀 Publicar ya
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

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
                    <div class="flex gap-3 p-3 bg-gray-50 rounded-lg" id="publicado-<?= $art['id'] ?>">
                        <?php if (!empty($art['imagen_url'])): ?>
                        <img src="<?= htmlspecialchars($art['imagen_url']) ?>" alt="" class="w-20 h-20 object-cover rounded-lg flex-shrink-0">
                        <?php else: ?>
                        <div class="w-20 h-20 bg-forest-100 rounded-lg flex items-center justify-center text-2xl flex-shrink-0">🌱</div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-sm truncate"><?= htmlspecialchars(mb_substr($art['titulo'], 0, 50)) ?>...</h3>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= $art['region'] === 'sudamerica' ? '🌎' : '🌐' ?>
                                <?= date('d/m/Y', strtotime($art['fecha_publicacion'])) ?>
                                • 👁️ <?= $art['vistas'] ?>
                            </p>
                            <div class="flex gap-2 mt-2">
                                <a href="../scriptum.php?titulus=<?= urlencode($art['slug']) ?>" target="_blank"
                                   class="text-xs px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded">
                                    Ver
                                </a>
                                <button onclick="despublicarArticulo(<?= $art['id'] ?>)"
                                        class="text-xs px-2 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded">
                                    Despublicar
                                </button>
                                <button onclick="eliminarArticulo(<?= $art['id'] ?>)"
                                        class="text-xs px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded">
                                    Eliminar
                                </button>
                            </div>
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
            btn.innerHTML = '⏳ Buscando con Tavily...';
            resultado.classList.remove('hidden', 'bg-green-100', 'bg-red-100');
            resultado.innerHTML = '<div class="text-center"><span class="animate-pulse">Buscando noticias...</span></div>';
            resultado.classList.remove('hidden');

            try {
                console.log('🚀 Iniciando generación de artículos...');
                const response = await fetch('api/generar_articulos.php', {
                    method: 'POST'
                });
                const data = await response.json();

                // Debug en consola
                console.log('📊 Respuesta completa:', data);

                // Mostrar informe detallado en modal
                mostrarInformeGeneracion(data);

                resultado.classList.add(data.success ? 'bg-green-100' : 'bg-red-100');
                resultado.innerHTML = `${data.message || data.error} <button onclick="document.getElementById('modalInforme').classList.remove('hidden')" class="ml-2 text-blue-600 underline">Ver informe detallado</button>`;

            } catch (error) {
                console.error('💥 Error:', error);
                resultado.classList.add('bg-red-100');
                resultado.innerHTML = 'Error de conexión: ' + error.message;
            }

            btn.disabled = false;
            btn.innerHTML = '🔄 Buscar y Generar Artículos';
        }

        function mostrarInformeGeneracion(data) {
            const modal = document.getElementById('modalInforme');
            const contenido = document.getElementById('modalInformeContenido');

            let html = '<div class="space-y-6">';

            // Resumen
            html += `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-blue-600">${data.debug?.total_candidatas || 0}</p>
                        <p class="text-xs text-gray-600">Candidatas Tavily</p>
                    </div>
                    <div class="bg-green-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-green-600">${data.debug?.candidatas_guardadas || 0}</p>
                        <p class="text-xs text-gray-600">Guardadas en cache</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-purple-600">${data.articulos_generados || 0}</p>
                        <p class="text-xs text-gray-600">Artículos generados</p>
                    </div>
                    <div class="bg-orange-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-orange-600">${data.pendientes_restantes || 0}</p>
                        <p class="text-xs text-gray-600">Pendientes restantes</p>
                    </div>
                </div>
            `;

            // Topics buscados
            if (data.debug?.topics_buscados) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2">🔍 Topics buscados en Tavily</h4>
                        <ul class="text-sm bg-gray-50 rounded p-3">
                            ${data.debug.topics_buscados.map(t => `<li class="py-1">• ${t}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            // Estadísticas de filtrado
            if (data.debug?.candidatas_estadisticas) {
                const stats = data.debug.candidatas_estadisticas;
                html += `
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2">📊 Filtrado de candidatas</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <div class="bg-green-100 p-2 rounded">✅ Aceptadas: ${stats.ok}</div>
                            <div class="bg-red-100 p-2 rounded">🔗 URL procesada: ${stats.url_procesada}</div>
                            <div class="bg-yellow-100 p-2 rounded">📝 Título similar: ${stats.titulo_similar}</div>
                            <div class="bg-gray-100 p-2 rounded">❌ URL vacía: ${stats.url_vacia}</div>
                        </div>
                    </div>
                `;
            }

            // Detalle de candidatas
            if (data.debug?.candidatas_detalle && data.debug.candidatas_detalle.length > 0) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2">📋 Detalle de candidatas (${data.debug.candidatas_detalle.length})</h4>
                        <div class="max-h-64 overflow-y-auto border rounded">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-100 sticky top-0">
                                    <tr>
                                        <th class="p-2 text-left">Título</th>
                                        <th class="p-2 text-left">Fuente</th>
                                        <th class="p-2 text-left">Estado</th>
                                        <th class="p-2 text-left">Razón</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.debug.candidatas_detalle.map(c => `
                                        <tr class="${c.estado === 'aceptada' ? 'bg-green-50' : 'bg-red-50'} border-b">
                                            <td class="p-2">${c.titulo || '(sin título)'}</td>
                                            <td class="p-2">${c.fuente}${c.preferido ? ' ⭐' : ''}</td>
                                            <td class="p-2">${c.estado === 'aceptada' ? '✅' : '❌'}</td>
                                            <td class="p-2">${c.razon}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }

            // Procesamiento
            if (data.debug?.procesamiento && data.debug.procesamiento.length > 0) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-700 mb-2">⚙️ Procesamiento</h4>
                        <div class="space-y-2 text-sm">
                            ${data.debug.procesamiento.map(p => `
                                <div class="p-2 rounded ${p.resultado?.includes('GUARDADO') ? 'bg-green-100' : 'bg-gray-100'}">
                                    <strong>${p.titulo || p.url}</strong><br>
                                    <span class="text-gray-600">${p.resultado}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Errores
            if (data.errores && data.errores.length > 0) {
                html += `
                    <div>
                        <h4 class="font-bold text-red-700 mb-2">❌ Errores</h4>
                        <ul class="text-sm bg-red-50 rounded p-3 text-red-700">
                            ${data.errores.map(e => `<li>• ${e}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            html += '</div>';

            contenido.innerHTML = html;
            modal.classList.remove('hidden');
        }

        function cerrarModalInforme() {
            document.getElementById('modalInforme').classList.add('hidden');
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
            if (confirm('¿Aprobar y PROGRAMAR este artículo?\n\nSe programará para: <?= date('d/m/Y H:i', strtotime($proximaFecha)) ?>\nSe generarán traducciones a PT, EN, FR, NL automáticamente.')) {
                cambiarEstado(id, 'publicado');
            }
        }

        function rechazar(id) {
            if (confirm('¿Rechazar este artículo?')) {
                cambiarEstado(id, 'rechazado', false);
            }
        }

        async function publicarAhora(id) {
            if (!confirm('¿Publicar AHORA este artículo?\n\nSe saltará la cola de programación.')) return;

            try {
                const response = await fetch('api/cambiar_estado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, estado: 'publicado', publicar_ahora: true })
                });
                const data = await response.json();

                if (data.success) {
                    alert('✅ Artículo publicado');
                    location.reload();
                } else {
                    alert(data.error || 'Error al publicar');
                }
            } catch (error) {
                alert('Error: ' + error.message);
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

        async function despublicarArticulo(id) {
            if (!confirm('¿Despublicar este artículo?\n\nVolverá a la lista de borradores.')) return;

            try {
                const response = await fetch('api/gestionar_articulo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, accion: 'despublicar' })
                });
                const data = await response.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                    document.getElementById('publicado-' + id)?.remove();
                    location.reload();
                } else {
                    alert('❌ ' + (data.error || 'Error al despublicar'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function eliminarArticulo(id) {
            if (!confirm('⚠️ ¿ELIMINAR este artículo?\n\nEsta acción NO se puede deshacer.\nSe eliminarán también las traducciones, reacciones y stories asociadas.')) return;

            try {
                const response = await fetch('api/gestionar_articulo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, accion: 'eliminar' })
                });
                const data = await response.json();

                if (data.success) {
                    alert('✅ ' + data.message);
                    document.getElementById('publicado-' + id)?.remove();
                } else {
                    alert('❌ ' + (data.error || 'Error al eliminar'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>

    <!-- Modal Informe de Generación -->
    <div id="modalInforme" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="bg-forest-600 text-white px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-bold">📊 Informe de Generación</h3>
                <button onclick="cerrarModalInforme()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
            <div id="modalInformeContenido" class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</body>
</html>
