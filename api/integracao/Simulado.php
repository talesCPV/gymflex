<?php
require_once __DIR__.'/ProvedorPagamento.php';

/**
 * Provedor de testes: não fala com nenhum banco. Serve para exercitar todo o fluxo
 * (cobrança -> webhook -> baixa automática) antes de existir um provedor real.
 * Webhook de teste: POST api/webhook.php?provedor=simulado
 *   cabeçalho  X-Webhook-Segredo: <integracao.webhook_segredo>
 *   corpo      {"id_externo":"SIM...","status":"paga","valor":129.90}
 */
class Simulado implements ProvedorPagamento {
  public function __construct(private array $cfg) {}

  public function nome(): string { return 'simulado'; }

  public function criarCobranca(array $l, array $opcoes = []): array {
    $id = 'SIM' . strtoupper(bin2hex(random_bytes(8)));
    return [
      'id_externo' => $id, 'tipo' => $opcoes['tipo'] ?? 'pix', 'status' => 'pendente',
      'qr_code' => "00020126SIMULADO{$id}5204000053039865802BR",
      'expira_em' => date('Y-m-d H:i:s', strtotime('+3 days')),
      'payload' => ['simulado' => true, 'lancamento' => $l['id']],
    ];
  }
  public function cancelarCobranca(string $idExterno): void {}
  public function consultarCobranca(string $idExterno): array { return ['status' => 'pendente']; }

  public function interpretarWebhook(string $corpo, array $cab): array {
    $segredo = (string)($this->cfg['webhook_segredo'] ?? '');
    if ($segredo === '' || !hash_equals($segredo, $cab['x-webhook-segredo'] ?? '')) throw new RuntimeException('Segredo inválido');
    $d = json_decode($corpo, true);
    if (!is_array($d) || empty($d['id_externo']) || !in_array($d['status'] ?? '', ['pendente','paga','cancelada','expirada'], true))
      throw new RuntimeException('Corpo inválido');
    return [['id_externo' => $d['id_externo'], 'status' => $d['status'],
             'pago_em' => $d['status'] === 'paga' ? date('Y-m-d H:i:s') : null, 'valor' => $d['valor'] ?? null]];
  }
}
