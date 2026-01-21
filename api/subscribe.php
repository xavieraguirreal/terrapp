<?php
/**
 * TERRApp API - Endpoint de suscripción
 *
 * POST /api/subscribe.php
 * Body: { "email": "user@example.com", "lang": "es_AR" }
 *
 * Respuesta exitosa:
 * {
 *   "success": true,
 *   "message": "...",
 *   "counter": { "real": 150, "display": 823, "formatted": "823" }
 * }
 */

define('TERRAPP_API', true);
require_once __DIR__ . '/config.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Intentar form data
    $input = $_POST;
}

$email = trim($input['email'] ?? '');
$lang = trim($input['lang'] ?? 'es_AR');
$nombre = trim($input['nombre'] ?? '');
$comment = trim($input['comment'] ?? '');
$source = trim($input['source'] ?? 'form');
$quizSessionId = trim($input['quiz_session_id'] ?? '');

// Validar email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email inválido', 'field' => 'email'], 400);
}

// Sanitizar
$email = strtolower($email);
$lang = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang) ? $lang : 'es_AR';

try {
    $pdo = getDB();

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id, confirmado FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $emailSent = false;

        // Si viene del quiz, guardar datos y enviar email con resultado
        if ($source === 'quiz' && !empty($comment)) {
            saveComment($pdo, $existing['id'], $comment, 'quiz_repeat');
            if (!empty($quizSessionId)) {
                linkQuizToSubscriber($pdo, $quizSessionId, $existing['id']);
            }
            $emailSent = sendQuizResultEmail($email, $nombre, $lang, $comment);
        }
        // Si es sugerencia (no quiz), guardar y enviar agradecimiento
        elseif (!empty($comment) && $source !== 'quiz') {
            saveComment($pdo, $existing['id'], $comment, $source . '_repeat');
            $emailSent = sendSuggestionThankYouEmail($email, $nombre, $lang, $comment);
        }

        $counter = getCounter($pdo);
        jsonResponse([
            'success' => true,
            'already_subscribed' => true,
            'message' => getAlreadySubscribedMessage($lang),
            'comment_saved' => !empty($comment),
            'email_sent' => $emailSent,
            'counter' => $counter
        ]);
    }

    // Generar multiplicador aleatorio (3-10)
    $multiplier = random_int(3, 10);

    // Generar token de confirmación
    $token = bin2hex(random_bytes(32));

    // Insertar suscriptor (confirmado automáticamente al recibir email)
    $stmt = $pdo->prepare("
        INSERT INTO subscribers (email, nombre, pais_codigo, ip_address, user_agent, random_multiplier, token_confirmacion, comentario, confirmado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $email,
        $nombre ?: null,
        $lang,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $multiplier,
        $token,
        $comment ?: null
    ]);

    $subscriberId = $pdo->lastInsertId();

    // Guardar comentario en tabla separada (si existe)
    if (!empty($comment)) {
        saveComment($pdo, $subscriberId, $comment, $source);
    }

    // Vincular quiz si hay session_id
    if (!empty($quizSessionId)) {
        linkQuizToSubscriber($pdo, $quizSessionId, $subscriberId);
    }

    // Actualizar contador cache
    updateCounterCache($pdo, $multiplier);

    // Enviar email según el caso
    if ($source === 'quiz' && !empty($comment)) {
        $emailSent = sendQuizResultEmail($email, $nombre, $lang, $comment);
    } else {
        $emailSent = sendWelcomeEmail($email, $nombre, $lang, $token);
    }

    // Obtener contador actualizado
    $counter = getCounter($pdo);

    jsonResponse([
        'success' => true,
        'message' => getSuccessMessage($lang),
        'email_sent' => $emailSent,
        'counter' => $counter
    ]);

} catch (PDOException $e) {
    logError('Subscribe error', ['email' => $email, 'error' => $e->getMessage()]);
    jsonResponse(['error' => 'Error al procesar la solicitud'], 500);
}

/**
 * Actualiza el cache del contador
 */
function updateCounterCache(PDO $pdo, int $multiplier): void {
    $pdo->exec("
        UPDATE counter_cache
        SET total_real = total_real + 1,
            total_display = total_display + {$multiplier}
        WHERE id = 1
    ");
}

/**
 * Guarda un comentario/sugerencia en la tabla subscriber_comments
 */
function saveComment(PDO $pdo, int $subscriberId, string $comment, string $source = 'form'): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO subscriber_comments (subscriber_id, comentario, source, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $subscriberId,
            $comment,
            $source,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        logError('Comment save error', ['subscriber_id' => $subscriberId, 'error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Vincula un resultado de quiz con un suscriptor
 */
function linkQuizToSubscriber(PDO $pdo, string $sessionId, int $subscriberId): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE quiz_results
            SET subscriber_id = ?, email_captured = TRUE
            WHERE session_id = ? AND subscriber_id IS NULL
        ");
        $stmt->execute([$subscriberId, $sessionId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        logError('Quiz link error', ['session_id' => $sessionId, 'subscriber_id' => $subscriberId, 'error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Obtiene el contador actual
 */
function getCounter(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM v_subscriber_count");
    $data = $stmt->fetch();

    return [
        'real' => (int)($data['total_real'] ?? 0),
        'display' => (int)($data['total_display'] ?? 0),
        'formatted' => $data['total_formatted'] ?? '0'
    ];
}

/**
 * Envía email de bienvenida via SendGrid
 */
function sendWelcomeEmail(string $email, string $nombre, string $lang, string $token): bool {
    if (empty(SENDGRID_API_KEY)) {
        logError('SendGrid API key not configured');
        return false;
    }

    $confirmUrl = LANDING_URL . "?confirm=" . $token;
    $nombreDisplay = $nombre ?: 'futuro/a huertero/a';
    $content = getEmailContent($lang, $nombreDisplay, $confirmUrl);

    $payload = [
        'personalizations' => [[
            'to' => [['email' => $email, 'name' => $nombre]],
            'subject' => $content['subject']
        ]],
        'from' => [
            'email' => SENDGRID_FROM_EMAIL,
            'name' => SENDGRID_FROM_NAME
        ],
        'content' => [
            ['type' => 'text/plain', 'value' => $content['text']],
            ['type' => 'text/html', 'value' => $content['html']]
        ],
        'tracking_settings' => [
            'click_tracking' => ['enable' => true],
            'open_tracking' => ['enable' => true]
        ]
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 202) {
        // Registrar en logs
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO email_logs (email_to, email_type, subject, status, sent_at)
                VALUES (?, 'welcome', ?, 'sent', NOW())
            ");
            $stmt->execute([$email, $content['subject']]);
        } catch (Exception $e) {
            logError('Email log error', ['error' => $e->getMessage()]);
        }
        return true;
    }

    logError('SendGrid error', ['code' => $httpCode, 'response' => $response]);
    return false;
}

/**
 * Contenido del email según idioma
 */
function getEmailContent(string $lang, string $nombre, string $confirmUrl): array {
    $contents = [
        'es' => [
            'subject' => '¡Bienvenido/a a TERRApp! 🌱',
            'text' => "Hola {$nombre},\n\n¡Gracias por tu interés en TERRApp!\n\nSerás de las primeras personas en enterarte cuando lancemos la versión beta.\n\nMientras tanto, seguí cultivando vida.\n\n🌱 El equipo de TERRApp",
            'html' => getHtmlTemplate($nombre, $confirmUrl, 'es')
        ],
        'pt' => [
            'subject' => 'Bem-vindo/a ao TERRApp! 🌱',
            'text' => "Olá {$nombre},\n\nObrigado pelo seu interesse no TERRApp!\n\nVocê será uma das primeiras pessoas a saber quando lançarmos a versão beta.\n\nEnquanto isso, continue cultivando vida.\n\n🌱 A equipe TERRApp",
            'html' => getHtmlTemplate($nombre, $confirmUrl, 'pt')
        ],
        'en' => [
            'subject' => 'Welcome to TERRApp! 🌱',
            'text' => "Hello {$nombre},\n\nThank you for your interest in TERRApp!\n\nYou will be among the first to know when we launch the beta version.\n\nMeanwhile, keep growing life.\n\n🌱 The TERRApp Team",
            'html' => getHtmlTemplate($nombre, $confirmUrl, 'en')
        ],
        'fr' => [
            'subject' => 'Bienvenue sur TERRApp! 🌱',
            'text' => "Bonjour {$nombre},\n\nMerci pour votre intérêt pour TERRApp!\n\nVous serez parmi les premiers à savoir quand nous lancerons la version bêta.\n\nEn attendant, continuez à cultiver la vie.\n\n🌱 L'équipe TERRApp",
            'html' => getHtmlTemplate($nombre, $confirmUrl, 'fr')
        ],
        'nl' => [
            'subject' => 'Welkom bij TERRApp! 🌱',
            'text' => "Hallo {$nombre},\n\nBedankt voor uw interesse in TERRApp!\n\nU zult een van de eersten zijn die het weet wanneer we de bètaversie lanceren.\n\nBlijf ondertussen leven kweken.\n\n🌱 Het TERRApp Team",
            'html' => getHtmlTemplate($nombre, $confirmUrl, 'nl')
        ]
    ];

    // Extraer código de idioma base
    $baseLang = substr($lang, 0, 2);

    return $contents[$baseLang] ?? $contents['es'];
}

/**
 * Template HTML del email
 */
function getHtmlTemplate(string $nombre, string $confirmUrl, string $lang): string {
    $texts = [
        'es' => [
            'greeting' => "¡Hola {$nombre}!",
            'thanks' => "Gracias por tu interés en TERRApp",
            'body' => "Serás de las primeras personas en enterarte cuando lancemos la versión beta. Estamos trabajando para crear una herramienta que realmente sirva a huerteros y huerteras de toda Sudamérica.",
            'meanwhile' => "Mientras tanto, seguí cultivando vida.",
            'team' => "El equipo de TERRApp"
        ],
        'pt' => [
            'greeting' => "Olá {$nombre}!",
            'thanks' => "Obrigado pelo seu interesse no TERRApp",
            'body' => "Você será uma das primeiras pessoas a saber quando lançarmos a versão beta. Estamos trabalhando para criar uma ferramenta que realmente sirva horticultores e horticultoras de toda a América do Sul.",
            'meanwhile' => "Enquanto isso, continue cultivando vida.",
            'team' => "A equipe TERRApp"
        ],
        'en' => [
            'greeting' => "Hello {$nombre}!",
            'thanks' => "Thank you for your interest in TERRApp",
            'body' => "You will be among the first to know when we launch the beta version. We are working to create a tool that truly serves gardeners throughout South America.",
            'meanwhile' => "Meanwhile, keep growing life.",
            'team' => "The TERRApp Team"
        ],
        'fr' => [
            'greeting' => "Bonjour {$nombre}!",
            'thanks' => "Merci pour votre intérêt pour TERRApp",
            'body' => "Vous serez parmi les premiers à savoir quand nous lancerons la version bêta. Nous travaillons à créer un outil qui serve vraiment les jardiniers de toute l'Amérique du Sud.",
            'meanwhile' => "En attendant, continuez à cultiver la vie.",
            'team' => "L'équipe TERRApp"
        ],
        'nl' => [
            'greeting' => "Hallo {$nombre}!",
            'thanks' => "Bedankt voor uw interesse in TERRApp",
            'body' => "U zult een van de eersten zijn die het weet wanneer we de bètaversie lanceren. We werken aan het creëren van een tool die tuiniers in heel Zuid-Amerika echt van dienst is.",
            'meanwhile' => "Blijf ondertussen leven kweken.",
            'team' => "Het TERRApp Team"
        ]
    ];

    $t = $texts[$lang] ?? $texts['es'];

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f9f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f9f4; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3d9268, #2d7553); padding: 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 700;">TERRApp</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">Inteligencia nativa para tu suelo</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1d3e30; margin: 0 0 20px 0; font-size: 24px;">{$t['greeting']}</h2>
                            <p style="color: #3d9268; font-size: 18px; font-weight: 600; margin: 0 0 20px 0;">{$t['thanks']}</p>
                            <p style="color: #5a3f2b; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">{$t['body']}</p>
                            <p style="color: #5a3f2b; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;"><strong>{$t['meanwhile']}</strong></p>
                            <p style="color: #8b6442; font-size: 14px; margin: 30px 0 0 0;">
                                🌱 {$t['team']}
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1d3e30; padding: 20px; text-align: center;">
                            <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0;">
                                © 2026 TERRApp - Una solución de VERUMax, impulsada por la Cooperativa de Trabajo Liberté Ltda.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Mensajes de éxito según idioma
 */
function getSuccessMessage(string $lang): string {
    $messages = [
        'es' => '¡Excelente! Te avisaremos cuando TERRApp esté disponible.',
        'pt' => 'Excelente! Avisaremos quando o TERRApp estiver disponível.',
        'en' => 'Excellent! We\'ll let you know when TERRApp is available.',
        'fr' => 'Excellent! Nous vous préviendrons quand TERRApp sera disponible.',
        'nl' => 'Uitstekend! We laten u weten wanneer TERRApp beschikbaar is.'
    ];

    $baseLang = substr($lang, 0, 2);
    return $messages[$baseLang] ?? $messages['es'];
}

/**
 * Mensajes de ya suscrito según idioma
 */
function getAlreadySubscribedMessage(string $lang): string {
    $messages = [
        'es' => '¡Ya estás en la lista! Te avisaremos cuando TERRApp esté disponible.',
        'pt' => 'Você já está na lista! Avisaremos quando o TERRApp estiver disponível.',
        'en' => 'You\'re already on the list! We\'ll let you know when TERRApp is available.',
        'fr' => 'Vous êtes déjà sur la liste! Nous vous préviendrons quand TERRApp sera disponible.',
        'nl' => 'U staat al op de lijst! We laten u weten wanneer TERRApp beschikbaar is.'
    ];

    $baseLang = substr($lang, 0, 2);
    return $messages[$baseLang] ?? $messages['es'];
}

/**
 * Envía email con resultado del quiz via SendGrid
 */
function sendQuizResultEmail(string $email, string $nombre, string $lang, string $quizData): bool {
    if (empty(SENDGRID_API_KEY)) {
        logError('SendGrid API key not configured');
        return false;
    }

    $nombreDisplay = $nombre ?: 'futuro/a huertero/a';

    // Parsear datos del quiz
    $profile = 'Curioso/a Verde';
    if (preg_match('/Perfil:\s*([^,]+)/', $quizData, $matches)) {
        $profile = trim($matches[1]);
    }

    $content = getQuizResultEmailContent($lang, $nombreDisplay, $profile, $quizData);

    $payload = [
        'personalizations' => [[
            'to' => [['email' => $email, 'name' => $nombre]],
            'subject' => $content['subject']
        ]],
        'from' => [
            'email' => SENDGRID_FROM_EMAIL,
            'name' => SENDGRID_FROM_NAME
        ],
        'content' => [
            ['type' => 'text/plain', 'value' => $content['text']],
            ['type' => 'text/html', 'value' => $content['html']]
        ],
        'tracking_settings' => [
            'click_tracking' => ['enable' => true],
            'open_tracking' => ['enable' => true]
        ]
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 202) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO email_logs (email_to, email_type, subject, status, sent_at)
                VALUES (?, 'quiz_result', ?, 'sent', NOW())
            ");
            $stmt->execute([$email, $content['subject']]);
        } catch (Exception $e) {
            logError('Email log error', ['error' => $e->getMessage()]);
        }
        return true;
    }

    logError('SendGrid error (quiz)', ['code' => $httpCode, 'response' => $response]);
    return false;
}

/**
 * Contenido del email de resultado del quiz
 */
function getQuizResultEmailContent(string $lang, string $nombre, string $profile, string $quizData): array {
    $baseLang = substr($lang, 0, 2);

    // Mapeo de perfiles a descripciones y tips
    $profiles = [
        'es' => [
            'Curioso/a Verde' => [
                'emoji' => '🌱',
                'desc' => 'Estás dando tus primeros pasos en el mundo de la huerta. ¡Qué emocionante!',
                'tips' => ['Empezá con especies fáciles como albahaca, lechuga o rabanitos', 'No te desanimes si algo no sale, es parte del aprendizaje', 'TERRApp te va a guiar paso a paso']
            ],
            'Huertero/a en Crecimiento' => [
                'emoji' => '🌿',
                'desc' => 'Ya tenés algo de experiencia y querés llevar tu huerta al siguiente nivel.',
                'tips' => ['Probá hacer asociaciones de cultivos para optimizar espacio', 'Empezá a compostar si todavía no lo hacés', 'TERRApp te va a ayudar a planificar rotaciones']
            ],
            'Cultivador/a Experimentado/a' => [
                'emoji' => '🥬',
                'desc' => 'Tenés experiencia y buscás optimizar tu producción.',
                'tips' => ['Experimentá con biopreparados caseros', 'Considerá ampliar la diversidad de especies', 'TERRApp te va a ayudar a llevar registros detallados']
            ],
            'Maestro/a de la Tierra' => [
                'emoji' => '🏆',
                'desc' => '¡Sos un/a verdadero/a experto/a! Tu conocimiento es valioso.',
                'tips' => ['Compartí tu experiencia con la comunidad', 'TERRApp te va a conectar con otros productores', 'Tu feedback nos ayuda a mejorar la app']
            ]
        ],
        'pt' => [
            'Curioso/a Verde' => [
                'emoji' => '🌱',
                'desc' => 'Você está dando seus primeiros passos no mundo da horta. Que emocionante!',
                'tips' => ['Comece com espécies fáceis como manjericão, alface ou rabanetes', 'Não desanime se algo não der certo, faz parte do aprendizado', 'TERRApp vai te guiar passo a passo']
            ],
            'Huertero/a en Crecimiento' => [
                'emoji' => '🌿',
                'desc' => 'Você já tem alguma experiência e quer levar sua horta para o próximo nível.',
                'tips' => ['Experimente fazer associações de culturas para otimizar espaço', 'Comece a compostar se ainda não faz', 'TERRApp vai te ajudar a planejar rotações']
            ],
            'Cultivador/a Experimentado/a' => [
                'emoji' => '🥬',
                'desc' => 'Você tem experiência e busca otimizar sua produção.',
                'tips' => ['Experimente com biopreparados caseiros', 'Considere ampliar a diversidade de espécies', 'TERRApp vai te ajudar a manter registros detalhados']
            ],
            'Maestro/a de la Tierra' => [
                'emoji' => '🏆',
                'desc' => 'Você é um/a verdadeiro/a especialista! Seu conhecimento é valioso.',
                'tips' => ['Compartilhe sua experiência com a comunidade', 'TERRApp vai te conectar com outros produtores', 'Seu feedback nos ajuda a melhorar o app']
            ]
        ],
        'en' => [
            'Curioso/a Verde' => [
                'emoji' => '🌱',
                'desc' => 'You\'re taking your first steps in the gardening world. How exciting!',
                'tips' => ['Start with easy species like basil, lettuce or radishes', 'Don\'t get discouraged if something doesn\'t work out, it\'s part of learning', 'TERRApp will guide you step by step']
            ],
            'Huertero/a en Crecimiento' => [
                'emoji' => '🌿',
                'desc' => 'You already have some experience and want to take your garden to the next level.',
                'tips' => ['Try companion planting to optimize space', 'Start composting if you haven\'t already', 'TERRApp will help you plan rotations']
            ],
            'Cultivador/a Experimentado/a' => [
                'emoji' => '🥬',
                'desc' => 'You have experience and are looking to optimize your production.',
                'tips' => ['Experiment with homemade bio-preparations', 'Consider expanding species diversity', 'TERRApp will help you keep detailed records']
            ],
            'Maestro/a de la Tierra' => [
                'emoji' => '🏆',
                'desc' => 'You\'re a true expert! Your knowledge is valuable.',
                'tips' => ['Share your experience with the community', 'TERRApp will connect you with other growers', 'Your feedback helps us improve the app']
            ]
        ]
    ];

    // Obtener datos del perfil o usar default
    $langProfiles = $profiles[$baseLang] ?? $profiles['es'];
    $profileData = $langProfiles[$profile] ?? $langProfiles['Curioso/a Verde'];

    $subjects = [
        'es' => "Tu resultado del quiz TERRApp: {$profileData['emoji']} {$profile}",
        'pt' => "Seu resultado do quiz TERRApp: {$profileData['emoji']} {$profile}",
        'en' => "Your TERRApp quiz result: {$profileData['emoji']} {$profile}",
        'fr' => "Votre résultat du quiz TERRApp: {$profileData['emoji']} {$profile}",
        'nl' => "Uw TERRApp-quizresultaat: {$profileData['emoji']} {$profile}"
    ];

    $tipsHtml = implode('', array_map(fn($tip) => "<li style=\"margin-bottom: 8px;\">✅ {$tip}</li>", $profileData['tips']));
    $tipsText = implode("\n", array_map(fn($tip) => "✅ {$tip}", $profileData['tips']));

    $texts = [
        'es' => [
            'subject' => $subjects['es'],
            'text' => "¡Hola {$nombre}!\n\nCompletaste el quiz de TERRApp y tu perfil es: {$profileData['emoji']} {$profile}\n\n{$profileData['desc']}\n\nTips para vos:\n{$tipsText}\n\n¡Te avisaremos cuando TERRApp esté disponible!\n\n🌱 El equipo de TERRApp",
            'html' => getQuizResultHtmlTemplate($nombre, $profile, $profileData, $tipsHtml, 'es')
        ],
        'pt' => [
            'subject' => $subjects['pt'],
            'text' => "Olá {$nombre}!\n\nVocê completou o quiz do TERRApp e seu perfil é: {$profileData['emoji']} {$profile}\n\n{$profileData['desc']}\n\nDicas para você:\n{$tipsText}\n\nAvisaremos quando o TERRApp estiver disponível!\n\n🌱 A equipe TERRApp",
            'html' => getQuizResultHtmlTemplate($nombre, $profile, $profileData, $tipsHtml, 'pt')
        ],
        'en' => [
            'subject' => $subjects['en'],
            'text' => "Hello {$nombre}!\n\nYou completed the TERRApp quiz and your profile is: {$profileData['emoji']} {$profile}\n\n{$profileData['desc']}\n\nTips for you:\n{$tipsText}\n\nWe'll let you know when TERRApp is available!\n\n🌱 The TERRApp Team",
            'html' => getQuizResultHtmlTemplate($nombre, $profile, $profileData, $tipsHtml, 'en')
        ]
    ];

    return $texts[$baseLang] ?? $texts['es'];
}

/**
 * Template HTML del email de resultado del quiz
 */
function getQuizResultHtmlTemplate(string $nombre, string $profile, array $profileData, string $tipsHtml, string $lang): string {
    $texts = [
        'es' => [
            'greeting' => "¡Hola {$nombre}!",
            'completed' => "Completaste el quiz de TERRApp",
            'your_profile' => "Tu perfil huertero",
            'tips_title' => "Tips personalizados para vos",
            'coming_soon' => "¡Te avisaremos cuando TERRApp esté disponible!",
            'team' => "El equipo de TERRApp"
        ],
        'pt' => [
            'greeting' => "Olá {$nombre}!",
            'completed' => "Você completou o quiz do TERRApp",
            'your_profile' => "Seu perfil de horticultor",
            'tips_title' => "Dicas personalizadas para você",
            'coming_soon' => "Avisaremos quando o TERRApp estiver disponível!",
            'team' => "A equipe TERRApp"
        ],
        'en' => [
            'greeting' => "Hello {$nombre}!",
            'completed' => "You completed the TERRApp quiz",
            'your_profile' => "Your gardener profile",
            'tips_title' => "Personalized tips for you",
            'coming_soon' => "We'll let you know when TERRApp is available!",
            'team' => "The TERRApp Team"
        ]
    ];

    $t = $texts[$lang] ?? $texts['es'];

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f9f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f9f4; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3d9268, #2d7553); padding: 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 700;">TERRApp</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">{$t['completed']}</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1d3e30; margin: 0 0 20px 0; font-size: 24px;">{$t['greeting']}</h2>

                            <!-- Profile Card -->
                            <div style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); border-radius: 12px; padding: 24px; margin: 20px 0; text-align: center;">
                                <p style="color: #5a3f2b; font-size: 14px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 1px;">{$t['your_profile']}</p>
                                <p style="font-size: 48px; margin: 0;">{$profileData['emoji']}</p>
                                <h3 style="color: #2d7553; font-size: 24px; margin: 10px 0 5px 0; font-weight: 700;">{$profile}</h3>
                                <p style="color: #5a3f2b; font-size: 14px; margin: 0;">{$profileData['desc']}</p>
                            </div>

                            <!-- Tips -->
                            <h3 style="color: #1d3e30; font-size: 18px; margin: 30px 0 15px 0;">{$t['tips_title']}</h3>
                            <ul style="color: #5a3f2b; font-size: 15px; line-height: 1.6; padding-left: 0; list-style: none;">
                                {$tipsHtml}
                            </ul>

                            <p style="color: #3d9268; font-size: 16px; font-weight: 600; margin: 30px 0 20px 0; text-align: center;">{$t['coming_soon']}</p>

                            <p style="color: #8b6442; font-size: 14px; margin: 30px 0 0 0; text-align: center;">
                                🌱 {$t['team']}
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1d3e30; padding: 20px; text-align: center;">
                            <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0;">
                                © 2026 TERRApp - Una solución de VERUMax, impulsada por la Cooperativa de Trabajo Liberté Ltda.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Envía email de agradecimiento por sugerencia via SendGrid
 */
function sendSuggestionThankYouEmail(string $email, string $nombre, string $lang, string $suggestion): bool {
    if (empty(SENDGRID_API_KEY)) {
        logError('SendGrid API key not configured');
        return false;
    }

    $nombreDisplay = $nombre ?: 'amigo/a huertero/a';
    $content = getSuggestionThankYouContent($lang, $nombreDisplay, $suggestion);

    $payload = [
        'personalizations' => [[
            'to' => [['email' => $email, 'name' => $nombre]],
            'subject' => $content['subject']
        ]],
        'from' => [
            'email' => SENDGRID_FROM_EMAIL,
            'name' => SENDGRID_FROM_NAME
        ],
        'content' => [
            ['type' => 'text/plain', 'value' => $content['text']],
            ['type' => 'text/html', 'value' => $content['html']]
        ],
        'tracking_settings' => [
            'click_tracking' => ['enable' => true],
            'open_tracking' => ['enable' => true]
        ]
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 202) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO email_logs (email_to, email_type, subject, status, sent_at)
                VALUES (?, 'suggestion_thanks', ?, 'sent', NOW())
            ");
            $stmt->execute([$email, $content['subject']]);
        } catch (Exception $e) {
            logError('Email log error', ['error' => $e->getMessage()]);
        }
        return true;
    }

    logError('SendGrid error (suggestion)', ['code' => $httpCode, 'response' => $response]);
    return false;
}

/**
 * Contenido del email de agradecimiento por sugerencia
 */
function getSuggestionThankYouContent(string $lang, string $nombre, string $suggestion): array {
    $baseLang = substr($lang, 0, 2);

    // Limitar longitud de la sugerencia para mostrar
    $suggestionPreview = mb_strlen($suggestion) > 100 ? mb_substr($suggestion, 0, 100) . '...' : $suggestion;

    $contents = [
        'es' => [
            'subject' => '¡Gracias por tu sugerencia para TERRApp! 💚',
            'text' => "¡Hola {$nombre}!\n\nRecibimos tu sugerencia:\n\"{$suggestionPreview}\"\n\n¡Muchas gracias por tomarte el tiempo de escribirnos! Tu feedback es muy valioso para construir una app que realmente sirva a la comunidad huerterea.\n\nEstamos revisando todas las sugerencias y las tendremos muy en cuenta.\n\n🌱 El equipo de TERRApp",
            'html' => getSuggestionThankYouHtmlTemplate($nombre, $suggestionPreview, 'es')
        ],
        'pt' => [
            'subject' => 'Obrigado pela sua sugestão para TERRApp! 💚',
            'text' => "Olá {$nombre}!\n\nRecebemos sua sugestão:\n\"{$suggestionPreview}\"\n\nMuito obrigado por dedicar seu tempo para nos escrever! Seu feedback é muito valioso para construir um app que realmente sirva à comunidade de horticultores.\n\nEstamos revisando todas as sugestões e as levaremos muito em conta.\n\n🌱 A equipe TERRApp",
            'html' => getSuggestionThankYouHtmlTemplate($nombre, $suggestionPreview, 'pt')
        ],
        'en' => [
            'subject' => 'Thank you for your TERRApp suggestion! 💚',
            'text' => "Hello {$nombre}!\n\nWe received your suggestion:\n\"{$suggestionPreview}\"\n\nThank you so much for taking the time to write to us! Your feedback is very valuable to build an app that truly serves the gardening community.\n\nWe are reviewing all suggestions and will take them into account.\n\n🌱 The TERRApp Team",
            'html' => getSuggestionThankYouHtmlTemplate($nombre, $suggestionPreview, 'en')
        ]
    ];

    return $contents[$baseLang] ?? $contents['es'];
}

/**
 * Template HTML del email de agradecimiento por sugerencia
 */
function getSuggestionThankYouHtmlTemplate(string $nombre, string $suggestion, string $lang): string {
    $texts = [
        'es' => [
            'greeting' => "¡Hola {$nombre}!",
            'thanks' => "¡Gracias por tu sugerencia!",
            'received' => "Recibimos tu mensaje:",
            'body' => "Tu feedback es muy valioso para construir una app que realmente sirva a la comunidad huerterea. Estamos revisando todas las sugerencias y las tendremos muy en cuenta.",
            'team' => "El equipo de TERRApp"
        ],
        'pt' => [
            'greeting' => "Olá {$nombre}!",
            'thanks' => "Obrigado pela sua sugestão!",
            'received' => "Recebemos sua mensagem:",
            'body' => "Seu feedback é muito valioso para construir um app que realmente sirva à comunidade de horticultores. Estamos revisando todas as sugestões e as levaremos muito em conta.",
            'team' => "A equipe TERRApp"
        ],
        'en' => [
            'greeting' => "Hello {$nombre}!",
            'thanks' => "Thank you for your suggestion!",
            'received' => "We received your message:",
            'body' => "Your feedback is very valuable to build an app that truly serves the gardening community. We are reviewing all suggestions and will take them into account.",
            'team' => "The TERRApp Team"
        ]
    ];

    $t = $texts[$lang] ?? $texts['es'];

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f9f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f9f4; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3d9268, #2d7553); padding: 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 700;">TERRApp</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">💚 {$t['thanks']}</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1d3e30; margin: 0 0 20px 0; font-size: 24px;">{$t['greeting']}</h2>

                            <p style="color: #5a3f2b; font-size: 14px; margin: 0 0 10px 0;">{$t['received']}</p>

                            <!-- Suggestion Box -->
                            <div style="background-color: #f5f5f5; border-left: 4px solid #3d9268; border-radius: 4px; padding: 16px; margin: 0 0 24px 0;">
                                <p style="color: #5a3f2b; font-size: 15px; font-style: italic; margin: 0;">"{$suggestion}"</p>
                            </div>

                            <p style="color: #5a3f2b; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">{$t['body']}</p>

                            <p style="color: #8b6442; font-size: 14px; margin: 30px 0 0 0;">
                                🌱 {$t['team']}
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1d3e30; padding: 20px; text-align: center;">
                            <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0;">
                                © 2026 TERRApp - Una solución de VERUMax, impulsada por la Cooperativa de Trabajo Liberté Ltda.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
