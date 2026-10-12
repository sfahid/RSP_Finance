<?php
// Copy to config/local.php in the private app directory. Never put this file in public_html.
return [
 'dsn'=>'mysql:host=localhost;dbname=CPANELUSER_readyfinance;charset=utf8mb4',
 'user'=>'CPANELUSER_finance',
 'password'=>'REPLACE_WITH_PRIVATE_DATABASE_PASSWORD',
 'storage'=>dirname(__DIR__).'/storage',
 'installation_uuid'=>'GENERATED_ON_INSTALL',
 'base_path'=>'', // Empty for a dedicated subdomain; /finance for a subfolder.
 'require_https'=>true,
 'allow_web_setup'=>false,
];
