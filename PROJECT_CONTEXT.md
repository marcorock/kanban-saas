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
- 47d88da — detalhes do projeto Fluxo Claro.
- b8f5fdb — participantes, convites e aceite Fluxo Claro.
- 01eb9ea — quadro Kanban Fluxo Claro.
- 46785c3 — painel de tarefas e checklist compacto.
- 61b86a4 — Minhas Tarefas Fluxo Claro.
- 4c5d3bc — históricos pessoal e de projeto Fluxo Claro.
- 06a0407 — perfil e configurações pessoais Fluxo Claro.

## Módulos consolidados

- Autenticação, confirmação/reset SMTP e perfil/sessões: docs/AUTHENTICATION.md.
- Projetos, papéis, convites, transferência e auditoria: docs/PROJECTS.md e docs/MEMBERS.md.
- Kanban transacional com board_version, tarefas, responsáveis, checklists e comentários: docs/KANBAN.md e docs/TASKS.md.
- Dashboard, Minhas Tarefas e históricos com escopo de tenant/projeto: docs/REPORTS.md.
- Fase 6 aprovada: preflight somente leitura, segurança, regressão e roteiro operacional em docs/PRODUCTION.md e docs/VALIDATION.md.
- Fluxo Claro já aplicado a acesso, navegação, dashboard, listagem, formulários, detalhes, equipe, quadro, painel, Minhas Tarefas e históricos. Detalhes da evolução em docs/DESIGN_SYSTEM.md e docs/DEVELOPMENT.md.
- Homologação operacional adiada pelo usuário para trabalhar UI/UX em incrementos pequenos. Sem publicação autorizada. Organizações seguem expansão pendente; sem cobrança no MVP.

## Continuidade recente

- Evolução dos incrementos anteriores e suas validações preservadas em docs/DEVELOPMENT.md, docs/DESIGN_SYSTEM.md e Git; os commits aprovados estão listados acima.
- Pausa após aprovação do Kanban (`01eb9ea`) e retomada em 30/10/2026. Painel revisado conforme preview aprovado, concluído/aprovado em `46785c3`.
- A aprovação de Minhas Tarefas autorizou seu commit (`61b86a4`) e início dos históricos. Homologação operacional permanece adiada.

## Entrega anterior — UI/UX: Minhas Tarefas

- Décimo incremento aprovado; commit de fechamento: `61b86a4` — `feat: redesenha Minhas Tarefas com Fluxo Claro`.
- Lista clara com prioridade, estado textual, prazo e projeto/coluna; links abrem o painel correto. Ícones de estado são informativos, sem checkbox que simule conclusão fora do quadro.
- Navegação GET Todas/Pendentes/Iniciadas/Concluídas mantém filtros e reinicia paginação. Filtros nativos recolhidos por padrão e abertos quando há critérios adicionais; total real dos resultados, vazios e aviso de arquivados.
- Twig e CSS externo próprios, sem JS novo. Formulários/links funcionam sem JS; detalhes da tarefa continuam dependendo de JS no quadro. Erros 422 recebem apresentação própria, mantendo status/validação.
- ReportingService, consultas, autorização, limites/ordem e schema preservados. Sem migration ou mudança do .env; homologação operacional adiada. Testes só em banco descartável.
- Regressão PHP completa, fixture própria e preflight local aprovados. Chromium/axe em 320–1440px aprovado para filtros/estados, paginação, XSS, atribuições, viewer, conta vazia, arquivados, links, 422/404 e uso sem JS; regressões de relatórios e dashboard aprovadas.
- Previews my-tasks-390.png/my-tasks-1440.png, filtros/vazio em storage/previews, fora do Git e inspecionados visualmente. Sem testes em aparelhos físicos/leitores de tela.
- Próximo incremento autorizado: UI/UX dos históricos pessoal e do projeto; configurações pessoais em entrega posterior.

## Entrega anterior — UI/UX: históricos pessoal e do projeto

- Décimo primeiro incremento aprovado; commit de fechamento: `4c5d3bc` — `feat: redesenha históricos com Fluxo Claro`.
- Linha do tempo com ação, autor, horário UTC e links de projeto/tarefa. Meu Histórico segue só ações próprias; histórico do projeto mostra ações da equipe.
- Filtros GET em disclosure nativo, preservação na paginação e totais reais. Antes/depois em comparações por campo, empilhadas no celular e lado a lado no desktop; textos longos sem rolagem horizontal.
- Twig/CSS externos, sem JS novo. Vazios, consulta de arquivados e erro 422 próprios. Retorno 422 mantém validação/status e limpeza volta ao mesmo projeto; restrições de convites permanecem no ActivityPresenter.
- ReportingService, autorização, consultas, ordenação e limites preservados. CSS de relatórios e paginação legados sem referências removidos. Sem migration, alteração do .env ou publicação.
- Regressão PHP completa, fixture de históricos, teste de relatórios e preflight local aprovados. Chromium/axe 320–1440px aprovado para pessoal/projeto, antes/depois, textos longos/XSS, filtros/paginação/UTC, autores, quatro papéis, privacidade, arquivados, links, 422/404 e uso sem JS; regressão de relatórios no navegador aprovada.
- Previews history-personal-390.png/history-project-390.png e versões 1440, history-changes-*.png/history-empty-390.png em storage/previews, fora do Git e inspecionados visualmente. Sem testes em aparelhos físicos/leitores de tela.
- Próximo incremento autorizado: UI/UX de perfil e configurações pessoais. Homologação operacional segue adiada; testes apenas em banco/Mailpit descartáveis.

## Entrega anterior — UI/UX: perfil e configurações pessoais

- Décimo segundo incremento aprovado; commit de fechamento: `06a0407` — `feat: redesenha perfil e configurações pessoais`.
- Perfil/segurança no Fluxo Claro: resumo da conta, e-mail somente leitura e nome; troca de senha/sessões em disclosures nativos, recolhidos por padrão e abertos no erro correspondente.
- Twig/CSS/JS separados; mostrar/ocultar senha, foco em erros e estado de envio progressivos. Formulários POST/CSRF funcionam sem JS e senhas não voltam ao HTML.
- Nome UTF-8 seguro preservado em 419, com status/rejeição mantidos. ProfileService, autorização, rate limit, transações e revogação preservados; nenhuma migration ou alteração do .env.
- Regressão PHP completa (incluindo 419 sem escrita) e preflight local aprovados. Chromium/axe 320–1440px aprovado para layout, disclosures, senhas, foco, 422/419, identidade, rascunho e POST sem JS com revogação real; regressão do shell aprovada.
- Previews settings-390.png/settings-1440.png e settings-security-390.png em storage/previews, fora do Git e inspecionados visualmente. Testes exclusivamente em banco/Mailpit descartáveis; sem aparelhos físicos/leitores de tela.
- Próximo incremento autorizado: revisão integrada de UI/UX e acessibilidade dos fluxos migrados. Homologação operacional permanece adiada; nenhuma publicação autorizada.

## Última entrega aprovada — revisão integrada e correção do hover

- Revisão e correção aprovadas; commit de fechamento: `fix: preserva contraste do dashboard e registra revisão integrada` (hash no Git). A revisão inicial não exercitava hover persistente dos indicadores; correção desse estado concluída e validada após relato do usuário.
- Novo teste ui-integration-browser.cjs: 11 rotas, cinco larguras, axe de páginas completas, texto 200%/espaçamento, teclado/jornada, movimento reduzido, assets/CSP e consulta sem JS. Evidência JSON em storage/previews, fora do Git.
- Regressão PHP completa e preflight local aprovados; integração passou com 77 cenários estruturais e 25 auditorias axe. Suite de acesso aprovada (cadastro/confirmação/reset SMTP, teclado, erros e uso sem JS). Detalhes/limites em docs/UI_REVIEW.md.
- Sem migration, alteração do .env ou publicação. Testes em banco/Mailpit descartáveis; homologação operacional segue adiada.
- Correção atual: hover do card Pendentes herdava a cor genérica do link, igual ao fundo. CSS do card agora preserva branco; teste reproduziu a falha antes da alteração e verifica hover/foco, contagem positiva/zero e axe com hover. Chromium 320–1440px e axe com hover aprovados; preview dashboard-hover-1440.png inspecionado visualmente, fora do Git.
- Desenvolvimento pausado a pedido explícito do usuário após o commit. Aguardar novas instruções, sem iniciar outro incremento. Homologação operacional permanece adiada.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
- Preparação para produção: docs/PRODUCTION.md. Registro de validação: docs/VALIDATION.md.
- UI/UX, componentes e navegação: docs/DESIGN_SYSTEM.md.
- Revisão integrada de UI/UX e acessibilidade: docs/UI_REVIEW.md.
