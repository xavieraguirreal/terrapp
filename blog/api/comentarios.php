<?php
/**
 * TERRApp Blog - API de Comentarios
 *
 * GET  /api/comentarios.php?articulo_id=X     - Obtener comentarios
 * POST /api/comentarios.php                    - Crear comentario
 * POST /api/comentarios.php?action=like&id=X  - Dar like
 */

// Debugging temporal - mostrar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../admin/config/config.php';
require_once __DIR__ . '/../admin/config/database.php';

try {
    $pdo = getConnection();

    // GET: Obtener comentarios
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $articuloId = (int)($_GET['articulo_id'] ?? 0);

        if (!$articuloId) {
            throw new Exception("Se requiere articulo_id");
        }

        $comentarios = obtenerComentarios($pdo, $articuloId);

        echo json_encode([
            'success' => true,
            'comentarios' => $comentarios,
            'total' => count($comentarios)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST: Crear comentario o dar like
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_GET['action'] ?? 'create';

        if ($action === 'like') {
            $comentarioId = (int)($_GET['id'] ?? 0);
            if (!$comentarioId) {
                throw new Exception("Se requiere id del comentario");
            }

            $resultado = darLike($pdo, $comentarioId);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Crear comentario
        $input = json_decode(file_get_contents('php://input'), true);

        $articuloId = (int)($input['articulo_id'] ?? 0);
        $email = trim($input['email'] ?? '');
        $contenido = trim($input['contenido'] ?? '');
        $captcha = trim($input['captcha'] ?? '');
        $captchaExpected = (int)($input['captcha_expected'] ?? 0);
        $parentId = (int)($input['parent_id'] ?? 0) ?: null;

        // Validaciones
        if (!$articuloId) {
            throw new Exception("Artículo no especificado");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }

        if (mb_strlen($contenido) < 10) {
            throw new Exception("El comentario debe tener al menos 10 caracteres");
        }

        if (mb_strlen($contenido) > 2000) {
            throw new Exception("El comentario es demasiado largo (máximo 2000 caracteres)");
        }

        // Validar captcha
        if ((int)$captcha !== $captchaExpected) {
            throw new Exception("Respuesta incorrecta al captcha");
        }

        // Verificar que es suscriptor
        $suscriptor = verificarSuscriptor($pdo, $email);
        if (!$suscriptor) {
            throw new Exception("Debés estar suscrito al newsletter para comentar. ¡Suscribite gratis!");
        }

        // Crear comentario
        $comentario = crearComentario($pdo, [
            'articulo_id' => $articuloId,
            'parent_id' => $parentId,
            'email' => $email,
            'nombre' => $suscriptor['nombre'] ?: explode('@', $email)[0],
            'contenido' => $contenido
        ]);

        // Notificar al admin
        notificarNuevoComentario($pdo, $comentario, $articuloId);

        echo json_encode([
            'success' => true,
            'mensaje' => '¡Comentario publicado!',
            'comentario' => $comentario
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Obtiene los comentarios de un artículo
 */
function obtenerComentarios(PDO $pdo, int $articuloId): array {
    $stmt = $pdo->prepare("
        SELECT id, parent_id, nombre, contenido, likes, es_admin, fecha_creacion
        FROM blog_comentarios
        WHERE articulo_id = ? AND estado = 'aprobado'
        ORDER BY fecha_creacion ASC
    ");
    $stmt->execute([$articuloId]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar en árbol (comentarios con respuestas)
    $tree = [];
    $map = [];

    foreach ($comentarios as $c) {
        $c['respuestas'] = [];
        $c['fecha_formateada'] = formatearFecha($c['fecha_creacion']);
        $map[$c['id']] = $c;
    }

    foreach ($map as $id => $c) {
        if ($c['parent_id'] && isset($map[$c['parent_id']])) {
            $map[$c['parent_id']]['respuestas'][] = $c;
        } else if (!$c['parent_id']) {
            $tree[] = &$map[$id];
        }
    }

    return $tree;
}

/**
 * Verifica si el email está suscrito
 */
function verificarSuscriptor(PDO $pdo, string $email): ?array {
    // Buscar en tabla de suscriptores
    $stmt = $pdo->prepare("
        SELECT email, nombre FROM subscribers
        WHERE email = ? AND confirmado = 1
    ");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Crea un nuevo comentario
 */
function crearComentario(PDO $pdo, array $data): array {
    $stmt = $pdo->prepare("
        INSERT INTO blog_comentarios (articulo_id, parent_id, email, nombre, contenido)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['articulo_id'],
        $data['parent_id'],
        $data['email'],
        $data['nombre'],
        $data['contenido']
    ]);

    $id = $pdo->lastInsertId();

    // Actualizar contador en artículo
    $pdo->prepare("
        UPDATE blog_articulos
        SET total_comentarios = (
            SELECT COUNT(*) FROM blog_comentarios
            WHERE articulo_id = ? AND estado = 'aprobado'
        )
        WHERE id = ?
    ")->execute([$data['articulo_id'], $data['articulo_id']]);

    return [
        'id' => (int)$id,
        'nombre' => $data['nombre'],
        'contenido' => $data['contenido'],
        'likes' => 0,
        'es_admin' => 0,
        'fecha_formateada' => 'Ahora',
        'respuestas' => []
    ];
}

/**
 * Da like a un comentario
 */
function darLike(PDO $pdo, int $comentarioId): array {
    // Hash del IP para evitar duplicados
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Verificar si ya dio like
    $stmt = $pdo->prepare("
        SELECT id FROM blog_comentarios_likes
        WHERE comentario_id = ? AND ip_hash = ?
    ");
    $stmt->execute([$comentarioId, $ipHash]);

    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Ya diste like a este comentario'];
    }

    // Insertar like
    $pdo->prepare("
        INSERT INTO blog_comentarios_likes (comentario_id, ip_hash)
        VALUES (?, ?)
    ")->execute([$comentarioId, $ipHash]);

    // Incrementar contador
    $pdo->prepare("
        UPDATE blog_comentarios SET likes = likes + 1 WHERE id = ?
    ")->execute([$comentarioId]);

    // Obtener nuevo total
    $stmt = $pdo->prepare("SELECT likes FROM blog_comentarios WHERE id = ?");
    $stmt->execute([$comentarioId]);
    $likes = $stmt->fetchColumn();

    return ['success' => true, 'likes' => (int)$likes];
}

/**
 * Notifica al admin sobre nuevo comentario
 */
function notificarNuevoComentario(PDO $pdo, array $comentario, int $articuloId): void {
    // Obtener título del artículo
    $stmt = $pdo->prepare("SELECT titulo, slug FROM blog_articulos WHERE id = ?");
    $stmt->execute([$articuloId]);
    $articulo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$articulo) return;

    // Enviar email (opcional - solo si está configurado)
    if (defined('ADMIN_EMAIL') && defined('SENDGRID_API_KEY')) {
        $asunto = "Nuevo comentario en: " . $articulo['titulo'];
        $cuerpo = "
            <h2>Nuevo comentario en TERRApp Blog</h2>
            <p><strong>Artículo:</strong> {$articulo['titulo']}</p>
            <p><strong>Autor:</strong> {$comentario['nombre']}</p>
            <p><strong>Comentario:</strong></p>
            <blockquote>{$comentario['contenido']}</blockquote>
            <p><a href='" . BLOG_URL . "scriptum.php?titulus={$articulo['slug']}#comentarios'>Ver en el blog</a></p>
        ";

        // Usar EmailNotifier si existe
        if (file_exists(__DIR__ . '/../admin/includes/EmailNotifier.php')) {
            require_once __DIR__ . '/../admin/includes/EmailNotifier.php';
            $notifier = new EmailNotifier(SENDGRID_API_KEY);
            try {
                $notifier->enviarEmail(ADMIN_EMAIL, $asunto, $cuerpo);
            } catch (Exception $e) {
                error_log("Error enviando notificación de comentario: " . $e->getMessage());
            }
        }
    }
}

/**
 * Formatea fecha relativa
 */
function formatearFecha(string $fecha): string {
    $timestamp = strtotime($fecha);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'Ahora';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';

    return date('d M Y', $timestamp);
}
