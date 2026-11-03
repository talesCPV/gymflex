<?php
// CLI: php api/setup.php "Nome" email senha  -> cria o usuário Master inicial
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__.'/lib.php';
[, $nome, $email, $senha] = $argv + [null,null,null,null];
if (!$nome || !$email || !$senha || strlen($senha) < 8) exit("Uso: php api/setup.php \"Nome\" email senha(>=8)\n");
db()->prepare('INSERT INTO usuarios (nome,email,senha_hash,id_perfil) VALUES (?,?,?,1)')
  ->execute([$nome, strtolower($email), password_hash($senha, PASSWORD_DEFAULT)]);
echo "Master criado.\n";
