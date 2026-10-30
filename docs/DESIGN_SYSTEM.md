# Design system — Fluxo Claro

Direção visual original para o Kanban SaaS: clareza, ritmo e foco no próximo passo. A imagem enviada pelo usuário serviu como referência de organização, sem reproduzir marca, paleta, dashboard ou componentes. Fluxo Claro é o nome interno deste sistema; o nome do produto continua vindo de APP_NAME.

## Escopo e evolução

Primeiro incremento: login, cadastro, recuperação/redefinição de senha, confirmação/reenvio de e-mail e aviso pós-cadastro. Layout de acesso em `auth/layout.html.twig`. O segundo incremento implementa o shell autenticado em `layout.html.twig`, com header, navegação e footer próprios; o conteúdo dos módulos permanece para entregas posteriores. A página inicial `/` mantém o visual anterior. O terceiro incremento redesenha o dashboard; o quarto incremento trata a listagem de projetos; o quinto incremento trata os formulários; o sexto incremento trata os detalhes; o sétimo incremento trata participantes e convites; o oitavo incremento trata o quadro Kanban; o nono incremento trata o painel de tarefas; próximo incremento proposto: Minhas Tarefas. Homologação operacional continua adiada.

O desenho deve servir às funções existentes. Não exibir busca global, notificações, calendário, assinatura, login social ou links de políticas sem a funcionalidade/conteúdo correspondente. Não há biblioteca de componentes remota, fonte externa, imagens geradas ou etapa de build.

## Identidade e cores

A marca usa três barras deslocadas, uma referência às colunas e ao avanço entre etapas. O símbolo vetorial e o favicon estão em `public/assets/brand/mark.svg`; o wordmark usa o nome configurado do projeto. O acento lima deve aparecer em detalhes de identidade, sem substituir a legibilidade dos botões.

| Papel | Token CSS | Cor | Uso |
|---|---|---|---|
| Fundo | --ds-color-canvas | #f3f3ec | Ambiente claro em tablet/desktop |
| Superfície | --ds-color-surface | #ffffff | Formulários e cards |
| Texto principal | --ds-color-ink | #20362f | Títulos, labels e conteúdo |
| Texto secundário | --ds-color-muted | #60716a | Descrições e ajuda |
| Ação principal | --ds-color-primary | #194f43 | Botões, links e foco |
| Hover principal | --ds-color-primary-hover | #123e34 | Retorno de interação |
| Acento | --ds-color-accent | #d9ef93 | Destaques sobre verde escuro |
| Fundo suave | --ds-color-soft | #f0f5ee | Ícones de estado e informação |
| Separadores | --ds-color-border | #d7dfd8 | Divisões não interativas |
| Borda de campo | --ds-color-control-border | #84958a | Delimitação de inputs |
| Erro | --ds-color-error / --ds-color-error-surface | #9e3436 / #fff2f0 | Erros com ícone e texto |
| Sucesso | --ds-color-success / --ds-color-success-surface | #205740 / #eef7ee | Confirmações e resultado |

Sobre branco, verde principal tem contraste aproximado 9,37:1; texto secundário 5,16:1; borda dos campos 3,16:1. Separadores claros não representam sozinhos controles. Contraste precisa ser reavaliado ao combinar outros fundos. Estado nunca é comunicado somente por cor.

Prioridades, conclusão e arquivamento continuarão com seus nomes funcionais. As cores desses componentes serão aplicadas quando as telas correspondentes forem migradas, sem alterar o significado das regras atuais.

## Tipografia, espaço e forma

- Interface: fonte do sistema (system-ui), sem download. Corpo e campos 16px, labels 14px, ajuda 13px. Títulos principais entre 32 e 42px, com linhas curtas; não usar caixa alta em parágrafos ou ações.
- Georgia fica restrita ao título editorial do painel ilustrativo de desktop. Não usar em formulários, tabelas ou cards de trabalho.
- Escala de espaço em múltiplos de 4px: 4, 8, 12, 16, 20, 24, 32, 40 e 48px.
- Campos/botões: raio 12px; superfícies principais: 24px. Sombras discretas apenas para hierarquia; evitar cartões aninhados sem propósito.
- Ícones vetoriais de traço, geralmente 20px; não substituem texto de ações importantes. Não usar emoji como ícone de controle.
- Textos em português brasileiro, diretos e orientados à ação. Uma ação principal por formulário. Sem mensagens promocionais de funções ainda inexistentes.

## Mobile first e estrutura responsiva

| Faixa | Acesso implementado | Shell interno implementado |
|---|---|---|
| Até 639px | Coluna única, superfície branca, margens de 24px, sem painel decorativo | Header compacto e navegação inferior com rótulos |
| 640–1023px | Formulário central em superfície de até 620px; sem painel decorativo | Mesma navegação principal, conteúdo com largura confortável para tablet |
| A partir de 1024px | Duas colunas em até 1240px; ilustração editorial à esquerda, formulário à direita | Sidebar de cerca de 232px, header contextual e conteúdo flexível |

Os fluxos de acesso são verificados em 320, 390, 768, 1024 e 1440px. Formulário útil com até 420px. Não bloquear zoom nem fixar altura do conteúdo. Cadastro pode rolar verticalmente; header/footer não ficam fixos, para não ocupar a área do teclado virtual. Texto dos campos com 16px evita diminuição excessiva em celular. A página usa altura mínima de viewport, sem ocultar conteúdo em telas baixas.

## Header, menus e footer

### Acesso — implementado

Header com marca à esquerda e uma navegação de acesso à direita. Login oferece Criar conta; cadastro oferece Entrar; recuperação/confirmar oferece Entrar. O aviso de conta ainda não confirmada oferece Sair por POST com CSRF. A marca retorna ao login. Não há hamburger para uma única alternativa.

Footer leve, após o conteúdo: “Seu trabalho, no seu ritmo.” e ano/nome do projeto. Não contém links vazios de termos, privacidade ou suporte. Em celular os textos ficam em coluna; no desktop em linha. A ilustração de fluxo é decorativa e não simula botões clicáveis.

### Área autenticada — implementada no segundo incremento

| Item | Rota | Função |
|---|---|---|
| Dashboard (Início no celular) | /dashboard | Visão geral e próximos prazos |
| Projetos | /projects | Projetos acessíveis |
| Minhas tarefas (Tarefas no celular) | /tasks | Atribuições próprias |
| Meu histórico | /history | Atividades próprias |
| Configurações | /settings | Perfil e segurança |
| Sair | POST /logout | Encerrar sessão com CSRF |

No celular/tablet, navegação inferior com Início, Projetos, Tarefas e Mais. Mais abre navegação secundária para Meu histórico, Configurações e Sair. Há padding de conteúdo e safe-area para a barra não cobrir as últimas ações. Mais usa diálogo nativo, circulação de foco por Tab/Shift+Tab, fechamento por Escape/botão/backdrop e devolução de foco ao acionador. Ao mudar para desktop o diálogo fecha e o foco retorna à navegação visível. Não depender de hover. No desktop os cinco destinos ficam na sidebar, com perfil/Sair no rodapé; o header mostra o contexto da página e acesso ao perfil. As ações específicas permanecem no conteúdo do módulo, sem duplicá-las no header. Destino atual usa `aria-current="page"`; páginas internas de projeto mantêm Projetos com `aria-current="location"`. Mais sinaliza o módulo secundário ativo sem se declarar uma página.

A navegação de projeto continua contextual, com os links existentes: visão geral, quadro, participantes e histórico. A exibição de ações acompanha as permissões existentes; UI não substitui autorização no backend. Não transformar todos os destinos em ícones sem legenda.

Footer interno é discreto, sem consumir a área de trabalho com conteúdo promocional. Perfil e saída ocupam o rodapé da sidebar; não há uma segunda barra fixa no celular.

## Componentes de acesso — implementados

| Componente | Comportamento |
|---|---|
| Botão principal | Verde profundo, texto branco, altura mínima de 52px; estados normal, hover, foco, envio/desabilitado |
| Ação secundária | Link ou botão contornado; alvo de pelo menos 44px nas ações isoladas |
| Campo | Label permanente, autocomplete apropriado, altura mínima 52px, placeholder apenas como exemplo |
| Senha | Mascarada inicialmente; mostrar/ocultar com botão de 44px, nome acessível e aria-pressed; limite validado pelo servidor |
| Ajuda | Vinculada ao campo por aria-describedby; explica a escolha sem repetir o label |
| Erro | Resumo textual antes do formulário, role=alert, foco programático após resposta do servidor; e-mail/nome preservados quando o backend já os disponibiliza, senha nunca reapresentada |
| Sucesso | Título contextual, role=status e próximo passo real: entrar, abrir painel ou pedir outro link |
| Envio | Botão indisponível e rótulo de progresso até a navegação; restaurado no retorno pelo histórico |

Sem JavaScript, os formulários continuam usando POST/CSRF e a validação do servidor; controles de mostrar senha permanecem ocultos. Regras de senha (12 caracteres mínimos e 72 bytes máximos) e respostas genéricas de recuperação permanecem no backend. Mensagens de sessão expirada também usam o novo layout.

## Separação e organização técnica

- `resources/views/auth/`: layout, painel visual e parciais estruturais de feedback/senha.
- `resources/views/auth.html.twig`, `account.html.twig`, `verify-notice.html.twig`: conteúdo de cada fluxo.
- `resources/views/ui/icons.html.twig`: marcação vetorial reutilizável de ícones, sem estilos ou código de comportamento.
- `public/assets/css/design-tokens.css`: variáveis de identidade, cores, espaçamento, tipografia e dimensões.
- `public/assets/css/auth.css`: layout e componentes das telas de acesso, incluindo todos os breakpoints/estados.
- `public/assets/js/auth.js`: melhorias de senha, envio e foco. Somente arquivo externo; sem handlers em atributos HTML.
- Novos templates sem style inline, blocos style ou scripts inline. Bootstrap permanece nos componentes dos módulos até sua migração planejada. O shell tem classes próprias `app-*`, sem redesenhar cards/tabelas/formulários neste incremento.

Os assets são locais e compatíveis com a CSP existente. O preflight verifica sua presença. Previews de teste em `storage/previews/` são ignorados pelo Git, não contêm dados reais e não são dependência da aplicação.

## Estrutura autenticada — implementação

- `app/Services/NavigationContext.php`: seção/título a partir de rotas conhecidas, sem expor o caminho bruto nem conceder permissão. `public/index.php` disponibiliza o contexto ao Twig.
- `resources/views/navigation.html.twig` e `resources/views/app/`: sidebar, header, navegação inferior, links secundários e diálogo. Dados de perfil continuam escapados pelo Twig.
- `public/assets/css/app-shell.css`: layout, breakpoints, safe-area, estados, foco e todas as dimensões do shell. `app-shell.js`: somente comportamento de diálogo/foco; sem CSS inline.
- `public/assets/css/legacy-layout.css`: preserva em arquivo externo as larguras anteriores das páginas públicas ainda não migradas.
- Sem JavaScript, Mais usa details/summary nativo e logout continua sendo POST com CSRF. A navegação desktop funciona por links comuns.
- Diálogo e navegação ficam abaixo da camada de interação apropriada: dialog usa top layer nativa; o offcanvas Bootstrap de tarefas permanece sobre a barra inferior.
- Header/sidebar/menu têm foco e textos legíveis; a barra inferior respeita safe-area e o conteúdo reserva espaço inferior. Não foram feitos testes em teclado virtual/aparelhos físicos.
- O shell aparece somente com usuário autenticado e e-mail confirmado; rotas e políticas existentes permanecem no backend. Convite autenticado recebe o mesmo contexto; visitantes continuam no fluxo público.
- Os estilos inline e o JSON de dados preexistentes dentro de módulos serão tratados na migração de cada componente; nenhum estilo ou script executável inline foi introduzido no shell.

## Acessibilidade e validação

Objetivo: WCAG 2.2 AA nos fluxos migrados, com foco visível, labels, teclado, mensagens perceptíveis, movimento reduzido e alvos confortáveis. Os testes automatizados não certificam conformidade e não substituem teste manual em leitores de tela ou aparelhos físicos.

`tests/auth-ui-browser.cjs` usa Chromium, Playwright e axe em ambiente descartável. Verifica largura sem overflow, ausência de CSS/JS inline, controles, contraste/regras automatizadas A/AA, teclado, interação da senha, mensagens, cadastro/confirmação, recuperação/reset e funcionamento sem JS. Para executá-lo, instale playwright-core e @axe-core/playwright apenas no ambiente de teste; defina AUTH_TEST_DATABASE=disposable, NODE_PATH e, se necessário, AUTH_TEST_URL/MAILPIT_TEST_URL/CHROMIUM_PATH/UI_PREVIEW_DIR. Expire limites de tentativas no banco descartável ao reutilizar um ambiente após testes de rate limit.

Referências de acessibilidade: [contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [tamanho mínimo de alvos](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) e [mensagens em formulários](https://www.w3.org/WAI/tutorials/forms/notifications/).

`tests/shell.php` verifica o contexto HTTP, estado ativo, escape, convite autenticado e saída protegida. `tests/shell-browser.cjs` reutiliza o fluxo de Kanban e verifica 320–1440px, menus, foco, Escape/resize, offcanvas e navegação/saída sem JavaScript. Axe neste teste é restrito aos componentes do shell; não representa auditoria dos módulos legados. Instalar playwright-core e @axe-core/playwright somente no ambiente de testes, como no teste de acesso. Previews: storage/previews/shell-390.png, shell-1440.png e shell-menu-390.png.

Validação do segundo incremento: regressão PHP completa aprovada; Chromium aprovado para o shell e para tarefas, relatórios e perfil. Preflight local somente de leitura aprovado, sem migrations novas ou alteração de credenciais. A leitura visual foi feita em previews de celular/desktop; aparelhos físicos e leitores de tela permanecem pendentes. O conteúdo interno do dashboard foi tratado no terceiro incremento, abaixo.

## Terceiro incremento — dashboard

- Hero com saudação breve e ações reais Novo projeto/Minhas tarefas. Quatro indicadores pessoais, em duas colunas no celular e quatro no tablet/desktop, levam aos filtros existentes.
- Pendentes usa destaque verde; atraso positivo ganha cor de atenção e rótulo explícito. Nota explica a sobreposição entre pendentes, iniciadas e atrasadas. Total de projetos ativos acompanha a seção de progresso.
- Ordem de leitura e de teclado: prazos, projetos e atividades. A partir de 1200px, prazos e progresso ficam lado a lado nessa mesma ordem. Não há reorganização visual contraditória à ordem do DOM.
- Barras nativas progress, sem dependência de JS, gráficos externos ou style inline. Percentual e contagem são visíveis; projetos sem tarefas recebem orientação para começar, sem inventar progresso.
- Linha de atividades própria do dashboard, mantendo o componente anterior dos históricos para sua migração futura. Datas explicitamente UTC, com datetime legível por máquina.
- Estados vazios para projetos, prazos e atividades; convite usa o estado de sessão existente e mantém validação no fluxo de aceite.
- `resources/views/dashboard.html.twig` e `resources/views/dashboard/activity.html.twig`: estrutura/dados; `public/assets/css/dashboard.css`: apresentação. Não há arquivo JS novo neste incremento.
- Consultas, permissões, limites (10 projetos, 5 prazos, 10 eventos) e escopos não foram alterados. Preflight verifica o novo CSS. Sem migration nova.
- Teste de navegador em `tests/dashboard-browser.cjs`: 320–1440px, axe A/AA da página, filtros, links de tarefas, progresso nativo sem JS, datas UTC, XSS, projeto sem tarefas e conta vazia. Previews em storage/previews/dashboard-390.png, dashboard-1440.png e dashboard-empty-390.png, ignorados no Git.
- Limitações: sem teste em dispositivos físicos/leitores de tela; módulos restantes continuam com seus componentes anteriores. A listagem foi tratada no quarto incremento; formulários/detalhes seguem em entregas próprias.

Validação do terceiro incremento: regressão PHP completa e preflight local aprovados; Chromium aprovado para dashboard, relatórios e shell. Axe sem violações A/AA nos casos de dashboard preenchido e vazio verificados. Previews mobile/desktop inspecionados visualmente; não há certificação de conformidade ou teste em aparelhos físicos.

## Quarto incremento — listagem de projetos

- Hero com ação Novo projeto; links GET Ativos/Arquivados com aria-current. Contagem corresponde ao filtro atual, sem contagens fictícias nas abas.
- Uma coluna no celular; duas a partir de 760px e três a partir de 1440px. Cards com nome completo, papel atual, vínculo, prévia de descrição (até 160 caracteres/duas linhas visuais) e prazo ou ausência dele.
- Acesso ao quadro e aos detalhes com alvos confortáveis. Arquivados recebem ícone/contexto de arquivo e link Consultar quadro; os quatro papéis têm nomes claros. Permissões continuam no backend.
- Estados vazios próprios por filtro; paginação por GET preserva estado e oferece limites não interativos. Feedback da listagem em componente próprio, preservando feedback antigo nos módulos ainda não migrados.
- `resources/views/projects/index.html.twig`: estrutura; `public/assets/css/projects-list.css`: apresentação. Sem JS novo. Sem mudanças em consultas, schema, escopo ou política; sem estatísticas adicionais por card.
- PHP `tests/projects-list.php` cria fixtures descartáveis para os quatro papéis, compartilhamento, arquivamento, nomes longos, XSS, vazios e paginação; browser `tests/projects-list-browser.cjs` testa 320–1440px, axe A/AA, teclado, links e navegação sem JS. Fixture e previews em storage/previews ficam fora do Git.
- Próximo incremento: criação/edição de projetos; detalhes, participantes e Kanban serão migrados separadamente. Sem homologação operacional neste incremento.

Validação do quarto incremento: regressão PHP completa, preflight local e Chromium da listagem/dashboard/perfil/shell aprovados. Axe sem violações A/AA nos casos verificados da listagem preenchida, arquivada, vazia e com textos longos. Previews inspecionados visualmente. Testes usaram exclusivamente banco descartável; aparelhos físicos e leitores de tela permanecem pendentes.

## Quinto incremento — formulários de projetos

- Seções de informações/prazos, indicação de nome obrigatório e demais campos opcionais; ajuda associada por aria-describedby e campos de pelo menos 52px/16px.
- Uma coluna no celular, datas lado a lado a partir de 640px e apoio contextual no desktop a partir de 1280px. Ações no fluxo normal, sem barra fixa disputando espaço com o teclado.
- Contadores informativos, mínimo de término conforme início e estado de envio, todos em JS externo. Sem JS, POST e validação do servidor continuam funcionais.
- Resumo textual com role=alert, foco em erro e preservação de campos em 422/419. Campos reapresentados continuam escapados; nenhum rascunho salvo automaticamente. Cancelar não altera o projeto.
- Templates em projects/form.html.twig; estilos em projects-form.css; interações em projects-form.js. O preflight verifica os assets. Backend preserva regras, escopo, transações e autorização.
- Previews em storage/previews/projects-new-390.png, projects-new-1440.png e correspondentes projects-edit-*.png. Testes em projects-form-browser.cjs, com fixture descartável da listagem; executar antes tests/projects-list.php ou o runner PHP.
- Limites: teste automatizado em Chromium; dispositivos físicos e leitores de tela não avaliados. Próximo incremento: detalhes do projeto; homologação operacional permanece adiada.

Validação do quinto incremento: regressão PHP completa e preflight local aprovados. Chromium/axe da página em 320–1440px aprovado para criação, edição e retorno de erro; fluxos de datas, 422/419, preservação/foco, XSS, cancelamento, autorização e envio sem JS aprovados. Regressões de listagem e dashboard também aprovadas; previews inspecionados visualmente.

## Sexto incremento — detalhes do projeto

- Estado/papel, nome completo e ação principal de quadro; edição apenas para quem gerencia projeto ativo. Navegação contextual por links GET, sem simular widget de tabs.
- Descrição completa e planejamento em coluna no celular, lado a lado a partir de 1200px. Datas ausentes explícitas e registros reais em UTC. Conteúdo longo quebra linha sem ocultar o contexto.
- Avisos explicam colaboração, consulta e arquivamento. Gestão do proprietário em seção própria; exclusão recolhida por details/summary nativo, com confirmação obrigatória e botão distinto de abrir opções.
- HTML em projects/show.html.twig; CSS em project-details.css; nenhum JS adicional. Descrição não usa style inline. Feedback de sucesso permanece acessível por role=status.
- Permissões, consultas, serviços, transações e auditoria preservados. Ações seguem POST/CSRF, inclusive sem JS. Não há progresso/totais fictícios ou exposição de dados de projetos inacessíveis.
- `tests/project-details-browser.cjs` verifica 320–1440px, axe A/AA, quatro papéis, atalhos, consulta, arquivamento/restauração/exclusão, confirmação/CSRF, sem JS e textos/XSS. Fixture de tests/projects-list.php, exclusivamente descartável. Registros de testes e previews ficam fora do Git.
- Limites: sem aparelhos físicos/leitores de tela; participantes, convites e demais módulos aguardam migração. Homologação operacional adiada.

Validação do sexto incremento: regressão PHP completa e preflight local aprovados; Chromium/axe dos detalhes ativo/arquivado, quatro papéis e exclusão expandida aprovado em 320–1440px. Fluxos de arquivar/restaurar/excluir, confirmação/CSRF, navegação sem JS e XSS aprovados; regressões de formulários e relatórios aprovadas. Previews inspecionados visualmente, sem certificação ou teste em dispositivos físicos.

## Sétimo incremento — participantes, convites e aceite

- Cards de equipe com papel/nome, e-mail somente para administração e indicação Você. Contagem da página, sem totais simulados. Atalho de convite visível no topo para quem pode gerenciar.
- Mudar papel, remover e transferir ficam em disclosures nativos. Confirmações sensíveis continuam obrigatórias; não são restauradas no erro. Papéis e opções refletem as restrições existentes, inclusive em arquivados.
- Convite com contexto de acesso e guia de papéis. Pendências mostram validade UTC e ações permitidas; seletor admin só para owner. Dados privados não aparecem para membro/visualizador.
- Aceite usa o layout de acesso para visitante e o shell para autenticado; não mostra projeto a conta incompatível. Caminho de entrar/cadastrar, prévia elegível e confirmação por POST preservados.
- HTML em Twig, CSS em project-members.css e melhorias em project-members.js (envio/foco, sem estilos). Native details e formulários continuam funcionais sem JS. Base auth agora oferece blocos de CSS/JS externo.
- 419 retorna resumo com dados de convite preservados quando aplicável, sem ação. SMTP e regras permanecem no serviço; retorno de falha tem mensagem acionável sem expor configuração.
- Testes de fixture/equipe em members-ui.php/members-browser.cjs, com banco e Mailpit descartáveis. Screenshots de equipe e aceite em storage/previews, ignorados no Git. Sem aparelhos físicos/leitores de tela.
- Próximo incremento: quadro Kanban. Painel de detalhes das tarefas em entrega própria; sem homologação operacional automática.

Validação do sétimo incremento: regressão PHP completa e preflight local aprovados. Chromium/axe de equipe e aceite aprovado, incluindo quatro papéis, privacidade, arquivados, SMTP, remoção/transferência, visitante/conta incompatível, uso único, 419/foco e operação sem JS. Regressões de detalhes e telas de acesso aprovadas. Previews inspecionados visualmente; nenhuma certificação ou validação em aparelhos físicos.


## Oitavo incremento — quadro Kanban

- Colunas verde suave e cards brancos, prioridades/conclusão com texto e cor, prazos e responsáveis legíveis. Nome completo do projeto e navegação contextual preservados.
- Rolagem horizontal isolada; colunas de 85vw até 575px e 288px acima. Botões anterior/próxima com rótulos acessíveis e movimento reduzido; controles compactos no celular para priorizar o quadro.
- Filtros e criação de coluna em disclosures nativos; contador/limpeza de filtros. Configurações recolhidas, campos de 16px e ações de pelo menos 44px. Formulários oferecem alternativas ao arrastar e mantêm permissões existentes.
- Templates HTML em Twig, CSS externo e JS externo para preencher estado, sem interpolar HTML do usuário. Restauração de foco e posição horizontal nas gravações; consulta do snapshot sem JS. Painel lateral de tarefas mantém seu visual anterior nesta entrega.
- Sem alteração em endpoints, políticas, serviços, schema, transações ou protocolo de versão. Estado JSON inerte e estilos de posicionamento em runtime das bibliotecas existentes permanecem.
- Teste `tests/board-ui-browser.cjs` reutiliza a regressão de drag, falhas, conflito e persistência. Verifica 320–1440px, axe A/AA, foco, navegação, filtros, papéis e fallback. Fixture de equipe e todas as operações somente em banco descartável.
- Próximo incremento: painel de detalhes das tarefas. Sem homologação operacional, aparelhos físicos ou leitores de tela neste incremento.

Validação do oitavo incremento: regressão PHP completa, preflight local e Chromium do quadro/tarefas/relatórios aprovados. Axe sem violações A/AA nos cenários do quadro verificados, incluindo filtros abertos e modos de leitura. Previews mobile/desktop inspecionados visualmente; testes não equivalem a certificação de acessibilidade ou validação em aparelhos físicos.


## Nono incremento — painel de tarefas e checklist compacto

A primeira proposta não foi aprovada. A revisão segue o preview visual validado pelo usuário, com fundo branco, separadores discretos e ações compactas. Mockup aprovado autoriza implementação; a entrega funcional ainda exige sua própria aprovação para commit.

- Resumo do título/coluna/prazo/responsáveis e progresso agregado das subtarefas. Contagem e percentual também no card; barra nativa, sem estilos inline. Progresso não conclui automaticamente a tarefa.
- Abas Subtarefas (inicial), Comentários e Detalhes; histórico em Detalhes. Navegação com setas/Home/End, painéis identificados e rascunhos preservados ao trocar de aba.
- Lista contínua, checkbox com gravação direta, texto e ações editar/excluir na mesma linha. Edição inline substitui texto/ações por campo e confirmar/cancelar. Enter salva; Escape cancela a edição antes de fechar o painel. Ações de 44px; textos longos quebram sem levar ações para outra linha.
- Adicionar item em linha única; nome/configuração/exclusão da lista em menu. Sem botão Salvar item separado abaixo de cada subtarefa. Campos de detalhes continuam com 16px e seções simples.
- Tela cheia abaixo de 640px e lateral de até 680px acima. Header com Fechar; corpo com rolagem própria. Texto preenchido em templates Twig, estilos/JS externos. Foco ao fechar/salvar, retry, confirmação de exclusão e rascunhos em erro preservados.
- BoardService agrega contagens no snapshot com escopo de tenant/projeto e consultas constantes. Sem migration nova, alteração de autoridade ou conclusão. Próximo incremento após aprovação da entrega: Minhas Tarefas; homologação operacional adiada.

Validação da revisão: regressão PHP e preflight local aprovados. Chromium verifica 320, 390, 768, 1024 e 1440px, incluindo alinhamento de checkbox/texto/ações e foco nas abas; axe sem violações A/AA nos cenários auditados. Marcação direta, edição/cancelamento, rascunhos/falhas e progresso 3/5/60% aprovados, assim como papéis, arquivados e regressões de relatórios/shell/dashboard. Previews reais em storage/previews inspecionados visualmente; sem validação em aparelhos físicos/leitores de tela.


## Décimo incremento — Minhas Tarefas

- Lista única, branca, com separadores discretos; título e projeto clicáveis, prioridade compacta, estado textual e prazo. Sem checkbox de conclusão ou novos controles de escrita na visão pessoal.
- Links GET de estado, com aria-current e sem simular tabs dinâmicas. Critérios preservados e página reiniciada. Total corresponde aos resultados filtrados, sem números simulados por estado.
- Filtros em disclosure nativo, recolhidos por padrão e abertos com critérios adicionais. Campos 16px/48px, ações mínimas de 44px, foco visível e funcionamento sem JS. Paginação mantém critérios.
- Arquivados avisam sobre consulta; estados vazios e erro 422 próprios. HTML em Twig, CSS externo my-tasks.css, sem JavaScript novo. Sem alteração de métricas, consultas ou schema.
- Próximo incremento após aprovação: históricos pessoal e de projeto. Homologação operacional permanece adiada.

Validação do décimo incremento: regressão PHP completa e preflight local aprovados. Chromium 320/390/768/1024/1440px e axe A/AA nos cenários auditados aprovados, incluindo lista, texto longo, vazios, filtros abertos, concluídas e 422. Filtros, paginação, atribuições, papéis, arquivados, deep link e uso sem JS aprovados; regressões de relatórios/dashboard aprovadas. Previews inspecionados visualmente; sem validação em aparelhos físicos/leitores de tela.


## Décimo primeiro incremento — históricos

- Linha do tempo em lista branca com separadores discretos: ícone informativo, ação, autor, horário UTC e links de contexto. Escopos pessoal/equipe explícitos e aviso de arquivo.
- Filtros em disclosure nativo, abertos com critérios ativos. GET preservado na paginação e total real, sem contagens por tipo simuladas.
- Comparações Antes/Depois por campo, em coluna no celular e duas colunas a partir de 640px. Conteúdo integral com quebras de linha; não há tabela larga ou estilo inline.
- Campos de 16px/48px, ações e disclosures com mínimo de 44px e foco visível. Vazios e erros próprios. Sem JS novo; filtros, paginação e expansões continuam nativos.
- Templates em reports/, CSS externo history.css; sem mudança de consultas ou privacidade. Estilos/template de paginação legados sem referências removidos. Próximo incremento após aprovação: perfil/configurações pessoais; homologação operacional adiada.

Validação do décimo primeiro incremento: PHP, preflight local e Chromium 320/390/768/1024/1440px aprovados. Axe A/AA sem violações nos cenários auditados: pessoal/projeto, snapshots expandidos, papéis, arquivados, vazios e 422. Comparações, filtros, paginação, UTC, autoria, privacidade, links e uso sem JS aprovados; regressão de relatórios aprovada. Previews inspecionados visualmente; sem teste em aparelhos físicos/leitores de tela.
