<?php
// GET lista | POST {id?,nome,modulos[]} cria/edita | DELETE ?id=
require __DIR__.'/lib.php';
$m = $_SERVER['REQUEST_METHOD'];
$me = $m === 'GET' ? require_module('usuarios') : require_master();
if ($m === 'GET') {
  $rows = db()->query('SELECT id,nome,master FROM perfis ORDER BY id')->fetchAll();
  foreach ($rows as &$r) {
    $q = db()->prepare('SELECT modulo FROM perfil_modulos WHERE id_perfil=?');
    $q->execute([$r['id']]);
    $r['modulos'] = $r['master'] ? MODULOS : $q->fetchAll(PDO::FETCH_COLUMN);
  }
  out($rows);
}
if ($m === 'POST') {
  $i = input();
  $nome = trim($i['nome'] ?? '');
  $mods = array_values(array_intersect($i['modulos'] ?? [], MODULOS));
  if ($nome === '') out(['erro'=>'Nome obrigatório'], 422);
  $pdo = db(); $pdo->beginTransaction();
  if (!empty($i['id'])) {
    $chk = $pdo->prepare('SELECT master FROM perfis WHERE id=?'); $chk->execute([$i['id']]);
    $p = $chk->fetch();
    if (!$p || $p['master']) { $pdo->rollBack(); out(['erro'=>'Perfil Master não pode ser editado'], 403); }
    $pdo->prepare('UPDATE perfis SET nome=? WHERE id=?')->execute([$nome, $i['id']]);
    $id = (int)$i['id'];
    $pdo->prepare('DELETE FROM perfil_modulos WHERE id_perfil=?')->execute([$id]);
  } else {
    try { $pdo->prepare('INSERT INTO perfis (nome) VALUES (?)')->execute([$nome]); }
    catch (PDOException $e) { $pdo->rollBack(); out(['erro'=>'Perfil já existe'], 409); }
    $id = (int)$pdo->lastInsertId();
  }
  $ins = $pdo->prepare('INSERT INTO perfil_modulos VALUES (?,?)');
  foreach ($mods as $mod) $ins->execute([$id, $mod]);
  $pdo->commit();
  out(['id'=>$id]);
}
if ($m === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  $q = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE id_perfil=?'); $q->execute([$id]);
  if ($q->fetchColumn() > 0) out(['erro'=>'Perfil em uso por usuários'], 409);
  db()->prepare('DELETE FROM perfis WHERE id=? AND master=0')->execute([$id]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
