<?php
// GET ?mes=AAAA-MM  -> {resumo, lancamentos}     (gera automaticamente mensalidades, salários e recorrências)
// GET ?recorrencias=1 -> contas recorrentes ativas
// POST {id?,tipo,categoria,descricao,valor,vencimento,repetir?,recorrente?}  cria/edita
// POST {acao:'pagar'|'estornar', id, pago_em?} | {acao:'gerar', mes, o:'mensalidades'|'salarios'} | {acao:'encerrar_recorrencia', id}
// DELETE ?id=  (somente pendentes; os gerados automaticamente são apenas cancelados)
require __DIR__.'/lib.php';
require_module('financeiro');
$m = $_SERVER['REQUEST_METHOD'];

function mes_ok(?string $s): bool { return $s && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s); }
function data_ok(?string $s): bool { $d = DateTime::createFromFormat('Y-m-d', (string)$s); return $d && $d->format('Y-m-d') === $s; }
// dia $dia dentro do mês AAAA-MM (limita ao último dia do mês) e ajustado para o dia útil mais próximo
function venc(string $mes, int $dia): string {
  $ult = (int)date('t', strtotime("$mes-01"));
  return dia_util_proximo(sprintf('%s-%02d', $mes, min($dia, $ult)));
}
function meses(string $de, string $ate): array {
  $r = []; for ($x = $de; $x <= $ate; $x = date('Y-m', strtotime("$x-01 +1 month"))) $r[] = $x; return $r;
}

function gerar_mensalidades(string $mes): int {
  $s = db()->query("SELECT id,nome,valor_combinado,dia_vencimento FROM alunos WHERE ativo=1 AND valor_combinado>0");
  $ins = db()->prepare("INSERT IGNORE INTO lancamentos (tipo,categoria,descricao,valor,vencimento,id_aluno,competencia) VALUES ('receber','Mensalidade',?,?,?,?,?)");
  $n = 0;
  foreach ($s->fetchAll() as $a) { $ins->execute(["Mensalidade $mes - {$a['nome']}", $a['valor_combinado'], venc($mes, (int)$a['dia_vencimento']), $a['id'], $mes]); $n += $ins->rowCount(); }
  return $n;
}
function gerar_salarios(string $mes): int {
  $s = db()->query("SELECT id,nome,salario,dia_pagamento FROM funcionarios WHERE ativo=1 AND salario>0");
  $ins = db()->prepare("INSERT IGNORE INTO lancamentos (tipo,categoria,descricao,valor,vencimento,id_funcionario,competencia) VALUES ('pagar','Salário',?,?,?,?,?)");
  $n = 0;
  foreach ($s->fetchAll() as $f) { $ins->execute(["Salário $mes - {$f['nome']}", $f['salario'], venc($mes, (int)$f['dia_pagamento']), $f['id'], $mes]); $n += $ins->rowCount(); }
  return $n;
}
function gerar_recorrencias(string $ate): int {
  $ins = db()->prepare("INSERT IGNORE INTO lancamentos (tipo,categoria,descricao,valor,vencimento,id_recorrencia,competencia) VALUES (?,?,?,?,?,?,?)");
  $n = 0;
  foreach (db()->query("SELECT * FROM recorrencias WHERE ativo=1")->fetchAll() as $r)
    foreach (meses($r['inicio'], $ate) as $mes) { $ins->execute([$r['tipo'],$r['categoria'],$r['descricao'],$r['valor'],venc($mes, (int)$r['dia_vencimento']),$r['id'],$mes]); $n += $ins->rowCount(); }
  return $n;
}
// Tudo o que é recorrente entra sozinho: mês atual e o próximo (a tabela impede duplicar)
function gerar_automaticos(): void {
  $atual = date('Y-m'); $prox = date('Y-m', strtotime('first day of next month'));
  foreach ([$atual, $prox] as $mes) { gerar_mensalidades($mes); gerar_salarios($mes); }
  gerar_recorrencias($prox);
}

if ($m === 'GET' && isset($_GET['recorrencias'])) {
  out(db()->query("SELECT * FROM recorrencias WHERE ativo=1 ORDER BY tipo,descricao")->fetchAll());
}

if ($m === 'GET') {
  $mes = $_GET['mes'] ?? date('Y-m');
  if (!mes_ok($mes)) out(['erro'=>'Mês inválido'], 422);
  gerar_automaticos();
  $ini = "$mes-01"; $fim = date('Y-m-d', strtotime("$ini +1 month"));
  $q = db()->prepare("SELECT l.*,a.nome aluno,f.nome funcionario, (l.pago_em IS NULL AND l.vencimento<CURDATE()) atrasado
    FROM lancamentos l LEFT JOIN alunos a ON a.id=l.id_aluno LEFT JOIN funcionarios f ON f.id=l.id_funcionario
    WHERE l.cancelado=0 AND l.vencimento>=? AND l.vencimento<? ORDER BY l.vencimento,l.id");
  $q->execute([$ini, $fim]);
  $r = db()->prepare("SELECT tipo, SUM(valor) total, SUM(IF(pago_em IS NOT NULL,valor,0)) pago,
      SUM(IF(pago_em IS NULL,valor,0)) pendente, SUM(IF(pago_em IS NULL AND vencimento<CURDATE(),valor,0)) atrasado
    FROM lancamentos WHERE cancelado=0 AND vencimento>=? AND vencimento<? GROUP BY tipo");
  $r->execute([$ini, $fim]);
  $res = ['receber'=>['total'=>0,'pago'=>0,'pendente'=>0,'atrasado'=>0], 'pagar'=>['total'=>0,'pago'=>0,'pendente'=>0,'atrasado'=>0]];
  foreach ($r->fetchAll() as $row) { $t = $row['tipo']; unset($row['tipo']); $res[$t] = array_map('floatval', $row); }
  $res['saldo_realizado'] = $res['receber']['pago'] - $res['pagar']['pago'];
  $res['saldo_previsto'] = $res['receber']['total'] - $res['pagar']['total'];
  $at = db()->prepare("SELECT tipo,COUNT(*) n,SUM(valor) total FROM lancamentos WHERE cancelado=0 AND pago_em IS NULL AND vencimento<? GROUP BY tipo");
  $at->execute([$ini]);
  $res['atrasos_anteriores'] = $at->fetchAll();
  out(['resumo'=>$res, 'lancamentos'=>$q->fetchAll()]);
}

if ($m === 'POST') {
  $i = input();
  $acao = $i['acao'] ?? 'salvar';

  if ($acao === 'pagar' || $acao === 'estornar') {
    $id = (int)($i['id'] ?? 0);
    if ($acao === 'pagar') {
      $d = $i['pago_em'] ?? date('Y-m-d');
      if (!data_ok($d)) out(['erro'=>'Data inválida'], 422);
      db()->prepare('UPDATE lancamentos SET pago_em=? WHERE id=?')->execute([$d, $id]);
    } else db()->prepare('UPDATE lancamentos SET pago_em=NULL WHERE id=?')->execute([$id]);
    out(['ok'=>true]);
  }

  if ($acao === 'gerar') {   // reprocessar um mês específico (ex.: meses passados)
    $mes = $i['mes'] ?? '';
    if (!mes_ok($mes)) out(['erro'=>'Mês inválido'], 422);
    if (($i['o'] ?? '') === 'mensalidades') out(['geradas'=>gerar_mensalidades($mes)]);
    if (($i['o'] ?? '') === 'salarios') out(['geradas'=>gerar_salarios($mes)]);
    out(['erro'=>'Geração inválida'], 422);
  }

  if ($acao === 'encerrar_recorrencia') {
    $id = (int)($i['id'] ?? 0);
    db()->prepare('UPDATE recorrencias SET ativo=0 WHERE id=?')->execute([$id]);
    // as ocorrências futuras ainda não pagas deixam de valer; as já pagas/vencidas ficam
    db()->prepare('UPDATE lancamentos SET cancelado=1 WHERE id_recorrencia=? AND pago_em IS NULL AND vencimento>CURDATE()')->execute([$id]);
    out(['ok'=>true]);
  }

  // salvar (criar/editar)
  $tipo = $i['tipo'] ?? ''; $cat = trim($i['categoria'] ?? ''); $desc = trim($i['descricao'] ?? '');
  $valor = (float)str_replace(',', '.', (string)($i['valor'] ?? 0));
  if (!in_array($tipo, ['receber','pagar'], true)) out(['erro'=>'Tipo inválido'], 422);
  if ($cat === '' || $desc === '') out(['erro'=>'Categoria e descrição são obrigatórias'], 422);
  if ($valor <= 0) out(['erro'=>'Valor deve ser maior que zero'], 422);
  if (!data_ok($i['vencimento'] ?? null)) out(['erro'=>'Vencimento inválido'], 422);
  if (!empty($i['id'])) {
    db()->prepare('UPDATE lancamentos SET tipo=?,categoria=?,descricao=?,valor=?,vencimento=? WHERE id=?')
      ->execute([$tipo,$cat,$desc,$valor,$i['vencimento'],$i['id']]);
    out(['id'=>(int)$i['id']]);
  }
  $dia = (int)substr($i['vencimento'], 8, 2); $base = substr($i['vencimento'], 0, 7);
  if (!empty($i['recorrente'])) {   // conta fixa: cria o modelo e as ocorrências entram sozinhas todo mês
    db()->prepare('INSERT INTO recorrencias (tipo,categoria,descricao,valor,dia_vencimento,inicio) VALUES (?,?,?,?,?,?)')
      ->execute([$tipo,$cat,$desc,$valor,$dia,$base]);
    gerar_recorrencias(date('Y-m', strtotime('first day of next month')));
    out(['recorrente'=>true]);
  }
  $rep = max(1, min(60, (int)($i['repetir'] ?? 1)));   // parcelas com prazo definido (ex.: empréstimo)
  $ins = db()->prepare('INSERT INTO lancamentos (tipo,categoria,descricao,valor,vencimento) VALUES (?,?,?,?,?)');
  for ($k = 0; $k < $rep; $k++) {
    $mes = date('Y-m', strtotime("$base-01 +$k month"));
    $ins->execute([$tipo,$cat,$rep > 1 ? sprintf('%s (%d/%d)', $desc, $k + 1, $rep) : $desc,$valor,$k === 0 ? $i['vencimento'] : venc($mes, $dia)]);
  }
  out(['criados'=>$rep]);
}

if ($m === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  $q = db()->prepare('SELECT pago_em,competencia FROM lancamentos WHERE id=?'); $q->execute([$id]);
  $l = $q->fetch();
  if (!$l) out(['erro'=>'Lançamento não encontrado'], 404);
  if ($l['pago_em']) out(['erro'=>'Só é possível excluir lançamentos pendentes (estorne antes)'], 409);
  // gerado automaticamente: só cancela, senão seria recriado no próximo acesso
  $l['competencia'] ? db()->prepare('UPDATE lancamentos SET cancelado=1 WHERE id=?')->execute([$id])
                    : db()->prepare('DELETE FROM lancamentos WHERE id=?')->execute([$id]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
