// DOM unit tests: no browser automation or production data.
import vm from 'node:vm';
import fs from 'node:fs/promises';
const nodes=new Map(),listeners=new Map();
class Element {
 constructor(id){this.id=id;this.value='';this.listeners={};this.classList={toggle(){},add(){}};this.parentElement=this;}
 addEventListener(type,fn){this.listeners[type]=fn;}
 set innerHTML(html){this.html=html;for(const id of this.children||[])nodes.delete(id);this.children=[];const tags=/<(input|select)\b([^>]*)(?:>([\s\S]*?)<\/select>)?/g;for(const match of html.matchAll(tags)){const id=/id="([^"]+)"/.exec(match[2])?.[1];if(!id)continue;const el=new Element(id);el.name=/name="([^"]+)"/.exec(match[2])?.[1];if(match[1]==='input')el.value=/value="([^"]*)"/.exec(match[2])?.[1]||'';else {el.options=[...(match[3]||'').matchAll(/<option value="([^"]*)"([^>]*)>(.*?)<\/option>/g)];el.value=(el.options.find(o=>o[2].includes('selected'))||el.options[0])?.[1]||'';}nodes.set(id,el);this.children.push(id);}}
 get innerHTML(){return this.html||'';}
 querySelector(){return new Element('stub');}querySelectorAll(){return [];}reportValidity(){return true;}showModal(){}close(){}insertBefore(){}
}
const document={addEventListener(t,fn){(listeners.get(t)||listeners.set(t,[]).get(t)).push(fn);},querySelector(s){return nodes.get(s)||nodes.get(s.replace(/^#/,''))||null;},createElement(){return new Element('created');}};
for(const id of ['app','modal','toast','content'])nodes.set(id,new Element(id));
const context=vm.createContext({document,window:{addEventListener(){},scrollTo(){}},location:{hash:'',pathname:'/'},history:{},localStorage:{getItem(){return null}},Intl,Date,Math,Number,String,JSON,Array,Object,URLSearchParams,crypto:{randomUUID(){return 'test-key'}},setTimeout(){},clearTimeout(){},console});
for(const file of ['app.js','imports.js','chart.js','ledger.js','bills.js','simple.js'])vm.runInContext(await fs.readFile(new URL('../public/assets/'+file,import.meta.url),'utf8'),context,{filename:file});
const fixture={household:{name:'Demo',currency:'PKR',timezone:'Asia/Karachi'},user:{name:'Demo tester',role:'owner'},today:'2026-10-14',stats:{cash:'1000',income:'100',spending:'20',net_worth:'1080'},cashflow:{incoming:'100',outgoing:'20'},accounts:[{uuid:'bank-abc',ledger_id:10,name:'Bank ABC',type:'bank',active:1,balance:'1000'}],categories:[{uuid:'food',name:'Groceries',type:'expense',active:1},{uuid:'sales',name:'Income',type:'income',active:1}],assets:[],members:[],transactions:[],bills:[],documents:[],periods:[],audit:[],editing_ready:true,planning_ready:true,parties_listing:{ready:true,unlinked:[],parties:[{uuid:'party-a',id:1,name:'Mr. A',kind:'person',active:1,row_version:1,receivable:'200',payable:'500',net:'-300',accounts:[{uuid:'a-r',account_kind:'receivable',active:1,name:'Mr. A receivable',position:'200'},{uuid:'a-p',account_kind:'payable',active:1,name:'Mr. A payable',position:'-500'}]},{uuid:'party-b',id:2,name:'Mr. B',kind:'business',active:1,row_version:1,receivable:'0',payable:'0',net:'0',accounts:[{uuid:'b-r',account_kind:'receivable',active:1,name:'Mr. B receivable',position:'0'},{uuid:'b-p',account_kind:'payable',active:1,name:'Mr. B payable',position:'0'}]}]}};
fixture.spending=[];context.fixture=fixture;context.nodes=nodes;context.Element=Element;
vm.runInContext(`state=fixture;openForm=function(title,body,submit){globalThis.lastForm={title,body,submit};nodes.clear();nodes.set('action-form',new Element('action-form'));nodes.set('party-side-fields',new Element('party-side-fields'));nodes.set('party-category',new Element('party-category'));nodes.set('party-effect',new Element('party-effect'));const el=new Element('modal');el.innerHTML=body;nodes.set('#action-form [type="submit"]',new Element('submit'));};request=async function(route,payload){globalThis.posted={route,payload};return {ok:true};};`,context);
let count=0;function check(ok,label){if(!ok)throw Error(label);count++;console.log('PASS: '+label);}const run=s=>vm.runInContext(s,context);
run('render()');const home=nodes.get('app')?.innerHTML||'';
// openForm's test adapter clears nodes only when forms open; render uses the original app node.
check(!home.includes('Goals')&&!home.includes('Budgets')&&!home.includes('data-page="plan"'),'Daily navigation has no budget or future-plan destinations');
check(home.includes('data-page="parties"')&&home.includes('data-page="transactions"'),'Parties and transactions are primary destinations');
run('ledgerState.uuid="bank-abc";ledgerState.kind="money";ledgerState.data={account:{id:10,uuid:"bank-ledger",name:"Bank ABC",active:1},from:"2026-10-01",to:"2026-10-14",opening:"1000",closing:"1000",debit:"0",credit:"0",rows:[],accounts:[],pages:1,page:1,matched:0,search:"",order:"newest"}');
check(run('simpleAccounts()').includes('data-action="simple-entry" data-id="bank-abc"')&&run('ledgerView()').includes('Money in')&&run('ledgerView()').includes('Money out')&&!run('ledgerBalance(100)').includes('Dr'),'Bank/cash account exposes direct recording and readable money-in/out ledger');
run('simpleEntry("bank-abc")');check(nodes.get('f-account_uuid').value==='bank-abc','Bank ledger recording preselects Bank ABC');
nodes.get('f-entry_kind').value='party_out';nodes.get('#action-form [type="submit"]').listeners.click();
check(context.lastForm.title==='Record payment'&&nodes.get('f-account_uuid').value==='bank-abc','Selecting party payment stays in the bank/cash workflow');
nodes.get('f-party_uuid').value='party-a';nodes.get('f-party_uuid').listeners.change();
check(nodes.get('f-receivable_uuid').value==='a-r'&&nodes.get('party-side-fields').innerHTML.includes('class="hidden"'),'Paying on behalf auto-selects the single receivable and hides the extra selector');
check(!nodes.get('f-receivable_uuid').options.some(o=>o[1]==='b-r'),'Recipient accounts cannot replace on-behalf party accounts');
check(nodes.get('f-other_party_uuid').options.some(o=>o[1]==='party-b'),'Another person/business can be selected as recipient');
await context.lastForm.submit({party_uuid:'party-a',receivable_uuid:'a-r',action:'pay_party',account_uuid:'bank-abc',other_party_uuid:'party-b',amount:'50',date:'2026-10-14',description:'Paid B on behalf of A'},'test-key');
check(context.posted.route==='party-transaction'&&context.posted.payload.account_uuid==='bank-abc'&&context.posted.payload.party_uuid==='party-a'&&context.posted.payload.other_party_uuid==='party-b','Payment sends bank ABC, balance party A and recipient B together');
nodes.get('f-action').value='repay_party';nodes.get('f-action').listeners.change();check(nodes.get('f-payable_uuid').value==='a-p'&&!nodes.has('f-receivable_uuid'),'Spending held money switches to this party payable');
run('partyEntryForm("in",{account_uuid:"bank-abc",party_uuid:"party-a",action:"receive_repayment"})');check(nodes.get('f-receivable_uuid').value==='a-r'&&nodes.get('f-account_uuid').value==='bank-abc','Receiving a repayment credits party receivable into chosen bank');
run('partyEntryForm("other",{party_uuid:"party-a",action:"paid_by_party"})');check(nodes.get('f-category_uuid').options.some(o=>o[1]==='food')&&!nodes.has('f-account_uuid'),'Party-paid expense chooses expense head and has no fake cash field');
run('partyEntryForm("other",{party_uuid:"party-a",action:"invoice"})');check(nodes.get('f-category_uuid').options.some(o=>o[1]==='sales')&&!nodes.get('f-category_uuid').options.some(o=>o[1]==='food'),'Income invoice selects income heads only');
run('partyEntryForm("out",{party_uuid:"party-a",account_uuid:"bank-abc",action:"pay_party",amount:"50",edit_uuid:"transaction-1",row_version:2})');check(nodes.has('f-reason'),'Party corrections require a reason');
run('page="party";simpleState.partyId="party-a";simpleState.partyHistory=true');check(run('partyView()').includes('They owe me')&&run('partyView()').includes('I owe / hold for them'),'One party screen displays both balances');
check(run('txRows([{uuid:"old",description:"Original",effective_date:"2026-10-14",type:"journal",amount:"50",status:"reversed"}])').includes('Original'),'Party correction history can reveal reversed originals');
run('simpleState.partyHistory=false');check(!run('txRows([{uuid:"old",description:"Original",type:"journal",amount:"50",status:"reversed"}])').includes('Original'),'Default party list hides cancelled originals');
check(run('simpleMore()').includes('Income &amp; expense heads'),'Selectable income and expense heads remain manageable');
check(run("['home','money','parties','transactions','bills','more','categories','settings','reports','assets','documents'].every(p=>{page=p;return content().length>20;})"),'Every daily route renders without references to retired planning UI');
console.log(count+' simple UI DOM checks passed (not a browser layout test).');

check(run('simpleAccounts()').includes('Chart of accounts')&&run('simpleAccounts()').includes('Journal entry'),'Accounts has direct chart and journal access');
check(run('simpleMore()').includes('Chart of accounts')&&run('simpleMore()').includes('Journal entry'),'Mobile More exposes chart and journal');
check(run("JSON.stringify(journalAmounts([{debit:'0.10',credit:'0'},{debit:'0.20',credit:'0'},{debit:'0',credit:'0.30'}]))")==='{"debit":30,"credit":30,"difference":0}','Journal totals use exact minor units for fractional splits');
for(const value of ['-1','0.001','NaN','1e3']){let rejected=false;try{run('journalCents('+JSON.stringify(value)+')');}catch{rejected=true;}check(rejected,'Invalid journal amount rejected: '+value);}
run("chartState.report={accounts:[{uuid:'bank',name:'Bank',class:'asset',is_group:0,active:1},{uuid:'group',name:'Assets',class:'asset',is_group:1,active:1},{uuid:'old',name:'Archived',class:'expense',is_group:0,active:0}]}");
check(run('journalLine(0)').includes('Choose account…')&&!run('journalLine(0)').includes('value="group"')&&!run('journalLine(0)').includes('value="old"'),'Journal requires selectable posting accounts, excluding groups and archived choices');
check(run("journalLine(0,{account_uuid:'old',debit:'50.300000',credit:'0.000000'})").includes('value="50.30"'),'Existing journal edit formats database decimals without invalid precision');
run("state.user.role='viewer'");check(!run('simpleAccounts()').includes('data-action="simple-journal"')&&!run('simpleMore()').includes('data-action="simple-journal"'),'Viewer navigation has no journal posting action');
console.log('10 additional accounting UI checks passed.');

run("state.user.role='owner';state.parties_listing.unlinked=[{uuid:'legacy-r',account_kind:'receivable',name:'Legacy receivable',active:1},{uuid:'legacy-p',account_kind:'payable',name:'Legacy payable',active:1},{uuid:'archived-r',account_kind:'receivable',name:'Archived receivable',active:0}];newParty()");
for(const side of ['receivable','payable']){
 const tag=context.lastForm.body.match(new RegExp('<select[^>]*name="'+side+'_uuid"[^>]*>'))[0];
 check(!tag.includes('required'),'Create empty '+side+' account is optional for native form validation');
 check(nodes.get('f-'+side+'_uuid').options.filter(o=>o[1]==='').length===1,'Single clearly labelled blank '+side+' choice');
}
check(!nodes.get('f-receivable_uuid').options.some(o=>o[1]==='archived-r'),'Archived accounts cannot be chosen while adding a party');
await context.lastForm.submit({name:'New demo party',kind:'person',receivable_uuid:'',payable_uuid:''},'new-party-key');
check(context.posted.route==='party-change'&&context.posted.payload.action==='create'&&context.posted.payload.receivable_uuid===''&&context.posted.payload.payable_uuid==='','Blank optional accounts submit the new-party command');
run("partyLink('legacy-r')");await context.lastForm.submit({party_uuid:'party-a',reason:'Assign existing receivable'},'link-party-key');
check(context.posted.payload.action==='link'&&context.posted.payload.ledger_uuid==='legacy-r'&&context.posted.payload.uuid==='party-a','Existing account assignment selects a real party');
run("partyEntryForm('out',{party_uuid:'party-a',account_uuid:'bank-abc',receivable_uuid:'a-r',edit_uuid:'existing-entry',row_version:4})");nodes.get('f-party_uuid').value='party-b';nodes.get('f-party_uuid').listeners.change();
check(nodes.get('f-receivable_uuid').value==='b-r','Replacing transaction party selects that party receivable instead of the old party account');
await context.lastForm.submit({party_uuid:'party-b',receivable_uuid:'b-r',action:'pay_party',account_uuid:'bank-abc',amount:'50',date:'2026-10-14',reason:'Correct party'},'replace-party-key');
check(context.posted.route==='party-transaction'&&context.posted.payload.party_uuid==='party-b'&&context.posted.payload.edit_uuid==='existing-entry'&&context.posted.payload.row_version===4,'Party replacement submits an audited transaction correction');
console.log('9 party form regression checks passed.');
