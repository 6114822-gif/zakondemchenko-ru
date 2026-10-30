<?php
require getenv('HOME').'/zakondemchenko.ru/public_html/api/legal_payment_common.php';
if (!is_file(LEGAL_ORDERS_DB)) throw new RuntimeException('Missing order database');
$p=new PDO('sqlite:'.LEGAL_ORDERS_DB);$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec('PRAGMA query_only=ON');
$rows=$p->query('SELECT id,public_id,status,service_slug,amount,is_test,created_at,paid_at,source FROM legal_orders ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as &$r){$r['control']=in_array((int)$r['id'], [3,4,5,6], true); $r['crm_external_id']='legal-order:'.$r['public_id'];unset($r['public_id']);}
echo json_encode(['captured_at'=>gmdate('c'),'orders'=>$rows],JSON_UNESCAPED_UNICODE),PHP_EOL;
