<?php
require __DIR__.'/lib.php';
$u = user();
$u ? out($u) : out(['erro'=>'Não autenticado'], 401);
