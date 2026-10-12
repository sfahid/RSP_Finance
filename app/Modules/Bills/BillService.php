<?php
declare(strict_types=1);
namespace ReadyFinance;
final class BillService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $cat=$c->scope('categories',$p['category_uuid']??'',$h);if(!(int)($c->one('SELECT * FROM ledger_accounts WHERE household_id=? AND id=?',[$h,$cat['ledger_id']])['active']??1))throw new Fault('Restore this expense account first.');if($cat['type']!=='expense')throw new Fault('Choose an expense category.');$id=$c->insert('bills',$row+['name'=>Core::text($p['name']??''),'amount'=>Core::money($p['amount']??''),'due_date'=>Core::date($p['due_date']??''),'category_id'=>$cat['id'],'created_at'=>Core::now()]);
 }
}
