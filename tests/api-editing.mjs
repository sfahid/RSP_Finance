import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const taskRoot=new URL('../../',import.meta.url),login=JSON.parse(await fs.readFile(new URL('work/empty-entry-login.json',taskRoot),'utf8')),base='http://127.0.0.1:8767/';
let csrf='',cookies=new Map(),count=0;
function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}
async function request(route,data=null,csrfOverride=null){const headers={Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};if(data!==null){headers['Content-Type']='application/json';headers['X-CSRF-Token']=csrfOverride??csrf;headers['Idempotency-Key']=crypto.randomUUID();}const r=await fetch(base+'?api='+route,{method:data===null?'GET':'POST',headers,body:data===null?undefined:JSON.stringify(data)});for(const item of r.headers.getSetCookie()){const [k,...v]=item.split(';')[0].split('=');cookies.set(k,v.join('='));}const body=await r.text();return {status:r.status,ok:r.ok,json:async()=>JSON.parse(body)};}
function role(value){execFileSync('C:/wamp64/bin/php/php8.3.6/php.exe',['-d','xdebug.mode=off',new URL('app/tests/editing-role.php',taskRoot).pathname.replace(/^\//,''),value],{stdio:'pipe'});}
check((await request('transaction-details&uuid='+crypto.randomUUID())).status===401,'Transaction details require authentication');
csrf=(await(await request('session')).json()).csrf;check((await request('login',login)).ok,'Synthetic owner can sign in');
try{for(const route of ['account-update','ledger-update','transaction-edit','category-move'])check((await request(route,{},'invalid')).status===403,route+' rejects invalid CSRF');
role('viewer');for(const route of ['account-update','ledger-update','transaction-edit','category-move','editing-upgrade'])check((await request(route,{})).status===403,'Viewer cannot '+route);
role('manager');check((await request('editing-upgrade',{})).status===403,'Manager cannot alter editing schema');
role('owner');check((await request('transaction-details&uuid='+crypto.randomUUID())).status===404,'Foreign or unavailable transaction details remain scoped');
check((await request('account-update',{uuid:crypto.randomUUID()})).status===404,'Foreign or unavailable account edits remain scoped');
check((await request('snapshot')).ok,'Snapshot remains available after role checks');
}finally{role('owner');}
console.log(count+' editing API checks passed.');
