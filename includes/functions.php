<?php
/**
 * Общие хелперы: санитизация ввода, отправка в Telegram/VK, простой rate-limit.
 */

declare(strict_types=1);

/**
 * Обрезает, убирает управляющие символы и экранирует HTML.
 * Используем на выходе (перед выводом) и как доп. подстраховку при сохранении.
 */
function clean_str(string $value, int $maxLength = 2000): string
{
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength);
    } else {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Определяет тип контакта: email, telegram (@username) или vk (ссылка/id).
 * Возвращает ['type' => 'email'|'telegram'|'vk'|'unknown', 'value' => нормализованное значение].
 */
function detect_contact(string $raw): array
{
    $raw = trim($raw);

    if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        return ['type' => 'email', 'value' => $raw];
    }

    // Telegram: @username или t.me/username
    if (preg_match('/^@?([a-zA-Z0-9_]{5,32})$/', $raw, $m)) {
        return ['type' => 'telegram', 'value' => '@' . $m[1]];
    }
    if (preg_match('#t\.me/([a-zA-Z0-9_]{5,32})#i', $raw, $m)) {
        return ['type' => 'telegram', 'value' => '@' . $m[1]];
    }

    // VK: vk.com/xxx или ссылка на профиль (поле теперь не предлагает VK явно,
    // но если человек всё равно вставит ссылку — распознаём и это)
    if (preg_match('#vk\.com/([a-zA-Z0-9_.]{2,64})#i', $raw, $m)) {
        return ['type' => 'vk', 'value' => 'https://vk.com/' . $m[1]];
    }

    return ['type' => 'unknown', 'value' => $raw];
}

/**
 * Простой rate-limit: не чаще 1 заявки с одного IP раз в N секунд.
 */
function is_rate_limited(PDO $pdo, string $ip, int $seconds = 60): bool
{
    $stmt = $pdo->prepare('SELECT last_submit FROM form_rate_limit WHERE ip_address = :ip');
    $stmt->execute(['ip' => $ip]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $last = strtotime($row['last_submit']);
    return $last !== false && (time() - $last) < $seconds;
}

function touch_rate_limit(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO form_rate_limit (ip_address, last_submit) VALUES (:ip, NOW())
         ON DUPLICATE KEY UPDATE last_submit = NOW()'
    );
    $stmt->execute(['ip' => $ip]);
}

/**
 * Отправка автоответа на email через встроенный mail() (sendmail на Beget).
 * В конце письма — подпись со ссылками на сайт, Telegram и ВК.
 */
function send_email(string $to, string $name, string $question): array
{
    $subject = 'Ваш вопрос принят — zakondemchenko.ru';

    $body = "Здравствуйте, {$name}!\n\n"
        . "Ваш вопрос принят в работу. Мы ответим вам на этот email в ближайшее время.\n\n"
        . "Ваш вопрос:\n{$question}\n\n"
        . "—\n"
        . "Сергей Демченко\n"
        . "Юрист в кармане — бесплатные юридические консультации 24/7\n"
        . SITE_URL . "\n"
        . "Telegram: " . TELEGRAM_CHANNEL_URL . "\n"
        . "ВКонтакте: " . VK_GROUP_URL . "\n";

    $fromName = '=?UTF-8?B?' . base64_encode('Сергей Демченко') . '?=';
    $headers = "From: {$fromName} <" . MAIL_FROM . ">\r\n"
        . "Reply-To: " . MAIL_FROM . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $ok = mail($to, $encodedSubject, $body, $headers);

    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'mail_failed'];
}

/**
 * Отправка сообщения через Telegram Bot API.
 * Возвращает true при успехе, false при ошибке (например, бот не может писать пользователю).
 */
function telegram_send(string $chatId, string $text): array
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'disable_web_page_preview' => true,
    ];

    $result = http_post_json($url, $payload);

    if ($result === null) {
        return ['ok' => false, 'error' => 'network_error'];
    }

    $decoded = json_decode($result, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        return ['ok' => false, 'error' => $decoded['description'] ?? 'unknown_error'];
    }

    return ['ok' => true];
}

/**
 * Отправка сообщения пользователю ВК от имени сообщества.
 * Требует, чтобы пользователь ранее разрешил сообщения от сообщества
 * (иначе VK API вернёт ошибку 900/901/902).
 */
function vk_send_message(string $screenName, string $text): array
{
    // Получаем numeric user_id по screen_name
    $resolveUrl = 'https://api.vk.com/method/utils.resolveScreenName'
        . '?screen_name=' . urlencode($screenName)
        . '&access_token=' . urlencode(VK_COMMUNITY_TOKEN)
        . '&v=' . VK_API_VERSION;

    $resolveResult = http_get($resolveUrl);
    $resolved = json_decode($resolveResult ?? '', true);

    if (empty($resolved['response']['object_id']) || ($resolved['response']['type'] ?? '') !== 'user') {
        return ['ok' => false, 'error' => 'user_not_found'];
    }

    $userId = (int) $resolved['response']['object_id'];

    $sendUrl = 'https://api.vk.com/method/messages.send'
        . '?user_id=' . $userId
        . '&message=' . urlencode($text)
        . '&random_id=' . random_int(1, PHP_INT_MAX)
        . '&access_token=' . urlencode(VK_COMMUNITY_TOKEN)
        . '&v=' . VK_API_VERSION;

    $sendResult = http_get($sendUrl);
    $decoded = json_decode($sendResult ?? '', true);

    if (isset($decoded['error'])) {
        // Код 900/901/902 — пользователь не разрешил сообщения от сообщества
        return ['ok' => false, 'error' => $decoded['error']['error_msg'] ?? 'vk_error'];
    }

    return ['ok' => true];
}

function http_post_json(string $url, array $payload): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $error = curl_errno($ch);
    curl_close($ch);

    return $error === 0 ? ($result === false ? null : $result) : null;
}

function http_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $error = curl_errno($ch);
    curl_close($ch);

    return $error === 0 ? ($result === false ? null : $result) : null;
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
