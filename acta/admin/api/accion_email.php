<?php
/**
 * TERRApp Blog - Procesar acciones desde email
 */

require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    mostrarRespuesta(false, 'Token no proporcionado');
    exit;
}

$resultado = ejecutarAccionToken($token);

mostrarRespuesta($resultado['success'], $resultado['accion'] ?? $resultado['error'] ?? 'Operación completada');

function mostrarRespuesta(bool $exito, string $mensaje): void {
    $color = $exito ? '#2d7553' : '#dc3545';
    $icono = $exito ? '✅' : '❌';
    $titulo = $exito ? 'Acción completada' : 'Error';

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} - TERRApp Blog</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #faf6f1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 400px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: {$color};
            margin: 0 0 15px;
        }
        p {
            color: #666;
            margin: 0 0 25px;
        }
        a {
            display: inline-block;
            background: {$color};
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{$icono}</div>
        <h1>{$titulo}</h1>
        <p>{$mensaje}</p>
        <a href="../index.php">Ir al panel de administración</a>
    </div>
</body>
</html>
HTML;
}
