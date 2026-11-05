# Contexto operacional — Kanban SaaS

> Arquivo de retomada breve. Histórico detalhado e decisões antigas permanecem no `docs/PROJECT_CONTEXT_ARCHIVE.md` (copiar o PROJECT_CONTEXT.md original para lá antes de substituir este arquivo), no Git e em `docs/DEVELOPMENT.md`. Leia documentos detalhados somente quando relacionados à tarefa.

## Estado de continuidade

- **Último incremento aprovado (05/11/2026):** incremento 3, sincronização automática do painel de tarefas e do contador do sino, na branch `syncronization`, base `4ce0e56`. Fechamento autorizado; hash do commit disponível no Git.
- **Trabalho atual:** incremento concluído e documentado; pausa solicitada após o commit. Sem migrations ou alterações em dados/credenciais locais.
- **Validação:** regressão PHP completa de fechamento aprovada em MariaDB/Mailpit descartáveis, schema `kanban_task_sync_20261105` (001–015). Suítes retomadas após iniciar o servidor HTTP descartável e isolar o rate limit acumulado entre testes; nenhum ajuste de código necessário. Evidência: `/tmp/kanbansaas-increment3-close.log`. Testes Chromium/axe 320–1440px e regressões afetadas já aprovados, sem repetição; `git diff --check` aprovado.
- **Próximo passo:** aguardar instruções. Sincronização do Dashboard não iniciada nem autorizada neste fechamento.
- **Pendências funcionais conhecidas:** audiência segura para a visibilidade "Público" dos Post-its ainda indefinida; validação operacional real de Web Push em dispositivo; homologação operacional de produção adiada.
- **Limitação atual:** token do painel de tarefa é do projeto; mudanças em outras tarefas podem provocar leitura dos detalhes abertos. A lista completa da central de notificações não é sincronizada nesse incremento.

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