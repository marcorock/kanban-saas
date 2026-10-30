# Revisão integrada de UI/UX — 30/10/2026

Revisão posterior ao perfil/configurações aprovado (`06a0407`), na branch `Design-system`. Cobre a integração dos módulos já migrados para o Fluxo Claro. Não retoma a homologação operacional adiada pelo usuário.

## Ambiente e escopo

PHP 8.3.32, imagem MariaDB 11.8.8, Mailpit v1.30.0 e Chromium 155.0.8059.84 em containers descartáveis. Migrations 001–008 aplicadas do zero nesse banco; nenhuma migration nova ou mudança do `.env`. Preflight do ambiente local de uso diário somente em leitura.

A regressão PHP completa continua cobrindo autenticação, SMTP, autorização/tenant, transações/concorrência, auditoria, revogação e desempenho. Esta revisão acrescenta uma verificação integrada de apresentação/navegação; não substitui esses testes de negócio nem repete todos os testes de mutação de cada módulo.

| Verificação integrada | Cobertura |
|---|---|
| Rotas autenticadas | Dashboard, projetos, criar projeto, detalhes, editar, participantes, quadro, Minhas Tarefas, histórico pessoal, histórico do projeto e configurações |
| Larguras | 320, 390, 768, 1024 e 1440px; navegação inferior/sidebar conforme a faixa |
| Estrutura | Um main/h1, idioma pt-BR, IDs únicos e ausência de overflow horizontal da página; o quadro conserva sua rolagem própria |
| Axe A/AA | Páginas completas a 390/1440px e estados Mais aberto, tarefa aberta e segurança expandida |
| Preferências de texto | Fonte raiz a 200% e, separadamente, line-height 1,5, letras 0,12em, palavras 0,16em e parágrafos 2em, nas 11 rotas a 390px |
| Teclado/jornada | Pular navegação, Mais/Escape/retorno de foco, tarefa atribuída → lista pessoal → painel → histórico do projeto |
| Movimento reduzido | Navegação entre colunas respeitando a preferência do navegador |
| Sem JS | Consulta das 11 rotas; forms/disclosures mantêm suas alternativas já testadas por módulo; detalhes dinâmicos do quadro continuam dependendo de JS |
| Assets/CSP | Respostas dos assets locais e console acompanhados durante a revisão |
| Acesso | Suite própria de login, cadastro, recuperação/reset, confirmação/reenvio, teclado, SMTP, erros, toggles e fallback sem JS |

O teste de preferências aplica estilos apenas no navegador de testes, simulando ajustes do usuário. Não introduz CSS/scripts inline na aplicação. A fonte raiz a 200% e a viewport estreita são verificações específicas de reflow; não equivalem a ensaio manual de todos os níveis de zoom em todos os navegadores.

## Execução reproduzível

Configure um ambiente/banco e Mailpit descartáveis como em [KANBAN.md](KANBAN.md). Aplique as migrations e mantenha o servidor HTTP apontando para esse mesmo banco. Instale Chromium, `playwright-core` e `@axe-core/playwright` fora das dependências de runtime da aplicação.

```sh
AUTH_TEST_DATABASE=disposable php tests/run.php
NODE_PATH=/caminho/das/ferramentas/node_modules \
AUTH_TEST_DATABASE=disposable \
AUTH_TEST_URL=http://127.0.0.1:8000 \
MAILPIT_TEST_URL=http://127.0.0.1:8025 \
node tests/ui-integration-browser.cjs

NODE_PATH=/caminho/das/ferramentas/node_modules \
AUTH_TEST_DATABASE=disposable \
AUTH_TEST_URL=http://127.0.0.1:8000 \
MAILPIT_TEST_URL=http://127.0.0.1:8025 \
node tests/auth-ui-browser.cjs
```

Execute sequencialmente: as suítes PHP criam triggers/fixtures e não podem disputar o banco com mutações do navegador. O teste integrado reutiliza `board-browser.cjs`, faz cadastro/confirmação reais no Mailpit e cria seu projeto/tarefas; não depende dos JSON de fixtures de outros módulos. `CHROMIUM_PATH` e `UI_PREVIEW_DIR` são opcionais como nos demais testes. Limites de autenticação podem exigir expiração das entradas de teste antes de repetir muitas suítes na mesma instância; nunca faça esse preparo em banco de uso diário.

## Evidências e limites

O teste integrado gera `storage/previews/ui-integration.json` somente ao passar: rotas, larguras, preferências, auditorias e erros de assets/CSP, sem cookies, senhas ou tokens. Os artefatos ficam fora do Git. A execução desta revisão produziu **77 cenários estruturais e 25 auditorias axe integradas**, sem violações nesses cenários e sem erro de asset/CSP registrado. A suíte de acesso também passou, com 17 auditorias axe adicionais, cadastro/confirmação/reset por Mailpit, erros, teclado e uso sem JS. Não houve correção de runtime necessária nos cenários testados. Revisão concluída e aprovada para commit do teste e deste registro.

Previews dos incrementos anteriores foram preservados em storage/previews. O teste integrado também captura a segurança expandida em `ui-review-settings-390.png`. Esses arquivos usam dados fictícios e não são um ambiente publicado.

Não há certificação de acessibilidade nem teste em aparelhos físicos, leitor de tela, teclado virtual, Safari/Firefox ou infraestrutura real de homologação/produção. Avaliação manual assistiva e homologação operacional continuam pendentes. A revisão não adiciona funções, altera permissões ou publica o sistema.

Registro aprovado pelo usuário, com instrução de criar o commit e aguardar novas orientações. A aprovação desta revisão não revoga o adiamento explícito da homologação operacional.


## Ajuste após relato do usuário — card Pendentes

O usuário identificou que o número sumia ao passar o mouse pelo indicador Pendentes. A revisão inicial auditava o dashboard em repouso; não exercitava hover persistente desse card. A regra `.dashboard a:hover` tinha especificidade maior que a cor base do card destacado e fazia o número herdar `#123e34`, também usado no fundo do hover.

A regra específica `.dashboard .dash-metric-featured:hover` agora preserva `color: #fff`. Alteração apenas de CSS, sem HTML/JS novo, consultas, migrations ou mudança do .env. O teste dashboard-browser.cjs foi ampliado para hover/foco nas cinco larguras, contagem positiva/zero e axe com o card em hover. A nova asserção reproduziu a falha antes da correção (`rgb(18, 62, 52)` em vez de branco). Após a correção, dashboard-browser.cjs passou em 320/390/768/1024/1440px, incluindo hover/foco, valores positivo/zero, axe em hover, filtros, links e uso sem JS. Preview dashboard-hover-1440.png inspecionado visualmente, fora do Git; o registro e a correção foram aprovados e incluídos no commit `fix: preserva contraste do dashboard e registra revisão integrada` (hash no Git). Desenvolvimento pausado, aguardando instruções.
