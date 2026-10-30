<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$lock = fopen(__FILE__ . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
$root = dirname(__DIR__) . '/public_html';
require_once $root . '/includes/config.php';
require_once $root . '/includes/lead_desk.php';
$result = ['ran_at' => gmdate('c')];
try { $result['notifications'] = lead_desk_flush_outbox(20); }
catch (Throwable $e) { $result['error'] = get_class($e); }
file_put_contents(__FILE__ . '.status.json', json_encode($result), LOCK_EX);
echo json_encode($result) . "\n";
