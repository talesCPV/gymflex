-- =====================================================================
-- GYMFLEX - Atualiza um banco criado com as MIGRAÇÕES ANTIGAS (sql/001 a 006)
-- para a estrutura atual, SEM apagar dados.
--
-- Quando usar: se ao rodar o schema.sql aparecerem erros como
--   "Unknown column 'l.forma_pagamento'" ou "Unknown column 'cancelado'"
-- (o schema.sql não altera tabelas que já existem).
--
-- Ordem:  1) este arquivo   2) schema.sql   (3) seed_master.sql, se ainda não tiver Master)
-- Seguro para repetir: só adiciona o que estiver faltando. Em banco novo não faz nada.
-- Funciona em MySQL 8 e MariaDB. Execute com o banco do GymFlex selecionado.
-- =====================================================================

SET @db = DATABASE();

-- funcionarios: dia de pagamento do salário
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'funcionarios') = 1
              AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'funcionarios' AND column_name = 'dia_pagamento') = 0,
              'ALTER TABLE funcionarios ADD COLUMN dia_pagamento TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER salario', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- lancamentos: forma de pagamento (manual, pix, boleto...)
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'lancamentos') = 1
              AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'lancamentos' AND column_name = 'forma_pagamento') = 0,
              'ALTER TABLE lancamentos ADD COLUMN forma_pagamento VARCHAR(20) NULL AFTER pago_em', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- lancamentos: ligação com a conta recorrente
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'lancamentos') = 1
              AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'lancamentos' AND column_name = 'id_recorrencia') = 0,
              'ALTER TABLE lancamentos ADD COLUMN id_recorrencia INT NULL', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- lancamentos: cancelado (gerados automaticamente são cancelados, não apagados)
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'lancamentos') = 1
              AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'lancamentos' AND column_name = 'cancelado') = 0,
              'ALTER TABLE lancamentos ADD COLUMN cancelado TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- lancamentos: impede ocorrência duplicada de conta recorrente
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'lancamentos') = 1
              AND (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @db AND table_name = 'lancamentos' AND index_name = 'uq_rec_comp') = 0,
              'ALTER TABLE lancamentos ADD UNIQUE KEY uq_rec_comp (id_recorrencia, competencia)', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- lancamentos: chave estrangeira para recorrencias
SET @sql = IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'lancamentos') = 1
              AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'recorrencias') = 1 AND (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema = @db AND table_name = 'lancamentos' AND column_name = 'id_recorrencia' AND referenced_table_name = 'recorrencias') = 0,
              'ALTER TABLE lancamentos ADD CONSTRAINT fk_lancamentos_recorrencia FOREIGN KEY (id_recorrencia) REFERENCES recorrencias(id) ON DELETE SET NULL', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'Atualização concluída. Agora execute o schema.sql.' AS resultado;
