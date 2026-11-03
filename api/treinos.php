<?php
// Aluno (usuário ligado a um aluno, não-Master): só leitura, só os seus treinos.
// Demais perfis com o módulo: GET lista | GET ?alunos=1 | POST {id?,nome,descricao,alunos[]} | DELETE ?id=
require __DIR__.'/lib.php';
$me = require_module('treinos');
$m = $_SERVER['REQUEST_METHOD'];

$q = db()->prepare('SELECT id FROM alunos WHERE id_usuario=? AND ativo=1');
$q->execute([$me['id']]);
$meuAluno = $me['master'] ? null : ($q->fetchColumn() ?: null);

if ($meuAluno) {
  if ($m !== 'GET') out(['erro'=>'Alunos só podem visualizar'], 403);
  $s = db()->prepare('SELECT t.id,t.nome,t.descricao,u.nome professor FROM treinos t
    JOIN treino_alunos ta ON ta.id_treino=t.id LEFT JOIN usuarios u ON u.id=t.id_criador
    WHERE ta.id_aluno=? AND t.ativo=1 ORDER BY t.nome');
  $s->execute([$meuAluno]);
  out(['aluno'=>true, 'treinos'=>$s->fetchAll()]);
}

if ($m === 'GET' && isset($_GET['alunos'])) {
  out(db()->query('SELECT id,nome,matricula FROM alunos WHERE ativo=1 ORDER BY nome')->fetchAll());
}
if ($m === 'GET') {
  $ts = db()->query('SELECT t.id,t.nome,t.descricao,t.ativo,u.nome professor FROM treinos t LEFT JOIN usuarios u ON u.id=t.id_criador WHERE t.ativo=1 ORDER BY t.nome')->fetchAll();
  $vinc = db()->query('SELECT ta.id_treino,a.id,a.nome FROM treino_alunos ta JOIN alunos a ON a.id=ta.id_aluno ORDER BY a.nome')->fetchAll();
  foreach ($ts as &$t) $t['alunos'] = array_values(array_filter($vinc, fn($v) => $v['id_treino'] == $t['id']));
  out(['aluno'=>false, 'treinos'=>$ts]);
}
if ($m === 'POST') {
  $i = input();
  $nome = trim($i['nome'] ?? '');
  if ($nome === '') out(['erro'=>'Nome obrigatório'], 422);
  $ids = array_values(array_unique(array_map('intval', $i['alunos'] ?? [])));
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $c = db()->prepare("SELECT COUNT(*) FROM alunos WHERE id IN ($in)"); $c->execute($ids);
    if ($c->fetchColumn() != count($ids)) out(['erro'=>'Aluno inválido'], 422);
  }
  $pdo = db(); $pdo->beginTransaction();
  if (!empty($i['id'])) {
    $id = (int)$i['id'];
    $pdo->prepare('UPDATE treinos SET nome=?,descricao=? WHERE id=? AND ativo=1')->execute([$nome, $i['descricao'] ?? null, $id]);
    $pdo->prepare('DELETE FROM treino_alunos WHERE id_treino=?')->execute([$id]);
  } else {
    $pdo->prepare('INSERT INTO treinos (nome,descricao,id_criador) VALUES (?,?,?)')->execute([$nome, $i['descricao'] ?? null, $me['id']]);
    $id = (int)$pdo->lastInsertId();
  }
  $ins = $pdo->prepare('INSERT INTO treino_alunos VALUES (?,?)');
  foreach ($ids as $a) $ins->execute([$id, $a]);
  $pdo->commit();
  out(['id'=>$id]);
}
if ($m === 'DELETE') {
  db()->prepare('UPDATE treinos SET ativo=0 WHERE id=?')->execute([(int)($_GET['id'] ?? 0)]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
