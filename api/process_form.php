<?php
/**
 * Обработчик формы "Задать вопрос".
 * Принимает JSON или обычный POST, валидирует, сохраняет в БД,
 * уведомляет админа в Telegram и пытается автоматически ответить пользователю.
 *
 * Ответ фронтенду — всегда JSON:
 * {
 *   "success": true,
 *   "autoReply": "sent" | "telegram_not_started" | "vk_not_allowed" | "skipped"
 * }
 * или
 * { "success": false, "errors": { "name": "...", ... } }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Access-Control-Allow-Origin: ' . SITE_URL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'errors' => ['general' => 'Метод не поддерживается']], 405);
}

// Поддерживаем и application/json (fetch из script.js), и обычный form-data
$raw = file_get_contents('php://input');
$input = [];
if ($raw !== false && $raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $input = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
}

$name = clean_str((string) ($input['name'] ?? ''), 100);
$contactRaw = clean_str((string) ($input['contact'] ?? ''), 255);
$question = clean_str((string) ($input['question'] ?? ''), 2000);
// honeypot-поле: должно быть пустым, боты обычно заполняют все поля
$honeypot = (string) ($input['website'] ?? '');

$errors = [];

if (mb_strlen($name) < 2) {
    $errors['name'] = 'Укажите имя (минимум 2 символа)';
}

$contact = detect_contact($contactRaw);
if ($contact['type'] === 'unknown' || $contactRaw === '') {
    $errors['contact'] = 'Укажите email (например, ivan@mail.ru) или Telegram-ник (например, @ivanov)';
}

if (mb_strlen($question) < 10) {
    $errors['question'] = 'Опишите вопрос подробнее (минимум 10 символов)';
}

if ($honeypot !== '') {
    // Похоже на бота — молча делаем вид, что всё прошло успешно
    json_response(['success' => true, 'autoReply' => 'skipped']);
}

if (!empty($errors)) {
    json_response(['success' => false, 'errors' => $errors], 422);
}

try {
    $pdo = get_db();
} catch (Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    json_response(['success' => false, 'errors' => ['general' => 'Техническая ошибка. Попробуйте написать нам напрямую в Telegram или ВК.']], 500);
}

$ip = client_ip();
if (is_rate_limited($pdo, $ip, 45)) {
    json_response(['success' => false, 'errors' => ['general' => 'Вы уже отправляли вопрос только что. Подождите немного.']], 429);
}

$stmt = $pdo->prepare(
    'INSERT INTO questions (name, contact_type, contact_value, question, ip_address, user_agent)
     VALUES (:name, :contact_type, :contact_value, :question, :ip, :ua)'
);
$stmt->execute([
    'name'         => $name,
    'contact_type' => $contact['type'],
    'contact_value'=> $contact['value'],
    'question'     => $question,
    'ip'           => $ip,
    'ua'           => clean_str((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255),
]);
$questionId = (int) $pdo->lastInsertId();

touch_rate_limit($pdo, $ip);

// --- Уведомление админу в Telegram ---
$adminText = "🆕 Новый вопрос с сайта (#{$questionId})\n\n"
    . "Имя: {$name}\n"
    . "Контакт: {$contact['value']} ({$contact['type']})\n\n"
    . "Вопрос:\n{$question}";

$adminNotify = telegram_send(TELEGRAM_ADMIN_CHAT_ID, $adminText);
if (!$adminNotify['ok']) {
    error_log('Не удалось уведомить админа: ' . ($adminNotify['error'] ?? 'unknown'));
}

// --- Автоответ пользователю ---
$autoReplyText = "Здравствуйте, {$name}! Ваш вопрос принят в работу. "
    . "Мы ответим вам в личные сообщения в ближайшее время. "
    . "А пока рекомендуем подписаться на наши ресурсы, чтобы не пропустить полезные разборы: "
    . "📱 Telegram: " . TELEGRAM_CHANNEL_URL . " | 🌐 ВКонтакте: " . VK_GROUP_URL;

$autoReplyStatus = 'skipped';

if ($contact['type'] === 'telegram') {
    $username = ltrim($contact['value'], '@');
    $stmt = $pdo->prepare('SELECT chat_id FROM telegram_subscribers WHERE telegram_username = :u');
    $stmt->execute(['u' => $username]);
    $subscriber = $stmt->fetch();

    if ($subscriber) {
        $result = telegram_send((string) $subscriber['chat_id'], $autoReplyText);
        $autoReplyStatus = $result['ok'] ? 'sent' : 'telegram_not_started';
    } else {
        // Пользователь ни разу не нажимал /start у бота — Bot API физически
        // не может написать ему первым. Это и есть ограничение, описанное в ТЗ.
        $autoReplyStatus = 'telegram_not_started';
    }
} elseif ($contact['type'] === 'vk') {
    $screenName = basename(parse_url($contact['value'], PHP_URL_PATH) ?? '');
    $result = vk_send_message($screenName, $autoReplyText);
    $autoReplyStatus = $result['ok'] ? 'sent' : 'vk_not_allowed';
} elseif ($contact['type'] === 'email') {
    // Email не требует предварительного "разрешения" от получателя, в отличие
    // от Telegram/VK — письмо просто отправляется сразу.
    $result = send_email($contact['value'], $name, $question);
    $autoReplyStatus = $result['ok'] ? 'sent' : 'email_failed';
}

$stmt = $pdo->prepare('UPDATE questions SET notified = :n, notify_error = :err WHERE id = :id');
$stmt->execute([
    'n'   => $autoReplyStatus === 'sent' ? 1 : 0,
    'err' => $autoReplyStatus === 'sent' ? null : $autoReplyStatus,
    'id'  => $questionId,
]);

json_response(['success' => true, 'autoReply' => $autoReplyStatus]);
