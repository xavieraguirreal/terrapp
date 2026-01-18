# **Arquitectura y Estrategia Integral para el Blog Moderno en 2025: Un Compendio Técnico de Diseño, Infraestructura y Semántica Artificial**

## **Resumen Ejecutivo y Visión Arquitectónica**

La concepción de un "blog" en el año 2025 ha trascendido la definición tradicional de una bitácora cronológica inversa para convertirse en una plataforma de publicación sofisticada, a menudo denominada "aplicación de conocimiento". La solicitud de investigar las implementaciones más modernas revela una industria en plena metamorfosis, donde la convergencia del diseño modular, la infraestructura distribuida en el borde (*edge computing*) y la inteligencia artificial generativa redefine cómo se crea, distribuye y consume el contenido. Al programar un blog hoy, no se está simplemente instalando un gestor de contenido; se está orquestando un ecosistema tecnológico diseñado para la retención de la atención en una economía digital saturada.

Este informe analiza exhaustivamente cada componente necesario para construir una plataforma de vanguardia. Desde la adopción de interfaces visuales basadas en "Bento Grids" 1 hasta la implementación de motores de búsqueda vectorial mediante *embeddings* 3, el documento sirve como una especificación técnica detallada. Se abordarán las inquietudes específicas sobre contadores de visitas en tiempo real, la arquitectura de la información para categorías y hashtags, y la integración de sistemas de comentarios descentralizados, todo bajo la óptica de la optimización del rendimiento (Core Web Vitals) y la experiencia de usuario (UX).

## **Capítulo 1: La Revolución Visual y la Experiencia de Usuario (UX)**

El diseño web en 2025 ha abandonado las plantillas rígidas de "columna principal \+ barra lateral" en favor de estructuras fluidas y modulares que priorizan la densidad de información sin sacrificar la jerarquía visual. La estética moderna no es meramente ornamental, sino una herramienta funcional para guiar al usuario a través de ecosistemas de contenido cada vez más complejos.

### **1.1 El Dominio del "Bento Grid" y la Arquitectura Modular**

La tendencia más disruptiva en el diseño de interfaces para sitios de contenido es la adopción generalizada del diseño de cuadrícula tipo Bento (*Bento Grid*). Inspirado en las cajas de almuerzo japonesas y popularizado inicialmente por la interfaz promocional de Apple, este estilo ha colonizado el diseño de blogs modernos por su capacidad para organizar contenido heterogéneo de manera coherente.2

A diferencia del flujo lineal tradicional, el Bento Grid permite presentar múltiples jerarquías de información en la primera pantalla (*above the fold*). Un blog moderno implementado bajo esta filosofía no muestra simplemente una lista de entradas recientes. En su lugar, utiliza "celdas" de diferentes dimensiones para destacar:

1. Un artículo pilar o "feature" (celda grande, 2x2).  
2. Métricas de comunidad o contadores de visitas en tiempo real (celda pequeña, 1x1).  
3. Enlaces rápidos a categorías populares o "trending topics" (celda rectangular, 1x2).  
4. Un módulo de suscripción al boletín integrado directamente en la grilla (celda mediana).

La ventaja técnica de este enfoque radica en su implementación mediante CSS Grid Layout. Al definir áreas de plantilla (grid-template-areas), los desarrolladores pueden reordenar radicalmente el contenido para dispositivos móviles sin alterar el DOM HTML, garantizando una accesibilidad semántica óptima. Mientras que en escritorio la cuadrícula ofrece una experiencia de "dashboard" de contenidos, en móvil las celdas se apilan de manera lógica, manteniendo la coherencia visual. La clave del éxito en 2025 es equilibrar la asimetría —combinando bloques grandes y pequeños— con un espaciado uniforme (*gaps*) de 16px o 24px, lo que comunica orden y profesionalismo.2

### **1.2 Minimalismo Funcional vs. Maximalismo Expresivo**

La industria ha superado la dicotomía estricta entre minimalismo y maximalismo, adoptando un enfoque híbrido contextual. Para las páginas de lectura (el artículo individual), impera el "Minimaluxe" o minimalismo de lujo.5 Este estilo se caracteriza por:

* **Tipografía Audaz:** El uso de fuentes *sans-serif* grotescas o *serif* editoriales de gran tamaño para títulos, optimizadas para legibilidad en pantallas de alta densidad (Retina/OLED).  
* **Espacio Negativo:** Márgenes generosos que focalizan la atención exclusivamente en el texto, eliminando barras laterales distractores que históricamente reducían la tasa de retención.

Por el contrario, las páginas de inicio y de aterrizaje abrazan un "Maximalismo Controlado" o Neubrutalismo.6 Aquí, el uso de colores de alto contraste, bordes negros gruesos y sombras duras no difuminadas evoca una estética técnica y cruda que resuena particularmente bien en blogs de desarrollo y tecnología. Este estilo, lejos de ser un error de diseño, es una declaración de intenciones que diferencia al sitio de la homogeneidad de las plantillas corporativas estándar ("Bootstrap look").

### **1.3 Tipografía Cinética y Micro-interacciones**

La interactividad en 2025 se define por la sutileza. La "tipografía cinética" —texto que reacciona al desplazamiento del usuario o a la posición del cursor— transforma los titulares estáticos en elementos vivos.1 Sin embargo, la implementación técnica debe ser cuidadosa para no afectar el rendimiento; el uso de Will-change en CSS y la limitación de animaciones a propiedades de transformación (transform, opacity) son prácticas obligatorias para mantener 60 FPS.

Las micro-interacciones son vitales para la retroalimentación del sistema. En un blog moderno, esto se manifiesta en:

* Botones de "Copiar Código" que ofrecen una animación de confirmación satisfactoria.  
* Índices de contenido (TOC) que iluminan progresivamente la sección activa mediante IntersectionObserver.  
* Barras de progreso de lectura que se adhieren al borde superior, proporcionando al usuario una referencia visual de su avance en artículos largos de formato "Long-form".7

### **1.4 Modo Oscuro Semántico**

El soporte para modo oscuro ya no es opcional. Las implementaciones modernas utilizan variables CSS (:root vs \[data-theme='dark'\]) y frameworks como Tailwind CSS para definir paletas semánticas. No se trata solo de invertir blanco por negro; los blogs de alta gama ajustan la saturación de las imágenes y reducen el contraste del texto (usando grises claros en lugar de blanco puro) para reducir la fatiga visual en entornos de baja luminosidad.8

## **Capítulo 2: Infraestructura Técnica y Stack Tecnológico**

La elección de la tecnología subyacente es la decisión más crítica al programar un blog en 2025\. La batalla por la supremacía se libra entre la renderización híbrida (Next.js) y la arquitectura de islas (Astro), con una clara tendencia hacia esta última para sitios centrados en contenido.

### **2.1 El Gran Debate: Next.js vs. Astro**

Para un desarrollador que busca "lo mejor para implementar en mi blog", la elección depende de la naturaleza dinámica del sitio.

#### **Astro: El Rey del Contenido Estático**

Astro se ha consolidado como la opción predilecta para blogs, portafolios y sitios editoriales en 2025\.9 Su filosofía de "Cero JavaScript por defecto" resuelve el problema histórico de la hidratación excesiva.

* **Arquitectura de Islas:** En lugar de enviar una aplicación React completa que debe ejecutarse en el navegador antes de ser interactiva, Astro envía HTML puro. Solo los componentes que requieren interactividad (como el buscador con IA o la sección de comentarios) se "hidratan" individualmente. Esto reduce drásticamente el *Total Blocking Time* (TBT) y mejora las métricas *Core Web Vitals*.  
* **Flexibilidad de Framework:** Permite utilizar componentes de React, Vue, Svelte o SolidJS dentro del mismo proyecto. Esto es ideal si encuentras una librería de galería perfecta en React y un sistema de comentarios en Svelte; Astro los integra sin problemas.11

#### **Next.js: La Potencia de la Aplicación Web**

Next.js con su *App Router* y *React Server Components* (RSC) sigue siendo una opción formidable, especialmente si el blog es parte de una plataforma SaaS más grande o requiere funcionalidades de usuario complejas (autenticación profunda, dashboards personalizados).9

* **Server Actions:** Facilitan la implementación de lógica de backend (como procesar un formulario de suscripción o un "like") directamente en el componente, eliminando la necesidad de gestionar una API REST separada.

**Recomendación de Implementación:** Si el objetivo principal es un blog de alto rendimiento y SEO técnico impecable, **Astro** es la recomendación técnica superior para 2025\. Si se prevé una evolución hacia una aplicación web compleja con cuentas de usuario y estados de sesión persistentes, **Next.js** ofrece la robustez necesaria.

### **2.2 Gestión de Contenido: Headless CMS vs. MDX**

La era de los CMS monolíticos acoplados al frontend (como el WordPress clásico) ha terminado para los desarrolladores modernos.

* **MDX (Markdown \+ JSX):** Para blogs de programación, MDX es el estándar de oro. Permite escribir contenido en Markdown estándar pero importar y renderizar componentes interactivos de React (como gráficos, demostraciones en vivo o alertas personalizadas) directamente dentro del flujo del texto.13  
* **CMS Headless:** Para equipos editoriales que prefieren una interfaz gráfica, soluciones como **Storyblok** o **Sanity** ofrecen lo mejor de ambos mundos: edición visual en tiempo real y entrega de contenido vía API JSON, permitiendo que el frontend (Astro/Next.js) permanezca ligero y desacoplado.15

### **2.3 Estilizado y Componentes: Tailwind y Shadcn/UI**

La metodología de "Utility-First" con Tailwind CSS es omnipresente en los blogs modernos debido a su capacidad para mantener el CSS pequeño (purgando clases no usadas) y garantizar consistencia visual. La librería **shadcn/ui** ha revolucionado el desarrollo de UI en 2024-2025; no es una biblioteca de componentes tradicional que se instala como dependencia, sino una colección de componentes accesibles (basados en Radix UI) que se copian directamente en el código base. Esto otorga al desarrollador control total sobre el código y el estilo, facilitando la personalización profunda necesaria para un blog único.17

## **Capítulo 3: Inteligencia Artificial y Arquitectura Semántica**

La solicitud del usuario sobre "usar la IA para relacionar artículos" toca el núcleo de la innovación en blogs modernos. En 2025, la IA no se usa solo para generar texto, sino para estructurar y conectar el conocimiento.

### **3.1 Búsqueda Semántica y Relacionamiento de Artículos (Embeddings)**

Los sistemas tradicionales de "artículos relacionados" se basaban en coincidencias de etiquetas (tags) o palabras clave, lo cual es frágil (e.g., un artículo sobre "React" podría no relacionarse con uno sobre "Frontend" si no comparten la etiqueta exacta). La solución moderna es la **Búsqueda Vectorial**.3

#### **Implementación Técnica:**

1. **Generación de Vectores (Embeddings):** Al momento de compilar o publicar un artículo, el contenido (título \+ cuerpo) se envía a una API de *embeddings* (como text-embedding-3-small de OpenAI o modelos open-source de Hugging Face). Esta API devuelve un vector: una lista de números de punto flotante que representan el "significado" multidimensional del texto.  
2. **Almacenamiento Vectorial:** Estos vectores se almacenan en una base de datos especializada. Para un blog, soluciones como **Pinecone**, **Supabase pgvector** o **Turso** son ideales por su integración con el ecosistema JS.3  
3. **Recuperación (Retrieval):**  
   * **Para Artículos Relacionados:** Se calcula la "similitud de coseno" entre el vector del artículo actual y todos los demás en la base de datos. Los resultados con mayor puntuación son semánticamente los más cercanos, independientemente de las palabras clave exactas.  
   * **Para el Buscador:** Cuando el usuario escribe una consulta, esta se convierte en vector y se compara con la base de datos, permitiendo búsquedas conceptuales (e.g., buscar "cómo centrar un div" devolverá artículos sobre Flexbox y Grid incluso si la frase exacta no aparece).

### **3.2 Auto-etiquetado y Clasificación con LLMs**

Para resolver la duda sobre "cómo armar las secciones o categorías", la IA ofrece una solución de consistencia taxonómica.

* **Pipeline de Clasificación:** Se puede implementar un *webhook* o una acción de servidor que se active al guardar un borrador. Este proceso envía el texto del artículo a un LLM (GPT-4o, Claude 3.5) con un prompt de sistema que define la taxonomía del blog.  
* **Prompt Engineering:** "Actúa como un editor jefe. Analiza este artículo y asígnale una categoría única de la siguiente lista:. Además, genera 5 etiquetas (hashtags) relevantes optimizadas para SEO."  
* **Beneficio:** Esto elimina la fragmentación de categorías (e.g., tener "ReactJS", "React.js" y "React" como etiquetas separadas) y asegura una estructura de navegación limpia y lógica.21

### **3.3 Experiencias Conversacionales (Chat con el Blog)**

Una tendencia emergente en 2025 es reemplazar o complementar la barra de búsqueda con una interfaz de chat RAG (*Retrieval-Augmented Generation*). Esto permite a los usuarios "preguntar al blog".

* **Mecanismo:** El usuario pregunta "¿Qué has escrito sobre el rendimiento en Next.js?". El sistema busca los fragmentos relevantes en la base de datos vectorial y los pasa como contexto a un LLM para que genere una respuesta sintetizada basada *únicamente* en el contenido del autor. Esto convierte el blog estático en un oráculo de conocimiento interactivo.20

## **Capítulo 4: Arquitectura de la Información y Taxonomía**

Una estructura de contenido sólida es vital para el SEO y la navegabilidad. La pregunta sobre "cómo armar las secciones" se responde mediante la estrategia de **Topic Clusters** (Grupos de Temas).

### **4.1 Estrategia de Páginas Pilar y Clusters**

En lugar de una nube de etiquetas desordenada, los blogs modernos se estructuran en torno a "Páginas Pilar".23

* **Página Pilar (Hub):** Una página exhaustiva que cubre un tema amplio (ej. "Desarrollo Web con React"). Esta página enlaza a múltiples sub-artículos.  
* **Contenido de Cluster (Spokes):** Artículos específicos que profundizan en subtemas (ej. "Uso de useEffect", "React Server Components"). Estos artículos enlazan entre sí y vuelven a la página pilar.  
* **Implementación en Código:** Las categorías deben ser jerárquicas. En el CMS o MDX, se define una relación parent-child. Visualmente, esto se representa en las "Migas de pan" (Breadcrumbs) y en la URL (ej. /blog/react/hooks/use-effect), lo cual refuerza la autoridad temática ante Google.

### **4.2 Hashtags y Taxonomía Facetada**

Los hashtags deben tratarse como una taxonomía transversal, permitiendo conexiones "muchos a muchos".

* **Implementación:** Mientras que un artículo pertenece a una sola *Categoría* (para definir la URL y la miga de pan), puede tener múltiples *Hashtags*.  
* **Páginas de Etiquetas:** Cada hashtag debe generar automáticamente una página de archivo (/tags/javascript) que no solo liste los artículos, sino que muestre estadísticas (ej. "45 artículos escritos sobre JavaScript") y posiblemente una descripción generada por IA sobre el enfoque del autor respecto a ese tema.

## **Capítulo 5: Funcionalidades de Engagement y Comunidad**

Para convertir visitantes en una comunidad, el blog debe ofrecer mecanismos de interacción modernos y sin fricción.

### **5.1 Sistema de Comentarios: Giscus y la Descentralización**

Los sistemas de comentarios tradicionales (Disqus, Facebook Comments) son pesados, rastrean a los usuarios e insertan publicidad. La implementación "best-in-class" para blogs técnicos en 2025 es **Giscus**.25

* **Cómo funciona:** Utiliza la API de "Discussions" de GitHub. Los comentarios se almacenan realmente como discusiones en un repositorio de GitHub vinculado al blog.  
* **Ventajas:** Es de código abierto, gratuito, sin anuncios, soporta Markdown y resaltado de código, y hereda el sistema de autenticación de GitHub (lo que reduce drásticamente el spam y eleva la calidad técnica de los comentarios).  
* **Integración:** Se implementa mediante un componente ligero de React/Web Component que se comunica con la API de GitHub.

### **5.2 Contadores de Visitas y Analítica en Tiempo Real**

El usuario solicitó explícitamente un "contador de visitas". En el diseño moderno, esto no es el viejo contador gif de los 90, sino una métrica de prueba social elegante.

* **Implementación de Privacidad (Analytics):** Herramientas como **Umami** o **Plausible** son esenciales. A diferencia de Google Analytics, son ligeras (\<2KB), no usan cookies (cumplimiento GDPR sin banners molestos) y pueden ser auto-alojadas. Ofrecen APIs para extraer el número de visitas programáticamente.27  
* **Contador en Tiempo Real:** Para mostrar "X personas leyendo ahora", se puede utilizar una base de datos clave-valor de baja latencia como **Redis** (ej. Upstash).  
  * Cada vez que se carga una página, se incrementa un contador en Redis.  
  * Se utiliza un componente Server Component o una *Edge Function* para leer este valor y renderizarlo estáticamente o hidratarlo en el cliente.  
  * **Deduplicación:** Para evitar inflar los números con recargas, se puede usar un hash de la IP o sesión almacenado temporalmente en Redis con un TTL (Time To Live) de 24 horas.

### **5.3 Reacciones (Claps/Likes)**

Inspirado en Medium, un sistema de "aplausos" permite feedback de bajo esfuerzo.

* **Técnica:** Se implementa con un botón cliente que invoca una *Server Action* o API Route.  
* **Debouncing:** Es crítico implementar "debouncing" (esperar a que el usuario deje de hacer clic por 500ms) antes de enviar la petición al servidor para evitar saturar la base de datos con escrituras innecesarias. Se puede usar animaciones SVG (como confeti o corazones explotando) para dar feedback inmediato antes de que la petición se complete.29

## **Capítulo 6: Componentes Avanzados para Artículos**

El contenido del artículo debe estar enriquecido con componentes que mejoren la legibilidad y la utilidad.

### **6.1 Tabla de Contenidos (TOC) Inteligente**

Para artículos largos, una TOC es obligatoria.

* **Sticky y Activa:** Debe permanecer fija en una barra lateral (escritorio) y resaltar la sección actual conforme el usuario hace scroll. Esto se logra técnicamente con la API IntersectionObserver de JavaScript, que detecta qué encabezado (h2, h3) está visible en el viewport.30  
* **Móvil:** En móviles, la TOC debe ser un acordeón colapsable al inicio o un botón flotante discreto.

### **6.2 Bloques de Código de Nueva Generación**

Para un blog de programación, el bloque de código es el ciudadano de primera clase.

* **Shiki / Prism:** Utilizar resaltadores de sintaxis en tiempo de compilación (*build-time syntax highlighting*). Esto significa que el HTML se genera ya coloreado con estilos en línea o clases CSS, evitando que el navegador tenga que ejecutar JavaScript pesado para pintar el código.  
* **Funcionalidades:** Cada bloque debe tener:  
  1. Nombre del archivo y lenguaje.  
  2. Botón de copiar con feedback visual.  
  3. Posibilidad de resaltar líneas específicas (diffs) para mostrar cambios de código.32

### **6.3 Indicadores de Lectura**

* **Tiempo Estimado:** Calcularlo automáticamente basándose en el conteo de palabras (aprox. 200 palabras/minuto) y mostrarlo en la cabecera.  
* **Barra de Progreso:** Una línea delgada en el tope de la ventana (position: fixed) que crece en anchura del 0% al 100% basada en la posición del scroll (window.scrollY). Esto aprovecha el efecto psicológico Zeigarnik para motivar al usuario a terminar la tarea de leer.

## **Capítulo 7: Monetización y Sostenibilidad**

Un blog moderno debe estar preparado para ser sostenible económicamente.

### **7.1 Muros de Pago (Paywalls) y Middleware**

Si se desea ofrecer contenido premium, la implementación moderna se realiza a nivel de servidor o borde (*Edge*), no en el cliente.

* **Protección Robusta:** Utilizando el *Middleware* de Next.js o Astro, se verifica la sesión del usuario (cookie/token) y su estado de suscripción contra una base de datos (o API de Stripe) *antes* de renderizar la página. Si no tiene permiso, se redirige o se muestra una versión truncada. Esto es más seguro que ocultar contenido con CSS, que puede ser burlado fácilmente.34

### **7.2 Integración de Newsletters**

El correo electrónico es el canal de distribución más potente.

* **API vs Embed:** No usar los formularios iframe lentos que proveen servicios como Mailchimp o ConvertKit. En su lugar, utilizar sus APIs para construir formularios nativos en el blog que se sientan parte del diseño. Esto mejora el rendimiento y la tasa de conversión.36

### **7.3 Web Stories de Google**

Para captar tráfico móvil desde Google Discover, se recomienda implementar **Google Web Stories**. Son páginas AMP visuales. Existen plugins y librerías para Astro/Next.js que permiten generar estas historias programáticamente a partir del contenido existente del blog, abriendo un nuevo canal de adquisición de tráfico.38

## **Conclusión**

Implementar un blog en 2025 es un ejercicio de orquestación tecnológica avanzada. La combinación ganadora para "lo mejor" implica:

1. **Diseño:** Interfaz modular **Bento Grid** con tipografía cuidada y modo oscuro semántico.  
2. **Core:** **Astro** para un rendimiento estático inigualable, o **Next.js** si se requiere complejidad dinámica.  
3. **Inteligencia:** Base de datos vectorial (**Pinecone**) para relacionar contenido y búsqueda semántica real.  
4. **Comunidad:** Comentarios vía **Giscus** y analítica privada con **Umami**.  
5. **Infraestructura:** Despliegue en el borde (*Edge*) para latencia mínima global.

Esta arquitectura no solo garantiza un sitio web rápido y estéticamente agradable, sino una plataforma de conocimiento escalable, capaz de entender su propio contenido y servirlo de manera inteligente a una audiencia global.

### ---

**Tabla Comparativa de Tecnologías Recomendadas (2025)**

| Componente | Opción Recomendada (Stack Moderno) | Alternativa Tradicional (Legacy) | Por qué elegir la moderna |
| :---- | :---- | :---- | :---- |
| **Framework** | **Astro** (Islas) | WordPress / Jekyll | Rendimiento JS nulo por defecto, flexibilidad de componentes. |
| **Estilos** | **Tailwind CSS \+ Shadcn/ui** | Bootstrap / CSS Puro | Desarrollo rápido, sistema de diseño consistente, purga de CSS no usado. |
| **Comentarios** | **Giscus** (GitHub Discussions) | Disqus / Facebook | Sin anuncios, sin tracking, autenticación segura, soporte Markdown. |
| **Buscador** | **Búsqueda Vectorial** (Embeddings) | Búsqueda por palabras clave (SQL LIKE) | Entiende el contexto y sinónimos, permite "artículos relacionados" inteligentes. |
| **Analítica** | **Umami / Plausible** | Google Analytics 4 | Privacidad (GDPR), peso ligero, propiedad de los datos. |
| **Base de Datos** | **Turso / Supabase** (SQL \+ Vectores) | MySQL Compartido | Escalabilidad en el borde (*Edge*), soporte nativo de vectores. |
| **Hosting** | **Vercel / Netlify** (Edge) | Hosting compartido (cPanel) | CDN global, CI/CD automático, funciones serverless. |

#### **Fuentes citadas**

1. Web Design Trends 2025: 4 Practical Ways for Small Businesses to Build Trust, acceso: enero 18, 2026, [https://www.sltcreative.com/unique-web-design-trends-that-will-dominate](https://www.sltcreative.com/unique-web-design-trends-that-will-dominate)  
2. How to Master Bento Grid Layouts for Stunning Websites in 2025, acceso: enero 18, 2026, [https://ecommercewebdesign.agency/how-to-master-bento-grid-layouts-for-stunning-websites-in-2025/](https://ecommercewebdesign.agency/how-to-master-bento-grid-layouts-for-stunning-websites-in-2025/)  
3. Semantic search with Pinecone and OpenAI, acceso: enero 18, 2026, [https://cookbook.openai.com/examples/vector\_databases/pinecone/semantic\_search](https://cookbook.openai.com/examples/vector_databases/pinecone/semantic_search)  
4. Web design trend: bento box \- Medium, acceso: enero 18, 2026, [https://medium.com/design-bootcamp/web-design-trend-bento-box-95814d99ac62](https://medium.com/design-bootcamp/web-design-trend-bento-box-95814d99ac62)  
5. Maximalism vs. Minimaluxe: Finding Your Style in 2025's Top Interior Trends, acceso: enero 18, 2026, [https://www.abodeaboveinteriors.com/blog/2025/maximalist-vs-minimaluxe](https://www.abodeaboveinteriors.com/blog/2025/maximalist-vs-minimaluxe)  
6. 25 Top Web Design Trends 2025: From Neubrutalism to Dynamic UI \- DepositPhotos Blog, acceso: enero 18, 2026, [https://blog.depositphotos.com/web-design-trends-2025.html](https://blog.depositphotos.com/web-design-trends-2025.html)  
7. 2025 Web Design Trends No One Is Talking About\! (Until Now) \- YouTube, acceso: enero 18, 2026, [https://www.youtube.com/watch?v=YAqsOI4RBV8](https://www.youtube.com/watch?v=YAqsOI4RBV8)  
8. Best UI/UX Design Trends to Follow in 2025 | by Rahim Ladhani \- Medium, acceso: enero 18, 2026, [https://nevinainfotech25.medium.com/best-ui-ux-design-trends-to-follow-in-2025-c31d3e62779c](https://nevinainfotech25.medium.com/best-ui-ux-design-trends-to-follow-in-2025-c31d3e62779c)  
9. Astro vs Next.js Comparison for Modern Web Apps \- Tailkits, acceso: enero 18, 2026, [https://tailkits.com/blog/astro-vs-nextjs/](https://tailkits.com/blog/astro-vs-nextjs/)  
10. Astro vs Next.js in 2025: A Comprehensive Comparison \- Lexington Themes, acceso: enero 18, 2026, [https://lexingtonthemes.com/blog/astro-vs-nextjs-in-2025-a-comprehensive-comparison.html](https://lexingtonthemes.com/blog/astro-vs-nextjs-in-2025-a-comprehensive-comparison.html)  
11. Why I Built MadeWithAstro.co and My Thoughts on Next.js vs. Astro : r/astrojs \- Reddit, acceso: enero 18, 2026, [https://www.reddit.com/r/astrojs/comments/1ghwy9s/why\_i\_built\_madewithastroco\_and\_my\_thoughts\_on/](https://www.reddit.com/r/astrojs/comments/1ghwy9s/why_i_built_madewithastroco_and_my_thoughts_on/)  
12. Next.js vs. Astro in 2025: Which Framework Is Best for Your Marketing Website?, acceso: enero 18, 2026, [https://makersden.io/blog/nextjs-vs-astro-in-2025-which-framework-best-for-your-marketing-website](https://makersden.io/blog/nextjs-vs-astro-in-2025-which-framework-best-for-your-marketing-website)  
13. Guides: MDX \- Next.js, acceso: enero 18, 2026, [https://nextjs.org/docs/app/guides/mdx](https://nextjs.org/docs/app/guides/mdx)  
14. Add a copy button to your Rehype (NextJS / MDX) code snippets \- Ty Barho, acceso: enero 18, 2026, [https://www.tybarho.com/articles/adding-a-copy-button-mdx-code-snippets](https://www.tybarho.com/articles/adding-a-copy-button-mdx-code-snippets)  
15. Integrate Astro with Storyblok, acceso: enero 18, 2026, [https://www.storyblok.com/docs/guides/astro](https://www.storyblok.com/docs/guides/astro)  
16. How I set up my BlogSite with Astro and Storyblok \- JavaScript in Plain English, acceso: enero 18, 2026, [https://javascript.plainenglish.io/how-i-set-up-my-blogsite-with-astro-and-storyblok-4faa6b016313](https://javascript.plainenglish.io/how-i-set-up-my-blogsite-with-astro-and-storyblok-4faa6b016313)  
17. Headless UI \- Unstyled, fully accessible UI components, acceso: enero 18, 2026, [https://headlessui.com/](https://headlessui.com/)  
18. BryceRussell/astro-headless-ui: A headless component library for Astro \- GitHub, acceso: enero 18, 2026, [https://github.com/BryceRussell/astro-headless-ui](https://github.com/BryceRussell/astro-headless-ui)  
19. Implementing vector search with OpenAI, Next.js, and Supabase \- LogRocket Blog, acceso: enero 18, 2026, [https://blog.logrocket.com/openai-vector-search-next-js-supabase/](https://blog.logrocket.com/openai-vector-search-next-js-supabase/)  
20. Building a Document-Based Chatbot with Next.js, LangChain, Pinecone, and GPT-4o LLM, acceso: enero 18, 2026, [https://medium.com/@ablahum/my-experience-on-building-a-document-based-chatbot-with-next-js-01d04d46e05e](https://medium.com/@ablahum/my-experience-on-building-a-document-based-chatbot-with-next-js-01d04d46e05e)  
21. Automate content tagging in 4 steps with AI \- KNIME, acceso: enero 18, 2026, [https://www.knime.com/blog/content-tagging-with-AI](https://www.knime.com/blog/content-tagging-with-AI)  
22. How to Leverage LLMs for Auto-tagging & Content Enrichment \- Enterprise Knowledge, acceso: enero 18, 2026, [https://enterprise-knowledge.com/how-to-leverage-llms-for-auto-tagging-content-enrichment/](https://enterprise-knowledge.com/how-to-leverage-llms-for-auto-tagging-content-enrichment/)  
23. Topic Cluster and Pillar Page SEO Guide \[Free Template\] \- Conductor, acceso: enero 18, 2026, [https://www.conductor.com/academy/topic-clusters/](https://www.conductor.com/academy/topic-clusters/)  
24. Designing Pillar Pages for Maximum SEO Impact \- Siteimprove, acceso: enero 18, 2026, [https://www.siteimprove.com/blog/pillar-page-design/](https://www.siteimprove.com/blog/pillar-page-design/)  
25. Setting Up Giscus: An Ad-Free Alternative to Disqus for Blog Comments, acceso: enero 18, 2026, [https://chocapikk.com/posts/2025/setting-up-giscus-comments/](https://chocapikk.com/posts/2025/setting-up-giscus-comments/)  
26. Moving from utterances to giscus \- Max Brenner, acceso: enero 18, 2026, [https://shipit.dev/posts/from-utterances-to-giscus.html](https://shipit.dev/posts/from-utterances-to-giscus.html)  
27. Best Google Analytics Alternatives: Umami, Plausible, Matomo \- AccuWeb Hosting, acceso: enero 18, 2026, [https://www.accuwebhosting.com/blog/best-google-analytics-alternatives/](https://www.accuwebhosting.com/blog/best-google-analytics-alternatives/)  
28. Comparing four privacy-focused google analytics alternatives \- Mark Pitblado, acceso: enero 18, 2026, [https://www.markpitblado.me/blog/comparing-four-privacy-focused-google-analytics-alternatives/](https://www.markpitblado.me/blog/comparing-four-privacy-focused-google-analytics-alternatives/)  
29. Building an interactive live reaction app with Next.js and Momento, acceso: enero 18, 2026, [https://www.gomomento.com/blog/building-an-interactive-live-reaction-app-with-next-js-and-momento%F0%9F%8E%AF/](https://www.gomomento.com/blog/building-an-interactive-live-reaction-app-with-next-js-and-momento%F0%9F%8E%AF/)  
30. Sticky Table of Contents with Scrolling Active States \- CSS-Tricks, acceso: enero 18, 2026, [https://css-tricks.com/sticky-table-of-contents-with-scrolling-active-states/](https://css-tricks.com/sticky-table-of-contents-with-scrolling-active-states/)  
31. Writing a React Table of Contents Component | Eyas's Blog, acceso: enero 18, 2026, [https://blog.eyas.sh/2022/03/react-toc/](https://blog.eyas.sh/2022/03/react-toc/)  
32. Adding 'Copy Button' to Code Blocks Using Custom MDX Components \- Modern Next.js Blog Series \#16 \- Eason Chang, acceso: enero 18, 2026, [https://easonchang.com/posts/code-copy-button](https://easonchang.com/posts/code-copy-button)  
33. Adding a Copy to Clipboard Button in MDX | SHSF Work \- Medium, acceso: enero 18, 2026, [https://medium.com/shsfwork/how-to-add-a-copy-to-clipboard-button-in-mdx-with-next-js-e1a182f40690](https://medium.com/shsfwork/how-to-add-a-copy-to-clipboard-button-in-mdx-with-next-js-e1a182f40690)  
34. Build a Stripe-hosted checkout page \- Stripe Documentation, acceso: enero 18, 2026, [https://docs.stripe.com/checkout/quickstart?client=next](https://docs.stripe.com/checkout/quickstart?client=next)  
35. Secure static paid content in Next.js (App Router) \- Eric Burel, acceso: enero 18, 2026, [https://www.ericburel.tech/blog/static-paid-content-app-router](https://www.ericburel.tech/blog/static-paid-content-app-router)  
36. How to make a Convertkit sign-up form with Next.js \- Makerkit, acceso: enero 18, 2026, [https://makerkit.dev/blog/tutorials/make-convertkit-signup-form-nextjs](https://makerkit.dev/blog/tutorials/make-convertkit-signup-form-nextjs)  
37. How to implement a Custom Newsletter form with ConvertKit and Effect \- Sandro Maglione, acceso: enero 18, 2026, [https://www.sandromaglione.com/articles/how-to-implement-a-custom-newsletter-form-with-convertkit-and-effect](https://www.sandromaglione.com/articles/how-to-implement-a-custom-newsletter-form-with-convertkit-and-effect)  
38. How to Create Google Web Stories for Any Website \- EmbedSocial, acceso: enero 18, 2026, [https://embedsocial.com/blog/google-web-stories/](https://embedsocial.com/blog/google-web-stories/)  
39. What Are Google Web Stories And How To Create Them ? \- NetConnect Digital Agency, acceso: enero 18, 2026, [https://netconnectdigital.com/what-are-google-web-stories/](https://netconnectdigital.com/what-are-google-web-stories/)