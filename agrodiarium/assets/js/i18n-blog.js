/**
 * TERRApp Blog - Sistema de Internacionalización (i18n)
 * Versión simplificada para la interfaz del blog
 */

const BLOG_I18N = {
    defaultLang: 'es_AR',
    currentLang: 'es_AR',

    // Idiomas disponibles (mismos que landing)
    languages: {
        es_AR: { name: 'Argentina', flag: 'ar', region: 'Español (Argentina)' },
        es_BO: { name: 'Bolivia', flag: 'bo', region: 'Español (Bolivia)' },
        pt_BR: { name: 'Brasil', flag: 'br', region: 'Português (Brasil)' },
        es_CL: { name: 'Chile', flag: 'cl', region: 'Español (Chile)' },
        es_CO: { name: 'Colombia', flag: 'co', region: 'Español (Colombia)' },
        es_EC: { name: 'Ecuador', flag: 'ec', region: 'Español (Ecuador)' },
        es_PY: { name: 'Paraguay', flag: 'py', region: 'Español (Paraguay)' },
        es_PE: { name: 'Perú', flag: 'pe', region: 'Español (Perú)' },
        es_UY: { name: 'Uruguay', flag: 'uy', region: 'Español (Uruguay)' },
        es_VE: { name: 'Venezuela', flag: 've', region: 'Español (Venezuela)' },
        en_GY: { name: 'Guyana', flag: 'gy', region: 'English (Guyana)' },
        fr_GF: { name: 'Guyane', flag: 'gf', region: 'Français (Guyane)' },
        nl_SR: { name: 'Suriname', flag: 'sr', region: 'Nederlands (Suriname)' }
    },

    // Traducciones de la interfaz del blog
    translations: {
        // ESPAÑOL (base para todos los países hispanohablantes)
        es_AR: {
            blog_title: 'Blog',
            search_placeholder: 'Buscar artículos...',
            reading_list: 'Mi lista de lectura',
            all_categories: 'Todos',
            no_articles: 'Aún no hay artículos publicados',
            no_results: 'No se encontraron artículos',
            try_another: 'Intenta con otra búsqueda o categoría',
            load_more: 'Cargar más artículos',
            min_read: 'min',
            views: 'vistas',
            back_to_blog: 'Volver al blog',
            editorial_opinion: 'Opinión Editorial TERRApp',
            tips_title: 'Tips para tu Huerta',
            reactions_title: '¿Te gustó este artículo?',
            share_title: 'Compartir',
            copy_link: 'Copiar link',
            link_copied: '¡Link copiado!',
            source: 'Fuente',
            view_original: 'Ver original',
            source_attribution: 'Atribución de fuente',
            source_notice: 'Este artículo fue elaborado a partir de información publicada originalmente por:',
            view_original_article: 'Ver artículo original',
            source_disclaimer: 'TERRApp respeta los derechos de autor. El contenido original pertenece a sus respectivos propietarios.',
            related_articles: 'Artículos Relacionados',
            article_not_found: 'Artículo no encontrado',
            article_not_found_desc: 'El artículo que buscas no existe o fue eliminado.',
            save_article: 'Guardar para después',
            remove_from_list: 'Quitar de mi lista',
            saved_to_list: 'Guardado en tu lista',
            removed_from_list: 'Eliminado de tu lista',
            thanks_reaction: '¡Gracias por tu reacción!',
            already_reacted: 'Ya reaccionaste a este artículo',
            footer_cta: '¿Te interesa la agricultura urbana?',
            footer_cta_link: 'Conoce TERRApp',
            footer_tagline: 'Agricultura Urbana para Sudamérica',
            nav_home: 'Inicio',
            all_rights: 'Todos los derechos reservados.',
            categories: 'Categorías',
            tags: 'Tags',
            my_list: 'Mi Lista',
            empty_list: 'Tu lista está vacía',
            empty_list_desc: 'Guarda artículos que quieras leer después haciendo clic en el ícono 🔖',
            explore_articles: 'Explorar artículos',
            rss_feed: 'RSS Feed',
            lang_select_title: 'Seleccionar país',
            shared_times: 'Compartido {n} veces',
            // Categorías
            cat_huertos: 'Huertos Urbanos',
            cat_compostaje: 'Compostaje',
            cat_riego: 'Riego',
            cat_plantas: 'Plantas',
            cat_tecnologia: 'Tecnología',
            cat_recetas: 'Recetas',
            cat_comunidad: 'Comunidad',
            cat_noticias: 'Noticias',
            // Text-to-Speech
            listen_article: 'Escuchar artículo',
            tts_not_supported: 'Tu navegador no soporta la lectura en voz alta',
            tts_no_content: 'No hay contenido para leer',
            tts_error: 'Error al leer el artículo',
            // Key Points
            key_points: 'Puntos clave'
        },

        // PORTUGUÉS (Brasil)
        pt_BR: {
            blog_title: 'Blog',
            search_placeholder: 'Pesquisar artigos...',
            reading_list: 'Minha lista de leitura',
            all_categories: 'Todos',
            no_articles: 'Ainda não há artigos publicados',
            no_results: 'Nenhum artigo encontrado',
            try_another: 'Tente outra pesquisa ou categoria',
            load_more: 'Carregar mais artigos',
            min_read: 'min',
            views: 'visualizações',
            back_to_blog: 'Voltar ao blog',
            editorial_opinion: 'Opinião Editorial TERRApp',
            tips_title: 'Dicas para sua Horta',
            reactions_title: 'Gostou deste artigo?',
            share_title: 'Compartilhar',
            copy_link: 'Copiar link',
            link_copied: 'Link copiado!',
            source: 'Fonte',
            view_original: 'Ver original',
            source_attribution: 'Atribuição de fonte',
            source_notice: 'Este artigo foi elaborado a partir de informações publicadas originalmente por:',
            view_original_article: 'Ver artigo original',
            source_disclaimer: 'TERRApp respeita os direitos autorais. O conteúdo original pertence aos seus respectivos proprietários.',
            related_articles: 'Artigos Relacionados',
            article_not_found: 'Artigo não encontrado',
            article_not_found_desc: 'O artigo que você procura não existe ou foi removido.',
            save_article: 'Salvar para depois',
            remove_from_list: 'Remover da lista',
            saved_to_list: 'Salvo na sua lista',
            removed_from_list: 'Removido da sua lista',
            thanks_reaction: 'Obrigado pela sua reação!',
            already_reacted: 'Você já reagiu a este artigo',
            footer_cta: 'Interessado em agricultura urbana?',
            footer_cta_link: 'Conheça o TERRApp',
            footer_tagline: 'Agricultura Urbana para América do Sul',
            nav_home: 'Início',
            all_rights: 'Todos os direitos reservados.',
            categories: 'Categorias',
            tags: 'Tags',
            my_list: 'Minha Lista',
            empty_list: 'Sua lista está vazia',
            empty_list_desc: 'Salve artigos que você quer ler depois clicando no ícone 🔖',
            explore_articles: 'Explorar artigos',
            rss_feed: 'Feed RSS',
            lang_select_title: 'Selecionar país',
            shared_times: 'Compartilhado {n} vezes',
            cat_huertos: 'Hortas Urbanas',
            cat_compostaje: 'Compostagem',
            cat_riego: 'Irrigação',
            cat_plantas: 'Plantas',
            cat_tecnologia: 'Tecnologia',
            cat_recetas: 'Receitas',
            cat_comunidad: 'Comunidade',
            cat_noticias: 'Notícias',
            listen_article: 'Ouvir artigo',
            tts_not_supported: 'Seu navegador não suporta leitura em voz alta',
            tts_no_content: 'Não há conteúdo para ler',
            tts_error: 'Erro ao ler o artigo',
            key_points: 'Pontos-chave'
        },

        // INGLÉS (Guyana)
        en_GY: {
            blog_title: 'Blog',
            search_placeholder: 'Search articles...',
            reading_list: 'My reading list',
            all_categories: 'All',
            no_articles: 'No articles published yet',
            no_results: 'No articles found',
            try_another: 'Try another search or category',
            load_more: 'Load more articles',
            min_read: 'min',
            views: 'views',
            back_to_blog: 'Back to blog',
            editorial_opinion: 'TERRApp Editorial Opinion',
            tips_title: 'Tips for your Garden',
            reactions_title: 'Did you like this article?',
            share_title: 'Share',
            copy_link: 'Copy link',
            link_copied: 'Link copied!',
            source: 'Source',
            view_original: 'View original',
            source_attribution: 'Source attribution',
            source_notice: 'This article was prepared based on information originally published by:',
            view_original_article: 'View original article',
            source_disclaimer: 'TERRApp respects copyright. The original content belongs to its respective owners.',
            related_articles: 'Related Articles',
            article_not_found: 'Article not found',
            article_not_found_desc: 'The article you are looking for does not exist or has been removed.',
            save_article: 'Save for later',
            remove_from_list: 'Remove from list',
            saved_to_list: 'Saved to your list',
            removed_from_list: 'Removed from your list',
            thanks_reaction: 'Thanks for your reaction!',
            already_reacted: 'You already reacted to this article',
            footer_cta: 'Interested in urban agriculture?',
            footer_cta_link: 'Discover TERRApp',
            footer_tagline: 'Urban Agriculture for South America',
            nav_home: 'Home',
            all_rights: 'All rights reserved.',
            categories: 'Categories',
            tags: 'Tags',
            my_list: 'My List',
            empty_list: 'Your list is empty',
            empty_list_desc: 'Save articles you want to read later by clicking the 🔖 icon',
            explore_articles: 'Explore articles',
            rss_feed: 'RSS Feed',
            lang_select_title: 'Select country',
            shared_times: 'Shared {n} times',
            cat_huertos: 'Urban Gardens',
            cat_compostaje: 'Composting',
            cat_riego: 'Irrigation',
            cat_plantas: 'Plants',
            cat_tecnologia: 'Technology',
            cat_recetas: 'Recipes',
            cat_comunidad: 'Community',
            cat_noticias: 'News',
            listen_article: 'Listen to article',
            tts_not_supported: 'Your browser does not support text-to-speech',
            tts_no_content: 'No content to read',
            tts_error: 'Error reading article',
            key_points: 'Key points'
        },

        // FRANCÉS (Guyana Francesa)
        fr_GF: {
            blog_title: 'Blog',
            search_placeholder: 'Rechercher des articles...',
            reading_list: 'Ma liste de lecture',
            all_categories: 'Tous',
            no_articles: 'Aucun article publié',
            no_results: 'Aucun article trouvé',
            try_another: 'Essayez une autre recherche ou catégorie',
            load_more: 'Charger plus d\'articles',
            min_read: 'min',
            views: 'vues',
            back_to_blog: 'Retour au blog',
            editorial_opinion: 'Opinion éditoriale TERRApp',
            tips_title: 'Conseils pour votre Potager',
            reactions_title: 'Vous avez aimé cet article?',
            share_title: 'Partager',
            copy_link: 'Copier le lien',
            link_copied: 'Lien copié!',
            source: 'Source',
            view_original: 'Voir l\'original',
            source_attribution: 'Attribution de la source',
            source_notice: 'Cet article a été préparé à partir d\'informations publiées à l\'origine par:',
            view_original_article: 'Voir l\'article original',
            source_disclaimer: 'TERRApp respecte les droits d\'auteur. Le contenu original appartient à ses propriétaires respectifs.',
            related_articles: 'Articles Connexes',
            article_not_found: 'Article non trouvé',
            article_not_found_desc: 'L\'article que vous recherchez n\'existe pas ou a été supprimé.',
            save_article: 'Sauvegarder pour plus tard',
            remove_from_list: 'Retirer de la liste',
            saved_to_list: 'Sauvegardé dans votre liste',
            removed_from_list: 'Retiré de votre liste',
            thanks_reaction: 'Merci pour votre réaction!',
            already_reacted: 'Vous avez déjà réagi à cet article',
            footer_cta: 'Intéressé par l\'agriculture urbaine?',
            footer_cta_link: 'Découvrez TERRApp',
            footer_tagline: 'Agriculture Urbaine pour l\'Amérique du Sud',
            nav_home: 'Accueil',
            all_rights: 'Tous droits réservés.',
            categories: 'Catégories',
            tags: 'Tags',
            my_list: 'Ma Liste',
            empty_list: 'Votre liste est vide',
            empty_list_desc: 'Sauvegardez les articles que vous voulez lire plus tard en cliquant sur l\'icône 🔖',
            explore_articles: 'Explorer les articles',
            rss_feed: 'Flux RSS',
            lang_select_title: 'Sélectionner le pays',
            shared_times: 'Partagé {n} fois',
            cat_huertos: 'Jardins Urbains',
            cat_compostaje: 'Compostage',
            cat_riego: 'Irrigation',
            cat_plantas: 'Plantes',
            cat_tecnologia: 'Technologie',
            cat_recetas: 'Recettes',
            cat_comunidad: 'Communauté',
            cat_noticias: 'Actualités',
            listen_article: 'Écouter l\'article',
            tts_not_supported: 'Votre navigateur ne prend pas en charge la lecture vocale',
            tts_no_content: 'Aucun contenu à lire',
            tts_error: 'Erreur lors de la lecture de l\'article',
            key_points: 'Points clés'
        },

        // NEERLANDÉS (Surinam)
        nl_SR: {
            blog_title: 'Blog',
            search_placeholder: 'Artikelen zoeken...',
            reading_list: 'Mijn leeslijst',
            all_categories: 'Alle',
            no_articles: 'Nog geen artikelen gepubliceerd',
            no_results: 'Geen artikelen gevonden',
            try_another: 'Probeer een andere zoekopdracht of categorie',
            load_more: 'Meer artikelen laden',
            min_read: 'min',
            views: 'weergaven',
            back_to_blog: 'Terug naar blog',
            editorial_opinion: 'TERRApp Redactionele Mening',
            tips_title: 'Tips voor je Tuin',
            reactions_title: 'Vond je dit artikel leuk?',
            share_title: 'Delen',
            copy_link: 'Link kopiëren',
            link_copied: 'Link gekopieerd!',
            source: 'Bron',
            view_original: 'Origineel bekijken',
            source_attribution: 'Bronvermelding',
            source_notice: 'Dit artikel is samengesteld op basis van informatie die oorspronkelijk is gepubliceerd door:',
            view_original_article: 'Origineel artikel bekijken',
            source_disclaimer: 'TERRApp respecteert het auteursrecht. De originele inhoud behoort toe aan de respectieve eigenaren.',
            related_articles: 'Gerelateerde Artikelen',
            article_not_found: 'Artikel niet gevonden',
            article_not_found_desc: 'Het artikel dat je zoekt bestaat niet of is verwijderd.',
            save_article: 'Opslaan voor later',
            remove_from_list: 'Verwijderen uit lijst',
            saved_to_list: 'Opgeslagen in je lijst',
            removed_from_list: 'Verwijderd uit je lijst',
            thanks_reaction: 'Bedankt voor je reactie!',
            already_reacted: 'Je hebt al gereageerd op dit artikel',
            footer_cta: 'Geïnteresseerd in stadslandbouw?',
            footer_cta_link: 'Ontdek TERRApp',
            footer_tagline: 'Stadslandbouw voor Zuid-Amerika',
            nav_home: 'Home',
            all_rights: 'Alle rechten voorbehouden.',
            categories: 'Categorieën',
            tags: 'Tags',
            my_list: 'Mijn Lijst',
            empty_list: 'Je lijst is leeg',
            empty_list_desc: 'Sla artikelen op die je later wilt lezen door op het 🔖 icoon te klikken',
            explore_articles: 'Artikelen verkennen',
            rss_feed: 'RSS Feed',
            lang_select_title: 'Selecteer land',
            shared_times: '{n} keer gedeeld',
            cat_huertos: 'Stadstuinen',
            cat_compostaje: 'Composteren',
            cat_riego: 'Irrigatie',
            cat_plantas: 'Planten',
            cat_tecnologia: 'Technologie',
            cat_recetas: 'Recepten',
            cat_comunidad: 'Gemeenschap',
            cat_noticias: 'Nieuws',
            listen_article: 'Artikel beluisteren',
            tts_not_supported: 'Uw browser ondersteunt geen tekst-naar-spraak',
            tts_no_content: 'Geen inhoud om te lezen',
            tts_error: 'Fout bij het lezen van het artikel',
            key_points: 'Belangrijke punten'
        }
    },

    // Inicializar
    init() {
        this.currentLang = this.detectLanguage();
        this.applyTranslations();
        this.renderLanguageOptions();
        this.updateCurrentLangIndicator();
        this.setupToggle();

        // Actualizar HTML lang attribute
        document.documentElement.lang = this.currentLang.replace('_', '-');
    },

    // Detectar idioma
    detectLanguage() {
        // 1. Verificar parámetro URL
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('lang');
        if (urlLang && this.languages[urlLang]) {
            return urlLang;
        }

        // 2. Verificar cookie
        const cookieLang = this.getCookie('terrapp_lang');
        if (cookieLang && this.languages[cookieLang]) {
            return cookieLang;
        }

        // 3. Detectar del navegador
        const browserLang = navigator.language || navigator.userLanguage;
        const langCode = browserLang.replace('-', '_');

        // Buscar coincidencia exacta
        if (this.languages[langCode]) {
            return langCode;
        }

        // Buscar coincidencia parcial
        const shortCode = langCode.split('_')[0];
        for (const code in this.languages) {
            if (code.startsWith(shortCode)) {
                return code;
            }
        }

        return this.defaultLang;
    },

    // Obtener traducciones para el idioma actual
    getTranslations() {
        const baseLang = this.currentLang.substring(0, 2);

        // Mapear idioma base a traducción
        const langMap = {
            'es': 'es_AR',
            'pt': 'pt_BR',
            'en': 'en_GY',
            'fr': 'fr_GF',
            'nl': 'nl_SR'
        };

        const translationLang = langMap[baseLang] || 'es_AR';
        return this.translations[translationLang] || this.translations['es_AR'];
    },

    // Aplicar traducciones
    applyTranslations() {
        const translations = this.getTranslations();

        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.dataset.i18n;
            if (translations[key]) {
                el.textContent = translations[key];
            }
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.dataset.i18nPlaceholder;
            if (translations[key]) {
                el.placeholder = translations[key];
            }
        });
    },

    // Obtener traducción
    t(key, params = {}) {
        const translations = this.getTranslations();
        let text = translations[key] || key;

        // Reemplazar parámetros {n}
        for (const [k, v] of Object.entries(params)) {
            text = text.replace(`{${k}}`, v);
        }

        return text;
    },

    // Renderizar opciones de idioma
    renderLanguageOptions() {
        const container = document.getElementById('langOptions');
        if (!container) return;

        let html = '';
        for (const code in this.languages) {
            const lang = this.languages[code];
            const isActive = code === this.currentLang;
            html += `
                <button class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left ${isActive ? 'bg-forest-50 dark:bg-forest-900/30 border-l-4 border-forest-500' : ''}" data-lang="${code}">
                    <span class="fi fi-${lang.flag}"></span>
                    <div class="flex-1">
                        <span class="text-sm font-medium">${lang.name}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 block">${lang.region}</span>
                    </div>
                    ${isActive ? '<svg class="w-4 h-4 text-forest-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : ''}
                </button>
            `;
        }
        container.innerHTML = html;

        // Agregar listeners
        container.querySelectorAll('button[data-lang]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const newLang = btn.dataset.lang;
                this.setLanguage(newLang);
            });
        });
    },

    // Cambiar idioma
    setLanguage(langCode) {
        if (!this.languages[langCode]) return;

        this.currentLang = langCode;
        this.saveCookie(langCode);

        // Aplicar cambios
        this.applyTranslations();
        this.updateCurrentLangIndicator();
        this.renderLanguageOptions();

        // Actualizar HTML
        document.documentElement.lang = langCode.replace('_', '-');

        // Cerrar menú
        const menu = document.getElementById('langMenu');
        if (menu) menu.classList.add('hidden');

        // Notificar a otros scripts que el idioma cambió
        document.dispatchEvent(new CustomEvent('terrapp:langchange', {
            detail: { lang: langCode }
        }));
    },

    // Actualizar indicador
    updateCurrentLangIndicator() {
        const lang = this.languages[this.currentLang];
        if (!lang) return;

        const flagEl = document.getElementById('currentFlag');
        const nameEl = document.getElementById('currentLangName');

        if (flagEl) flagEl.className = `fi fi-${lang.flag}`;
        if (nameEl) nameEl.textContent = lang.name;
    },

    // Setup toggle del menú
    setupToggle() {
        const toggle = document.getElementById('langToggle');
        const menu = document.getElementById('langMenu');

        if (!toggle || !menu) return;

        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    },

    // Cookies
    getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    },

    saveCookie(langCode) {
        const date = new Date();
        date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
        document.cookie = `terrapp_lang=${langCode};expires=${date.toUTCString()};path=/`;
    }
};

// Copiar traducciones para países hispanohablantes
['es_BO', 'es_CL', 'es_CO', 'es_EC', 'es_PY', 'es_PE', 'es_UY', 'es_VE'].forEach(code => {
    BLOG_I18N.translations[code] = BLOG_I18N.translations['es_AR'];
});

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    BLOG_I18N.init();
});
