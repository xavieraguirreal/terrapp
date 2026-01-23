<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/EmailNotifier.php';

echo "<h3>Test de envío de email</h3>";
echo "<p><strong>API Key (primeros 20):</strong> " . substr(SENDGRID_API_KEY, 0, 20) . "...</p>";

$notifier = new EmailNotifier();

// Intentar enviar email de prueba
$resultado = $notifier->enviarEmail(
    ADMIN_EMAIL,
    'Test desde TERRApp - ' . date('H:i:s'),
    '<h1>Test</h1><p>Si ves este email, SendGrid funciona correctamente.</p>'
);

if ($resultado) {
    echo "<p style='color:green'>✅ Email enviado correctamente</p>";
} else {
    echo "<p style='color:red'>❌ Error al enviar email - revisá blog/admin/logs/email.log</p>";
}
