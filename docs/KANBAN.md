# Quadro Kanban

Abra **Projeto → Abrir quadro Kanban**. Projetos novos e projetos existentes não excluídos recebem cinco colunas iniciais. Nomes, ordem e indicação de conclusão são personalizáveis.

- Proprietário e administrador criam, renomeiam, ordenam e excluem colunas vazias. Deve permanecer pelo menos uma coluna.
- Proprietário, administrador e membro criam tarefas, editam títulos e movimentam cards entre colunas ou dentro da mesma coluna.
- Visualizador tem somente leitura. Quadros de projetos arquivados também ficam somente para consulta.
- Arraste o controle de uma coluna ou card para ordenar/mover. Os formulários em **Configurar coluna** e **Editar título / mover** oferecem alternativas ao arrastar.
- A coluna **Conclui tarefas** define conclusão, independentemente de seu nome. Alterar essa opção também atualiza as tarefas existentes nela.
- Entrar em uma coluna de conclusão registra a data real; mover entre duas colunas de conclusão preserva a data; sair reabre a tarefa. O histórico mantém os eventos anteriores.

## Interface Fluxo Claro

O quadro usa colunas de 85vw no celular (até 575px) e 288px nas demais larguras, com rolagem horizontal própria. Os botões **Coluna anterior** e **Próxima coluna** permitem navegar sem arrastar; movimento reduzido é respeitado. O restante da página continua com rolagem vertical normal.

**Filtrar tarefas** recolhe os seletores de prioridade, responsável, coluna e prazo; mostra a quantidade de filtros ativos e oferece **Limpar filtros**. Com filtros ativos, a contagem mostra visíveis de total e o arrastar fica suspenso; os formulários de movimentação continuam disponíveis. Prioridade, atraso e conclusão são comunicados por texto, além das cores.

Criação de coluna e configurações ficam recolhidas. Campos têm texto de 16px e ações principais têm alvo mínimo de 44px. Após gravações, foco retorna ao formulário correspondente e a posição horizontal é preservada. Os detalhes abrem no painel Fluxo Claro, com tela cheia no celular e apresentação lateral nas demais larguras; veja [TASKS.md](TASKS.md).

Estrutura está em `resources/views/board.html.twig` e `kanban/templates.html.twig`; apresentação em `public/assets/css/board.css`; comportamento em `public/assets/js/board.js`. JavaScript clona templates HTML nativos e preenche `textContent`/valores, sem inserir HTML vindo dos usuários. O estado JSON inerte existente não é código executável. Sortable/Bootstrap mantêm seus estilos de posicionamento em tempo de execução; não há CSS ou scripts executáveis inline escritos para a nova interface. Sem JS, o snapshot do servidor permite consultar títulos/colunas; controles dependentes de JS permanecem ocultos.

## Persistência e concorrência

Alterações são gravadas por Fetch em JSON. Enquanto a gravação está em andamento, controles de escrita ficam desabilitados. Ao receber erro, o navegador reconstrói a última versão confirmada, restaurando posições. Em falha de conexão a gravação pode ter ocorrido no servidor: use **Atualizar quadro** para conferir antes de repetir.

Cada alteração exige CSRF, autenticação válida, e-mail confirmado e vínculos no tenant/projeto. O backend bloqueia a linha do projeto, revalida autorização e compara `board_version`. Uma versão desatualizada recebe HTTP 409 sem alterar dados; atualize a página antes de continuar. Os quadros não recebem atualizações em tempo real neste incremento.

Ordenação, conclusão/reabertura, incremento de versão e auditoria são atômicos. Cada tarefa usa FK composta para a coluna do mesmo tenant/projeto. Eventos de tarefa também têm FK composta em activity_logs. Exclusão de coluna com tarefas é bloqueada tanto no serviço quanto por FK.

## Endpoint interno

`GET /projects/{id}/board` exibe o quadro. `POST /projects/{id}/board` recebe um objeto JSON contendo `_token`, `version` inteira, `action` e os campos da ação:

| action | Campos |
|---|---|
| column.create | name (1–80 caracteres), is_done (booleano) |
| column.update | column_id, name, is_done |
| column.delete | column_id |
| columns.reorder | column_ids (lista inteira, única e completa do quadro) |
| task.create | column_id, title (1–200 caracteres) |
| task.rename | task_id, title |
| task.move | task_id, column_id, position (inteira, base zero) |

IDs são inteiros positivos; posições são contíguas por coluna. O servidor ignora campos de tenant, projeto, autor ou conclusão enviados pelo cliente e usa seu próprio contexto. Respostas bem-sucedidas contêm `{ok: true, board: ...}` com a versão e o estado persistido; erros contêm `{ok: false, message: ...}`. Códigos relevantes: 401 sessão inválida, 403 permissão, 404 escopo inválido, 409 conflito/estado incompatível, 419 CSRF, 422 validação. O corpo JSON tem limite de 64 KiB. A validação ocorre também antes do roteamento para contornar o tratamento de JSON inválido no Pecee 5.

O snapshot de cada tarefa inclui `subtask_total` e `subtask_done` como inteiros, agregados de todas as listas com escopo de tenant/projeto. Card e painel derivam o percentual; sem itens o card omite o resumo. O checklist não modifica a conclusão por coluna.

## Instalação e dependência

Execute `php bin/migrate.php` para aplicar 006. A migration inicializa colunas nos projetos não excluídos existentes; projetos futuros recebem as colunas na transação de criação. A criação padrão é registrada como `board.initialized`.

[SortableJS](https://github.com/SortableJS/Sortable) 1.15.7 é servido localmente em `public/assets/vendor/sortablejs/`; versão, integridade SHA-512 do pacote e licença MIT acompanham o ativo. Não há CDN, framework frontend ou etapa de build. Preserve a licença ao atualizar.

## Testes

No mesmo ambiente descartável descrito em AUTHENTICATION.md:

```sh
AUTH_TEST_DATABASE=disposable php tests/board.php
```

O teste cobre ordenação, conclusão/reabertura, papéis, tenant/projeto, CSRF, validação, FKs, conflito entre dois processos, rollback e auditoria.

O teste de navegador requer Chromium e `playwright-core` instalados em ambiente de testes externo ao projeto. Por exemplo, instale a ferramenta em `/tmp/kanban-browser-tests` e execute:

```sh
NODE_PATH=/tmp/kanban-browser-tests/node_modules \
CHROMIUM_PATH=/usr/bin/chromium-browser \
AUTH_TEST_DATABASE=disposable \
AUTH_TEST_URL=http://127.0.0.1:8000 \
MAILPIT_TEST_URL=http://127.0.0.1:8025 \
node tests/board-browser.cjs
```

Ele usa cadastro real no ambiente descartável e Mailpit para confirmar a conta, cria um projeto, arrasta cards e verifica restauração visual, conflitos, persistência e layout móvel. Não execute testes no banco de uso diário ou produção.

O teste visual `tests/board-ui-browser.cjs` requer também `@axe-core/playwright` e a fixture de `tests/members-ui.php` (ou runner PHP completo) no mesmo banco descartável. Reutiliza a regressão funcional do quadro e verifica 320–1440px, axe A/AA, foco/rolagem, filtros, papéis, arquivados e consulta sem JS. Previews em `storage/previews/kanban-390.png` e `kanban-1440.png`, ignorados no Git.

## Próximo incremento

Detalhes, responsáveis, datas, checklists e comentários já estão disponíveis conforme [TASKS.md](TASKS.md). Quadro e painel de detalhes já usam o Fluxo Claro. Próximo incremento de UI/UX: Minhas Tarefas. Homologação operacional permanece adiada.
