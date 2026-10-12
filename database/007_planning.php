<?php
declare(strict_types=1);
return static function(PDO $p):void {
 foreach(['budgets','bills','funds','obligations','recurring_rules'] as $table)foreach(['archived'=>'TINYINT NOT NULL DEFAULT 0','row_version'=>'INT NOT NULL DEFAULT 1','updated_at'=>'DATETIME(6) NULL'] as $name=>$definition){
  $q=$p->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$name]);if(!$q->fetchColumn())$p->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition);
 }
 $p->exec("INSERT IGNORE INTO schema_migrations VALUES ('007_planning',UTC_TIMESTAMP(6))");
};
