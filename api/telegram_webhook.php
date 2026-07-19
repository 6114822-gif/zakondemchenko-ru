<?php
/**
 * Webhook для Telegram-бота @PravKompas_bot.
 *
 * Два независимых сценария:
 *
 * 1) ЛИЧНЫЕ СООБЩЕНИЯ (chat.type === 'private'): Telegram Bot API не позволяет
 *    боту написать пользователю первым, пока пользователь сам не написал боту
 *    (обычно — не нажал /start). Здесь мы запоминаем пару (username -> chat_id)
 *    в таблице telegram_subscribers, чтобы process_form.php мог потом слать
 *    автоответ по нику, указанному в форме на сайте.
 *
 * 2) ГРУППА @pravkomp (chat.type === 'group'/'supergroup'): бот читает вопросы
 *    участников (после отключения privacy mode в @BotFather) и автоматически
 *    отвечает через LLM, с обязательной пометкой, что это не индивидуальная
 *    консультация.
 *
 * НАСТРОЙКА (один раз после деплоя):
 * curl "https://api.telegram.org/bot<ТОКЕН>/setWebhook?url=https://zakondemchenko.ru/api/telegram_webhook.php&secret_token=<TELEGRAM_WEBHOOK_SECRET>"
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

// Проверяем секретный токен, который Telegram присылает в заголовке
$incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!hash_equals(TELEGRAM_WEBHOOK_SECRET, $incomingSecret)) {
    http_response_code(403);
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '', true);

if (!is_array($update)) {
    http_response_code(200);
    exit;
}

$message = $update['message'] ?? null;

if ($message && isset($message['chat']['id'], $message['chat']['type'])) {
    $chatType = (string) $message['chat']['type'];
    $chatId = (int) $message['chat']['id'];
    $text = trim((string) ($message['text'] ?? ''));

    try {
        if ($chatType === 'private') {
            handle_private_message($message, $chatId, $text);
        } elseif (in_array($chatType, ['group', 'supergroup'], true)) {
            handle_group_message($message, $chatId, $text);
        }
    } catch (Throwable $e) {
        error_log('telegram_webhook error: ' . $e->getMessage());
    }
}

http_response_code(200);

/**
 * Личный чат с ботом: запоминаем chat_id по username, чтобы форма на сайте
 * могла присылать автоответ.
 */
function handle_private_message(array $message, int $chatId, string $text): void
{
    $username = $message['from']['username'] ?? null;
    if (!$username) {
        return;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO telegram_subscribers (telegram_username, chat_id) VALUES (:u, :c)
         ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id)'
    );
    $stmt->execute(['u' => $username, 'c' => $chatId]);

    if (str_starts_with($text, '/start')) {
        telegram_send(
            (string) $chatId,
            "Здравствуйте! Теперь я смогу присылать вам ответы на вопросы, заданные через сайт zakondemchenko.ru.\n\n"
            . "Просто оставьте свой Telegram-ник в форме на сайте, и мы свяжемся с вами здесь."
        );
    }
}

/**
 * Группа @pravkomp: если сообщение похоже на вопрос — генерируем ответ через
 * LLM и публикуем его в группе с пометкой, что это не индивидуальная
 * консультация.
 */
function handle_group_message(array $message, int $chatId, string $text): void
{
    $chatUsername = $message['chat']['username'] ?? '';
    if (strcasecmp($chatUsername, TELEGRAM_GROUP_USERNAME) !== 0) {
        return; // отвечаем только в целевой группе, не в любой, куда добавят бота
    }

    if (!empty($message['from']['is_bot'])) {
        return; // не отвечаем на сообщения других ботов (защита от петли)
    }

    if (!looks_like_question($text)) {
        return;
    }

    $answer = generate_legal_answer($text);
    if ($answer === null) {
        return; // тихо пропускаем, если LLM недоступна — не шлём ошибку в публичный чат
    }

    telegram_send_reply($chatId, (int) $message['message_id'], $answer);
}

/**
 * Простая эвристика "это похоже на вопрос": есть "?", разумная длина,
 * не команда.
 */
function looks_like_question(string $text): bool
{
    if ($text === '' || str_starts_with($text, '/')) {
        return false;
    }
    if (mb_strlen($text) < 12 || mb_strlen($text) > 1500) {
        return false;
    }
    return str_contains($text, '?');
}

/**
 * Генерация ответа через OpenRouter (OpenAI-совместимый API).
 * Возвращает null при любой ошибке — вызывающий код тогда просто промолчит.
 */
function generate_legal_answer(string $question): ?string
{
    $systemPrompt = 'Ты — ассистент юриста Сергея Демченко (20 лет практики, сайт zakondemchenko.ru, '
        . 'Telegram-группа @pravkomp), помогаешь коротко отвечать на юридические вопросы участников группы. '
        . 'Отвечай по-русски, по существу, ссылайся на конкретные нормы права (номер статьи и закон), если это уместно. '
        . 'Не давай советов, которые могут навредить (например, как уклониться от законных обязательств). '
        . 'Пиши кратко — 3-6 предложений, без длинных вступлений. '
        . 'В конце ответа ВСЕГДА добавляй отдельной строкой: '
        . '"Это общая информация, а не индивидуальная консультация по вашей ситуации. '
        . 'Для разбора именно вашего случая — напишите @demlider или оставьте вопрос на zakondemchenko.ru".';

    $payload = [
        'model'    => OPENROUTER_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question],
        ],
        'max_tokens' => 500,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT    => 25,
    ]);
    $result = curl_exec($ch);
    $ok = curl_errno($ch) === 0;
    curl_close($ch);

    if (!$ok) {
        return null;
    }

    $decoded = json_decode((string) $result, true);
    $answer = $decoded['choices'][0]['message']['content'] ?? null;

    return is_string($answer) && trim($answer) !== '' ? trim($answer) : null;
}

function telegram_send_reply(int $chatId, int $replyToMessageId, string $text): void
{
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    http_post_json($url, [
        'chat_id'             => $chatId,
        'text'                => $text,
        'reply_to_message_id' => $replyToMessageId,
    ]);
}
