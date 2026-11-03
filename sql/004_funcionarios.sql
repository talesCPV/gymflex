-- GYMFLEX - Módulo Funcionários
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
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
