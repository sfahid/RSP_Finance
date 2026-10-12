<?php
declare(strict_types=1);
return static function(PDO $p):void {
 $sql=file_get_contents(__DIR__.'/001_core.sql')."\n".file_get_contents(__DIR__.'/002_commands.sql');
 preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? (\w+) \((.*?)\)\s*(?:ENGINE=InnoDB)?;/s',$sql,$tables,PREG_SET_ORDER);
 foreach($tables as $table){$name=$table[1];$q=$p->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$name]);if($q->fetchColumn()!=='InnoDB')$p->exec('ALTER TABLE `'.$name.'` ENGINE=InnoDB');}
 foreach($tables as $table){$name=$table[1];preg_match_all('/FOREIGN KEY\(([^)]+)\) REFERENCES (\w+)\(([^)]+)\)/',$table[2],$foreign,PREG_SET_ORDER);foreach($foreign as $fk){$q=$p->prepare('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION');$q->execute([$name]);$groups=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$groups[$r['CONSTRAINT_NAME']]['columns'][]=$r['COLUMN_NAME'];$groups[$r['CONSTRAINT_NAME']]['references'][]=$r['REFERENCED_COLUMN_NAME'];$groups[$r['CONSTRAINT_NAME']]['parent']=$r['REFERENCED_TABLE_NAME'];}$found=false;foreach($groups as $g)if(implode(',',$g['columns'])===$fk[1]&&$g['parent']===$fk[2]&&implode(',',$g['references'])===$fk[3])$found=true;if(!$found)$p->exec('ALTER TABLE `'.$name.'` ADD '.$fk[0]);}}
 $p->exec("INSERT INTO schema_migrations VALUES ('003_storage',UTC_TIMESTAMP(6))");
};
