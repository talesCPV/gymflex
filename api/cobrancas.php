<?php
// GET ?status=1 -> {ativo, provedor} | GET ?id_lancamento=N -> cobranças do lançamento
// POST {id_lancamento, tipo?} cria cobrança | POST {acao:'cancelar', id}
require __DIR__.'/lib.php';
require __DIR__.'/integracao/Servico.php';
require_module('financeiro');
$m = $_SERVER['REQUEST_METHOD'];
try {
  if ($m === 'GET' && isset($_GET['status'])) { $p = Provedores::ativo(); out(['ativo' => (bool)$p, 'provedor' => $p?->nome()]); }
  if ($m === 'GET') {
    $q = db()->prepare('SELECT id,id_lancamento,provedor,tipo,status,valor,qr_code,url,linha_digitavel,expira_em,pago_em,criado_em FROM cobrancas WHERE id_lancamento=? ORDER BY id DESC');
    $q->execute([(int)($_GET['id_lancamento'] ?? 0)]);
    out($q->fetchAll());
  }
  if ($m === 'POST') {
    $i = input();
    if (($i['acao'] ?? '') === 'cancelar') { Servico::cancelarCobranca((int)($i['id'] ?? 0)); out(['ok' => true]); }
    out(Servico::criarCobranca((int)($i['id_lancamento'] ?? 0), ['tipo' => $i['tipo'] ?? 'pix']));
  }
} catch (RuntimeException $e) { out(['erro' => $e->getMessage()], 422); }
out(['erro'=>'Método inválido'], 405);
