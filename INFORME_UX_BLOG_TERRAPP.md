# Informe UX/UI: Blog TERRApp

**Fecha:** 2026-01-20
**Enfoque:** Experiencia de Usuario y Características del Frontend

---

## 1. Resumen

Blog moderno con diseño Bento Grid, búsqueda inteligente con IA, asistente virtual conversacional, soporte multi-idioma (5 idiomas), modo oscuro, y múltiples micro-interacciones que mejoran la experiencia del usuario.

---

## 2. Layout y Diseño Visual

### 2.1 Bento Grid
El listado de artículos usa un layout tipo "Bento Grid" (inspirado en Apple):

- **Artículo destacado:** Ocupa 2 columnas × 2 filas
- **Artículos normales:** Celdas de 1×1
- **Responsivo:**
  - Desktop: 3 columnas
  - Tablet: 2 columnas
  - Móvil: 1 columna
- **Cards con:**
  - Imagen con hover zoom suave
  - Categoría con icono emoji
  - Fecha + tiempo de lectura
  - Título con efecto kinético
  - Extracto del contenido
  - Stats (vistas, reacciones)
  - Botón guardar

### 2.2 Tipografía Cinética
Micro-animaciones sutiles que dan vida a la interfaz:

| Elemento | Efecto en Hover |
|----------|-----------------|
| Títulos de cards | Se desplazan 4px a la derecha + separan letras |
| Títulos en artículo | Underline animado que crece de 0 a 100% |
| Botones de categoría | Scale 1.02 + efecto ripple |
| Cards relacionados | Elevación + desplazamiento del título |

Todas las animaciones usan `transform` para mantener 60 FPS.

### 2.3 Modo Oscuro
- Toggle en el header (icono sol/luna)
- Detecta preferencia del sistema automáticamente
- Persiste la elección del usuario
- Colores optimizados para lectura nocturna
- Imágenes con brillo/saturación reducidos

---

## 3. Navegación y Descubrimiento

### 3.1 Filtro por Categorías
- Botones tipo "chips" horizontales
- Categorías con iconos emoji:
  - 🌱 Huertos Urbanos
  - 🌿 Compostaje
  - 💧 Riego
  - 🌻 Plantas
  - 📱 Tecnología
  - 🍳 Recetas
  - 🤝 Comunidad
  - 📰 Noticias
- Animación al cambiar de categoría
- Estado activo destacado en verde

### 3.2 Búsqueda
Dos modos de búsqueda:

**Búsqueda Normal:**
- Filtra por título, contenido y tags
- Resultados instantáneos mientras se escribe
- Debounce de 300ms

**Búsqueda Semántica (IA):**
- Activar con botón 🧠
- Entiende el significado, no solo palabras
- Ejemplo: "plantas para balcón pequeño" encuentra artículos sobre cultivo en espacios reducidos aunque no mencionen "balcón"
- Muestra badge de similitud (ej: "🧠 78.5%")
- Debounce de 500ms

### 3.3 Artículos Relacionados
Al final de cada artículo:
- 3 artículos similares
- Algoritmo basado en: tags compartidos, palabras del título, categoría
- Muestra porcentaje de similitud
- Cards con efecto hover

### 3.4 Lista de Lectura
- Icono 🔖 en cada card y artículo
- Guardar para leer después
- Contador en el header
- Persiste entre sesiones
- Toast de confirmación al guardar/quitar

---

## 4. Lectura de Artículos

### 4.1 Barra de Progreso
- Barra fina en la parte superior
- Indica el progreso de lectura
- Color verde que avanza con el scroll

### 4.2 Tabla de Contenidos (TOC)
**Desktop:**
- Sidebar derecho sticky
- Lista de secciones (h2, h3)
- Resalta la sección actual mientras se lee
- Click para saltar a cualquier sección
- Mini barra de progreso

**Móvil:**
- Acordeón colapsable arriba del artículo
- Se expande al tocar
- Misma funcionalidad que desktop

### 4.3 Estructura del Artículo
```
┌─────────────────────────────────────┐
│ Barra de progreso                   │
├─────────────────────────────────────┤
│ Breadcrumbs: Blog > Categoría       │
├─────────────────────────────────────┤
│ Título                              │
│ Fecha • Tiempo lectura • Vistas     │
├─────────────────────────────────────┤
│ Imagen destacada                    │
├─────────────────────────────────────┤
│ TOC móvil (colapsable)              │
├─────────────────────────────────────┤
│ Contenido con headings h2/h3        │
├─────────────────────────────────────┤
│ 🌱 Opinión Editorial TERRApp        │
├─────────────────────────────────────┤
│ 💡 Tips (si aplica)                 │
├─────────────────────────────────────┤
│ Reacciones emoji                    │
├─────────────────────────────────────┤
│ Compartir en redes                  │
├─────────────────────────────────────┤
│ Fuente original                     │
├─────────────────────────────────────┤
│ Tags                                │
├─────────────────────────────────────┤
│ Artículos relacionados              │
└─────────────────────────────────────┘
```

---

## 5. Interacción y Engagement

### 5.1 Reacciones Emoji
4 opciones para reaccionar:
- 🌱 Interesante
- 💚 Me encanta
- 🔥 Importante
- 😐 No me convence

Características:
- Animación "pop" al hacer clic
- Efecto confeti que explota desde el botón
- Solo se puede reaccionar una vez por tipo
- Contador visible de cada reacción

### 5.2 Compartir
Botones para compartir en:
- WhatsApp (abre app con texto)
- Facebook
- Twitter/X
- LinkedIn
- Copiar link (con toast de confirmación)

Muestra total de veces compartido.

### 5.3 Contador de Vistas
- Se registra al abrir el artículo
- Visible en el header del artículo
- También en las cards del listado

---

## 6. Asistente Virtual: Terri

### 6.1 Características
- **Nombre:** Terri (de TERRApp + "terra", tierra en latín)
- **Avatar:** 🌱
- **Ubicación:** Botón flotante abajo a la derecha

### 6.2 Interfaz
- Botón circular verde con icono de chat
- Al abrir: modal con diseño tipo messenger
- Header con avatar y nombre
- Área de mensajes con scroll
- Input de texto + botón enviar
- Botón para limpiar historial

### 6.3 Funcionalidad
- Responde preguntas sobre los artículos del blog
- Busca información relevante automáticamente
- Cita las fuentes con links a los artículos
- Mantiene contexto de la conversación
- Detecta el idioma y responde en el mismo

### 6.4 Ejemplos de Uso
- "¿Cómo empiezo un huerto en mi balcón?"
- "¿Qué plantas son buenas para principiantes?"
- "How do I make compost at home?"
- "Quais são os benefícios da agricultura urbana?"

### 6.5 Feedback Visual
- Indicador de "escribiendo..." (tres puntos animados)
- Mensajes del usuario a la derecha (verde)
- Respuestas de Terri a la izquierda (gris)
- Fuentes mostradas temporalmente debajo

---

## 7. Multi-idioma

### 7.1 Idiomas Soportados
| Idioma | Bandera | Región |
|--------|---------|--------|
| Español | 🇦🇷🇨🇱🇨🇴... | Latinoamérica |
| Portugués | 🇧🇷 | Brasil |
| Inglés | 🇬🇾 | Guyana |
| Francés | 🇬🇫 | Guayana Francesa |
| Neerlandés | 🇸🇷 | Surinam |

### 7.2 Qué se Traduce
- Interfaz completa (botones, labels, mensajes)
- Artículos (título, contenido, opinión, tips)
- Chat Terri (saludo, hints, respuestas)
- Fechas (formato localizado)

### 7.3 Selector de Idioma
- Basado en país (banderas)
- Persiste en cookie
- Cambio sin recargar página

---

## 8. Feedback y Estados

### 8.1 Toast Notifications
Mensajes temporales para:
- "Artículo guardado en tu lista"
- "Artículo eliminado de tu lista"
- "Link copiado al portapapeles"
- "¡Gracias por tu reacción!"
- "Ya reaccionaste a este artículo"
- "🧠 Búsqueda inteligente activada/desactivada"

### 8.2 Skeleton Loaders
Mientras carga:
- Placeholders animados (pulse)
- Mantienen el layout
- Se reemplazan con contenido real

### 8.3 Estados Vacíos
- Sin resultados de búsqueda: Mensaje amigable + sugerencia
- Sin artículos: Icono 🌱 + "Vuelve pronto"

### 8.4 Placeholders de Imagen
Cuando un artículo no tiene imagen:
- Fondo degradado verde
- Título del artículo
- Emoji 🌱
- Patrón decorativo sutil

---

## 9. Responsive Design

### 9.1 Breakpoints
- **Móvil:** < 640px
- **Tablet:** 640px - 1024px
- **Desktop:** > 1024px

### 9.2 Adaptaciones Móviles
- Grid de 1 columna
- TOC colapsable (acordeón)
- Chat ocupa pantalla completa
- Botones más grandes (touch-friendly)
- Fuentes ajustadas para legibilidad

### 9.3 Scroll to Top
- Botón que aparece al bajar 500px
- Scroll suave al hacer clic
- Oculto en la parte superior

---

## 10. Accesibilidad

- Navegación por teclado
- Focus visible en elementos interactivos
- Labels descriptivos
- Contraste de colores adecuado
- Tamaños de fuente escalables

---

## 11. Performance

- Lazy loading de imágenes
- Datos cargados desde JSON estático
- Animaciones optimizadas (transform only)
- Debounce en búsqueda
- Cache de búsquedas semánticas

---

## 12. Resumen Visual de Features

| Feature | Ubicación | Descripción |
|---------|-----------|-------------|
| Bento Grid | Listado | Layout moderno tipo Apple |
| Tipografía Cinética | Cards, títulos | Micro-animaciones en hover |
| Modo Oscuro | Global | Toggle sol/luna |
| Filtro Categorías | Listado | Chips con emojis |
| Búsqueda Normal | Listado | Por palabras clave |
| Búsqueda Semántica | Listado | Por significado (IA) |
| TOC | Artículo | Índice sticky/colapsable |
| Barra Progreso | Artículo | Indicador de lectura |
| Reacciones | Artículo | 4 emojis + confeti |
| Compartir | Artículo | 5 redes + copiar |
| Lista Lectura | Global | Guardar para después |
| Chat Terri | Flotante | Asistente IA |
| Multi-idioma | Global | 5 idiomas |
| Skeleton Loaders | Carga | Placeholders animados |
| Scroll to Top | Flotante | Botón para subir |

---

*Documento para equipo de desarrollo - 2026-01-20*
