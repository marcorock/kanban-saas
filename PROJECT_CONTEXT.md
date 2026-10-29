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
- Branch de trabalho atual: perfil-config. Não mudar/mesclar branches automaticamente.

## Incrementos aprovados e commitados

- e6b15d7 — estrutura PHP, roteamento, Twig, PDO e migrations iniciais.
- a6ccc86 — cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit.
- eaa70ff — confirmação de e-mail e recuperação de senha por SMTP; tokens SHA-256 de uso único e revogação de sessões.
- 0cb3560 — projetos: CRUD, arquivar/restaurar, exclusão lógica, paginação, policies e auditoria.
- c26cb49 — convites, participantes, papéis, transferência de propriedade e regra permanente de continuidade.
- b39e005 — quadro Kanban, colunas/cards, drag and drop, conclusão e controle de concorrência.
- 7d170fe — detalhes das tarefas, responsáveis, prioridades/datas, checklists, comentários e filtros.
- 75927a9 — dashboard, Minhas Tarefas e históricos consolidados.
- aba013c — perfil, troca de senha e encerramento das demais sessões.
- 58c0e2a — validação de segurança/desempenho e preparação para instalação.

## Entrega anterior — dashboard e históricos (Fase 5)

- Dashboard, Minhas Tarefas e históricos aprovados pelo usuário; incluídos no commit `75927a9` — `feat: adiciona dashboard e históricos consolidados`.
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

## Entrega anterior — perfil e configurações pessoais

- Retomada solicitada em 29/10/2026. Incremento aprovado pelo usuário; commit de fechamento: `aba013c` — `feat: adiciona perfil e configurações pessoais`. Próxima etapa autorizada: Fase 6.
- `/settings`: edição do nome, e-mail confirmado para consulta, troca de senha e encerramento das demais sessões.
- Troca exige senha atual e confirmação; invalida tokens de recuperação, incrementa session_version e regenera a sessão atual/CSRF.
- ProfileService bloqueia o usuário e revalida a versão na transação; rollback preserva senha/tokens/versão. Identidade vem apenas da sessão.
- Formulários protegidos por CSRF, limites por usuário/ação e escape Twig. Sem nova migration; schema local 001–008 permanece suficiente.
- Teste descartável de perfil aprovado: autorização, validação, campos protegidos, XSS, senha, rollback, tokens, revogação e concorrência entre processos.
- Regressão PHP aprovada: sessões, autenticação, contas/SMTP, projetos, membros, Kanban, tarefas e relatórios; migrations idempotentes e sintaxe PHP verificadas. Teste visual original não executado; Chromium do perfil concluído e aprovado durante a Fase 6.
- Limitações: sem alteração de e-mail, avatar, tema/fuso ou listagem individual de dispositivos; conta não altera nome do tenant.
- Aprovação do perfil autorizou a Fase 6, implementada abaixo.
- Gerenciamento de organizações segue pendente de expansão; sem cobrança/pagamentos no MVP.

## Entrega anterior — Fase 6: validação e preparação para produção

- Incremento aprovado pelo usuário; commit de fechamento: `58c0e2a` — `chore: prepara validação e instalação em produção`. O commit do perfil não contém esta fase.
- Preflight `php bin/check.php [--production]`: runtime/extensões, configuração segura, assets, MariaDB/UTC e migrations; somente leitura e sem exposição de segredos.
- CSP, proteção de frames e Permissions-Policy em respostas dinâmicas, inclusive erros; HSTS na configuração de produção com APP_URL HTTPS.
- Composer explicita extensões; lock sincronizado sem alterar versões. Audit sem avisos conhecidos; validate mantém aviso preexistente de licença não definida.
- Quadro consulta apenas campos dos cards; descrições continuam no detalhe. Carga de 1.000 tarefas: 5 consultas e pico adicional de 1,64 MiB; amostra local, sem SLA.
- Regressão PHP, preflight com usuário SQL somente leitura, concorrência e Chromium (perfil, tarefas, relatórios e Kanban) aprovados. Runner sequencial em tests/run.php.
- Check local aprovado em leitura; nenhuma migration nova ou alteração no .env. Ambiente de teste descartável separado do banco de uso diário.
- Roteiro de instalação, backup, permissões, SMTP, HTTPS e reversão: docs/PRODUCTION.md. Evidências e limitações: docs/VALIDATION.md.
- Não houve deploy, teste do SMTP/HTTPS de produção ou ensaio de carga HTTP concorrente.
- Homologação operacional adiada por solicitação explícita do usuário. Após o commit, o usuário definiu UI/UX como prioridade, conforme incremento abaixo. Publicação requer autorização explícita.

## Última entrega aprovada — UI/UX: telas de acesso

- Solicitado pelo usuário: design system original, mobile first e pequenos incrementos; primeiro login, cadastro e recuperação/reset.
- Fluxo Claro: verde profundo, lima, superfícies claras e hierarquia tipográfica própria. APP_NAME permanece o nome do produto.
- Implementados header/footer de acesso, formulários, confirmação/reenvio/aviso de e-mail e estados de erro/sucesso/envio. Sem redesenho das telas internas ou da página inicial `/`.
- HTML em Twig; tokens/layout em CSS externo; comportamento em JS externo. Nenhum inline nos novos templates; separação registrada no AGENTS.md.
- Melhorias progressivas: mostrar senha, foco no resumo de erros, bloqueio de envio repetido; POST/CSRF e login funcionam sem JS.
- Testes PHP de autenticação, conta/SMTP, segurança e preflight aprovados. Chromium/axe: 320/390/768/1024/1440px, sem overflow, acessibilidade automatizada A/AA, fluxos de conta e fallback sem JS aprovados.
- Previews mobile/desktop em storage/previews (ignorados no Git), verificados visualmente; sem teste em dispositivos físicos/leitores de tela.
- Design system, menus internos planejados, componentes e limites: docs/DESIGN_SYSTEM.md. Preflight local aprovado com novos assets; nenhuma migration ou alteração no .env.
- Incremento aprovado pelo usuário; commit de fechamento: `feat: define design system e redesenha telas de acesso`. Próximo incremento autorizado: shell autenticado (header, menus responsivos e footer); adaptar módulos depois, um por vez.
- Homologação operacional permanece adiada; nenhuma publicação realizada.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
- Preparação para produção: docs/PRODUCTION.md. Registro de validação: docs/VALIDATION.md.
- UI/UX, componentes e navegação: docs/DESIGN_SYSTEM.md.
