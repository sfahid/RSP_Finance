import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const root=new URL('../../',import.meta.url),login=JSON.parse(await fs.readFile(new URL('work/empty-entry-login.json',root),'utf8')),base='http://127.0.0.1:8767/';
let csrf='',cookies=new Map(),count=0;
function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}
async function call(route,data=null,token=csrf,key=crypto.randomUUID()){
 const headers={Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};
 if(data!==null){headers['X-CSRF-Token']=token;headers['Idempotency-Key']=key;if(!(data instanceof FormData))headers['Content-Type']='application/json';}
 const r=await fetch(base+'?api='+route,{method:data===null?'GET':'POST',headers,body:data===null?undefined:data instanceof FormData?data:JSON.stringify(data)});
 for(const item of r.headers.getSetCookie()){const [k,...v]=item.split(';')[0].split('=');cookies.set(k,v.join('='));}let body;try{body=await r.json();}catch{throw Error('Invalid response '+r.status);}return {status:r.status,body};
}
function role(value){execFileSync('C:/wamp64/bin/php/php8.3.6/php.exe',['-d','xdebug.mode=off',new URL('app/tests/editing-role.php',root).pathname.replace(/^\//,''),value],{stdio:'pipe'});}
check((await call('parties')).status===401,'Party list requires authentication');
csrf=(await call('session')).body.csrf;check((await call('login',login)).status===200,'Synthetic owner signs in');
try{
 const list=(await call('parties')).body;check(list.ready&&list.parties.length>1,'Party list exposes both balances');const p=list.parties.find(p=>p.active&&Number(p.receivable)>0&&Number(p.payable)>0);
 check((await call('party-ledger&uuid='+p.uuid)).status===200,'Party ledger reads current and imported records');
 check((await call('party-change',{},'invalid')).status===403,'Party changes require CSRF');check((await call('party-transaction',{},'invalid')).status===403,'Party transactions require CSRF');
 role('viewer');check((await call('party-ledger&uuid='+p.uuid)).status===200,'Viewer may inspect party history');check((await call('party-change',{})).status===403,'Viewer cannot change parties');check((await call('party-transaction',{})).status===403,'Viewer cannot post party payments');check((await call('parties-upgrade',{})).status===403,'Viewer cannot upgrade');
 role('manager');check((await call('parties-upgrade',{})).status===403,'Only owner may enable party tables');
 const name='API party '+crypto.randomUUID(),key=crypto.randomUUID(),create={action:'create',name,kind:'business'};const created=await call('party-change',create,csrf,key);check(created.status===200,'Manager may add a business party');check((await call('party-change',create,csrf,key)).body.uuid===created.body.uuid,'API duplicate create returns same party');
 const snap=(await call('snapshot')).body,bank=snap.accounts.find(a=>a.active&&a.type==='bank'),own=(await call('parties')).body.parties.find(p=>p.uuid===created.body.uuid),receivable=own.accounts.find(a=>a.account_kind==='receivable').uuid;
 const payload={party_uuid:own.uuid,action:'pay_party',receivable_uuid:receivable,other_party_uuid:p.uuid,account_uuid:bank.uuid,amount:'1',date:snap.today,description:'API bank pays third party on behalf'},payKey=crypto.randomUUID();const payment=await call('party-transaction',payload,csrf,payKey);check(payment.status===200,'Direct bank-to-party payment is accepted');check((await call('party-transaction',payload,csrf,payKey)).body.uuid===payment.body.uuid,'API party payment retry is idempotent');
 check((await call('party-entry&uuid='+payment.body.uuid)).body.entry.payload.other_party_uuid===p.uuid,'Third-party recipient is retained');
 const stale=await call('party-transaction',{...payload,edit_uuid:payment.body.uuid,row_version:999,reason:'Stale request'});check(stale.status===409,'Stale financial edits are rejected');
 check((await call('create',{kind:'budget'})).status===422,'API rejects retired budgeting');check((await call('reserve',{})).status===422,'API rejects retired reservations');check((await call('recurring',{})).body.generated===0,'Scheduler endpoint no longer generates plans');
 check((await call('party-ledger&uuid='+crypto.randomUUID())).status===404,'Missing party cannot expose other records');check((await call('transaction-list&filter=all&page=2')).body.page===2,'All transactions are paginated');
 role('owner');check((await call('parties-upgrade',{})).status===200,'Owner upgrade is repeatable');
 // Import a bank debit, track its party and optional recipient, then correct it safely.
 const csv='Date,Description,Debit,Credit,Balance\n'+snap.today+',API third-party statement '+crypto.randomUUID()+',2,0,100\n',form=new FormData();form.set('account_uuid',bank.uuid);form.set('file',new Blob([csv],{type:'text/csv'}),'synthetic-party.csv');
 const upload=await call('import-upload',form);check(upload.status===200,'CSV statement upload still works');const batch=upload.body.batch.uuid,preview=upload.body,row=preview.rows[0];
 const committed=await call('import-commit',{uuid:batch,acknowledged:true,choices:[{id:row.id,type:'lend',ledger_uuid:receivable,other_party_uuid:p.uuid}]});check(committed.status===200,'Statement assignment supports third-party recipient');
 const ledger=(await call('party-ledger&uuid='+own.uuid)).body,posted=ledger.rows.find(t=>t.description.startsWith('API third-party statement'));
 check(!!posted&&posted.description.includes('on behalf of'),'Imported party transaction appears in party history');
 const metadata=(await call('party-entry&uuid='+posted.uuid)).body.entry;
 const correction=await call('party-transaction',{...metadata.payload,description:'Corrected statement note',edit_uuid:posted.uuid,row_version:metadata.transaction.row_version,reason:'Correct statement note'});check(correction.status===200,'Imported party note is editable without losing bank link');
 check((await call('party-transaction',{...metadata.payload,amount:'3',edit_uuid:correction.body.uuid,row_version:1,reason:'Wrong bank amount'})).status===409,'Statement bank amount remains protected');
 check((await call('import-archive',{uuid:batch})).status===200,'Uploaded statements can still be archived');
}finally{role('owner');}
console.log(count+' party API checks passed.');
