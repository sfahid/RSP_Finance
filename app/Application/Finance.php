<?php
declare(strict_types=1);
namespace ReadyFinance;
/** Application command facade. Domain services own their rules. */
final class Finance {
 public function __construct(public Core $c){}
 public function setup(array $p):array{return (new HouseholdService($this->c))->setup($p);}
 public function snapshot(int $h,array $u):array{return (new ReportingService($this->c))->snapshot($h,$u);}
 public function reserve(int $h,int $u,array $p,string $key):array{return (new FundService($this->c))->reserve($h,$u,$p,$key);}
 public function runRecurring(int $h,int $u):array{return (new RecurrenceService($this->c))->runRecurring($h,$u);}
 public function create(int $h,int $u,string $kind,array $p,?string $key=null):array{
  $c=$this->c;$key=$key??Core::uuid();if(!preg_match('/^[a-zA-Z0-9_.:-]{8,180}$/D',$key))throw new Fault('A valid command key is required.');$hash=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));$command=$kind.':'.$key;return $c->atomic(function()use($c,$h,$u,$kind,$p,$hash,$command){$c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$old=$c->one('SELECT * FROM command_receipts WHERE household_id=? AND command_key=?',[$h,$command]);if($old){if(!hash_equals($old['payload_hash'],$hash))throw new Fault('Command key conflict.',409);return json_decode($old['result_json'],true,32,JSON_THROW_ON_ERROR);}$uuid=Core::uuid();$row=['uuid'=>$uuid,'household_id'=>$h];
   switch($kind){
   case 'account':(new AccountService($c))->create($h,$u,$p,$uuid);break;
   case 'bill':(new BillService($c))->create($h,$u,$p,$uuid);break;
   case 'budget':(new BudgetService($c))->create($h,$u,$p,$uuid);break;
   case 'fund':(new FundService($c))->create($h,$u,$p,$uuid);break;
   case 'member':(new MemberService($c))->create($h,$u,$p,$uuid);break;
   case 'category':(new CategoryService($c))->create($h,$u,$p,$uuid);break;
   case 'debt':(new ObligationService($c))->create($h,$u,$p,$uuid);break;
   case 'recurring':(new RecurrenceService($c))->create($h,$u,$p,$uuid);break;
   default:throw new Fault('Unknown operation.',404);
   }$c->audit($h,$u,$kind.'.saved',$uuid);$result=['uuid'=>$uuid];$c->insert('command_receipts',['household_id'=>$h,'command_key'=>$command,'payload_hash'=>$hash,'result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>Core::now()]);return $result;});
 }
}
