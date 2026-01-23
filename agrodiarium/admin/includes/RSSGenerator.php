<?php
/**
 * TERRApp Blog - Generador de RSS Feed
 */

class RSSGenerator {

    private string $blogUrl;
    private string $outputPath;

    public function __construct() {
        $this->blogUrl = BLOG_URL ?? 'https://terrapp.verumax.com/agrodiarium/';
        $this->outputPath = __DIR__ . '/../../feed.xml';
    }

    /**
     * Genera el RSS Feed con los artículos publicados
     */
    public function generar(array $articulos, int $limite = 20): bool {
        $items = array_slice($articulos, 0, $limite);

        $rss = $this->generarXML($items);

        return file_put_contents($this->outputPath, $rss) !== false;
    }

    /**
     * Genera el XML del RSS
     */
    private function generarXML(array $articulos): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= '<channel>' . "\n";

        // Información del canal
        $xml .= '  <title>TERRApp Blog - Agricultura Urbana</title>' . "\n";
        $xml .= '  <link>' . $this->escape($this->blogUrl) . '</link>' . "\n";
        $xml .= '  <description>Noticias, tips y consejos sobre huertos urbanos y agricultura en ciudades para Sudamérica</description>' . "\n";
        $xml .= '  <language>es</language>' . "\n";
        $xml .= '  <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
        $xml .= '  <atom:link href="' . $this->escape($this->blogUrl . 'feed.xml') . '" rel="self" type="application/rss+xml"/>' . "\n";
        $xml .= '  <image>' . "\n";
        $xml .= '    <url>' . $this->escape($this->blogUrl . '../landing/assets/images/logo_terrapp_icono.png') . '</url>' . "\n";
        $xml .= '    <title>TERRApp Blog</title>' . "\n";
        $xml .= '    <link>' . $this->escape($this->blogUrl) . '</link>' . "\n";
        $xml .= '  </image>' . "\n";

        // Items
        foreach ($articulos as $art) {
            $xml .= $this->generarItem($art);
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }

    /**
     * Genera un item del RSS
     */
    private function generarItem(array $art): string {
        $link = $this->blogUrl . 'scriptum.php?titulus=' . urlencode($art['slug']);
        $fecha = isset($art['fecha_publicacion'])
            ? date('r', strtotime($art['fecha_publicacion']))
            : date('r');

        $descripcion = strip_tags($art['contenido'] ?? '');
        $descripcion = mb_substr($descripcion, 0, 500) . '...';

        $contenido = $art['contenido'] ?? '';
        if (!empty($art['opinion_editorial'])) {
            $contenido .= "\n\n🌱 Opinión Editorial TERRApp:\n" . $art['opinion_editorial'];
        }

        $item = '  <item>' . "\n";
        $item .= '    <title>' . $this->escape($art['titulo']) . '</title>' . "\n";
        $item .= '    <link>' . $this->escape($link) . '</link>' . "\n";
        $item .= '    <guid isPermaLink="true">' . $this->escape($link) . '</guid>' . "\n";
        $item .= '    <pubDate>' . $fecha . '</pubDate>' . "\n";
        $item .= '    <description>' . $this->escape($descripcion) . '</description>' . "\n";
        $item .= '    <content:encoded><![CDATA[' . nl2br(htmlspecialchars($contenido)) . ']]></content:encoded>' . "\n";

        // Categoría
        if (!empty($art['categoria'])) {
            $item .= '    <category>' . $this->escape($art['categoria']) . '</category>' . "\n";
        }

        // Imagen
        if (!empty($art['imagen_url'])) {
            $item .= '    <enclosure url="' . $this->escape($art['imagen_url']) . '" type="image/jpeg"/>' . "\n";
        }

        // Fuente
        if (!empty($art['fuente_url'])) {
            $item .= '    <source url="' . $this->escape($art['fuente_url']) . '">' . $this->escape($art['fuente_nombre'] ?? 'Fuente externa') . '</source>' . "\n";
        }

        $item .= '  </item>' . "\n";

        return $item;
    }

    /**
     * Escapa caracteres especiales para XML
     */
    private function escape(string $text): string {
        return htmlspecialchars($text, ENT_XML1, 'UTF-8');
    }
}
