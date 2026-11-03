<?php
// Autenticado: POST {email,id_perfil} gera convite | GET lista
// Público:     GET ?token= valida | POST {token,nome,celular,senha} aceita
require __DIR__.'/lib.php';
$m = $_SERVER['REQUEST_METHOD'];
$i = $m === 'POST' ? input() : [];
$token = $_GET['token'] ?? $i['token'] ?? null;

if ($token) {
  $q = db()->prepare('SELECT * FROM convites WHERE token=? AND usado=0 AND expira_em>NOW()');
  $q->execute([$token]); $c = $q->fetch();
  if (!$c) out(['erro'=>'Convite inválido ou expirado'], 404);
  if ($m === 'GET') out(['email'=>$c['email']]);
  $nome = trim($i['nome'] ?? '');
  if ($nome === '' || !valid_pass($i['senha'] ?? '')) out(['erro'=>'Nome e senha (mín. 8) obrigatórios'], 422);
  $pdo = db(); $pdo->beginTransaction();
  try {
    $pdo->prepare('INSERT INTO usuarios (nome,email,celular,senha_hash,id_perfil) VALUES (?,?,?,?,?)')
      ->execute([$nome,$c['email'],$i['celular']??null,password_hash($i['senha'],PASSWORD_DEFAULT),$c['id_perfil']]);
    $pdo->prepare('UPDATE convites SET usado=1 WHERE id=?')->execute([$c['id']]);
    $pdo->commit();
  } catch (PDOException $e) { $pdo->rollBack(); out(['erro'=>'Email já cadastrado'], 409); }
  out(['ok'=>true]);
}

$me = require_module('usuarios');
if ($m === 'GET') out(db()->query('SELECT c.id,c.email,p.nome perfil,c.usado,c.expira_em FROM convites c JOIN perfis p ON p.id=c.id_perfil ORDER BY c.id DESC LIMIT 100')->fetchAll());
if ($m === 'POST') {
  $email = strtolower(trim($i['email'] ?? '')); $idp = (int)($i['id_perfil'] ?? 0);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['erro'=>'Email inválido'], 422);
  $p = db()->prepare('SELECT master FROM perfis WHERE id=?'); $p->execute([$idp]); $p = $p->fetch();
  if (!$p || ($p['master'] && !$me['master'])) out(['erro'=>'Perfil inválido ou não permitido'], 403);
  $t = bin2hex(random_bytes(32));
  db()->prepare('INSERT INTO convites (token,email,id_perfil,id_criador,expira_em) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 7 DAY))')
    ->execute([$t,$email,$idp,$me['id']]);
  out(['token'=>$t, 'link'=>"convite.html?token=$t"]);   // envio por email: integrar depois
}
out(['erro'=>'Método inválido'], 405);
