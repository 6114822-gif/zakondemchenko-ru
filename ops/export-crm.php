<?php
require getenv('HOME').'/demchenkos.ru/public_html/includes/config.php';
$dsn=defined('LEAD_DESK_DSN')?trim((string)LEAD_DESK_DSN):'';
if ($dsn==='') {$dir=defined('STORE_DATA_DIR')&&STORE_DATA_DIR!==''?STORE_DATA_DIR:getenv('HOME').'/demchenkos.ru/private_store';$dsn='sqlite:'.$dir.'/orders.sqlite3';}
if (!str_starts_with($dsn,'sqlite:') || !is_file(substr($dsn,7))) throw new RuntimeException('Read-only export requires existing SQLite CRM');
$p=new PDO($dsn);$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec('PRAGMA query_only=ON');
$rows=$p->query("SELECT id,external_id,source,status,amount,paid,created_at,updated_at,last_notification_at FROM crm_leads WHERE external_id LIKE 'legal-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);echo json_encode(['crm'=>$rows],JSON_UNESCAPED_UNICODE),PHP_EOL;
