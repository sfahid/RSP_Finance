<?php
declare(strict_types=1);
return static function(PDO $p):void {
 $p->exec(file_get_contents(__DIR__.'/008_parties.sql'));
 $p->exec("INSERT IGNORE INTO schema_migrations VALUES ('008_parties',UTC_TIMESTAMP(6))");
};
