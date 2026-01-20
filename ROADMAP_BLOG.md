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
- **Estado:** Pendiente
- **Complejidad:** Baja
- **Descripción:** Sistema de comentarios usando GitHub Discussions, sin ads ni tracking
- **Tecnología:** Giscus (GitHub Discussions API)
- **Ubicación:** scriptum.php (final del artículo)
- **Requisitos:**
  - [ ] Crear repositorio público para discussions (o usar terrapp existente)
  - [ ] Habilitar GitHub Discussions en el repo
  - [ ] Configurar Giscus (giscus.app)
  - [ ] Integrar componente en scriptum.php
  - [ ] Soporte para modo oscuro
  - [ ] Lazy loading del componente

### 2.2 Artículos Relacionados por Similitud
- **Estado:** Pendiente (actualmente solo por categoría)
- **Complejidad:** Media
- **Descripción:** Mejorar el algoritmo de artículos relacionados usando similitud de contenido
- **Ubicación:** blog.js - loadRelatedArticles()
- **Características:**
  - [ ] Comparar similitud de títulos (Levenshtein o palabras comunes)
  - [ ] Comparar tags compartidos
  - [ ] Ponderar por categoría + tags + título
  - [ ] Fallback a categoría si no hay matches
  - [ ] Excluir artículo actual de resultados

---

## Fase 3: Inteligencia Artificial (Prioridad Baja)

### 3.1 Búsqueda Semántica con Embeddings
- **Estado:** Pendiente
- **Complejidad:** Alta
- **Descripción:** Usar vectores/embeddings para búsqueda por significado, no solo keywords
- **Tecnología:** OpenAI text-embedding-3-small + Base de datos vectorial
- **Características:**
  - [ ] Generar embeddings al publicar artículo
  - [ ] Almacenar vectores en BD (pgvector o tabla JSON)
  - [ ] Búsqueda por similitud de coseno
  - [ ] Artículos relacionados basados en embeddings
  - [ ] Búsqueda semántica desde el buscador

### 3.2 Chat RAG con el Blog
- **Estado:** Pendiente
- **Complejidad:** Alta
- **Descripción:** Interfaz de chat para "preguntar al blog" usando RAG
- **Tecnología:** Embeddings + OpenAI GPT-4
- **Características:**
  - [ ] Interfaz de chat en el blog
  - [ ] Retrieval de fragmentos relevantes
  - [ ] Generación de respuesta basada en contenido del blog
  - [ ] Citas/referencias a artículos fuente
  - [ ] Historial de conversación

---

## Resumen de Prioridades

| # | Funcionalidad | Prioridad | Complejidad | Fase |
|---|---------------|-----------|-------------|------|
| 1 | TOC (Índice de Contenidos) | Alta | Media | 1 |
| 2 | Bento Grid Layout | Alta | Media | 1 |
| 3 | Tipografía Cinética | Alta | Baja | 1 |
| 4 | Comentarios Giscus | Media | Baja | 2 |
| 5 | Artículos Relacionados (similitud) | Media | Media | 2 |
| 6 | Búsqueda Semántica | Baja | Alta | 3 |
| 7 | Chat RAG | Baja | Alta | 3 |

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
