# Plan: Blog TERRApp - Adaptación del Sistema de Noticias

**Stack:** PHP + HTML + JS + Tailwind (mismo que landing)
**Fecha:** Enero 2026

---

## Resumen

Adaptar el sistema de noticias de `E:\appNews` para crear un blog en `E:\appTerrapp\blog\` que genere contenido sobre **agricultura urbana, huertos y TERRApp** usando IA.

---

## Arquitectura

```
E:\appTerrapp\
├── landing/              # Landing actual (sin cambios)
│   └── index.html        # + Carrusel últimas 3 noticias
│
├── blog/                 # Portal público
│   ├── index.html        # Listado (Bento Grid)
│   ├── articulo.html     # Detalle artículo
│   ├── mi-lista/         # Lista de lectura personal
│   ├── categoria/        # Páginas por categoría
│   ├── tag/              # Páginas por hashtag
│   ├── stories/          # Web Stories (AMP)
│   ├── feed.xml          # RSS Feed (generado)
│   ├── assets/
│   │   ├── css/blog.css
│   │   └── js/
│   │       ├── blog.js
│   │       └── i18n-blog.js
│   ├── data/
│   │   └── articulos.json  # Artículos (generado)
│   │
│   └── admin/              # Panel admin del blog
│       ├── index.php
│       ├── revisar.php
│       ├── config/
│       │   ├── config.php
│       │   └── database.php
│       ├── includes/
│       │   ├── functions.php
│       │   ├── TavilyClient.php
│       │   ├── OpenAIClient.php
│       │   ├── EmailNotifier.php
│       │   ├── WebStoryGenerator.php
│       │   └── RSSGenerator.php
│       ├── api/
│       │   ├── generar_articulos.php
│       │   ├── cambiar_estado.php
│       │   ├── exportar_json.php
│       │   ├── accion_email.php
│       │   ├── registrar_vista.php
│       │   ├── registrar_reaccion.php
│       │   └── registrar_share.php
│       └── cron/
│           └── generar_diario.php
│
└── sql/
    └── schema_blog.sql
```

---

## Diferencias vs appNews

| Aspecto | appNews | TERRApp Blog |
|---------|---------|--------------|
| Temática | Agroecología | Agricultura urbana, huertos |
| Frontend | PHP dinámico | HTML + JSON + JS (estático) |
| Multi-idioma | No | Sí (5 idiomas) |
| Prioridad geográfica | No | Sí (Sudamérica primero, ratio 3-4:1) |
| Estilo contenido | Reescritura libre | Fiel al original + opinión + tips |
| Gestión | Solo web | Web + Email con botones |

---

## Generación de Contenido con IA

### Estructura del Artículo:
1. **Título** (fiel al original)
2. **Contenido** (reescritura mínima, sin inventar)
3. **Opinión Editorial TERRApp** (perspectiva agricultura urbana)
4. **Tips para tu huerta** (opcional, si aplica)
5. **Fuente original** (link)

### Idiomas:
- **Español**: Neutro formal latinoamericano (SIN regionalismos)
- **Portugués**: Brasileño
- **Francés**: Guayanés
- **Inglés**: Guyano
- **Neerlandés**: Surinamés

### Auto-clasificación:
- OpenAI asigna **1 categoría** de lista predefinida
- OpenAI genera **3-5 tags** para SEO

---

## Prioridad Geográfica

- Buscar noticias en todo el mundo
- Ratio: **3-4 sudamericanas : 1 internacional**
- Detectar región por dominio (.ar, .br, .cl, etc.)
- Admin puede saltear criterio si vale la pena

---

## Funcionalidades del Blog

### Listado (Bento Grid):
- Buscador de artículos
- Filtro por categorías
- Modo oscuro
- Cards asimétricos (destacado 2x2, normales 1x1)

### Artículo:
- Barra de progreso de lectura
- TOC sticky (índice flotante)
- Tiempo de lectura estimado
- Contador de vistas
- Reacciones (🌱💚🔥) con debouncing y animación
- Compartir (WA, FB, TW, LI, copiar) con contador
- Comentarios via Giscus (GitHub Discussions)
- Artículos relacionados (3 cards)
- Newsletter integrado
- Breadcrumbs para SEO

### Extras:
- RSS Feed automático
- Web Stories (AMP) para Google Discover
- Lista "Guardar para después" (localStorage)
- Páginas de categoría (Topic Clusters)
- Páginas de hashtags/tags
- Lazy loading de imágenes
- Skeleton loaders

---

## Gestión por Email

Cuando se genera artículo, email al admin con:
- Título y región
- Contenido generado vs original
- Opinión editorial y tips
- **Botones**: ✅ Aprobar | ❌ Rechazar | ⏭️ Saltear criterio | ✏️ Editar en web

---

## Cronjob

```bash
# Generación diaria a las 6:00 AM
0 6 * * * php /path/to/blog/admin/cron/generar_diario.php
```

Publicación programada sin cron extra (frontend filtra por fecha).

---

## Archivos a Crear

### Backend (blog/admin/):
- `config/config.php` - API keys, temas búsqueda
- `config/database.php` - Conexión PDO
- `includes/functions.php` - Funciones BD + exportar
- `includes/TavilyClient.php` - Cliente Tavily
- `includes/OpenAIClient.php` - Cliente OpenAI (nuevo prompt)
- `includes/EmailNotifier.php` - Emails con SendGrid
- `includes/WebStoryGenerator.php` - Generar Web Stories
- `includes/RSSGenerator.php` - Generar RSS
- `index.php` - Panel admin
- `revisar.php` - Revisar artículo
- `api/generar_articulos.php` - Generar con IA
- `api/cambiar_estado.php` - Aprobar/rechazar
- `api/exportar_json.php` - Exportar a JSON
- `api/accion_email.php` - Procesar acciones email
- `api/registrar_vista.php` - Contador vistas
- `api/registrar_reaccion.php` - Guardar reacciones
- `api/registrar_share.php` - Contador compartidos
- `cron/generar_diario.php` - Cronjob

### Frontend (blog/):
- `index.html` - Listado Bento Grid
- `articulo.html` - Detalle artículo
- `mi-lista/index.html` - Lista de lectura
- `categoria/index.html` - Página categoría
- `tag/index.html` - Página hashtag
- `stories/index.html` - Listado Web Stories
- `stories/story.html` - Template AMP
- `assets/css/blog.css` - Estilos
- `assets/js/blog.js` - Lógica JS
- `assets/js/i18n-blog.js` - Traducciones
- `data/articulos.json` - Datos (generado)
- `feed.xml` - RSS (generado)

### Base de datos:
- `sql/schema_blog.sql` - Tablas

---

## Categorías Predefinidas

```
├── 🌱 Huertos Urbanos
├── 🌿 Compostaje
├── 💧 Riego
├── 🌻 Plantas
└── 📱 Tecnología
```

---

## Verificación

1. Admin: Generar artículos, aprobar uno
2. Email: Verificar botones de acción
3. JSON: Verificar exportación
4. Blog: Ver listado y detalle
5. Landing: Verificar carrusel
6. Multi-idioma: Cambiar país
7. Modo oscuro: Toggle
8. Compartir: Contador
9. Giscus: Comentarios
10. RSS/Stories: Generación

---

## Resumen de Funcionalidades

| Categoría | Funcionalidades |
|-----------|-----------------|
| **Diseño** | Bento Grid, Modo oscuro, Skeleton loaders |
| **Lectura** | Barra progreso, TOC sticky, Tiempo lectura, Lazy loading |
| **Engagement** | Reacciones, Comentarios (Giscus), Guardar para después |
| **Social** | Compartir (4 redes), Share count, Newsletter |
| **SEO** | Breadcrumbs, Topic Clusters, Tags, RSS, Web Stories |
| **IA** | Auto-categorías, Auto-tags, Artículos relacionados |
| **Admin** | Email con acciones, Panel web, Prioridad geográfica |
| **Navegación** | Buscador, Categorías, Tags, Mi lista |
