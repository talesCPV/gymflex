<?php
// GET lista | GET ?usuarios=1 usuários ainda sem aluno | POST {id?,...} | DELETE ?id= (desativa)
require __DIR__.'/lib.php';
$me = require_module('alunos');
$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'GET' && isset($_GET['usuarios'])) {
  $q = db()->prepare('SELECT u.id,u.nome,u.email FROM usuarios u WHERE u.ativo=1 AND (u.id NOT IN (SELECT id_usuario FROM alunos WHERE id_usuario IS NOT NULL) OR u.id=?) ORDER BY u.nome');
  $q->execute([(int)$_GET['usuarios']]);
  out($q->fetchAll());
}
if ($m === 'GET') {
  out(db()->query('SELECT a.*,u.email usuario_email FROM alunos a LEFT JOIN usuarios u ON u.id=a.id_usuario ORDER BY a.nome')->fetchAll());
}
if ($m === 'POST') {
  $i = input();
  $nome = trim($i['nome'] ?? '');
  $valor = (float)str_replace(',', '.', (string)($i['valor_combinado'] ?? 0));
  $dia = (int)($i['dia_vencimento'] ?? 10);
  $data = $i['data_cadastro'] ?? date('Y-m-d');
  $idu = ($i['id_usuario'] ?? '') === '' ? null : (int)$i['id_usuario'];
  if ($nome === '') out(['erro'=>'Nome obrigatório'], 422);
  if ($valor < 0) out(['erro'=>'Valor inválido'], 422);
  if ($dia < 1 || $dia > 31) out(['erro'=>'Dia de vencimento deve ser de 1 a 31'], 422);
  if (!DateTime::createFromFormat('Y-m-d', $data)) out(['erro'=>'Data inválida'], 422);
  if ($idu !== null) {
    $c = db()->prepare('SELECT 1 FROM usuarios WHERE id=?'); $c->execute([$idu]);
    if (!$c->fetch()) out(['erro'=>'Usuário não encontrado'], 422);
  }
  $campos = [$nome,$idu,trim($i['endereco']??'')?:null,trim($i['telefone']??'')?:null,trim($i['contrato']??'')?:null,$data,$valor,$dia];
  try {
    if (!empty($i['id'])) {
      db()->prepare('UPDATE alunos SET nome=?,id_usuario=?,endereco=?,telefone=?,contrato=?,data_cadastro=?,valor_combinado=?,dia_vencimento=?,ativo=? WHERE id=?')
        ->execute([...$campos,(int)($i['ativo']??1),$i['id']]);
      out(['id'=>(int)$i['id']]);
    }
    db()->prepare('INSERT INTO alunos (nome,id_usuario,endereco,telefone,contrato,data_cadastro,valor_combinado,dia_vencimento) VALUES (?,?,?,?,?,?,?,?)')
      ->execute($campos);
    $id = (int)db()->lastInsertId();
    db()->prepare('UPDATE alunos SET matricula=? WHERE id=?')->execute([date('Y').str_pad($id, 5, '0', STR_PAD_LEFT), $id]);
    out(['id'=>$id]);
  } catch (PDOException $e) {
    out(['erro'=>$e->getCode()==23000 ? 'Esse usuário já está vinculado a outro aluno' : 'Erro ao salvar'], 409);
  }
}
if ($m === 'DELETE') {
  db()->prepare('UPDATE alunos SET ativo=0 WHERE id=?')->execute([(int)($_GET['id'] ?? 0)]);
  out(['ok'=>true]);
}
out(['erro'=>'Método inválido'], 405);
