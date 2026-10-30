"""Read-only report refresh through the existing SSH alias; no payments or messages."""
from pathlib import Path
import argparse,json,re,subprocess,os
p=argparse.ArgumentParser();p.add_argument('--output',type=Path,default=Path.home()/'Private/zakondemchenko/current-sales/report-data.json');p.add_argument('--canvas',type=Path);a=p.parse_args()
command='; '.join('/usr/local/php/cgi/8.3/bin/php ~/zakondemchenko.ru/private/'+n for n in ['export-orders.php','export-leads.php','export-crm.php'])
r=subprocess.run(['ssh','-o','BatchMode=yes','-o','ConnectTimeout=15','zakondemchenko-deploy',command],capture_output=True,text=True,check=True)
data={}
for line in r.stdout.splitlines():
 if line.startswith('{'):data.update(json.loads(line))
assert all(k in data for k in ['leads','orders','crm','captured_at']), 'Incomplete snapshot'
crm={x['external_id']:x for x in data['crm']};leads=[];orders=[]
for x in data['leads']:
 m=x['metadata'];c=crm.get('legal-form:'+str(x['id']),{});utm=m.get('attribution') or {};source=' / '.join(utm.get(k,'') for k in ['utm_source','utm_medium','utm_campaign'] if utm.get(k)) or 'посадочная; UTM не переданы'
 leads.append({'id':x['id'],'crm':c.get('id'),'source':source,'service':m.get('service') or 'не выбрана','notified':bool(c.get('last_notification_at')),'answer':('технический ответ доставлен' if x['id']==25 else 'сохранён; происхождение не подтверждено') if x['answered'] else 'нет','control':bool(x['control'])})
for x in data['orders']:
 c=crm.get(x['crm_external_id'],{});control=bool(x['control']) or x.get('source')=='production_activation_check'
 orders.append({'id':x['id'],'date':x['created_at'][:10],'amount':float(x['amount']),'status':x['status'],'crm':str(c['id'])+' — '+c['status'] if c else 'нет в текущей CRM','crmPaid':bool(c.get('paid')),'control':control,'test':bool(x['is_test'])})
result={'captured_at':data['captured_at'],'leads':leads,'orders':orders,'commercial_leads':sum(not x['control'] for x in leads),'paid_orders':sum(x['status']=='paid' and not x['test'] and not x['control'] for x in orders),'revenue_rub':sum(x['amount'] for x in orders if x['status']=='paid' and not x['test'] and not x['control'])}
a.output.parent.mkdir(parents=True,exist_ok=True,mode=0o700);a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2));a.output.chmod(0o600)
if a.canvas:
 text=a.canvas.read_text();assert text.count('// SNAPSHOT_START')==1 and text.count('// SNAPSHOT_END')==1
 replacement='// SNAPSHOT_START\nconst capturedAt = '+json.dumps(data['captured_at'])+';\nconst leads = '+json.dumps(leads,ensure_ascii=False)+';\nconst orders = '+json.dumps(orders,ensure_ascii=False)+';\n// SNAPSHOT_END'
 text=re.sub(r'// SNAPSHOT_START.*?// SNAPSHOT_END',lambda _:replacement,text,flags=re.S);a.canvas.write_text(text)
print(json.dumps({k:result[k] for k in ['captured_at','commercial_leads','paid_orders','revenue_rub']},ensure_ascii=False))
