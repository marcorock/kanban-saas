# Contexto operacional — Kanban SaaS

> Arquivo de retomada breve. Histórico detalhado e decisões antigas permanecem no `docs/PROJECT_CONTEXT_ARCHIVE.md` (copiar o PROJECT_CONTEXT.md original para lá antes de substituir este arquivo), no Git e em `docs/DEVELOPMENT.md`. Leia documentos detalhados somente quando relacionados à tarefa.

## Estado de continuidade

- **Workspaces (05/11/2026):** Etapa 1 — Modelo e permissões concluída, aprovada e commitada em `97a5a28`. Migration `016_workspace_model.sql`: papéis `admin/member` em `tenant_users`, padrão anulável em `users` com FK composta para o vínculo e tabela `workspace_invitations` (hash único, validade e estados). `WorkspaceRepository` exige vínculo do usuário e do proprietário; `WorkspacePolicy` separa poderes administrativos dos de projeto e deriva propriedade exclusivamente de `tenants.owner_id`. Cadastro existente permanece transacional; nenhuma interface, rota, aceite ou gerenciamento de etapas futuras foi adicionado. Plano em `x_brainstorm/IMPLEMENTACAO_WORKSPACE.md`.
- **Base atual:** branch `syncronization`, HEAD inicial `97a5a28`. Etapa 1 confirmada no Git; árvore limpa no início da Etapa 2.
- **Trabalho atual (05/11/2026):** incremento 4 aprovado pelo usuário e autorizado para commit, sincronização automática do Dashboard via FluxoSync: versão/fragmentos Twig, indicadores, prazos, progresso e atividades, com paginação e fallback sem JS preservados. Sem dependências, migrations ou mudanças em Kanban/Post-its/notificações.
- **Validação:** testes direcionados HTTP/PHP e Chromium com dois usuários aprovados no banco descartável `kanban_dashboard_sync_20261105`; isolamento, revogação, paginação, XSS, foco, offline/retorno, consulta condicional, ausência de reload, sem JS e axe 320/1440px. Sintaxe PHP/JS e `git diff --check` aprovados. Suítes anteriores não repetidas.
- **Validação da Etapa 1:** banco descartável `kanban_workspace_step1_20261105`, migrations 001–016 e reexecução sem alterações; `tests/workspaces-model.php`, `tests/auth.php` e `tests/projects.php` aprovados. Cobertos matriz administrativa, vínculo do proprietário, FK do padrão/concorrência com revogação, convites, projetos privados e rollback. Sintaxe PHP e `git diff --check` aprovados. Reset revisado, inventário automático compatível: registro atualizado via `--update-schema --registry=/tmp/workspace-step1-schema.json`, reset de 24 tabelas, IDs 1 e 16 migrations preservadas. Banco local e seu inventário não alterados; atualizar o inventário local quando a migration for aplicada nele. Sem regressão completa.
- **Etapa 2 — Contexto e autorização (05/11/2026):** concluída e aprovada pelo usuário; fechamento autorizado em commit Conventional Commits exclusivamente deste incremento. `WorkspaceContext` imutável vincula usuário/tenant, valida vínculo vigente e proprietário com vínculo, sem depender de sessão ou padrão. Rotas `/workspaces/{workspaceId}/projects/{id}` cobrem consulta/edição, participantes, histórico, Kanban e APIs de tarefas/versão/estado; contexto preservado nos formulários, links, redirecionamentos e endpoints do quadro. Serviços de projeto/participantes/Kanban propagam contexto até `ProjectRepository`; rotas existentes derivam contexto do projeto e mantêm autorização. Transações bloqueiam Workspace/vínculos antes do projeto; conflito MariaDB 1020 convertido em 409 com rollback. Sem migrations, dependências ou seletor.
- **Validação da Etapa 2:** banco descartável `kanban_workspace_step2_20261105`, migrations existentes 001–016. `tests/workspaces-context.php` cobre HTTP/PHP, usuário em dois Workspaces, contexto incorreto, vínculo/papel de projeto, CSRF, preferência independente, links/formulários/endpoints, revogação e bloqueio concorrente. Regressões direcionadas `tests/board.php`, `tests/members.php`, `tests/projects.php` e `tests/workspaces-model.php` aprovadas; sintaxe PHP/JS e `git diff --check` aprovados. Banco local preservado; sem regressão completa. Fechamento: diff conferido, sem mudanças de código após a aprovação e sem repetição dos testes aprovados.
- **Próxima etapa:** Etapa 3 — Isolamento dos módulos e sincronização, não iniciada; aguardar nova solicitação do usuário. Listagens globais, criação no contexto ativo, notas, Dashboard e descarte de respostas de outro Workspace continuam para a Etapa 3; notificações para a 4 e padrão/seletor para a 5. Não liberar alternância antes de isolar os módulos. Os quatro incrementos de sincronização anteriores estão concluídos; não reabrir. Sem push, merge ou deploy autorizados.
- **Limitações:** polling de 7s; foco em bloco alterado adia sua aplicação; alterações em qualquer tarefa de projeto acessível podem disparar leitura completa do Dashboard, ainda que os blocos visíveis não mudem. Saudação/banner mantêm comportamento anterior.
- **Pendências anteriores preservadas:** audiência segura para Post-its públicos; Web Push em dispositivo; homologação de produção. Token do painel de tarefa continua por projeto; lista completa da central de notificações não sincronizada.

## Arquitetura essencial

- PHP ^8.3, Composer/PSR-4, Pecee 5, Twig 3, Bootstrap 5.3; JavaScript vanilla/Fetch e SortableJS local; **sem SPA ou build**.
- MariaDB 11+, PDO, UTC, isolamento por tenant/projeto; segurança e concorrência são invariantes.
- DocumentRoot `public/`; configurações em `config/`, bootstrap em `bootstrap/`, views em `resources/views/`.
- PHP local roda no container web (não no PATH do host), pasta `/var/www/projects/kanbansaas`; dados/credenciais locais em `.env` não versionado.
- Banco local: migrations **001–015** segundo o último registro; conferir o estado real. Fixtures e regressões destrutivas apenas em banco descartável.
- `local-tools/` é local e ignorado pelo Git; manutenção de reset conforme `docs/TEST_DATABASE_RESET.md`.

## Mapa de referência sob demanda

- Arquitetura/schema: `docs/ARCHITECTURE.md`, `docs/DATABASE.md`.
- Segurança/contas: `docs/AUTHENTICATION.md`; projetos/membros: `docs/PROJECTS.md`, `docs/MEMBERS.md`.
- Kanban/tarefas: `docs/KANBAN.md`, `docs/TASKS.md`; notificações: `docs/NOTIFICATIONS.md`.
- UI/UX: `docs/DESIGN_SYSTEM.md`; relatórios: `docs/REPORTS.md`.
- Histórico e validações: `docs/DEVELOPMENT.md`, `docs/VALIDATION.md`, `docs/PROJECT_CONTEXT_ARCHIVE.md`.
- Reset local: `docs/TEST_DATABASE_RESET.md`.

## Regra de atualização

Manter este arquivo curto, registrando apenas **último aprovado, trabalho atual, validação, limitações e próximo passo**. Mover detalhes encerrados para a documentação específica ou histórico, sem duplicar relatórios volumosos aqui.