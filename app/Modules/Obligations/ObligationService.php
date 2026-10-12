<?php
declare(strict_types=1);
namespace ReadyFinance;
final class ObligationService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $direction=$p['direction']??'borrowed';if(!in_array($direction,['borrowed','lent'],true))throw new Fault('Choose borrowed or lent.');$name=Core::text($p['name']??'');$amount=Core::money($p['amount']??'');$lid=$c->ledger($h,$name,$direction==='borrowed'?'liability':'asset');$c->insert('obligations',$row+['name'=>$name,'direction'=>$direction,'ledger_id'=>$lid,'original_amount'=>$amount,'due_date'=>empty($p['due_date'])?null:Core::date($p['due_date']),'created_at'=>Core::now()]);(new Accounting($c))->postInTransaction($h,$u,['type'=>$direction==='borrowed'?'borrow':'lend','obligation_uuid'=>$uuid,'account_uuid'=>$p['account_uuid']??'','amount'=>$amount,'date'=>Core::date($p['date']??$c->today($h)),'description'=>$name],'debt:'.$uuid);
 }
}
