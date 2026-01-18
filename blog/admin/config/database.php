<?php
/**
 * TERRApp Blog - Conexión a la base de datos con PDO
 */

require_once __DIR__ . '/config.php';

/**
 * Obtiene la conexión PDO singleton
 */
function getConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Error de conexión: " . $e->getMessage());
            }
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión a base de datos']));
        }
    }

    return $pdo;
}

/**
 * Respuesta JSON estandarizada
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Log de errores
 */
function logError(string $message, array $context = []): void {
    $logFile = __DIR__ . '/../../logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $entry = date('Y-m-d H:i:s') . " | " . $message;
    if (!empty($context)) {
        $entry .= " | " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    $entry .= "\n";

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
