-- GYMFLEX - Módulo Alunos
CREATE TABLE IF NOT EXISTS alunos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  id_usuario INT NULL UNIQUE,              -- login do aluno (opcional)
  endereco VARCHAR(255) NULL,
  telefone VARCHAR(20) NULL,
  matricula VARCHAR(20) NULL UNIQUE,       -- gerada automaticamente: AAAA + id
  contrato VARCHAR(60) NULL,               -- ex.: Mensal, Trimestral, Anual
  data_cadastro DATE NOT NULL,
  valor_combinado DECIMAL(10,2) NOT NULL DEFAULT 0,
  dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,  -- dia do mês da mensalidade
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
