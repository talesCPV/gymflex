-- GYMFLEX - Módulo Usuários
CREATE TABLE IF NOT EXISTS perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL UNIQUE,
  master TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- módulos liberados para cada perfil (master ignora esta tabela)
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
  senha_hash VARCHAR(255) NOT NULL,
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
-- O primeiro Master é criado por: php api/setup.php "Nome" email senha
