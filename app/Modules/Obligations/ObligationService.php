<?php
declare(strict_types=1);
namespace ReadyFinance;
final class ObligationService {
 public function __construct(private Core $c){}
 /** Attach tracking to an existing posting account without inventing a cash movement. Caller holds the household lock. */
 public function track(int $h,int $u,string $ledgerUuid,string $direction):array {
  $c=$this->c;$ledger=$c->scope('ledger_accounts',$ledgerUuid,$h,true);
  $old=$c->one('SELECT * FROM obligations WHERE household_id=? AND ledger_id=?',[$h,$ledger['id']]);
  if(!in_array($direction,['lent','borrowed'],true))throw new Fault('Choose a receivable or payable direction.');
  $class=$direction==='lent'?'asset':'liability';$kind=$direction==='lent'?'receivable':'payable';
  if($ledger['class']!==$class||($ledger['is_group']??0)||!(int)($ledger['active']??1)||(!$old&&($ledger['account_kind']??'')!==$kind))throw new Fault('Choose an active '.$kind.' subaccount.');
  if($old){if($old['direction']!==$direction)throw new Fault('The account direction does not match.');return $old;}
  $balance=$c->balance($h,(int)$ledger['id']);$remaining=$direction==='lent'?$balance:bcsub('0',$balance,6);
  if(bccomp($remaining,'0',6)<0)throw new Fault('This account has a contrary balance. Correct it before tracking settlements.');
  $uuid=Core::uuid();$c->insert('obligations',['uuid'=>$uuid,'household_id'=>$h,'name'=>$ledger['name'],'direction'=>$direction,'ledger_id'=>$ledger['id'],'original_amount'=>$remaining,'due_date'=>null,'created_at'=>Core::now()]);
  $c->audit($h,$u,'obligation.tracked',$uuid,['ledger_uuid'=>$ledgerUuid,'opening_posted'=>false]);return $c->scope('obligations',$uuid,$h);
 }
 public function createParty(int $h,int $u,array $p,string $key):array {
  if(!(new ChartService($this->c))->ready())throw new Fault('Enable the chart of accounts first.');
  if(!preg_match('/^[a-zA-Z0-9_.:-]{8,150}$/D',$key))throw new Fault('A valid command key is required.');
  $c=$this->c;return $c->atomic(function()use($c,$h,$u,$p,$key){
   $c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$command='party:'.$key;$hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));$old=$c->one('SELECT * FROM command_receipts WHERE household_id=? AND command_key=?',[$h,$command]);
   if($old){if($old['payload_hash']!==$hash)throw new Fault('Command key conflict.',409);return json_decode($old['result_json'],true);}
   $kind=$p['kind']??'';if(!in_array($kind,['receivable','payable'],true))throw new Fault('Choose Receivable or Payable.');$name=Core::text($p['name']??'',120);
   if($c->one('SELECT id FROM ledger_accounts WHERE household_id=? AND name=? AND account_kind=?',[$h,$name,$kind]))throw new Fault('An account with this name already exists. Select it from the list.');
   $lid=$c->ledger($h,$name,$kind==='receivable'?'asset':'liability');(new ChartService($c))->attach($h,$lid,'',$kind);$ledger=$c->one('SELECT uuid FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$lid]);
   $ob=$this->track($h,$u,$ledger['uuid'],$kind==='receivable'?'lent':'borrowed');$result=['ledger_uuid'=>$ledger['uuid'],'obligation_uuid'=>$ob['uuid']];
   $c->insert('command_receipts',['household_id'=>$h,'command_key'=>$command,'payload_hash'=>$hash,'result_json'=>json_encode($result),'created_at'=>Core::now()]);$c->audit($h,$u,'party.created',$ledger['uuid'],['kind'=>$kind,'name'=>$name]);return $result;
  });
 }
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $direction=$p['direction']??'borrowed';if(!in_array($direction,['borrowed','lent'],true))throw new Fault('Choose borrowed or lent.');$name=Core::text($p['name']??'');$amount=Core::money($p['amount']??'');$lid=$c->ledger($h,$name,$direction==='borrowed'?'liability':'asset');(new ChartService($c))->attach($h,$lid,(string)($p['parent_uuid']??''),$direction==='borrowed'?'payable':'receivable');$c->insert('obligations',$row+['name'=>$name,'direction'=>$direction,'ledger_id'=>$lid,'original_amount'=>$amount,'due_date'=>empty($p['due_date'])?null:Core::date($p['due_date']),'created_at'=>Core::now()]);(new Accounting($c))->postInTransaction($h,$u,['type'=>$direction==='borrowed'?'borrow':'lend','obligation_uuid'=>$uuid,'account_uuid'=>$p['account_uuid']??'','amount'=>$amount,'date'=>Core::date($p['date']??$c->today($h)),'description'=>$name],'debt:'.$uuid);
 }
}
