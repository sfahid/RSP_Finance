<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
$config=require (getenv('RPF_CONFIG')?:dirname(__DIR__).'/config/local.php');
$p=new PDO($config['dsn'],getenv('RPF_DB_ADMIN')?:'root',getenv('RPF_DB_ADMIN_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$lock=$p->query('SELECT GET_LOCK("readyfinance_migrations",10)')->fetchColumn();if(!$lock)throw new RuntimeException('Migration lock unavailable.');
try{foreach(glob(dirname(__DIR__).'/database/*.{sql,php}',GLOB_BRACE) as $file){$v=pathinfo($file,PATHINFO_FILENAME);$q=$p->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version=?');$q->execute([$v]);if(!$q->fetchColumn()){if(pathinfo($file,PATHINFO_EXTENSION)==='php'){$migration=require $file;$migration($p);}else{$p->exec(file_get_contents($file));}echo "Applied $v\n";}}}finally{$p->query('SELECT RELEASE_LOCK("readyfinance_migrations")')->fetchColumn();}
