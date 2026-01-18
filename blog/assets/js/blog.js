/**
 * TERRApp Blog - JavaScript principal
 */

// Estado global
let articulos = [];
let articulosFiltrados = [];
let categoriaActual = 'all';
let articulosVisibles = 9;
let articuloActual = null;

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
                renderArticles();
                renderFeatured();
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
        renderFeatured();

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

    if (articulosFiltrados.length === 0) {
        container.innerHTML = '';
        noResults?.classList.remove('hidden');
        loadMoreBtn?.classList.add('hidden');
        return;
    }

    noResults?.classList.add('hidden');

    const visibles = articulosFiltrados.slice(0, articulosVisibles);

    container.innerHTML = visibles.map(art => createArticleCard(art)).join('');

    // Mostrar/ocultar botón de cargar más
    if (articulosFiltrados.length > articulosVisibles) {
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

    const imagenHtml = featured.imagen_url
        ? `<img src="${featured.imagen_url}" alt="${escapeHtml(traducido.titulo)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">`
        : `<div class="w-full h-full image-placeholder placeholder-lg">
               <span class="placeholder-title">${escapeHtml(traducido.titulo)}</span>
           </div>`;

    container.innerHTML = `
        <a href="scriptum.php?titulus=${featured.slug}" class="block relative rounded-2xl overflow-hidden shadow-xl group">
            <div class="aspect-[21/9] bg-gray-200 dark:bg-gray-700">
                ${imagenHtml}
            </div>
            ${featured.imagen_url ? '<div class="absolute inset-0 featured-gradient"></div>' : ''}
            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-10 text-white ${featured.imagen_url ? '' : 'hidden'}">
                <span class="inline-block px-3 py-1 bg-forest-600 rounded-full text-sm mb-3">
                    ${catIcono} ${catNombre}
                </span>
                <h2 class="text-2xl md:text-4xl font-bold mb-2 group-hover:text-forest-200 transition">
                    ${escapeHtml(traducido.titulo)}
                </h2>
                <p class="text-gray-200 text-sm md:text-base line-clamp-2">
                    ${escapeHtml(traducido.contenido.substring(0, 150))}...
                </p>
            </div>
        </a>
    `;
    container.classList.remove('hidden');
}

function createArticleCard(art) {
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

    const imagenHtml = art.imagen_url
        ? `<img src="${art.imagen_url}" alt="${escapeHtml(traducido.titulo)}" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" loading="lazy">`
        : `<div class="w-full h-full image-placeholder placeholder-card">
               <span class="placeholder-title">${escapeHtml(traducido.titulo)}</span>
           </div>`;

    return `
        <article class="article-card bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-md">
            <a href="scriptum.php?titulus=${art.slug}" class="block">
                <div class="h-48 bg-gray-200 dark:bg-gray-700 relative overflow-hidden">
                    ${imagenHtml}
                    <span class="absolute top-3 left-3 px-2 py-1 bg-white/90 dark:bg-gray-800/90 rounded-full text-xs font-medium">
                        ${catIcono} ${catNombre}
                    </span>
                </div>
            </a>
            <div class="p-5">
                <div class="flex items-center justify-between mb-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>${fecha} • ${art.tiempo_lectura} ${t('min_read')}</span>
                    <span>${art.region === 'sudamerica' ? '🌎' : '🌐'}</span>
                </div>
                <a href="scriptum.php?titulus=${art.slug}" class="block">
                    <h3 class="font-bold text-lg mb-2 line-clamp-2 hover:text-forest-600 transition">
                        ${escapeHtml(traducido.titulo)}
                    </h3>
                </a>
                <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-3">
                    ${escapeHtml(traducido.contenido.substring(0, 120))}...
                </p>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 text-sm text-gray-500">
                        <span>👁️ ${art.vistas}</span>
                        <span>❤️ ${(art.reaccion_interesante || 0) + (art.reaccion_encanta || 0) + (art.reaccion_importante || 0)}</span>
                    </div>
                    <button onclick="event.preventDefault(); toggleSave(${art.id})" class="p-1 ${isSaved ? 'text-forest-600' : 'text-gray-400'} hover:text-forest-600 transition">
                        <svg class="w-5 h-5" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </article>
    `;
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

    // Imagen
    const imgContainer = document.getElementById('articleImageContainer');
    const imgElement = document.getElementById('articleImage');
    if (art.imagen_url) {
        imgElement.src = art.imagen_url;
        imgElement.alt = traducido.titulo;
        imgContainer.classList.remove('hidden');
    } else {
        // Mostrar placeholder con título si no hay imagen
        imgContainer.classList.remove('hidden');
        imgContainer.innerHTML = `
            <div class="w-full h-64 md:h-96 image-placeholder placeholder-lg rounded-xl">
                <span class="placeholder-title">${escapeHtml(traducido.titulo)}</span>
            </div>`;
    }

    // Contenido
    document.getElementById('articleBody').innerHTML = formatContent(traducido.contenido);

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
}

function loadRelatedArticles(currentArt) {
    const related = articulos
        .filter(a => a.id !== currentArt.id && a.categoria === currentArt.categoria)
        .slice(0, 3);

    if (related.length === 0) return;

    document.getElementById('relatedSection').classList.remove('hidden');
    document.getElementById('relatedArticles').innerHTML = related.map(art => {
        const traducido = getArticuloEnIdioma(art);
        const imagenHtml = art.imagen_url
            ? `<img src="${art.imagen_url}" alt="" class="w-full h-full object-cover">`
            : `<div class="w-full h-full image-placeholder placeholder-card">
                   <span class="placeholder-title">${escapeHtml(traducido.titulo)}</span>
               </div>`;
        return `
            <a href="scriptum.php?titulus=${art.slug}" class="block bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition">
                <div class="h-32 bg-gray-200 dark:bg-gray-700">
                    ${imagenHtml}
                </div>
                <div class="p-4">
                    <h4 class="font-semibold text-sm line-clamp-2">${escapeHtml(traducido.titulo)}</h4>
                </div>
            </a>
        `;
    }).join('');
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

    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = e.target.value.toLowerCase().trim();

            if (query === '') {
                filterByCategory(categoriaActual);
            } else {
                articulosFiltrados = articulos.filter(a =>
                    a.titulo.toLowerCase().includes(query) ||
                    a.contenido.toLowerCase().includes(query) ||
                    (a.tags && a.tags.some(t => t.toLowerCase().includes(query)))
                );
                renderArticles();
            }
        }, 300);
    });
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

    // Animación
    const btn = event.target.closest('.reaction-btn');
    btn?.classList.add('animate', 'active');
    setTimeout(() => btn?.classList.remove('animate'), 300);

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

function registerView(id) {
    // Solo registrar una vez por sesión
    const viewed = sessionStorage.getItem(`terrapp_viewed_${id}`);
    if (viewed) return;

    sessionStorage.setItem(`terrapp_viewed_${id}`, 'true');
    fetch(`admin/api/registrar_vista.php?id=${id}`);
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

        if (countInteresante) countInteresante.textContent = m.reaccion_interesante;
        if (countEncanta) countEncanta.textContent = m.reaccion_encanta;
        if (countImportante) countImportante.textContent = m.reaccion_importante;

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
    // Convertir saltos de línea a párrafos
    return text.split('\n\n').map(p => `<p>${escapeHtml(p)}</p>`).join('');
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
