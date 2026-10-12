import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const root=new URL('../../',import.meta.url),login=JSON.parse(await fs.readFile(new URL('work/empty-entry-login.json',root),'utf8')),base='http://127.0.0.1:8767/';
let csrf='',cookies=new Map(),count=0;
function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}
async function request(route,data=null,token=null,key=null){const headers={Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};if(data!==null){headers['Content-Type']='application/json';headers['X-CSRF-Token']=token??csrf;headers['Idempotency-Key']=key??crypto.randomUUID();}const response=await fetch(base+'?api='+route,{method:data===null?'GET':'POST',headers,body:data===null?undefined:JSON.stringify(data)});for(const item of response.headers.getSetCookie()){const [k,...v]=item.split(';')[0].split('=');cookies.set(k,v.join('='));}return {status:response.status,body:await response.text()};}
function role(value){execFileSync('C:/wamp64/bin/php/php8.3.6/php.exe',['-d','xdebug.mode=off',new URL('app/tests/editing-role.php',root).pathname.replace(/^\//,''),value],{stdio:'pipe'});}
check((await request('plan-details&kind=budget&uuid='+crypto.randomUUID())).status===401,'Plan details require sign-in');
csrf=JSON.parse((await request('session')).body).csrf;check((await request('login',login)).status===200,'Synthetic owner signs in');
try{
 const snapshot=JSON.parse((await request('snapshot')).body);check(snapshot.planning_ready,'Snapshot advertises enabled plan editing');const budget=snapshot.budgets.find(b=>b.name.startsWith('Plan expense')&&!Number(b.archived));check(!!budget,'Synthetic budget is present');
 const route='plan-details&kind=budget&uuid='+budget.uuid;
 check((await request('plan-change',{},'invalid')).status===403,'Plan changes reject invalid CSRF');check((await request('planning-upgrade',{},'invalid')).status===403,'Upgrade rejects invalid CSRF');
 role('viewer');check((await request(route)).status===200,'Viewer can inspect plans');check((await request('plan-change',{})).status===403,'Viewer cannot edit or delete plans');check((await request('planning-upgrade',{})).status===403,'Viewer cannot enable the migration');
 role('manager');check((await request('planning-upgrade',{})).status===403,'Manager cannot alter the schema');const r=JSON.parse((await request(route)).body).record;
 const payload={kind:'budget',uuid:budget.uuid,action:'update',row_version:r.row_version,category_uuid:r.category_uuid,month:r.month,amount:'180',reason:'Synthetic API budget update'},key=crypto.randomUUID();
 check((await request('plan-change',payload,null,key)).status===200,'Manager can update a budget');check((await request('plan-change',payload,null,key)).status===200,'HTTP retry is idempotent');
 check((await request('plan-change',{...payload,amount:'181'},null,key)).status===409,'Changed retry data is rejected');check((await request('plan-change',payload)).status===409,'Stale plan version is rejected');
 role('owner');check((await request('plan-details&kind=debt&uuid='+crypto.randomUUID())).status===404,'Unavailable or foreign loans remain scoped');check((await request('planning-upgrade',{})).status===200,'Owner can safely repeat the upgrade');
}finally{role('owner');}
console.log(count+' planning API checks passed.');
