-- GYMFLEX - Módulo Financeiro
CREATE TABLE IF NOT EXISTS lancamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('receber','pagar') NOT NULL,
  categoria VARCHAR(60) NOT NULL,          -- Mensalidade, Aluguel, Luz, Salário...
  descricao VARCHAR(160) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  vencimento DATE NOT NULL,
  pago_em DATE NULL,                       -- NULL = pendente
  id_aluno INT NULL,                       -- mensalidade gerada
  id_funcionario INT NULL,                 -- salário gerado
  competencia CHAR(7) NULL,                -- AAAA-MM (só em lançamentos gerados)
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (vencimento),
  UNIQUE KEY uq_aluno_comp (id_aluno, competencia),          -- evita mensalidade duplicada
  UNIQUE KEY uq_func_comp (id_funcionario, competencia),     -- evita salário duplicado
  FOREIGN KEY (id_aluno) REFERENCES alunos(id) ON DELETE SET NULL,
  FOREIGN KEY (id_funcionario) REFERENCES funcionarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
