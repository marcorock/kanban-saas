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
| aba013c | Perfil, troca de senha e encerramento das demais sessões |
| 58c0e2a | Validação, segurança, desempenho e preparação de instalação |
| 2f0fd5c | Design system Fluxo Claro e telas de acesso |
| 0460acf | Header, menus responsivos e footer da área autenticada |
| 756af0e | Dashboard Fluxo Claro com indicadores, prazos e progresso nativo |
| ace0d17 | Listagem de projetos com filtros, cards e atalhos |
| 39f7edc | Formulários de criação/edição com validação e retorno contextual |
| 47d88da | Detalhes do projeto, planejamento e gerenciamento por papel |
| b8f5fdb | Participantes, convites e aceite Fluxo Claro |
| 01eb9ea | Quadro Kanban Fluxo Claro |
| 46785c3 | Painel de tarefas, progresso agregado e checklist compacto |
| 61b86a4 | Minhas Tarefas Fluxo Claro |

Os requisitos completos originais foram preservados em [REQUIREMENTS.md](REQUIREMENTS.md); PROJECT_CONTEXT.md mantém somente estado conciso, etapa corrente e próximo incremento. `git log` é a fonte dos commits completos; CHANGELOG registra a evolução funcional.

## Pausa em 29/10/2026

Dashboard, Minhas Tarefas e históricos aprovados e registrados no commit `feat: adiciona dashboard e históricos consolidados` (consultar Git para o hash). Regressão PHP e Chromium aprovadas; migrations 001–008 aplicadas ao ambiente local. Desenvolvimento pausado a pedido do usuário, sem iniciar perfil/configurações pessoais, que será o próximo incremento na retomada.

## Retomada em 29/10/2026

Usuário solicitou continuar com perfil e configurações pessoais. Perfil implementado em `/settings`, com edição do nome, troca de senha e revogação das demais sessões, sem nova migration. Teste de perfil em banco descartável aprovado, incluindo rollback e concorrência. Regressão PHP dos oito módulos anteriores e migrations idempotentes aprovadas; sem teste visual em navegador neste incremento. Aprovado pelo usuário e incluído no commit `feat: adiciona perfil e configurações pessoais`; próximo incremento: revisão de segurança/desempenho e preparação para produção (Fase 6).

## Fase 6 — validação e preparação para produção

Iniciada após aprovação do perfil. Preflight CLI somente de leitura, requisitos PHP explícitos, cabeçalhos de segurança, redução de memória do snapshot e runner sequencial implementados. Regressão PHP, preflight com usuário SQL somente leitura, carga sintética de 1.000 tarefas e Chromium aprovados. Evidências em [VALIDATION.md](VALIDATION.md), instalação em [PRODUCTION.md](PRODUCTION.md). Sem migrations novas, sem mudança de credenciais locais, sem publicação. Incremento aprovado pelo usuário e registrado no commit `chore: prepara validação e instalação em produção`. A pedido do usuário, a homologação operacional foi adiada: após este commit, aguardar sua descrição dos próximos detalhes antes de iniciar nova implementação.

## UI/UX — primeiro incremento: acesso

Após adiar a homologação operacional, o usuário solicitou uma identidade original com base mobile first e entregas pequenas, começando por login, cadastro e reset. Fluxo Claro define cores, tipografia, componentes, header/footer e evolução dos menus em [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md). Implementação inclui continuidade do cadastro com confirmação/reenvio de e-mail; telas internas e página inicial ainda usam o layout anterior.

HTML/Twig, CSS e JavaScript permanecem em arquivos separados, sem inline nas novas telas; regra adicionada ao AGENTS.md por pedido explícito. Sem nova migration ou alteração de credenciais. Testes PHP de autenticação/conta/segurança/preflight e Chromium/axe dos fluxos migrados aprovados. Preview mobile e desktop verificado visualmente; testes automatizados não substituem validação em aparelhos físicos e leitores de tela.

Incremento aprovado e incluído no commit `feat: define design system e redesenha telas de acesso`. Próximo incremento autorizado: shell autenticado (header, menus responsivos e footer), seguido da adaptação de cada módulo. Não iniciar todos os módulos de uma vez; homologação operacional continua adiada.

## UI/UX — segundo incremento: estrutura autenticada

Implementado após aprovação das telas de acesso. Header contextual, sidebar desktop, navegação inferior mobile/tablet, diálogo Mais e footer; estado ativo por módulo e atalhos de perfil. Logout mantém POST/CSRF, e Mais tem fallback nativo sem JavaScript. NavigationContext prepara apenas apresentação; não substitui autorização. Convites autenticados recebem o shell sem alterar a lógica do aceite.

Regressão PHP completa aprovada, incluindo testes novos de shell. Chromium/axe dos componentes novos aprovado em 320–1440px, com foco, Escape, resize e coexistência do offcanvas; regressões de tarefas, relatórios e perfil também aprovadas. Preflight local somente de leitura aprovado; nenhuma migration nova, mudança do .env ou publicação. CSS/JS/Twig permanecem separados. Previews em storage/previews, fora do Git.

Aprovado pelo usuário e incluído no commit `feat: redesenha navegação da área autenticada`. Próximo incremento autorizado: dashboard; componentes dos demais módulos continuam nas versões anteriores e serão migrados um por vez. Homologação operacional permanece adiada.

## UI/UX — terceiro incremento: dashboard

Iniciado após aprovação da navegação. Dashboard redesenhado com quatro indicadores pessoais e atalhos de filtro, prazos priorizados, progresso nativo e atividades compactas. Contagem de projetos ativos junto do progresso. Estados vazios e CTA de projeto, com mesma leitura mobile/desktop. Twig e CSS externos separados; removido o style inline da barra, sem JavaScript novo.

ReportingService e suas regras permanecem iguais. Regressão PHP completa e preflight local aprovados. Chromium/axe da página em 320–1440px, filtros, deep links, progresso sem JS, datas UTC, XSS e conta vazia aprovados; regressões de relatórios e shell também aprovadas. Previews verificados visualmente; sem teste em aparelhos físicos/leitores de tela. Nenhuma migration nova, alteração de .env ou publicação. Homologação operacional continua adiada. Incremento aprovado e incluído no commit `feat: redesenha dashboard com Fluxo Claro`; próximo incremento autorizado: listagem de projetos.

## UI/UX — quarto incremento: listagem de projetos

Iniciado após aprovação do dashboard. Listagem ativa/arquivada redesenhada em cards responsivos com papel, vínculo, prazo e prévia de descrição; atalhos de quadro e detalhes. Estados vazios, paginação e feedback próprios. Twig e CSS externos, sem JS adicional. Repository e políticas permanecem iguais, com 25 itens por página.

Regressão PHP completa e preflight local aprovados. Testes novos de listagem em PHP/Chromium/axe aprovados para quatro papéis, isolamento, paginação com filtro, XSS, estados vazios, 320–1440px e uso sem JavaScript. Regressões Chromium de dashboard, perfil e shell também aprovadas. Previews inspecionados visualmente; sem teste em dispositivos físicos/leitores de tela.

Incremento aprovado e incluído no commit `feat: redesenha listagem de projetos`. Próximo incremento autorizado: formulários de criação/edição. Sem novas migrations, alteração do .env ou deploy; homologação operacional adiada.

## UI/UX — quinto incremento: criação e edição de projetos

Iniciado após aprovação da listagem. Formulários com seções de informações/prazos, indicação de obrigatoriedade, ajuda, contadores e retorno de erro com foco. CSS e JS externos; envio comum por POST continua sem JS. Em 419, criação/edição reapresentam os dados e token atual, mantendo rejeição sem escrita. Políticas, validações, serviços e transações preservados.

Regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px aprovado nos formulários (criação/edição, datas, 422/419, foco/preservação, XSS, cancelamento, papéis e envio sem JS); regressões de listagem e dashboard também aprovadas. Previews inspecionados visualmente, sem teste em dispositivos físicos/leitores de tela. Documentação de contexto consolidada para manter leitura curta; o histórico completo continua neste arquivo e no Git. Nenhuma migration nova, mudança do .env ou deploy. Incremento aprovado e incluído no commit `feat: redesenha formulários de projetos`; próximo incremento autorizado: detalhes do projeto.

## UI/UX — sexto incremento: detalhes do projeto

Iniciado após aprovação dos formulários. Detalhes com quadro em destaque, badges de estado/papel, descrição completa em texto simples, planejamento com datas de registro UTC e navegação contextual. Gestão exclusiva do proprietário separada; exclusão em details/summary nativo, com checkbox obrigatório preservado. Sem JS novo, sem style inline ou alterações em consultas/políticas/serviços.

Regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px aprovado para quatro papéis, atalhos/consulta, arquivar/restaurar/excluir, confirmação/CSRF, sem JS e XSS; regressões de formulários e relatórios também aprovadas. Previews inspecionados visualmente; sem teste em dispositivos físicos/leitores de tela. Ações reais de teste ocorreram somente no banco descartável. Sem migration nova, alteração do .env ou publicação. Incremento aprovado e incluído no commit `feat: redesenha detalhes do projeto`; próximo incremento autorizado: participantes e convites. Homologação operacional permanece adiada.

## UI/UX — sétimo incremento: participantes e convites

Iniciado após aprovação dos detalhes. Equipe em cards, atalho para convidar, guia de papéis, controles de acesso recolhidos e confirmações nativas. Privacidade de e-mails e pendentes restrita à administração, opções de admin exclusivas ao owner e consulta em arquivados preservadas. Aceite integrado ao layout de acesso para visitantes e ao shell para autenticados, incluindo conta incompatível/convite indisponível.

CSS/JS externos; formulários continuam com POST/CSRF e uso sem JS. Retorno 419 agora usa resumo de erro; convite preserva e-mail/papel quando aplicável, sem reter confirmações sensíveis. Serviços, consultas, limites, tokens e transações não alterados. Regressão PHP e preflight local aprovados; Chromium/axe de equipe e aceite, detalhes e telas de acesso aprovados. Papéis, privacidade, SMTP, confirmações, CSRF, transferência e XSS verificados; previews inspecionados visualmente. Sem teste em aparelhos físicos/leitores de tela. Fixtures só em banco descartável.

Incremento aprovado e incluído no commit `feat: redesenha participantes e convites`. Próximo incremento autorizado: quadro Kanban; painel de tarefas em entrega posterior. Homologação operacional permanece adiada, sem migration nova ou alteração do .env.


## UI/UX — oitavo incremento: quadro Kanban

Iniciado após aprovação de participantes/convites, registrados no commit `b8f5fdb`. Quadro com colunas/cards Fluxo Claro, filtros recolhidos com contador/limpeza, navegação horizontal e gestão por papel. Controles mobile compactos; edição por formulários preserva alternativa ao arrastar. Renderização do quadro clona templates Twig e preenche texto/valores, com CSS e JS externos. Gravações restauram foco/posição horizontal; consulta sem JS continua disponível. Painel de tarefas permanece para entrega própria.

Endpoints, autorização, serviços, controle de versão, transações e banco preservados. Nenhuma migration nova, alteração do .env ou publicação. Homologação operacional permanece adiada. Próximo incremento após aprovação: painel de detalhes das tarefas.

Validação: regressão PHP completa e preflight local aprovados. Chromium/axe do quadro em 320–1440px aprovado para papéis, arquivados, filtros, navegação, foco/rolagem e consulta sem JS, além da regressão de arrastar, falha, conflito e persistência. Regressões de tarefas e relatórios aprovadas. Previews kanban-390.png/kanban-1440.png inspecionados visualmente, fora do Git. Sem testes em aparelhos físicos/leitores de tela. Incremento aprovado e incluído no commit `feat: redesenha quadro Kanban com Fluxo Claro` (hash no Git).


## Pausa após o oitavo incremento de UI/UX

A pedido do usuário, o desenvolvimento foi pausado após aprovação e commit do quadro Kanban. O próximo incremento não foi iniciado. Na retomada, continuar pelo visual do painel de detalhes das tarefas, mantendo Twig, CSS e JavaScript separados. Validações e limitações do Kanban estão registradas acima e no PROJECT_CONTEXT.md. Homologação operacional permanece adiada.


## Retomada em 30/10/2026 — nono incremento de UI/UX

Retomada solicitada pelo usuário após pausa com Kanban aprovado e commitado (`01eb9ea`). Implementado o painel de detalhes das tarefas no Fluxo Claro, com seções de informações, planejamento, responsáveis, checklists, comentários e histórico. Tela cheia no celular abaixo de 640px e lateral de até 680px acima; header com fechamento acessível, corpo com rolagem própria e progresso nativo das listas.

Estrutura dinâmica migrou para templates Twig em tasks/templates.html.twig; apresentação em task-panel.css; interações continuam externas em board.js. Textos/valores são preenchidos sem interpolar HTML. Fechar por Escape usa as mesmas proteções de descarte/gravação; foco retorna ao card. Gravações restauram posição/foco do formulário e falha de leitura oferece nova tentativa sem editor anterior. Paginações identificadas para comentários e histórico, mantendo limites existentes. Endpoints, serviços, autorização, transações e schema preservados; nenhuma migration nova ou alteração do .env. Homologação operacional continua adiada.

Próximo incremento após aprovação: Minhas Tarefas. Históricos e configurações pessoais permanecem em entregas próprias. Este incremento aguarda aprovação para commit.

Validação do nono incremento: regressão PHP completa e preflight local aprovados. Chromium/axe 320–1440px aprovado para painel, quatro papéis, arquivados, foco/Escape, rascunhos, XSS, retry, exclusão e paginação independente de comentários/histórico. Regressões do quadro, relatórios e shell aprovadas. Previews task-panel-*.png/task-checklists-*.png inspecionados visualmente, fora do Git. Testes exclusivamente em banco/Mailpit descartáveis; sem aparelhos físicos/leitores de tela ou certificação de acessibilidade.


### Revisão visual solicitada — preview antes da implementação

O usuário não aprovou o desenho inicial do nono incremento, especialmente os botões e sua disposição. Solicitou interface mais limpa, contagem/percentual das subtarefas na tarefa principal e checklist com marcação, edição e exclusão na mesma linha. Foi apresentado mockup conceitual mobile/desktop por imagegen, com resumo também no card Kanban, edição inline e ações compactas. Nenhuma alteração adicional na aplicação nesta etapa de preview. A revisão aguarda validação visual; a versão atual do painel permanece sem aprovação e sem commit. O progresso proposto agrega itens das listas e não substitui a conclusão por coluna. As validações acima correspondem à primeira implementação, não à proposta ainda não implementada.


### Implementação da revisão após aprovação do preview

O usuário aprovou o mockup e autorizou implementar. A revisão adota fundo branco, resumo de progresso, abas com Subtarefas inicialmente ativa e checklist em linhas compactas. Checkbox grava diretamente; editar/excluir ficam alinhados ao título e confirmar/cancelar permanecem na mesma linha durante edição. Adição compacta, menu de lista, atalhos Enter/Escape, navegação das abas com setas/Home/End e rascunhos preservados. Marcações com falha/cancelamento voltam ao estado confirmado; editar texto preserva a conclusão do item.

Card e painel mostram concluídas/total e percentual agregado de todas as listas, sem alterar a conclusão por coluna. BoardService passou a agregar itens no snapshot com SQL restrito ao tenant/projeto, mantendo cinco consultas. Testes PHP acrescentam múltiplas listas, isolamento por tarefa/projeto, reabertura e exclusão. Testes de navegador foram adaptados às abas e à marcação direta; revisão concluída e aguardando aprovação da entrega funcional. Não há migration nova, alteração do .env ou publicação. Aprovação do preview não foi tratada como aprovação da entrega funcional para commit.

Validação da revisão: regressão PHP completa e teste ampliado de progresso aprovados (múltiplas listas, isolamento por tarefa/projeto, reabertura/exclusão, 100% sem conclusão automática). Carga de 100/1.000 tarefas manteve cinco consultas no quadro. Preflight local aprovado. Chromium/axe do painel em 320–1440px aprovado para layout das ações, marcação direta, edição/cancelamento inline, preservação em falhas, 3/5 e 60%, abas/teclado, foco, retry, exclusão, paginação, quatro papéis e arquivados. Regressões de relatórios, shell e dashboard aprovadas. Previews reais mobile/desktop/edição inspecionados visualmente, fora do Git. Sem teste em aparelhos físicos/leitores de tela. Incremento permanece sem commit até aprovação da entrega.


Nono incremento aprovado pelo usuário e incluído no commit `feat: redesenha painel de tarefas e checklist compacto` (hash no Git). Próximo incremento autorizado: UI/UX de Minhas Tarefas; históricos e configurações pessoais em entregas próprias.


## UI/UX — décimo incremento: Minhas Tarefas

Iniciado após aprovação e commit do painel/checklist compacto (`46785c3`). Lista clara com estado, prioridade, prazo, contexto e link direto à tarefa. Navegação GET de estados preserva critérios e reinicia página; filtros nativos recolhidos por padrão, abertos com critérios adicionais, total real e paginação de 25 itens. Vazios explicam atribuições e arquivados recebem aviso de consulta. Não há checkbox de conclusão nesta visão, pois o estado da tarefa continua sendo definido no quadro.

HTML em reports/tasks.html.twig e reports/tasks-error.html.twig, CSS em my-tasks.css, sem JS novo. POST/mutações não são introduzidos; GET funciona sem JS. Retorno 422 usa a nova apresentação, mantendo validação/status. ReportingService, políticas, consultas, ordenação e limites preservados. Sem migration nova ou alteração do .env. Fixture e teste de navegador próprios em my-tasks-ui.php/my-tasks-browser.cjs, exclusivamente em banco descartável.

Incremento concluído, aguardando aprovação própria para commit. Próximo passo após aprovação: UI/UX dos históricos pessoal e de projeto; configurações pessoais em entrega posterior. Homologação operacional permanece adiada.

Validação do décimo incremento: regressão PHP completa, fixture própria e preflight local aprovados. Chromium/axe de Minhas Tarefas em 320–1440px aprovado para lista, título longo/HTML literal, filtros/estados e sua preservação, paginação, atribuições próprias/alheias, conta vazia, viewer, arquivados, deep link, 422/404 e navegação sem JS. Regressões de relatórios e dashboard aprovadas. Previews mobile/desktop, filtros e vazio inspecionados visualmente, fora do Git. Sem testes em aparelhos físicos/leitores de tela. Fixtures executadas exclusivamente em banco/Mailpit descartáveis.


Décimo incremento aprovado pelo usuário e incluído no commit `feat: redesenha Minhas Tarefas com Fluxo Claro` (hash no Git). Próximo incremento autorizado: históricos pessoal e do projeto; configurações pessoais em entrega posterior.


## UI/UX — décimo primeiro incremento: históricos

Iniciado após aprovação e commit de Minhas Tarefas (`61b86a4`). Históricos pessoal e do projeto com linha do tempo, ação/autor, horários UTC e contexto com links de projeto/tarefa. Escopos pessoais/equipe explícitos. Filtros nativos recolhidos por padrão, abertos com critérios ativos, totais reais e paginação GET preservada. Comparação de snapshots em campos com Antes/Depois, empilhados no celular e lado a lado no desktop, sem tabela larga.

HTML em reports/history.html.twig/events.html.twig/history-error.html.twig; CSS externo history.css, sem JS novo. Privacidade continua no ActivityPresenter; templates não recebem dados administrativos ocultos para membros/viewers. Consultas, autorização, limites e ordenação preservados. O erro 422 recebe a nova apresentação, mantendo status/validação; Limpar filtros agora retorna ao mesmo histórico do projeto quando aplicável. Assets/templates legados reports.css e reports/pagination.html.twig, sem referências restantes, foram removidos. Sem migration ou alteração do .env.

Fixture de testes em history-ui.php e navegador em history-browser.cjs, exclusivamente descartáveis. Incremento concluído; aguarda aprovação própria para commit. Próximo incremento após aprovação: perfil/configurações pessoais. Homologação operacional continua adiada. PROJECT_CONTEXT foi consolidado para manter o estado atual conciso; este arquivo e Git preservam a evolução anterior.

Validação do décimo primeiro incremento: regressão PHP completa, fixture de históricos, teste de relatórios após ajuste de retorno 422 e preflight local aprovados. Chromium/axe 320–1440px aprovado para pessoal/projeto, snapshots longos e HTML literal, filtros/paginação/UTC, ações próprias/equipe, privacidade administrativa inclusive após rebaixamento, arquivados, links, 422/404 e navegação sem JS. Regressão de relatórios no navegador aprovada. Previews mobile/desktop e comparações inspecionados visualmente, fora do Git. Sem testes em aparelhos físicos/leitores de tela. Fixtures exclusivamente em banco/Mailpit descartáveis. Sem migration ou mudança do .env.


Décimo primeiro incremento aprovado e incluído no commit `feat: redesenha históricos com Fluxo Claro` (hash no Git). Próximo incremento autorizado: UI/UX de perfil e configurações pessoais.
