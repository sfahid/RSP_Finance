<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
$ok=version_compare(PHP_VERSION,'8.3','>=');echo 'PHP 8.3+: '.($ok?'OK':'MISSING').PHP_EOL;
foreach(['pdo_mysql','bcmath','openssl','fileinfo','mbstring','zip','dom','xml','xmlreader','xmlwriter','simplexml','iconv','gd'] as $ext){$found=extension_loaded($ext);$ok=$ok&&$found;echo $ext.': '.($found?'OK':'MISSING').PHP_EOL;}
$config=getenv('RPF_CONFIG')?:dirname(__DIR__).'/config/local.php';
if(!is_file($config)){echo "Private configuration missing.\n";exit(1);}
$c=require $config;
try{$p=new PDO($c['dsn'],$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);echo "Database connection OK\n";}catch(Throwable $e){echo "Database connection failed. Check private configuration; details withheld.\n";$ok=false;}
$parent=is_dir($c['storage'])?$c['storage']:dirname($c['storage']);if(!is_writable($parent)){echo "Private storage is not writable.\n";$ok=false;}
exit($ok?0:1);
