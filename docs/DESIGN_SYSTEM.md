# Design system — Fluxo Claro

Direção visual original para o Kanban SaaS: clareza, ritmo e foco no próximo passo. A imagem enviada pelo usuário serviu como referência de organização, sem reproduzir marca, paleta, dashboard ou componentes. Fluxo Claro é o nome interno deste sistema; o nome do produto continua vindo de APP_NAME.

## Escopo e evolução

Primeiro incremento: login, cadastro, recuperação/redefinição de senha, confirmação/reenvio de e-mail e aviso pós-cadastro. Layout próprio em `auth/layout.html.twig`; páginas internas e página inicial `/` continuam com seu layout atual. Próximo incremento proposto: shell autenticado (header, navegação e footer), mantendo o conteúdo dos módulos para entregas posteriores. Homologação operacional continua adiada.

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

| Faixa | Acesso implementado | Shell interno planejado |
|---|---|---|
| Até 639px | Coluna única, superfície branca, margens de 24px, sem painel decorativo | Header compacto e navegação inferior com rótulos |
| 640–1023px | Formulário central em superfície de até 620px; sem painel decorativo | Mesma navegação principal, conteúdo com largura confortável para tablet |
| A partir de 1024px | Duas colunas em até 1240px; ilustração editorial à esquerda, formulário à direita | Sidebar de cerca de 232px, header contextual e conteúdo flexível |

Os fluxos de acesso são verificados em 320, 390, 768, 1024 e 1440px. Formulário útil com até 420px. Não bloquear zoom nem fixar altura do conteúdo. Cadastro pode rolar verticalmente; header/footer não ficam fixos, para não ocupar a área do teclado virtual. Texto dos campos com 16px evita diminuição excessiva em celular. A página usa altura mínima de viewport, sem ocultar conteúdo em telas baixas.

## Header, menus e footer

### Acesso — implementado

Header com marca à esquerda e uma navegação de acesso à direita. Login oferece Criar conta; cadastro oferece Entrar; recuperação/confirmar oferece Entrar. O aviso de conta ainda não confirmada oferece Sair por POST com CSRF. A marca retorna ao login. Não há hamburger para uma única alternativa.

Footer leve, após o conteúdo: “Seu trabalho, no seu ritmo.” e ano/nome do projeto. Não contém links vazios de termos, privacidade ou suporte. Em celular os textos ficam em coluna; no desktop em linha. A ilustração de fluxo é decorativa e não simula botões clicáveis.

### Área autenticada — especificação para o próximo incremento

| Item | Rota | Função |
|---|---|---|
| Início | /dashboard | Visão geral e próximos prazos |
| Projetos | /projects | Projetos acessíveis |
| Minhas tarefas (Tarefas no celular) | /tasks | Atribuições próprias |
| Meu histórico | /history | Atividades próprias |
| Configurações | /settings | Perfil e segurança |
| Sair | POST /logout | Encerrar sessão com CSRF |

No celular/tablet, navegação inferior com Início, Projetos, Tarefas e Mais. Mais abre navegação secundária para Meu histórico, Configurações e Sair. Garantir padding de conteúdo e safe-area para a barra não cobrir ações; menu deve funcionar por teclado, fechar com Escape e devolver foco ao acionador. Não depender de hover. No desktop os cinco destinos ficam na sidebar, com Sair no rodapé; header mostra contexto da página e ação relevante quando existir. Estado ativo deve usar `aria-current="page"`, texto e indicador visual.

Navegação de projeto será contextual: visão geral, quadro, participantes e histórico. A exibição de ações acompanha as permissões existentes; UI não substitui autorização no backend. Não transformar todos os destinos em ícones sem legenda.

Footer interno deve ser discreto, sem consumir a área de trabalho com conteúdo promocional. Identificação da conta/saída pode ocupar o rodapé da sidebar; não criar uma segunda barra fixa no celular.

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
- Novos templates sem style inline, blocos style ou scripts inline. Bootstrap permanece nas telas antigas até sua migração planejada; não recebe overrides globais deste incremento.

Os assets são locais e compatíveis com a CSP existente. O preflight verifica sua presença. Previews de teste em `storage/previews/` são ignorados pelo Git, não contêm dados reais e não são dependência da aplicação.

## Acessibilidade e validação

Objetivo: WCAG 2.2 AA nos fluxos migrados, com foco visível, labels, teclado, mensagens perceptíveis, movimento reduzido e alvos confortáveis. Os testes automatizados não certificam conformidade e não substituem teste manual em leitores de tela ou aparelhos físicos.

`tests/auth-ui-browser.cjs` usa Chromium, Playwright e axe em ambiente descartável. Verifica largura sem overflow, ausência de CSS/JS inline, controles, contraste/regras automatizadas A/AA, teclado, interação da senha, mensagens, cadastro/confirmação, recuperação/reset e funcionamento sem JS. Para executá-lo, instale playwright-core e @axe-core/playwright apenas no ambiente de teste; defina AUTH_TEST_DATABASE=disposable, NODE_PATH e, se necessário, AUTH_TEST_URL/MAILPIT_TEST_URL/CHROMIUM_PATH/UI_PREVIEW_DIR. Expire limites de tentativas no banco descartável ao reutilizar um ambiente após testes de rate limit.

Referências de acessibilidade: [contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [tamanho mínimo de alvos](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) e [mensagens em formulários](https://www.w3.org/WAI/tutorials/forms/notifications/).
