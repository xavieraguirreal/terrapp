<?php
/**
 * TERRApp API - Configuración
 *
 * IMPORTANTE: Este archivo contiene credenciales sensibles.
 * En producción, usar variables de entorno.
 */

// Prevenir acceso directo
if (!defined('TERRAPP_API')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'verumax_terrapp');
define('DB_USER', 'verumax_admin');
define('DB_PASS', '9BD121wk36210270');
define('DB_CHARSET', 'utf8mb4');

// Configuración de SendGrid (misma API key que VERUMax/Certificatum)
define('SENDGRID_API_KEY', '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');
define('SENDGRID_FROM_EMAIL', 'terrapp@verumax.com');
define('SENDGRID_FROM_NAME', 'TERRApp');

// URL base de la landing
define('LANDING_URL', 'https://terrapp.verumax.com/landing/');

// Modo debug
define('DEBUG_MODE', false);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

/**
 * Conexión PDO singleton
 */
function getDB(): PDO {
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
                throw $e;
            }
            http_response_code(500);
            exit(json_encode(['error' => 'Error de conexión a base de datos']));
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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log de errores
 */
function logError(string $message, array $context = []): void {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = date('Y-m-d H:i:s') . " | " . $message;
    if (!empty($context)) {
        $entry .= " | " . json_encode($context);
    }
    $entry .= "\n";

    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
