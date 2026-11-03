<?php
// Controle de acesso: entrada dos alunos na academia.
// GET ?resumo=1[&data=AAAA-MM-DD]  -> {kpis, recentes, config}
// GET ?inadimplentes=1             -> alunos com mensalidade vencida
// POST {busca}                     -> procura por matrícula (ou nome) e registra a entrada
// POST {id_aluno}                  -> registra a entrada de um aluno escolhido
// POST {id_aluno, forcar:true, motivo}  -> libera manualmente um bloqueio (registrado)
require __DIR__.'/lib.php';
$me = require_module('acesso');
$m = $_SERVER['REQUEST_METHOD'];
$cfg = (require __DIR__.'/config.php')['acesso'] ?? [];
$carencia = max(0, (int)($cfg['carencia_dias'] ?? 5));
$bloquear = (bool)($cfg['bloquear_inadimplente'] ?? true);

function data_ok(?string $s): bool { $d = DateTime::createFromFormat('Y-m-d', (string)$s); return $d && $d->format('Y-m-d') === $s; }
function brl(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

/** @return array [resultado, motivo] */
function avaliar(array $aluno, int $carencia, bool $bloquear): array {
  if (!(int)$aluno['ativo']) return ['bloqueado', 'Aluno inativo'];
  $q = db()->prepare('SELECT mensalidades,total,dias_atraso FROM v_alunos_inadimplentes WHERE id_aluno=?');
  $q->execute([$aluno['id']]);
  $r = $q->fetch();
  if (!$r) return ['liberado', 'Em dia'];
  $d = (int)$r['dias_atraso'];
  $txt = sprintf('Mensalidade em atraso há %d dia(s) (%s)', $d, brl((float)$r['total']));
  if ($d <= $carencia) return ['liberado', "$txt, dentro da carência de $carencia dia(s)"];
  return $bloquear ? ['bloqueado', $txt] : ['liberado', "Atenção: $txt"];
}

if ($m === 'GET' && isset($_GET['inadimplentes'])) {
  out(['carencia_dias' => $carencia, 'bloqueia' => $bloquear,
       'alunos' => db()->query('SELECT * FROM v_alunos_inadimplentes ORDER BY dias_atraso DESC, nome')->fetchAll()]);
}

if ($m === 'GET') {
  $dia = $_GET['data'] ?? date('Y-m-d');
  if (!data_ok($dia)) out(['erro' => 'Data inválida'], 422);
  $ini = "$dia 00:00:00"; $fim = date('Y-m-d 00:00:00', strtotime("$dia +1 day"));
  $k = db()->prepare("SELECT SUM(resultado='liberado') liberados, SUM(resultado='bloqueado') bloqueados,
      COUNT(DISTINCT CASE WHEN resultado='liberado' THEN id_aluno END) alunos_unicos
    FROM acessos WHERE data_hora>=? AND data_hora<?");
  $k->execute([$ini, $fim]); $k = $k->fetch();
  $inad = db()->prepare('SELECT COUNT(*) FROM v_alunos_inadimplentes WHERE dias_atraso>?'); $inad->execute([$carencia]);
  $l = db()->prepare("SELECT ac.id, ac.data_hora, ac.resultado, ac.motivo, ac.manual, ac.origem, a.nome aluno, a.matricula, u.nome operador
    FROM acessos ac LEFT JOIN alunos a ON a.id=ac.id_aluno LEFT JOIN usuarios u ON u.id=ac.id_usuario
    WHERE ac.data_hora>=? AND ac.data_hora<? ORDER BY ac.id DESC LIMIT 50");
  $l->execute([$ini, $fim]);
  out(['data' => $dia,
       'kpis' => ['liberados' => (int)$k['liberados'], 'bloqueados' => (int)$k['bloqueados'],
                  'alunos_unicos' => (int)$k['alunos_unicos'], 'inadimplentes_bloqueados' => (int)$inad->fetchColumn()],
       'recentes' => $l->fetchAll(),
       'config' => ['carencia_dias' => $carencia, 'bloqueia' => $bloquear]]);
}

if ($m === 'POST') {
  $i = input();
  if (!empty($i['id_aluno'])) {
    $q = db()->prepare('SELECT id,nome,matricula,ativo FROM alunos WHERE id=?'); $q->execute([(int)$i['id_aluno']]);
    $aluno = $q->fetch();
    if (!$aluno) out(['erro' => 'Aluno não encontrado'], 404);
  } else {
    $b = trim((string)($i['busca'] ?? ''));
    if ($b === '') out(['erro' => 'Informe a matrícula ou o nome'], 422);
    $q = db()->prepare('SELECT id,nome,matricula,ativo FROM alunos WHERE matricula=?'); $q->execute([$b]);
    $achados = $q->fetchAll();
    if (!$achados) {
      $q = db()->prepare('SELECT id,nome,matricula,ativo FROM alunos WHERE nome LIKE ? ORDER BY nome LIMIT 8');
      $q->execute(['%' . addcslashes($b, '%_\\') . '%']);
      $achados = $q->fetchAll();
    }
    if (!$achados) out(['erro' => 'Aluno não encontrado'], 404);
    if (count($achados) > 1) out(['candidatos' => $achados]);
    $aluno = $achados[0];
  }

  [$res, $motivo] = avaliar($aluno, $carencia, $bloquear);
  $manual = 0;
  if (!empty($i['forcar'])) {
    $just = trim((string)($i['motivo'] ?? ''));
    if ($res !== 'bloqueado') out(['erro' => 'Este aluno não está bloqueado'], 422);
    if ($just === '') out(['erro' => 'Informe o motivo da liberação manual'], 422);
    $motivo = substr("Liberação manual: $just (bloqueio: $motivo)", 0, 160); $res = 'liberado'; $manual = 1;
  } else {
    // leitura repetida (duplo clique, leitor disparando duas vezes): não registra de novo em 2 minutos
    $d = db()->prepare("SELECT data_hora FROM acessos WHERE id_aluno=? AND resultado=? AND manual=0 AND data_hora > (NOW() - INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1");
    $d->execute([$aluno['id'], $res]);
    if ($d->fetchColumn()) out(['aluno' => $aluno, 'resultado' => $res, 'motivo' => $motivo, 'duplicado' => true]);
  }
  db()->prepare('INSERT INTO acessos (id_aluno,resultado,motivo,manual,origem,id_usuario) VALUES (?,?,?,?,?,?)')
    ->execute([$aluno['id'], $res, $motivo, $manual, 'recepcao', $me['id']]);
  out(['aluno' => $aluno, 'resultado' => $res, 'motivo' => $motivo, 'manual' => (bool)$manual, 'duplicado' => false]);
}
out(['erro' => 'Método inválido'], 405);
