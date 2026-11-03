
# GYNFLEX

## SISTEMA DE GERENCIAMENTO DE ACADEMIA

O GYMFLEX tem o objetivo de ser um sistema completo de gerenciamento de academia, desde o cadstro de alunos, acompanhamento de treinos, gerencimento financeiro, cadastro de funcionários, controle de acesso, cartão de ponto, etc. Cada módulo ganhará um capítulo aqui neste documento explicando certinho como será seu funcionamento, proposta de UI desejada e mais ou menos quais tabelas do banco ele deve manipular.
O sistema inteiro será desenvolvido em HTML5, CSS, JS na parte do frontend, PHP no backend e um banco em MySQL.
A identidade visual do sistema quero que acompanhe o estilo do Retrocompiler, onde o fundo é predominantemente preto ou cinza chumpo, linhas, contornos botões e fontes destacadas em NEON. utilização de cards e modais de configuração.

## Módulo Usuários

O primeiro módulo a se criar será o de cadastro de usuários, que deve ter a seguinte premissa:
1- Só se tem acesso externamente ao cadastro de usuários por convite enviado ou cadastro feito diretamente dentro do sistema por usuários com tais previlégios
2- Teremos 2 tipos de usuários:
* Master - Acesso total a todos os módulos
* Perfis criados - O usuário Master tem acesso de criar tipos de perfis e definir quais módulos estes perfis terão acesso, ex: Alunos, professores, secretaria, contador, etc...
3- Campos previstos para tabela de usuario: id, Nome, email, celular, hash da senha (concatenar, email+senha), perfil de acesso

## Módulo Alunos

 Alguns campos podem estar em tabelas auxiliares (pai filho), a depender da necessidade.

CRUD de alunos: id, Nome, id_usuario, endereço, telefone, matrícula, contrato, data de cadastro, valor combinado, data de vencimento, ...

## Módulo Funcionários

 CRUD de cadastro de funcionários (professores, secretaria, atendente, etc... )

## Módulo Treinos

 Cadastro de treino montado por um professor e lincado a um aluno, o aluno, através de seu usuário, passará a ter acesso apenas ao seu treino, um professor terá acesso a todos os treinos, inclusive podendo criar novos treinos e ligar eles aos alunos. Cada treino deve ter um campo nome e um descrição, um professor pode criar quantos treinos ele achar necessário e direcionando eles a seus alunos conforme eles forem evoluindo

## Módulo Financeiro

Este módulo deve ter o controle de todas contas a pagar e faturas a receber, seja locação de espaço ou mensalidade de alunos, ainda estou estudando algum getway de pagamento para podermos integrar com o sistema. A tab A Pagar deve conter tudos os custos, alugél, agua, luz, internet, salários, empréstimos a pagar, Prolabores, manutenção de equipamentos, etc. No final com todos os dados de entrada e saída devemos ter uma visão geral da saúde financeira do negócio.


### CONCLUSÃO

Acho que isso é o suficiente para começarmos, este documento é vivo, podendo ser alterado por mim ou diretamente por você, conforme formos descidindo  novas diretivas para o sistema.

---

## Atualizar o código (`clone.sh`)

Clone o repositório manualmente na primeira vez. Depois, para trazer as novidades do GitHub:

```bash
./clone.sh [branch]      # padrão: main
```

O script fica na raiz do projeto e atualiza sempre o repositório em que ele está, de qualquer pasta que você o execute. Regras:

- **Seu banco:** nunca é alterado, e o `api/config.local.php` é guardado antes e devolvido no final, mesmo se algo falhar. Configure o banco **só** nesse arquivo; assim você nunca precisa editar arquivos do repositório.
- **Suas edições em arquivos versionados não impedem a atualização e não exigem commit:** o script as guarda de lado (`git stash`), atualiza e as reaplica. Se a atualização mexeu nas mesmas linhas, o arquivo fica na versão nova e as suas edições continuam guardadas (`git stash list`, `git stash show -p`, `git stash pop`); nada é perdido.
- **Commits locais que divergem do GitHub:** o script para e devolve tudo como estava.
- Se `sql/` mudar, ele avisa para você reexecutar `sql/schema.sql` (seguro de repetir).
- Copiado para fora de um repositório, ele clona em `./gymflex`.

## Como rodar (desenvolvimento)

Requisitos: PHP 8.1+ com `pdo_mysql` e MySQL 8 (ou MariaDB 10.5+). O `sql/schema.sql` foi testado em MySQL 8.0 e MariaDB 10.11.

> **Já criou o banco com os arquivos antigos (`sql/001`–`006`)?** O `schema.sql` não altera tabelas que já existem e dará erros como `Unknown column 'l.forma_pagamento'`. Para manter seus dados, execute **antes** o `sql/upgrade_migracoes_antigas.sql` (adiciona só o que falta, pode repetir, não faz nada em banco novo) e depois o `schema.sql`. Se o banco é só de teste, também pode apagar as tabelas e recomeçar.

1. Crie o banco vazio, com o nome que preferir:
   ```sql
   CREATE DATABASE gymflex CHARACTER SET utf8mb4;
   ```
2. Crie tabelas, views e procedures (um único arquivo, pode ser executado de novo sem perder dados):
   ```bash
   mysql -u SEU_USUARIO -p gymflex < sql/schema.sql
   ```
3. Configure o acesso ao banco: copie `api/config.example.php` para `api/config.local.php` e ajuste `host`, `port`, `db`, `user` e `pass` (o arquivo não vai para o git).
4. Crie o usuário Master inicial, de um destes jeitos:
   - **SQL** (funciona no Workbench): execute `sql/seed_master.sql`. Login: `master@gymflex.local` / `Trocar@123`. **Troque a senha no primeiro acesso** (Usuários > Editar). Dá para gerar o seu próprio hash com `php -r 'echo password_hash("SUA_SENHA", PASSWORD_DEFAULT);'` e colar no arquivo antes de executar.
   - **Linha de comando:**
     ```bash
     php api/setup.php "Seu Nome" email@exemplo.com suasenha123
     ```
5. Suba o servidor e acesse `http://localhost:8000`:
   ```bash
   php -S localhost:8000
   ```

**Decisão técnica:** a senha usa `password_hash()` do PHP (bcrypt/argon2, com salt próprio) em vez de `email+senha`, por ser mais seguro e não exigir rehash ao trocar o email.

## Estrutura

- `sql/seed_master.sql`: cria o primeiro usuário Master.
- `sql/upgrade_migracoes_antigas.sql`: atualiza bancos criados com as migrações antigas, sem perder dados.
- `sql/schema.sql`: tabelas, views (`v_lancamentos`, `v_resumo_mensal`) e procedures (`sp_baixar_lancamento`, `sp_estornar_lancamento`, `sp_encerrar_recorrencia`).
- `api/`: endpoints PHP (um por módulo) e `lib.php` (sessão, permissões, dia útil).
- `api/integracao/`: base para bancos/gateways (ver abaixo).
- `js/`, `css/`, `*.html`: frontend.

## Integração bancária / gateway (preparada, desligada)

Nenhum banco foi escolhido ainda; a baixa é manual. A estrutura para plugar um provedor já existe e é a mesma para qualquer banco ou gateway:

- **Tabelas:** `cobrancas` (Pix/boleto/cartão emitidos para um lançamento), `integracao_eventos` (log de tudo que chega nos webhooks) e `extrato_bancario` (para conciliação futura).
- **Contrato:** `api/integracao/ProvedorPagamento.php` define o que um provedor precisa fazer (criar/cancelar/consultar cobrança e validar+interpretar webhooks).
- **Fluxo pronto:** `api/cobrancas.php` cria a cobrança; o banco avisa em `api/webhook.php?provedor=NOME` (endpoint público, autenticidade validada pelo provedor); o evento é registrado e o lançamento recebe baixa pela mesma procedure `sp_baixar_lancamento` usada na baixa manual. Webhooks repetidos não duplicam a baixa.
- **Provedor `simulado`:** serve para testar o fluxo sem banco. Em `api/config.local.php` use `'integracao' => ['provedor' => 'simulado', 'webhook_segredo' => '...']` e simule o pagamento com:
  ```bash
  curl -X POST "http://localhost:8000/api/webhook.php?provedor=simulado" \
       -H "X-Webhook-Segredo: SEU_SEGREDO" -d '{"id_externo":"ID_DA_COBRANCA","status":"paga"}'
  ```
- **Para ligar um banco real:** criar `api/integracao/NomeDoBanco.php` implementando a interface, registrar um `case` em `Provedores::ativo()` e colocar as credenciais em `config.local.php`. Ainda não há botão "Cobrar" na tela do Financeiro; ele entra junto com o primeiro provedor real.

## Controle de acesso (entrada dos alunos)

Módulo **Acesso**: a recepção digita (ou lê com um leitor de código de barras) a **matrícula** do aluno, ou parte do **nome**, e o sistema responde na hora **LIBERADO** ou **BLOQUEADO**, registrando a tentativa.

- **Libera** se o aluno está ativo e sem mensalidade vencida além da carência. **Bloqueia** se estiver inativo ou com mensalidade vencida há mais dias que a carência.
- **Carência:** 5 dias por padrão. Em `api/config.local.php`: `'acesso' => ['carencia_dias' => 5, 'bloquear_inadimplente' => true]`. Com `bloquear_inadimplente` em `false` o sistema só avisa e não bloqueia.
- **Liberação manual:** num bloqueio, "Liberar mesmo assim" exige um motivo; a entrada fica registrada como manual, com quem liberou e a justificativa.
- **Nome com vários resultados:** o sistema mostra as opções para escolher. Leituras repetidas do mesmo aluno em 2 minutos não geram registro duplicado.
- **Painel do dia:** entradas liberadas, alunos diferentes, bloqueios e inadimplentes bloqueados, mais o histórico do dia escolhido e a lista de inadimplentes.
- **Permissão:** o módulo "Acesso" é liberado por perfil (Usuários > Perfis); um perfil de recepção pode ter só ele.
- **Banco:** tabela `acessos` e view `v_alunos_inadimplentes` (no `schema.sql`; em banco existente basta executá-lo de novo). A coluna `origem` já prevê outras fontes, como uma catraca, que ainda **não** está integrada.

## Cartão de ponto (funcionários)

Módulo **Ponto**, para controle interno da jornada. **Não substitui** um REP/sistema de ponto homologado exigido pela legislação trabalhista (Portaria MTP 671/2021); confirme com o contador antes de usá-lo como registro oficial.

- **Bater o ponto:** o funcionário entra com o próprio login e toca em "Registrar entrada/saída". A hora é **sempre a do servidor**, nunca a do aparelho. É preciso que o usuário esteja vinculado ao funcionário (Funcionários > Usuário de acesso). Toques repetidos em menos de 60 segundos são ignorados.
- **Entrada ou saída:** deduzido pela ordem no dia (1ª marcação = entrada, 2ª = saída, 3ª = entrada...). Isso permite o intervalo de almoço e continua correto depois de uma correção.
- **Espelho do mês:** marcações por dia, horas trabalhadas, esperado e **saldo até ontem**. A jornada padrão é 8h por dia útil (`'ponto' => ['jornada_minutos' => 480]` em `api/config.local.php`), de segunda a sexta, exceto feriados nacionais; pode ser alterada por funcionário. Dia sem marcação conta como falta; dia com número ímpar de marcações aparece como "saída não registrada".
- **Dois módulos, liberados por perfil:** `Ponto` (bater o próprio ponto e ver o próprio espelho) e `Gestão de ponto` (ver quem está dentro, ver o espelho de qualquer funcionário, lançar marcação manual, cancelar marcação, definir jornada).
- **Correções com rastro:** a marcação manual exige justificativa e fica marcada com `*`. Marcações não são apagadas: o cancelamento exige motivo e a marcação continua visível, riscada.
- **Ainda não tratado:** abonos, férias, atestados, horas extras com adicional, banco de horas com vencimento e jornadas com turno que vira a meia-noite.
- **Banco:** tabelas `ponto_registros` e `ponto_jornadas` (no `schema.sql`; em banco existente basta executá-lo de novo).

## Regras do Financeiro

- **Geração automática:** ao abrir o Financeiro, entram sozinhos (mês atual e próximo) as mensalidades dos alunos ativos, os salários dos funcionários ativos e as contas recorrentes (a pagar e a receber). Nada duplica. Excluir uma ocorrência gerada apenas a cancela.
- **Recorrentes:** marque "Conta recorrente" ao criar um lançamento. Para parar, use "Recorrentes" → "Encerrar" (ocorrências futuras pendentes são canceladas).
- **Dia de pagamento do salário:** fica no cadastro do funcionário.
- **Dia útil:** todo vencimento gerado automaticamente cai no dia útil mais próximo (sábado → sexta, domingo → segunda; em empate, o seguinte). Consideram-se feriados nacionais, Carnaval, Sexta-feira Santa e Corpus Christi. Feriados estaduais e municipais **não** são considerados.
- **Pagamento:** baixa manual por enquanto. O gateway/integração bancária será definido depois.
