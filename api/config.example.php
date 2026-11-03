<?php
// Copie para api/config.local.php (esse arquivo não vai para o git) e ajuste.
return [
  'host' => '127.0.0.1',
  'port' => 3306,
  'db'   => 'gymflex',
  'user' => 'usuario_mysql',
  'pass' => 'senha_mysql',
  'acesso' => [
    'carencia_dias' => 5,
    'bloquear_inadimplente' => true,
  ],
  'ponto' => [
    'jornada_minutos' => 480,          // jornada diária padrão (480 = 8h), de segunda a sexta, exceto feriados
  ],
  'integracao' => [
    'provedor' => null,                 // null = desligado | 'simulado' (testes)
    'webhook_segredo' => 'troque-por-um-segredo-longo',
  ],
];
