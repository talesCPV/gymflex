<?php
require __DIR__.'/lib.php';
$i = input();
$s = db()->prepare('SELECT id,senha_hash FROM usuarios WHERE email=? AND ativo=1');
$s->execute([strtolower(trim($i['email'] ?? ''))]);
$u = $s->fetch();
if (!$u || !password_verify($i['senha'] ?? '', $u['senha_hash'])) out(['erro'=>'Email ou senha inválidos'], 401);
session_regenerate_id(true);
$_SESSION['uid'] = $u['id'];
out(user());
