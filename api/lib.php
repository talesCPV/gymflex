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

// ---- Dias úteis (feriados nacionais; municipais/estaduais não são considerados) ----
function feriados_nacionais(int $ano): array {
  static $cache = [];
  if (isset($cache[$ano])) return $cache[$ano];
  // Páscoa (algoritmo de Meeus/Jones/Butcher)
  $a = $ano % 19; $b = intdiv($ano, 100); $c = $ano % 100; $d = intdiv($b, 4); $e = $b % 4;
  $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
  $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $mm = intdiv($a + 11 * $h + 22 * $l, 451);
  $mes = intdiv($h + $l - 7 * $mm + 114, 31); $dia = (($h + $l - 7 * $mm + 114) % 31) + 1;
  $pascoa = new DateTime(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
  $mov = fn(int $n) => (clone $pascoa)->modify(($n >= 0 ? '+' : '') . "$n days")->format('m-d');
  $fix = ['01-01','04-21','05-01','09-07','10-12','11-02','11-15','12-25'];
  if ($ano >= 2024) $fix[] = '11-20';                       // Consciência Negra (nacional desde 2024)
  // Carnaval (seg/ter), Sexta-feira Santa e Corpus Christi: bancos não funcionam
  return $cache[$ano] = array_merge($fix, [$mov(-48), $mov(-47), $mov(-2), $mov(60)]);
}
function eh_util(DateTime $d): bool {
  return (int)$d->format('N') < 6 && !in_array($d->format('m-d'), feriados_nacionais((int)$d->format('Y')), true);
}
// Dia útil mais próximo (empate: o seguinte). Ex.: sábado -> sexta, domingo -> segunda.
function dia_util_proximo(string $data): string {
  $d = new DateTime($data);
  if (eh_util($d)) return $data;
  for ($k = 1; $k <= 10; $k++) {
    $depois = (clone $d)->modify("+$k days"); if (eh_util($depois)) return $depois->format('Y-m-d');
    $antes = (clone $d)->modify("-$k days");  if (eh_util($antes))  return $antes->format('Y-m-d');
  }
  return $data;
}
