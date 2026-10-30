# Detalhes das tarefas

Clique no título de um card para abrir o painel lateral. Proprietário, administrador e membro podem editar; visualizadores e projetos arquivados ficam em modo de consulta.

## Interface Fluxo Claro

O painel ocupa a tela abaixo de 640px e a lateral (até 680px) nas demais larguras. O resumo mostra título, coluna atual, prazo, responsáveis e progresso das subtarefas. Abas **Subtarefas** (inicial), **Comentários** e **Detalhes** organizam o conteúdo; planejamento, responsáveis, formulário principal e histórico ficam em Detalhes. Setas, Home e End navegam pelas abas. Trocar de aba preserva os rascunhos.

O progresso soma os itens de todas as listas da tarefa: concluídos/total e percentual arredondado, também no card do Kanban. Não é média dos percentuais das listas. Sem itens, o painel mostra **Sem subtarefas** e omite percentual/barra; o card omite o resumo. Isso não altera a conclusão da tarefa principal, que continua sendo definida pela coluna. O snapshot agrega as contagens no SQL com escopo de tenant/projeto e conserva o número constante de consultas.

Cada subtarefa tem checkbox, título, editar e excluir na mesma linha. O checkbox grava diretamente; falha/cancelamento restaura o estado confirmado. Clicar no título ou lápis abre a edição na própria linha, com confirmar/cancelar; Enter salva e Escape cancela. Editar o título mantém a marcação existente. Textos longos podem quebrar dentro da área de texto, mantendo as ações alinhadas na mesma linha. Adicionar item usa campo/ação compactos; menu da lista concentra renomear/excluir. Exclusões continuam exigindo confirmação.

Fechar ou pressionar Escape solicita confirmação quando existem rascunhos e fica bloqueado durante gravação. O foco retorna ao card. Gravações restauram posição/foco e erros mantêm o texto digitado; falha de leitura remove editor anterior e oferece **Tentar novamente**. Visualizadores/arquivados têm aviso e consulta sem escrita. Comentários e histórico mantêm paginação independente de 20 itens. Horários continuam em UTC.

HTML em `resources/views/task-panel.html.twig` e `tasks/templates.html.twig`, CSS em `public/assets/css/task-panel.css`, JS externo em `public/assets/js/board.js`. Templates recebem texto/valores, sem interpretar HTML dos usuários. O painel depende de JS; sem ele, o quadro continua consultável por snapshot. Bootstrap mantém modalização/foco e posicionamento em runtime.

## Campos e filtros

- Título (até 200 caracteres) e descrição em texto simples (até 16.000).
- Prioridades baixa, normal, alta e urgente.
- Início previsto e prazo como datas; início real como data/hora em UTC. O prazo não pode anteceder o início previsto; início real não pode estar no futuro nem após a conclusão já registrada.
- Múltiplos responsáveis (até 100), todos participantes verificados do mesmo projeto, incluindo visualizadores. Convites ainda não aceitos não são elegíveis.
- Conclusão real continua sendo definida pela coluna de conclusão do Kanban, sem edição direta desse campo no formulário.

Cards exibem prioridade, responsáveis, prazo e indicador de atraso. Uma tarefa pendente fica atrasada quando seu prazo é anterior à data corrente em UTC. Os filtros do quadro combinam responsável, prioridade, coluna e prazo (atrasadas, próximos sete dias ou sem prazo).

Os filtros operam apenas sobre dados que o usuário já pode consultar. Com filtros ativos, o arrastar fica desabilitado para não gravar posições a partir de uma lista incompleta; os formulários de movimentação continuam disponíveis. Limpe os filtros para voltar a arrastar.

## Checklists e comentários

Uma tarefa aceita múltiplas listas (título até 120 caracteres), com itens/subtarefas simples (até 300 caracteres) que podem ser editados, concluídos ou excluídos. Não há hierarquia nem responsáveis próprios por item. O painel mostra a quantidade concluída por lista. Excluir uma lista exige confirmação e remove seus itens, preservando o evento na auditoria.

Comentários (até 5.000 caracteres) podem ser adicionados por participantes com escrita. Somente o autor pode editar; autor, proprietário ou administrador podem excluir. Exclusão é lógica. Comentários e histórico têm paginação independente de 20 registros. A linha do tempo identifica ação, autor e horário UTC; os valores anteriores/novos ficam registrados no banco para a visualização completa de auditoria do próximo módulo.

O painel pede confirmação antes de descartar campos alterados. Erros de gravação mantêm os rascunhos. Ler ou atualizar os detalhes também atualiza o estado e a versão do quadro; há confirmação antes de descartar alterações locais nessa operação.

## Integridade e concorrência

Todos os detalhes e itens são validados pelo tenant, projeto e tarefa, mesmo quando IDs válidos de outro contexto são enviados. A API exige sessão válida e e-mail confirmado. Alterações passam pelo bloqueio e controle de versão do BoardService, na mesma transação que a auditoria.

Remover um participante retira suas atribuições às tarefas, registra os responsáveis anteriores/novos e incrementa a versão do quadro, sem afetar outros projetos. Inclusões, mudanças de papel e transferências também atualizam a versão. Uma tela desatualizada recebe 409 ao tentar salvar; responsáveis removidos são rejeitados mesmo após atualizar a tela. FKs compostas reforçam esse isolamento.

## API interna

`GET /projects/{id}/tasks/{taskId}` retorna `{ok, board, details}`. O estado do quadro e os detalhes são lidos em uma única transação. Parâmetros opcionais: `comments_page` e `activity_page`.

Escrita usa o mesmo `POST /projects/{id}/board` JSON já documentado em [KANBAN.md](KANBAN.md), com `_token`, `version`, `action` e `task_id`:

| action | Campos adicionais |
|---|---|
| task.update | title, description, priority, starts_on, started_at, due_on, assignee_ids |
| checklist.create | title |
| checklist.update | checklist_id, title |
| checklist.delete | checklist_id, confirm=true |
| item.create | checklist_id, title |
| item.update | item_id, title, is_done (booleano) |
| item.delete | item_id, confirm=true |
| comment.create | body |
| comment.update | comment_id, body |
| comment.delete | comment_id, confirm=true |

`task.update` grava o formulário completo; datas vazias/nulas limpam o campo. Datas previstas usam `YYYY-MM-DD`; início real usa `YYYY-MM-DD HH:MM:SS` UTC. IDs devem ser inteiros; assignee_ids é uma lista de IDs únicos. Campos de autoridade, tenant e conclusão enviados pelo cliente são ignorados. O limite global de JSON permanece em 64 KiB.

## Instalação e testes

Aplique `php bin/migrate.php` (migration 007). Bootstrap JS é servido localmente a partir da dependência Composer já existente, com licença em `public/assets/vendor/bootstrap/`. Não há nova dependência de runtime.

Em ambiente descartável configurado conforme AUTHENTICATION.md e KANBAN.md:

```sh
AUTH_TEST_DATABASE=disposable php tests/tasks.php
```

Os testes cobrem datas, responsáveis inválidos, autoria de comentários, IDOR entre tarefas/projetos, leitura sem confirmação, paginação, auditoria, rollback e limpeza das atribuições na remoção de membro.

Para o navegador, use Chromium e playwright-core no ambiente externo de testes descrito em KANBAN.md, executando `node tests/tasks-browser.cjs` com as mesmas variáveis. Ele reutiliza os testes do Kanban e acrescenta painel, responsáveis, datas, checklists, comentários, conteúdo HTML literal, preservação de rascunho e filtros no desktop/mobile.

Dashboard, Minhas Tarefas e históricos estão disponíveis conforme [REPORTS.md](REPORTS.md). Links com `/projects/{id}/board?task={taskId}` abrem o painel da tarefa; o backend revalida seu escopo.


O teste `tests/task-panel-ui-browser.cjs` requer também `@axe-core/playwright` e a fixture descartável de `tests/members-ui.php` (ou runner PHP completo). Reutiliza a regressão de tarefas e acrescenta validação visual/acessibilidade, alinhamento das ações, edição/cancelamento inline, marcação direta, agregação 3/5 e 60%, falhas/rascunhos, navegação de abas, foco/Escape, retry, exclusão, paginação, quatro papéis e arquivados. Muda o projeto ativo da fixture para arquivado ao final; recrie a fixture antes de outros testes que precisem dele ativo. Previews em storage/previews/task-panel-390.png/task-panel-1440.png e task-checklists-*.png, ignorados no Git.
