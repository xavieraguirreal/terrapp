# Roadmap Blog TERRApp

**Última actualización:** 2026-01-20

---

## Fase 1: Mejoras de UX/UI (Prioridad Alta)

### 1.1 TOC - Tabla de Contenidos
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Media
- **Descripción:** Índice de contenidos sticky en sidebar que resalta la sección activa mientras el usuario hace scroll
- **Tecnología:** IntersectionObserver API
- **Ubicación:** Artículos largos (scriptum.php)
- **Características:**
  - [x] Generar TOC automáticamente desde headings (h2, h3)
  - [x] Sidebar sticky en desktop
  - [x] Acordeón colapsable en móvil
  - [x] Resaltado de sección activa al hacer scroll
  - [x] Click para navegar a sección
  - [x] Barra de progreso de lectura

### 1.2 Bento Grid Layout
- **Estado:** ✅ COMPLETADO (2026-01-19)
- **Complejidad:** Media
- **Descripción:** Mejorar el layout actual a un verdadero Bento Grid con celdas de diferentes tamaños
- **Ubicación:** blog/index.html
- **Características:**
  - [ ] Featured article en celda 2x2
  - [ ] Artículos secundarios en celdas 1x1
  - [ ] Celda de métricas/estadísticas (1x1)
  - [ ] Celda de categorías populares (1x2)
  - [ ] Layout responsivo que se adapta a móvil
  - [ ] CSS Grid con grid-template-areas

### 1.3 Tipografía Cinética
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Baja
- **Descripción:** Micro-animaciones sutiles en títulos al hacer hover
- **Ubicación:** Cards de artículos, títulos principales
- **Características:**
  - [x] Hover effect en títulos de cards (translateX + letter-spacing)
  - [x] Underline animado en hover
  - [x] Animaciones en h2/h3 del artículo
  - [x] Efectos en botones de categoría (scale + ripple)
  - [x] Optimizado para 60 FPS (solo transform)
  - [x] Soporte modo oscuro

---

## Fase 2: Engagement y Comunidad (Prioridad Media)

### 2.1 Comentarios con Giscus
- **Estado:** ❌ DESCARTADO
- **Razón:** El público objetivo (agricultura urbana, Sudamérica) probablemente no tiene cuenta de GitHub.

### 2.2 Sistema de Comentarios Propio
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Media
- **Descripción:** Sistema de comentarios propio vinculado a suscriptores del newsletter
- **Ubicación:** blog/api/comentarios.php, blog.js, blog.css
- **Características:**
  - [x] Solo suscriptores del newsletter pueden comentar
  - [x] Captcha matemático anti-spam
  - [x] Comentarios anidados (respuestas)
  - [x] Sistema de likes por IP (sin duplicados)
  - [x] Notificación por email al admin
  - [x] Multi-idioma (ES, PT, EN, FR, NL)
  - [x] Diseño responsive con modo oscuro
  - [x] Animaciones y feedback visual

### 2.3 Artículos Relacionados por Similitud
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Media
- **Descripción:** Mejorar el algoritmo de artículos relacionados usando similitud de contenido
- **Ubicación:** blog.js - loadRelatedArticles()
- **Características:**
  - [x] Comparar tags compartidos (40% peso)
  - [x] Comparar palabras clave del título (35% peso)
  - [x] Ponderar por categoría (25% peso)
  - [x] Extracción de keywords con stopwords ES/EN
  - [x] Fallback a categoría si no hay matches
  - [x] Excluir artículo actual de resultados
  - [x] Mostrar porcentaje de similitud
  - [x] Efectos kinéticos en hover

---

## Fase 3: Inteligencia Artificial (Prioridad Baja)

### 3.1 Búsqueda Semántica con Embeddings
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Alta
- **Descripción:** Usar vectores/embeddings para búsqueda por significado, no solo keywords
- **Tecnología:** OpenAI text-embedding-3-small + MySQL JSON
- **Características:**
  - [x] Generar embeddings al publicar artículo (generar_embedding.php)
  - [x] Almacenar vectores en BD (tabla blog_embeddings con JSON)
  - [x] Búsqueda por similitud de coseno (EmbeddingsClient.php)
  - [x] API de búsqueda semántica (buscar_semantico.php)
  - [x] Toggle de búsqueda semántica en frontend
  - [x] Badge de similitud en resultados (🧠 78.5%)
  - [x] Cache de búsquedas frecuentes (blog_search_cache)

### 3.2 Chat RAG con el Blog (Terri)
- **Estado:** ✅ COMPLETADO (2026-01-20)
- **Complejidad:** Alta
- **Descripción:** Asistente virtual "Terri" para preguntar al blog usando RAG
- **Tecnología:** Embeddings + OpenAI GPT-4o-mini
- **Características:**
  - [x] Interfaz de chat flotante (botón + modal)
  - [x] Retrieval de artículos relevantes via embeddings
  - [x] Generación de respuesta basada en contenido del blog
  - [x] Citas/referencias a artículos fuente con links
  - [x] Historial de conversación (sessionStorage)
  - [x] Multi-idioma (detecta idioma de la pregunta)
  - [x] Disponible en blog y landing page

---

## Resumen de Prioridades

| # | Funcionalidad | Estado | Complejidad | Fase |
|---|---------------|--------|-------------|------|
| 1 | TOC (Índice de Contenidos) | ✅ Completado | Media | 1 |
| 2 | Bento Grid Layout | ✅ Completado | Media | 1 |
| 3 | Tipografía Cinética | ✅ Completado | Baja | 1 |
| 4 | Sistema de Comentarios | ✅ Completado | Media | 2 |
| 5 | Artículos Relacionados (similitud) | ✅ Completado | Media | 2 |
| 6 | Búsqueda Semántica | ✅ Completado | Alta | 3 |
| 7 | Chat RAG (Terri) | ✅ Completado | Alta | 3 |

---

## Orden de Implementación Sugerido

1. **Tipografía Cinética** - Rápido de implementar, mejora visual inmediata
2. **TOC** - Alto impacto en UX para artículos largos
3. **Bento Grid** - Mejora significativa del diseño
4. **Giscus** - Fácil integración, añade engagement
5. **Artículos Relacionados mejorados** - Mejor descubrimiento de contenido
6. **Embeddings** (futuro) - Requiere más infraestructura
7. **Chat RAG** (futuro) - Depende de embeddings

---

## Notas Técnicas

### Stack Actual
- Frontend: HTML + Tailwind CSS + Vanilla JS
- Backend: PHP
- Datos: MySQL + JSON estático
- APIs: OpenAI (generación), Tavily (búsqueda noticias)

### Consideraciones
- Mantener rendimiento (Core Web Vitals)
- Soporte modo oscuro en todas las nuevas features
- Responsive design (mobile-first)
- Accesibilidad (ARIA labels, keyboard navigation)
