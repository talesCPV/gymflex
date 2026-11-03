<?php
// Endpoint PÚBLICO chamado pelo banco/gateway: POST api/webhook.php?provedor=NOME
// A autenticidade é verificada pelo próprio provedor (ver ProvedorPagamento::interpretarWebhook).
define('SEM_SESSAO', true);
require __DIR__.'/lib.php';
require __DIR__.'/integracao/Servico.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['erro'=>'Método inválido'], 405);
$cab = [];
foreach ($_SERVER as $k => $v) if (str_starts_with($k, 'HTTP_')) $cab[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
try { [$code, $corpo] = Servico::webhook((string)($_GET['provedor'] ?? ''), file_get_contents('php://input'), $cab); }
catch (Throwable $e) { error_log('webhook: '.$e->getMessage()); out(['erro'=>'Erro interno'], 500); }
out($corpo, $code);
