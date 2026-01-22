<?php
/**
 * TERRApp Blog - Funciones auxiliares
 */

require_once __DIR__ . '/../config/database.php';

// ============================================
// FUNCIONES DE URLs Y DUPLICADOS
// ============================================

/**
 * Verifica si una URL ya fue procesada
 */
function urlYaProcesada(string $url): bool {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id FROM blog_urls_procesadas WHERE url = ?");
    $stmt->execute([$url]);
    return $stmt->fetch() !== false;
}

/**
 * Registra una URL como procesada
 */
function registrarUrl(string $url): void {
    $pdo = getConnection();
    $stmt = $pdo->prepare("INSERT IGNORE INTO blog_urls_procesadas (url) VALUES (?)");
    $stmt->execute([$url]);
}

/**
 * Verifica si un artículo es duplicado comparando título y URL
 * contra artículos existentes en cualquier estado (borrador, publicado, rechazado, programado)
 *
 * @param string $titulo Título del artículo a verificar
 * @param string $url URL de la fuente original
 * @return array|null Información del duplicado si existe, null si no es duplicado
 */
function verificarDuplicado(string $titulo, string $url): ?array {
    $pdo = getConnection();

    // Normalizar título para comparación
    $tituloNormalizado = mb_strtolower(trim($titulo));
    $tituloNormalizado = preg_replace('/[^\p{L}\p{N}\s]/u', '', $tituloNormalizado);

    // 1. Buscar por URL exacta de fuente
    $stmt = $pdo->prepare("
        SELECT id, titulo, estado, fuente_url
        FROM blog_articulos
        WHERE fuente_url = ?
        LIMIT 1
    ");
    $stmt->execute([$url]);
    $duplicado = $stmt->fetch();

    if ($duplicado) {
        return [
            'tipo' => 'url',
            'articulo_id' => $duplicado['id'],
            'titulo_existente' => $duplicado['titulo'],
            'estado' => $duplicado['estado']
        ];
    }

    // 2. Buscar por título similar (usando LIKE con las primeras palabras)
    $palabras = explode(' ', $tituloNormalizado);
    $palabrasClave = array_slice($palabras, 0, 5); // Primeras 5 palabras
    $busqueda = '%' . implode('%', $palabrasClave) . '%';

    $stmt = $pdo->prepare("
        SELECT id, titulo, estado, fuente_url
        FROM blog_articulos
        WHERE LOWER(titulo) LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$busqueda]);
    $duplicado = $stmt->fetch();

    if ($duplicado) {
        // Verificar similitud más estricta (Levenshtein o similar)
        $tituloExistente = mb_strtolower($duplicado['titulo']);
        $tituloExistente = preg_replace('/[^\p{L}\p{N}\s]/u', '', $tituloExistente);

        // Si el título comienza igual o es muy similar
        similar_text($tituloNormalizado, $tituloExistente, $porcentaje);

        if ($porcentaje > 70) { // 70% de similitud
            return [
                'tipo' => 'titulo',
                'articulo_id' => $duplicado['id'],
                'titulo_existente' => $duplicado['titulo'],
                'estado' => $duplicado['estado'],
                'similitud' => round($porcentaje, 1) . '%'
            ];
        }
    }

    return null;
}

// ============================================
// FUNCIONES DE IMÁGENES
// ============================================

/**
 * Descarga una imagen externa y la guarda localmente
 *
 * @param string $url URL de la imagen externa
 * @param int $articuloId ID del artículo para nombrar el archivo
 * @return string|null Ruta relativa de la imagen guardada o null si falla
 */
function descargarImagenArticulo(string $url, int $articuloId): ?string {
    if (empty($url)) {
        return null;
    }

    // Carpeta de destino
    $uploadDir = __DIR__ . '/../../uploads/articulos/';
    $webPath = 'uploads/articulos/'; // Ruta desde la raíz del blog

    // Crear carpeta si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    try {
        // Obtener extensión de la URL
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = strtolower($pathInfo['extension'] ?? 'jpg');

        // Validar extensión
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg';
        }

        // Nombre del archivo: articulo-{id}-{timestamp}.{ext}
        $filename = "articulo-{$articuloId}-" . time() . ".{$extension}";
        $filepath = $uploadDir . $filename;

        // Descargar imagen con cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // Verificar que se descargó correctamente
        if ($httpCode !== 200 || empty($imageData)) {
            logError("Error descargando imagen: HTTP {$httpCode} - {$url}");
            return null;
        }

        // Verificar que es una imagen
        if (strpos($contentType, 'image/') === false) {
            logError("URL no es imagen válida: {$contentType} - {$url}");
            return null;
        }

        // Guardar archivo
        if (file_put_contents($filepath, $imageData) === false) {
            logError("Error guardando imagen: {$filepath}");
            return null;
        }

        // Devolver ruta web relativa
        return $webPath . $filename;

    } catch (Exception $e) {
        logError("Excepción descargando imagen: " . $e->getMessage());
        return null;
    }
}

/**
 * Actualiza la imagen de un artículo descargándola localmente
 */
function actualizarImagenArticulo(int $articuloId): bool {
    $pdo = getConnection();

    // Obtener URL actual de la imagen
    $stmt = $pdo->prepare("SELECT imagen_url FROM blog_articulos WHERE id = ?");
    $stmt->execute([$articuloId]);
    $articulo = $stmt->fetch();

    if (!$articulo || empty($articulo['imagen_url'])) {
        return false;
    }

    $urlActual = $articulo['imagen_url'];

    // Si ya es una imagen local, no hacer nada
    if (strpos($urlActual, 'uploads/') !== false || strpos($urlActual, '/uploads/') !== false) {
        return true;
    }

    // Descargar imagen
    $localPath = descargarImagenArticulo($urlActual, $articuloId);

    if ($localPath) {
        // Actualizar en BD con la ruta local
        $stmt = $pdo->prepare("UPDATE blog_articulos SET imagen_url = ? WHERE id = ?");
        return $stmt->execute([$localPath, $articuloId]);
    }

    return false;
}

/**
 * Verifica si un título es muy similar a uno existente
 * Usa múltiples métodos: similitud de texto + palabras clave
 */
function tituloEsSimilar(string $titulo, ?string &$tituloSimilar = null): bool {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT titulo FROM blog_articulos WHERE estado IN ('publicado', 'rechazado', 'borrador')");
    $titulosExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tituloNormalizado = normalizarTexto($titulo);
    $palabrasNuevo = extraerPalabrasClave($titulo);

    foreach ($titulosExistentes as $existente) {
        $existenteNormalizado = normalizarTexto($existente);

        // Método 1: Similitud de texto directo
        similar_text($tituloNormalizado, $existenteNormalizado, $porcentajeSimilar);
        if ($porcentajeSimilar > 60) {
            $tituloSimilar = $existente;
            return true;
        }

        // Método 2: Similitud por palabras clave (Jaccard)
        $palabrasExistente = extraerPalabrasClave($existente);
        $similitudJaccard = calcularSimilitudJaccard($palabrasNuevo, $palabrasExistente);
        if ($similitudJaccard > 0.5) {
            $tituloSimilar = $existente;
            return true;
        }

        // Método 3: Mismas palabras importantes (para noticias de mismo evento)
        $palabrasImportantes = array_intersect($palabrasNuevo, $palabrasExistente);
        $palabrasComunes = count($palabrasImportantes);
        // Si comparten 3+ palabras clave importantes, probablemente es la misma noticia
        if ($palabrasComunes >= 3 && count($palabrasNuevo) <= 8) {
            $tituloSimilar = $existente;
            return true;
        }
    }

    return false;
}

/**
 * Extrae palabras clave significativas de un texto
 * Elimina stopwords y palabras muy cortas
 */
function extraerPalabrasClave(string $texto): array {
    $stopwords = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'al', 'a', 'en', 'con', 'por', 'para', 'sin', 'sobre',
        'que', 'es', 'son', 'fue', 'ser', 'han', 'ha', 'hay',
        'se', 'su', 'sus', 'este', 'esta', 'esto', 'estos', 'estas',
        'mas', 'pero', 'como', 'cuando', 'donde', 'si', 'no', 'ya',
        'entre', 'desde', 'hasta', 'hacia', 'bajo', 'ante',
        'y', 'o', 'ni', 'e', 'u', 'the', 'and', 'or', 'of', 'in', 'to', 'for'
    ];

    $texto = normalizarTexto($texto);
    $palabras = explode(' ', $texto);

    // Filtrar stopwords y palabras muy cortas
    $palabrasClave = array_filter($palabras, function($p) use ($stopwords) {
        return strlen($p) >= 4 && !in_array($p, $stopwords);
    });

    return array_values($palabrasClave);
}

/**
 * Calcula similitud de Jaccard entre dos conjuntos de palabras
 */
function calcularSimilitudJaccard(array $set1, array $set2): float {
    if (empty($set1) || empty($set2)) {
        return 0.0;
    }

    $interseccion = count(array_intersect($set1, $set2));
    $union = count(array_unique(array_merge($set1, $set2)));

    return $union > 0 ? $interseccion / $union : 0.0;
}

/**
 * Normaliza texto para comparación
 */
function normalizarTexto(string $texto): string {
    $texto = mb_strtolower($texto);
    $texto = preg_replace('/[áàäâã]/u', 'a', $texto);
    $texto = preg_replace('/[éèëê]/u', 'e', $texto);
    $texto = preg_replace('/[íìïî]/u', 'i', $texto);
    $texto = preg_replace('/[óòöôõ]/u', 'o', $texto);
    $texto = preg_replace('/[úùüû]/u', 'u', $texto);
    $texto = preg_replace('/[ñ]/u', 'n', $texto);
    $texto = preg_replace('/[ç]/u', 'c', $texto);
    $texto = preg_replace('/[^a-z0-9\s]/u', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

/**
 * Genera slug a partir de título
 */
function generarSlug(string $titulo): string {
    $slug = normalizarTexto($titulo);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = substr($slug, 0, 100);

    // Verificar unicidad
    $pdo = getConnection();
    $baseSlug = $slug;
    $contador = 1;

    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM blog_articulos WHERE slug = ?");
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            break;
        }
        $slug = $baseSlug . '-' . $contador;
        $contador++;
    }

    return $slug;
}

// ============================================
// FUNCIONES DE NOTICIAS PENDIENTES (CACHE)
// ============================================

/**
 * Guarda candidatas de Tavily en la tabla de pendientes
 */
function guardarCandidatasPendientes(array $candidatas): int {
    $pdo = getConnection();
    $guardadas = 0;

    // Títulos ya procesados en este batch para evitar duplicados dentro del mismo batch
    $titulosEnEsteBatch = [];

    // Obtener títulos de pendientes existentes para comparar
    $stmtPendientes = $pdo->query("SELECT titulo FROM blog_noticias_pendientes WHERE usado = 0");
    $titulosPendientes = $stmtPendientes->fetchAll(PDO::FETCH_COLUMN);

    foreach ($candidatas as $c) {
        $url = $c['url'] ?? '';
        if (empty($url)) continue;

        if (urlYaProcesada($url)) continue;

        $titulo = $c['title'] ?? $c['titulo'] ?? '';
        if (empty($titulo)) continue;

        // Verificar contra artículos en BD
        if (tituloEsSimilar($titulo)) continue;

        // Verificar contra pendientes existentes
        if (tituloEsSimilarEnLista($titulo, $titulosPendientes)) continue;

        // Verificar contra otros títulos de este mismo batch
        if (tituloEsSimilarEnLista($titulo, $titulosEnEsteBatch)) continue;

        // Agregar a la lista de este batch
        $titulosEnEsteBatch[] = $titulo;

        // Detectar región por dominio
        $region = detectarRegionPorDominio($url);

        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO blog_noticias_pendientes
                (url, titulo, descripcion, contenido, imagen_url, fuente, region)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $url,
                $titulo,
                mb_substr($c['content'] ?? '', 0, 1000),
                $c['raw_content'] ?? $c['content'] ?? '',
                $c['image'] ?? null,
                parse_url($url, PHP_URL_HOST),
                $region
            ]);
            if ($stmt->rowCount() > 0) $guardadas++;
        } catch (Exception $e) {
            // Ignorar duplicados
        }
    }

    return $guardadas;
}

/**
 * Verifica si un título es similar a alguno en una lista dada
 */
function tituloEsSimilarEnLista(string $titulo, array $listaTitulos): bool {
    if (empty($listaTitulos)) return false;

    $tituloNormalizado = normalizarTexto($titulo);
    $palabrasNuevo = extraerPalabrasClave($titulo);

    foreach ($listaTitulos as $existente) {
        if (empty($existente)) continue;

        $existenteNormalizado = normalizarTexto($existente);

        // Método 1: Similitud de texto directo
        similar_text($tituloNormalizado, $existenteNormalizado, $porcentajeSimilar);
        if ($porcentajeSimilar > 60) {
            return true;
        }

        // Método 2: Similitud por palabras clave (Jaccard)
        $palabrasExistente = extraerPalabrasClave($existente);
        $similitudJaccard = calcularSimilitudJaccard($palabrasNuevo, $palabrasExistente);
        if ($similitudJaccard > 0.5) {
            return true;
        }

        // Método 3: Mismas palabras importantes
        $palabrasComunes = count(array_intersect($palabrasNuevo, $palabrasExistente));
        if ($palabrasComunes >= 3 && count($palabrasNuevo) <= 8) {
            return true;
        }
    }

    return false;
}

/**
 * Detecta región por dominio
 */
function detectarRegionPorDominio(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    $dominiosSudamerica = DOMINIOS_SUDAMERICA ?? ['.ar', '.br', '.cl', '.co', '.pe', '.ec', '.uy', '.py', '.bo', '.ve'];

    foreach ($dominiosSudamerica as $tld) {
        if (str_ends_with($host, $tld)) {
            return 'sudamerica';
        }
    }
    return 'internacional';
}

/**
 * Obtiene la siguiente noticia pendiente para procesar
 */
function obtenerPendiente(): ?array {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM blog_noticias_pendientes WHERE usado = 0 ORDER BY fecha_obtenida ASC LIMIT 1");
    $pendiente = $stmt->fetch();
    return $pendiente ?: null;
}

/**
 * Marca una pendiente como usada
 */
function marcarPendienteUsada(int $id): void {
    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE blog_noticias_pendientes SET usado = 1 WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Cuenta pendientes disponibles
 */
function contarPendientes(): int {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM blog_noticias_pendientes WHERE usado = 0");
    return (int) $stmt->fetchColumn();
}

// ============================================
// FUNCIONES DE ARTÍCULOS
// ============================================

/**
 * Guarda un artículo en la base de datos
 */
function guardarArticulo(array $datos): int {
    $pdo = getConnection();

    $slug = generarSlug($datos['titulo']);
    $tips = !empty($datos['tips']) ? json_encode($datos['tips'], JSON_UNESCAPED_UNICODE) : null;
    $tags = !empty($datos['tags']) ? json_encode($datos['tags'], JSON_UNESCAPED_UNICODE) : null;

    // Calcular tiempo de lectura
    $palabras = str_word_count(strip_tags($datos['contenido']));
    $tiempoLectura = max(1, ceil($palabras / 200));

    $stmt = $pdo->prepare("
        INSERT INTO blog_articulos
        (titulo, slug, contenido, opinion_editorial, tips, contenido_original,
         fuente_nombre, fuente_url, imagen_url, region, pais_origen,
         categoria, tags, estado, tiempo_lectura)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'borrador', ?)
    ");

    $stmt->execute([
        $datos['titulo'],
        $slug,
        $datos['contenido'],
        $datos['opinion_editorial'] ?? null,
        $tips,
        $datos['contenido_original'] ?? null,
        $datos['fuente_nombre'] ?? null,
        $datos['fuente_url'] ?? null,
        $datos['imagen_url'] ?? null,
        $datos['region'] ?? 'internacional',
        $datos['pais_origen'] ?? null,
        $datos['categoria'] ?? 'noticias',
        $tags,
        $tiempoLectura
    ]);

    return (int) $pdo->lastInsertId();
}

// ============================================
// FUNCIONES DE TRADUCCIONES
// ============================================

/**
 * Guarda una traducción de artículo
 */
function guardarTraduccion(int $articuloId, string $idioma, array $traduccion): bool {
    $pdo = getConnection();

    $tips = !empty($traduccion['tips']) ? json_encode($traduccion['tips'], JSON_UNESCAPED_UNICODE) : null;

    $stmt = $pdo->prepare("
        INSERT INTO blog_articulos_traducciones
        (articulo_id, idioma, titulo, contenido, opinion_editorial, tips)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            titulo = VALUES(titulo),
            contenido = VALUES(contenido),
            opinion_editorial = VALUES(opinion_editorial),
            tips = VALUES(tips),
            fecha_traduccion = CURRENT_TIMESTAMP
    ");

    return $stmt->execute([
        $articuloId,
        $idioma,
        $traduccion['titulo'] ?? '',
        $traduccion['contenido'] ?? '',
        $traduccion['opinion_editorial'] ?? null,
        $tips
    ]);
}

/**
 * Guarda todas las traducciones de un artículo
 */
function guardarTraducciones(int $articuloId, array $traducciones): int {
    $guardadas = 0;

    foreach ($traducciones as $idioma => $traduccion) {
        if ($traduccion !== null) {
            if (guardarTraduccion($articuloId, $idioma, $traduccion)) {
                $guardadas++;
            }
        }
    }

    return $guardadas;
}

/**
 * Obtiene las traducciones de un artículo
 */
function obtenerTraducciones(int $articuloId): array {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT idioma, titulo, contenido, opinion_editorial, tips
        FROM blog_articulos_traducciones
        WHERE articulo_id = ?
    ");
    $stmt->execute([$articuloId]);

    $traducciones = [];
    while ($row = $stmt->fetch()) {
        $row['tips'] = json_decode($row['tips'] ?? '[]', true) ?: [];
        $traducciones[$row['idioma']] = $row;
    }

    return $traducciones;
}

/**
 * Verifica si un artículo tiene traducciones
 */
function tieneTraduccion(int $articuloId, string $idioma): bool {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT 1 FROM blog_articulos_traducciones
        WHERE articulo_id = ? AND idioma = ?
    ");
    $stmt->execute([$articuloId, $idioma]);
    return $stmt->fetch() !== false;
}

/**
 * Obtiene todos los artículos con filtro opcional
 */
function obtenerArticulos(?string $estado = null, int $limite = 100, int $offset = 0): array {
    $pdo = getConnection();

    if ($estado) {
        $stmt = $pdo->prepare("SELECT * FROM blog_articulos WHERE estado = ? ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?");
        $stmt->execute([$estado, $limite, $offset]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM blog_articulos ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limite, $offset]);
    }

    return $stmt->fetchAll();
}

/**
 * Cuenta artículos por estado
 */
function contarArticulosPorEstado(?string $estado = null): int {
    $pdo = getConnection();

    if ($estado) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_articulos WHERE estado = ?");
        $stmt->execute([$estado]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_articulos");
    }

    return (int) $stmt->fetchColumn();
}

/**
 * Obtiene un artículo por ID
 */
function obtenerArticulo(int $id): ?array {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM blog_articulos WHERE id = ?");
    $stmt->execute([$id]);
    $articulo = $stmt->fetch();

    if ($articulo) {
        // Decodificar JSON fields
        $articulo['tips'] = json_decode($articulo['tips'] ?? '[]', true) ?: [];
        $articulo['tags'] = json_decode($articulo['tags'] ?? '[]', true) ?: [];
    }

    return $articulo ?: null;
}

/**
 * Obtiene un artículo por slug
 */
function obtenerArticuloPorSlug(string $slug): ?array {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM blog_articulos WHERE slug = ?");
    $stmt->execute([$slug]);
    $articulo = $stmt->fetch();

    if ($articulo) {
        $articulo['tips'] = json_decode($articulo['tips'] ?? '[]', true) ?: [];
        $articulo['tags'] = json_decode($articulo['tags'] ?? '[]', true) ?: [];
    }

    return $articulo ?: null;
}

/**
 * Cambia el estado de un artículo
 * Cuando se aprueba, se PROGRAMA automáticamente (no publica inmediato)
 *
 * @param int $id ID del artículo
 * @param string $estado Estado destino: 'publicado' (se programa), 'rechazado', 'borrador'
 * @param bool $saltearCriterio Si es true, no actualiza contador regional
 * @param bool $publicarAhora Si es true, publica inmediatamente sin programar
 */
function cambiarEstadoArticulo(int $id, string $estado, bool $saltearCriterio = false, bool $publicarAhora = false, ?string $fechaPersonalizada = null): bool {
    $pdo = getConnection();
    $articulo = obtenerArticulo($id);

    if (!$articulo) return false;

    // Descargar imagen al servidor local cuando se aprueba
    if ($estado === 'publicado') {
        actualizarImagenArticulo($id);
    }

    if ($estado === 'publicado') {
        // Actualizar contador regional
        if (!$saltearCriterio) {
            actualizarContadorRegional($articulo['region']);
        }

        if ($publicarAhora) {
            // Publicar inmediatamente (sin cola de programación)
            $stmt = $pdo->prepare("
                UPDATE blog_articulos
                SET estado = 'publicado', fecha_publicacion = NOW(), fecha_programada = NULL
                WHERE id = ?
            ");
            $stmt->execute([$id]);
        } else {
            // Usar fecha personalizada si se proporciona, sino calcular automáticamente
            $fechaProgramada = $fechaPersonalizada ?? calcularProximaFechaPublicacion(INTERVALO_PUBLICACION_HORAS ?? 2);

            $stmt = $pdo->prepare("
                UPDATE blog_articulos
                SET estado = 'programado', fecha_programada = ?
                WHERE id = ?
            ");
            $stmt->execute([$fechaProgramada, $id]);
        }

        // Exportar JSON (incluye solo los que ya cumplieron su fecha)
        exportarArticulosJSON();
    } elseif ($estado === 'programado') {
        // Programación con fecha personalizada desde el admin
        actualizarImagenArticulo($id);

        // Actualizar contador regional
        if (!$saltearCriterio) {
            actualizarContadorRegional($articulo['region']);
        }

        // Usar fecha personalizada proporcionada, sino calcular automáticamente
        $fechaProgramada = $fechaPersonalizada ?? calcularProximaFechaPublicacion(INTERVALO_PUBLICACION_HORAS ?? 2);

        $stmt = $pdo->prepare("
            UPDATE blog_articulos
            SET estado = 'programado', fecha_programada = ?
            WHERE id = ?
        ");
        $stmt->execute([$fechaProgramada, $id]);

        // Exportar JSON
        exportarArticulosJSON();
    } else {
        // Rechazado o borrador
        $stmt = $pdo->prepare("
            UPDATE blog_articulos
            SET estado = ?, fecha_publicacion = NULL, fecha_programada = NULL
            WHERE id = ?
        ");
        $stmt->execute([$estado, $id]);
    }

    return $stmt->rowCount() > 0;
}

/**
 * Actualiza el contenido de un artículo
 */
function actualizarArticulo(int $id, array $datos): bool {
    $pdo = getConnection();

    $tips = !empty($datos['tips']) ? json_encode($datos['tips'], JSON_UNESCAPED_UNICODE) : null;
    $tags = !empty($datos['tags']) ? json_encode($datos['tags'], JSON_UNESCAPED_UNICODE) : null;

    $stmt = $pdo->prepare("
        UPDATE blog_articulos
        SET titulo = ?, contenido = ?, opinion_editorial = ?, tips = ?, categoria = ?, tags = ?
        WHERE id = ?
    ");

    return $stmt->execute([
        $datos['titulo'],
        $datos['contenido'],
        $datos['opinion_editorial'] ?? null,
        $tips,
        $datos['categoria'] ?? 'noticias',
        $tags,
        $id
    ]);
}

// ============================================
// FUNCIONES DE CONTADOR REGIONAL
// ============================================

/**
 * Obtiene el contador regional actual
 */
function obtenerContadorRegional(): array {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM blog_contador_regional WHERE id = 1");
    $contador = $stmt->fetch();

    if (!$contador) {
        return [
            'contador_sudamerica' => 0,
            'contador_internacional' => 0,
            'ratio_objetivo' => RATIO_REGIONAL_OBJETIVO ?? 3.5,
            'ratio_actual' => 0
        ];
    }

    $contador['ratio_actual'] = $contador['contador_internacional'] > 0
        ? round($contador['contador_sudamerica'] / $contador['contador_internacional'], 1)
        : $contador['contador_sudamerica'];

    return $contador;
}

/**
 * Actualiza el contador regional
 */
function actualizarContadorRegional(string $region): void {
    $pdo = getConnection();

    if ($region === 'sudamerica') {
        $pdo->query("UPDATE blog_contador_regional SET contador_sudamerica = contador_sudamerica + 1 WHERE id = 1");
    } else {
        $pdo->query("UPDATE blog_contador_regional SET contador_internacional = contador_internacional + 1 WHERE id = 1");
    }
}

/**
 * Sugiere qué región debería ser la próxima publicación
 */
function sugerirRegion(): string {
    $contador = obtenerContadorRegional();
    $ratioActual = $contador['ratio_actual'];
    $ratioObjetivo = $contador['ratio_objetivo'];

    // Si el ratio actual es menor que el objetivo, sugerir sudamericana
    if ($ratioActual < $ratioObjetivo) {
        return 'sudamerica';
    }

    return 'internacional';
}

// ============================================
// FUNCIONES DE EXPORTACIÓN
// ============================================

/**
 * Exporta artículos publicados a JSON para el frontend
 * Incluye traducciones en múltiples idiomas
 */
function exportarArticulosJSON(): bool {
    $pdo = getConnection();

    // Obtener artículos publicados
    $stmt = $pdo->query("
        SELECT
            id, titulo, slug, contenido, opinion_editorial, tips,
            fuente_nombre, fuente_url, imagen_url,
            region, pais_origen, categoria, tags,
            fecha_publicacion, vistas, tiempo_lectura,
            reaccion_interesante, reaccion_encanta, reaccion_importante, reaccion_noconvence,
            (shares_whatsapp + shares_facebook + shares_twitter + shares_linkedin + shares_copy) as total_shares
        FROM blog_articulos
        WHERE estado = 'publicado'
          AND (fecha_programada IS NULL OR fecha_programada <= NOW())
        ORDER BY fecha_publicacion DESC
    ");

    $articulos = $stmt->fetchAll();

    // Procesar cada artículo
    foreach ($articulos as &$art) {
        $art['tips'] = json_decode($art['tips'] ?? '[]', true) ?: [];
        $art['tags'] = json_decode($art['tags'] ?? '[]', true) ?: [];
        $art['fecha_publicacion'] = date('c', strtotime($art['fecha_publicacion']));

        // Agregar traducciones
        $traducciones = obtenerTraducciones((int)$art['id']);
        if (!empty($traducciones)) {
            $art['traducciones'] = $traducciones;
        }
    }

    $json = json_encode([
        'generado' => date('c'),
        'total' => count($articulos),
        'idiomas_disponibles' => ['es', 'pt', 'en', 'fr', 'nl'],
        'articulos' => $articulos
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $rutaJSON = __DIR__ . '/../../data/articulos.json';

    // Crear directorio si no existe
    $dir = dirname($rutaJSON);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($rutaJSON, $json) !== false;
}

/**
 * Genera el RSS Feed
 */
function generarRSSFeed(): bool {
    $pdo = getConnection();

    $stmt = $pdo->query("
        SELECT id, titulo, slug, contenido, fecha_publicacion, imagen_url
        FROM blog_articulos
        WHERE estado = 'publicado'
        ORDER BY fecha_publicacion DESC
        LIMIT 20
    ");

    $articulos = $stmt->fetchAll();

    $blogUrl = BLOG_URL ?? 'https://terrapp.verumax.com/blog/';

    $rss = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $rss .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    $rss .= '<channel>' . "\n";
    $rss .= '  <title>TERRApp Blog - Agricultura Urbana</title>' . "\n";
    $rss .= '  <link>' . htmlspecialchars($blogUrl) . '</link>' . "\n";
    $rss .= '  <description>Noticias y tips sobre huertos urbanos y agricultura en ciudades para Sudamérica</description>' . "\n";
    $rss .= '  <language>es</language>' . "\n";
    $rss .= '  <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    $rss .= '  <atom:link href="' . htmlspecialchars($blogUrl) . 'feed.xml" rel="self" type="application/rss+xml"/>' . "\n";

    foreach ($articulos as $art) {
        $link = $blogUrl . 'scriptum.php?titulus=' . urlencode($art['slug']);
        $descripcion = mb_substr(strip_tags($art['contenido']), 0, 300) . '...';

        $rss .= '  <item>' . "\n";
        $rss .= '    <title>' . htmlspecialchars($art['titulo']) . '</title>' . "\n";
        $rss .= '    <link>' . htmlspecialchars($link) . '</link>' . "\n";
        $rss .= '    <guid isPermaLink="true">' . htmlspecialchars($link) . '</guid>' . "\n";
        $rss .= '    <pubDate>' . date('r', strtotime($art['fecha_publicacion'])) . '</pubDate>' . "\n";
        $rss .= '    <description>' . htmlspecialchars($descripcion) . '</description>' . "\n";

        if (!empty($art['imagen_url'])) {
            $rss .= '    <enclosure url="' . htmlspecialchars($art['imagen_url']) . '" type="image/jpeg"/>' . "\n";
        }

        $rss .= '  </item>' . "\n";
    }

    $rss .= '</channel>' . "\n";
    $rss .= '</rss>';

    $rutaRSS = __DIR__ . '/../../feed.xml';

    return file_put_contents($rutaRSS, $rss) !== false;
}

// ============================================
// FUNCIONES DE MÉTRICAS
// ============================================

/**
 * Incrementa el contador de vistas (inflado) y vistas únicas (real por IP)
 */
function registrarVista(int $articuloId): array {
    $pdo = getConnection();
    $resultado = ['vista_total' => true, 'vista_unica' => false];

    // Siempre incrementar el contador de vistas totales (inflado)
    $stmt = $pdo->prepare("UPDATE blog_articulos SET vistas = vistas + 1 WHERE id = ?");
    $stmt->execute([$articuloId]);

    // Intentar registrar vista única por IP
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash = hash('sha256', $ip . 'terrapp_salt_2026');

    try {
        // Insertar en tabla de vistas únicas (fallará si ya existe por el UNIQUE KEY)
        $stmt = $pdo->prepare("INSERT INTO blog_vistas_unicas (articulo_id, ip_hash) VALUES (?, ?)");
        $stmt->execute([$articuloId, $ipHash]);

        // Si llegamos aquí, es una vista única nueva
        $stmt = $pdo->prepare("UPDATE blog_articulos SET vistas_unicas = vistas_unicas + 1 WHERE id = ?");
        $stmt->execute([$articuloId]);
        $resultado['vista_unica'] = true;
    } catch (PDOException $e) {
        // Duplicate entry = ya visitó antes, no es vista única
        // Solo registramos la vista total (ya hecho arriba)
    }

    return $resultado;
}

/**
 * Registra una reacción
 */
function registrarReaccion(int $articuloId, string $tipo): bool {
    $pdo = getConnection();

    $columnas = [
        'interesante' => 'reaccion_interesante',
        'encanta' => 'reaccion_encanta',
        'importante' => 'reaccion_importante',
        'noconvence' => 'reaccion_noconvence'
    ];

    if (!isset($columnas[$tipo])) {
        return false;
    }

    $columna = $columnas[$tipo];
    $stmt = $pdo->prepare("UPDATE blog_articulos SET {$columna} = {$columna} + 1 WHERE id = ?");
    return $stmt->execute([$articuloId]);
}

/**
 * Registra un share
 */
function registrarShare(int $articuloId, string $red): bool {
    $pdo = getConnection();

    $columnas = [
        'whatsapp' => 'shares_whatsapp',
        'facebook' => 'shares_facebook',
        'twitter' => 'shares_twitter',
        'linkedin' => 'shares_linkedin',
        'copy' => 'shares_copy'
    ];

    if (!isset($columnas[$red])) {
        return false;
    }

    $columna = $columnas[$red];
    $stmt = $pdo->prepare("UPDATE blog_articulos SET {$columna} = {$columna} + 1 WHERE id = ?");
    return $stmt->execute([$articuloId]);
}

// ============================================
// FUNCIONES DE TOKENS PARA ACCIONES EMAIL
// ============================================

/**
 * Genera token para acción desde email
 */
function generarTokenAccion(int $articuloId, string $accion): string {
    $pdo = getConnection();
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO blog_tokens_accion (token, articulo_id, accion) VALUES (?, ?, ?)");
    $stmt->execute([$token, $articuloId, $accion]);

    return $token;
}

/**
 * Valida y ejecuta acción desde token
 */
function ejecutarAccionToken(string $token): array {
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT * FROM blog_tokens_accion WHERE token = ? AND usado = 0");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        return ['success' => false, 'error' => 'Token inválido o ya usado'];
    }

    // Marcar como usado
    $stmt = $pdo->prepare("UPDATE blog_tokens_accion SET usado = 1, fecha_uso = NOW() WHERE id = ?");
    $stmt->execute([$tokenData['id']]);

    // Ejecutar acción
    $articuloId = $tokenData['articulo_id'];
    $accion = $tokenData['accion'];

    switch ($accion) {
        case 'aprobar':
            cambiarEstadoArticulo($articuloId, 'publicado');
            return ['success' => true, 'accion' => 'Artículo aprobado y publicado'];

        case 'rechazar':
            cambiarEstadoArticulo($articuloId, 'rechazado');
            return ['success' => true, 'accion' => 'Artículo rechazado'];

        case 'saltear':
            cambiarEstadoArticulo($articuloId, 'publicado', true);
            return ['success' => true, 'accion' => 'Artículo publicado (criterio regional omitido)'];

        default:
            return ['success' => false, 'error' => 'Acción desconocida'];
    }
}

// ============================================
// FUNCIONES DE ESTADÍSTICAS
// ============================================

/**
 * Obtiene estadísticas del blog
 */
function obtenerEstadisticas(): array {
    $pdo = getConnection();

    $stmt = $pdo->query("SELECT * FROM v_blog_stats");
    return $stmt->fetch() ?: [];
}

/**
 * Obtiene categorías con conteo
 */
function obtenerCategorias(): array {
    $pdo = getConnection();

    $stmt = $pdo->query("
        SELECT
            c.*,
            COUNT(a.id) as total_articulos
        FROM blog_categorias c
        LEFT JOIN blog_articulos a ON a.categoria = c.slug AND a.estado = 'publicado'
        WHERE c.activo = 1
        GROUP BY c.id
        ORDER BY c.orden
    ");

    return $stmt->fetchAll();
}

// ============================================
// SITIOS PREFERIDOS
// ============================================

/**
 * Obtiene todos los sitios preferidos
 */
function obtenerSitiosPreferidos(bool $soloActivos = false): array {
    $pdo = getConnection();

    $sql = "SELECT * FROM blog_sitios_preferidos";
    if ($soloActivos) {
        $sql .= " WHERE activo = 1";
    }
    $sql .= " ORDER BY prioridad DESC, nombre ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Agrega un sitio preferido
 */
function agregarSitioPreferido(string $dominio, string $nombre, int $prioridad = 1): bool {
    $pdo = getConnection();

    try {
        $stmt = $pdo->prepare("INSERT INTO blog_sitios_preferidos (dominio, nombre, prioridad) VALUES (?, ?, ?)");
        return $stmt->execute([$dominio, $nombre, $prioridad]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Elimina un sitio preferido
 */
function eliminarSitioPreferido(int $id): bool {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM blog_sitios_preferidos WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Activa/desactiva un sitio preferido
 */
function toggleSitioPreferido(int $id): bool {
    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE blog_sitios_preferidos SET activo = NOT activo WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Elimina un artículo y sus datos relacionados
 */
function eliminarArticulo(int $id): bool {
    $pdo = getConnection();

    try {
        $pdo->beginTransaction();

        // Eliminar traducciones
        $stmt = $pdo->prepare("DELETE FROM blog_articulos_traducciones WHERE articulo_id = ?");
        $stmt->execute([$id]);

        // Eliminar reacciones
        $stmt = $pdo->prepare("DELETE FROM blog_reacciones WHERE articulo_id = ?");
        $stmt->execute([$id]);

        // Eliminar shares
        $stmt = $pdo->prepare("DELETE FROM blog_articulo_shares WHERE articulo_id = ?");
        $stmt->execute([$id]);

        // Eliminar web stories asociadas
        $stmt = $pdo->prepare("DELETE FROM blog_web_stories WHERE articulo_id = ?");
        $stmt->execute([$id]);

        // Eliminar el artículo
        $stmt = $pdo->prepare("DELETE FROM blog_articulos WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        // Regenerar JSON público
        exportarArticulosJSON();

        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Error eliminando artículo {$id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Despublica un artículo (lo vuelve a borrador)
 */
function despublicarArticulo(int $id): bool {
    $pdo = getConnection();

    $stmt = $pdo->prepare("
        UPDATE blog_articulos
        SET estado = 'borrador',
            fecha_publicacion = NULL,
            fecha_programada = NULL
        WHERE id = ?
    ");

    $result = $stmt->execute([$id]);

    if ($result) {
        // Regenerar JSON público
        exportarArticulosJSON();
    }

    return $result;
}

// ============================================
// FUNCIONES DE PUBLICACIÓN PROGRAMADA
// ============================================

/**
 * Calcula la próxima fecha de publicación disponible
 * Busca la última fecha (publicada o programada) y suma el intervalo
 *
 * @param int $intervaloHoras Horas entre publicaciones (default 2)
 * @return string Fecha ISO 8601 de próxima publicación
 */
function calcularProximaFechaPublicacion(int $intervaloHoras = 2): string {
    $pdo = getConnection();

    // Buscar la última fecha de publicación o programada
    $stmt = $pdo->query("
        SELECT
            GREATEST(
                COALESCE(MAX(fecha_publicacion), '1970-01-01'),
                COALESCE(MAX(fecha_programada), '1970-01-01')
            ) as ultima_fecha
        FROM blog_articulos
        WHERE estado IN ('publicado', 'programado')
    ");

    $resultado = $stmt->fetch();
    $ultimaFecha = $resultado['ultima_fecha'] ?? null;

    // Si no hay artículos previos o la fecha es muy antigua, usar ahora
    if (!$ultimaFecha || strtotime($ultimaFecha) < strtotime('-1 year')) {
        return date('Y-m-d H:i:s');
    }

    // Calcular próxima fecha sumando el intervalo
    $proximaFecha = date('Y-m-d H:i:s', strtotime($ultimaFecha . " + {$intervaloHoras} hours"));

    // Si la próxima fecha ya pasó, usar ahora
    if (strtotime($proximaFecha) < time()) {
        return date('Y-m-d H:i:s');
    }

    return $proximaFecha;
}

/**
 * Obtiene artículos programados pendientes de publicar
 */
function obtenerArticulosProgramados(): array {
    $pdo = getConnection();
    $stmt = $pdo->query("
        SELECT * FROM blog_articulos
        WHERE estado = 'programado'
        ORDER BY fecha_programada ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Publica artículos programados cuya fecha ya pasó
 * (para llamar desde cron o al cargar el admin)
 */
function publicarProgramadosVencidos(): int {
    $pdo = getConnection();

    // Actualizar artículos cuya fecha programada ya pasó
    $stmt = $pdo->prepare("
        UPDATE blog_articulos
        SET estado = 'publicado', fecha_publicacion = fecha_programada
        WHERE estado = 'programado'
          AND fecha_programada <= NOW()
    ");
    $stmt->execute();

    $actualizados = $stmt->rowCount();

    // Si hubo cambios, regenerar el JSON
    if ($actualizados > 0) {
        exportarArticulosJSON();
    }

    return $actualizados;
}
