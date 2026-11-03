<?php
// GET ?mes=AAAA-MM  -> {resumo, lancamentos}
// POST {id?,tipo,categoria,descricao,valor,vencimento,repetir?}  cria/edita
// POST {acao:'pagar'|'estornar', id, pago_em?} | {acao:'gerar', mes, o:'mensalidades'|'salarios'}
// DELETE ?id=  (somente pendentes)
require __DIR__.'/lib.php';
require_module('financeiro');
$m = $_SERVER['REQUEST_METHOD'];

function mes_ok(?string $s): bool { return $s && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s); }
function data_ok(?string $s): bool { $d = DateTime::createFromFormat('Y-m-d', (string)$s); return $d && $d->format('Y-m-d') === $s; }
// dia $dia dentro do mês AAAA-MM (limita ao último dia do mês)
function dia_no_mes(string $mes, int $dia): string {
  $ult = (int)date('t', strtotime("$mes-01"));
  return sprintf('%s-%02d', $mes, min($dia, $ult));
}

if ($m === 'GET') {
  $mes = $_GET['mes'] ?? date('Y-m');
  if (!mes_ok($mes)) out(['erro'=>'Mês inválido'], 422);
  $ini = "$mes-01"; $fim = date('Y-m-d', strtotime("$ini +1 month"));
  $q = db()->prepare("SELECT l.*,a.nome aluno,f.nome funcionario, (l.pago_em IS NULL AND l.vencimento<CURDATE()) atrasado
    FROM lancamentos l LEFT JOIN alunos a ON a.id=l.id_aluno LEFT JOIN funcionarios f ON f.id=l.id_funcionario
    WHERE l.vencimento>=? AND l.vencimento<? ORDER BY l.vencimento,l.id");
  $q->execute([$ini, $fim]);
  $r = db()->prepare("SELECT tipo, SUM(valor) total, SUM(IF(pago_em IS NOT NULL,valor,0)) pago,
      SUM(IF(pago_em IS NULL,valor,0)) pendente, SUM(IF(pago_em IS NULL AND vencimento<CURDATE(),valor,0)) atrasado
    FROM lancamentos WHERE vencimento>=? AND vencimento<? GROUP BY tipo");
  $r->execute([$ini, $fim]);
  $res = ['receber'=>['total'=>0,'pago'=>0,'pendente'=>0,'atrasado'=>0], 'pagar'=>['total'=>0,'pago'=>0,'pendente'=>0,'atrasado'=>0]];
  foreach ($r->fetchAll() as $row) { $t = $row['tipo']; unset($row['tipo']); $res[$t] = array_map('floatval', $row); }
  $res['saldo_realizado'] = $res['receber']['pago'] - $res['pagar']['pago'];
  $res['saldo_previsto'] = $res['receber']['total'] - $res['pagar']['total'];
  // contas em atraso de meses anteriores continuam cobrando atenção
  $at = db()->prepare("SELECT tipo,COUNT(*) n,SUM(valor) total FROM lancamentos WHERE pago_em IS NULL AND vencimento<? GROUP BY tipo");
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

  if ($acao === 'gerar') {
    $mes = $i['mes'] ?? '';
    if (!mes_ok($mes)) out(['erro'=>'Mês inválido'], 422);
    if (($i['o'] ?? '') === 'mensalidades') {
      $s = db()->query("SELECT id,nome,valor_combinado,dia_vencimento FROM alunos WHERE ativo=1 AND valor_combinado>0");
      $ins = db()->prepare("INSERT IGNORE INTO lancamentos (tipo,categoria,descricao,valor,vencimento,id_aluno,competencia) VALUES ('receber','Mensalidade',?,?,?,?,?)");
      $n = 0;
      foreach ($s->fetchAll() as $a) { $ins->execute(["Mensalidade $mes - {$a['nome']}", $a['valor_combinado'], dia_no_mes($mes, (int)$a['dia_vencimento']), $a['id'], $mes]); $n += $ins->rowCount(); }
      out(['geradas'=>$n]);
    }
    if (($i['o'] ?? '') === 'salarios') {
      $s = db()->query("SELECT id,nome,salario FROM funcionarios WHERE ativo=1 AND salario>0");
      $ins = db()->prepare("INSERT IGNORE INTO lancamentos (tipo,categoria,descricao,valor,vencimento,id_funcionario,competencia) VALUES ('pagar','Salário',?,?,?,?,?)");
      $n = 0;
      foreach ($s->fetchAll() as $f) { $ins->execute(["Salário $mes - {$f['nome']}", $f['salario'], dia_no_mes($mes, 5), $f['id'], $mes]); $n += $ins->rowCount(); }
      out(['geradas'=>$n]);
    }
    out(['erro'=>'Geração inválida'], 422);
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
  $rep = max(1, min(60, (int)($i['repetir'] ?? 1)));
  $ins = db()->prepare('INSERT INTO lancamentos (tipo,categoria,descricao,valor,vencimento) VALUES (?,?,?,?,?)');
  $dia = (int)substr($i['vencimento'], 8, 2); $base = substr($i['vencimento'], 0, 7);
  for ($k = 0; $k < $rep; $k++) {
    $mes = date('Y-m', strtotime("$base-01 +$k month"));
    $ins->execute([$tipo,$cat,$rep > 1 ? sprintf('%s (%d/%d)', $desc, $k + 1, $rep) : $desc,$valor,dia_no_mes($mes, $dia)]);
  }
  out(['criados'=>$rep]);
}

if ($m === 'DELETE') {
  $q = db()->prepare('DELETE FROM lancamentos WHERE id=? AND pago_em IS NULL');
  $q->execute([(int)($_GET['id'] ?? 0)]);
  $q->rowCount() ? out(['ok'=>true]) : out(['erro'=>'Só é possível excluir lançamentos pendentes (estorne antes)'], 409);
}
out(['erro'=>'Método inválido'], 405);
