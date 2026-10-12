<?php
declare(strict_types=1);
namespace ReadyFinance;
final class ImportService {
 public function __construct(private Core $c){}
 public function ready():bool{return (bool)$this->c->query("SELECT COUNT(*) FROM schema_migrations WHERE version='004_statement_import'")->fetchColumn();}
 public function upgrade():array {
  $c=$this->c;$locked=$c->query('SELECT GET_LOCK("rpf_statement_upgrade",10)')->fetchColumn();if(!$locked)throw new Fault('Upgrade is busy.',409);
  try{$c->db->exec(file_get_contents(dirname(__DIR__,3).'/database/004_statement_import.sql'));return ['ok'=>true];}finally{$c->query('SELECT RELEASE_LOCK("rpf_statement_upgrade")');}
 }
 private function requireReady():void{if(!$this->ready())throw new Fault('The owner must enable statement imports first.',503);}
 public function upload(int $h,int $u,array $file,string $accountUuid,string $sheet=''):array {
  $this->requireReady();$c=$this->c;$account=$c->scope('accounts',$accountUuid,$h);if(!in_array($account['type'],['bank','savings','wallet'],true)||!$account['active'])throw new Fault('Select an active PKR bank, savings or wallet account.');
  if(($file['error']??-1)!==UPLOAD_ERR_OK||($file['size']??0)>5000000)throw new Fault('Choose a statement smaller than 5 MB.');
  $source=StatementReader::read($file['tmp_name'],$file['name'],$sheet);$uuid=Core::uuid();
  $c->atomic(function()use($c,$h,$u,$file,$source,$account,$uuid){$c->insert('import_batches',['uuid'=>$uuid,'household_id'=>$h,'account_id'=>$account['id'],'created_by'=>$u,'filename'=>mb_substr(basename($file['name']),0,190),'file_hash'=>hash_file('sha256',$file['tmp_name']),'sheet'=>$source['sheet'],'source_json'=>json_encode($source,JSON_THROW_ON_ERROR),'created_at'=>Core::now()]);$c->audit($h,$u,'statement.staged',$uuid,['rows'=>count($source['rows'])]);});
  return $this->map($h,$u,$uuid,$source['mapping'],'dmy');
 }
 public function map(int $h,int $u,string $uuid,array $map,string $dateFormat):array {
  $this->requireReady();$c=$this->c;$c->atomic(function()use($c,$h,$u,$uuid,$map,$dateFormat){$batch=$c->scope('import_batches',$uuid,$h,true);
   if($c->query("SELECT COUNT(*) FROM import_rows WHERE batch_id=? AND status<>'pending'",[$batch['id']])->fetchColumn()>0)throw new Fault('This batch has reviewed rows. Upload again to change its mapping.');
   $source=json_decode($batch['source_json'],true,64,JSON_THROW_ON_ERROR);$normal=[];
   foreach(['date','description','reference','debit','credit','amount','balance','direction'] as $key){$i=$map[$key]??-1;if(!is_numeric($i)||((int)$i)<-1||((int)$i)>=count($source['headers']))throw new Fault('Invalid column mapping.');$normal[$key]=(int)$i;}
   if($normal['date']<0||$normal['description']<0||($normal['debit']<0&&$normal['credit']<0&&$normal['amount']<0))throw new Fault('Map Date, Description and Debit/Credit or Amount.');
   if(!in_array($dateFormat,['dmy','mdy'],true))throw new Fault('Choose a valid date format.');
   // UPDATE preserves row identity on remapping. No DELETE privilege is needed.
   $ordinal=[];foreach($source['rows'] as $index=>$raw){$values=['household_id'=>$h,'batch_id'=>$batch['id'],'source_row'=>$index+1,'effective_date'=>null,'description'=>'','reference_text'=>'','amount'=>null,'direction'=>null,'bank_balance'=>null,'fingerprint'=>null,'error_text'=>null];
    $cell=fn($k)=>$normal[$k]<0?'':trim((string)($raw[$normal[$k]]??''));
    try{
     if(strtolower(pathinfo($batch['filename'],PATHINFO_EXTENSION))==='pdf'&&count($raw)!==count($source['headers']))throw new Fault('PDF column count is ambiguous. Use CSV/Excel or skip this row.');
     $values['effective_date']=StatementReader::date($cell('date'),$dateFormat);$values['description']=Core::text($cell('description'),500);$values['reference_text']=mb_substr($cell('reference'),0,500);
     if($normal['debit']>=0||$normal['credit']>=0){$dr=StatementReader::amount($cell('debit'))??'0';$cr=StatementReader::amount($cell('credit'))??'0';$d=bccomp($dr,'0',6)>0;$in=bccomp($cr,'0',6)>0;if($d===$in)throw new Fault('Exactly one of Debit or Credit must be greater than zero.');$values['direction']=$d?'out':'in';$values['amount']=$d?$dr:$cr;}
     else{$a=StatementReader::amount($cell('amount'),true);if($a===null||bccomp($a,'0',6)===0)throw new Fault('Transaction amount is missing or zero.');$direction=strtolower($cell('direction'));if($normal['direction']>=0&&!in_array($direction,['dr','debit','out','withdrawal','cr','credit','in','deposit'],true))throw new Fault('Direction must be DR/CR, Debit/Credit or Out/In.');$values['direction']=$normal['direction']>=0?(in_array($direction,['dr','debit','out','withdrawal'],true)?'out':'in'):(bccomp($a,'0',6)<0?'out':'in');$values['amount']=bccomp($a,'0',6)<0?bcsub('0',$a,6):$a;}
     $values['bank_balance']=StatementReader::amount($cell('balance'),true);
     if($values['effective_date']>$c->today($h))throw new Fault('Future statement date cannot be posted.');
     $key=json_encode([$values['effective_date'],preg_replace('/\s+/u',' ',mb_strtolower($values['description'])),preg_replace('/\s+/u',' ',mb_strtolower($values['reference_text'])),$values['direction'],$values['amount'],$values['bank_balance']],JSON_THROW_ON_ERROR);$occurrence=($ordinal[$key]??0)+1;$ordinal[$key]=$occurrence;$values['fingerprint']=hash('sha256',$key.'|'.$occurrence.($values['bank_balance']===null?'|'.$batch['file_hash']:''));
    }catch(Fault $e){$values['error_text']=mb_substr($e->getMessage(),0,500);}
    $existing=$c->one('SELECT id FROM import_rows WHERE batch_id=? AND source_row=?',[$batch['id'],$index+1]);if($existing){$keys=array_keys($values);$c->query('UPDATE import_rows SET '.implode(',',array_map(fn($k)=>$k.'=?',$keys)).' WHERE id=?',array_merge(array_values($values),[$existing['id']]));}else $c->insert('import_rows',$values);
   }
   $c->query("UPDATE import_batches SET mapping_json=?,status='ready' WHERE id=?",[json_encode(['columns'=>$normal,'date_format'=>$dateFormat],JSON_THROW_ON_ERROR),$batch['id']]);$c->audit($h,$u,'statement.mapped',$uuid);
  });return $this->view($h,$uuid);
 }
 public function listing(int $h):array {
  if(!$this->ready())return ['ready'=>false,'batches'=>[]];return ['ready'=>true,'batches'=>$this->c->all('SELECT b.uuid,b.filename,b.sheet,b.status,b.created_at,a.name account_name FROM import_batches b JOIN accounts a ON a.id=b.account_id WHERE b.household_id=? ORDER BY b.id DESC LIMIT 50',[$h])];
 }
 public function view(int $h,string $uuid):array {
  $this->requireReady();$c=$this->c;$b=$c->scope('import_batches',$uuid,$h);$source=json_decode($b['source_json'],true,64,JSON_THROW_ON_ERROR);$account=$c->one('SELECT * FROM accounts WHERE household_id=? AND id=?',[$h,$b['account_id']]);
  $rows=$c->all('SELECT r.*,l.transaction_id duplicate_id FROM import_rows r LEFT JOIN import_links l ON l.household_id=r.household_id AND l.account_id=? AND l.fingerprint=r.fingerprint WHERE r.household_id=? AND r.batch_id=? ORDER BY r.source_row',[$b['account_id'],$h,$b['id']]);
  $signed=fn($r)=>$r['direction']==='out'?bcsub('0',$r['amount'],6):$r['amount'];$valid=array_values(array_filter($rows,fn($r)=>!$r['error_text']));$withBalances=count($valid)>0&&count(array_filter($valid,fn($r)=>$r['bank_balance']!==null))===count($valid);$forward=0;$reverse=0;
  if($withBalances)for($i=1;$i<count($valid);$i++){if(bccomp($valid[$i]['bank_balance'],bcadd($valid[$i-1]['bank_balance'],$signed($valid[$i]),6),6)!==0)$forward++;if(bccomp($valid[$i-1]['bank_balance'],bcadd($valid[$i]['bank_balance'],$signed($valid[$i-1]),6),6)!==0)$reverse++;}
  $newestFirst=$withBalances?$reverse<$forward:(count($valid)>1&&$valid[0]['effective_date']>$valid[count($valid)-1]['effective_date']);$chronological=$newestFirst?array_reverse($valid):$valid;$debits='0';$credits='0';foreach($valid as $r)if($r['direction']==='out')$debits=bcadd($debits,$r['amount'],6);else $credits=bcadd($credits,$r['amount'],6);
  $warning=[];if(!$withBalances)$warning[]='Running balances are missing; compare totals before posting. Automatic duplicates are limited to the same file; check existing-entry suggestions for overlapping statements.';elseif(min($forward,$reverse)>0)$warning[]='Running balances do not reconcile. Check mapping, row order and missing transactions before posting.';
  if(count($valid)!==count($rows))$warning[]='Some rows are invalid. They cannot be posted; correct the mapping or skip them.';
  if($source['mapping']['amount']>=0&&$source['mapping']['direction']<0&&$source['mapping']['debit']<0&&$source['mapping']['credit']<0)$warning[]='Signed Amount mapping: positive values are incoming and negative values are outgoing. Confirm this convention.';
  $start=$chronological[0]??null;$end=$chronological[count($chronological)-1]??null;$summary=['rows'=>count($rows),'valid'=>count($valid),'debits'=>$debits,'credits'=>$credits,'order'=>$newestFirst?'newest first':'oldest first','opening'=>$withBalances&&$start?bcsub($start['bank_balance'],$signed($start),6):null,'closing'=>$withBalances&&$end?$end['bank_balance']:null,'warnings'=>$warning];
  foreach($rows as &$r){$r['matches']=[];if(!$r['error_text']&&$r['status']==='pending'&&!$r['duplicate_id']){$sum=$signed($r);$r['matches']=$c->all("SELECT t.uuid,t.description,t.type FROM transactions t JOIN journal_entries je ON je.transaction_id=t.id JOIN journal_lines jl ON jl.journal_id=je.id WHERE t.household_id=? AND t.effective_date=? AND t.status='posted' AND t.type NOT IN ('opening','reversal') AND jl.household_id=? AND jl.ledger_id=? GROUP BY t.id HAVING SUM(jl.debit-jl.credit)=? LIMIT 5",[$h,$r['effective_date'],$h,$account['ledger_id'],$sum]);}$r['possible_transfer']=preg_match('/transfer|raast|cash|cdm|atm|loan/i',$r['description'].' '.$r['reference_text'])===1;}unset($r);
  return ['batch'=>['uuid'=>$b['uuid'],'filename'=>$b['filename'],'account_uuid'=>$account['uuid'],'account_name'=>$account['name'],'status'=>$b['status'],'sheet'=>$b['sheet']],'headers'=>$source['headers'],'sheets'=>$source['sheets'],'mapping'=>json_decode($b['mapping_json']??'{}',true),'sample'=>array_slice($source['rows'],0,3),'rows'=>$rows,'summary'=>$summary];
 }
 public function commit(int $h,int $u,string $uuid,array $choices,bool $acknowledged):array {
  $this->requireReady();if(!$acknowledged)throw new Fault('Confirm that you checked dates, balances and classifications.');if(count($choices)<1||count($choices)>100)throw new Fault('Review between 1 and 100 rows at a time.');
  $c=$this->c;$result=$c->atomic(function()use($c,$h,$u,$uuid,$choices){$c->one('SELECT id FROM households WHERE id=? FOR UPDATE',[$h]);$batch=$c->scope('import_batches',$uuid,$h,true);$account=$c->one('SELECT * FROM accounts WHERE id=? AND household_id=?',[$batch['account_id'],$h]);$counts=['posted'=>0,'matched'=>0,'skipped'=>0,'duplicates'=>0];$ids=[];
   foreach($choices as $choice){$id=(int)($choice['id']??0);if(isset($ids[$id]))throw new Fault('A row was selected twice.');$ids[$id]=true;$r=$c->one('SELECT * FROM import_rows WHERE household_id=? AND batch_id=? AND id=? FOR UPDATE',[$h,$batch['id'],$id]);if(!$r)throw new Fault('Statement row does not belong to this household.',404);if($r['status']!=='pending')continue;$type=$choice['type']??'skip';
    $existing=$r['fingerprint']?$c->one('SELECT * FROM import_links WHERE household_id=? AND account_id=? AND fingerprint=?',[$h,$account['id'],$r['fingerprint']]):null;
    if($existing){$c->query("UPDATE import_rows SET status='duplicate',transaction_id=? WHERE id=?",[$existing['transaction_id'],$id]);$counts['duplicates']++;continue;}
    if($type==='skip'){$c->query("UPDATE import_rows SET status='skipped' WHERE id=?",[$id]);$counts['skipped']++;continue;}if($r['error_text'])throw new Fault('Row '.$r['source_row'].': '.$r['error_text']);
    if($type==='match'){$t=$c->scope('transactions',(string)($choice['transaction_uuid']??''),$h);$effect=$c->query('SELECT COALESCE(SUM(jl.debit-jl.credit),0) FROM journal_lines jl JOIN journal_entries je ON je.id=jl.journal_id WHERE je.transaction_id=? AND jl.household_id=? AND jl.ledger_id=?',[$t['id'],$h,$account['ledger_id']])->fetchColumn();$signed=$r['direction']==='out'?bcsub('0',$r['amount'],6):$r['amount'];if($t['status']!=='posted'||in_array($t['type'],['opening','reversal'],true)||$t['effective_date']!==$r['effective_date']||bccomp($effect,$signed,6)!==0)throw new Fault('Matched transaction must have the same bank account, date, direction and amount.');$status='matched';}
    else {
     $allowed=$r['direction']==='out'?['expense','transfer','lend','repay']:['income','refund','transfer','borrow','collect'];if(!in_array($type,$allowed,true))throw new Fault('Row '.$r['source_row'].': classification does not match bank direction.');
     $p=['type'=>$type,'date'=>$r['effective_date'],'description'=>$r['description'],'amount'=>$r['amount'],'account_uuid'=>$account['uuid']];
     if(in_array($type,['expense','income','refund'],true))$p['category_uuid']=$choice['category_uuid']??'';
     if($type==='transfer'){$other=$c->scope('accounts',(string)($choice['other_account_uuid']??''),$h);if($other['id']===$account['id'])throw new Fault('Choose another account for transfers.');if($r['direction']==='in'){$p['account_uuid']=$other['uuid'];$p['to_account_uuid']=$account['uuid'];}else $p['to_account_uuid']=$other['uuid'];}
     if(in_array($type,['borrow','lend','repay','collect'],true))$p['obligation_uuid']=$choice['obligation_uuid']??'';
     $t=(new Accounting($c))->postInTransaction($h,$u,$p,'bank:'.$account['uuid'].':'.$r['fingerprint']);$status='posted';
    }
    if($c->one('SELECT transaction_id FROM import_links WHERE household_id=? AND account_id=? AND transaction_id=?',[$h,$account['id'],$t['id']]))throw new Fault('This bank entry is already linked to another statement row. Check the earlier import or skip this row.');
    $c->insert('import_links',['household_id'=>$h,'account_id'=>$account['id'],'fingerprint'=>$r['fingerprint'],'transaction_id'=>$t['id'],'batch_id'=>$batch['id']]);$c->query('UPDATE import_rows SET status=?,transaction_id=? WHERE id=?',[$status,$t['id'],$id]);$counts[$status]++;
   }
   $pending=$c->query("SELECT COUNT(*) FROM import_rows WHERE batch_id=? AND status='pending'",[$batch['id']])->fetchColumn();$c->query('UPDATE import_batches SET status=? WHERE id=?',[$pending?'reviewing':'complete',$batch['id']]);$c->audit($h,$u,'statement.reviewed',$uuid,$counts);return $counts;
  });return ['result'=>$result,'preview'=>$this->view($h,$uuid)];
 }
}
