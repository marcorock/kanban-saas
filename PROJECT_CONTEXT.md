# Contexto operacional — Kanban SaaS

> Arquivo de retomada breve. Histórico detalhado e decisões antigas permanecem no `docs/PROJECT_CONTEXT_ARCHIVE.md` (copiar o PROJECT_CONTEXT.md original para lá antes de substituir este arquivo), no Git e em `docs/DEVELOPMENT.md`. Leia documentos detalhados somente quando relacionados à tarefa.

## Estado de continuidade

- **Base atual:** branch `syncronization`, commit inicial `90e7f59`. Incremento anterior aprovado; não reaberto. Árvore limpa no início deste trabalho.
- **Trabalho atual (05/11/2026):** incremento 4 aprovado pelo usuário e autorizado para commit, sincronização automática do Dashboard via FluxoSync: versão/fragmentos Twig, indicadores, prazos, progresso e atividades, com paginação e fallback sem JS preservados. Sem dependências, migrations ou mudanças em Kanban/Post-its/notificações.
- **Validação:** testes direcionados HTTP/PHP e Chromium com dois usuários aprovados no banco descartável `kanban_dashboard_sync_20261105`; isolamento, revogação, paginação, XSS, foco, offline/retorno, consulta condicional, ausência de reload, sem JS e axe 320/1440px. Sintaxe PHP/JS e `git diff --check` aprovados. Suítes anteriores não repetidas.
- **Próximo passo:** fechamento do incremento aprovado em commit; aguardar solicitação para novo trabalho. Sem push, merge ou deploy autorizados. Os quatro incrementos de sincronização planejados estão concluídos.
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