import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const root=new URL('../../',import.meta.url),login=JSON.parse(await fs.readFile(new URL('work/empty-entry-login.json',root),'utf8')),base='http://127.0.0.1:8767/';
let csrf='',cookies=new Map(),count=0;
function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}
async function request(route,data=null,token=null){const headers={Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};if(data!==null){headers['Content-Type']='application/json';headers['X-CSRF-Token']=token??csrf;headers['Idempotency-Key']=crypto.randomUUID();}const r=await fetch(base+'?api='+route,{method:data===null?'GET':'POST',headers,body:data===null?undefined:JSON.stringify(data)});for(const c of r.headers.getSetCookie()){const [k,...v]=c.split(';')[0].split('=');cookies.set(k,v.join('='));}return {status:r.status,body:await r.text()};}
function role(value){execFileSync('C:/wamp64/bin/php/php8.3.6/php.exe',['-d','xdebug.mode=off',new URL('app/tests/editing-role.php',root).pathname.replace(/^\//,''),value],{stdio:'pipe'});}
for(const route of ['imports','imports&archived=1'])check((await request(route)).status===401,'Statement lists require authentication');
csrf=JSON.parse((await request('session')).body).csrf;check((await request('login',login)).status===200,'Synthetic owner signs in');
try{
 for(const route of ['import-archive','import-restore','import-party-create'])check((await request(route,{},'invalid')).status===403,route+' requires CSRF');
 role('viewer');for(const route of ['import-archive','import-restore','import-party-create'])check((await request(route,{})).status===403,'Viewer cannot '+route);check((await request('imports')).status===403,'Viewer cannot read private statements');
 role('owner');for(const route of ['import-archive','import-restore'])check((await request(route,{uuid:crypto.randomUUID()})).status===404,route+' scopes missing statement');
 const list=await request('imports');check(list.status===200&&Array.isArray(JSON.parse(list.body).settlement_accounts),'Active statement response includes receivable/payable targets');check((await request('imports&archived=1')).status===200,'Separate archived list is available');
 role('manager');const result=await request('import-party-create',{name:'API party '+crypto.randomUUID(),kind:'payable'});check(result.status===200&&!!JSON.parse(result.body).ledger_uuid,'Manager can create zero-balance payable');
}finally{role('owner');}
console.log(count+' statement API checks passed.');
