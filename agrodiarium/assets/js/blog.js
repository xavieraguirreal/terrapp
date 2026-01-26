/**
 * TERRApp Blog - JavaScript principal
 */

// Estado global
let articulos = [];
let articulosFiltrados = [];
let categoriaActual = 'all';
let articulosVisibles = 9;
let articuloActual = null;
let semanticSearchEnabled = false;
let semanticSearchPending = null;

/**
 * Genera HTML de imagen con fallback automático cuando falla la carga
 * @param {string} url - URL de la imagen
 * @param {string} titulo - Título para el placeholder
 * @param {string} size - Tamaño: 'lg', 'card', o vacío para normal
 * @param {string} extraClasses - Clases CSS adicionales para la imagen
 */
function generarImagenConFallback(url, titulo, size = '', extraClasses = '') {
    const tituloEscapado = escapeHtml(titulo);
    const sizeClass = size ? `placeholder-${size}` : '';

    if (!url) {
        return `<div class="w-full h-full image-placeholder ${sizeClass}">
                    <span class="placeholder-title">${tituloEscapado}</span>
                </div>`;
    }

    // Usar data attributes para el fallback (evita problemas con caracteres especiales)
    return `<div class="w-full h-full img-fallback-container" data-fallback-title="${tituloEscapado.replace(/"/g, '&quot;')}" data-fallback-size="${sizeClass}">
                <img src="${url}"
                     alt="${tituloEscapado}"
                     class="w-full h-full object-cover ${extraClasses}"
                     loading="lazy"
                     onerror="handleImageError(this)">
            </div>`;
}

/**
 * Maneja errores de carga de imagen de forma robusta
 */
function handleImageError(img) {
    const container = img.parentElement;
    if (!container) return;

    const title = container.dataset.fallbackTitle || 'Imagen no disponible';
    const sizeClass = container.dataset.fallbackSize || '';

    container.innerHTML = `<div class="w-full h-full image-placeholder ${sizeClass}">
        <span class="placeholder-title">${title}</span>
    </div>`;
}

/**
 * Maneja errores de imagen en cards del bento grid
 */
function handleBentoImageError(img) {
    const container = img.parentElement;
    if (!container) return;

    const title = container.dataset.fallbackTitle || 'Artículo';

    container.innerHTML = `<div class="w-full h-full flex flex-col items-center justify-center text-white p-4 bento-placeholder">
        <span class="text-sm font-semibold text-center line-clamp-2 mb-2">${title}</span>
        <span class="text-2xl">🌱</span>
    </div>`;
}

// ============================================
// MULTI-IDIOMA
// ============================================

/**
 * Obtiene el código de idioma base (pt, en, fr, nl)
 */
function getIdiomaBase() {
    // Obtener idioma del sistema i18n del blog
    const langFull = (typeof BLOG_I18N !== 'undefined' && BLOG_I18N.currentLang)
        ? BLOG_I18N.currentLang
        : (document.cookie.match(/terrapp_lang=([^;]+)/) || [])[1] || 'es_AR';

    // Extraer código base (es, pt, en, fr, nl)
    const base = langFull.split('_')[0];

    // Solo devolver si tenemos traducción para ese idioma
    return ['pt', 'en', 'fr', 'nl'].includes(base) ? base : null;
}

/**
 * Obtiene el contenido de un artículo en el idioma actual
 */
function getArticuloEnIdioma(articulo) {
    const idioma = getIdiomaBase();

    // Si es español o no hay idioma, devolver original
    if (!idioma) {
        return {
            titulo: articulo.titulo,
            contenido: articulo.contenido,
            opinion_editorial: articulo.opinion_editorial,
            tips: articulo.tips || []
        };
    }

    // Buscar traducción
    if (articulo.traducciones && articulo.traducciones[idioma]) {
        const trad = articulo.traducciones[idioma];
        return {
            titulo: trad.titulo || articulo.titulo,
            contenido: trad.contenido || articulo.contenido,
            opinion_editorial: trad.opinion_editorial || articulo.opinion_editorial,
            tips: trad.tips || articulo.tips || []
        };
    }

    // Si no hay traducción, devolver original
    return {
        titulo: articulo.titulo,
        contenido: articulo.contenido,
        opinion_editorial: articulo.opinion_editorial,
        tips: articulo.tips || []
    };
}

// Categorías con iconos
const CATEGORIAS = {
    'huertos-urbanos': { nombre: 'Huertos Urbanos', icono: '🌱' },
    'compostaje': { nombre: 'Compostaje', icono: '🌿' },
    'riego': { nombre: 'Riego', icono: '💧' },
    'plantas': { nombre: 'Plantas', icono: '🌻' },
    'tecnologia': { nombre: 'Tecnología', icono: '📱' },
    'recetas': { nombre: 'Recetas', icono: '🍳' },
    'comunidad': { nombre: 'Comunidad', icono: '🤝' },
    'noticias': { nombre: 'Noticias', icono: '📰' }
};

// ============================================
// INICIALIZACIÓN
// ============================================

document.addEventListener('DOMContentLoaded', async () => {
    // Cargar preferencia de tema
    loadThemePreference();

    // Actualizar contador de lista de lectura
    updateReadingListCount();

    // Configurar botón scroll-to-top
    setupScrollToTop();

    // Cargar artículos si estamos en index
    if (document.getElementById('articlesGrid')) {
        await loadArticles();
        setupSearch();
    }

    // Configurar barra de progreso en artículo
    if (document.getElementById('progressBar')) {
        setupProgressBar();
    }

    // Escuchar cambios de idioma para re-renderizar
    document.addEventListener('terrapp:langchange', () => {
        // Re-renderizar artículos en el nuevo idioma
        if (articulos.length > 0) {
            if (document.getElementById('articlesGrid')) {
                renderArticles(); // Bento Grid incluye el featured
            }
            if (articuloActual) {
                renderArticle(articuloActual);
            }
        }
    });
});

// ============================================
// CARGA DE ARTÍCULOS
// ============================================

async function loadArticles() {
    try {
        const response = await fetch('data/articulos.json');
        if (!response.ok) throw new Error('No se pudieron cargar los artículos');

        const data = await response.json();
        articulos = data.articulos || [];

        // Cargar vistas actualizadas desde la BD
        await actualizarVistasDesdeDB();

        articulosFiltrados = [...articulos];

        renderCategories();
        renderArticles();
        // renderFeatured ya no es necesario, está integrado en renderArticles con Bento Grid

    } catch (error) {
        console.error('Error cargando artículos:', error);
        document.getElementById('articlesGrid').innerHTML = `
            <div class="col-span-full text-center py-12">
                <div class="text-6xl mb-4">🌱</div>
                <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400">
                    Aún no hay artículos publicados
                </h3>
                <p class="text-gray-500">Vuelve pronto para ver contenido nuevo</p>
            </div>
        `;
    }
}

async function loadArticle(id, slug) {
    try {
        const response = await fetch('data/articulos.json');
        if (!response.ok) throw new Error('Error cargando datos');

        const data = await response.json();
        articulos = data.articulos || [];

        // Buscar artículo por ID o slug
        const articulo = id
            ? articulos.find(a => a.id == id)
            : articulos.find(a => a.slug === slug);

        if (!articulo) {
            showNotFound();
            return;
        }

        articuloActual = articulo;
        renderArticle(articulo);

        // Registrar vista y actualizar métricas
        registerView(articulo.id);

        // Cargar métricas actualizadas desde BD
        loadMetrics(articulo.id);

        // Cargar relacionados
        loadRelatedArticles(articulo);

    } catch (error) {
        console.error('Error:', error);
        showNotFound();
    }
}

// ============================================
// RENDERIZADO
// ============================================

function renderCategories() {
    const container = document.getElementById('categoriesFilter');
    if (!container) return;

    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    // Obtener categorías con artículos
    const categoriasConArticulos = new Set(articulos.map(a => a.categoria));

    let html = `
        <button onclick="filterByCategory('all')" class="category-btn ${categoriaActual === 'all' ? 'active' : ''} px-4 py-2 rounded-full font-medium transition">
            ${t('all_categories')}
        </button>
    `;

    // Mapeo de slugs a keys de traducción
    const catKeyMap = {
        'huertos-urbanos': 'cat_huertos',
        'compostaje': 'cat_compostaje',
        'riego': 'cat_riego',
        'plantas': 'cat_plantas',
        'tecnologia': 'cat_tecnologia',
        'recetas': 'cat_recetas',
        'comunidad': 'cat_comunidad',
        'noticias': 'cat_noticias'
    };

    for (const [slug, cat] of Object.entries(CATEGORIAS)) {
        if (categoriasConArticulos.has(slug)) {
            const catKey = catKeyMap[slug] || 'cat_noticias';
            const catNombre = t(catKey) || cat.nombre;
            html += `
                <button onclick="filterByCategory('${slug}')" class="category-btn ${categoriaActual === slug ? 'active' : ''} px-4 py-2 rounded-full font-medium transition">
                    ${cat.icono} ${catNombre}
                </button>
            `;
        }
    }

    container.innerHTML = html;
}

function renderArticles() {
    const container = document.getElementById('articlesGrid');
    const noResults = document.getElementById('noResults');
    const loadMoreBtn = document.getElementById('loadMore');

    if (!container) return;

    // Quitar skeletons
    container.querySelectorAll('.skeleton-card').forEach(el => el.remove());

    // Determinar si mostrar featured (solo cuando no hay filtro)
    const showFeatured = categoriaActual === 'all' && articulosFiltrados.length === articulos.length;

    if (articulosFiltrados.length === 0) {
        container.innerHTML = '';
        noResults?.classList.remove('hidden');
        loadMoreBtn?.classList.add('hidden');
        return;
    }

    noResults?.classList.add('hidden');

    // Construir HTML del Bento Grid
    let html = '';

    if (showFeatured && articulosFiltrados.length > 0) {
        // Primer artículo como featured (2x2)
        html += createBentoCard(articulosFiltrados[0], true);

        // Resto de artículos como cards normales
        const resto = articulosFiltrados.slice(1, articulosVisibles);
        html += resto.map(art => createBentoCard(art, false)).join('');
    } else {
        // Sin featured, mostrar todos como cards normales
        const visibles = articulosFiltrados.slice(0, articulosVisibles);
        html += visibles.map(art => createBentoCard(art, false)).join('');
    }

    container.innerHTML = html;

    // Mostrar/ocultar botón de cargar más
    const totalMostrados = showFeatured ? articulosVisibles : articulosVisibles;
    if (articulosFiltrados.length > totalMostrados) {
        loadMoreBtn?.classList.remove('hidden');
    } else {
        loadMoreBtn?.classList.add('hidden');
    }
}

function renderFeatured() {
    const container = document.getElementById('featuredArticle');
    if (!container || articulos.length === 0) return;

    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    const featured = articulos[0];
    const traducido = getArticuloEnIdioma(featured);

    // Traducir categoría
    const catKeyMap = {
        'huertos-urbanos': 'cat_huertos',
        'compostaje': 'cat_compostaje',
        'riego': 'cat_riego',
        'plantas': 'cat_plantas',
        'tecnologia': 'cat_tecnologia',
        'recetas': 'cat_recetas',
        'comunidad': 'cat_comunidad',
        'noticias': 'cat_noticias'
    };
    const catKey = catKeyMap[featured.categoria] || 'cat_noticias';
    const catNombre = t(catKey) || CATEGORIAS[featured.categoria]?.nombre || 'Noticias';
    const catIcono = CATEGORIAS[featured.categoria]?.icono || '📰';

    const imagenHtml = generarImagenConFallback(
        featured.imagen_url,
        traducido.titulo,
        'lg',
        'group-hover:scale-105 transition-transform duration-500'
    );

    container.innerHTML = `
        <a href="scriptum.php?titulus=${featured.slug}" class="block relative rounded-2xl overflow-hidden shadow-xl group">
            <div class="aspect-[4/3] sm:aspect-[16/9] md:aspect-[21/9] bg-gray-200 dark:bg-gray-700">
                ${imagenHtml}
            </div>
            ${featured.imagen_url ? '<div class="absolute inset-0 featured-gradient"></div>' : ''}
            <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-6 md:p-10 text-white ${featured.imagen_url ? '' : 'hidden'}">
                <span class="inline-block px-3 py-1 bg-forest-600 rounded-full text-xs sm:text-sm mb-2 sm:mb-3">
                    ${catIcono} ${catNombre}
                </span>
                <h2 class="text-lg sm:text-2xl md:text-4xl font-bold mb-1 sm:mb-2 group-hover:text-forest-200 transition line-clamp-2 sm:line-clamp-none">
                    ${escapeHtml(traducido.titulo)}
                </h2>
                <p class="text-gray-200 text-xs sm:text-sm md:text-base line-clamp-2 hidden sm:block">
                    ${escapeHtml(traducido.contenido.substring(0, 150))}...
                </p>
            </div>
        </a>
    `;
    container.classList.remove('hidden');
}

/**
 * Crea una card para el Bento Grid
 * @param {Object} art - Artículo
 * @param {boolean} isFeatured - Si es el artículo destacado (2x2)
 */
function createBentoCard(art, isFeatured = false) {
    const fecha = formatDateLocalized(art.fecha_publicacion);
    const isSaved = isInReadingList(art.id);
    const traducido = getArticuloEnIdioma(art);
    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    // Traducir categoría
    const catKeyMap = {
        'huertos-urbanos': 'cat_huertos',
        'compostaje': 'cat_compostaje',
        'riego': 'cat_riego',
        'plantas': 'cat_plantas',
        'tecnologia': 'cat_tecnologia',
        'recetas': 'cat_recetas',
        'comunidad': 'cat_comunidad',
        'noticias': 'cat_noticias'
    };
    const catKey = catKeyMap[art.categoria] || 'cat_noticias';
    const catNombre = t(catKey) || CATEGORIAS[art.categoria]?.nombre || 'Noticias';
    const catIcono = CATEGORIAS[art.categoria]?.icono || '📰';

    const totalReactions = (art.reaccion_interesante || 0) + (art.reaccion_encanta || 0) + (art.reaccion_importante || 0);

    // Badge de similitud para búsqueda semántica
    const similarityBadge = art.similitud_porcentaje
        ? `<span class="bento-similarity" title="Similitud semántica">🧠 ${art.similitud_porcentaje}</span>`
        : '';

    if (isFeatured) {
        // Card destacada con imagen de fondo y overlay
        const imagenUrl = art.imagen_url || '';
        const bgStyle = imagenUrl
            ? `background-image: url('${imagenUrl}'); background-size: cover; background-position: center;`
            : `background: linear-gradient(135deg, #2d7553 0%, #3d9268 50%, #558b2f 100%);`;

        return `
            <article class="bento-card bento-featured">
                <a href="scriptum.php?titulus=${art.slug}" class="bento-card-inner block">
                    <div class="bento-image" style="${bgStyle}"></div>
                    <div class="bento-overlay"></div>
                    ${similarityBadge ? `<div class="bento-similarity-wrapper">${similarityBadge}</div>` : ''}
                    <div class="bento-content">
                        <span class="bento-category">${catIcono} ${catNombre}</span>
                        <div class="bento-meta">
                            <span>${fecha}</span>
                            <span>•</span>
                            <span>${art.tiempo_lectura} ${t('min_read')}</span>
                            <span>•</span>
                            <span>${art.region === 'sudamerica' ? '🌎' : '🌐'}</span>
                        </div>
                        <h3 class="bento-title">${escapeHtml(traducido.titulo)}</h3>
                        <p class="bento-excerpt">${escapeHtml(traducido.contenido.substring(0, 150))}...</p>
                        <div class="bento-footer">
                            <div class="bento-stats">
                                <span class="bento-stat">👁️ ${art.vistas}</span>
                                <span class="bento-stat">❤️ ${totalReactions}</span>
                            </div>
                            <button onclick="event.preventDefault(); event.stopPropagation(); toggleSave(${art.id})"
                                    class="bento-save ${isSaved ? 'saved' : ''}">
                                <svg class="w-5 h-5" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </a>
            </article>
        `;
    }

    // Card normal - con handler de error para imágenes
    const tituloEscapado = escapeHtml(traducido.titulo).replace(/"/g, '&quot;');
    const placeholderCard = `<div class="w-full h-full flex flex-col items-center justify-center text-white p-4 bento-placeholder">
               <span class="text-sm font-semibold text-center line-clamp-2 mb-2">${escapeHtml(traducido.titulo)}</span>
               <span class="text-2xl">🌱</span>
           </div>`;
    const imagenHtml = art.imagen_url
        ? `<div class="w-full h-full img-fallback-container" data-fallback-title="${tituloEscapado}">
               <img src="${art.imagen_url}" alt="${escapeHtml(traducido.titulo)}" class="w-full h-full object-cover" loading="lazy"
                    onerror="handleBentoImageError(this)">
           </div>`
        : placeholderCard;

    return `
        <article class="bento-card">
            <a href="scriptum.php?titulus=${art.slug}" class="bento-card-inner block">
                <div class="bento-image">
                    ${imagenHtml}
                    ${similarityBadge ? `<div class="bento-similarity-wrapper">${similarityBadge}</div>` : ''}
                </div>
                <div class="bento-content">
                    <span class="bento-category">${catIcono} ${catNombre}</span>
                    <div class="bento-meta">
                        <span>${fecha}</span>
                        <span>•</span>
                        <span>${art.tiempo_lectura} ${t('min_read')}</span>
                    </div>
                    <h3 class="bento-title">${escapeHtml(traducido.titulo)}</h3>
                    <p class="bento-excerpt">${escapeHtml(traducido.contenido.substring(0, 100))}...</p>
                    <div class="bento-footer">
                        <div class="bento-stats">
                            <span class="bento-stat">👁️ ${art.vistas}</span>
                            <span class="bento-stat">❤️ ${totalReactions}</span>
                        </div>
                        <button onclick="event.preventDefault(); event.stopPropagation(); toggleSave(${art.id})"
                                class="bento-save ${isSaved ? 'saved' : ''}">
                            <svg class="w-5 h-5" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </a>
        </article>
    `;
}

// Mantener función anterior para compatibilidad (lista de lectura, etc.)
function createArticleCard(art) {
    return createBentoCard(art, false);
}

function renderArticle(art) {
    document.getElementById('articleSkeleton').classList.add('hidden');
    document.getElementById('articleContent').classList.remove('hidden');

    // Obtener contenido en el idioma actual
    const traducido = getArticuloEnIdioma(art);

    // Actualizar título de página y meta tags
    document.title = `${traducido.titulo} - TERRApp Blog`;
    document.querySelector('meta[name="description"]')?.setAttribute('content', traducido.contenido.substring(0, 160));
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', traducido.titulo);

    // Breadcrumb - usar traducción de categoría
    const categoria = CATEGORIAS[art.categoria] || { nombre: 'Noticias', icono: '📰' };
    const catKeyMap = {
        'huertos-urbanos': 'cat_huertos',
        'compostaje': 'cat_compostaje',
        'riego': 'cat_riego',
        'plantas': 'cat_plantas',
        'tecnologia': 'cat_tecnologia',
        'recetas': 'cat_recetas',
        'comunidad': 'cat_comunidad',
        'noticias': 'cat_noticias'
    };
    const catKey = catKeyMap[art.categoria] || 'cat_noticias';
    const categoriaNombre = (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(catKey) || categoria.nombre : categoria.nombre;
    document.getElementById('breadcrumbCategory').textContent = categoriaNombre;

    // Header
    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;
    document.getElementById('articleTitle').textContent = traducido.titulo;
    document.getElementById('articleDate').innerHTML += ' ' + formatDateLocalized(art.fecha_publicacion);
    document.getElementById('articleReadTime').innerHTML += ` ${art.tiempo_lectura} ${t('min_read')}`;
    document.getElementById('articleViews').innerHTML += ` ${art.vistas} ${t('views')}`;
    // Región - usar traducción para "Internacional"
    const regionTexts = {
        'es': { sudamerica: 'Sudamérica', internacional: 'Internacional' },
        'pt': { sudamerica: 'América do Sul', internacional: 'Internacional' },
        'en': { sudamerica: 'South America', internacional: 'International' },
        'fr': { sudamerica: 'Amérique du Sud', internacional: 'International' },
        'nl': { sudamerica: 'Zuid-Amerika', internacional: 'Internationaal' }
    };
    const idiomaBase = getIdiomaBase() || 'es';
    const regionText = regionTexts[idiomaBase] || regionTexts['es'];
    document.getElementById('articleRegion').innerHTML = art.region === 'sudamerica'
        ? `🌎 ${art.pais_origen || regionText.sudamerica}`
        : `🌐 ${regionText.internacional}`;

    // Imagen con fallback
    const imgContainer = document.getElementById('articleImageContainer');
    imgContainer.classList.remove('hidden');

    if (art.imagen_url) {
        const tituloEscapado = escapeHtml(traducido.titulo);
        imgContainer.innerHTML = `
            <div class="img-fallback-container" data-fallback-title="${tituloEscapado.replace(/"/g, '&quot;')}" data-fallback-size="placeholder-lg">
                <img src="${art.imagen_url}"
                     alt="${tituloEscapado}"
                     class="w-full rounded-xl shadow-lg"
                     onerror="handleImageError(this)">
            </div>`;
    } else {
        // Mostrar placeholder con título si no hay imagen
        imgContainer.innerHTML = `
            <div class="w-full h-64 md:h-96 image-placeholder placeholder-lg rounded-xl">
                <span class="placeholder-title">${escapeHtml(traducido.titulo)}</span>
            </div>`;
    }

    // Contenido - con fuente al inicio
    const fuenteHeader = art.fuente_nombre
        ? `<p class="text-sm text-gray-500 dark:text-gray-400 italic mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
               <span class="font-semibold not-italic text-gray-700 dark:text-gray-300">${escapeHtml(art.fuente_nombre)}:</span>
           </p>`
        : '';
    document.getElementById('articleBody').innerHTML = fuenteHeader + formatContent(traducido.contenido);

    // Opinión editorial
    document.getElementById('editorialContent').innerHTML = formatContent(traducido.opinion_editorial || '');

    // Tips
    if (traducido.tips && traducido.tips.length > 0) {
        document.getElementById('tipsSection').classList.remove('hidden');
        document.getElementById('tipsList').innerHTML = traducido.tips.map(tip =>
            `<li class="flex items-start gap-2">
                <span class="text-yellow-600">✓</span>
                <span>${escapeHtml(tip)}</span>
            </li>`
        ).join('');
    }

    // Reacciones
    document.getElementById('countInteresante').textContent = art.reaccion_interesante || 0;
    document.getElementById('countEncanta').textContent = art.reaccion_encanta || 0;
    document.getElementById('countImportante').textContent = art.reaccion_importante || 0;
    document.getElementById('countNoconvence').textContent = art.reaccion_noconvence || 0;

    // Total shares
    if (art.total_shares > 0) {
        document.getElementById('totalShares').textContent = t('shared_times').replace('{n}', art.total_shares);
    }

    // Fuente
    document.getElementById('sourceName').textContent = art.fuente_nombre || 'Fuente externa';
    if (art.fuente_url) {
        document.getElementById('sourceLink').href = art.fuente_url;
    }

    // Tags
    if (art.tags && art.tags.length > 0) {
        document.getElementById('tagsSection').classList.remove('hidden');
        document.getElementById('tagsList').innerHTML = art.tags.map(tag =>
            `<a href="tag/?t=${encodeURIComponent(tag)}" class="tag">#${escapeHtml(tag)}</a>`
        ).join('');
    }

    // Actualizar botón de guardar
    updateSaveButton();

    // Generar TOC después de cargar el contenido
    setTimeout(() => {
        generateTOC();
    }, 100); // Pequeño delay para asegurar que el DOM está listo

    // Generar puntos clave
    renderKeyPoints(art);

    // Preparar text-to-speech
    resetSpeechUI();

    // Cargar comentarios
    loadComments(art.id);
}

function loadRelatedArticles(currentArt) {
    // Calcular score de similitud para cada artículo
    const articlesWithScore = articulos
        .filter(a => a.id !== currentArt.id)
        .map(art => ({
            ...art,
            score: calculateSimilarityScore(currentArt, art)
        }))
        .filter(a => a.score > 0) // Solo artículos con alguna similitud
        .sort((a, b) => b.score - a.score) // Ordenar por score descendente
        .slice(0, 3);

    // Fallback: si no hay similares, mostrar de la misma categoría
    let related = articlesWithScore;
    if (related.length === 0) {
        related = articulos
            .filter(a => a.id !== currentArt.id && a.categoria === currentArt.categoria)
            .slice(0, 3);
    }

    if (related.length === 0) return;

    document.getElementById('relatedSection').classList.remove('hidden');
    document.getElementById('relatedArticles').innerHTML = related.map(art => {
        const traducido = getArticuloEnIdioma(art);
        const imagenHtml = generarImagenConFallback(art.imagen_url, traducido.titulo, 'card');
        return `
            <a href="scriptum.php?titulus=${art.slug}" class="related-card block bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition">
                <div class="h-32 bg-gray-200 dark:bg-gray-700">
                    ${imagenHtml}
                </div>
                <div class="p-4">
                    <h4 class="related-title font-semibold text-sm line-clamp-2">${escapeHtml(traducido.titulo)}</h4>
                    ${art.score ? `<div class="text-xs text-gray-400 mt-1">${Math.round(art.score * 100)}% similar</div>` : ''}
                </div>
            </a>
        `;
    }).join('');
}

/**
 * Calcula un score de similitud entre dos artículos
 * Basado en: tags compartidos, palabras del título, y categoría
 */
function calculateSimilarityScore(artA, artB) {
    let score = 0;

    // 1. Tags compartidos (peso: 40%)
    const tagsA = (artA.tags || []).map(t => t.toLowerCase());
    const tagsB = (artB.tags || []).map(t => t.toLowerCase());
    const sharedTags = tagsA.filter(t => tagsB.includes(t));

    if (tagsA.length > 0 && tagsB.length > 0) {
        const tagScore = sharedTags.length / Math.max(tagsA.length, tagsB.length);
        score += tagScore * 0.4;
    }

    // 2. Palabras comunes en el título (peso: 35%)
    const wordsA = extractKeywords(artA.titulo);
    const wordsB = extractKeywords(artB.titulo);
    const sharedWords = wordsA.filter(w => wordsB.includes(w));

    if (wordsA.length > 0 && wordsB.length > 0) {
        const wordScore = sharedWords.length / Math.max(wordsA.length, wordsB.length);
        score += wordScore * 0.35;
    }

    // 3. Misma categoría (peso: 25%)
    if (artA.categoria === artB.categoria) {
        score += 0.25;
    }

    return score;
}

/**
 * Extrae palabras clave de un texto (elimina stopwords)
 */
function extractKeywords(text) {
    if (!text) return [];

    // Stopwords en español
    const stopwords = new Set([
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'al', 'a', 'en', 'con', 'por', 'para',
        'y', 'o', 'que', 'se', 'su', 'sus', 'es', 'son',
        'como', 'pero', 'más', 'este', 'esta', 'estos', 'estas',
        'ha', 'han', 'hay', 'ser', 'está', 'están', 'fue', 'fueron',
        'the', 'a', 'an', 'of', 'to', 'in', 'for', 'on', 'with', 'and', 'or', 'is', 'are'
    ]);

    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Quitar acentos
        .split(/\s+/)
        .filter(word => word.length > 2 && !stopwords.has(word));
}

function showNotFound() {
    document.getElementById('articleSkeleton')?.classList.add('hidden');
    document.getElementById('articleContent')?.classList.add('hidden');
    document.getElementById('articleNotFound')?.classList.remove('hidden');
}

// ============================================
// FILTROS Y BÚSQUEDA
// ============================================

function filterByCategory(categoria) {
    categoriaActual = categoria;
    articulosVisibles = 9;

    if (categoria === 'all') {
        articulosFiltrados = [...articulos];
    } else {
        articulosFiltrados = articulos.filter(a => a.categoria === categoria);
    }

    renderCategories();
    renderArticles();
}

function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    // Restaurar estado de búsqueda semántica
    semanticSearchEnabled = localStorage.getItem('semanticSearch') === 'true';
    updateSemanticToggleUI();

    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        if (semanticSearchPending) {
            semanticSearchPending.abort();
            semanticSearchPending = null;
        }

        const query = e.target.value.trim();

        if (query === '') {
            filterByCategory(categoriaActual);
            return;
        }

        // Delay más largo para búsqueda semántica (requiere API call)
        const delay = semanticSearchEnabled ? 500 : 300;

        debounceTimer = setTimeout(() => {
            if (semanticSearchEnabled && query.length >= 3) {
                performSemanticSearch(query);
            } else {
                // Búsqueda local tradicional
                const queryLower = query.toLowerCase();
                articulosFiltrados = articulos.filter(a =>
                    a.titulo.toLowerCase().includes(queryLower) ||
                    a.contenido.toLowerCase().includes(queryLower) ||
                    (a.tags && a.tags.some(t => t.toLowerCase().includes(queryLower)))
                );
                renderArticles();
            }
        }, delay);
    });

    // Enter para buscar inmediatamente
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && semanticSearchEnabled) {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();
            if (query.length >= 3) {
                performSemanticSearch(query);
            }
        }
    });
}

/**
 * Toggle búsqueda semántica
 */
function toggleSemanticSearch() {
    semanticSearchEnabled = !semanticSearchEnabled;
    localStorage.setItem('semanticSearch', semanticSearchEnabled);
    updateSemanticToggleUI();

    // Mostrar toast
    const message = semanticSearchEnabled
        ? '🧠 Búsqueda inteligente activada'
        : '🔍 Búsqueda normal activada';
    showToast(message);

    // Re-ejecutar búsqueda si hay texto
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim().length >= 3) {
        searchInput.dispatchEvent(new Event('input'));
    }
}

/**
 * Actualiza UI del toggle de búsqueda semántica
 */
function updateSemanticToggleUI() {
    const toggle = document.getElementById('semanticToggle');
    if (!toggle) return;

    if (semanticSearchEnabled) {
        toggle.classList.add('text-forest-500', 'bg-forest-50', 'dark:bg-forest-900/30');
        toggle.classList.remove('text-gray-400');
        toggle.title = 'Búsqueda inteligente (IA) - Activada';
    } else {
        toggle.classList.remove('text-forest-500', 'bg-forest-50', 'dark:bg-forest-900/30');
        toggle.classList.add('text-gray-400');
        toggle.title = 'Búsqueda inteligente (IA) - Desactivada';
    }
}

/**
 * Realiza búsqueda semántica via API
 */
async function performSemanticSearch(query) {
    const searchInput = document.getElementById('searchInput');

    // Mostrar loading
    searchInput?.classList.add('animate-pulse');

    try {
        // Crear AbortController para poder cancelar
        const controller = new AbortController();
        semanticSearchPending = controller;

        const response = await fetch(`api/buscar_semantico.php?q=${encodeURIComponent(query)}&limit=20`, {
            signal: controller.signal
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error en búsqueda');
        }

        // Mapear resultados a formato de artículos
        if (data.resultados && data.resultados.length > 0) {
            // Enriquecer resultados con datos completos de artículos
            articulosFiltrados = data.resultados.map(r => {
                const artCompleto = articulos.find(a => a.id === r.id);
                return {
                    ...(artCompleto || r),
                    similitud: r.similitud,
                    similitud_porcentaje: r.similitud_porcentaje
                };
            }).filter(Boolean);
        } else {
            articulosFiltrados = [];
        }

        renderArticles(true); // true = mostrar similitud

        // Mostrar info de resultados
        if (data.cached) {
            console.log('Búsqueda semántica (cache):', query, data.total, 'resultados');
        } else {
            console.log('Búsqueda semántica:', query, data.total, 'resultados,', data.tokens_usados, 'tokens');
        }

    } catch (error) {
        if (error.name === 'AbortError') {
            console.log('Búsqueda cancelada');
            return;
        }
        console.error('Error en búsqueda semántica:', error);
        // Fallback a búsqueda local
        const queryLower = query.toLowerCase();
        articulosFiltrados = articulos.filter(a =>
            a.titulo.toLowerCase().includes(queryLower) ||
            a.contenido.toLowerCase().includes(queryLower)
        );
        renderArticles();
    } finally {
        searchInput?.classList.remove('animate-pulse');
        semanticSearchPending = null;
    }
}

function loadMoreArticles() {
    articulosVisibles += 6;
    renderArticles();
}

// ============================================
// LISTA DE LECTURA
// ============================================

function getReadingList() {
    try {
        return JSON.parse(localStorage.getItem('terrapp_reading_list') || '[]');
    } catch {
        return [];
    }
}

function saveReadingList(list) {
    localStorage.setItem('terrapp_reading_list', JSON.stringify(list));
    updateReadingListCount();
}

function isInReadingList(id) {
    return getReadingList().includes(id);
}

function toggleSave(id) {
    const list = getReadingList();
    const index = list.indexOf(id);
    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    if (index > -1) {
        list.splice(index, 1);
        showToast(t('removed_from_list'));
    } else {
        list.push(id);
        showToast(t('saved_to_list'));
    }

    saveReadingList(list);
    renderArticles();
}

function toggleSaveArticle() {
    if (!articuloActual) return;
    toggleSave(articuloActual.id);
    updateSaveButton();
}

function updateSaveButton() {
    const btn = document.getElementById('saveBtn');
    if (!btn || !articuloActual) return;

    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    if (isInReadingList(articuloActual.id)) {
        btn.classList.add('saved');
        btn.title = t('remove_from_list');
    } else {
        btn.classList.remove('saved');
        btn.title = t('save_article');
    }
}

function updateReadingListCount() {
    const count = getReadingList().length;
    const badge = document.getElementById('readingListCount');
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }
}

// ============================================
// REACCIONES Y COMPARTIR
// ============================================

function addReaction(tipo) {
    if (!articuloActual) return;

    const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

    // Verificar si ya reaccionó
    const reacted = localStorage.getItem(`terrapp_reaction_${articuloActual.id}_${tipo}`);
    if (reacted) {
        showToast(t('already_reacted'));
        return;
    }

    // Marcar como reaccionado
    localStorage.setItem(`terrapp_reaction_${articuloActual.id}_${tipo}`, 'true');

    // Incrementar contador visual
    const countEl = document.getElementById(`count${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
    if (countEl) {
        countEl.textContent = parseInt(countEl.textContent) + 1;
    }

    // Animación pop + confeti
    const btn = event.target.closest('.reaction-btn');
    btn?.classList.add('animate', 'active');
    setTimeout(() => btn?.classList.remove('animate'), 300);

    // Lanzar confeti desde el botón
    if (btn) {
        launchConfetti(btn, tipo)
    }

    // Enviar al servidor
    fetch(`admin/api/registrar_reaccion.php?id=${articuloActual.id}&tipo=${tipo}`);

    showToast(t('thanks_reaction'));
}

function shareOn(platform) {
    if (!articuloActual) return;

    // Construir URL con parámetro de idioma si no es español
    const shareUrlBase = getShareUrl();
    const url = encodeURIComponent(shareUrlBase);

    // Usar título traducido
    const traducido = getArticuloEnIdioma(articuloActual);
    const text = encodeURIComponent(traducido.titulo + ' - TERRApp Blog');

    let shareLink = '';
    switch (platform) {
        case 'whatsapp':
            shareLink = `https://wa.me/?text=${text}%20${url}`;
            break;
        case 'facebook':
            shareLink = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareLink = `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
            break;
        case 'linkedin':
            shareLink = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
            break;
    }

    if (shareLink) {
        window.open(shareLink, '_blank', 'width=600,height=400');
        fetch(`admin/api/registrar_share.php?id=${articuloActual.id}&red=${platform}`);
    }
}

function copyLink() {
    const shareUrl = getShareUrl();
    navigator.clipboard.writeText(shareUrl).then(() => {
        const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;
        showToast(t('link_copied'));
        if (articuloActual) {
            fetch(`admin/api/registrar_share.php?id=${articuloActual.id}&red=copy`);
        }
    });
}

/**
 * Obtiene la URL para compartir, incluyendo el parámetro de idioma si no es español
 */
function getShareUrl() {
    if (!articuloActual) return window.location.href;

    // Base URL
    const baseUrl = `${window.location.origin}/agrodiarium/scriptum.php?titulus=${articuloActual.slug}`;

    // Agregar idioma si no es español
    let lang = 'es_AR';
    if (typeof BLOG_I18N !== 'undefined' && BLOG_I18N.currentLang) {
        lang = BLOG_I18N.currentLang;
    } else {
        // Fallback: leer cookie directamente
        const cookieMatch = document.cookie.match(/terrapp_lang=([^;]+)/);
        if (cookieMatch) {
            lang = cookieMatch[1];
        }
    }

    // Si no es español, agregar parámetro lang
    if (lang && !lang.startsWith('es_')) {
        return `${baseUrl}&lang=${lang}`;
    }

    return baseUrl;
}

// ============================================
// VISTAS
// ============================================

/**
 * Boost global de vistas - incrementa vistas de todos los artículos
 * Se ejecuta silenciosamente cada vez que alguien abre cualquier página
 */
async function boostGlobalViews() {
    try {
        await fetch('admin/api/boost_vistas.php');
        // Silencioso, no hacemos nada con la respuesta
    } catch (error) {
        // Ignorar errores silenciosamente
    }
}

/**
 * Actualiza las vistas y reacciones de los artículos desde la BD
 * Para mostrar datos actualizados en lugar de los del JSON estático
 */
async function actualizarVistasDesdeDB() {
    try {
        const response = await fetch('admin/api/obtener_vistas_todos.php');
        if (!response.ok) return;

        const data = await response.json();
        if (!data.success || !data.data) return;

        // Actualizar cada artículo con las vistas actuales de la BD
        articulos.forEach(art => {
            const vistasDB = data.data[art.id];
            if (vistasDB) {
                art.vistas = vistasDB.vistas;
                art.reaccion_interesante = vistasDB.reaccion_interesante;
                art.reaccion_encanta = vistasDB.reaccion_encanta;
                art.reaccion_importante = vistasDB.reaccion_importante;
                art.reaccion_noconvence = vistasDB.reaccion_noconvence;
            }
        });
    } catch (error) {
        console.error('Error actualizando vistas:', error);
        // Si falla, usamos las vistas del JSON (no crítico)
    }
}

async function registerView(id) {
    // Por ahora cuenta todas las visitas (sin restricción de sesión)
    try {
        const response = await fetch(`admin/api/registrar_vista.php?id=${id}`);
        const data = await response.json();

        if (data.success) {
            console.log('[TERRApp] Vista registrada para artículo', id);
        } else {
            console.error('[TERRApp] Error al registrar vista:', data.error);
        }
    } catch (error) {
        console.error('[TERRApp] Error de red al registrar vista:', error);
    }
}

/**
 * Carga las métricas actualizadas del artículo desde la BD
 */
async function loadMetrics(id) {
    try {
        const response = await fetch(`admin/api/obtener_metricas.php?id=${id}`);
        if (!response.ok) return;

        const data = await response.json();
        if (!data.success || !data.metricas) return;

        const m = data.metricas;
        const t = (key) => (typeof BLOG_I18N !== 'undefined') ? BLOG_I18N.t(key) : key;

        // Actualizar vistas en el header del artículo
        const viewsEl = document.getElementById('articleViews');
        if (viewsEl) {
            viewsEl.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                ${m.vistas} ${t('views')}
            `;
        }

        // Actualizar contadores de reacciones
        const countInteresante = document.getElementById('countInteresante');
        const countEncanta = document.getElementById('countEncanta');
        const countImportante = document.getElementById('countImportante');
        const countNoconvence = document.getElementById('countNoconvence');

        if (countInteresante) countInteresante.textContent = m.reaccion_interesante;
        if (countEncanta) countEncanta.textContent = m.reaccion_encanta;
        if (countImportante) countImportante.textContent = m.reaccion_importante;
        if (countNoconvence) countNoconvence.textContent = m.reaccion_noconvence;

        // Actualizar total de shares
        if (m.total_shares > 0) {
            const sharesEl = document.getElementById('totalShares');
            if (sharesEl) {
                sharesEl.textContent = t('shared_times').replace('{n}', m.total_shares);
            }
        }
    } catch (error) {
        console.error('Error cargando métricas:', error);
    }
}

// ============================================
// BLOG NAVIGATION MENUS
// ============================================

// Toggle categories dropdown
function toggleCategoriesMenu() {
    const menu = document.getElementById('categoriesMenu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Close categories menu when clicking outside
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('categoriesDropdown');
    const menu = document.getElementById('categoriesMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.classList.add('hidden');
    }
});

// Toggle blog mobile menu
function toggleBlogMobileMenu() {
    const menu = document.getElementById('blogMobileMenu');
    const btn = document.getElementById('blogMobileMenuBtn');
    if (menu && btn) {
        menu.classList.toggle('hidden');
        const icon = btn.querySelector('svg');
        if (menu.classList.contains('hidden')) {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';
        } else {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
        }
    }
}

// Close blog mobile menu
function closeBlogMobileMenu() {
    const menu = document.getElementById('blogMobileMenu');
    const btn = document.getElementById('blogMobileMenuBtn');
    if (menu && btn) {
        menu.classList.add('hidden');
        const icon = btn.querySelector('svg');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';
    }
}

// Setup mobile menu button
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('blogMobileMenuBtn');
    if (btn) {
        btn.addEventListener('click', toggleBlogMobileMenu);
    }
});

// ============================================
// DARK MODE
// ============================================

function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';

    if (isDark) {
        html.setAttribute('data-theme', 'light');
        localStorage.setItem('terrapp_theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        localStorage.setItem('terrapp_theme', 'dark');
    }
}

function loadThemePreference() {
    const saved = localStorage.getItem('terrapp_theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
}

// ============================================
// SCROLL TO TOP
// ============================================

function setupScrollToTop() {
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    if (!scrollTopBtn) return;

    // Mostrar/ocultar botón según scroll
    window.addEventListener('scroll', () => {
        if (window.scrollY > 500) {
            scrollTopBtn.classList.remove('opacity-0', 'pointer-events-none');
            scrollTopBtn.classList.add('opacity-100');
        } else {
            scrollTopBtn.classList.add('opacity-0', 'pointer-events-none');
            scrollTopBtn.classList.remove('opacity-100');
        }
    });

    // Click para subir
    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// ============================================
// BARRA DE PROGRESO
// ============================================

function setupProgressBar() {
    const progressBar = document.getElementById('progressBar');
    if (!progressBar) return;

    window.addEventListener('scroll', () => {
        const winScroll = document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        progressBar.style.width = scrolled + '%';
    });
}

// ============================================
// CONFETTI ANIMATION
// ============================================

/**
 * Lanza confeti desde un elemento
 * @param {HTMLElement} element - Elemento desde donde lanzar
 * @param {string} tipo - Tipo de reacción (interesante, encanta, importante)
 */
function launchConfetti(element, tipo) {
    // Colores según el tipo de reacción
    const colors = {
        interesante: ['#2d7553', '#3d9268', '#558b2f', '#7cb342', '#aed581'], // verdes
        encanta: ['#2d7553', '#4caf50', '#81c784', '#a5d6a7', '#c8e6c9'],     // verdes claros
        importante: ['#ff5722', '#ff7043', '#ff8a65', '#ffab91', '#f4511e']    // naranjas/rojos
    };

    const shapes = ['', 'square', 'star'];
    const particleColors = colors[tipo] || colors.interesante;

    // Obtener posición del botón
    const rect = element.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    // Crear contenedor de confeti
    const container = document.createElement('div');
    container.className = 'confetti-container';
    document.body.appendChild(container);

    // Crear partículas
    const particleCount = 15;
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        particle.className = `confetti ${shape}`;

        // Color aleatorio del array
        particle.style.backgroundColor = particleColors[Math.floor(Math.random() * particleColors.length)];

        // Posición inicial (centro del botón)
        particle.style.left = centerX + 'px';
        particle.style.top = centerY + 'px';

        // Variación en la animación
        const angle = (Math.random() * 360) * (Math.PI / 180);
        const velocity = 50 + Math.random() * 80;
        const dx = Math.cos(angle) * velocity;
        const dy = Math.sin(angle) * velocity - 50; // Bias hacia arriba

        particle.style.setProperty('--dx', dx + 'px');
        particle.style.setProperty('--dy', dy + 'px');
        particle.style.animation = `confettiExplode 0.8s ease-out forwards`;
        particle.style.animationDelay = (Math.random() * 0.1) + 's';

        container.appendChild(particle);
    }

    // Remover contenedor después de la animación
    setTimeout(() => {
        container.remove();
    }, 1000);
}

// ============================================
// UTILIDADES
// ============================================

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-AR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

function formatDateLocalized(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);

    // Obtener locale según idioma actual
    let locale = 'es-AR';
    if (typeof BLOG_I18N !== 'undefined') {
        const lang = BLOG_I18N.currentLang || 'es_AR';
        const localeMap = {
            'pt_BR': 'pt-BR',
            'en_GY': 'en-US',
            'fr_GF': 'fr-FR',
            'nl_SR': 'nl-NL'
        };
        // Para español, usar el país específico
        if (lang.startsWith('es_')) {
            locale = lang.replace('_', '-');
        } else {
            locale = localeMap[lang] || 'es-AR';
        }
    }

    return date.toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

function formatContent(text) {
    if (!text) return '';

    const lines = text.split('\n');
    const result = [];
    let currentParagraph = [];
    let inList = false;
    let listItems = [];

    const flushParagraph = () => {
        if (currentParagraph.length > 0) {
            const text = currentParagraph.join(' ').trim();
            if (text) {
                result.push(`<p>${escapeHtml(text)}</p>`);
            }
            currentParagraph = [];
        }
    };

    const flushList = () => {
        if (listItems.length > 0) {
            result.push(`<ul class="list-disc list-inside space-y-1 my-4">${listItems.join('')}</ul>`);
            listItems = [];
            inList = false;
        }
    };

    for (const line of lines) {
        const trimmed = line.trim();

        // Línea vacía = fin de párrafo
        if (!trimmed) {
            flushList();
            flushParagraph();
            continue;
        }

        // Heading h2
        if (trimmed.startsWith('## ')) {
            flushList();
            flushParagraph();
            const headingText = trimmed.substring(3).trim();
            result.push(`<h2>${escapeHtml(headingText)}</h2>`);
            continue;
        }

        // Heading h3
        if (trimmed.startsWith('### ')) {
            flushList();
            flushParagraph();
            const headingText = trimmed.substring(4).trim();
            result.push(`<h3>${escapeHtml(headingText)}</h3>`);
            continue;
        }

        // Lista
        if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
            flushParagraph();
            inList = true;
            const itemText = trimmed.replace(/^[-*]\s+/, '');
            listItems.push(`<li>${escapeHtml(itemText)}</li>`);
            continue;
        }

        // Si estábamos en lista y ya no, cerrar lista
        if (inList) {
            flushList();
        }

        // Texto normal - agregar al párrafo actual
        currentParagraph.push(trimmed);
    }

    // Flush final
    flushList();
    flushParagraph();

    return result.join('\n');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message) {
    // Remover toast existente
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

// ============================================
// TABLE OF CONTENTS (TOC)
// ============================================

let tocObserver = null;
let tocHeadings = [];

/**
 * Genera la Tabla de Contenidos a partir de los headings del artículo
 */
function generateTOC() {
    const articleBody = document.getElementById('articleBody');
    const tocListDesktop = document.getElementById('tocListDesktop');
    const tocListMobile = document.getElementById('tocListMobile');
    const tocMobileContainer = document.getElementById('tocMobileContainer');

    if (!articleBody) return;

    // Obtener todos los h2 y h3 del contenido
    tocHeadings = Array.from(articleBody.querySelectorAll('h2, h3'));

    // Si no hay headings, ocultar TOC
    if (tocHeadings.length < 2) {
        if (tocMobileContainer) tocMobileContainer.style.display = 'none';
        const tocSidebar = document.querySelector('.toc-sidebar');
        if (tocSidebar) tocSidebar.style.display = 'none';
        return;
    }

    // Agregar IDs a los headings si no tienen
    tocHeadings.forEach((heading, index) => {
        if (!heading.id) {
            heading.id = `section-${index + 1}`;
        }
    });

    // Generar HTML del TOC
    const tocHTML = tocHeadings.map((heading, index) => {
        const level = heading.tagName.toLowerCase() === 'h2' ? 2 : 3;
        const text = heading.textContent.trim();
        const id = heading.id;

        return `
            <li class="toc-item">
                <a href="#${id}" class="toc-link" data-level="${level}" data-index="${index}">
                    ${escapeHtml(text)}
                </a>
            </li>
        `;
    }).join('');

    // Insertar en ambas listas (desktop y mobile)
    if (tocListDesktop) tocListDesktop.innerHTML = tocHTML;
    if (tocListMobile) tocListMobile.innerHTML = tocHTML;

    // Configurar click handlers
    document.querySelectorAll('.toc-link').forEach(link => {
        link.addEventListener('click', handleTocClick);
    });

    // Inicializar IntersectionObserver para destacar sección activa
    setupTocObserver();

    // Actualizar barra de progreso con scroll
    setupTocProgress();
}

/**
 * Maneja el click en un link del TOC
 */
function handleTocClick(e) {
    e.preventDefault();
    const targetId = this.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);

    if (targetElement) {
        // Cerrar TOC móvil si está abierto
        const tocMobileContent = document.getElementById('tocMobileContent');
        const tocMobileToggle = document.getElementById('tocMobileToggle');
        if (tocMobileContent && tocMobileContent.classList.contains('open')) {
            tocMobileContent.classList.remove('open');
            tocMobileToggle.classList.remove('open');
        }

        // Scroll suave al elemento
        const headerOffset = 100; // Altura del header sticky
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });

        // Actualizar URL sin recargar
        history.pushState(null, null, `#${targetId}`);
    }
}

/**
 * Configura el IntersectionObserver para destacar la sección activa
 */
function setupTocObserver() {
    if (tocObserver) {
        tocObserver.disconnect();
    }

    if (tocHeadings.length === 0) return;

    const options = {
        root: null,
        rootMargin: '-100px 0px -60% 0px', // Ajustar según el header
        threshold: 0
    };

    tocObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                setActiveTocLink(id);
            }
        });
    }, options);

    // Observar todos los headings
    tocHeadings.forEach(heading => {
        tocObserver.observe(heading);
    });
}

/**
 * Establece el link activo en el TOC
 */
function setActiveTocLink(activeId) {
    document.querySelectorAll('.toc-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === `#${activeId}`) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

/**
 * Configura la barra de progreso del TOC
 */
function setupTocProgress() {
    const progressBar = document.getElementById('tocProgressBar');
    if (!progressBar) return;

    window.addEventListener('scroll', () => {
        const articleBody = document.getElementById('articleBody');
        if (!articleBody) return;

        const rect = articleBody.getBoundingClientRect();
        const articleTop = rect.top + window.scrollY;
        const articleHeight = rect.height;
        const scrolled = window.scrollY - articleTop + 200; // 200px offset
        const progress = Math.min(100, Math.max(0, (scrolled / articleHeight) * 100));

        progressBar.style.width = `${progress}%`;
    });
}

/**
 * Toggle del TOC móvil
 */
function toggleMobileToc() {
    const tocMobileContent = document.getElementById('tocMobileContent');
    const tocMobileToggle = document.getElementById('tocMobileToggle');

    if (tocMobileContent && tocMobileToggle) {
        tocMobileContent.classList.toggle('open');
        tocMobileToggle.classList.toggle('open');
    }
}

// ============================================
// CHAT RAG - Preguntar al Blog
// ============================================

let chatHistory = [];
let chatOpen = false;

/**
 * Obtiene el idioma actual del usuario
 */
function getCurrentLanguage() {
    // Intentar obtener del sistema i18n del blog
    if (typeof BLOG_I18N !== 'undefined' && BLOG_I18N.currentLang) {
        return BLOG_I18N.currentLang.split('_')[0]; // es_AR -> es
    }

    // Intentar obtener de la cookie
    const cookieMatch = document.cookie.match(/terrapp_lang=([^;]+)/);
    if (cookieMatch) {
        return cookieMatch[1].split('_')[0]; // es_AR -> es
    }

    // Default
    return 'es';
}

/**
 * Inicializa el componente de chat
 */
function initChatRAG() {
    // Crear el HTML del chat si no existe
    if (!document.getElementById('chatRAG')) {
        createChatHTML();
    }

    // Cargar historial de sessionStorage
    const saved = sessionStorage.getItem('terrapp_chat_history');
    if (saved) {
        chatHistory = JSON.parse(saved);
        renderChatHistory();
    }
}

/**
 * Traducciones del chat
 */
const CHAT_I18N = {
    es: {
        title: 'Terri',
        subtitle: 'Tu asistente de agricultura urbana',
        placeholder: 'Escribí tu pregunta...',
        welcome: '¡Hola! Soy <strong>Terri</strong>, tu asistente de agricultura urbana. Puedo responder preguntas sobre huertos, compostaje, riego y más, basándome en los artículos del blog.',
        hint: 'Probá preguntar: "¿Cómo empiezo un huerto en mi balcón?"',
        sources: 'Fuentes:',
        tooltip: 'Preguntale al blog',
        clear: 'Limpiar chat'
    },
    pt: {
        title: 'Terri',
        subtitle: 'Seu assistente de agricultura urbana',
        placeholder: 'Escreva sua pergunta...',
        welcome: 'Olá! Sou <strong>Terri</strong>, seu assistente de agricultura urbana. Posso responder perguntas sobre hortas, compostagem, irrigação e mais, baseando-me nos artigos do blog.',
        hint: 'Tente perguntar: "Como começo uma horta na minha varanda?"',
        sources: 'Fontes:',
        tooltip: 'Pergunte ao blog',
        clear: 'Limpar chat'
    },
    en: {
        title: 'Terri',
        subtitle: 'Your urban farming assistant',
        placeholder: 'Write your question...',
        welcome: 'Hello! I\'m <strong>Terri</strong>, your urban farming assistant. I can answer questions about gardens, composting, watering and more, based on the blog articles.',
        hint: 'Try asking: "How do I start a garden on my balcony?"',
        sources: 'Sources:',
        tooltip: 'Ask the blog',
        clear: 'Clear chat'
    },
    fr: {
        title: 'Terri',
        subtitle: 'Votre assistant d\'agriculture urbaine',
        placeholder: 'Écrivez votre question...',
        welcome: 'Bonjour! Je suis <strong>Terri</strong>, votre assistant d\'agriculture urbaine. Je peux répondre à vos questions sur les jardins, le compostage, l\'arrosage et plus encore, en me basant sur les articles du blog.',
        hint: 'Essayez de demander: "Comment commencer un jardin sur mon balcon?"',
        sources: 'Sources:',
        tooltip: 'Demandez au blog',
        clear: 'Effacer le chat'
    },
    nl: {
        title: 'Terri',
        subtitle: 'Uw stadslandbouw assistent',
        placeholder: 'Schrijf uw vraag...',
        welcome: 'Hallo! Ik ben <strong>Terri</strong>, uw stadslandbouw assistent. Ik kan vragen beantwoorden over tuinen, composteren, water geven en meer, gebaseerd op de blogartikelen.',
        hint: 'Probeer te vragen: "Hoe begin ik een tuin op mijn balkon?"',
        sources: 'Bronnen:',
        tooltip: 'Vraag de blog',
        clear: 'Chat wissen'
    }
};

/**
 * Obtiene texto traducido del chat
 */
function chatT(key) {
    const lang = getCurrentLanguage();
    return CHAT_I18N[lang]?.[key] || CHAT_I18N['es'][key];
}

/**
 * Crea el HTML del chat
 */
function createChatHTML() {
    const t = chatT;

    const chatHTML = `
        <!-- Botón flotante del chat -->
        <button id="chatToggleBtn" onclick="toggleChat()" class="chat-toggle-btn" title="${t('tooltip')}">
            <svg class="chat-icon-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <svg class="chat-icon-close hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span class="chat-badge hidden">1</span>
        </button>

        <!-- Modal del chat -->
        <div id="chatRAG" class="chat-container hidden">
            <div class="chat-header">
                <div class="chat-header-info">
                    <span class="chat-avatar">🌱</span>
                    <div>
                        <h3 class="chat-title">${t('title')}</h3>
                        <p class="chat-subtitle">${t('subtitle')}</p>
                    </div>
                </div>
                <button onclick="clearChatHistory()" class="chat-clear-btn" title="${t('clear')}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>

            <div id="chatMessages" class="chat-messages">
                <!-- Mensaje de bienvenida -->
                <div class="chat-message assistant">
                    <div class="chat-message-content">
                        <p>${t('welcome')}</p>
                        <p class="chat-hint">${t('hint')}</p>
                    </div>
                </div>
            </div>

            <div id="chatSources" class="chat-sources hidden">
                <span class="chat-sources-label">${t('sources')}</span>
                <div id="chatSourcesList" class="chat-sources-list"></div>
            </div>

            <form id="chatForm" onsubmit="sendChatMessage(event)" class="chat-input-form">
                <input type="text" id="chatInput" placeholder="${t('placeholder')}" class="chat-input" autocomplete="off" maxlength="500">
                <button type="submit" id="chatSendBtn" class="chat-send-btn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', chatHTML);
}

/**
 * Toggle del chat
 */
function toggleChat() {
    const chat = document.getElementById('chatRAG');
    const btnOpen = document.querySelector('.chat-icon-open');
    const btnClose = document.querySelector('.chat-icon-close');

    chatOpen = !chatOpen;

    if (chatOpen) {
        chat.classList.remove('hidden');
        chat.classList.add('chat-open');
        btnOpen.classList.add('hidden');
        btnClose.classList.remove('hidden');
        document.getElementById('chatInput').focus();
    } else {
        chat.classList.add('hidden');
        chat.classList.remove('chat-open');
        btnOpen.classList.remove('hidden');
        btnClose.classList.add('hidden');
    }
}

/**
 * Envía un mensaje al chat
 */
async function sendChatMessage(event) {
    event.preventDefault();

    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const question = input.value.trim();

    if (!question || question.length < 3) return;

    // Deshabilitar input
    input.disabled = true;
    sendBtn.disabled = true;

    // Agregar mensaje del usuario
    addChatMessage('user', question);
    input.value = '';

    // Mostrar typing indicator
    showTypingIndicator();

    try {
        // Detectar idioma actual
        const currentLang = getCurrentLanguage();

        // Determinar URL base de la API según la página
        const isOnBlog = window.location.pathname.includes('/agrodiarium/');
        const isOnLanding = window.location.pathname.includes('/landing/');
        const apiBase = isOnBlog ? 'api/' : (isOnLanding ? '../agrodiarium/api/' : '/agrodiarium/api/');

        const response = await fetch(apiBase + 'chat_rag.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question: question,
                history: chatHistory.slice(-6), // Últimos 6 mensajes para contexto
                lang: currentLang
            })
        });

        const data = await response.json();
        hideTypingIndicator();

        if (!data.success) {
            throw new Error(data.error || 'Error en el chat');
        }

        // Agregar respuesta
        addChatMessage('assistant', data.response, data.sources);

        // Mostrar fuentes
        if (data.sources && data.sources.length > 0) {
            showChatSources(data.sources);
        }

    } catch (error) {
        hideTypingIndicator();
        addChatMessage('assistant', 'Perdón, hubo un error al procesar tu pregunta. Por favor, intentá de nuevo.');
        console.error('Error en chat RAG:', error);
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
    }
}

/**
 * Agrega un mensaje al chat
 */
function addChatMessage(role, content, sources = null) {
    const container = document.getElementById('chatMessages');

    const messageHTML = `
        <div class="chat-message ${role}">
            <div class="chat-message-content">
                <p>${escapeHtml(content).replace(/\n/g, '<br>')}</p>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', messageHTML);
    container.scrollTop = container.scrollHeight;

    // Guardar en historial
    chatHistory.push({ role, content });
    sessionStorage.setItem('terrapp_chat_history', JSON.stringify(chatHistory));
}

/**
 * Muestra las fuentes citadas
 */
function showChatSources(sources) {
    const container = document.getElementById('chatSources');
    const list = document.getElementById('chatSourcesList');

    list.innerHTML = sources.map(s => `
        <a href="scriptum.php?titulus=${s.slug}" class="chat-source-link" target="_blank">
            📄 ${escapeHtml(s.titulo)}
        </a>
    `).join('');

    container.classList.remove('hidden');

    // Ocultar después de unos segundos
    setTimeout(() => {
        container.classList.add('hidden');
    }, 10000);
}

/**
 * Muestra indicador de typing
 */
function showTypingIndicator() {
    const container = document.getElementById('chatMessages');
    const typingHTML = `
        <div id="typingIndicator" class="chat-message assistant">
            <div class="chat-typing">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', typingHTML);
    container.scrollTop = container.scrollHeight;
}

/**
 * Oculta indicador de typing
 */
function hideTypingIndicator() {
    document.getElementById('typingIndicator')?.remove();
}

/**
 * Limpia el historial del chat
 */
function clearChatHistory() {
    chatHistory = [];
    sessionStorage.removeItem('terrapp_chat_history');

    const container = document.getElementById('chatMessages');
    container.innerHTML = `
        <div class="chat-message assistant">
            <div class="chat-message-content">
                <p>${chatT('welcome')}</p>
                <p class="chat-hint">${chatT('hint')}</p>
            </div>
        </div>
    `;

    document.getElementById('chatSources').classList.add('hidden');
}

/**
 * Renderiza el historial guardado
 */
function renderChatHistory() {
    const container = document.getElementById('chatMessages');

    // Mantener mensaje de bienvenida y agregar historial
    chatHistory.forEach(msg => {
        const messageHTML = `
            <div class="chat-message ${msg.role}">
                <div class="chat-message-content">
                    <p>${escapeHtml(msg.content).replace(/\n/g, '<br>')}</p>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', messageHTML);
    });

    container.scrollTop = container.scrollHeight;
}

// Inicializar chat cuando carga la página
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar en blog y landing
    const path = window.location.pathname;
    if (path.includes('/agrodiarium/') || path.includes('/landing/') || path === '/' || path.endsWith('/index.html')) {
        initChatRAG();
    }
});

// ============================================
// SISTEMA DE COMENTARIOS
// ============================================

let comentariosCaptcha = { a: 0, b: 0 };

/**
 * Traducciones para comentarios
 */
const COMMENTS_I18N = {
    es: {
        title: 'Comentarios',
        no_comments: 'Sé el primero en comentar',
        login_hint: 'Debés estar suscrito al newsletter para comentar',
        placeholder: 'Escribe tu comentario...',
        email_placeholder: 'Tu email (de suscriptor)',
        submit: 'Publicar',
        reply: 'Responder',
        cancel: 'Cancelar',
        likes: 'Me gusta',
        admin_badge: 'Admin',
        captcha_label: '¿Cuánto es',
        subscribe_cta: '¿No estás suscrito?',
        subscribe_link: 'Suscribite gratis',
        success: '¡Comentario publicado!',
        error_captcha: 'Respuesta incorrecta',
        error_email: 'Email inválido',
        error_content: 'El comentario es muy corto',
        error_subscriber: 'Debés estar suscrito para comentar'
    },
    pt: {
        title: 'Comentários',
        no_comments: 'Seja o primeiro a comentar',
        login_hint: 'Você precisa estar inscrito na newsletter para comentar',
        placeholder: 'Escreva seu comentário...',
        email_placeholder: 'Seu email (de inscrito)',
        submit: 'Publicar',
        reply: 'Responder',
        cancel: 'Cancelar',
        likes: 'Curtir',
        admin_badge: 'Admin',
        captcha_label: 'Quanto é',
        subscribe_cta: 'Não está inscrito?',
        subscribe_link: 'Inscreva-se grátis',
        success: 'Comentário publicado!',
        error_captcha: 'Resposta incorreta',
        error_email: 'Email inválido',
        error_content: 'O comentário é muito curto',
        error_subscriber: 'Você precisa estar inscrito para comentar'
    },
    en: {
        title: 'Comments',
        no_comments: 'Be the first to comment',
        login_hint: 'You must be subscribed to the newsletter to comment',
        placeholder: 'Write your comment...',
        email_placeholder: 'Your email (subscriber)',
        submit: 'Post',
        reply: 'Reply',
        cancel: 'Cancel',
        likes: 'Like',
        admin_badge: 'Admin',
        captcha_label: 'What is',
        subscribe_cta: 'Not subscribed?',
        subscribe_link: 'Subscribe free',
        success: 'Comment posted!',
        error_captcha: 'Wrong answer',
        error_email: 'Invalid email',
        error_content: 'Comment is too short',
        error_subscriber: 'You must be subscribed to comment'
    },
    fr: {
        title: 'Commentaires',
        no_comments: 'Soyez le premier à commenter',
        login_hint: 'Vous devez être abonné à la newsletter pour commenter',
        placeholder: 'Écrivez votre commentaire...',
        email_placeholder: 'Votre email (abonné)',
        submit: 'Publier',
        reply: 'Répondre',
        cancel: 'Annuler',
        likes: 'J\'aime',
        admin_badge: 'Admin',
        captcha_label: 'Combien font',
        subscribe_cta: 'Pas abonné?',
        subscribe_link: 'Abonnez-vous gratuitement',
        success: 'Commentaire publié!',
        error_captcha: 'Mauvaise réponse',
        error_email: 'Email invalide',
        error_content: 'Le commentaire est trop court',
        error_subscriber: 'Vous devez être abonné pour commenter'
    },
    nl: {
        title: 'Reacties',
        no_comments: 'Wees de eerste om te reageren',
        login_hint: 'Je moet geabonneerd zijn op de nieuwsbrief om te reageren',
        placeholder: 'Schrijf je reactie...',
        email_placeholder: 'Je email (abonnee)',
        submit: 'Plaatsen',
        reply: 'Reageren',
        cancel: 'Annuleren',
        likes: 'Leuk',
        admin_badge: 'Admin',
        captcha_label: 'Hoeveel is',
        subscribe_cta: 'Niet geabonneerd?',
        subscribe_link: 'Abonneer gratis',
        success: 'Reactie geplaatst!',
        error_captcha: 'Verkeerd antwoord',
        error_email: 'Ongeldige email',
        error_content: 'De reactie is te kort',
        error_subscriber: 'Je moet geabonneerd zijn om te reageren'
    }
};

function commentsT(key) {
    const lang = getCurrentLanguage();
    return COMMENTS_I18N[lang]?.[key] || COMMENTS_I18N['es'][key];
}

/**
 * Carga y renderiza los comentarios de un artículo
 */
async function loadComments(articuloId) {
    const container = document.getElementById('commentsSection');
    if (!container) return;

    try {
        const response = await fetch(`api/comentarios.php?articulo_id=${articuloId}`);
        const data = await response.json();

        if (data.success) {
            renderComments(data.comentarios, articuloId);
        }
    } catch (error) {
        console.error('Error cargando comentarios:', error);
    }
}

/**
 * Renderiza los comentarios
 */
function renderComments(comentarios, articuloId) {
    const container = document.getElementById('commentsSection');
    if (!container) return;

    // Generar captcha
    comentariosCaptcha = generarCaptcha();

    const t = commentsT;
    const lang = getCurrentLanguage();

    let html = `
        <div class="comments-container">
            <h3 class="comments-title">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                </svg>
                ${t('title')} (${comentarios.length})
            </h3>

            <div class="comments-list">
    `;

    if (comentarios.length === 0) {
        html += `
            <div class="comments-empty">
                <span class="text-4xl">💬</span>
                <p>${t('no_comments')}</p>
            </div>
        `;
    } else {
        comentarios.forEach(c => {
            html += renderComentario(c);
        });
    }

    html += `
            </div>

            <!-- Formulario de comentario -->
            <div class="comment-form-container">
                <h4 class="comment-form-title">💬 ${t('title')}</h4>
                <p class="comment-form-hint">${t('login_hint')}</p>

                <form id="commentForm" onsubmit="submitComment(event, ${articuloId})" class="comment-form">
                    <input type="hidden" id="commentParentId" value="">

                    <div class="comment-form-row">
                        <input type="email" id="commentEmail" placeholder="${t('email_placeholder')}" required class="comment-input">
                    </div>

                    <div class="comment-form-row">
                        <textarea id="commentContent" placeholder="${t('placeholder')}" required minlength="10" maxlength="2000" class="comment-textarea"></textarea>
                    </div>

                    <div class="comment-form-footer">
                        <div class="comment-captcha">
                            <label>${t('captcha_label')} ${comentariosCaptcha.a} + ${comentariosCaptcha.b}?</label>
                            <input type="number" id="commentCaptcha" required class="comment-captcha-input">
                        </div>

                        <button type="submit" id="commentSubmitBtn" class="comment-submit-btn">
                            ${t('submit')}
                        </button>
                    </div>

                    <p class="comment-subscribe-hint">
                        ${t('subscribe_cta')} <a href="#notify">${t('subscribe_link')}</a>
                    </p>
                </form>
            </div>
        </div>
    `;

    container.innerHTML = html;
    container.classList.remove('hidden');
}

/**
 * Renderiza un comentario individual
 */
function renderComentario(c) {
    const t = commentsT;
    const adminBadge = c.es_admin ? `<span class="comment-admin-badge">${t('admin_badge')}</span>` : '';

    let html = `
        <div class="comment" data-id="${c.id}">
            <div class="comment-header">
                <span class="comment-avatar">${c.nombre.charAt(0).toUpperCase()}</span>
                <div class="comment-meta">
                    <span class="comment-author">${escapeHtml(c.nombre)} ${adminBadge}</span>
                    <span class="comment-date">${c.fecha_formateada}</span>
                </div>
            </div>
            <div class="comment-body">
                <p>${escapeHtml(c.contenido)}</p>
            </div>
            <div class="comment-actions">
                <button onclick="likeComment(${c.id})" class="comment-like-btn">
                    👍 <span id="likes-${c.id}">${c.likes}</span>
                </button>
                <button onclick="replyToComment(${c.id}, '${escapeHtml(c.nombre)}')" class="comment-reply-btn">
                    💬 ${t('reply')}
                </button>
            </div>
    `;

    // Respuestas
    if (c.respuestas && c.respuestas.length > 0) {
        html += `<div class="comment-replies">`;
        c.respuestas.forEach(r => {
            const rAdminBadge = r.es_admin ? `<span class="comment-admin-badge">${t('admin_badge')}</span>` : '';
            html += `
                <div class="comment comment-reply" data-id="${r.id}">
                    <div class="comment-header">
                        <span class="comment-avatar comment-avatar-sm">${r.nombre.charAt(0).toUpperCase()}</span>
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(r.nombre)} ${rAdminBadge}</span>
                            <span class="comment-date">${r.fecha_formateada}</span>
                        </div>
                    </div>
                    <div class="comment-body">
                        <p>${escapeHtml(r.contenido)}</p>
                    </div>
                    <div class="comment-actions">
                        <button onclick="likeComment(${r.id})" class="comment-like-btn">
                            👍 <span id="likes-${r.id}">${r.likes}</span>
                        </button>
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    }

    html += `</div>`;
    return html;
}

/**
 * Genera captcha matemático simple
 */
function generarCaptcha() {
    const a = Math.floor(Math.random() * 10) + 1;
    const b = Math.floor(Math.random() * 10) + 1;
    return { a, b, result: a + b };
}

/**
 * Envía un nuevo comentario
 */
async function submitComment(event, articuloId) {
    event.preventDefault();

    const t = commentsT;
    const email = document.getElementById('commentEmail').value.trim();
    const contenido = document.getElementById('commentContent').value.trim();
    const captcha = document.getElementById('commentCaptcha').value.trim();
    const parentId = document.getElementById('commentParentId').value || null;
    const submitBtn = document.getElementById('commentSubmitBtn');

    // Validar captcha localmente primero
    if (parseInt(captcha) !== comentariosCaptcha.result) {
        showToast(t('error_captcha'));
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '...';

    try {
        const response = await fetch('api/comentarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                articulo_id: articuloId,
                email,
                contenido,
                captcha,
                captcha_expected: comentariosCaptcha.result,
                parent_id: parentId ? parseInt(parentId) : null
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast(t('success'));
            // Recargar comentarios
            loadComments(articuloId);
            // Limpiar formulario
            document.getElementById('commentContent').value = '';
            document.getElementById('commentCaptcha').value = '';
            document.getElementById('commentParentId').value = '';
        } else {
            showToast(data.error || 'Error');
        }
    } catch (error) {
        console.error('Error enviando comentario:', error);
        showToast('Error de conexión');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = t('submit');
        // Regenerar captcha
        comentariosCaptcha = generarCaptcha();
        const captchaLabel = document.querySelector('.comment-captcha label');
        if (captchaLabel) {
            captchaLabel.textContent = `${t('captcha_label')} ${comentariosCaptcha.a} + ${comentariosCaptcha.b}?`;
        }
    }
}

/**
 * Dar like a un comentario
 */
async function likeComment(comentarioId) {
    try {
        const response = await fetch(`api/comentarios.php?action=like&id=${comentarioId}`, {
            method: 'POST'
        });
        const data = await response.json();

        if (data.success) {
            document.getElementById(`likes-${comentarioId}`).textContent = data.likes;
        } else {
            showToast(data.error);
        }
    } catch (error) {
        console.error('Error dando like:', error);
    }
}

/**
 * Responder a un comentario
 */
function replyToComment(comentarioId, autorNombre) {
    const t = commentsT;
    document.getElementById('commentParentId').value = comentarioId;
    document.getElementById('commentContent').placeholder = `${t('reply')} a ${autorNombre}...`;
    document.getElementById('commentContent').focus();

    // Mostrar botón cancelar
    const form = document.getElementById('commentForm');
    if (!document.getElementById('cancelReplyBtn')) {
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.id = 'cancelReplyBtn';
        cancelBtn.className = 'comment-cancel-btn';
        cancelBtn.textContent = t('cancel');
        cancelBtn.onclick = cancelReply;
        form.querySelector('.comment-form-footer').appendChild(cancelBtn);
    }
}

/**
 * Cancelar respuesta
 */
function cancelReply() {
    const t = commentsT;
    document.getElementById('commentParentId').value = '';
    document.getElementById('commentContent').placeholder = t('placeholder');
    document.getElementById('cancelReplyBtn')?.remove();
}

// ============================================
// TEXT-TO-SPEECH (ESCUCHAR ARTÍCULO)
// ============================================

let speechSynthesis = window.speechSynthesis;
let speechUtterance = null;
let isSpeaking = false;
let isPaused = false;
let currentArticleText = '';
let speechStartTime = 0;
let speechDuration = 0;

/**
 * Prepara el texto del artículo para lectura
 */
function prepareArticleTextForSpeech() {
    if (!articuloActual) return '';

    const traducido = getArticuloEnIdioma(articuloActual);

    // Construir texto completo para leer
    let text = traducido.titulo + '.\n\n';

    // Contenido principal (sin HTML)
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = formatContent(traducido.contenido);
    text += tempDiv.textContent + '\n\n';

    // Opinión editorial
    if (traducido.opinion_editorial) {
        text += 'Opinión editorial. ' + traducido.opinion_editorial + '\n\n';
    }

    // Tips
    if (traducido.tips && traducido.tips.length > 0) {
        text += 'Tips para tu huerta. ';
        traducido.tips.forEach((tip, i) => {
            text += `Tip ${i + 1}: ${tip}. `;
        });
    }

    return text;
}

/**
 * Obtiene la voz apropiada según el idioma
 */
function getVoiceForLanguage() {
    const voices = speechSynthesis.getVoices();
    const lang = getIdiomaBase() || 'es';

    // Mapeo de idiomas a códigos de voz
    const langMap = {
        'es': ['es-ES', 'es-MX', 'es-AR', 'es'],
        'pt': ['pt-BR', 'pt-PT', 'pt'],
        'en': ['en-US', 'en-GB', 'en'],
        'fr': ['fr-FR', 'fr-CA', 'fr'],
        'nl': ['nl-NL', 'nl']
    };

    const preferredLangs = langMap[lang] || langMap['es'];

    // Buscar voz que coincida
    for (const prefLang of preferredLangs) {
        const voice = voices.find(v => v.lang.startsWith(prefLang.split('-')[0]));
        if (voice) return voice;
    }

    // Fallback a primera voz disponible
    return voices[0];
}

/**
 * Alterna la lectura del artículo
 */
function toggleListenArticle() {
    const playIcon = document.getElementById('playIcon');
    const pauseIcon = document.getElementById('pauseIcon');
    const stopBtn = document.getElementById('stopBtn');
    const listenBtn = document.getElementById('listenBtn');

    if (!speechSynthesis) {
        showToast('Tu navegador no soporta la lectura en voz alta');
        return;
    }

    if (isSpeaking && !isPaused) {
        // Pausar
        speechSynthesis.pause();
        isPaused = true;
        playIcon.classList.remove('hidden');
        pauseIcon.classList.add('hidden');
    } else if (isPaused) {
        // Reanudar
        speechSynthesis.resume();
        isPaused = false;
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
    } else {
        // Iniciar nueva lectura
        startSpeech();
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
        stopBtn.classList.remove('hidden');
        listenBtn.classList.add('bg-red-600');
    }
}

/**
 * Inicia la lectura del artículo
 */
function startSpeech() {
    // Cancelar cualquier lectura previa
    speechSynthesis.cancel();

    currentArticleText = prepareArticleTextForSpeech();

    if (!currentArticleText) {
        showToast('No hay contenido para leer');
        return;
    }

    speechUtterance = new SpeechSynthesisUtterance(currentArticleText);

    // Configurar voz
    const voice = getVoiceForLanguage();
    if (voice) {
        speechUtterance.voice = voice;
    }

    // Configurar propiedades
    speechUtterance.rate = 1;  // Velocidad normal
    speechUtterance.pitch = 1; // Tono normal
    speechUtterance.volume = 1; // Volumen máximo

    // Estimar duración (aprox 150 palabras por minuto)
    const wordCount = currentArticleText.split(/\s+/).length;
    speechDuration = (wordCount / 150) * 60; // En segundos

    // Eventos
    speechUtterance.onstart = () => {
        isSpeaking = true;
        speechStartTime = Date.now();
        updateSpeechProgress();
    };

    speechUtterance.onend = () => {
        resetSpeechUI();
    };

    speechUtterance.onerror = (event) => {
        console.error('Speech error:', event);
        resetSpeechUI();
        if (event.error !== 'canceled') {
            showToast('Error al leer el artículo');
        }
    };

    speechSynthesis.speak(speechUtterance);
}

/**
 * Detiene la lectura del artículo
 */
function stopListenArticle() {
    speechSynthesis.cancel();
    resetSpeechUI();
}

/**
 * Restablece la UI de lectura
 */
function resetSpeechUI() {
    isSpeaking = false;
    isPaused = false;

    const playIcon = document.getElementById('playIcon');
    const pauseIcon = document.getElementById('pauseIcon');
    const stopBtn = document.getElementById('stopBtn');
    const listenBtn = document.getElementById('listenBtn');
    const progress = document.getElementById('audioProgress');
    const timeDisplay = document.getElementById('audioTime');

    if (playIcon) playIcon.classList.remove('hidden');
    if (pauseIcon) pauseIcon.classList.add('hidden');
    if (stopBtn) stopBtn.classList.add('hidden');
    if (listenBtn) listenBtn.classList.remove('bg-red-600');
    if (progress) progress.style.width = '0%';
    if (timeDisplay) timeDisplay.textContent = '';
}

/**
 * Actualiza la barra de progreso de lectura
 */
function updateSpeechProgress() {
    if (!isSpeaking || isPaused) return;

    const elapsed = (Date.now() - speechStartTime) / 1000;
    const progress = Math.min((elapsed / speechDuration) * 100, 100);

    const progressBar = document.getElementById('audioProgress');
    const timeDisplay = document.getElementById('audioTime');

    if (progressBar) {
        progressBar.style.width = `${progress}%`;
    }

    if (timeDisplay) {
        const elapsedMin = Math.floor(elapsed / 60);
        const elapsedSec = Math.floor(elapsed % 60);
        const totalMin = Math.floor(speechDuration / 60);
        const totalSec = Math.floor(speechDuration % 60);
        timeDisplay.textContent = `${elapsedMin}:${elapsedSec.toString().padStart(2, '0')} / ${totalMin}:${totalSec.toString().padStart(2, '0')}`;
    }

    if (isSpeaking) {
        requestAnimationFrame(updateSpeechProgress);
    }
}

// Cargar voces cuando estén disponibles
if (speechSynthesis) {
    speechSynthesis.onvoiceschanged = () => {
        // Las voces ya están cargadas
    };
}

// ============================================
// PUNTOS CLAVE (KEY POINTS)
// ============================================

/**
 * Genera los puntos clave a partir del contenido del artículo
 * Extrae información relevante del contenido y tips
 */
function generateKeyPoints(article) {
    if (!article) return [];

    const traducido = getArticuloEnIdioma(article);
    const keyPoints = [];

    // Extraer puntos del contenido
    const content = traducido.contenido || '';

    // Método 1: Buscar oraciones que empiecen con patrones importantes
    const importantPatterns = [
        /(?:^|\. )([A-Z][^.]+(?:importante|destacar|clave|fundamental|esencial|principal)[^.]+\.)/gi,
        /(?:^|\. )([A-Z][^.]+(?:permite|ayuda|beneficia|mejora|aumenta|reduce)[^.]+\.)/gi,
        /(?:^|\. )([A-Z][^.]+(?:según|de acuerdo|estudios|investigaciones)[^.]+\.)/gi
    ];

    // Método 2: Extraer primeras oraciones de cada sección h2
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = formatContent(content);

    const h2Elements = tempDiv.querySelectorAll('h2');
    h2Elements.forEach(h2 => {
        // Obtener el siguiente elemento que sea un párrafo
        let sibling = h2.nextElementSibling;
        while (sibling && sibling.tagName !== 'P') {
            sibling = sibling.nextElementSibling;
        }
        if (sibling && sibling.textContent) {
            const firstSentence = sibling.textContent.split('.')[0];
            if (firstSentence && firstSentence.length > 30 && firstSentence.length < 200) {
                keyPoints.push(firstSentence.trim() + '.');
            }
        }
    });

    // Método 3: Usar los tips si existen (ya son puntos clave naturalmente)
    if (traducido.tips && traducido.tips.length > 0) {
        traducido.tips.forEach(tip => {
            if (tip && tip.length > 20 && keyPoints.length < 5) {
                // Transformar el tip en un punto clave
                keyPoints.push(tip);
            }
        });
    }

    // Método 4: Extraer datos numéricos/estadísticos
    const statsRegex = /([A-Z][^.]*(?:\d+%|\d+ por ciento|\d+\.\d+|\d+ millones|\d+ mil)[^.]*\.)/g;
    let match;
    while ((match = statsRegex.exec(content)) !== null && keyPoints.length < 5) {
        const stat = match[1].trim();
        if (stat.length > 30 && stat.length < 200 && !keyPoints.includes(stat)) {
            keyPoints.push(stat);
        }
    }

    // Si no se encontraron suficientes puntos, extraer oraciones importantes del primer párrafo
    if (keyPoints.length < 3) {
        const plainText = tempDiv.textContent || '';
        const sentences = plainText.split(/[.!?]+/).filter(s => s.trim().length > 40);

        for (let i = 0; i < Math.min(3, sentences.length) && keyPoints.length < 5; i++) {
            const sentence = sentences[i].trim();
            if (sentence.length > 40 && sentence.length < 200 && !keyPoints.some(kp => kp.includes(sentence.substring(0, 30)))) {
                keyPoints.push(sentence + '.');
            }
        }
    }

    // Limitar a máximo 5 puntos y eliminar duplicados
    const uniquePoints = [...new Set(keyPoints)].slice(0, 5);

    return uniquePoints;
}

/**
 * Renderiza los puntos clave en la UI
 */
function renderKeyPoints(article) {
    const keyPointsSection = document.getElementById('keyPointsSection');
    const keyPointsList = document.getElementById('keyPointsList');

    if (!keyPointsSection || !keyPointsList) return;

    const points = generateKeyPoints(article);

    if (points.length === 0) {
        keyPointsSection.classList.add('hidden');
        return;
    }

    keyPointsList.innerHTML = points.map(point => `
        <li class="flex items-start gap-3">
            <span class="flex-shrink-0 w-2 h-2 mt-2 bg-blue-500 rounded-full"></span>
            <span>${escapeHtml(point)}</span>
        </li>
    `).join('');

    keyPointsSection.classList.remove('hidden');
}
