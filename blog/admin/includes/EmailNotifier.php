<?php
/**
 * TERRApp Blog - Notificador por Email usando SendGrid
 */

class EmailNotifier {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private string $adminEmail;

    public function __construct() {
        $this->apiKey = SENDGRID_API_KEY;
        $this->fromEmail = SENDGRID_FROM_EMAIL;
        $this->fromName = SENDGRID_FROM_NAME;
        $this->adminEmail = ADMIN_EMAIL;
    }

    /**
     * Envía notificación de nuevo artículo para revisar
     */
    public function notificarNuevoArticulo(array $articulo): bool {
        // Generar tokens para acciones
        $tokenAprobar = generarTokenAccion($articulo['id'], 'aprobar');
        $tokenRechazar = generarTokenAccion($articulo['id'], 'rechazar');
        $tokenSaltear = generarTokenAccion($articulo['id'], 'saltear');

        $adminUrl = BLOG_ADMIN_URL;

        // URLs de acciones
        $urlAprobar = "{$adminUrl}api/accion_email.php?token={$tokenAprobar}";
        $urlRechazar = "{$adminUrl}api/accion_email.php?token={$tokenRechazar}";
        $urlSaltear = "{$adminUrl}api/accion_email.php?token={$tokenSaltear}";
        $urlEditar = "{$adminUrl}revisar.php?id={$articulo['id']}";

        // Emoji de región
        $regionEmoji = $articulo['region'] === 'sudamerica' ? '🌎' : '🌐';
        $regionTexto = $articulo['region'] === 'sudamerica' ? 'Sudamérica' : 'Internacional';
        if (!empty($articulo['pais_origen'])) {
            $regionTexto .= " ({$articulo['pais_origen']})";
        }

        // Contenido completo (formateado para email)
        $contenidoCompleto = nl2br(htmlspecialchars($articulo['contenido'] ?? ''));
        $originalCompleto = nl2br(htmlspecialchars($articulo['contenido_original'] ?? ''));
        $opinionCompleta = nl2br(htmlspecialchars($articulo['opinion_editorial'] ?? ''));

        // Tips
        $tipsHtml = '';
        $tips = is_array($articulo['tips']) ? $articulo['tips'] : json_decode($articulo['tips'] ?? '[]', true);
        if (!empty($tips)) {
            $tipsHtml = '<tr><td style="padding: 15px; background: #f0f9f4; border-radius: 8px;">';
            $tipsHtml .= '<strong style="color: #2d7553;">💡 TIPS:</strong><ul style="margin: 10px 0 0; padding-left: 20px;">';
            foreach ($tips as $tip) {
                $tipsHtml .= '<li style="margin-bottom: 5px;">' . htmlspecialchars($tip) . '</li>';
            }
            $tipsHtml .= '</ul></td></tr>';
        }

        $subject = "🌱 Nueva noticia para revisar - TERRApp Blog";

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background: #faf6f1;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #2d7553 0%, #558b2f 100%); padding: 25px; text-align: center;">
                <h1 style="color: white; margin: 0; font-size: 22px;">🌱 Nueva noticia para revisar</h1>
                <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0; font-size: 14px;">TERRApp Blog</p>
            </td>
        </tr>

        <!-- Título -->
        <tr>
            <td style="padding: 20px 20px 10px;">
                <h2 style="margin: 0; color: #1a1a1a; font-size: 18px;">{$articulo['titulo']}</h2>
                <p style="margin: 8px 0 0; color: #666; font-size: 14px;">
                    {$regionEmoji} <strong>{$regionTexto}</strong> &nbsp;|&nbsp;
                    📰 {$articulo['fuente_nombre']}
                </p>
            </td>
        </tr>

        <!-- Contenido generado -->
        <tr>
            <td style="padding: 15px 20px;">
                <div style="background: #f8f8f8; border-left: 4px solid #2d7553; padding: 15px; border-radius: 0 8px 8px 0;">
                    <strong style="color: #2d7553;">📝 CONTENIDO GENERADO:</strong>
                    <div style="margin: 10px 0 0; color: #444; line-height: 1.7;">{$contenidoCompleto}</div>
                </div>
            </td>
        </tr>

        <!-- Contenido original -->
        <tr>
            <td style="padding: 0 20px 15px;">
                <div style="background: #fff8e6; border-left: 4px solid #f5a623; padding: 15px; border-radius: 0 8px 8px 0; max-height: 400px; overflow-y: auto;">
                    <strong style="color: #b8860b;">📄 CONTENIDO ORIGINAL:</strong>
                    <p style="margin: 8px 0; font-size: 12px;">
                        <a href="{$articulo['fuente_url']}" target="_blank" style="color: #b8860b;">🔗 Ver en sitio original →</a>
                    </p>
                    <div style="margin: 10px 0 0; color: #666; font-size: 13px; line-height: 1.6;">{$originalCompleto}</div>
                </div>
            </td>
        </tr>

        <!-- Opinión editorial -->
        <tr>
            <td style="padding: 0 20px 15px;">
                <div style="background: #e8f4e8; border-left: 4px solid #558b2f; padding: 15px; border-radius: 0 8px 8px 0;">
                    <strong style="color: #33691e;">🌱 OPINIÓN EDITORIAL:</strong>
                    <div style="margin: 10px 0 0; color: #444; line-height: 1.7;">{$opinionCompleta}</div>
                </div>
            </td>
        </tr>

        <!-- Tips -->
        {$tipsHtml}

        <!-- Botones de acción -->
        <tr>
            <td style="padding: 25px 20px; text-align: center; background: #fafafa;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 5px; text-align: center;">
                            <a href="{$urlAprobar}" style="display: inline-block; padding: 12px 24px; background: #2d7553; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 5px;">✅ APROBAR</a>
                        </td>
                        <td style="padding: 5px; text-align: center;">
                            <a href="{$urlRechazar}" style="display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 5px;">❌ RECHAZAR</a>
                        </td>
                        <td style="padding: 5px; text-align: center;">
                            <a href="{$urlSaltear}" style="display: inline-block; padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 5px;">⏭️ SALTEAR</a>
                        </td>
                    </tr>
                </table>
                <p style="margin: 15px 0 0;">
                    <a href="{$urlEditar}" style="color: #2d7553; text-decoration: underline;">✏️ Ver/Editar en panel web</a>
                </p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="padding: 15px; text-align: center; background: #333; color: #999; font-size: 12px;">
                <p style="margin: 0;">TERRApp Blog - Agricultura Urbana para Sudamérica</p>
                <p style="margin: 5px 0 0;">Este es un email automático, no responder.</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return $this->enviarEmail($this->adminEmail, $subject, $html);
    }

    /**
     * Envía email genérico usando SendGrid API
     */
    public function enviarEmail(string $to, string $subject, string $htmlContent): bool {
        $url = 'https://api.sendgrid.com/v3/mail/send';

        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $to]],
                    'subject' => $subject
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $htmlContent
                ]
            ]
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logError("Error enviando email: " . $error);
            return false;
        }

        // SendGrid devuelve 202 para envío exitoso
        if ($httpCode !== 202 && $httpCode !== 200) {
            logError("Error SendGrid: HTTP {$httpCode} - {$response}");
            return false;
        }

        return true;
    }
}
