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
