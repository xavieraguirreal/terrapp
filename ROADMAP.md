# TERRApp - Roadmap y Pendientes

## EN PROGRESO - Bug pendientes.php (2026-01-22)

### Problema
Los registros importados desde `importar_url.php` se guardan correctamente en `blog_noticias_pendientes` pero NO aparecen en la página `pendientes.php`.

### Lo que sabemos
- Los registros EXISTEN en la base de datos (verificado en phpMyAdmin)
- `usado = 0` (correcto)
- `fecha_obtenida` tiene fecha válida (2026-01-22 10:33:24)
- El sistema detecta duplicados correctamente (la URL ya está guardada)
- Pero la página pendientes.php no los muestra

### Debug agregado
Se subió `blog/admin/pendientes.php` con código de debug:
- Muestra errores PHP
- Agrega comentarios HTML con info de la query

### Próximo paso
1. Ir a https://terrapp.verumax.com/blog/admin/pendientes.php
2. Click derecho → "Ver código fuente"
3. Buscar líneas `<!-- DEBUG:` para ver qué devuelve la query
4. Si dice "Total registros encontrados: 0" pero COUNT(*) devuelve registros, hay un problema con la conexión o la query

### Archivos involucrados
- `blog/admin/pendientes.php` - página que muestra pendientes
- `blog/admin/importar_url.php` - página para importar URLs
- `blog/admin/includes/functions.php` - función `guardarCandidatasPendientes()`

---

## Completado (2026-01-22)

- [x] Aumentar max_results de Tavily de 5 a 12
- [x] Comparar títulos originales de fuente en vez de generados por IA
- [x] Agregar países hemisferio sur (Sudáfrica, Australia, NZ) a dominios
- [x] Agregar `titulo_original` a tabla blog_articulos
- [x] Fix: agregar `fecha_obtenida = NOW()` en INSERT de pendientes
