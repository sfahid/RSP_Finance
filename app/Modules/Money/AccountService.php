<?php
declare(strict_types=1);
namespace ReadyFinance;
final class AccountService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $name=Core::text($p['name']??'');$type=$p['type']??'bank';if(!in_array($type,['cash','bank','wallet','savings','credit_card'],true))throw new Fault('Choose a supported account type.');$lid=$c->ledger($h,$name,$type==='credit_card'?'liability':'asset');(new ChartService($c))->attach($h,$lid,(string)($p['parent_uuid']??''),$type);$id=$c->insert('accounts',$row+['ledger_id'=>$lid,'name'=>$name,'type'=>$type,'currency'=>'PKR','created_at'=>Core::now()]);$opening=Core::money($p['opening']??'0',true);if(bccomp($opening,'0',6)>0)(new Accounting($c))->postInTransaction($h,$u,['type'=>'opening','amount'=>$opening,'account_uuid'=>$uuid,'date'=>Core::date($p['date']??$c->today($h)),'description'=>'Opening balance for '.$name],'opening:'.$uuid);
 }
}
