<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
require_once dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/bootstrap.php';
$c=new ReadyFinance\Core();$f=new ReadyFinance\Finance($c);$count=0;
foreach($c->all("SELECT household_id,MIN(user_id) user_id FROM household_memberships WHERE role='owner' AND active=1 GROUP BY household_id") as $row){$r=$f->runRecurring((int)$row['household_id'],(int)$row['user_id']);$count+=$r['generated'];}
file_put_contents($c->config['storage'].'/scheduler-heartbeat.json',json_encode(['last_success'=>gmdate('c'),'generated'=>$count]));echo "Generated $count bills.\n";
