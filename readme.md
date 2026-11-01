
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