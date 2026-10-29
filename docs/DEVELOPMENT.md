# Desenvolvimento e continuidade

## Aprovação de incrementos

As regras permanentes estão em [AGENTS.md](../AGENTS.md). A aprovação do usuário autoriza o commit do incremento concluído e o início automático do próximo planejado. Pedidos explícitos para não seguir, parar ou interromper suspendem esse avanço. Não incluir mudanças ainda não aprovadas no commit anterior.

Ao concluir um incremento, registrar implementação, testes, migrations aplicadas localmente, limitações e próximo passo no PROJECT_CONTEXT.md e na documentação afetada. Não fazer push, merge ou deploy em produção por inferência dessa aprovação.

## Histórico consolidado

| Commit | Entrega aprovada |
|---|---|
| e6b15d7 | Estrutura PHP 8.3, roteamento, Twig, PDO e migrations iniciais |
| a6ccc86 | Cadastro, login/logout, sessões, CSRF e rate limit |
| eaa70ff | Verificação de e-mail, recuperação de senha e SMTP/Mailpit |
| 0cb3560 | Projetos, isolamento, permissões e auditoria |
| c26cb49 | Convites, participantes, papéis, transferência e regra de continuidade |
| b39e005 | Kanban com colunas/cards, drag and drop e controle de versão |
| 7d170fe | Detalhes das tarefas, responsáveis, datas, checklists, comentários e filtros |
| 75927a9 | Dashboard, Minhas Tarefas e históricos consolidados |

Os requisitos completos originais foram preservados em [REQUIREMENTS.md](REQUIREMENTS.md); PROJECT_CONTEXT.md mantém somente estado conciso, etapa corrente e próximo incremento. `git log` é a fonte dos commits completos; CHANGELOG registra a evolução funcional.

## Pausa em 29/10/2026

Dashboard, Minhas Tarefas e históricos aprovados e registrados no commit `feat: adiciona dashboard e históricos consolidados` (consultar Git para o hash). Regressão PHP e Chromium aprovadas; migrations 001–008 aplicadas ao ambiente local. Desenvolvimento pausado a pedido do usuário, sem iniciar perfil/configurações pessoais, que será o próximo incremento na retomada.

## Retomada em 29/10/2026

Usuário solicitou continuar com perfil e configurações pessoais. Perfil implementado em `/settings`, com edição do nome, troca de senha e revogação das demais sessões, sem nova migration. Teste de perfil em banco descartável aprovado, incluindo rollback e concorrência. Regressão PHP dos oito módulos anteriores e migrations idempotentes aprovadas; sem teste visual em navegador neste incremento. Aprovado pelo usuário e incluído no commit `feat: adiciona perfil e configurações pessoais`; próximo incremento: revisão de segurança/desempenho e preparação para produção (Fase 6).
