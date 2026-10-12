<?php
declare(strict_types=1);
namespace ReadyFinance;
final class MemberService {
 public function __construct(private Core $c){}
 public function create(int $h,int $u,array $p,string $uuid):void {
  $c=$this->c;$row=['uuid'=>$uuid,'household_id'=>$h];
  $c->insert('members',$row+['name'=>Core::text($p['name']??''),'relationship'=>Core::text($p['relationship']??'Member',80),'created_at'=>Core::now()]);
 }
}
