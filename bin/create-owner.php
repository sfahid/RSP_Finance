<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
require_once dirname(__DIR__).'/bootstrap.php';
$payload=[];
foreach(['name','household','email','password'] as $field){$value=getenv('RPF_OWNER_'.strtoupper($field));if($value===false||$value==='')throw new RuntimeException('Supply RPF_OWNER_'.strtoupper($field).' through a private environment variable.');$payload[$field]=$value;}
(new \ReadyFinance\Finance(new \ReadyFinance\Core()))->setup($payload);
echo "Owner created. Sign in through the secure app address.\n";
