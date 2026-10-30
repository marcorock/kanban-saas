# Design system — Fluxo Claro

Direção visual original para o Kanban SaaS: clareza, ritmo e foco no próximo passo. A imagem enviada pelo usuário serviu como referência de organização, sem reproduzir marca, paleta, dashboard ou componentes. Fluxo Claro é o nome interno deste sistema; o nome do produto continua vindo de APP_NAME.

## Escopo e evolução

Primeiro incremento: login, cadastro, recuperação/redefinição de senha, confirmação/reenvio de e-mail e aviso pós-cadastro. Layout de acesso em `auth/layout.html.twig`. O segundo incremento implementa o shell autenticado em `layout.html.twig`, com header, navegação e footer próprios; o conteúdo dos módulos permanece para entregas posteriores. A página inicial `/` mantém o visual anterior. O terceiro incremento redesenha o dashboard; próximo incremento proposto: listagem de projetos. Homologação operacional continua adiada.

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
- Limitações: sem teste em dispositivos físicos/leitores de tela; módulos restantes continuam com seus componentes anteriores. Próxima entrega proposta: listagem de projetos, seguida de formulários/detalhes em incremento próprio.

Validação do terceiro incremento: regressão PHP completa e preflight local aprovados; Chromium aprovado para dashboard, relatórios e shell. Axe sem violações A/AA nos casos de dashboard preenchido e vazio verificados. Previews mobile/desktop inspecionados visualmente; não há certificação de conformidade ou teste em aparelhos físicos.
