<?php
declare(strict_types=1);
namespace ReadyFinance;
/** Plan metadata changes never rewrite journals. Deletion is reversible archival. */
final class PlanManagementService {
 private const TABLES=['budget'=>'budgets','bill'=>'bills','fund'=>'funds','debt'=>'obligations','recurring'=>'recurring_rules'];
 public function __construct(private Core $c){}
 public function ready():bool{return (bool)$this->c->query("SELECT COUNT(*) FROM schema_migrations WHERE version='007_planning'")->fetchColumn();}
 public function upgrade():array {
  $c=$this->c;if(!(new AccountManagementService($c))->ready())throw new Fault('Enable editing and review tools first.');
  $lock='rpf_plan_'.substr(hash('sha256',(string)$c->query('SELECT DATABASE()')->fetchColumn()),0,40);
  if(!$c->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn())throw new Fault('Upgrade is busy.',409);
  try{($migration=require dirname(__DIR__,3).'/database/007_planning.php')($c->db);return ['ok'=>true];}finally{$c->query('SELECT RELEASE_LOCK(?)',[$lock]);}
 }
 private function table(string $kind):string {return self::TABLES[$kind]??throw new Fault('Choose a supported plan.');}
 public function details(int $h,string $kind,string $uuid):array {
  if(!$this->ready())throw new Fault('Enable plan editing tools first.');$c=$this->c;$row=$c->scope($this->table($kind),$uuid,$h);
  if(isset($row['category_id']))$row['category_uuid']=$c->one('SELECT uuid FROM categories WHERE household_id=? AND id=?',[$h,$row['category_id']])['uuid'];
  if($kind==='fund'){$row['account_uuid']=$c->one('SELECT uuid FROM accounts WHERE household_id=? AND id=?',[$h,$row['account_id']])['uuid'];$row['reserved']=$this->reserved($h,(int)$row['id']);}
  if($kind==='bill')$row['paid']=$this->paid($h,(int)$row['id']);
  $entries=[];
  if($kind==='debt'){$row['ledger_uuid']=$c->one('SELECT uuid FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$row['ledger_id']])['uuid'];$position=$c->balance($h,(int)$row['ledger_id']);$row['remaining']=$row['direction']==='borrowed'?bcsub('0',$position,6):$position;
   $entries=$c->all("SELECT t.uuid,t.type,t.description,t.effective_date,t.amount,t.status FROM transactions t WHERE t.household_id=? AND t.status='posted' AND t.type<>'reversal' AND EXISTS(SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id=j.id AND l.household_id=j.household_id WHERE j.transaction_id=t.id AND j.household_id=t.household_id AND l.ledger_id=?) ORDER BY t.effective_date DESC,t.id DESC LIMIT 50",[$h,$row['ledger_id']]);
  }
  return ['record'=>$row,'transactions'=>$entries];
 }
 private function paid(int $h,int $id):string{return (string)$this->c->query('SELECT COALESCE(SUM(amount),0) FROM bill_allocations WHERE household_id=? AND bill_id=?',[$h,$id])->fetchColumn();}
 private function reserved(int $h,int $id):string{return (string)$this->c->query('SELECT COALESCE(SUM(amount),0) FROM fund_allocations WHERE household_id=? AND fund_id=?',[$h,$id])->fetchColumn();}
 private function category(int $h,string $uuid):array {
  $c=$this->c;$cat=$c->scope('categories',$uuid,$h);$ledger=$c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']]);
  if($cat['type']!=='expense'||!(int)($ledger['active']??1))throw new Fault('Choose an active expense account.');return $cat;
 }
 public function change(int $h,int $u,array $p,string $key):array {
  if(!$this->ready())throw new Fault('Enable plan editing tools first.');$kind=(string)($p['kind']??'');$table=$this->table($kind);
  if(!preg_match('/^[a-zA-Z0-9_.:-]{8,150}$/D',$key))throw new Fault('A valid command key is required.');$c=$this->c;
  return $c->atomic(function()use($c,$h,$u,$p,$key,$kind,$table){
   $c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$command='plan:'.$key;$hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));$old=$c->one('SELECT * FROM command_receipts WHERE household_id=? AND command_key=?',[$h,$command]);
   if($old){if(!hash_equals($old['payload_hash'],$hash))throw new Fault('Command key conflict.',409);return json_decode($old['result_json'],true);}
   $row=$c->scope($table,(string)($p['uuid']??''),$h,true);
   if((string)($p['row_version']??'')!==(string)$row['row_version'])throw new Fault('This item changed. Close and reopen it before saving.',409);
   $action=$p['action']??'';$reason=Core::text($p['reason']??'',400);$changes=[];
   if($action==='update'){
    if($row['archived'])throw new Fault('Restore this item before editing.');
    if($kind==='budget'){
     $month=substr(Core::date((string)($p['month']??'').'-01'),0,7);$c->guardPeriod($h,$row['month'].'-01');$c->guardPeriod($h,$month.'-01');$cat=$this->category($h,(string)($p['category_uuid']??''));
     if($c->one('SELECT id FROM budgets WHERE household_id=? AND category_id=? AND month=? AND id<>?',[$h,$cat['id'],$month,$row['id']]))throw new Fault('A budget already exists for that account and month. Edit or restore it instead.');
     $changes=['category_id'=>$cat['id'],'month'=>$month,'amount'=>Core::money($p['amount']??'')];
    }elseif($kind==='bill'){
     $amount=Core::money($p['amount']??'');$paid=$this->paid($h,(int)$row['id']);if(bccomp($amount,$paid,6)<0)throw new Fault('Bill amount cannot be less than payments already recorded.');$cat=$this->category($h,(string)($p['category_uuid']??''));
     if($cat['id']!==$row['category_id']&&bccomp($paid,'0',6)>0)throw new Fault('Correct or reverse the existing payments before changing their bill account.');
     $changes=['name'=>Core::text($p['name']??''),'amount'=>$amount,'due_date'=>Core::date($p['due_date']??''),'category_id'=>$cat['id']];
    }elseif($kind==='fund'){
     $account=$c->scope('accounts',(string)($p['account_uuid']??''),$h);if(!$account['active']||$account['type']==='credit_card')throw new Fault('Choose an active cash or bank account.');
     if($account['id']!==$row['account_id']&&bccomp($this->reserved($h,(int)$row['id']),'0',6)!==0)throw new Fault('Release reserved money before changing the backing account.');
     $purpose=$p['purpose']??'';if(!in_array($purpose,['goal','sinking_fund'],true))throw new Fault('Choose a goal or sinking fund.');
     $changes=['name'=>Core::text($p['name']??''),'purpose'=>$purpose,'target'=>Core::money($p['target']??''),'target_date'=>empty($p['target_date'])?null:Core::date($p['target_date']),'account_id'=>$account['id']];
    }elseif($kind==='debt'){
     foreach(['amount','original_amount','direction','account_uuid','date'] as $field)if(array_key_exists($field,$p))throw new Fault('Change loan amounts, direction or cash movements through the linked transactions.');
     $name=Core::text($p['name']??'',120);$ledger=$c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$row['ledger_id']]);
     if($c->one('SELECT id FROM ledger_accounts WHERE household_id=? AND class=? AND parent_id<=>? AND name=? AND id<>?',[$h,$ledger['class'],$ledger['parent_id'],$name,$ledger['id']]))throw new Fault('Another account under this heading already uses that name.');
     $changes=['name'=>$name,'due_date'=>empty($p['due_date'])?null:Core::date($p['due_date'])];$c->query('UPDATE ledger_accounts SET name=? WHERE household_id=? AND id=?',[$name,$h,$row['ledger_id']]);
    }else{
     $freq=$p['frequency']??'';if(!in_array($freq,['weekly','monthly','yearly'],true))throw new Fault('Choose weekly, monthly or yearly.');$date=Core::date($p['next_date']??'');$cat=$this->category($h,(string)($p['category_uuid']??''));
     if(!in_array((string)($p['active']??''),['0','1'],true))throw new Fault('Choose running or paused.');
     $changes=['name'=>Core::text($p['name']??''),'amount'=>Core::money($p['amount']??''),'category_id'=>$cat['id'],'frequency'=>$freq,'next_date'=>$date,'anchor_day'=>(int)substr($date,8,2),'active'=>(int)$p['active']];
    }
   }elseif(in_array($action,['delete','restore'],true)){
    if((bool)$row['archived']===($action==='delete'))throw new Fault('This item is already '.($action==='delete'?'deleted':'restored').'.',409);
    if($kind==='budget')$c->guardPeriod($h,$row['month'].'-01');
    if($action==='restore'&&isset($row['category_id'])){$category=$c->one('SELECT uuid FROM categories WHERE household_id=? AND id=?',[$h,$row['category_id']]);$this->category($h,$category['uuid']);}
    if($kind==='debt'&&$action==='delete'&&bccomp($c->balance($h,(int)$row['ledger_id']),'0',6)!==0)throw new Fault('This loan still has a balance. Correct/reverse mistaken entries or record settlement before deleting it.');
    if($kind==='fund'&&$action==='delete'){$reserved=$this->reserved($h,(int)$row['id']);if(bccomp($reserved,'0',6)>0)$c->insert('fund_allocations',['household_id'=>$h,'fund_id'=>$row['id'],'amount'=>bcsub('0',$reserved,6),'source_key'=>'plan-release:'.$key,'created_at'=>Core::now()]);}
    $changes=['archived'=>$action==='delete'?1:0];
   }else throw new Fault('Choose Edit, Delete or Restore.');
   $changes['row_version']=(int)$row['row_version']+1;$changes['updated_at']=Core::now();$assign=implode(',',array_map(fn($column)=>$column.'=?',array_keys($changes)));
   $c->query('UPDATE '.$table.' SET '.$assign.' WHERE household_id=? AND id=?',[...array_values($changes),$h,$row['id']]);
   $c->audit($h,$u,$kind.'.'.$action,$row['uuid'],['reason'=>$reason,'before'=>$row,'after'=>$changes]);$result=['ok'=>true,'uuid'=>$row['uuid'],'row_version'=>$changes['row_version']];
   $c->insert('command_receipts',['household_id'=>$h,'command_key'=>$command,'payload_hash'=>$hash,'result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>Core::now()]);return $result;
  });
 }
}
