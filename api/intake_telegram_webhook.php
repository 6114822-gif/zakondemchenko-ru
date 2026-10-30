<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$provided = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if (!defined('TELEGRAM_WEBHOOK_SECRET') || strlen((string) TELEGRAM_WEBHOOK_SECRET) < 16
    || !hash_equals((string) TELEGRAM_WEBHOOK_SECRET, $provided)) {
    http_response_code(403); exit;
}
$update = json_decode((string) file_get_contents('php://input'), true);
$message = is_array($update) ? ($update['message'] ?? null) : null;
if (!is_array($message) || ($message['chat']['type'] ?? '') !== 'private'
    || !empty($message['from']['is_bot'])) { http_response_code(200); exit; }
$chatId = (string) ($message['chat']['id'] ?? '');
$username = (string) ($message['from']['username'] ?? '');
$text = trim((string) ($message['text'] ?? ''));
if (!preg_match('/\A[1-9][0-9]*\z/D', $chatId)
    || !preg_match('/\A[A-Za-z0-9_]{5,32}\z/D', $username)) { http_response_code(200); exit; }

try {
    $pdo = get_db();
    $save = $pdo->prepare('INSERT INTO telegram_subscribers (telegram_username, chat_id) VALUES (:u, :c)
        ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id)');
    $save->execute(['u' => $username, 'c' => $chatId]);
    $reply = 'Для обсуждения юридической задачи и ответа юристу напишите лично Сергею: https://t.me/demlider';
    if (preg_match('/\A\/start(?:@[A-Za-z0-9_]+)?(?:\s+q_([1-9][0-9]*))?\z/D', $text, $match)) {
        $reply = 'Канал ответов подключён. Юрист сможет ответить на заявку с сайта. Для уточнений напишите лично Сергею: https://t.me/demlider';
        if (!empty($match[1])) {
            // A sequential question ID alone never grants access to an answer.
            $find = $pdo->prepare("SELECT ai_answer FROM questions WHERE id=:id AND contact_type='telegram'
                AND LOWER(contact_value)=:contact");
            $find->execute(['id' => (int) $match[1], 'contact' => '@' . strtolower($username)]);
            $question = $find->fetch();
            if (is_array($question)) {
                $answer = trim((string) ($question['ai_answer'] ?? ''));
                $reply = $answer !== ''
                    ? mb_substr($answer, 0, 3400) . "\n\nДля уточнений: https://t.me/demlider"
                    : 'Заявка №' . $match[1] . ' сохранена. Ответ ещё не готов. Для обсуждения задачи напишите Сергею: https://t.me/demlider';
            }
        }
    }
    $sent = telegram_send($chatId, $reply);
    if (empty($sent['ok'])) error_log('intake Telegram reply failed');
} catch (Throwable $error) {
    error_log('intake Telegram webhook failed class=' . get_class($error));
    http_response_code(503); exit;
}
http_response_code(200);
