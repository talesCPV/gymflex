<?php
// Copie api/config.example.php para api/config.local.php (ignorado pelo git) e ajuste.
$cfg = [
  'host' => '127.0.0.1', 'port' => 3306, 'db' => 'gymflex', 'user' => 'root', 'pass' => '',
  'acesso' => [
    'carencia_dias' => 5,              // dias de atraso tolerados antes de bloquear a entrada
    'bloquear_inadimplente' => true,   // false = só avisa, não bloqueia
  ],
  'integracao' => [
    'provedor' => null,          // null = desligado (baixa manual) | 'simulado' | futuramente 'inter', 'santander'...
    'webhook_segredo' => '',     // segredo compartilhado usado pelo provedor 'simulado'
  ],
];
if (file_exists(__DIR__.'/config.local.php')) $cfg = array_replace_recursive($cfg, require __DIR__.'/config.local.php');
return $cfg;
