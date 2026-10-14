<?php
declare(strict_types=1);
namespace ReadyFinance;
final class RecurrenceService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $cat=$c->scope('categories',$p['category_uuid']??'',$h);if(!(int)($c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']])['active']??1))throw new Fault('Restore this expense account first.');if($cat['type']!=='expense')throw new Fault('Choose an expense category.');$freq=$p['frequency']??'monthly';if(!in_array($freq,['weekly','monthly','yearly'],true))throw new Fault('Choose weekly monthly or yearly.');$date=Core::date($p['next_date']??'');$c->insert('recurring_rules',$row+['name'=>Core::text($p['name']??''),'amount'=>Core::money($p['amount']??''),'category_id'=>$cat['id'],'frequency'=>$freq,'next_date'=>$date,'anchor_day'=>(int)substr($date,8,2)]);
 }
 public function runRecurring(int $h,int $u):array {return ['generated'=>0,'retired'=>true];}
}
