<?php
declare(strict_types=1);
namespace ReadyFinance;

final class Fault extends \RuntimeException { public function __construct(string $message, public int $status=422){parent::__construct($message);} }
final class Core {
 public \PDO $db; public array $config;
 public function __construct(){
  $path=getenv('RPF_CONFIG')?:dirname(__DIR__,2).'/config/local.php'; if(!is_file($path))throw new Fault('Run the installation command before opening the app.',503);
  $this->config=require $path;
  $this->db=new \PDO($this->config['dsn'],$this->config['user'],$this->config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);
  $this->db->exec("SET time_zone = '+00:00'");
  if($this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE<>'InnoDB'")->fetchColumn()>0)throw new Fault('Database storage must be InnoDB. Run the storage migration before using the app.',503);
 }
 public static function uuid():string { $time=(int)floor(microtime(true)*1000);$x=substr(pack('J',$time),2).random_bytes(10);$x[6]=chr((ord($x[6])&15)|112);$x[8]=chr((ord($x[8])&63)|128);$h=bin2hex($x);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
 public static function now():string{return gmdate('Y-m-d H:i:s');}
 public static function money(mixed $v,bool $zero=false):string { $s=is_string($v)?trim($v):'';if(!preg_match('/^\d{1,12}(\.\d{1,6})?$/D',$s)||bccomp($s,bcadd($s,'0',2),6)!==0)throw new Fault('Enter a valid amount with at most two decimal places.');$s=bcadd($s,'0',6);if(bccomp($s,'0',6)<($zero?0:1))throw new Fault('Amount must be greater than zero.');return $s; }
 public static function text(mixed $v,int $max=150):string{ $s=is_string($v)?trim($v):'';if($s===''||mb_strlen($s)>$max)throw new Fault('Enter a value between 1 and '.$max.' characters.');return $s; }
 public static function date(mixed $v):string { $s=is_string($v)?$v:'';$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)throw new Fault('Choose a valid date.');return $s; }
 public function query(string $sql,array $p=[]):\PDOStatement{ $q=$this->db->prepare($sql);$q->execute($p);return $q; }
 public function one(string $sql,array $p=[]):?array {return $this->query($sql,$p)->fetch()?:null;}
 public function all(string $sql,array $p=[]):array{return $this->query($sql,$p)->fetchAll();}
 public function insert(string $table,array $row):int { $keys=array_keys($row);$this->query('INSERT INTO '.$table.' (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($row));return (int)$this->db->lastInsertId(); }
 public function scope(string $table,string $uuid,int $hid,bool $lock=false):array{ $r=$this->one('SELECT * FROM '.$table.' WHERE uuid=? AND household_id=?'.($lock?' FOR UPDATE':''),[$uuid,$hid]);if(!$r)throw new Fault('This item is unavailable.',404);return $r; }
 public function atomic(callable $fn):mixed { $this->db->beginTransaction();try{$r=$fn();$this->db->commit();return $r;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;} }
 public function audit(int $hid,?int $uid,string $action,?string $uuid,array $details=[]):void{$this->insert('audit_events',['uuid'=>self::uuid(),'household_id'=>$hid,'actor_id'=>$uid,'action'=>$action,'entity_uuid'=>$uuid,'details'=>json_encode($details,JSON_THROW_ON_ERROR),'created_at'=>self::now()]);}
 public function ledger(int $hid,string $name,string $class,?string $key=null):int{return $this->insert('ledger_accounts',['uuid'=>self::uuid(),'household_id'=>$hid,'name'=>$name,'class'=>$class,'system_key'=>$key]);}
 public function system(int $hid,string $key):int{$r=$this->one('SELECT id FROM ledger_accounts WHERE household_id=? AND system_key=?',[$hid,$key]);if(!$r)throw new Fault('Required account mapping is unavailable.',500);return (int)$r['id'];}
 public function today(int $hid):string {$h=$this->one('SELECT timezone FROM households WHERE id=?',[$hid]);return (new \DateTimeImmutable('now',new \DateTimeZone($h['timezone'])))->format('Y-m-d');}
 public function balance(int $hid,int $ledger):string{return (string)$this->query('SELECT COALESCE(SUM(debit-credit),0) FROM journal_lines WHERE household_id=? AND ledger_id=?',[$hid,$ledger])->fetchColumn();}
 public function guardPeriod(int $hid,string $date):void { $m=substr($date,0,7);$this->query('INSERT IGNORE INTO periods(household_id,month,locked) VALUES(?,?,0)',[$hid,$m]);$r=$this->one('SELECT locked FROM periods WHERE household_id=? AND month=? FOR UPDATE',[$hid,$m]);if($r['locked'])throw new Fault('This financial month is locked. Reopen it or use a current-period reversal.',409);}
}
