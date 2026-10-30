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

## Módulos consolidados

- Autenticação, confirmação/reset SMTP e perfil/sessões: docs/AUTHENTICATION.md.
- Projetos, papéis, convites, transferência e auditoria: docs/PROJECTS.md e docs/MEMBERS.md.
- Kanban transacional com board_version, tarefas, responsáveis, checklists e comentários: docs/KANBAN.md e docs/TASKS.md.
- Dashboard, Minhas Tarefas e históricos com escopo de tenant/projeto: docs/REPORTS.md.
- Fase 6 aprovada: preflight somente leitura, segurança, regressão e roteiro operacional em docs/PRODUCTION.md e docs/VALIDATION.md.
- Fluxo Claro já aplicado a acesso, navegação, dashboard, listagem, formulários, detalhes, equipe e quadro e painel. Detalhes da evolução em docs/DESIGN_SYSTEM.md e docs/DEVELOPMENT.md.
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

## Entrega anterior — UI/UX: detalhes do projeto

- Sexto incremento aprovado; commit de fechamento: `47d88da` — `feat: redesenha detalhes do projeto`. Próximo incremento autorizado: participantes e convites.
- Quadro em destaque, papel/estado, descrição completa, planejamento com UTC e navegação de projeto. Gestão separada e visível apenas ao proprietário.
- Disclosure nativo de exclusão mantém checkbox obrigatório e POST/CSRF; arquivar/restaurar e links funcionam sem JS novo. Twig/CSS separados; descrição sem style inline.
- Consultas, autorização, serviços, transações e auditoria preservados. Nenhuma migration nova ou alteração do .env; testes só em banco descartável.
- Regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px: quatro papéis, consulta, atalhos, arquivar/restaurar/excluir, confirmação/CSRF, sem JS e XSS aprovados; regressões de formulários e relatórios aprovadas.
- Previews project-details-390.png/project-details-1440.png e archived-* em storage/previews, ignorados no Git e inspecionados visualmente. Sem teste em dispositivos físicos/leitores de tela. Próximo incremento após aprovação: participantes e convites.
- Homologação operacional permanece adiada; sem publicação.

## Entrega anterior — UI/UX: participantes e convites

- Sétimo incremento aprovado; commit de fechamento: `b8f5fdb` — `feat: redesenha participantes e convites`. Próximo incremento autorizado: quadro Kanban.
- Equipe com papéis e guia, atalho de convite, controles recolhidos, confirmações nativas de remoção/transferência, pendências e consulta de arquivados.
- Privacidade mantida no serviço/HTML: e-mails e convites só para administração, opções admin só para proprietário. Contagens da página, mesma paginação de 25 por seção.
- Aceite usa layout de acesso para visitante e shell para autenticado; prévia apenas para conta elegível, sem conceder acesso na abertura. POST/CSRF funciona sem JS.
- Twig/CSS/JS separados; envio/foco progressivos, 419 contextual com dados de convite, sem reter confirmações sensíveis. Serviços, queries, limites, tokens e transações preservados.
- Regressão PHP completa e preflight local aprovados. Chromium/axe da equipe passou em 320–1440px, incluindo privacidade, SMTP, aceite, remoção, transferência e XSS; regressões de detalhes e telas de acesso aprovadas.
- Previews members-390.png/members-1440.png e invitation-guest-390.png/invitation-accept-390.png em storage/previews, ignorados no Git e inspecionados visualmente; sem aparelhos físicos/leitores de tela.
- Fixtures somente em banco/Mailpit descartáveis, sem migration ou alteração do .env. Homologação operacional continua adiada.
- Próximo incremento após aprovação: quadro Kanban; painel de tarefas em entrega própria.

## Entrega anterior — UI/UX: quadro Kanban

- Oitavo incremento aprovado; commit de fechamento: `01eb9ea` — `feat: redesenha quadro Kanban com Fluxo Claro`.
- Colunas/cards Fluxo Claro, filtros recolhidos com contador/limpeza, navegação horizontal, controles mobile e gestão por papel. Formulários continuam como alternativa ao arrastar.
- HTML em Twig/templates nativos; CSS/JS externos. Preenchimento seguro de texto/valores, restauração de foco e rolagem nas gravações; snapshot para consulta sem JS.
- Endpoints, autorização, serviços, versão concorrente, transações e schema preservados. Painel de tarefas mantém visual anterior, para próximo incremento próprio.
- Nenhuma migration nova, alteração do .env ou publicação; homologação operacional adiada. Fixtures somente em banco/Mailpit descartáveis.
- Regressão PHP completa e preflight local aprovados. Chromium/axe do quadro aprovado em 320–1440px, com arrastar, erro/409, persistência, foco/rolagem, filtros, papéis, arquivados e consulta sem JS; regressões de tarefas e relatórios aprovadas.
- Previews kanban-390.png/kanban-1440.png em storage/previews, ignorados no Git e inspecionados visualmente. Sem teste em dispositivos físicos/leitores de tela.
- Desenvolvimento retomado em 30/10/2026 a pedido do usuário, pelo UI/UX do painel de detalhes das tarefas.

## Entrega anterior — UI/UX: painel de tarefas e checklist compacto

- Nono incremento aprovado; commit de fechamento: `46785c3` — `feat: redesenha painel de tarefas e checklist compacto`.
- Revisão substitui o primeiro desenho não aprovado: fundo branco, resumo da tarefa e abas Subtarefas (inicial), Comentários e Detalhes; histórico em Detalhes. Tela cheia abaixo de 640px e lateral de até 680px acima.
- Subtarefas com checkbox de gravação direta, título editável, editar/excluir na mesma linha; confirmação/cancelamento inline. Adição de item em linha única e gestão de lista no menu. Ações permanecem na linha inclusive no celular.
- Resumo agregado no painel/card: concluídas/total de todas as listas e percentual arredondado. Sem itens mostra Sem subtarefas no painel e omite barra/card. Progresso não muda conclusão por coluna.
- BoardService inclui contagens por tarefa com agregação SQL limitada ao tenant/projeto; mantém 5 consultas no snapshot, sem N+1. Sem alteração de autorização, endpoints, transações ou schema; sem migrations novas ou alteração do .env.
- HTML em Twig/templates, CSS e JS externos. Texto inserido por textContent/valores. Descarte, confirmação de exclusão, retry, rascunhos em erro, foco e posição preservados; checkbox com falha retorna ao estado confirmado.
- Regressão PHP completa, teste ampliado de progresso e preflight local aprovados. Snapshot mantém 5 consultas em carga de 100/1.000 tarefas. Chromium/axe 320–1440px aprovado para alinhamento, marcação/edição/cancelamento, falhas/rascunhos, abas, foco/Escape, exclusão, retry, paginação, quatro papéis e arquivados; regressões de relatórios, shell e dashboard aprovadas.
- Previews reais task-panel-390.png/task-panel-1440.png e task-inline-edit-390.png em storage/previews, ignorados no Git e inspecionados visualmente. Fixtures só em banco/Mailpit descartáveis; sem aparelhos físicos/leitores de tela. Homologação operacional adiada.
- Próximo incremento autorizado: UI/UX de Minhas Tarefas; históricos e configurações em entregas próprias.

## Última entrega aprovada — UI/UX: Minhas Tarefas

- Décimo incremento aprovado; commit de fechamento: `feat: redesenha Minhas Tarefas com Fluxo Claro` (hash no Git).
- Lista clara com prioridade, estado textual, prazo e projeto/coluna; links abrem o painel correto. Ícones de estado são informativos, sem checkbox que simule conclusão fora do quadro.
- Navegação GET Todas/Pendentes/Iniciadas/Concluídas mantém filtros e reinicia paginação. Filtros nativos recolhidos por padrão e abertos quando há critérios adicionais; total real dos resultados, vazios e aviso de arquivados.
- Twig e CSS externo próprios, sem JS novo. Formulários/links funcionam sem JS; detalhes da tarefa continuam dependendo de JS no quadro. Erros 422 recebem apresentação própria, mantendo status/validação.
- ReportingService, consultas, autorização, limites/ordem e schema preservados. Sem migration ou mudança do .env; homologação operacional adiada. Testes só em banco descartável.
- Regressão PHP completa, fixture própria e preflight local aprovados. Chromium/axe em 320–1440px aprovado para filtros/estados, paginação, XSS, atribuições, viewer, conta vazia, arquivados, links, 422/404 e uso sem JS; regressões de relatórios e dashboard aprovadas.
- Previews my-tasks-390.png/my-tasks-1440.png, filtros/vazio em storage/previews, fora do Git e inspecionados visualmente. Sem testes em aparelhos físicos/leitores de tela.
- Próximo incremento autorizado: UI/UX dos históricos pessoal e do projeto; configurações pessoais em entrega posterior.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
- Preparação para produção: docs/PRODUCTION.md. Registro de validação: docs/VALIDATION.md.
- UI/UX, componentes e navegação: docs/DESIGN_SYSTEM.md.
