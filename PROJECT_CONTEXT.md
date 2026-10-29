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
- Banco local configurado no .env (não versionado). Migrations 001–008 aplicadas; nenhum teste com fixtures foi executado no banco de uso diário.
- Mailpit local: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; interface http://mail.localhost. Produção segue configurável por MAIL_*.
- Branch de trabalho atual: Authentication. Não mudar/mesclar branches automaticamente.

## Incrementos aprovados e commitados

- e6b15d7 — estrutura PHP, roteamento, Twig, PDO e migrations iniciais.
- a6ccc86 — cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit.
- eaa70ff — confirmação de e-mail e recuperação de senha por SMTP; tokens SHA-256 de uso único e revogação de sessões.
- 0cb3560 — projetos: CRUD, arquivar/restaurar, exclusão lógica, paginação, policies e auditoria.
- c26cb49 — convites, participantes, papéis, transferência de propriedade e regra permanente de continuidade.
- b39e005 — quadro Kanban, colunas/cards, drag and drop, conclusão e controle de concorrência.
- 7d170fe — detalhes das tarefas, responsáveis, prioridades/datas, checklists, comentários e filtros.

## Última entrega aprovada — dashboard e históricos (Fase 5)

- Dashboard, Minhas Tarefas e históricos aprovados pelo usuário; incluídos no commit de fechamento desta pausa: `feat: adiciona dashboard e históricos consolidados`. Consultar `git log -1` para o hash ao retomar.
- Migration 008: índices de período de activity_logs por tenant/projeto e por autor.
- Dashboard: projetos ativos acessíveis, contagens de tarefas atribuídas em projetos ativos, progresso paginado, prazos próximos e atividades recentes.
- Minhas Tarefas: somente atribuições próprias, com filtros de projeto, prioridade, estado, prazo e arquivamento; 25 registros por página.
- Meu Histórico: ações próprias em projetos ainda acessíveis. Histórico do projeto: ações de todos os participantes; filtros de ação/período e snapshots legíveis.
- ReportingService aplica vínculos atuais de tenant e projeto em todas as consultas; ProjectRepository valida filtros de projeto.
- Detalhes/e-mails de convites nos históricos são restritos à administração atual, inclusive após alteração de papel.
- ActivityPresenter usa campos conhecidos, nomes em lote e saída escapada; relatórios não exibem JSON bruto.
- Navegação unificada e links de tarefas abrem o painel correto por query task, com autorização no backend.
- Regressão PHP aprovada: sessões, contas/SMTP, projetos, participantes, Kanban, tarefas e relatórios.
- Testes de relatórios aprovados: contagens/progresso, múltiplos tenants, acesso, privacidade, período UTC, XSS, paginação e revogação.
- Chromium aprovado: Kanban, navegação, métricas, filtros, snapshots seguros, links diretos e layout móvel.
- Testes somente em PHP 8.3.32/MariaDB 11.8.8/Mailpit descartáveis; ambiente local recebe apenas migrations verificadas.

## Pausa e retomada

- Desenvolvimento pausado por solicitação explícita do usuário em 29/10/2026. Não iniciar nova implementação até o usuário solicitar retomada.
- Incremento aprovado será commitado antes de encerrar; nenhum trabalho de perfil foi iniciado.
- Ao retomar: consultar este contexto e `git status`/`git log`; implementar perfil e configurações pessoais, pendência dos requisitos de conta.
- Depois: revisão final de segurança/desempenho, instalação e preparação para produção (Fase 6).
- Gerenciamento de organizações segue pendente de expansão; sem cobrança/pagamentos no MVP.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
