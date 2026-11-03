<?php
require_once __DIR__.'/Provedores.php';

/** Regras independentes de provedor: criar cobrança e processar webhooks. */
class Servico {
  public static function criarCobranca(int $idLancamento, array $opcoes = []): array {
    $p = Provedores::ativo();
    if (!$p) throw new RuntimeException('Nenhuma integração de pagamento ativa');
    $q = db()->prepare('SELECT * FROM v_lancamentos WHERE id=?'); $q->execute([$idLancamento]);
    $l = $q->fetch();
    if (!$l || $l['tipo'] !== 'receber') throw new RuntimeException('Só é possível cobrar lançamentos a receber');
    if ($l['status'] === 'pago' || $l['status'] === 'cancelado') throw new RuntimeException('Lançamento já pago ou cancelado');
    // cobrança pendente existente é reaproveitada (evita duas cobranças para a mesma mensalidade)
    $ex = db()->prepare("SELECT * FROM cobrancas WHERE id_lancamento=? AND provedor=? AND status='pendente' ORDER BY id DESC LIMIT 1");
    $ex->execute([$idLancamento, $p->nome()]);
    if ($c = $ex->fetch()) return $c;
    $r = $p->criarCobranca($l, $opcoes);
    db()->prepare('INSERT INTO cobrancas (id_lancamento,provedor,tipo,id_externo,status,valor,qr_code,url,linha_digitavel,expira_em,payload) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$idLancamento, $p->nome(), $r['tipo'] ?? 'pix', $r['id_externo'], $r['status'] ?? 'pendente', $l['valor'],
                 $r['qr_code'] ?? null, $r['url'] ?? null, $r['linha_digitavel'] ?? null, $r['expira_em'] ?? null,
                 json_encode($r['payload'] ?? [])]);
    $id = (int)db()->lastInsertId();
    $s = db()->prepare('SELECT * FROM cobrancas WHERE id=?'); $s->execute([$id]);
    return $s->fetch();
  }

  public static function cancelarCobranca(int $id): void {
    $s = db()->prepare("SELECT * FROM cobrancas WHERE id=? AND status='pendente'"); $s->execute([$id]);
    $c = $s->fetch();
    if (!$c) throw new RuntimeException('Cobrança pendente não encontrada');
    $p = Provedores::ativo();
    if ($p && $p->nome() === $c['provedor']) $p->cancelarCobranca($c['id_externo']);
    db()->prepare("UPDATE cobrancas SET status='cancelada' WHERE id=?")->execute([$id]);
  }

  /** @return array [http_status, corpo] */
  public static function webhook(string $nomeProvedor, string $corpo, array $cab): array {
    $p = Provedores::ativo();
    if (!$p || $p->nome() !== $nomeProvedor) return [404, ['erro' => 'Provedor não ativo']];
    $log = db()->prepare('INSERT INTO integracao_eventos (provedor,corpo,assinatura_valida) VALUES (?,?,0)');
    $log->execute([$nomeProvedor, $corpo]);
    $idLog = (int)db()->lastInsertId();
    try { $eventos = $p->interpretarWebhook($corpo, $cab); }
    catch (RuntimeException $e) {
      db()->prepare('UPDATE integracao_eventos SET erro=? WHERE id=?')->execute([substr($e->getMessage(), 0, 255), $idLog]);
      return [401, ['erro' => 'Webhook rejeitado']];
    }
    db()->prepare('UPDATE integracao_eventos SET assinatura_valida=1, id_externo=?, status_evento=? WHERE id=?')
      ->execute([$eventos[0]['id_externo'] ?? null, $eventos[0]['status'] ?? null, $idLog]);
    $feitos = 0; $erros = [];
    foreach ($eventos as $ev) {
      $s = db()->prepare('SELECT * FROM cobrancas WHERE provedor=? AND id_externo=?'); $s->execute([$nomeProvedor, $ev['id_externo']]);
      $c = $s->fetch();
      if (!$c) { $erros[] = "Cobrança {$ev['id_externo']} desconhecida"; continue; }
      if ($c['status'] === $ev['status']) { $feitos++; continue; }      // evento repetido: nada a fazer
      db()->prepare('UPDATE cobrancas SET status=?, pago_em=? WHERE id=?')->execute([$ev['status'], $ev['pago_em'] ?? null, $c['id']]);
      if ($ev['status'] === 'paga') {
        $b = db()->prepare('CALL sp_baixar_lancamento(?,?,?)');
        $b->execute([$c['id_lancamento'], substr($ev['pago_em'] ?? date('Y-m-d'), 0, 10), $c['tipo'] === 'cartao' ? 'cartao' : ($c['tipo'] === 'boleto' ? 'boleto' : 'pix')]);
        $b->closeCursor();
      }
      $feitos++;
    }
    db()->prepare('UPDATE integracao_eventos SET processado=?, erro=? WHERE id=?')
      ->execute([$erros ? 0 : 1, $erros ? substr(implode('; ', $erros), 0, 255) : null, $idLog]);
    return [200, ['ok' => true, 'processados' => $feitos, 'erros' => count($erros)]];
  }
}
