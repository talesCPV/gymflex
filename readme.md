
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

## Baixar e atualizar o código (`clone.sh`)

```bash
./clone.sh [branch] [pasta]     # padrão: main e ./gymflex
```

Na primeira vez clona o repositório; dentro de um repositório já clonado, atualiza (`git fetch` + avanço rápido). Nunca altera o banco nem o seu `api/config.local.php`: o arquivo é guardado antes e devolvido no final, mesmo se algo falhar. Se houver alterações suas em arquivos versionados, o script para sem mexer em nada. Se `sql/` mudar, ele avisa para você reexecutar `sql/schema.sql` (seguro de repetir).

## Como rodar (desenvolvimento)

Requisitos: PHP 8.1+ com `pdo_mysql` e MySQL 8 (ou MariaDB 10.5+).

1. Crie o banco vazio, com o nome que preferir:
   ```sql
   CREATE DATABASE gymflex CHARACTER SET utf8mb4;
   ```
2. Crie tabelas, views e procedures (um único arquivo, pode ser executado de novo sem perder dados):
   ```bash
   mysql -u SEU_USUARIO -p gymflex < sql/schema.sql
   ```
3. Configure o acesso ao banco: copie `api/config.example.php` para `api/config.local.php` e ajuste `db`, `user` e `pass` (o arquivo não vai para o git).
4. Crie o usuário Master inicial:
   ```bash
   php api/setup.php "Seu Nome" email@exemplo.com suasenha123
   ```
5. Suba o servidor e acesse `http://localhost:8000`:
   ```bash
   php -S localhost:8000
   ```

**Decisão técnica:** a senha usa `password_hash()` do PHP (bcrypt/argon2, com salt próprio) em vez de `email+senha`, por ser mais seguro e não exigir rehash ao trocar o email.

## Estrutura

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

## Regras do Financeiro

- **Geração automática:** ao abrir o Financeiro, entram sozinhos (mês atual e próximo) as mensalidades dos alunos ativos, os salários dos funcionários ativos e as contas recorrentes (a pagar e a receber). Nada duplica. Excluir uma ocorrência gerada apenas a cancela.
- **Recorrentes:** marque "Conta recorrente" ao criar um lançamento. Para parar, use "Recorrentes" → "Encerrar" (ocorrências futuras pendentes são canceladas).
- **Dia de pagamento do salário:** fica no cadastro do funcionário.
- **Dia útil:** todo vencimento gerado automaticamente cai no dia útil mais próximo (sábado → sexta, domingo → segunda; em empate, o seguinte). Consideram-se feriados nacionais, Carnaval, Sexta-feira Santa e Corpus Christi. Feriados estaduais e municipais **não** são considerados.
- **Pagamento:** baixa manual por enquanto. O gateway/integração bancária será definido depois.
