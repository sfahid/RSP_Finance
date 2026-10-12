<?php
declare(strict_types=1);
namespace ReadyFinance;
final class CategoryService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $name=Core::text($p['name']??'');$type=$p['type']??'expense';if(!in_array($type,['income','expense'],true))throw new Fault('Choose income or expense.');$lid=$c->ledger($h,$name,$type);$c->insert('categories',$row+['ledger_id'=>$lid,'name'=>$name,'type'=>$type]);
 }
}
