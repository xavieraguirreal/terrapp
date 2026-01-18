<?php
/**
 * TERRApp Blog - Generador de Web Stories (AMP)
 */

class WebStoryGenerator {

    private string $storiesPath;
    private string $blogUrl;

    public function __construct() {
        $this->storiesPath = __DIR__ . '/../../stories/';
        $this->blogUrl = BLOG_URL ?? 'https://terrapp.verumax.com/blog/';

        if (!is_dir($this->storiesPath)) {
            mkdir($this->storiesPath, 0755, true);
        }
    }

    /**
     * Genera Web Story a partir de un artículo con tips
     */
    public function generarStory(array $articulo): ?string {
        $tips = is_array($articulo['tips']) ? $articulo['tips'] : json_decode($articulo['tips'] ?? '[]', true);

        // Solo generar si hay tips
        if (empty($tips)) {
            return null;
        }

        $storyId = $articulo['slug'];
        $storyFile = $this->storiesPath . "story-{$storyId}.html";

        // Generar slides
        $slides = $this->generarSlides($articulo, $tips);

        $html = $this->generarHTML($articulo, $slides);

        if (file_put_contents($storyFile, $html)) {
            return "stories/story-{$storyId}.html";
        }

        return null;
    }

    /**
     * Genera los slides de la story
     */
    private function generarSlides(array $articulo, array $tips): string {
        $slides = '';

        // Slide de portada
        $imagen = $articulo['imagen_url'] ?? '';
        $slides .= $this->slidePortada($articulo['titulo'], $imagen);

        // Slide de intro
        $slides .= $this->slideTexto(
            'Sobre este artículo',
            mb_substr($articulo['contenido'], 0, 200) . '...',
            '#2d7553'
        );

        // Slides de tips
        foreach ($tips as $i => $tip) {
            $numero = $i + 1;
            $slides .= $this->slideTip("Tip #{$numero}", $tip);
        }

        // Slide final con CTA
        $slides .= $this->slideCTA($articulo['slug']);

        return $slides;
    }

    private function slidePortada(string $titulo, string $imagen): string {
        $imagenHtml = $imagen
            ? "<amp-img src=\"{$imagen}\" layout=\"fill\" object-fit=\"cover\"></amp-img>"
            : '';

        return <<<HTML
    <amp-story-page id="cover">
      <amp-story-grid-layer template="fill">
        {$imagenHtml}
      </amp-story-grid-layer>
      <amp-story-grid-layer template="fill" class="bottom-gradient"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="bottom">
        <h1 class="story-title">{$this->escapeHtml($titulo)}</h1>
        <p class="story-publisher">TERRApp Blog</p>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    private function slideTexto(string $titulo, string $texto, string $color): string {
        return <<<HTML
    <amp-story-page id="intro">
      <amp-story-grid-layer template="fill" style="background: {$color};"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="center">
        <h2 class="slide-title">{$this->escapeHtml($titulo)}</h2>
        <p class="slide-text">{$this->escapeHtml($texto)}</p>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    private function slideTip(string $titulo, string $tip): string {
        $id = 'tip-' . md5($tip);
        return <<<HTML
    <amp-story-page id="{$id}">
      <amp-story-grid-layer template="fill" style="background: linear-gradient(135deg, #558b2f 0%, #2d7553 100%);"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="center">
        <div class="tip-icon">💡</div>
        <h2 class="tip-title">{$this->escapeHtml($titulo)}</h2>
        <p class="tip-text">{$this->escapeHtml($tip)}</p>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    private function slideCTA(string $slug): string {
        $url = $this->blogUrl . "scriptum.php?titulus={$slug}";
        return <<<HTML
    <amp-story-page id="cta">
      <amp-story-grid-layer template="fill" style="background: #1a1a1a;"></amp-story-grid-layer>
      <amp-story-grid-layer template="vertical" class="center">
        <div class="cta-icon">🌱</div>
        <h2 class="cta-title">¿Te gustó?</h2>
        <p class="cta-text">Lee el artículo completo en nuestro blog</p>
        <a href="{$url}" class="cta-button">Leer más</a>
      </amp-story-grid-layer>
    </amp-story-page>
HTML;
    }

    /**
     * Genera el HTML completo de la Web Story
     */
    private function generarHTML(array $articulo, string $slides): string {
        $titulo = $this->escapeHtml($articulo['titulo']);
        $imagen = $articulo['imagen_url'] ?? '';

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
  <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
  <style amp-custom>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; }

    .bottom-gradient {
      background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
    }

    .bottom { justify-content: flex-end; padding-bottom: 60px; }
    .center { justify-content: center; align-items: center; text-align: center; padding: 20px; }

    .story-title {
      color: white;
      font-size: 28px;
      font-weight: bold;
      line-height: 1.3;
      margin: 0;
      padding: 0 20px;
    }

    .story-publisher {
      color: rgba(255,255,255,0.8);
      font-size: 14px;
      margin-top: 10px;
    }

    .slide-title, .tip-title, .cta-title {
      color: white;
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 20px;
    }

    .slide-text, .tip-text, .cta-text {
      color: rgba(255,255,255,0.9);
      font-size: 18px;
      line-height: 1.6;
    }

    .tip-icon, .cta-icon {
      font-size: 48px;
      margin-bottom: 20px;
    }

    .cta-button {
      display: inline-block;
      margin-top: 30px;
      padding: 15px 30px;
      background: #2d7553;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <amp-story standalone
    title="{$titulo}"
    publisher="TERRApp"
    publisher-logo-src="{$this->blogUrl}../landing/assets/images/logo_terrapp_icono.png"
    poster-portrait-src="{$imagen}">

{$slides}

  </amp-story>
</body>
</html>
HTML;
    }

    private function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
