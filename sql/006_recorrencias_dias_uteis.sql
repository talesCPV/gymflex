-- GYMFLEX - Recorrências automáticas, dia de pagamento do salário e cancelamento lógico

-- dia do mês em que o salário do funcionário é pago
ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS dia_pagamento TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER salario;

-- modelos de contas recorrentes (a pagar e a receber), geradas todo mês automaticamente
CREATE TABLE IF NOT EXISTS recorrencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('receber','pagar') NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  descricao VARCHAR(160) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  dia_vencimento TINYINT UNSIGNED NOT NULL,
  inicio CHAR(7) NOT NULL,                 -- AAAA-MM da primeira ocorrência
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE lancamentos ADD COLUMN IF NOT EXISTS id_recorrencia INT NULL;
-- cancelado: lançamentos gerados automaticamente não são apagados (senão seriam recriados)
ALTER TABLE lancamentos ADD COLUMN IF NOT EXISTS cancelado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE lancamentos ADD UNIQUE KEY IF NOT EXISTS uq_rec_comp (id_recorrencia, competencia);
