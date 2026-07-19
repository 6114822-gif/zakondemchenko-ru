<?php
/**
 * Простая админ-панель: редактирование data/news.json (блоки "Для бизнеса" / "Для граждан").
 * Без БД — специально, чтобы обновление новостей было максимально простым и быстрым.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$newsFile = __DIR__ . '/../data/news.json';
$message = '';

function load_news(string $file): array
{
    if (!is_file($file)) {
        return ['business' => [], 'citizens' => []];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return [
        'business' => $data['business'] ?? [],
        'citizens' => $data['citizens'] ?? [],
    ];
}

function clean_field(string $value, int $max): string
{
    return mb_substr(trim($value), 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Сессия устарела, попробуйте ещё раз.';
    } else {
        $result = ['business' => [], 'citizens' => []];

        foreach (['business', 'citizens'] as $category) {
            $items = $_POST[$category] ?? [];
            foreach ($items as $item) {
                $title = clean_field((string) ($item['title'] ?? ''), 200);
                if ($title === '') {
                    continue; // пустые строки пропускаем — так работает "удаление"
                }
                $result[$category][] = [
                    'title'   => $title,
                    'excerpt' => clean_field((string) ($item['excerpt'] ?? ''), 400),
                    'link'    => clean_field((string) ($item['link'] ?? ''), 300) ?: TELEGRAM_CHANNEL_URL,
                    'date'    => clean_field((string) ($item['date'] ?? ''), 20) ?: date('Y-m-d'),
                ];
            }
        }

        file_put_contents($newsFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        $message = 'Сохранено.';
    }
}

$news = load_news($newsFile);

function render_rows(array $items, string $category): void
{
    if (empty($items)) {
        $items = [['title' => '', 'excerpt' => '', 'link' => '', 'date' => '']];
    }
    foreach ($items as $i => $item) {
        $t = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $e = htmlspecialchars($item['excerpt'] ?? '', ENT_QUOTES, 'UTF-8');
        $l = htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($item['date'] ?? '', ENT_QUOTES, 'UTF-8');
        echo "<div class=\"row\">"
            . "<input name=\"{$category}[{$i}][title]\" value=\"{$t}\" placeholder=\"Заголовок\">"
            . "<textarea name=\"{$category}[{$i}][excerpt]\" placeholder=\"Краткое описание\">{$e}</textarea>"
            . "<input name=\"{$category}[{$i}][link]\" value=\"{$l}\" placeholder=\"Ссылка (Telegram/VK пост)\">"
            . "<input type=\"date\" name=\"{$category}[{$i}][date]\" value=\"{$d}\">"
            . "<button type=\"button\" class=\"del\" onclick=\"this.parentElement.remove()\">Удалить</button>"
            . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор новостей — Админ-панель</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:2rem 1rem 4rem}
        .wrap{max-width:900px;margin:auto}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}
        h1{font-size:1.5rem}
        a.logout{color:#64748b;text-decoration:none;font-size:.9rem}
        h2{margin-top:2.5rem;border-bottom:2px solid #c9a227;padding-bottom:.5rem}
        .row{display:grid;grid-template-columns:1fr;gap:.5rem;background:#fff;padding:1rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1rem}
        .row input,.row textarea{width:100%;padding:.6rem;border:1px solid #cbd5e1;border-radius:6px;font-family:inherit;box-sizing:border-box}
        .row textarea{min-height:60px;resize:vertical}
        .del{justify-self:start;background:#fee2e2;color:#b91c1c;border:none;padding:.4rem .8rem;border-radius:6px;cursor:pointer}
        .add{background:#e2e8f0;border:none;padding:.6rem 1rem;border-radius:6px;cursor:pointer;margin-top:.5rem}
        .save{margin-top:2rem;background:#0f172a;color:#fff;border:none;padding:.9rem 2rem;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem}
        .msg{background:#dcfce7;color:#166534;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.5rem}
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Редактор новостей</h1>
        <a class="logout" href="logout.php">Выйти</a>
    </header>

    <?php if ($message !== ''): ?><div class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <h2>Важно для бизнеса</h2>
        <div id="business-rows"><?php render_rows($news['business'], 'business'); ?></div>
        <button type="button" class="add" data-cat="business">+ Добавить новость</button>

        <h2>Важно для граждан</h2>
        <div id="citizens-rows"><?php render_rows($news['citizens'], 'citizens'); ?></div>
        <button type="button" class="add" data-cat="citizens">+ Добавить новость</button>

        <br>
        <button type="submit" class="save">Сохранить</button>
    </form>
</div>
<script>
document.querySelectorAll('.add').forEach(btn => {
    btn.addEventListener('click', () => {
        const cat = btn.dataset.cat;
        const container = document.getElementById(cat + '-rows');
        const i = container.children.length;
        const row = document.createElement('div');
        row.className = 'row';
        row.innerHTML = `
            <input name="${cat}[${i}][title]" placeholder="Заголовок">
            <textarea name="${cat}[${i}][excerpt]" placeholder="Краткое описание"></textarea>
            <input name="${cat}[${i}][link]" placeholder="Ссылка (Telegram/VK пост)">
            <input type="date" name="${cat}[${i}][date]">
            <button type="button" class="del" onclick="this.parentElement.remove()">Удалить</button>
        `;
        container.appendChild(row);
    });
});
</script>
</body>
</html>
