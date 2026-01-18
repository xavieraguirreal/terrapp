/**
 * TERRApp Blog - JavaScript principal
 */

// Estado global
let articulos = [];
let articulosFiltrados = [];
let categoriaActual = 'all';
let articulosVisibles = 9;
let articuloActual = null;

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

    // Cargar artículos si estamos en index
    if (document.getElementById('articlesGrid')) {
        await loadArticles();
        setupSearch();
    }

    // Configurar barra de progreso en artículo
    if (document.getElementById('progressBar')) {
        setupProgressBar();
    }
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

        // Registrar vista
        registerView(articulo.id);

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

    // Obtener categorías con artículos
    const categoriasConArticulos = new Set(articulos.map(a => a.categoria));

    let html = `
        <button onclick="filterByCategory('all')" class="category-btn ${categoriaActual === 'all' ? 'active' : ''} px-4 py-2 rounded-full font-medium transition">
            Todos
        </button>
    `;

    for (const [slug, cat] of Object.entries(CATEGORIAS)) {
        if (categoriasConArticulos.has(slug)) {
            html += `
                <button onclick="filterByCategory('${slug}')" class="category-btn ${categoriaActual === slug ? 'active' : ''} px-4 py-2 rounded-full font-medium transition">
                    ${cat.icono} ${cat.nombre}
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

    const featured = articulos[0];

    container.innerHTML = `
        <a href="articulo.html?slug=${featured.slug}" class="block relative rounded-2xl overflow-hidden shadow-xl group">
            <div class="aspect-[21/9] bg-gray-200 dark:bg-gray-700">
                ${featured.imagen_url
                    ? `<img src="${featured.imagen_url}" alt="${escapeHtml(featured.titulo)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">`
                    : '<div class="w-full h-full image-placeholder"></div>'
                }
            </div>
            <div class="absolute inset-0 featured-gradient"></div>
            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-10 text-white">
                <span class="inline-block px-3 py-1 bg-forest-600 rounded-full text-sm mb-3">
                    ${CATEGORIAS[featured.categoria]?.icono || '📰'} ${CATEGORIAS[featured.categoria]?.nombre || 'Noticias'}
                </span>
                <h2 class="text-2xl md:text-4xl font-bold mb-2 group-hover:text-forest-200 transition">
                    ${escapeHtml(featured.titulo)}
                </h2>
                <p class="text-gray-200 text-sm md:text-base line-clamp-2">
                    ${escapeHtml(featured.contenido.substring(0, 150))}...
                </p>
            </div>
        </a>
    `;
    container.classList.remove('hidden');
}

function createArticleCard(art) {
    const fecha = formatDate(art.fecha_publicacion);
    const categoria = CATEGORIAS[art.categoria] || { nombre: 'Noticias', icono: '📰' };
    const isSaved = isInReadingList(art.id);

    return `
        <article class="article-card bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-md">
            <a href="articulo.html?slug=${art.slug}" class="block">
                <div class="h-48 bg-gray-200 dark:bg-gray-700 relative overflow-hidden">
                    ${art.imagen_url
                        ? `<img src="${art.imagen_url}" alt="${escapeHtml(art.titulo)}" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" loading="lazy">`
                        : '<div class="w-full h-full image-placeholder"></div>'
                    }
                    <span class="absolute top-3 left-3 px-2 py-1 bg-white/90 dark:bg-gray-800/90 rounded-full text-xs font-medium">
                        ${categoria.icono} ${categoria.nombre}
                    </span>
                </div>
            </a>
            <div class="p-5">
                <div class="flex items-center justify-between mb-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>${fecha} • ${art.tiempo_lectura} min</span>
                    <span>${art.region === 'sudamerica' ? '🌎' : '🌐'}</span>
                </div>
                <a href="articulo.html?slug=${art.slug}" class="block">
                    <h3 class="font-bold text-lg mb-2 line-clamp-2 hover:text-forest-600 transition">
                        ${escapeHtml(art.titulo)}
                    </h3>
                </a>
                <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-3">
                    ${escapeHtml(art.contenido.substring(0, 120))}...
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

    // Actualizar título de página y meta tags
    document.title = `${art.titulo} - TERRApp Blog`;
    document.querySelector('meta[name="description"]')?.setAttribute('content', art.contenido.substring(0, 160));
    document.querySelector('meta[property="og:title"]')?.setAttribute('content', art.titulo);

    // Breadcrumb
    const categoria = CATEGORIAS[art.categoria] || { nombre: 'Noticias', icono: '📰' };
    document.getElementById('breadcrumbCategory').textContent = categoria.nombre;

    // Header
    document.getElementById('articleTitle').textContent = art.titulo;
    document.getElementById('articleDate').innerHTML += ' ' + formatDate(art.fecha_publicacion);
    document.getElementById('articleReadTime').innerHTML += ` ${art.tiempo_lectura} min de lectura`;
    document.getElementById('articleViews').innerHTML += ` ${art.vistas} vistas`;
    document.getElementById('articleRegion').innerHTML = art.region === 'sudamerica'
        ? `🌎 ${art.pais_origen || 'Sudamérica'}`
        : '🌐 Internacional';

    // Imagen
    if (art.imagen_url) {
        document.getElementById('articleImage').src = art.imagen_url;
        document.getElementById('articleImage').alt = art.titulo;
        document.getElementById('articleImageContainer').classList.remove('hidden');
    }

    // Contenido
    document.getElementById('articleBody').innerHTML = formatContent(art.contenido);

    // Opinión editorial
    document.getElementById('editorialContent').innerHTML = formatContent(art.opinion_editorial || '');

    // Tips
    if (art.tips && art.tips.length > 0) {
        document.getElementById('tipsSection').classList.remove('hidden');
        document.getElementById('tipsList').innerHTML = art.tips.map(tip =>
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
        document.getElementById('totalShares').textContent = `Compartido ${art.total_shares} veces`;
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
    document.getElementById('relatedArticles').innerHTML = related.map(art => `
        <a href="articulo.html?slug=${art.slug}" class="block bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition">
            <div class="h-32 bg-gray-200 dark:bg-gray-700">
                ${art.imagen_url
                    ? `<img src="${art.imagen_url}" alt="" class="w-full h-full object-cover">`
                    : '<div class="w-full h-full image-placeholder"></div>'
                }
            </div>
            <div class="p-4">
                <h4 class="font-semibold text-sm line-clamp-2">${escapeHtml(art.titulo)}</h4>
            </div>
        </a>
    `).join('');
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

    if (index > -1) {
        list.splice(index, 1);
        showToast('Eliminado de tu lista');
    } else {
        list.push(id);
        showToast('Guardado en tu lista');
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

    if (isInReadingList(articuloActual.id)) {
        btn.classList.add('saved');
        btn.title = 'Quitar de mi lista';
    } else {
        btn.classList.remove('saved');
        btn.title = 'Guardar para después';
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

    // Verificar si ya reaccionó
    const reacted = localStorage.getItem(`terrapp_reaction_${articuloActual.id}_${tipo}`);
    if (reacted) {
        showToast('Ya reaccionaste a este artículo');
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

    showToast('¡Gracias por tu reacción!');
}

function shareOn(platform) {
    if (!articuloActual) return;

    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent(articuloActual.titulo + ' - TERRApp Blog');

    let shareUrl = '';
    switch (platform) {
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${text}%20${url}`;
            break;
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
            break;
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
            break;
    }

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
        fetch(`admin/api/registrar_share.php?id=${articuloActual.id}&red=${platform}`);
    }
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showToast('¡Link copiado!');
        if (articuloActual) {
            fetch(`admin/api/registrar_share.php?id=${articuloActual.id}&red=copy`);
        }
    });
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
