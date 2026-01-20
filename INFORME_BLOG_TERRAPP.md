# Informe Técnico: Blog TERRApp

**Versión:** 1.0
**Fecha:** 2026-01-20
**Propósito:** Documentación para replicar el sistema en otros proyectos

---

## 1. Resumen Ejecutivo

Blog con generación automática de contenido vía IA, búsqueda semántica con embeddings, asistente virtual (Chat RAG), y múltiples características de UX modernas. Multi-idioma (5 idiomas).

---

## 2. Stack Tecnológico

| Capa | Tecnología |
|------|------------|
| Frontend | HTML5 + Tailwind CSS + Vanilla JS |
| Backend | PHP 8.x |
| Base de Datos | MySQL 8.x (InnoDB) |
| IA - Generación | OpenAI GPT-4o-mini |
| IA - Embeddings | OpenAI text-embedding-3-small |
| Búsqueda de Noticias | Tavily API |
| Email | SendGrid |

---

## 3. Arquitectura de Carpetas

```
blog/
├── index.html              # Listado de artículos (Bento Grid)
├── scriptum.php            # Detalle de artículo
├── data/
│   └── articulos.json      # Datos exportados (generado)
├── feed.xml                # RSS Feed (generado)
├── assets/
│   ├── css/blog.css        # Estilos + animaciones
│   └── js/
│       ├── blog.js         # Lógica principal + Chat
│       └── i18n-blog.js    # Traducciones (5 idiomas)
├── api/
│   ├── buscar_semantico.php  # Búsqueda con embeddings
│   └── chat_rag.php          # Chat RAG API
└── admin/
    ├── index.php           # Dashboard admin
    ├── revisar.php         # Revisar/editar artículo
    ├── config/
    │   ├── config.php      # API keys, configuración
    │   └── database.php    # Conexión PDO
    ├── includes/
    │   ├── OpenAIClient.php      # Cliente GPT
    │   ├── EmbeddingsClient.php  # Cliente Embeddings
    │   ├── ChatRAGClient.php     # Cliente Chat RAG
    │   ├── TavilyClient.php      # Búsqueda noticias
    │   ├── EmailNotifier.php     # Notificaciones email
    │   └── functions.php         # Funciones BD
    ├── api/
    │   ├── generar_articulos.php   # Genera con IA
    │   ├── generar_embedding.php   # Genera embeddings
    │   ├── cambiar_estado.php      # Aprobar/rechazar
    │   ├── exportar_json.php       # Exporta a JSON
    │   ├── registrar_vista.php     # Contador vistas
    │   ├── registrar_reaccion.php  # Reacciones emoji
    │   └── registrar_share.php     # Contador shares
    ├── sql/
    │   └── embeddings.sql    # Schema embeddings
    └── cron/
        └── generar_diario.php  # Cronjob diario
```

---

## 4. Características Implementadas

### 4.1 Frontend / UX

#### Bento Grid Layout
- Grid responsivo con CSS Grid
- Artículo destacado en celda 2x2
- Cards normales en celdas 1x1
- Transiciones suaves en hover

```css
.bento-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}
.bento-featured {
    grid-column: span 2;
    grid-row: span 2;
}
```

#### Tipografía Cinética
- Hover en títulos: `translateX(4px) + letter-spacing`
- Underline animado progresivo
- Efectos en botones: scale + ripple
- Optimizado 60 FPS (solo `transform`)

#### Modo Oscuro
- Toggle persistente (localStorage)
- Detecta preferencia del sistema (`prefers-color-scheme`)
- Variables CSS para temas
- Todas las secciones soportadas

#### TOC (Tabla de Contenidos)
- Generado automáticamente desde h2/h3
- Sidebar sticky en desktop
- Acordeón colapsable en móvil
- Resaltado de sección activa (IntersectionObserver)
- Barra de progreso de lectura

#### Reacciones Emoji
- 4 tipos: 🌱 Interesante, 💚 Encanta, 🔥 Importante, 😐 No convence
- Animación confeti al reaccionar
- Guardado en localStorage (anti-spam)
- Contadores en BD

#### Compartir en Redes
- WhatsApp, Facebook, Twitter/X, LinkedIn
- Copiar link al portapapeles
- Contador de shares por red social
- URL con parámetro de idioma

#### Lista de Lectura
- Guardar artículos para después
- Persistencia en localStorage
- Icono 🔖 en cada card
- Contador en header

#### Skeleton Loaders
- Animación pulse mientras carga
- Placeholders para imágenes, títulos, texto

#### Lazy Loading
- Imágenes con `loading="lazy"`
- Fallback con IntersectionObserver

#### Scroll to Top
- Botón flotante que aparece al scrollear
- Animación suave

---

### 4.2 Inteligencia Artificial

#### Generación de Artículos
- **Input:** Búsqueda de noticias con Tavily API
- **Proceso:** OpenAI GPT-4o-mini reescribe y genera:
  - Título fiel al original
  - Contenido reescrito
  - Opinión editorial TERRApp
  - Tips prácticos (si aplica)
  - Detección de región (Sudamérica/Internacional)
- **Costo:** ~$0.002 por artículo

#### Búsqueda Semántica con Embeddings
- **Modelo:** `text-embedding-3-small` (1536 dimensiones)
- **Almacenamiento:** MySQL JSON
- **Algoritmo:** Similitud de coseno
- **Cache:** 24 horas para queries frecuentes
- **UI:** Toggle 🧠 para activar/desactivar
- **Resultado:** Badge con % similitud

```php
// Similitud de coseno
public static function cosineSimilarity(array $a, array $b): float {
    $dotProduct = 0; $normA = 0; $normB = 0;
    for ($i = 0; $i < count($a); $i++) {
        $dotProduct += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    return $dotProduct / (sqrt($normA) * sqrt($normB));
}
```

#### Chat RAG (Terri)
- **Nombre:** Terri (de TERRApp + terra)
- **Modelo:** GPT-4o-mini
- **Flujo RAG:**
  1. Usuario pregunta
  2. Genera embedding de la pregunta
  3. Busca artículos similares (top 3)
  4. GPT genera respuesta basada en artículos
  5. Muestra fuentes con links
- **Multi-idioma:** Detecta idioma y responde en el mismo
- **Historial:** sessionStorage (últimos 6 mensajes)
- **Ubicación:** Blog + Landing page
- **Costo:** ~$0.001-0.003 por pregunta

---

### 4.3 Multi-idioma

#### Idiomas Soportados
| Código | Idioma | Región |
|--------|--------|--------|
| es | Español | Latinoamérica (neutro) |
| pt | Portugués | Brasil |
| en | Inglés | Guyana |
| fr | Francés | Guayana Francesa |
| nl | Neerlandés | Surinam |

#### Implementación
- Cookie `terrapp_lang` con código de idioma
- Archivo `i18n-blog.js` con traducciones
- Artículos traducidos en tabla separada
- Chat Terri detecta idioma automáticamente

---

### 4.4 Artículos Relacionados

#### Algoritmo de Similitud
```javascript
// Pesos del algoritmo
Tags compartidos:     40%
Palabras del título:  35%
Misma categoría:      25%
```

- Extracción de keywords (sin stopwords ES/EN)
- Fallback a misma categoría si no hay matches
- Muestra % de similitud
- Máximo 3 artículos relacionados

---

### 4.5 SEO y Performance

#### Meta Tags Dinámicos
- Open Graph (Facebook)
- Twitter Cards
- Descripción desde contenido

#### RSS Feed
- Generación automática al exportar
- Formato RSS 2.0

#### Optimizaciones
- JSON estático (no consulta BD en frontend)
- Cache de búsquedas semánticas (24h)
- Lazy loading de imágenes
- CSS/JS minificado (producción)

---

## 5. Base de Datos

### Tablas Principales

```sql
-- Artículos
blog_articulos (
    id, titulo, slug, contenido, opinion_editorial,
    tips JSON, categoria, tags JSON, imagen_url,
    estado ENUM('borrador','publicado','rechazado','programado'),
    region ENUM('sudamerica','internacional'),
    pais_origen, fuente_nombre, fuente_url,
    vistas, tiempo_lectura,
    reaccion_interesante, reaccion_encanta,
    reaccion_importante, reaccion_noconvence,
    fecha_creacion, fecha_publicacion
)

-- Traducciones
blog_articulos_traducciones (
    id, articulo_id, idioma,
    titulo, contenido, opinion_editorial, tips JSON
)

-- Embeddings
blog_embeddings (
    id, articulo_id, embedding JSON,
    texto_hash, modelo, tokens_usados
)

-- Cache búsquedas
blog_search_cache (
    id, query_hash, query_text,
    embedding JSON, resultados JSON,
    hits, fecha_expiracion
)

-- Shares
blog_articulo_shares (
    articulo_id, red_social, cantidad
)
```

---

## 6. APIs

### Públicas

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/buscar_semantico.php?q=texto` | GET | Búsqueda semántica |
| `/api/chat_rag.php` | POST | Chat RAG |

### Admin

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/admin/api/generar_articulos.php` | POST | Generar con IA |
| `/admin/api/generar_embedding.php?all=1` | GET | Generar embeddings |
| `/admin/api/cambiar_estado.php` | POST | Aprobar/rechazar |
| `/admin/api/exportar_json.php` | GET | Exportar a JSON |
| `/admin/api/registrar_vista.php?id=X` | GET | Registrar vista |
| `/admin/api/registrar_reaccion.php` | GET | Registrar reacción |
| `/admin/api/registrar_share.php` | GET | Registrar share |

---

## 7. Configuración Requerida

### Variables de Entorno / Config

```php
// config.php
define('OPENAI_API_KEY', 'sk-...');
define('TAVILY_API_KEY', '...');
define('SENDGRID_API_KEY', '...');
define('ADMIN_EMAIL', 'admin@example.com');
define('BLOG_URL', 'https://example.com/blog/');
```

### Cronjobs

```bash
# Generación diaria de artículos
0 6 * * * php /path/to/blog/admin/cron/generar_diario.php

# Exportar JSON (después de aprobar)
*/5 * * * * php /path/to/blog/admin/cron/exportar_json.php
```

---

## 8. Flujo de Contenido

```
1. Cronjob o Admin → "Generar Artículos"
        ↓
2. Tavily busca noticias relevantes
        ↓
3. OpenAI reescribe + detecta región
        ↓
4. Artículo guardado como BORRADOR
        ↓
5. Email al admin con preview
        ↓
6. Admin aprueba/rechaza (web o email)
        ↓
7. Al aprobar → genera embedding
        ↓
8. Exporta JSON para frontend
        ↓
9. Frontend muestra artículos
```

---

## 9. Costos Estimados (OpenAI)

| Operación | Modelo | Costo aprox. |
|-----------|--------|--------------|
| Generar artículo | gpt-4o-mini | $0.002 |
| Generar embedding | text-embedding-3-small | $0.00002 |
| Pregunta Chat RAG | gpt-4o-mini | $0.001-0.003 |

**Total mensual estimado (100 artículos + 1000 chats):** ~$5-10 USD

---

## 10. Checklist de Implementación

### Backend
- [ ] Configurar PHP 8.x
- [ ] Crear base de datos MySQL
- [ ] Ejecutar SQL de tablas
- [ ] Configurar API keys en config.php
- [ ] Configurar cronjobs

### Frontend
- [ ] Subir archivos HTML/CSS/JS
- [ ] Configurar traducciones i18n
- [ ] Verificar rutas de assets

### IA
- [ ] Obtener API key de OpenAI
- [ ] Obtener API key de Tavily
- [ ] Generar embeddings iniciales
- [ ] Probar búsqueda semántica
- [ ] Probar Chat RAG

### Testing
- [ ] Probar modo oscuro
- [ ] Probar multi-idioma
- [ ] Probar en móvil
- [ ] Probar reacciones
- [ ] Probar compartir
- [ ] Probar TOC
- [ ] Probar Chat Terri

---

## 11. Contacto

Para dudas sobre la implementación, contactar al equipo de desarrollo.

---

*Documento generado el 2026-01-20*
