<?php
require dirname(__DIR__).'/bootstrap.php';
$c=new ReadyFinance\Core();if(!str_starts_with((string)$c->query('SELECT DATABASE()')->fetchColumn(),'readyfinance_entrytest_'))throw new RuntimeException('Isolated test database required.');
$role=$argv[1]??'';if(!in_array($role,['owner','manager','viewer'],true))throw new RuntimeException('Invalid role');$c->query('UPDATE household_memberships SET role=?',[$role]);
