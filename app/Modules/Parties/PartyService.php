<?php
declare(strict_types=1);
namespace ReadyFinance;
/** Parties organize existing posting accounts; balances always come from journals. */
final class PartyService {
 public function __construct(private Core $c){}
 public function ready():bool{return (bool)$this->c->query("SELECT COUNT(*) FROM schema_migrations WHERE version='008_parties'")->fetchColumn();}
 public function upgrade():array {
  if(!(new AccountManagementService($this->c))->ready())throw new Fault('Enable account editing first.');
  $c=$this->c;$lock='rpf_party_'.substr(hash('sha256',(string)$c->query('SELECT DATABASE()')->fetchColumn()),0,40);
  if(!$c->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn())throw new Fault('Upgrade is busy.',409);
  try{($m=require dirname(__DIR__,3).'/database/008_parties.php')($c->db);return ['ok'=>true];}finally{$c->query('SELECT RELEASE_LOCK(?)',[$lock]);}
 }
 private function requireReady():void{if(!$this->ready())throw new Fault('Enable parties first.');}
 public function labelRows(int $h,array $rows):array {
  if(!$rows||!$this->ready())return $rows;$ids=array_column($rows,'uuid');$actions=$this->c->all('SELECT t.uuid,e.action FROM party_entries e JOIN transactions t ON t.id=e.transaction_id AND t.household_id=e.household_id WHERE e.household_id=? AND t.uuid IN ('.implode(',',array_fill(0,count($ids),'?')).')',[$h,...$ids]);$map=array_column($actions,'action','uuid');foreach($rows as &$row)$row['action']=$map[$row['uuid']]??null;unset($row);return $rows;
 }
 private function key(string $key):void{if(!preg_match('/^[a-zA-Z0-9_.:-]{8,150}$/D',$key))throw new Fault('A valid command key is required.');}
 private function receipt(int $h,string $key,array $p):?array{$r=$this->c->one('SELECT * FROM command_receipts WHERE household_id=? AND command_key=?',[$h,'party:'.$key]);if(!$r)return null;if($r['payload_hash']!==hash('sha256',json_encode($p,JSON_THROW_ON_ERROR)))throw new Fault('Command key conflict.',409);return json_decode($r['result_json'],true);}
 private function saveReceipt(int $h,string $key,array $p,array $r):array{$this->c->insert('command_receipts',['household_id'=>$h,'command_key'=>'party:'.$key,'payload_hash'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR)),'result_json'=>json_encode($r,JSON_THROW_ON_ERROR),'created_at'=>Core::now()]);return $r;}
 public function listing(int $h):array {
  $c=$this->c;$chart=(new ChartService($c))->ready();
  $accounts=$chart?$c->all("SELECT l.*,COALESCE(SUM(j.debit-j.credit),0) position FROM ledger_accounts l LEFT JOIN journal_lines j ON j.ledger_id=l.id AND j.household_id=l.household_id WHERE l.household_id=? AND l.is_group=0 AND l.account_kind IN ('receivable','payable') GROUP BY l.id ORDER BY l.name,l.id",[$h]):[];
  if(!$this->ready())return ['ready'=>false,'parties'=>[],'unlinked'=>$accounts];
  $links=$c->all('SELECT party_id,ledger_id FROM party_accounts WHERE household_id=?',[$h]);$owner=[];foreach($links as $l)$owner[$l['ledger_id']]=$l['party_id'];
  $parties=$c->all('SELECT uuid,id,name,kind,active,row_version FROM parties WHERE household_id=? ORDER BY name,id',[$h]);
  foreach($parties as &$party){$party['accounts']=[];$party['receivable']='0';$party['payable']='0';foreach($accounts as $a)if(($owner[$a['id']]??null)==$party['id']){$party['accounts'][]=$a;$col=$a['account_kind'];$value=$col==='payable'?bcsub('0',$a['position'],6):$a['position'];$party[$col]=bcadd($party[$col],$value,6);}$party['net']=bcsub($party['receivable'],$party['payable'],6);}unset($party);
  return ['ready'=>true,'parties'=>$parties,'unlinked'=>array_values(array_filter($accounts,fn($a)=>!isset($owner[$a['id']])))];
 }
 private function attach(int $h,int $u,int $pid,string $uuid):void {
  $c=$this->c;$a=$c->scope('ledger_accounts',$uuid,$h,true);if($a['is_group']||!in_array($a['account_kind'],['receivable','payable'],true))throw new Fault('Select a receivable or payable posting account.');
  $old=$c->one('SELECT party_id FROM party_accounts WHERE household_id=? AND ledger_id=?',[$h,$a['id']]);if($old){if((int)$old['party_id']!==$pid)throw new Fault('This account already belongs to another party.',409);return;}
  $c->insert('party_accounts',['household_id'=>$h,'party_id'=>$pid,'ledger_id'=>$a['id']]);$c->audit($h,$u,'party.account_linked',$uuid,['party_id'=>$pid,'journals_changed'=>false]);
 }
 public function change(int $h,int $u,array $p,string $key):array {
  $this->requireReady();$this->key($key);$c=$this->c;return $c->atomic(function()use($c,$h,$u,$p,$key){
   $c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);if($r=$this->receipt($h,$key,$p))return $r;
   $action=$p['action']??'create';$uuid=(string)($p['uuid']??'');
   if($action==='create'){$name=Core::text($p['name']??'',100);$kind=$p['kind']??'person';if(!in_array($kind,['person','business'],true))throw new Fault('Choose person or business.');if($c->one('SELECT id FROM parties WHERE household_id=? AND name=?',[$h,$name]))throw new Fault('This party already exists. Open it from the list.');$uuid=Core::uuid();$pid=$c->insert('parties',['uuid'=>$uuid,'household_id'=>$h,'name'=>$name,'kind'=>$kind,'created_at'=>Core::now()]);
    foreach(['receivable','payable'] as $side){$selected=(string)($p[$side.'_uuid']??'');if($selected){$a=$c->scope('ledger_accounts',$selected,$h);if($a['account_kind']!==$side)throw new Fault('Choose the matching account type.');$this->attach($h,$u,$pid,$selected);}else{$lid=$c->ledger($h,$name.' — '.$side,$side==='receivable'?'asset':'liability');(new ChartService($c))->attach($h,$lid,'',$side);$a=$c->one('SELECT uuid FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$lid]);(new ObligationService($c))->track($h,$u,$a['uuid'],$side==='receivable'?'lent':'borrowed');$this->attach($h,$u,$pid,$a['uuid']);}}
   }else{$party=$c->scope('parties',$uuid,$h,true);$pid=(int)$party['id'];if((string)($p['row_version']??'')!==(string)$party['row_version'])throw new Fault('Party details changed. Reopen this form.',409);Core::text($p['reason']??'',400);
    if($action==='link'){$this->attach($h,$u,$pid,(string)($p['ledger_uuid']??''));}
    elseif($action==='rename'){$name=Core::text($p['name']??'',100);$kind=$p['kind']??'person';if(!in_array($kind,['person','business'],true))throw new Fault('Choose person or business.');if($c->one('SELECT id FROM parties WHERE household_id=? AND name=? AND id<>?',[$h,$name,$pid]))throw new Fault('Another party uses this name.');$c->query('UPDATE parties SET name=?,kind=? WHERE household_id=? AND id=?',[$name,$kind,$h,$pid]);}
    elseif(in_array($action,['archive','restore'],true)){if($action==='archive')foreach($c->all('SELECT ledger_id FROM party_accounts WHERE household_id=? AND party_id=?',[$h,$pid]) as $l)if(bccomp($c->balance($h,$l['ledger_id']),'0',6)!==0)throw new Fault('Settle all party accounts before deleting.');$c->query('UPDATE parties SET active=? WHERE household_id=? AND id=?',[$action==='restore'?1:0,$h,$pid]);}
    else throw new Fault('Choose a supported party action.');$c->query('UPDATE parties SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) WHERE household_id=? AND id=?',[$h,$pid]);
   }
   $c->audit($h,$u,'party.'.$action,$uuid,['reason'=>$p['reason']??null]);return $this->saveReceipt($h,$key,$p,['uuid'=>$uuid]);
  });
 }
 private function payload(int $h,array $p):array {
  $c=$this->c;$party=$c->scope('parties',(string)($p['party_uuid']??''),$h);if(!$party['active'])throw new Fault('Restore this party before recording transactions.');$action=$p['action']??'';$amount=Core::money($p['amount']??'');$date=Core::date($p['date']??'');$desc=trim((string)($p['description']??''))?:$party['name'].' — '.str_replace('_',' ',$action);$desc=Core::text($desc,500);
  $side=match($action){'pay_party','receive_repayment','invoice'=>'receivable','receive_party','repay_party','paid_by_party','supplier_bill'=>'payable','offset'=>'both',default=>throw new Fault('Choose a party transaction purpose.')};
  $ledger=function(string $which)use($c,$h,$party,$p):array{$uuid=(string)($p[$which.'_uuid']??'');$a=$c->scope('ledger_accounts',$uuid,$h);if(!$c->one('SELECT ledger_id FROM party_accounts WHERE household_id=? AND party_id=? AND ledger_id=?',[$h,$party['id'],$a['id']])||$a['account_kind']!==$which)throw new Fault('Choose this party\'s '.$which.' account.');return $a;};
  $r=in_array($side,['receivable','both'],true)?$ledger('receivable'):null;$v=in_array($side,['payable','both'],true)?$ledger('payable'):null;
  $cash=null;if(in_array($action,['pay_party','receive_repayment','receive_party','repay_party'],true)){$a=$c->scope('accounts',(string)($p['account_uuid']??''),$h);if(!$a['active'])throw new Fault('Choose an active bank or cash account.');$cash=$c->one('SELECT uuid FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$a['ledger_id']]);}
  if(!empty($p['other_party_uuid'])){$other=$c->scope('parties',(string)$p['other_party_uuid'],$h);$desc.=' · '.(in_array($action,['pay_party','repay_party'],true)?'Paid to ': 'Received from ').$other['name'].' on behalf of '.$party['name'];$desc=Core::text($desc,500);}
  $category=null;if(in_array($action,['paid_by_party','supplier_bill','invoice'],true)){$cat=$c->scope('categories',(string)($p['category_uuid']??''),$h);if($cat['type']!==($action==='invoice'?'income':'expense'))throw new Fault('Choose the matching income or expense category.');$category=$c->one('SELECT uuid FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']]);}
  [$dr,$cr]=match($action){'pay_party'=>[$r,$cash],'receive_repayment'=>[$cash,$r],'receive_party'=>[$cash,$v],'repay_party'=>[$v,$cash],'paid_by_party','supplier_bill'=>[$category,$v],'invoice'=>[$r,$category],'offset'=>[$v,$r]};
  return ['type'=>'journal','date'=>$date,'description'=>$desc,'lines'=>[['account_uuid'=>$dr['uuid'],'debit'=>$amount,'credit'=>'0'],['account_uuid'=>$cr['uuid'],'debit'=>'0','credit'=>$amount]]];
 }
 public function entry(int $h,string $uuid):?array {
  if(!$this->ready())return null;$c=$this->c;$t=$c->scope('transactions',$uuid,$h);$e=$c->one('SELECT e.*,p.uuid party_uuid FROM party_entries e JOIN parties p ON p.id=e.party_id AND p.household_id=e.household_id WHERE e.household_id=? AND e.transaction_id=?',[$h,$t['id']]);if($e)return ['transaction'=>$t,'payload'=>json_decode($e['payload_json'],true)];
  // Old loan/statement entries become readable under their linked party without migrating journals.
  if(!in_array($t['type'],['borrow','lend','repay','collect'],true))return null;
  $links=$c->all('SELECT pt.uuid party_uuid,l.uuid ledger_uuid,l.account_kind FROM journal_entries j JOIN journal_lines jl ON jl.journal_id=j.id AND jl.household_id=j.household_id JOIN ledger_accounts l ON l.id=jl.ledger_id AND l.household_id=jl.household_id JOIN party_accounts pa ON pa.ledger_id=l.id AND pa.household_id=l.household_id JOIN parties pt ON pt.id=pa.party_id AND pt.household_id=pa.household_id WHERE j.household_id=? AND j.transaction_id=?',[$h,$t['id']]);if(count($links)!==1)return null;$link=$links[0];$original=(new TransactionEditingService($c))->details($h,$uuid)['payload'];$action=['lend'=>'pay_party','borrow'=>'receive_party','repay'=>'repay_party','collect'=>'receive_repayment'][$t['type']];$p=['party_uuid'=>$link['party_uuid'],'action'=>$action,'account_uuid'=>$original['account_uuid'],'date'=>$t['effective_date'],'amount'=>$t['amount'],'description'=>$t['description'],$link['account_kind'].'_uuid'=>$link['ledger_uuid']];return ['transaction'=>$t,'payload'=>$p];
 }
 public function post(int $h,int $u,array $p,string $key):array {
  $this->requireReady();$this->key($key);$c=$this->c;return $c->atomic(function()use($c,$h,$u,$p,$key){$c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);if($r=$this->receipt($h,$key,$p))return $r;$journal=$this->payload($h,$p);$engine=new Accounting($c);
   if(!empty($p['edit_uuid'])){$original=(new TransactionEditingService($c))->details($h,(string)$p['edit_uuid']);if($original['transaction']['type']==='opening')throw new Fault('Opening balances cannot become party transactions.');if(!empty($original['payload']['bill_uuid']))throw new Fault('Correct this linked bill payment from its bill.');if(isset($original['payload']['obligation_uuid']))$journal['obligation_uuid']=$original['payload']['obligation_uuid'];$r=(new TransactionEditingService($c))->editInTransaction($h,$u,['uuid'=>$p['edit_uuid'],'row_version'=>$p['row_version']??'','reason'=>$p['reason']??'','payload'=>$journal],'party-edit:'.$key);$t=$c->scope('transactions',$r['uuid'],$h);}
   else $t=$engine->postJournalInTransaction($h,$u,$journal,'party-post:'.$key);
   $party=$c->scope('parties',$p['party_uuid'],$h);foreach($c->all('SELECT l.id,l.account_kind FROM party_accounts pa JOIN ledger_accounts l ON l.id=pa.ledger_id AND l.household_id=pa.household_id WHERE pa.household_id=? AND pa.party_id=?',[$h,$party['id']]) as $l){$b=$c->balance($h,$l['id']);if(($l['account_kind']==='receivable'&&bccomp($b,'0',6)<0)||($l['account_kind']==='payable'&&bccomp($b,'0',6)>0))throw new Fault('Payment exceeds this party account balance. Choose an advance instead or correct related payments.');}$c->insert('party_entries',['household_id'=>$h,'transaction_id'=>$t['id'],'party_id'=>$party['id'],'action'=>$p['action'],'payload_json'=>json_encode($p,JSON_THROW_ON_ERROR)]);$c->audit($h,$u,'party.transaction',$t['uuid'],['party_uuid'=>$party['uuid'],'action'=>$p['action'],'other_party_uuid'=>$p['other_party_uuid']??null]);return $this->saveReceipt($h,$key,$p,['uuid'=>$t['uuid']]);
  });
 }
 public function ledger(int $h,array $p):array {
  $this->requireReady();$c=$this->c;$party=$c->scope('parties',(string)($p['uuid']??''),$h);$search=trim((string)($p['search']??''));if(mb_strlen($search)>120)throw new Fault('Search is too long.');$page=max(1,(int)($p['page']??1));$history=($p['history']??'0')==='1';
  $where="t.household_id=? AND EXISTS(SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id=j.id AND l.household_id=j.household_id JOIN party_accounts pa ON pa.ledger_id=l.ledger_id AND pa.household_id=l.household_id WHERE j.transaction_id=t.id AND j.household_id=t.household_id AND pa.party_id=?) AND LOCATE(LOWER(?),LOWER(t.description))>0".($history?'':" AND t.status='posted' AND t.type<>'reversal'");$args=[$h,$party['id'],$search];$count=(int)$c->query('SELECT COUNT(*) FROM transactions t WHERE '.$where,$args)->fetchColumn();$pages=max(1,(int)ceil($count/50));$page=min($page,$pages);
  $rows=$c->all('SELECT t.uuid,t.description,t.type,t.amount,t.effective_date,t.status,t.row_version,t.replaces_id,e.action FROM transactions t LEFT JOIN party_entries e ON e.transaction_id=t.id AND e.household_id=t.household_id WHERE '.$where.' ORDER BY t.effective_date DESC,t.id DESC LIMIT 50 OFFSET '.(($page-1)*50),$args);return ['party'=>$party,'rows'=>$rows,'page'=>$page,'pages'=>$pages,'count'=>$count,'search'=>$search,'history'=>$history];
 }
}
