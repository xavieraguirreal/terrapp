<?php
/**
 * TERRApp Blog - Generador de Web Stories (AMP)
 * Crea historias visuales tipo Instagram para Google Discover
 * Las stories se publican en el hub centralizado de VERUMax: stories.verumax.com
 */

class WebStoryGenerator {

    private string $storiesBasePath;    // Ruta al hub de stories (stories.verumax.com)
    private string $appStoriesPath;     // Ruta para stories de esta app (stories.verumax.com/terrapp/)
    private string $storiesBaseUrl;     // URL del hub de stories
    private string $blogUrl;            // URL del blog de TERRApp
    private string $appName;            // Nombre de la app (terrapp)
    private PDO $pdo;

    public function __construct() {
        // Configuración del hub centralizado de stories
        // En el servidor: terrapp/blog/admin/includes/ -> ../../../../stories/
        $this->storiesBasePath = defined('STORIES_HUB_PATH')
            ? STORIES_HUB_PATH
            : realpath(__DIR__ . '/../../../../') . '/stories/';

        $this->storiesBaseUrl = defined('STORIES_HUB_URL')
            ? STORIES_HUB_URL
            : 'https://stories.verumax.com/';

        $this->blogUrl = defined('BLOG_URL')
            ? BLOG_URL
            : 'https://terrapp.verumax.com/blog/';

        $this->appName = 'terrapp';
        $this->appStoriesPath = $this->storiesBasePath . $this->appName . '/';

        $this->pdo = getConnection();

        // Crear carpetas si no existen
        if (!is_dir($this->storiesBasePath)) {
            @mkdir($this->storiesBasePath, 0755, true);
        }
        if (!is_dir($this->appStoriesPath)) {
            @mkdir($this->appStoriesPath, 0755, true);
        }
    }

    /**
     * Genera Web Story a partir de un artículo ID
     * @return int|null ID de la story creada
     */
    public function generarDesdeArticulo(int $articuloId): ?int {
        // Obtener artículo
        $stmt = $this->pdo->prepare("SELECT * FROM blog_articulos WHERE id = ?");
        $stmt->execute([$articuloId]);
        $articulo = $stmt->fetch();

        if (!$articulo) {
            throw new Exception("Artículo no encontrado");
        }

        $tips = is_array($articulo['tips']) ? $articulo['tips'] : json_decode($articulo['tips'] ?? '[]', true);

        if (empty($tips)) {
            throw new Exception("El artículo no tiene tips para generar una story");
        }

        // Generar slug único
        $slug = $this->generarSlug($articulo['titulo']);

        // Generar slides JSON
        $slides = $this->generarSlidesData($articulo, $tips);

        // Verificar si ya existe
        $stmt = $this->pdo->prepare("SELECT id FROM blog_web_stories WHERE articulo_id = ?");
        $stmt->execute([$articuloId]);
        $existente = $stmt->fetch();

        if ($existente) {
            // Actualizar existente
            $stmt = $this->pdo->prepare("
                UPDATE blog_web_stories
                SET titulo = ?, slides = ?, poster_url = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $articulo['titulo'],
                json_encode($slides, JSON_UNESCAPED_UNICODE),
                $articulo['imagen_url'],
                $existente['id']
            ]);
            $storyId = $existente['id'];
            $slug = $this->obtenerSlugPorId($storyId);
        } else {
            // Crear nueva
            $stmt = $this->pdo->prepare("
                INSERT INTO blog_web_stories (articulo_id, titulo, slug, poster_url, slides)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $articuloId,
                $articulo['titulo'],
                $slug,
                $articulo['imagen_url'],
                json_encode($slides, JSON_UNESCAPED_UNICODE)
            ]);
            $storyId = $this->pdo->lastInsertId();
        }

        // Generar archivo HTML en el hub centralizado
        $this->generarArchivoHTML($storyId, $articulo, $slides, $slug);

        return (int)$storyId;
    }

    /**
     * Genera datos de slides
     */
    private function generarSlidesData(array $articulo, array $tips): array {
        $slides = [];

        // Slide portada
        $slides[] = [
            'tipo' => 'portada',
            'titulo' => $articulo['titulo'],
            'imagen' => $articulo['imagen_url'] ?: ''
        ];

        // Slides de tips (máximo 8)
        $tipsSlice = array_slice($tips, 0, 8);
        foreach ($tipsSlice as $i => $tip) {
            $slides[] = [
                'tipo' => 'tip',
                'numero' => $i + 1,
                'texto' => $tip
            ];
        }

        // Slide CTA
        $slides[] = [
            'tipo' => 'cta',
            'slug' => $articulo['slug']
        ];

        return $slides;
    }

    /**
     * Genera archivo HTML de la story en el hub centralizado
     */
    private function generarArchivoHTML(int $storyId, array $articulo, array $slides, string $slug): void {
        $html = $this->renderHTML($articulo, $slides, $slug);
        $filename = "story-{$slug}.html";

        // Guardar en la carpeta de la app dentro del hub
        $result = @file_put_contents($this->appStoriesPath . $filename, $html);

        if ($result === false) {
            // Fallback: intentar en la carpeta local del blog
            $localPath = __DIR__ . '/../../stories/';
            if (!is_dir($localPath)) {
                mkdir($localPath, 0755, true);
            }
            file_put_contents($localPath . $filename, $html);
            error_log("WebStoryGenerator: No se pudo escribir en hub centralizado, usando fallback local");
        }
    }

    /**
     * Renderiza HTML completo de la story
     */
    private function renderHTML(array $articulo, array $slides, string $slug): string {
        $titulo = $this->esc($articulo['titulo']);
        $imagen = $articulo['imagen_url'] ?: '';
        $slidesHtml = '';

        foreach ($slides as $i => $slide) {
            $pageId = 'page-' . ($i + 1);
            if ($slide['tipo'] === 'portada') {
                $slidesHtml .= $this->slidePortada($pageId, $slide);
            } elseif ($slide['tipo'] === 'tip') {
                $slidesHtml .= $this->slideTip($pageId, $slide);
            } elseif ($slide['tipo'] === 'cta') {
                $slidesHtml .= $this->slideCTA($pageId, $slide);
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html amp lang="es">
<head>
  <meta charset="utf-8">
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <script async custom-element="amp-story" src="https://cdn.ampproject.org/v0/amp-story-1.0.js"></script>
  <title>{$titulo} - TERRApp</title>
  <link rel="canonical" href="{$this->blogUrl}scriptum.php?titulus={$articulo['slug']}">
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
  <link rel="icon" type="image/png" href="https://terrapp.verumax.com/landing/assets/images/logo_terrapp_icono.png">
  <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style>
  <noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
  <style amp-custom>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; }
    .gradient { background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%); }
    .bottom { justify-content: flex-end; padding-bottom: 60px; }
    .center { justify-content: center; align-items: center; text-align: center; padding: 20px; }
    .story-title { color: white; font-size: 28px; font-weight: bold; line-height: 1.3; margin: 0; padding: 0 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
    .story-pub { color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 10px; }
    .tip-num { font-size: 64px; font-weight: bold; color: #4ade80; margin-bottom: 10px; }
    .tip-text { color: white; font-size: 22px; line-height: 1.5; padding: 0 15px; }
    .cta-icon { font-size: 64px; margin-bottom: 20px; }
    .cta-title { color: white; font-size: 28px; font-weight: bold; margin-bottom: 15px; }
    .cta-text { color: rgba(255,255,255,0.9); font-size: 18px; margin-bottom: 25px; }
    .cta-btn { display: inline-block; padding: 15px 35px; background: #2d7553; color: white; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; }
    .bg-green { background: linear-gradient(135deg, #2d7553 0%, #3d9268 100%); }
    .bg-dark { background: #1a1a1a; }
  </style>
</head>
<body>
  <amp-story standalone
    title="{$titulo}"
    publisher="TERRApp"
    publisher-logo-src="https://terrapp.verumax.com/landing/assets/images/logo_terrapp_icono.png"
    poster-portrait-src="{$imagen}">
{$slidesHtml}
  </amp-story>
</body>
</html>
HTML;
    }

    private function slidePortada(string $id, array $slide): string {
        $titulo = $this->esc($slide['titulo']);
        $imgHtml = !empty($slide['imagen'])
            ? '<amp-img src="' . $this->esc($slide['imagen']) . '" layout="fill" object-fit="cover"></amp-img>'
            : '';

        return <<<HTML

    <amp-story-page id="{$id}">
      <amp-story-grid-layer template="fill">{$imgHtml}</amp-story-grid-layer>
      <amp-story-grid-layer template="fill" class="gradient"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="bottom">
        <h1 class="story-title">{$titulo}</h1>
        <p class="story-pub">TERRApp Blog</p>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    private function slideTip(string $id, array $slide): string {
        $num = (int)$slide['numero'];
        $texto = $this->esc($slide['texto']);

        return <<<HTML

    <amp-story-page id="{$id}">
      <amp-story-grid-layer template="fill" class="bg-green"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="center">
        <div class="tip-num">#{$num}</div>
        <p class="tip-text">{$texto}</p>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    private function slideCTA(string $id, array $slide): string {
        $url = $this->blogUrl . 'scriptum.php?titulus=' . urlencode($slide['slug']);

        return <<<HTML

    <amp-story-page id="{$id}">
      <amp-story-grid-layer template="fill" class="bg-dark"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="center">
        <div class="cta-icon">🌱</div>
        <h2 class="cta-title">¿Te gustó?</h2>
        <p class="cta-text">Lee el artículo completo en nuestro blog</p>
        <a href="{$url}" class="cta-btn">Leer más</a>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    /**
     * Publica una story
     */
    public function publicar(int $storyId): bool {
        $stmt = $this->pdo->prepare("UPDATE blog_web_stories SET estado = 'publicado', fecha_publicacion = NOW() WHERE id = ?");
        $result = $stmt->execute([$storyId]);
        if ($result) {
            $this->exportarJSON();
        }
        return $result;
    }

    /**
     * Obtiene todas las stories
     */
    public function obtenerStories(?string $estado = null): array {
        $sql = "SELECT s.*, a.titulo as articulo_titulo, a.imagen_url as articulo_imagen
                FROM blog_web_stories s
                JOIN blog_articulos a ON s.articulo_id = a.id";

        if ($estado) {
            $sql .= " WHERE s.estado = ?";
            $stmt = $this->pdo->prepare($sql . " ORDER BY s.fecha_creacion DESC");
            $stmt->execute([$estado]);
        } else {
            $stmt = $this->pdo->query($sql . " ORDER BY s.fecha_creacion DESC");
        }

        return $stmt->fetchAll();
    }

    /**
     * Obtiene artículos que pueden generar stories (tienen tips y no tienen story)
     */
    public function obtenerArticulosSinStory(): array {
        $stmt = $this->pdo->query("
            SELECT a.id, a.titulo, a.tips, a.imagen_url, a.fecha_publicacion
            FROM blog_articulos a
            LEFT JOIN blog_web_stories s ON a.id = s.articulo_id
            WHERE a.estado = 'publicado'
              AND a.tips IS NOT NULL
              AND a.tips != '[]'
              AND s.id IS NULL
            ORDER BY a.fecha_publicacion DESC
            LIMIT 20
        ");
        return $stmt->fetchAll();
    }

    /**
     * Elimina una story
     */
    public function eliminar(int $storyId): bool {
        $stmt = $this->pdo->prepare("SELECT slug FROM blog_web_stories WHERE id = ?");
        $stmt->execute([$storyId]);
        $story = $stmt->fetch();

        if ($story) {
            // Eliminar del hub centralizado
            $file = $this->appStoriesPath . "story-{$story['slug']}.html";
            if (file_exists($file)) {
                @unlink($file);
            }
            // Fallback: eliminar de carpeta local si existe
            $localFile = __DIR__ . "/../../stories/story-{$story['slug']}.html";
            if (file_exists($localFile)) {
                @unlink($localFile);
            }
        }

        $stmt = $this->pdo->prepare("DELETE FROM blog_web_stories WHERE id = ?");
        $result = $stmt->execute([$storyId]);

        if ($result) {
            $this->exportarJSON();
        }

        return $result;
    }

    /**
     * Exporta stories publicadas al JSON del hub centralizado
     * Incluye el campo 'app' para identificar de qué aplicación viene cada story
     */
    public function exportarJSON(): void {
        $stories = $this->obtenerStories('publicado');

        // Datos de stories de TERRApp
        $terrappStories = array_map(function($s) {
            return [
                'id' => (int)$s['id'],
                'app' => $this->appName,
                'titulo' => $s['titulo'],
                'slug' => $s['slug'],
                'poster' => $s['poster_url'] ?: $s['articulo_imagen'],
                'url' => $this->storiesBaseUrl . $this->appName . '/story-' . $s['slug'] . '.html',
                'fecha' => $s['fecha_publicacion'],
                'vistas' => (int)$s['vistas']
            ];
        }, $stories);

        // Intentar cargar stories existentes de otras apps del hub
        $hubJsonPath = $this->storiesBasePath . 'stories.json';
        $allStories = [];

        if (file_exists($hubJsonPath)) {
            $existingData = @json_decode(file_get_contents($hubJsonPath), true);
            if (is_array($existingData)) {
                // Filtrar stories que NO son de esta app
                $allStories = array_filter($existingData, function($s) {
                    return ($s['app'] ?? '') !== $this->appName;
                });
            }
        }

        // Combinar con las stories de esta app
        $allStories = array_merge(array_values($allStories), $terrappStories);

        // Ordenar por fecha (más recientes primero)
        usort($allStories, function($a, $b) {
            return strtotime($b['fecha'] ?? '1970-01-01') - strtotime($a['fecha'] ?? '1970-01-01');
        });

        // Guardar en el hub centralizado
        $result = @file_put_contents(
            $hubJsonPath,
            json_encode($allStories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Fallback: también guardar localmente
        if ($result === false) {
            $localJsonPath = __DIR__ . '/../../stories/stories.json';
            file_put_contents(
                $localJsonPath,
                json_encode($terrappStories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            error_log("WebStoryGenerator: No se pudo escribir JSON en hub, usando fallback local");
        }
    }

    /**
     * Genera slug único
     */
    private function generarSlug(string $titulo): string {
        $slug = mb_strtolower($titulo);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = mb_substr($slug, 0, 50);

        $base = $slug;
        $i = 1;
        while ($this->slugExiste($slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function slugExiste(string $slug): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM blog_web_stories WHERE slug = ?");
        $stmt->execute([$slug]);
        return (bool)$stmt->fetch();
    }

    private function obtenerSlugPorId(int $id): string {
        $stmt = $this->pdo->prepare("SELECT slug FROM blog_web_stories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: '';
    }

    private function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
