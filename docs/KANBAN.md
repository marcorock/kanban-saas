# Quadro Kanban

Abra **Projeto → Abrir quadro Kanban**. Projetos novos e projetos existentes não excluídos recebem cinco colunas iniciais. Nomes, ordem e indicação de conclusão são personalizáveis.

- Proprietário e administrador criam, renomeiam, ordenam e excluem colunas vazias. Deve permanecer pelo menos uma coluna.
- Proprietário, administrador e membro criam tarefas, editam títulos e movimentam cards entre colunas ou dentro da mesma coluna.
- Visualizador tem somente leitura. Quadros de projetos arquivados também ficam somente para consulta.
- Arraste o controle de uma coluna ou card para ordenar/mover. Os formulários em **Configurar coluna** e **Editar título / mover** oferecem alternativas ao arrastar.
- A coluna **Conclui tarefas** define conclusão, independentemente de seu nome. Alterar essa opção também atualiza as tarefas existentes nela.
- Entrar em uma coluna de conclusão registra a data real; mover entre duas colunas de conclusão preserva a data; sair reabre a tarefa. O histórico mantém os eventos anteriores.

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

## Próximo incremento

Detalhes de tarefa em modal/offcanvas, descrição, múltiplos responsáveis, prioridades, datas, checklists e comentários serão tratados na Fase 4. Neste incremento os cards possuem título, ordenação e conclusão real.
