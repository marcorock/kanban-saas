# Contexto do projeto — Kanban SaaS

## Referências e regras

- Regras permanentes de execução e aprovação: [AGENTS.md](AGENTS.md).
- Requisitos completos preservados: [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md).
- Histórico dos commits aprovados: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).
- Aprovação de incremento autoriza seu commit e início automático do próximo. Pedidos explícitos para não seguir/parar/interromper suspendem o avanço; proibição de commit também deve ser respeitada.
- Mudanças atuais ainda não aprovadas não entram no commit anterior. Não inferir autorização para push, merge, deploy em produção ou operações destrutivas.

## Arquitetura e ambiente

- PHP ^8.3 (mesmo requisito de produção), Composer/PSR-4, Pecee 5, Twig 3 e Bootstrap 5.3.
- Vanilla JS/Fetch e SortableJS local 1.15.7; sem SPA ou etapa de build.
- MariaDB 11+, PDO nativo, UTC e transações; banco compartilhado com isolamento por tenant e projeto.
- Um tenant pessoal por cadastro. Participar do tenant não concede acesso automático aos projetos.
- Controllers, Services, Repository de projetos e Policy; ActivityLogger compartilhado grava eventos dentro das transações.
- DocumentRoot public/; configuração em config/, bootstrap em bootstrap/, templates em resources/views/.
- Ambiente local usa containers Coruja; PHP não está no PATH do host. Executar PHP no container web em /var/www/projects/kanbansaas.
- Banco local configurado no .env (não versionado). Migrations 001–007 aplicadas; nenhum teste com fixtures foi executado no banco de uso diário.
- Mailpit local: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; interface http://mail.localhost. Produção segue configurável por MAIL_*.
- Branch de trabalho atual: Authentication. Não mudar/mesclar branches automaticamente.

## Incrementos aprovados e commitados

- e6b15d7 — estrutura PHP, roteamento, Twig, PDO e migrations iniciais.
- a6ccc86 — cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit.
- eaa70ff — confirmação de e-mail e recuperação de senha por SMTP; tokens SHA-256 de uso único e revogação de sessões.
- 0cb3560 — projetos: CRUD, arquivar/restaurar, exclusão lógica, paginação, policies e auditoria.
- c26cb49 — convites, participantes, papéis, transferência de propriedade e regra permanente de continuidade.
- b39e005 — quadro Kanban, colunas/cards, drag and drop, conclusão e controle de concorrência.

## Incremento atual — detalhes de tarefas (Fase 4)

- Kanban aprovado e commitado em b39e005. Detalhes de tarefas implementados/validados e em revisão, ainda sem commit.
- Migration 007: descrição, prioridade e datas de tarefas; task_assignees, task_checklists, task_checklist_items e task_comments com FKs compostas.
- Painel Bootstrap Offcanvas local: edição, múltiplos responsáveis, checklists/subtarefas simples, comentários e histórico paginado.
- Responsáveis devem ser participantes verificados do projeto; remoção de membro limpa atribuições com auditoria e invalidação de versões abertas.
- Comentários: autor edita; autor/proprietário/admin exclui logicamente; visualizador e projeto arquivado têm somente leitura.
- Datas previstas são DATE; início real e conclusão são UTC. Conclusão continua vinculada à coluna is_done.
- TaskService opera dentro da transação e controle de versão do BoardService; leitura de quadro e detalhes é consistente.
- Filtros locais de responsável, prioridade, coluna e prazo; arrastar fica indisponível durante filtros para preservar ordenação completa.
- Regressão PHP aprovada, incluindo autorização, IDOR, datas, atribuições, rollback, paginação e remoção de membro.
- Chromium aprovado: Kanban, painel, descrição literal segura, responsáveis, datas, checklists, comentários, rascunhos em erro, filtros e mobile.
- Testes executados apenas em PHP 8.3.32/MariaDB 11.8.8/Mailpit descartáveis; ferramentas de navegador ficam em container de teste.

## Próximo incremento após aprovação

- Fazer commit dos detalhes de tarefas aprovados e iniciar Fase 5: dashboard, Minhas Tarefas e históricos de projeto/usuário com filtros.
- Depois: validação final de segurança/desempenho e preparação para produção.
- Pendentes adicionais: edição de perfil e gerenciamento de organizações; sem cobrança/pagamentos no MVP.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
