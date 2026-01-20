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

    // Crear ID único para este contenedor
    const containerId = 'img-' + Math.random().toString(36).substr(2, 9);

    return `<div id="${containerId}" class="w-full h-full">
                <img src="${url}"
                     alt="${tituloEscapado}"
                     class="w-full h-full object-cover ${extraClasses}"
                     loading="lazy"
                     onerror="this.parentElement.innerHTML='<div class=\\'w-full h-full image-placeholder ${sizeClass}\\'><span class=\\'placeholder-title\\'>${tituloEscapado.replace(/'/g, "\\'")}</span></div>'">
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

    // Card normal
    const imagenHtml = art.imagen_url
        ? `<img src="${art.imagen_url}" alt="${escapeHtml(traducido.titulo)}" class="w-full h-full object-cover" loading="lazy">`
        : `<div class="w-full h-full flex flex-col items-center justify-center text-white p-4">
               <span class="text-sm font-semibold text-center line-clamp-2 mb-2">${escapeHtml(traducido.titulo)}</span>
               <span class="text-2xl">🌱</span>
           </div>`;

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
            <img src="${art.imagen_url}"
                 alt="${tituloEscapado}"
                 class="w-full rounded-xl shadow-lg"
                 onerror="this.parentElement.innerHTML='<div class=\\'w-full h-64 md:h-96 image-placeholder placeholder-lg rounded-xl\\'><span class=\\'placeholder-title\\'>${tituloEscapado.replace(/'/g, "\\'")}</span></div>'">`;
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
    const baseUrl = `${window.location.origin}/blog/scriptum.php?titulus=${articuloActual.slug}`;

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
