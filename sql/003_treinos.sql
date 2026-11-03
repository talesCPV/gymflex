-- GYMFLEX - Módulo Treinos
CREATE TABLE IF NOT EXISTS treinos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT NULL,
  id_criador INT NULL,                       -- professor que montou o treino
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_criador) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- um treino pode ser direcionado a vários alunos
CREATE TABLE IF NOT EXISTS treino_alunos (
  id_treino INT NOT NULL,
  id_aluno INT NOT NULL,
  PRIMARY KEY (id_treino, id_aluno),
  FOREIGN KEY (id_treino) REFERENCES treinos(id) ON DELETE CASCADE,
  FOREIGN KEY (id_aluno) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
