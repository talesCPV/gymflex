<?php
// Copie para api/config.local.php (esse arquivo não vai para o git) e ajuste.
return [
  'host' => '127.0.0.1',
  'port' => 3306,
  'db'   => 'gymflex',
  'user' => 'usuario_mysql',
  'pass' => 'senha_mysql',
  'integracao' => [
    'provedor' => null,                 // null = desligado | 'simulado' (testes)
    'webhook_segredo' => 'troque-por-um-segredo-longo',
  ],
];
