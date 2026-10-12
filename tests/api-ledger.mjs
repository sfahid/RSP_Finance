import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const root=new URL('../../',import.meta.url),login=JSON.parse(await fs.readFile(new URL('work/empty-entry-login.json',root),'utf8')),base='http://127.0.0.1:8767/';
let csrf='',cookies=new Map(),count=0;
function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}
async function request(route,data=null){const headers={Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};if(data!==null){headers['Content-Type']='application/json';headers['X-CSRF-Token']=csrf;headers['Idempotency-Key']=crypto.randomUUID();}const r=await fetch(base+'?api='+route,{method:data===null?'GET':'POST',headers,body:data===null?undefined:JSON.stringify(data)});for(const c of r.headers.getSetCookie()){const [k,...v]=c.split(';')[0].split('=');cookies.set(k,v.join('='));}return {status:r.status,body:await r.text()};}
function role(value){execFileSync('C:/wamp64/bin/php/php8.3.6/php.exe',['-d','xdebug.mode=off',new URL('app/tests/editing-role.php',root).pathname.replace(/^\//,''),value],{stdio:'pipe'});}
check((await request('account-ledger&uuid='+crypto.randomUUID())).status===401,'Ledger requires sign-in');
csrf=JSON.parse((await request('session')).body).csrf;check((await request('login',login)).status===200,'Synthetic owner signs in');
try{
 const snapshot=JSON.parse((await request('snapshot')).body),account=snapshot.accounts.find(a=>a.name.startsWith('Ledger pagination bank'));
 check(!!account,'Large synthetic ledger is present');const route='account-ledger&kind=money&uuid='+account.uuid;
 const d=JSON.parse((await request(route)).body);check(d.entries>1000&&d.rows.length===50&&d.pages>20,'API returns paginated history beyond 1000 entries');
 role('viewer');check((await request(route)).status===200,'Viewer can read authorized account ledger');check((await request('transaction-details&uuid='+d.rows[0].uuid)).status===200,'Viewer can inspect journal details');check((await request('transaction-edit',{})).status===403,'Viewer cannot edit from the ledger');
 role('owner');check((await request('account-ledger&uuid='+crypto.randomUUID())).status===404,'Unavailable account is scoped');check((await request(route+'&page=0')).status===422,'Invalid page yields validation error');check((await request(route+'&from=2025-02-01&to=2025-01-01')).status===422,'Invalid date range yields validation error');
 check((await request(route+'&search='+encodeURIComponent('Find this merchant'))).status===200,'Ledger search endpoint is available');
}finally{role('owner');}
console.log(count+' ledger API checks passed.');
