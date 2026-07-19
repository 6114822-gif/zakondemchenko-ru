<?php
/**
 * Отдаёт последние посты группы ВК (только текст) для блока "Лента VK".
 * Кэширует ответ VK API в файл на 15 минут, чтобы не дёргать API на каждый
 * визит и не упереться в лимиты ВК.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

const CACHE_TTL = 900; // 15 минут
const POSTS_COUNT = 4;
const EXCERPT_LENGTH = 320;

$cacheFile = __DIR__ . '/../data/vk_cache.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    readfile($cacheFile);
    exit;
}

$url = 'https://api.vk.com/method/wall.get'
    . '?owner_id=-' . VK_GROUP_ID
    . '&count=' . POSTS_COUNT
    . '&filter=owner'
    . '&access_token=' . urlencode(VK_COMMUNITY_TOKEN)
    . '&v=' . VK_API_VERSION;

$response = http_get($url);
$decoded = json_decode($response ?? '', true);

if (empty($decoded['response']['items'])) {
    // Не удалось получить свежие данные — отдаём старый кэш, если он есть,
    // иначе пустой список (фронтенд покажет ссылку на группу вместо ленты).
    if (is_file($cacheFile)) {
        readfile($cacheFile);
        exit;
    }
    echo json_encode(['posts' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$posts = [];
foreach ($decoded['response']['items'] as $item) {
    $text = trim((string) ($item['text'] ?? ''));
    if ($text === '') {
        continue; // пропускаем репосты картинок/видео без текста
    }

    $isTruncated = mb_strlen($text) > EXCERPT_LENGTH;
    $excerpt = $isTruncated ? mb_substr($text, 0, EXCERPT_LENGTH) . '…' : $text;

    $posts[] = [
        'text'      => $excerpt,
        'truncated' => $isTruncated,
        'date'      => date('d.m.Y', (int) ($item['date'] ?? time())),
        'url'       => 'https://vk.com/prav_kompas?w=wall-' . VK_GROUP_ID . '_' . $item['id'],
    ];

    if (count($posts) >= POSTS_COUNT) {
        break;
    }
}

$payload = json_encode(['posts' => $posts], JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $payload, LOCK_EX);
echo $payload;
