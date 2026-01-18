<?php
/**
 * TERRApp Blog - Artículo con meta tags dinámicos
 * Este archivo genera los Open Graph tags correctos para compartir en redes sociales
 * Soporta múltiples idiomas para meta tags
 */

// Obtener el slug del artículo
$titulus = isset($_GET['titulus']) ? trim($_GET['titulus']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Detectar idioma desde URL param o cookie
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : '';
if (empty($lang) && isset($_COOKIE['terrapp_lang'])) {
    $lang = $_COOKIE['terrapp_lang'];
}

// Mapear idioma completo a código base para traducciones
$langBase = '';
if (!empty($lang)) {
    $langParts = explode('_', $lang);
    $langBase = $langParts[0]; // pt, en, fr, nl
    // Solo usar si es un idioma con traducciones (no español)
    if (!in_array($langBase, ['pt', 'en', 'fr', 'nl'])) {
        $langBase = '';
    }
}

// Mapeo de locale para og:locale
$localeMap = [
    'pt' => 'pt_BR',
    'en' => 'en_US',
    'fr' => 'fr_FR',
    'nl' => 'nl_NL',
    '' => 'es_LA'
];
$ogLocale = $localeMap[$langBase] ?? 'es_LA';

// Valores por defecto
$article = null;
$pageTitle = 'Artículo - TERRApp Blog';
$pageDescription = 'Artículo del Blog TERRApp sobre agricultura urbana';
$pageImage = 'https://terrapp.verumax.com/landing/assets/images/logo_terrapp_icono.png';
$pageUrl = 'https://terrapp.verumax.com/blog/scriptum.php';
$canonicalUrl = 'https://terrapp.verumax.com/blog/scriptum.php';

// Cargar artículo desde JSON
$jsonPath = __DIR__ . '/data/articulos.json';
if (file_exists($jsonPath)) {
    $data = json_decode(file_get_contents($jsonPath), true);

    if ($data && isset($data['articulos'])) {
        foreach ($data['articulos'] as $art) {
            if (($titulus && $art['slug'] === $titulus) || ($id && $art['id'] == $id)) {
                $article = $art;
                break;
            }
        }
    }
}

// Si encontramos el artículo, actualizar meta tags
if ($article) {
    // Usar traducción si está disponible y el idioma no es español
    $titulo = $article['titulo'];
    $contenido = $article['contenido'] ?? '';

    if (!empty($langBase) && isset($article['traducciones'][$langBase])) {
        $trad = $article['traducciones'][$langBase];
        $titulo = $trad['titulo'] ?? $titulo;
        $contenido = $trad['contenido'] ?? $contenido;
    }

    $pageTitle = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . ' - TERRApp Blog';

    // Descripción: usar contenido resumido
    $desc = strip_tags($contenido);
    $pageDescription = htmlspecialchars(mb_substr($desc, 0, 160), ENT_QUOTES, 'UTF-8') . '...';

    // Imagen
    if (!empty($article['imagen_url'])) {
        $pageImage = $article['imagen_url'];
    }

    // URLs - incluir lang si no es español
    $slug = htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8');
    $langParam = !empty($langBase) ? "&lang={$lang}" : '';
    $pageUrl = "https://terrapp.verumax.com/blog/scriptum.php?titulus={$slug}{$langParam}";
    // Canonical siempre apunta a la versión española (SEO)
    $canonicalUrl = "https://terrapp.verumax.com/blog/scriptum.php?titulus={$slug}";
}

// Obtener parámetro para pasar al JS
$jsParam = $titulus ? "titulus=" . urlencode($titulus) : ($id ? "id={$id}" : '');
?>
<!DOCTYPE html>
<html lang="<?= !empty($langBase) ? $langBase : 'es' ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO -->
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $pageUrl ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:image" content="<?= $pageImage ?>">
    <meta property="og:site_name" content="TERRApp Blog">
    <meta property="og:locale" content="<?= $ogLocale ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $pageUrl ?>">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= $pageDescription ?>">
    <meta name="twitter:image" content="<?= $pageImage ?>">

    <!-- RSS -->
    <link rel="alternate" type="application/rss+xml" title="TERRApp Blog RSS" href="feed.xml">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../landing/assets/images/logo_terrapp_icono.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">

    <!-- Flag Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['class', '[data-theme="dark"]'],
            theme: {
                extend: {
                    colors: {
                        'earth': {
                            50: '#faf6f1',
                            100: '#f0e6d8',
                            800: '#5a3f2b',
                            900: '#4a3425',
                        },
                        'forest': {
                            100: '#dcf0e4',
                            500: '#3d9268',
                            600: '#2d7553',
                            700: '#265e44',
                        },
                        'leaf': '#558b2f'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                        'serif': ['Merriweather', 'Georgia', 'serif'],
                    }
                }
            }
        }
    </script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/blog.css">
</head>
<body class="bg-earth-50 dark:bg-gray-900 text-earth-900 dark:text-gray-100 font-sans min-h-screen transition-colors duration-300">
    <!-- Reading Progress Bar -->
    <div id="progressBar" class="fixed top-0 left-0 h-1 bg-forest-600 z-[60] transition-all duration-150" style="width: 0%"></div>

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <!-- Back & Logo -->
                <div class="flex items-center gap-4">
                    <a href="./" class="text-gray-600 dark:text-gray-300 hover:text-forest-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <a href="../landing/" class="flex items-center gap-2">
                        <img src="../landing/assets/images/logo_terrapp_icono.png" alt="TERRApp" class="w-8 h-8">
                        <span class="text-lg font-bold text-forest-600 dark:text-forest-500">TERRApp</span>
                    </a>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <!-- Language Selector -->
                    <div class="relative">
                        <button id="langToggle" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors border border-gray-200 dark:border-gray-600" aria-label="Seleccionar idioma">
                            <span id="currentFlag" class="fi fi-ar"></span>
                            <span id="currentLangName" class="text-sm font-medium hidden sm:inline">Argentina</span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div id="langMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-xl overflow-hidden z-50">
                            <div class="p-2 text-xs text-gray-500 font-semibold uppercase tracking-wider border-b border-gray-100 dark:border-gray-700" data-i18n="lang_select_title">Seleccionar país</div>
                            <div id="langOptions" class="max-h-80 overflow-y-auto"></div>
                        </div>
                    </div>

                    <!-- Save to List -->
                    <button id="saveBtn" onclick="toggleSaveArticle()" class="p-2 text-gray-600 dark:text-gray-300 hover:text-forest-600 transition" title="Guardar para después">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                        </svg>
                    </button>

                    <!-- Dark Mode -->
                    <button onclick="toggleDarkMode()" class="p-2 text-gray-600 dark:text-gray-300 hover:text-forest-600 transition">
                        <svg class="w-6 h-6 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        <svg class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <!-- Breadcrumbs -->
        <nav id="breadcrumbs" class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            <a href="../landing/" class="hover:text-forest-600">TERRApp</a>
            <span class="mx-2">/</span>
            <a href="./" class="hover:text-forest-600">Blog</a>
            <span class="mx-2">/</span>
            <span id="breadcrumbCategory" class="text-gray-700 dark:text-gray-300">Cargando...</span>
        </nav>

        <article class="max-w-3xl mx-auto">
            <!-- Skeleton Loader -->
            <div id="articleSkeleton">
                <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4 animate-pulse"></div>
                <div class="flex gap-4 mb-6">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div>
                </div>
                <div class="h-64 bg-gray-200 dark:bg-gray-700 rounded-xl mb-8 animate-pulse"></div>
                <div class="space-y-3">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full animate-pulse"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full animate-pulse"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 animate-pulse"></div>
                </div>
            </div>

            <!-- Article Content (se llena dinámicamente) -->
            <div id="articleContent" class="hidden">
                <!-- Header -->
                <header class="mb-8">
                    <h1 id="articleTitle" class="text-3xl md:text-4xl font-bold leading-tight mb-4"></h1>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <span id="articleDate" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <span id="articleReadTime" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <span id="articleViews" class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </span>
                        <span id="articleRegion" class="flex items-center gap-1"></span>
                    </div>
                </header>

                <!-- Featured Image -->
                <div id="articleImageContainer" class="mb-8 hidden">
                    <img id="articleImage" src="" alt="" class="w-full rounded-xl shadow-lg">
                </div>

                <!-- Content -->
                <div id="articleBody" class="prose prose-lg dark:prose-invert max-w-none mb-8 font-serif">
                    <!-- Contenido del artículo -->
                </div>

                <!-- Editorial Opinion -->
                <div id="editorialOpinion" class="bg-forest-50 dark:bg-forest-900/20 border-l-4 border-forest-600 p-6 rounded-r-xl mb-8">
                    <h3 class="text-lg font-bold text-forest-700 dark:text-forest-400 mb-3 flex items-center gap-2">
                        <span>🌱</span> <span data-i18n="editorial_opinion">Opinión Editorial TERRApp</span>
                    </h3>
                    <div id="editorialContent" class="text-gray-700 dark:text-gray-300"></div>
                </div>

                <!-- Tips -->
                <div id="tipsSection" class="hidden bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-6 mb-8">
                    <h3 class="text-lg font-bold text-yellow-700 dark:text-yellow-400 mb-4 flex items-center gap-2">
                        <span>💡</span> <span data-i18n="tips_title">Tips para tu Huerta</span>
                    </h3>
                    <ul id="tipsList" class="space-y-3"></ul>
                </div>

                <!-- Reactions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4" data-i18n="reactions_title">¿Te gustó este artículo?</h3>
                    <div class="flex flex-wrap gap-3" id="reactionsContainer">
                        <button onclick="addReaction('interesante')" class="reaction-btn flex items-center gap-2 px-4 py-2 rounded-full border border-gray-200 dark:border-gray-600 hover:bg-forest-50 dark:hover:bg-forest-900/30 transition">
                            <span class="text-xl">🌱</span>
                            <span id="countInteresante">0</span>
                        </button>
                        <button onclick="addReaction('encanta')" class="reaction-btn flex items-center gap-2 px-4 py-2 rounded-full border border-gray-200 dark:border-gray-600 hover:bg-green-50 dark:hover:bg-green-900/30 transition">
                            <span class="text-xl">💚</span>
                            <span id="countEncanta">0</span>
                        </button>
                        <button onclick="addReaction('importante')" class="reaction-btn flex items-center gap-2 px-4 py-2 rounded-full border border-gray-200 dark:border-gray-600 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition">
                            <span class="text-xl">🔥</span>
                            <span id="countImportante">0</span>
                        </button>
                    </div>
                </div>

                <!-- Share -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4" data-i18n="share_title">Compartir</h3>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="shareOn('whatsapp')" class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            WhatsApp
                        </button>
                        <button onclick="shareOn('facebook')" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </button>
                        <button onclick="shareOn('twitter')" class="flex items-center gap-2 px-4 py-2 bg-black hover:bg-gray-800 text-white rounded-lg transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            X
                        </button>
                        <button onclick="shareOn('linkedin')" class="flex items-center gap-2 px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            LinkedIn
                        </button>
                        <button onclick="copyLink()" class="flex items-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                            </svg>
                            <span data-i18n="copy_link">Copiar link</span>
                        </button>
                    </div>
                    <p id="totalShares" class="text-sm text-gray-500 mt-3"></p>
                </div>

                <!-- Source -->
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-4 mb-8">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">📰 <span data-i18n="source">Fuente</span>:</span>
                        <span id="sourceName"></span> -
                        <a id="sourceLink" href="#" target="_blank" class="text-forest-600 hover:underline" data-i18n="view_original">Ver original</a>
                    </p>
                </div>

                <!-- Tags -->
                <div id="tagsSection" class="hidden mb-8">
                    <div class="flex flex-wrap gap-2" id="tagsList"></div>
                </div>

                <!-- Related Articles -->
                <div id="relatedSection" class="hidden">
                    <h3 class="text-xl font-bold mb-6" data-i18n="related_articles">Artículos Relacionados</h3>
                    <div id="relatedArticles" class="grid grid-cols-1 md:grid-cols-3 gap-4"></div>
                </div>
            </div>

            <!-- Article Not Found -->
            <div id="articleNotFound" class="hidden text-center py-12">
                <div class="text-6xl mb-4">🌱</div>
                <h2 class="text-2xl font-bold text-gray-700 dark:text-gray-300 mb-2" data-i18n="article_not_found">Artículo no encontrado</h2>
                <p class="text-gray-500 mb-6" data-i18n="article_not_found_desc">El artículo que buscas no existe o fue eliminado.</p>
                <a href="./" class="inline-block px-6 py-3 bg-forest-600 hover:bg-forest-700 text-white font-semibold rounded-lg transition" data-i18n="back_to_blog">
                    Volver al blog
                </a>
            </div>
        </article>
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center">
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    <span data-i18n="footer_cta">¿Te interesa la agricultura urbana?</span> <a href="../landing/" class="text-forest-600 hover:underline font-semibold" data-i18n="footer_cta_link">Conoce TERRApp</a>
                </p>
                <p class="text-sm text-gray-500">&copy; 2026 TERRApp by VERUMax</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollTopBtn" class="fixed bottom-6 right-6 z-50 w-12 h-12 bg-forest-600 hover:bg-forest-700 text-white rounded-full shadow-lg shadow-forest-600/30 flex items-center justify-center transition-all opacity-0 pointer-events-none" aria-label="Volver arriba">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
        </svg>
    </button>

    <!-- Scripts -->
    <script src="assets/js/i18n-blog.js"></script>
    <script src="assets/js/blog.js"></script>
    <script>
        // Cargar artículo al iniciar
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('id');
            const titulus = params.get('titulus');

            if (id || titulus) {
                loadArticle(id, titulus);
            } else {
                showNotFound();
            }
        });
    </script>
</body>
</html>
