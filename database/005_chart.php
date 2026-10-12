<?php
declare(strict_types=1);
return static function(PDO $p):void {
 foreach(['parent_id'=>'BIGINT UNSIGNED NULL','is_group'=>'TINYINT NOT NULL DEFAULT 0','account_kind'=>"VARCHAR(30) NOT NULL DEFAULT 'general'"] as $name=>$definition){$q=$p->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_accounts' AND COLUMN_NAME=?");$q->execute([$name]);if(!$q->fetchColumn())$p->exec('ALTER TABLE ledger_accounts ADD COLUMN '.$name.' '.$definition);}
 $q=$p->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_accounts' AND CONSTRAINT_NAME='fk_ledger_parent'");if(!$q->fetchColumn())$p->exec('ALTER TABLE ledger_accounts ADD CONSTRAINT fk_ledger_parent FOREIGN KEY(household_id,parent_id) REFERENCES ledger_accounts(household_id,id)');
 // Backfill hierarchy without changing any ledger balances or original journals.
 foreach($p->query('SELECT id FROM households')->fetchAll(PDO::FETCH_COLUMN) as $h){
  $roots=[];foreach(['asset'=>'Assets','liability'=>'Liabilities','income'=>'Income','expense'=>'Expenses','equity'=>'Equity'] as $class=>$name){$key='chart_root_'.$class;$q=$p->prepare('SELECT id FROM ledger_accounts WHERE household_id=? AND system_key=?');$q->execute([$h,$key]);$id=$q->fetchColumn();if(!$id){$q=$p->prepare('INSERT INTO ledger_accounts(uuid,household_id,name,class,system_key,is_group,account_kind) VALUES(?,?,?,?,?,1,?)');$q->execute([\ReadyFinance\Core::uuid(),$h,$name,$class,$key,'group']);$id=$p->lastInsertId();}$roots[$class]=$id;}
  foreach(['receivable'=>['asset','Receivables'],'payable'=>['liability','Payables']] as $kind=>[$class,$label]){$key='chart_group_'.$kind;$q=$p->prepare('SELECT id FROM ledger_accounts WHERE household_id=? AND system_key=?');$q->execute([$h,$key]);$id=$q->fetchColumn();if(!$id){$q=$p->prepare('INSERT INTO ledger_accounts(uuid,household_id,name,class,system_key,is_group,account_kind,parent_id) VALUES(?,?,?,?,?,1,?,?)');$q->execute([\ReadyFinance\Core::uuid(),$h,$label,$class,$key,'group',$roots[$class]]);$id=$p->lastInsertId();}$roots[$kind]=$id;}
  foreach(['asset','liability','income','expense','equity'] as $class){$q=$p->prepare('UPDATE ledger_accounts SET parent_id=? WHERE household_id=? AND class=? AND parent_id IS NULL AND is_group=0');$q->execute([$roots[$class],$h,$class]);}
  $q=$p->prepare("UPDATE ledger_accounts SET account_kind=class WHERE household_id=? AND is_group=0 AND account_kind='general'");$q->execute([$h]);
  $q=$p->prepare('UPDATE ledger_accounts l JOIN accounts a ON a.ledger_id=l.id AND a.household_id=l.household_id SET l.account_kind=a.type WHERE l.household_id=?');$q->execute([$h]);
  foreach(['lent'=>['receivable','asset'],'borrowed'=>['payable','liability']] as $direction=>[$kind,$class]){$q=$p->prepare('UPDATE ledger_accounts l JOIN obligations o ON o.ledger_id=l.id AND o.household_id=l.household_id SET l.account_kind=?,l.parent_id=? WHERE o.household_id=? AND o.direction=?');$q->execute([$kind,$roots[$kind],$h,$direction]);}
 }
 $p->exec("INSERT IGNORE INTO schema_migrations VALUES ('005_chart',UTC_TIMESTAMP(6))");
};
