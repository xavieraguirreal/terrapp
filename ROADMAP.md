# TERRApp - Roadmap y Pendientes

## Pendiente

_(Sin tareas pendientes actualmente)_

---

## Completado (2026-01-22)

- [x] Aumentar max_results de Tavily de 5 a 12
- [x] Comparar títulos originales de fuente en vez de generados por IA
- [x] Agregar países hemisferio sur (Sudáfrica, Australia, NZ) a dominios
- [x] Agregar `titulo_original` a tabla blog_articulos
- [x] Fix: agregar `fecha_obtenida = NOW()` en INSERT de pendientes
- [x] Aumentar límite de procesamiento de pendientes de 3 a 10
- [x] Aclarar flujo: pendientes.php muestra 2 cosas diferentes:
  - **Pendientes en cache** (168 total, incluye procesados)
  - **Pendientes sin usar** (25 con `usado=0`) - estos son los que se procesan
  - **Borradores** (tabla `blog_articulos`) - artículos ya generados por OpenAI

## Flujo del sistema

```
Tavily/Importar URL
    ↓
blog_noticias_pendientes (usado=0)
    ↓ [Buscar y Generar - máx 10 por vez]
blog_articulos (estado=borrador)
    ↓ [Aprobar]
blog_articulos (estado=programado/publicado)
```
