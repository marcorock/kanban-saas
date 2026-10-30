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
- Branch de trabalho atual: Design-system. Não mudar/mesclar branches automaticamente.

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
- 2f0fd5c — design system Fluxo Claro e telas de acesso.
- 0460acf — estrutura autenticada: header, menus responsivos e footer.
- 756af0e — dashboard Fluxo Claro.
- ace0d17 — listagem de projetos Fluxo Claro.
- 39f7edc — formulários de criação/edição de projetos.

## Módulos consolidados

- Autenticação, confirmação/reset SMTP e perfil/sessões: docs/AUTHENTICATION.md.
- Projetos, papéis, convites, transferência e auditoria: docs/PROJECTS.md e docs/MEMBERS.md.
- Kanban transacional com board_version, tarefas, responsáveis, checklists e comentários: docs/KANBAN.md e docs/TASKS.md.
- Dashboard, Minhas Tarefas e históricos com escopo de tenant/projeto: docs/REPORTS.md.
- Fase 6 aprovada: preflight somente leitura, segurança, regressão e roteiro operacional em docs/PRODUCTION.md e docs/VALIDATION.md.
- Fluxo Claro já aplicado a acesso, navegação, dashboard, listagem e formulários. Detalhes da evolução em docs/DESIGN_SYSTEM.md e docs/DEVELOPMENT.md.
- Homologação operacional adiada pelo usuário para trabalhar UI/UX em incrementos pequenos. Sem publicação autorizada. Organizações seguem expansão pendente; sem cobrança no MVP.

## Entrega anterior — UI/UX: listagem de projetos

- Quarto incremento aprovado; commit de fechamento: `ace0d17` — `feat: redesenha listagem de projetos`. Próximo incremento autorizado: formulários de criação/edição.
- Cards responsivos com nome, papel/vínculo, prazo, descrição resumida e atalhos para quadro/detalhes. Filtros GET Ativos/Arquivados, vazios e paginação próprios.
- Twig e CSS separados, sem JS novo. Sem mudanças em queries/políticas: 25 itens por página, mesmos vínculos de tenant/projeto e ordenação. Formulários/detalhes ainda não migrados.
- Regressão PHP completa e preflight local aprovados. Chromium/axe da listagem aprovado em 320–1440px, com papéis, arquivados/viewer, paginação/filtro, teclado, XSS, vazios e sem JS; regressões de dashboard, perfil e shell também aprovadas.
- Fixtures exclusivamente em banco descartável; previews em storage/previews/projects-390.png e projects-1440.png, fora do Git, inspecionados visualmente. Sem teste em aparelhos físicos/leitores de tela.
- Nenhuma migration nova, mudança de .env ou publicação. Homologação operacional continua adiada.
- Próximo incremento após aprovação: formulários de criação/edição de projetos; detalhes em entrega posterior.

## Entrega anterior — UI/UX: formulários de projetos

- Quinto incremento aprovado; commit de fechamento: `39f7edc` — `feat: redesenha formulários de projetos`. Próximo incremento autorizado: detalhes do projeto.
- Criação/edição com informações e prazos em seções, labels/ajuda, campos opcionais explícitos, ações mobile e apoio contextual no desktop.
- Twig/CSS/JS externos separados. Contadores, limite de data de término, foco em erros e estado de envio são melhorias progressivas; POST funciona sem JS.
- Respostas 422 preservam dados; 419 de criação/edição agora reapresenta o formulário e CSRF atual, mantendo status e sem gravar dados. Regras/serviços/políticas não foram alterados.
- Regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px: criação/edição, datas, 422/419, preservação/foco, XSS, cancelamento, papéis e envio sem JS aprovados; regressões de listagem e dashboard aprovadas.
- Previews projects-new-390.png/projects-new-1440.png e projects-edit-* em storage/previews, ignorados no Git e inspecionados visualmente. Sem teste em aparelhos físicos/leitores de tela, migration nova ou alteração de .env. Fixtures apenas em banco descartável.
- Próximo incremento após aprovação: detalhes do projeto. Homologação operacional permanece adiada.

## Última entrega aprovada — UI/UX: detalhes do projeto

- Sexto incremento aprovado; commit de fechamento: `feat: redesenha detalhes do projeto`. Próximo incremento autorizado: participantes e convites.
- Quadro em destaque, papel/estado, descrição completa, planejamento com UTC e navegação de projeto. Gestão separada e visível apenas ao proprietário.
- Disclosure nativo de exclusão mantém checkbox obrigatório e POST/CSRF; arquivar/restaurar e links funcionam sem JS novo. Twig/CSS separados; descrição sem style inline.
- Consultas, autorização, serviços, transações e auditoria preservados. Nenhuma migration nova ou alteração do .env; testes só em banco descartável.
- Regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px: quatro papéis, consulta, atalhos, arquivar/restaurar/excluir, confirmação/CSRF, sem JS e XSS aprovados; regressões de formulários e relatórios aprovadas.
- Previews project-details-390.png/project-details-1440.png e archived-* em storage/previews, ignorados no Git e inspecionados visualmente. Sem teste em dispositivos físicos/leitores de tela. Próximo incremento após aprovação: participantes e convites.
- Homologação operacional permanece adiada; sem publicação.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
- Preparação para produção: docs/PRODUCTION.md. Registro de validação: docs/VALIDATION.md.
- UI/UX, componentes e navegação: docs/DESIGN_SYSTEM.md.
