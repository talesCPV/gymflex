<?php
// GET lista | POST {id?,nome,email,celular,senha?,id_perfil,ativo?} | DELETE ?id= (desativa)
require __DIR__.'/lib.php';
$me = require_module('usuarios');
$m = $_SERVER['REQUEST_METHOD'];
function perfil_permitido(array $me, int $idPerfil): bool {
  $q = db()->prepare('SELECT master FROM perfis WHERE id=?'); $q->execute([$idPerfil]);
  $p = $q->fetch();
  return $p && (!$p['master'] || $me['master']);   // só Master cria Master
}
if ($m === 'GET') {
  out(db()->query('SELECT u.id,u.nome,u.email,u.celular,u.ativo,u.id_perfil,p.nome perfil FROM usuarios u JOIN perfis p ON p.id=u.id_perfil ORDER BY u.nome')->fetchAll());
}
if ($m === 'POST') {
  $i = input();
  $nome = trim($i['nome'] ?? ''); $email = strtolower(trim($i['email'] ?? ''));
  $idp = (int)($i['id_perfil'] ?? 0);
  if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(['erro'=>'Nome e email válidos são obrigatórios'], 422);
  if (!perfil_permitido($me, $idp)) out(['erro'=>'Perfil inválido ou não permitido'], 403);
  try {
    if (!empty($i['id'])) {
      $alvo = db()->prepare('SELECT p.master FROM usuarios u JOIN perfis p ON p.id=u.id_perfil WHERE u.id=?');
      $alvo->execute([$i['id']]); $a = $alvo->fetch();
      if (!$a) out(['erro'=>'Usuário não encontrado'], 404);
      if ($a['master'] && !$me['master']) out(['erro'=>'Sem permissão'], 403);
      db()->prepare('UPDATE usuarios SET nome=?,email=?,celular=?,id_perfil=?,ativo=? WHERE id=?')
        ->execute([$nome,$email,$i['celular']??null,$idp,(int)($i['ativo']??1),$i['id']]);
      if (!empty($i['senha'])) {
        if (!valid_pass($i['senha'])) out(['erro'=>'Senha mínima de 8 caracteres'], 422);
        db()->prepare('UPDATE usuarios SET senha_hash=? WHERE id=?')->execute([password_hash($i['senha'], PASSWORD_DEFAULT), $i['id']]);
      }
      out(['id'=>(int)$i['id']]);
    }
    if (!valid_pass($i['senha'] ?? '')) out(['erro'=>'Senha mínima de 8 caracteres'], 422);
    db()->prepare('INSERT INTO usuarios (nome,email,celular,senha_hash,id_perfil) VALUES (?,?,?,?,?)')
      ->execute([$nome,$email,$i['celular']??null,password_hash($i['senha'], PASSWORD_DEFAULT),$idp]);
    out(['id'=>(int)db()->lastInsertId()]);
  } catch (PDOException $e) {
    out(['erro'=>$e->getCode()==23000 ? 'Email já cadastrado' : 'Erro ao salvar'], 409);
  }
}
if ($m === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  if ($id === $me['id']) out(['erro'=>'Você não pode desativar a si mesmo'], 409);
  db()->prepare('UPDATE usuarios u JOIN perfis p ON p.id=u.id_perfil SET u.ativo=0 WHERE u.id=? AND (p.master=0 OR ?)')
    ->execute([$id, (int)$me['master']]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
