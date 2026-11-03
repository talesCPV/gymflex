<?php
require_once __DIR__.'/ProvedorPagamento.php';

class Provedores {
  /** Provedor configurado em integracao.provedor, ou null (integração desligada). */
  public static function ativo(): ?ProvedorPagamento {
    $cfg = (require __DIR__.'/../config.php')['integracao'] ?? [];   // config.php antigo, sem a chave: integração desligada
    switch ($cfg['provedor'] ?? null) {
      case null: case '': return null;
      case 'simulado': require_once __DIR__.'/Simulado.php'; return new Simulado($cfg);
      // case 'inter': require_once __DIR__.'/Inter.php'; return new Inter($cfg);
      default: throw new RuntimeException('Provedor desconhecido: ' . $cfg['provedor']);
    }
  }
}
