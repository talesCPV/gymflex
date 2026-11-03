<?php
/**
 * Contrato que todo banco/gateway precisa cumprir para ser plugado no GYMFLEX.
 *
 * Para adicionar um provedor (ex.: Inter, Santander, um gateway):
 *   1. crie api/integracao/MeuProvedor.php implementando esta interface;
 *   2. registre-o em Provedores::ativo() (um "case");
 *   3. defina 'provedor' => 'meu' em api/config.local.php e coloque as credenciais lá.
 * O resto (tabelas, webhook, baixa do lançamento) já funciona para qualquer provedor.
 *
 * Status normalizados: pendente | paga | cancelada | expirada
 */
interface ProvedorPagamento {
  /** Identificador gravado em cobrancas.provedor e usado na URL do webhook. */
  public function nome(): string;

  /**
   * Cria a cobrança no provedor para um lançamento a receber.
   * @param array $lancamento linha de v_lancamentos (id, descricao, valor, vencimento, aluno...)
   * @param array $opcoes     ex.: ['tipo' => 'pix'|'boleto'|'pix_boleto'|'cartao']
   * @return array ['id_externo','tipo','status','qr_code'?,'url'?,'linha_digitavel'?,'expira_em'?,'payload'=>array]
   */
  public function criarCobranca(array $lancamento, array $opcoes = []): array;

  /** Cancela a cobrança no provedor. */
  public function cancelarCobranca(string $idExterno): void;

  /** Consulta o estado atual. @return array ['status','pago_em'?,'valor'?] */
  public function consultarCobranca(string $idExterno): array;

  /**
   * Valida a autenticidade do webhook (assinatura, segredo, IP, mTLS...) e o converte
   * em eventos normalizados. Deve lançar RuntimeException se a requisição não for autêntica.
   * @param array $cabecalhos nomes em minúsculas (ex.: 'x-webhook-segredo')
   * @return array lista de ['id_externo','status','pago_em'?(Y-m-d H:i:s),'valor'?]
   */
  public function interpretarWebhook(string $corpo, array $cabecalhos): array;
}
