<?php
// Cartão de ponto dos funcionários (controle interno; não substitui REP homologado).
// Módulos: 'ponto' = bater o próprio ponto e ver o próprio espelho; 'ponto_gestao' = ver a equipe,
// ver o espelho de qualquer um, lançar marcação manual, cancelar marcação e definir jornada.
//
// GET  ?hoje=1                               estado do ponto do usuário logado
// GET  ?equipe=1                             (gestão) quem está dentro / fora agora
// GET  ?espelho=1&mes=AAAA-MM[&id_funcionario=N]
// POST {acao:'bater'}                        registra a marcação do usuário logado (hora do servidor)
// POST {acao:'manual', id_funcionario, data_hora, justificativa}   (gestão)
// POST {acao:'cancelar', id, motivo}                               (gestão)
// POST {acao:'jornada', id_funcionario, minutos}                   (gestão)
require __DIR__.'/lib.php';
$u = user();
if (!$u) out(['erro' => 'Não autenticado'], 401);
$podeBater = in_array('ponto', $u['modulos'], true);
$gestor = in_array('ponto_gestao', $u['modulos'], true);
if (!$podeBater && !$gestor) out(['erro' => 'Sem permissão'], 403);
$m = $_SERVER['REQUEST_METHOD'];
$padrao = max(0, (int)(((require __DIR__.'/config.php')['ponto'] ?? [])['jornada_minutos'] ?? 480));

$agora = db()->query('SELECT NOW() AS n, CURDATE() AS d')->fetch();
$hoje = $agora['d'];
$utc = new DateTimeZone('UTC');
function ts(string $s): int { global $utc; return (new DateTime($s, $utc))->getTimestamp(); }
function hm(string $dt): string { return substr($dt, 11, 5); }

$q = db()->prepare('SELECT id,nome,cargo,data_admissao FROM funcionarios WHERE id_usuario=? AND ativo=1');
$q->execute([$u['id']]);
$meu = $q->fetch() ?: null;

function jornada_de(int $idf, int $padrao): int {
  $q = db()->prepare('SELECT minutos FROM ponto_jornadas WHERE id_funcionario=?'); $q->execute([$idf]);
  $v = $q->fetchColumn();
  return $v === false ? $padrao : (int)$v;
}
/** marcações do funcionário no intervalo [ini, fim) */
function marcacoes(int $idf, string $ini, string $fim): array {
  $q = db()->prepare("SELECT r.id, r.data_hora, r.origem, r.justificativa, r.cancelado, r.cancelado_motivo, uc.nome AS registrou
    FROM ponto_registros r LEFT JOIN usuarios uc ON uc.id = r.id_usuario
    WHERE r.id_funcionario=? AND r.data_hora>=? AND r.data_hora<? ORDER BY r.data_hora, r.id");
  $q->execute([$idf, $ini, $fim]);
  return $q->fetchAll();
}
/** soma os pares entrada/saída; ímpar = marcação em aberto */
function calcular_dia(array $rows, string $agoraStr, bool $ehHoje): array {
  $ativas = array_values(array_filter($rows, fn($r) => !(int)$r['cancelado']));
  $min = 0; $aberto = false;
  for ($i = 0; $i < count($ativas); $i += 2) {
    if (isset($ativas[$i + 1])) $min += intdiv(ts($ativas[$i + 1]['data_hora']) - ts($ativas[$i]['data_hora']), 60);
    else { $aberto = true; if ($ehHoje) $min += max(0, intdiv(ts($agoraStr) - ts($ativas[$i]['data_hora']), 60)); }
  }
  return [$min, $aberto];
}
function estado_hoje(int $idf, array $agora): array {
  $rows = marcacoes($idf, $agora['d'] . ' 00:00:00', date('Y-m-d', strtotime($agora['d'] . ' +1 day')) . ' 00:00:00');
  $ativas = array_values(array_filter($rows, fn($r) => !(int)$r['cancelado']));
  [$min] = calcular_dia($rows, $agora['n'], true);
  $out = [];
  foreach ($ativas as $i => $r) $out[] = ['id' => $r['id'], 'hora' => hm($r['data_hora']), 'tipo' => $i % 2 ? 'saida' : 'entrada', 'origem' => $r['origem']];
  return ['marcacoes' => $out, 'dentro' => count($ativas) % 2 === 1, 'proximo' => count($ativas) % 2 ? 'saida' : 'entrada',
          'trabalhado_min' => $min, 'agora' => hm($agora['n'])];
}

if ($m === 'GET' && isset($_GET['hoje'])) {
  if (!$podeBater) out(['erro' => 'Sem permissão'], 403);
  if (!$meu) out(['vinculado' => false]);
  out(['vinculado' => true, 'funcionario' => ['id' => $meu['id'], 'nome' => $meu['nome']]] + estado_hoje((int)$meu['id'], $agora));
}

if ($m === 'GET' && isset($_GET['equipe'])) {
  if (!$gestor) out(['erro' => 'Sem permissão'], 403);
  $fs = db()->query('SELECT id,nome,cargo FROM funcionarios WHERE ativo=1 ORDER BY nome')->fetchAll();
  $q = db()->prepare("SELECT id_funcionario, data_hora FROM ponto_registros WHERE cancelado=0 AND data_hora>=? AND data_hora<? ORDER BY data_hora, id");
  $q->execute([$hoje . ' 00:00:00', date('Y-m-d', strtotime($hoje . ' +1 day')) . ' 00:00:00']);
  $por = [];
  foreach ($q->fetchAll() as $r) $por[$r['id_funcionario']][] = $r['data_hora'];
  foreach ($fs as &$f) {
    $p = $por[$f['id']] ?? [];
    $f['situacao'] = !$p ? 'sem_registro' : (count($p) % 2 ? 'dentro' : 'fora');
    $f['desde'] = $p ? hm(end($p)) : null;
    $f['primeira_entrada'] = $p ? hm($p[0]) : null;
  }
  out(['funcionarios' => $fs]);
}

if ($m === 'GET' && isset($_GET['espelho'])) {
  $mes = $_GET['mes'] ?? substr($hoje, 0, 7);
  if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) out(['erro' => 'Mês inválido'], 422);
  $idf = (int)($_GET['id_funcionario'] ?? ($meu['id'] ?? 0));
  if (!$idf) out(['erro' => 'Seu usuário não está vinculado a um funcionário'], 422);
  if ((!$meu || $idf !== (int)$meu['id']) && !$gestor) out(['erro' => 'Sem permissão'], 403);
  $q = db()->prepare('SELECT id,nome,cargo,data_admissao FROM funcionarios WHERE id=?'); $q->execute([$idf]);
  $f = $q->fetch();
  if (!$f) out(['erro' => 'Funcionário não encontrado'], 404);
  $jorn = jornada_de($idf, $padrao);
  $ini = "$mes-01"; $fim = date('Y-m-d', strtotime("$ini +1 month"));
  $rows = marcacoes($idf, "$ini 00:00:00", "$fim 00:00:00");
  $porDia = [];
  foreach ($rows as $r) $porDia[substr($r['data_hora'], 0, 10)][] = $r;
  $sem = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  $dias = []; $tot = ['trabalhado_min' => 0, 'esperado_min' => 0, 'saldo_min' => 0, 'faltas' => 0, 'incompletos' => 0];
  for ($d = $ini; $d < $fim; $d = date('Y-m-d', strtotime("$d +1 day"))) {
    $rs = $porDia[$d] ?? [];
    $util = eh_util(new DateTime($d));
    [$trab, $aberto] = calcular_dia($rs, $agora['n'], $d === $hoje);
    $ativas = array_values(array_filter($rs, fn($r) => !(int)$r['cancelado']));
    $passado = $d < $hoje;
    $esp = ($passado && $util && $d >= $f['data_admissao']) ? $jorn : 0;
    $marc = []; $i = 0;
    foreach ($rs as $r) {
      $c = (int)$r['cancelado'];
      $marc[] = ['id' => $r['id'], 'hora' => hm($r['data_hora']), 'tipo' => $c ? null : ($i % 2 ? 'saida' : 'entrada'), 'origem' => $r['origem'],
                 'justificativa' => $r['justificativa'], 'registrou' => $r['registrou'], 'cancelado' => $c, 'cancelado_motivo' => $r['cancelado_motivo']];
      if (!$c) $i++;
    }
    $dias[] = ['data' => $d, 'dia_semana' => $sem[(int)date('w', strtotime($d))], 'util' => $util, 'hoje' => $d === $hoje, 'futuro' => $d > $hoje,
               'marcacoes' => $marc, 'trabalhado_min' => $trab, 'esperado_min' => $esp, 'saldo_min' => $passado ? $trab - $esp : null,
               'incompleto' => $aberto && !($d === $hoje)];
    $tot['trabalhado_min'] += $trab; $tot['esperado_min'] += $esp;
    if ($passado) { $tot['saldo_min'] += $trab - $esp; if ($esp > 0 && !$ativas) $tot['faltas']++; if ($aberto) $tot['incompletos']++; }
  }
  out(['funcionario' => ['id' => $f['id'], 'nome' => $f['nome'], 'cargo' => $f['cargo']], 'mes' => $mes, 'jornada_minutos' => $jorn,
       'dias' => $dias, 'totais' => $tot, 'pode_gerenciar' => $gestor]);
}

if ($m === 'POST') {
  $i = input();
  $acao = $i['acao'] ?? '';

  if ($acao === 'bater') {
    if (!$podeBater) out(['erro' => 'Sem permissão'], 403);
    if (!$meu) out(['erro' => 'Seu usuário não está vinculado a um funcionário. Peça para vincular em Funcionários.'], 422);
    $idf = (int)$meu['id'];
    $ult = db()->prepare('SELECT 1 FROM ponto_registros WHERE id_funcionario=? AND cancelado=0 AND data_hora > (NOW() - INTERVAL 60 SECOND) LIMIT 1');
    $ult->execute([$idf]);
    if ($ult->fetchColumn()) out(['duplicado' => true, 'registrado' => null] + estado_hoje($idf, $agora));
    $antes = estado_hoje($idf, $agora);
    db()->prepare("INSERT INTO ponto_registros (id_funcionario,data_hora,origem,id_usuario) VALUES (?,NOW(),'proprio',?)")->execute([$idf, $u['id']]);
    $agora2 = db()->query('SELECT NOW() AS n, CURDATE() AS d')->fetch();
    out(['duplicado' => false, 'registrado' => $antes['proximo']] + estado_hoje($idf, $agora2));
  }

  if (!$gestor) out(['erro' => 'Sem permissão'], 403);

  if ($acao === 'manual') {
    $idf = (int)($i['id_funcionario'] ?? 0);
    $just = trim((string)($i['justificativa'] ?? ''));
    $dh = str_replace('T', ' ', trim((string)($i['data_hora'] ?? '')));
    $dt = DateTime::createFromFormat('Y-m-d H:i', $dh);
    if (!$dt || $dt->format('Y-m-d H:i') !== $dh) out(['erro' => 'Data e hora inválidas'], 422);
    if (strlen($just) < 5) out(['erro' => 'Informe a justificativa (mínimo 5 caracteres)'], 422);
    $c = db()->prepare('SELECT 1 FROM funcionarios WHERE id=?'); $c->execute([$idf]);
    if (!$c->fetchColumn()) out(['erro' => 'Funcionário não encontrado'], 404);
    $fut = db()->prepare('SELECT ? > NOW()'); $fut->execute(["$dh:00"]);
    if ($fut->fetchColumn()) out(['erro' => 'Não é possível lançar marcação no futuro'], 422);
    db()->prepare("INSERT INTO ponto_registros (id_funcionario,data_hora,origem,justificativa,id_usuario) VALUES (?,?,'manual',?,?)")
      ->execute([$idf, "$dh:00", substr($just, 0, 200), $u['id']]);
    out(['ok' => true]);
  }

  if ($acao === 'cancelar') {
    $motivo = trim((string)($i['motivo'] ?? ''));
    if (strlen($motivo) < 5) out(['erro' => 'Informe o motivo do cancelamento (mínimo 5 caracteres)'], 422);
    $q = db()->prepare('UPDATE ponto_registros SET cancelado=1, cancelado_motivo=?, cancelado_por=? WHERE id=? AND cancelado=0');
    $q->execute([substr($motivo, 0, 200), $u['id'], (int)($i['id'] ?? 0)]);
    $q->rowCount() ? out(['ok' => true]) : out(['erro' => 'Marcação não encontrada ou já cancelada'], 404);
  }

  if ($acao === 'jornada') {
    $idf = (int)($i['id_funcionario'] ?? 0); $min = (int)($i['minutos'] ?? -1);
    if ($min < 0 || $min > 1440) out(['erro' => 'Jornada deve ficar entre 0 e 1440 minutos'], 422);
    $c = db()->prepare('SELECT 1 FROM funcionarios WHERE id=?'); $c->execute([$idf]);
    if (!$c->fetchColumn()) out(['erro' => 'Funcionário não encontrado'], 404);
    db()->prepare('REPLACE INTO ponto_jornadas (id_funcionario,minutos) VALUES (?,?)')->execute([$idf, $min]);
    out(['ok' => true]);
  }
  out(['erro' => 'Ação inválida'], 422);
}
out(['erro' => 'Método inválido'], 405);
