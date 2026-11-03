<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

const MODULOS = ['usuarios','alunos','funcionarios','treinos','financeiro'];

function db(): PDO {
  static $pdo;
  if (!$pdo) {
    $c = require __DIR__.'/config.php';
    $pdo = new PDO("mysql:host={$c['host']};dbname={$c['db']};charset=utf8mb4", $c['user'], $c['pass'],
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  }
  return $pdo;
}
function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data); exit; }
function input(): array { return json_decode(file_get_contents('php://input'), true) ?? $_POST; }

function user(): ?array {
  if (empty($_SESSION['uid'])) return null;
  $s = db()->prepare('SELECT u.id,u.nome,u.email,u.id_perfil,p.nome perfil,p.master FROM usuarios u JOIN perfis p ON p.id=u.id_perfil WHERE u.id=? AND u.ativo=1');
  $s->execute([$_SESSION['uid']]);
  $u = $s->fetch();
  if (!$u) return null;
  if ($u['master']) { $u['modulos'] = MODULOS; }
  else {
    $m = db()->prepare('SELECT modulo FROM perfil_modulos WHERE id_perfil=?');
    $m->execute([$u['id_perfil']]);
    $u['modulos'] = $m->fetchAll(PDO::FETCH_COLUMN);
  }
  return $u;
}
function require_module(string $mod): array {
  $u = user();
  if (!$u) out(['erro'=>'Não autenticado'], 401);
  if (!in_array($mod, $u['modulos'], true)) out(['erro'=>'Sem permissão'], 403);
  return $u;
}
function require_master(): array {
  $u = user();
  if (!$u) out(['erro'=>'Não autenticado'], 401);
  if (!$u['master']) out(['erro'=>'Apenas Master'], 403);
  return $u;
}
function valid_pass(string $p): bool { return strlen($p) >= 8; }
