<?php
// Backend regression test for api/process_form.php.
// Runs the real handler against an in-memory SQLite database (no MySQL in dev/CI) with a
// copy of the repo where config.php and db.php are replaced by test doubles.
// Telegram/VK/mail are not contacted: the test bot token is invalid and the network call fails fast.
// Usage: php tests/process_form_test.php
declare(strict_types=1);

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/zakon-backend-' . bin2hex(random_bytes(4));
mkdir($tmp . '/api', 0777, true);
mkdir($tmp . '/includes', 0777, true);
copy($root . '/api/process_form.php', $tmp . '/api/process_form.php');
copy($root . '/includes/functions.php', $tmp . '/includes/functions.php');
file_put_contents($tmp . '/includes/config.php', <<<'PHP'
<?php
define('TELEGRAM_BOT_TOKEN', 'test');
define('TELEGRAM_ADMIN_CHAT_ID', '1');
define('VK_COMMUNITY_TOKEN', 'test');
define('VK_API_VERSION', '5.199');
define('SITE_URL', 'https://example.test');
define('TELEGRAM_CHANNEL_URL', 'https://t.me/x');
define('VK_GROUP_URL', 'https://vk.com/x');
define('MAIL_FROM', 'noreply@example.test');
PHP);
file_put_contents($tmp . '/includes/db.php', <<<'PHP'
<?php
class TestPdo extends PDO {
    public function prepare(string $q, array $o = []): PDOStatement|false {
        $q = str_replace('ON DUPLICATE KEY UPDATE last_submit = NOW()', "ON CONFLICT(ip_address) DO UPDATE SET last_submit = datetime('now')", $q);
        $q = str_replace('NOW()', "datetime('now')", $q);
        return parent::prepare($q, $o);
    }
}
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new TestPdo('sqlite:' . __DIR__ . '/test.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE IF NOT EXISTS questions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, contact_type TEXT, contact_value TEXT, question TEXT, notified INT DEFAULT 0, notify_error TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS telegram_subscribers (id INTEGER PRIMARY KEY AUTOINCREMENT, telegram_username TEXT UNIQUE, chat_id INT, started_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS form_rate_limit (ip_address TEXT PRIMARY KEY, last_submit TEXT)');
    }
    return $pdo;
}
PHP);

$cmd = ['php', '-S', '127.0.0.1:8099', '-t', $tmp];
$proc = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($proc, $tmp) {
    proc_terminate($proc);
    exec('rm -rf ' . escapeshellarg($tmp));
});
usleep(500000);

function post(array $body, string $ip = '127.0.0.1'): array {
    $ch = curl_init('http://127.0.0.1:8099/api/process_form.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $out, true)];
}
function rowCount(string $tmp): int {
    if (!is_file($tmp . '/includes/test.sqlite')) return 0; // DB is only touched after validation passes
    $pdo = new PDO('sqlite:' . $tmp . '/includes/test.sqlite');
    return (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
}

$checks = 0;
function ok(bool $cond, string $msg): void { global $checks; if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } $checks++; }

$valid = ['name' => 'Тест Подрядчик', 'contact' => 'test@example.ru', 'question' => 'Нужна претензия: работы выполнены, заказчик не оплатил.'];

[$c, $r] = post($valid);
ok($c === 422 && isset($r['errors']['consent']) && rowCount($tmp) === 0, 'missing consent must be rejected and not stored');
[$c, $r] = post($valid + ['consent' => false]);
ok($c === 422 && isset($r['errors']['consent']) && rowCount($tmp) === 0, 'consent=false must be rejected');
[$c, $r] = post($valid + ['consent' => 'no']);
ok($c === 422 && rowCount($tmp) === 0, 'non-truthy consent string must be rejected');
[$c, $r] = post(['consent' => true, 'name' => 'A', 'contact' => '123', 'question' => 'x']);
ok($c === 422 && isset($r['errors']['name'], $r['errors']['contact'], $r['errors']['question']), 'field validation errors');
[$c, $r] = post($valid + ['consent' => true, 'website' => 'http://spam']);
ok($c === 200 && $r['autoReply'] === 'skipped' && rowCount($tmp) === 0, 'honeypot is silently dropped, nothing stored');
[$c, $r] = post($valid + ['consent' => true, 'source' => 'landing-podryad', 'service' => 'claim']);
ok($c === 200 && $r['success'] === true && rowCount($tmp) === 1, 'valid lead with consent is stored');
[$c, $r] = post($valid + ['consent' => true]);
ok($c === 429 && rowCount($tmp) === 1, 'second submit from same IP is rate limited');
$pdo = new PDO('sqlite:' . $tmp . '/includes/test.sqlite');
$row = $pdo->query('SELECT * FROM questions')->fetch(PDO::FETCH_ASSOC);
ok($row['contact_value'] === 'test@example.ru' && $row['contact_type'] === 'email', 'stored contact normalised');
$ch = curl_init('http://127.0.0.1:8099/api/process_form.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
curl_exec($ch);
ok(curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 405, 'GET is rejected with 405');
curl_close($ch);

echo "PASS: $checks backend checks (SQLite double; Telegram/VK/mail not contacted)\n";
