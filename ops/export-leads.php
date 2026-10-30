<?php
require getenv('HOME').'/zakondemchenko.ru/public_html/includes/config.php';require getenv('HOME').'/zakondemchenko.ru/public_html/includes/db.php';
$p=get_db();$rows=$p->query('SELECT q.id,q.created_at,q.ai_answer IS NOT NULL AS answered,q.notified,o.payload_json,o.sent_at FROM questions q LEFT JOIN legal_crm_relay_outbox o ON o.external_id=CONCAT("legal-form:",q.id) WHERE q.created_at>="2026-10-30 00:00:00" ORDER BY q.id')->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as &$r){$d=json_decode($r['payload_json']??'{}',true);$r['control']=in_array((int)$r['id'], [23,24,25,26,27,28], true);$r['metadata']=$d['lead_metadata']??[];unset($r['payload_json']);}
echo json_encode(['leads'=>$rows],JSON_UNESCAPED_UNICODE),PHP_EOL;
