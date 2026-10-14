<?php
declare(strict_types=1);
$source=getenv('RPF_SOURCE_DATABASE');$target=getenv('RPF_RESTORE_DATABASE');if(!preg_match('/^readyfinance_(entrytest|restore)_[a-z0-9_]+$/D',$source?:'')||!preg_match('/^readyfinance_restore_[a-z0-9_]+$/D',$target?:''))throw new RuntimeException('Synthetic restore databases required.');
$p=new PDO('mysql:host=127.0.0.1;port=3306','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$n=0;
foreach(['parties'=>'id','party_accounts'=>'household_id,ledger_id','party_entries'=>'household_id,transaction_id'] as $table=>$order){$old=$p->query('SELECT * FROM `'.$source.'`.'.$table.' ORDER BY '.$order)->fetchAll();$new=$p->query('SELECT * FROM `'.$target.'`.'.$table.' ORDER BY '.$order)->fetchAll();if($old!==$new)throw new RuntimeException($table.' restore differs');$n++;}
if(!$p->query('SELECT COUNT(*) FROM `'.$target.'`.schema_migrations WHERE version="008_parties"')->fetchColumn())throw new RuntimeException('Party upgrade missing');$n++;echo $n.' additional party restore checks passed.'.PHP_EOL;
