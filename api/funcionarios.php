<?php
// GET lista | GET ?usuarios=ID usuários livres | POST {id?,...} | DELETE ?id= (desativa)
require __DIR__.'/lib.php';
require_module('funcionarios');
$m = $_SERVER['REQUEST_METHOD'];

function cpf_valido(string $c): bool {
  if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
  for ($t = 9; $t < 11; $t++) {
    for ($d = 0, $i = 0; $i < $t; $i++) $d += $c[$i] * (($t + 1) - $i);
    if ($c[$t] != ((10 * $d) % 11) % 10) return false;
  }
  return true;
}

if ($m === 'GET' && isset($_GET['usuarios'])) {
  $q = db()->prepare('SELECT id,nome,email FROM usuarios WHERE ativo=1 AND (id NOT IN (SELECT id_usuario FROM funcionarios WHERE id_usuario IS NOT NULL) OR id=?) ORDER BY nome');
  $q->execute([(int)$_GET['usuarios']]);
  out($q->fetchAll());
}
if ($m === 'GET') {
  out(db()->query('SELECT f.*,u.email usuario_email FROM funcionarios f LEFT JOIN usuarios u ON u.id=f.id_usuario ORDER BY f.nome')->fetchAll());
}
if ($m === 'POST') {
  $i = input();
  $nome = trim($i['nome'] ?? ''); $cargo = trim($i['cargo'] ?? '');
  $cpf = preg_replace('/\D/', '', $i['cpf'] ?? '');
  $salario = (float)str_replace(',', '.', (string)($i['salario'] ?? 0));
  $data = $i['data_admissao'] ?? date('Y-m-d');
  $idu = ($i['id_usuario'] ?? '') === '' ? null : (int)$i['id_usuario'];
  if ($nome === '' || $cargo === '') out(['erro'=>'Nome e cargo são obrigatórios'], 422);
  if ($cpf !== '' && !cpf_valido($cpf)) out(['erro'=>'CPF inválido'], 422);
  if ($salario < 0) out(['erro'=>'Salário inválido'], 422);
  if (!DateTime::createFromFormat('Y-m-d', $data)) out(['erro'=>'Data inválida'], 422);
  if ($idu !== null) {
    $c = db()->prepare('SELECT 1 FROM usuarios WHERE id=?'); $c->execute([$idu]);
    if (!$c->fetch()) out(['erro'=>'Usuário não encontrado'], 422);
  }
  $campos = [$nome,$idu,$cargo,$cpf ?: null,trim($i['telefone']??'')?:null,trim($i['endereco']??'')?:null,$data,$salario];
  try {
    if (!empty($i['id'])) {
      db()->prepare('UPDATE funcionarios SET nome=?,id_usuario=?,cargo=?,cpf=?,telefone=?,endereco=?,data_admissao=?,salario=?,ativo=? WHERE id=?')
        ->execute([...$campos,(int)($i['ativo']??1),$i['id']]);
      out(['id'=>(int)$i['id']]);
    }
    db()->prepare('INSERT INTO funcionarios (nome,id_usuario,cargo,cpf,telefone,endereco,data_admissao,salario) VALUES (?,?,?,?,?,?,?,?)')
      ->execute($campos);
    out(['id'=>(int)db()->lastInsertId()]);
  } catch (PDOException $e) {
    $msg = str_contains($e->getMessage(), 'cpf') ? 'CPF já cadastrado' : 'Esse usuário já está vinculado a outro funcionário';
    out(['erro'=>$e->getCode()==23000 ? $msg : 'Erro ao salvar'], 409);
  }
}
if ($m === 'DELETE') {
  db()->prepare('UPDATE funcionarios SET ativo=0 WHERE id=?')->execute([(int)($_GET['id'] ?? 0)]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
