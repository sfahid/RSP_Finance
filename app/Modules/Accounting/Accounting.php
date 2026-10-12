<?php
declare(strict_types=1);
namespace ReadyFinance;
final class Accounting {
 public function __construct(private Core $c){}
 public function post(int $hid,int $uid,array $p,string $key):array {
  if(!preg_match('/^[a-zA-Z0-9_.:-]{8,180}$/D',$key))throw new Fault('A valid command key is required.');
  return $this->c->atomic(fn()=>$this->postInTransaction($hid,$uid,$p,$key));
 }
 public function postInTransaction(int $h,int $u,array $p,string $key):array {
  $c=$this->c;$hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));
  // Household row serializes financial commands, reservations and period locks.
  $c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);
  $existing=$c->one('SELECT * FROM transactions WHERE household_id=? AND source_key=?',[$h,$key]);
  if($existing){if(!hash_equals($existing['payload_hash'],$hash))throw new Fault('This command key was already used for different data.',409);return $existing;}
  $date=Core::date($p['date']??$c->today($h));$c->guardPeriod($h,$date);if($date>$c->today($h))throw new Fault('Use a bill or recurring schedule for future commitments.');
  $type=$p['type']??'expense';$amount=Core::money($p['amount']??'');$desc=Core::text($p['description']??ucfirst($type),500);
  $account=$c->scope('accounts',(string)($p['account_uuid']??''),$h);if(!$account['active'])throw new Fault('This account is inactive.');
  $line=fn(int $ledger,string $debit,string $credit,?int $cat=null,?int $member=null,?string $project=null)=>['ledger_id'=>$ledger,'debit'=>$debit,'credit'=>$credit,'category_id'=>$cat,'member_id'=>$member,'project'=>$project];
  $lines=[];$splits=[];
  if(in_array($type,['expense','income','refund'],true)){
   $splits=$p['splits']??[['category_uuid'=>$p['category_uuid']??'','amount'=>$amount,'member_uuid'=>$p['member_uuid']??null,'project'=>$p['project']??null,'tags'=>$p['tags']??null]];
   if(!is_array($splits)||count($splits)<1||count($splits)>50)throw new Fault('Use between 1 and 50 splits.');$total='0';
   foreach($splits as &$s){$cat=$c->scope('categories',(string)($s['category_uuid']??''),$h);if($cat['type']!==($type==='income'?'income':'expense'))throw new Fault('Choose a compatible category.');if(!(int)($c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']])['active']??1))throw new Fault('This income or expense account is archived. Restore it or choose another account.');$m=isset($s['member_uuid'])&&$s['member_uuid']?$c->scope('members',$s['member_uuid'],$h)['id']:null;$a=Core::money($s['amount']??'');$total=bcadd($total,$a,6);$s['category_id']=$cat['id'];$s['member_id']=$m;$s['amount']=$a;$s['project']=!empty($s['project'])?Core::text($s['project'],120):null;
    $lines[]=$line((int)$cat['ledger_id'],$type==='expense'?$a:'0',$type==='expense'?'0':$a,(int)$cat['id'],$m,$s['project']);}
   unset($s);if(bccomp($total,$amount,6)!==0)throw new Fault('Split amounts must equal the total.');
   $lines[]=$line((int)$account['ledger_id'],$type==='expense'?'0':$amount,$type==='expense'?$amount:'0');
  }elseif($type==='transfer'){
   $dest=$c->scope('accounts',(string)($p['to_account_uuid']??''),$h);if($dest['id']===$account['id']||!$dest['active'])throw new Fault('Choose a different active destination.');
   $lines=[$line((int)$dest['ledger_id'],$amount,'0'),$line((int)$account['ledger_id'],'0',$amount)];
  }elseif($type==='opening'){
   $liability=$account['type']==='credit_card';$lines=[$line((int)$account['ledger_id'],$liability?'0':$amount,$liability?$amount:'0'),$line($c->system($h,'opening_equity'),$liability?$amount:'0',$liability?'0':$amount)];
  }elseif(in_array($type,['borrow','lend','repay','collect'],true)){
   $debt=$c->scope('obligations',(string)($p['obligation_uuid']??''),$h,true);$borrowed=$debt['direction']==='borrowed';if(!empty($debt['archived']))throw new Fault('Restore this deleted loan before recording movements.');
   $debtLedger=$c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$debt['ledger_id']]);if(!(int)($debtLedger['active']??1))throw new Fault('This receivable or payable account is archived.');
   if(($type==='borrow'||$type==='repay')!==$borrowed)throw new Fault('This action does not match the obligation direction.');
   if(in_array($type,['repay','collect'],true)){ $bal=$c->balance($h,(int)$debt['ledger_id']);$outstanding=$borrowed?bcsub('0',$bal,6):$bal;if(bccomp($amount,$outstanding,6)>0)throw new Fault('Payment exceeds remaining principal.'); }
   $cashDebit=in_array($type,['borrow','collect'],true);$lines=[$line((int)$account['ledger_id'],$cashDebit?$amount:'0',$cashDebit?'0':$amount),$line((int)$debt['ledger_id'],$cashDebit?'0':$amount,$cashDebit?$amount:'0')];
  }else{throw new Fault('Unsupported transaction type.');}
  $bill=null;if(!empty($p['bill_uuid'])){if($type!=='expense')throw new Fault('Bill settlement must be an expense.');$bill=$c->scope('bills',$p['bill_uuid'],$h,true);if(!empty($bill['archived']))throw new Fault('Restore this deleted bill before paying it.');$paid=(string)$c->query('SELECT COALESCE(SUM(amount),0) FROM bill_allocations WHERE household_id=? AND bill_id=?',[$h,$bill['id']])->fetchColumn();if(bccomp($amount,bcsub($bill['amount'],$paid,6),6)>0)throw new Fault('Payment exceeds the bill balance.');if(count($splits)!==1||$splits[0]['category_id']!==$bill['category_id'])throw new Fault('Use the bill category for this payment.');}
  $t=$this->persist($h,$u,$type,$date,$desc,$amount,$key,$hash,$lines,$splits);
  if((new PlanManagementService($c))->ready()){if(isset($debt))$c->query('UPDATE obligations SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) WHERE household_id=? AND id=?',[$h,$debt['id']]);if($bill)$c->query('UPDATE bills SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) WHERE household_id=? AND id=?',[$h,$bill['id']]);}
  if($bill)$c->insert('bill_allocations',['household_id'=>$h,'bill_id'=>$bill['id'],'transaction_id'=>$t['id'],'amount'=>$amount]);
  return $t;
 }
 private function persist(int $h,int $u,string $type,string $date,string $desc,string $amount,string $key,string $hash,array $lines,array $splits=[],?int $reverse=null):array{
  $c=$this->c;$dr='0';$cr='0';foreach($lines as $l){$dr=bcadd($dr,$l['debit'],6);$cr=bcadd($cr,$l['credit'],6);}if(count($lines)<2||bccomp($dr,$cr,6)!==0||bccomp($dr,'0',6)<=0)throw new Fault('Journal is not balanced.',500);
  $uuid=Core::uuid();$id=$c->insert('transactions',['uuid'=>$uuid,'household_id'=>$h,'type'=>$type,'effective_date'=>$date,'description'=>$desc,'amount'=>$amount,'status'=>'posted','source_key'=>$key,'payload_hash'=>$hash,'created_by'=>$u,'created_at'=>Core::now(),'reversal_of'=>$reverse]);
  $jid=$c->insert('journal_entries',['uuid'=>Core::uuid(),'household_id'=>$h,'transaction_id'=>$id,'effective_date'=>$date,'created_at'=>Core::now()]);foreach($lines as $l)$c->insert('journal_lines',['household_id'=>$h,'journal_id'=>$jid]+$l);
  foreach($splits as $s)$c->insert('transaction_splits',['household_id'=>$h,'transaction_id'=>$id,'category_id'=>$s['category_id'],'member_id'=>$s['member_id'],'project'=>$s['project'],'tags'=>isset($s['tags'])?mb_substr((string)$s['tags'],0,250):null,'amount'=>$s['amount']]);
  $c->audit($h,$u,'transaction.posted',$uuid,['type'=>$type,'amount'=>$amount]);$c->insert('outbox_events',['uuid'=>Core::uuid(),'household_id'=>$h,'event_type'=>'TransactionPosted','entity_uuid'=>$uuid,'created_at'=>Core::now()]);return $c->scope('transactions',$uuid,$h);
 }
 public function postJournal(int $h,int $u,array $p,string $key):array {
  if(!preg_match('/^[a-zA-Z0-9_.:-]{8,180}$/D',$key))throw new Fault('Command key required.');$c=$this->c;if(!(new ChartService($c))->ready())throw new Fault('Enable the chart first.');
  return $c->atomic(fn()=>$this->postJournalInTransaction($h,$u,$p,$key));
 }
 public function postJournalInTransaction(int $h,int $u,array $p,string $key):array {
  $c=$this->c;return (function()use($c,$h,$u,$p,$key){$c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));$existing=$c->one('SELECT * FROM transactions WHERE household_id=? AND source_key=?',[$h,'journal:'.$key]);if($existing){if($existing['payload_hash']!==$hash)throw new Fault('Command key conflict.',409);return $existing;}
   $date=Core::date($p['date']??'');if($date>$c->today($h))throw new Fault('Future journals cannot be posted.');$c->guardPeriod($h,$date);$description=Core::text($p['description']??'',500);$input=$p['lines']??[];if(count($input)<2||count($input)>50)throw new Fault('Use between 2 and 50 journal lines.');$lines=[];$total='0';
   foreach($input as $item){$account=$c->scope('ledger_accounts',(string)($item['account_uuid']??''),$h);if(!(int)($account['active']??1))throw new Fault('This account is archived.');if($account['is_group'])throw new Fault('Post to subaccounts, not parent groups.');$debit=Core::money($item['debit']??'0',true);$credit=Core::money($item['credit']??'0',true);if((bccomp($debit,'0',6)>0)===(bccomp($credit,'0',6)>0))throw new Fault('Each journal line needs exactly one positive debit or credit.');$category=$c->one('SELECT id FROM categories WHERE household_id=? AND ledger_id=?',[$h,$account['id']]);$lines[]=['ledger_id'=>$account['id'],'debit'=>$debit,'credit'=>$credit,'category_id'=>$category['id']??null,'member_id'=>null,'project'=>null];$total=bcadd($total,$debit,6);}
   $t=$this->persist($h,$u,'journal',$date,$description,$total,'journal:'.$key,$hash,$lines);
   foreach($c->all('SELECT ledger_id,direction FROM obligations WHERE household_id=?',[$h]) as $ob){$balance=$c->balance($h,$ob['ledger_id']);if(($ob['direction']==='borrowed'&&bccomp($balance,'0',6)>0)||($ob['direction']==='lent'&&bccomp($balance,'0',6)<0))throw new Fault('Journal would make a tracked loan principal position invalid.');}return $t;
  })();
 }
 public function reverse(int $h,int $u,string $uuid,string $date,string $reason,string $key):array{
  return $this->c->atomic(fn()=>$this->reverseInTransaction($h,$u,$uuid,$date,$reason,$key));
 }
 public function reverseInTransaction(int $h,int $u,string $uuid,string $date,string $reason,string $key,bool $checkPrincipal=true):array {
  return (function()use($h,$u,$uuid,$date,$reason,$key,$checkPrincipal){$c=$this->c;$c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$p=['uuid'=>$uuid,'date'=>$date,'reason'=>$reason];$hash=hash('sha256',json_encode($p));$old=$c->one('SELECT * FROM transactions WHERE household_id=? AND source_key=?',[$h,$key]);if($old){if($old['payload_hash']!==$hash)throw new Fault('Command key conflict.',409);return $old;}$t=$c->scope('transactions',$uuid,$h,true);if($t['status']!=='posted'||$t['type']==='reversal')throw new Fault('This transaction cannot be reversed.',409);$date=Core::date($date);if($date<$t['effective_date']||$date>$c->today($h))throw new Fault('Reversal date must be between the original date and today.');$c->guardPeriod($h,$date);$rows=$c->all('SELECT jl.* FROM journal_lines jl JOIN journal_entries j ON j.id=jl.journal_id WHERE j.transaction_id=? AND jl.household_id=?',[$t['id'],$h]);$lines=[];foreach($rows as $r)$lines[]=['ledger_id'=>$r['ledger_id'],'debit'=>$r['credit'],'credit'=>$r['debit'],'category_id'=>$r['category_id'],'member_id'=>$r['member_id'],'project'=>$r['project']];$r=$this->persist($h,$u,'reversal',$date,'Reversal: '.Core::text($reason,400),$t['amount'],$key,$hash,$lines,[],(int)$t['id']);$c->query("UPDATE transactions SET status='reversed', row_version=row_version+1 WHERE id=?",[$t['id']]);foreach($c->all('SELECT * FROM bill_allocations WHERE transaction_id=? AND household_id=?',[$t['id'],$h]) as $a)$c->insert('bill_allocations',['household_id'=>$h,'bill_id'=>$a['bill_id'],'transaction_id'=>$r['id'],'amount'=>bcsub('0',$a['amount'],6)]);if((new PlanManagementService($c))->ready()){$c->query('UPDATE bills b SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) WHERE household_id=? AND EXISTS(SELECT 1 FROM bill_allocations ba WHERE ba.household_id=b.household_id AND ba.bill_id=b.id AND ba.transaction_id=?)',[$h,$t['id']]);$c->query('UPDATE obligations o SET row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) WHERE household_id=? AND EXISTS(SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id=j.id AND l.household_id=j.household_id WHERE j.household_id=o.household_id AND j.transaction_id=? AND l.ledger_id=o.ledger_id)',[$h,$t['id']]);}if($checkPrincipal)foreach($c->all('SELECT ledger_id,direction FROM obligations WHERE household_id=?',[$h]) as $ob){$position=$c->balance($h,(int)$ob['ledger_id']);if(($ob['direction']==='borrowed'&&bccomp($position,'0',6)>0)||($ob['direction']==='lent'&&bccomp($position,'0',6)<0))throw new Fault('Reverse related repayments first so the principal position remains valid.',409);}$c->audit($h,$u,'transaction.reversed',$uuid,['reversal_uuid'=>$r['uuid'],'reason'=>$reason]);return $r;})();
 }
}
