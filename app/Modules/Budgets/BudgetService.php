<?php
declare(strict_types=1);
namespace ReadyFinance;
final class BudgetService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $cat=$c->scope('categories',$p['category_uuid']??'',$h);if(!(int)($c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']])['active']??1))throw new Fault('Restore this expense account first.');if($cat['type']!=='expense')throw new Fault('Choose an expense category.');$month=substr(Core::date(($p['month']??'').'-01'),0,7);$a=Core::money($p['amount']??'');$old=$c->one('SELECT uuid FROM budgets WHERE household_id=? AND category_id=? AND month=?',[$h,$cat['id'],$month]);if($old){$uuid=$old['uuid'];$c->query('UPDATE budgets SET amount=? WHERE household_id=? AND uuid=?',[$a,$h,$uuid]);}else{$c->insert('budgets',$row+['category_id'=>$cat['id'],'month'=>$month,'amount'=>$a]);}
 }
}
