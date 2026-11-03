-- =====================================================================
-- GYMFLEX - Schema completo (tabelas, views e procedures)
--
-- Uso (crie o banco antes, com o nome que quiser):
--   mysql -u USUARIO -p NOME_DO_BANCO < sql/schema.sql
--
-- Pode ser executado de novo sem perder dados (CREATE ... IF NOT EXISTS,
-- views e procedures são recriadas). Testado em MariaDB 10.11; a sintaxe é
-- compatível com MySQL 8.
-- Depois: php api/setup.php "Nome" email senha   (cria o usuário Master)
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- USUÁRIOS E PERFIS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL UNIQUE,
  master TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- módulos liberados para cada perfil (o Master ignora esta tabela)
CREATE TABLE IF NOT EXISTS perfil_modulos (
  id_perfil INT NOT NULL,
  modulo VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_perfil, modulo),
  FOREIGN KEY (id_perfil) REFERENCES perfis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  celular VARCHAR(20) NULL,
  senha_hash VARCHAR(255) NOT NULL,       -- password_hash() do PHP
  id_perfil INT NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_perfil) REFERENCES perfis(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token CHAR(64) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL,
  id_perfil INT NOT NULL,
  id_criador INT NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  expira_em DATETIME NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_perfil) REFERENCES perfis(id),
  FOREIGN KEY (id_criador) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO perfis (id, nome, master) VALUES (1, 'Master', 1);

-- ---------------------------------------------------------------------
-- ALUNOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alunos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  id_usuario INT NULL UNIQUE,              -- login do aluno (opcional)
  endereco VARCHAR(255) NULL,
  telefone VARCHAR(20) NULL,
  matricula VARCHAR(20) NULL UNIQUE,       -- gerada pela API: AAAA + id (5 dígitos)
  contrato VARCHAR(60) NULL,               -- Mensal, Trimestral, Anual...
  data_cadastro DATE NOT NULL,
  valor_combinado DECIMAL(10,2) NOT NULL DEFAULT 0,
  dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,   -- dia do mês da mensalidade
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TREINOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS treinos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT NULL,
  id_criador INT NULL,                     -- professor que montou o treino
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_criador) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treino_alunos (
  id_treino INT NOT NULL,
  id_aluno INT NOT NULL,
  PRIMARY KEY (id_treino, id_aluno),
  FOREIGN KEY (id_treino) REFERENCES treinos(id) ON DELETE CASCADE,
  FOREIGN KEY (id_aluno) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FUNCIONÁRIOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcionarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  id_usuario INT NULL UNIQUE,              -- login do funcionário (opcional)
  cargo VARCHAR(60) NOT NULL,              -- Professor, Secretaria, Atendente...
  cpf CHAR(11) NULL UNIQUE,                -- somente dígitos
  telefone VARCHAR(20) NULL,
  endereco VARCHAR(255) NULL,
  data_admissao DATE NOT NULL,
  salario DECIMAL(10,2) NOT NULL DEFAULT 0,
  dia_pagamento TINYINT UNSIGNED NOT NULL DEFAULT 5,     -- dia do mês do pagamento do salário
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FINANCEIRO
-- ---------------------------------------------------------------------
-- modelos de contas recorrentes (a pagar e a receber): geram um lançamento por mês
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

CREATE TABLE IF NOT EXISTS lancamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('receber','pagar') NOT NULL,
  categoria VARCHAR(60) NOT NULL,          -- Mensalidade, Aluguel, Luz, Salário...
  descricao VARCHAR(160) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  vencimento DATE NOT NULL,
  pago_em DATE NULL,                       -- NULL = pendente
  forma_pagamento VARCHAR(20) NULL,        -- manual, pix, boleto, cartao...
  id_aluno INT NULL,                       -- mensalidade gerada
  id_funcionario INT NULL,                 -- salário gerado
  id_recorrencia INT NULL,                 -- ocorrência de conta recorrente
  competencia CHAR(7) NULL,                -- AAAA-MM (só nos lançamentos gerados)
  cancelado TINYINT(1) NOT NULL DEFAULT 0, -- gerados automaticamente não são apagados, só cancelados
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vencimento),
  UNIQUE KEY uq_aluno_comp (id_aluno, competencia),        -- impede mensalidade duplicada
  UNIQUE KEY uq_func_comp (id_funcionario, competencia),   -- impede salário duplicado
  UNIQUE KEY uq_rec_comp (id_recorrencia, competencia),    -- impede recorrência duplicada
  FOREIGN KEY (id_aluno) REFERENCES alunos(id) ON DELETE SET NULL,
  FOREIGN KEY (id_funcionario) REFERENCES funcionarios(id) ON DELETE SET NULL,
  FOREIGN KEY (id_recorrencia) REFERENCES recorrencias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- INTEGRAÇÃO BANCÁRIA / GATEWAY (preparada; nenhum provedor ativo ainda)
-- Independe do banco escolhido: cada provedor é uma classe em api/integracao/.
-- ---------------------------------------------------------------------
-- cobranças emitidas no provedor (Pix, boleto, cartão) para um lançamento a receber
CREATE TABLE IF NOT EXISTS cobrancas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_lancamento INT NOT NULL,
  provedor VARCHAR(30) NOT NULL,           -- inter, santander, simulado...
  tipo ENUM('pix','boleto','pix_boleto','cartao') NOT NULL DEFAULT 'pix',
  id_externo VARCHAR(100) NOT NULL,        -- txid / nosso número / id da cobrança no provedor
  status ENUM('pendente','paga','cancelada','expirada') NOT NULL DEFAULT 'pendente',
  valor DECIMAL(10,2) NOT NULL,
  qr_code TEXT NULL,                       -- Pix copia e cola
  url VARCHAR(500) NULL,                   -- link do boleto / checkout
  linha_digitavel VARCHAR(100) NULL,
  expira_em DATETIME NULL,
  pago_em DATETIME NULL,
  payload MEDIUMTEXT NULL,                 -- resposta bruta do provedor (JSON)
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cobranca_externa (provedor, id_externo),
  INDEX (id_lancamento),
  FOREIGN KEY (id_lancamento) REFERENCES lancamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- registro de tudo o que chega nos webhooks (auditoria e reprocessamento)
CREATE TABLE IF NOT EXISTS integracao_eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(30) NOT NULL,
  id_externo VARCHAR(100) NULL,
  status_evento VARCHAR(30) NULL,
  corpo MEDIUMTEXT NOT NULL,
  assinatura_valida TINYINT(1) NOT NULL DEFAULT 0,
  processado TINYINT(1) NOT NULL DEFAULT 0,
  erro VARCHAR(255) NULL,
  recebido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (provedor, id_externo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- movimentações do extrato do banco, para conciliação com os lançamentos
CREATE TABLE IF NOT EXISTS extrato_bancario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provedor VARCHAR(30) NOT NULL,
  id_externo VARCHAR(100) NOT NULL,
  data DATE NOT NULL,
  tipo ENUM('credito','debito') NOT NULL,
  valor DECIMAL(12,2) NOT NULL,
  descricao VARCHAR(255) NULL,
  id_lancamento INT NULL,                  -- preenchido quando conciliado
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extrato (provedor, id_externo),
  FOREIGN KEY (id_lancamento) REFERENCES lancamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- VIEWS
-- ---------------------------------------------------------------------
-- lançamentos com nome do aluno/funcionário e status calculado
CREATE OR REPLACE VIEW v_lancamentos AS
SELECT l.id, l.tipo, l.categoria, l.descricao, l.valor, l.vencimento, l.pago_em, l.forma_pagamento,
       l.id_aluno, l.id_funcionario, l.id_recorrencia, l.competencia, l.cancelado, l.criado_em,
       a.nome AS aluno, f.nome AS funcionario,
       CASE WHEN l.cancelado = 1 THEN 'cancelado'
            WHEN l.pago_em IS NOT NULL THEN 'pago'
            WHEN l.vencimento < CURDATE() THEN 'atrasado'
            ELSE 'pendente' END AS status,
       (l.cancelado = 0 AND l.pago_em IS NULL AND l.vencimento < CURDATE()) AS atrasado
FROM lancamentos l
LEFT JOIN alunos a ON a.id = l.id_aluno
LEFT JOIN funcionarios f ON f.id = l.id_funcionario;

-- saúde financeira por mês (AAAA-MM) e tipo, ignorando cancelados
CREATE OR REPLACE VIEW v_resumo_mensal AS
SELECT DATE_FORMAT(vencimento, '%Y-%m') AS mes, tipo,
       SUM(valor) AS total,
       SUM(IF(pago_em IS NOT NULL, valor, 0)) AS pago,
       SUM(IF(pago_em IS NULL, valor, 0)) AS pendente,
       SUM(IF(pago_em IS NULL AND vencimento < CURDATE(), valor, 0)) AS atrasado
FROM lancamentos
WHERE cancelado = 0
GROUP BY DATE_FORMAT(vencimento, '%Y-%m'), tipo;

-- ---------------------------------------------------------------------
-- PROCEDURES
-- A baixa/estorno ficam no banco para que o pagamento manual, o webhook do
-- banco e a conciliação usem exatamente a mesma regra.
-- ---------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_baixar_lancamento$$
CREATE PROCEDURE sp_baixar_lancamento(IN p_id INT, IN p_data DATE, IN p_forma VARCHAR(20))
BEGIN
  -- só baixa o que está pendente (idempotente: webhook repetido não faz nada)
  UPDATE lancamentos
     SET pago_em = COALESCE(p_data, CURDATE()), forma_pagamento = COALESCE(p_forma, 'manual')
   WHERE id = p_id AND cancelado = 0 AND pago_em IS NULL;
  SELECT ROW_COUNT() AS afetados;
END$$

DROP PROCEDURE IF EXISTS sp_estornar_lancamento$$
CREATE PROCEDURE sp_estornar_lancamento(IN p_id INT)
BEGIN
  UPDATE lancamentos SET pago_em = NULL, forma_pagamento = NULL WHERE id = p_id;
END$$

DROP PROCEDURE IF EXISTS sp_encerrar_recorrencia$$
CREATE PROCEDURE sp_encerrar_recorrencia(IN p_id INT)
BEGIN
  UPDATE recorrencias SET ativo = 0 WHERE id = p_id;
  -- ocorrências futuras ainda não pagas deixam de valer; as pagas/vencidas ficam
  UPDATE lancamentos SET cancelado = 1
   WHERE id_recorrencia = p_id AND pago_em IS NULL AND vencimento > CURDATE();
END$$

DELIMITER ;
