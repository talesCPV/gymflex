-- =====================================================================
-- GYMFLEX - Cria o primeiro usuário MASTER
-- Rode DEPOIS do schema.sql (que já cria o perfil "Master"):
--   mysql -u USUARIO -p NOME_DO_BANCO < sql/seed_master.sql
--
-- Login inicial:   email: master@gymflex.local    senha: Trocar@123
-- TROQUE A SENHA no primeiro acesso (Usuários > Editar) ou gere o seu próprio hash:
--   php -r 'echo password_hash("SUA_SENHA", PASSWORD_DEFAULT), "\n";'
-- e cole no lugar do hash abaixo (a senha nunca é guardada em texto puro).
-- Pode ser executado de novo: não cria duplicado.
-- =====================================================================
INSERT INTO usuarios (nome, email, senha_hash, id_perfil)
SELECT 'Administrador',
       'master@gymflex.local',
       '$2y$10$VUf.2VwD51lq6tifkfDV/OfQKvPlrS.eKJmc9E2Qw1tRVz0hs8mUC',
       (SELECT id FROM perfis WHERE master = 1 LIMIT 1)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'master@gymflex.local');
