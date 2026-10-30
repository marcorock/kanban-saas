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
