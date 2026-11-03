<?php
// Copie para config.local.php para sobrescrever (não versionado).
$cfg = ['host'=>'127.0.0.1','db'=>'gymflex','user'=>'root','pass'=>''];
if (file_exists(__DIR__.'/config.local.php')) $cfg = array_merge($cfg, require __DIR__.'/config.local.php');
return $cfg;
