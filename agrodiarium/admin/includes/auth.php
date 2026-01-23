<?php
/**
 * TERRApp Blog - Autenticación Simple (Temporal)
 *
 * TODO FUTURO: Implementar login completo con:
 * - Usuario/contraseña en BD
 * - 2FA con Google Authenticator (TOTP)
 * - Sesiones seguras
 * - Rate limiting
 */

// Clave de acceso temporal (parámetro en URL)
define('ADMIN_ACCESS_KEY', '36210270');

/**
 * Verifica si el usuario tiene acceso al admin
 * Uso: incluir al inicio de cada página admin
 */
function verificarAcceso(): bool {
    // Verificar si la clave está en la URL o en sesión
    session_start();

    // Si viene el parámetro, guardar en sesión
    if (isset($_GET[ADMIN_ACCESS_KEY])) {
        $_SESSION['admin_auth'] = true;
        $_SESSION['admin_auth_time'] = time();

        // Redirigir sin el parámetro para limpiar URL
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: $url");
        exit;
    }

    // Verificar sesión (válida por 24 horas)
    if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
        if (time() - ($_SESSION['admin_auth_time'] ?? 0) < 86400) {
            return true;
        }
    }

    return false;
}

/**
 * Muestra página de acceso denegado y termina
 */
function mostrarAccesoDenegado(): void {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado - TERRApp</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg text-center max-w-md">
            <div class="text-6xl mb-4">🔒</div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Acceso Restringido</h1>
            <p class="text-gray-600 mb-6">Esta área requiere autorización.</p>
            <a href="../../" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                Volver al Blog
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Verificar acceso para APIs (retorna JSON si no autorizado)
 */
function verificarAccesoAPI(): bool {
    session_start();

    if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
        if (time() - ($_SESSION['admin_auth_time'] ?? 0) < 86400) {
            return true;
        }
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado', 'success' => false]);
    exit;
}
