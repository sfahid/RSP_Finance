<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
require_once dirname(__DIR__).'/bootstrap.php';
use ReadyFinance\Core;
$root=dirname(__DIR__);$config=$root.'/config/local.php';
if(is_file($config)){echo "Installation configuration already exists. No changes made.\n";exit;}
foreach(['pdo_mysql','bcmath','openssl','fileinfo','mbstring'] as $ext)if(!extension_loaded($ext))throw new RuntimeException('Missing PHP extension '.$ext);
$dbname=$argv[1]??'readyfinance';if(!preg_match('/^readyfinance[a-z0-9_]*$/D',$dbname))throw new RuntimeException('Use a readyfinance database name.');
$admin=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('RPF_DB_ADMIN')?:'root',getenv('RPF_DB_ADMIN_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exists=$admin->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$admin->quote($dbname))->fetchColumn();if($exists)throw new RuntimeException('The database already exists. Refusing to overwrite it.');
$admin->exec('CREATE DATABASE `'.$dbname.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$admin->exec('USE `'.$dbname.'`');$admin->exec(file_get_contents($root.'/database/001_core.sql'));
foreach(glob($root.'/database/*.sql') as $file)if(basename($file)!=='001_core.sql')$admin->exec(file_get_contents($file));
foreach(glob($root.'/database/*.php') as $file){$migration=require $file;$migration($admin);}
$dbuser='rpf_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(24));
$admin->exec('CREATE USER '.$admin->quote($dbuser).'@\'localhost\' IDENTIFIED BY '.$admin->quote($password));
$admin->exec('GRANT SELECT, INSERT, UPDATE ON `'.$dbname.'`.* TO '.$admin->quote($dbuser).'@\'localhost\'');
$admin->exec('GRANT DELETE ON `'.$dbname.'`.login_attempts TO '.$admin->quote($dbuser).'@\'localhost\'');
$storage=$root.'/storage';foreach(['sessions','documents','logs','backups'] as $d)if(!is_dir($storage.'/'.$d))mkdir($storage.'/'.$d,0700,true);
$settings=['dsn'=>'mysql:host=127.0.0.1;port=3306;dbname='.$dbname.';charset=utf8mb4','user'=>$dbuser,'password'=>$password,'storage'=>$storage,'installation_uuid'=>Core::uuid(),'base_path'=>'/readyfinance'];
file_put_contents($config,"<?php\nreturn ".var_export($settings,true).";\n");
echo "Ready Personal Finance installed with a dedicated empty database. Open the app on localhost to create your owner account.\n";
