<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
require_once dirname(__DIR__).'/bootstrap.php';
use ReadyFinance\Core;
$root=dirname(__DIR__);
$config=require (getenv('RPF_CONFIG')?:$root.'/config/local.php');
foreach(['pdo_mysql','bcmath','openssl','fileinfo','mbstring'] as $ext)if(!extension_loaded($ext))throw new RuntimeException('Missing PHP extension '.$ext);
if(empty($config['installation_uuid'])||$config['installation_uuid']==='GENERATED_ON_INSTALL')throw new RuntimeException('Generate an installation UUID before installation.');
$p=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($p->query('SHOW TABLES')->fetchColumn()!==false)throw new RuntimeException('Database must be empty. No tables were changed.');
foreach(glob($root.'/database/*.sql') as $file)$p->exec(file_get_contents($file));
foreach(glob($root.'/database/*.php') as $file){$migration=require $file;$migration($p);}
foreach(['sessions','documents','logs','backups'] as $dir)if(!is_dir($config['storage'].'/'.$dir)&&!mkdir($config['storage'].'/'.$dir,0700,true))throw new RuntimeException('Cannot create private storage.');
echo "Schema installed. Run create-owner.php privately before opening the hosted app. No default credentials exist.\n";
