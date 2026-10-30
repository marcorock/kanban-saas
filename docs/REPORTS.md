# Dashboard, tarefas e históricos

A navegação principal inclui **Dashboard**, **Projetos**, **Minhas Tarefas** e **Meu Histórico**. Todas essas páginas exigem sessão válida e e-mail confirmado.

## Dashboard

- **Projetos ativos:** todos os projetos não arquivados/excluídos que o usuário pode consultar, próprios ou compartilhados.
- **Minhas tarefas pendentes/concluídas/atrasadas:** somente tarefas atribuídas ao usuário em projetos ativos acessíveis.
- **Minhas tarefas iniciadas:** pendentes com início real preenchido; não depende do nome da coluna. Esse indicador pode se sobrepor a pendentes/atrasadas.
- **Progresso:** percentual das tarefas concluídas entre todas as tarefas de cada projeto ativo acessível, inclusive tarefas sem responsável. Projeto sem tarefas tem 0%. Há dez projetos por página.
- **Prazos próximos:** até cinco tarefas pendentes atribuídas ao usuário, com prazo de hoje até sete dias adiante, inclusive, considerando UTC.
- **Atividades recentes:** dez eventos mais recentes dos projetos acessíveis, incluindo arquivados. Mostram ação, autor e contexto; os detalhes ficam no histórico de cada projeto.

As consultas do dashboard usam uma transação de leitura para manter as contagens consistentes. Não há gráficos complexos ou atualizações em tempo real.

## UI do dashboard — Fluxo Claro

O terceiro incremento visual mantém as consultas e regras acima. Quatro indicadores pessoais levam aos filtros de pendentes, iniciadas, concluídas e atrasadas. O total de projetos ativos aparece junto de Projetos em movimento; não é uma contagem de projetos criados somente pelo usuário.

No celular/tablet, prazos vêm antes dos projetos e atividades, tanto no DOM quanto na apresentação. Em desktop amplo, prazos e progresso ficam lado a lado. Prazos mantêm links diretos ao painel da tarefa e contexto do projeto. Atividades mostram ações de todos os participantes acessíveis; o atalho Meu histórico leva às ações próprias, sem se apresentar como histórico completo dos projetos.

Progresso usa elemento HTML progress, com valor/limite e nome acessível, funcionando sem JavaScript. A cor e a aparência ficam em dashboard.css, sem style inline. Projeto sem tarefas tem 0%, explicitado como pronto para começar. Nenhum dado é simulado e não há gráfico derivado de contagens que se sobrepõem; iniciadas e atrasadas também contam entre pendentes.

Estado vazio oferece ação de criar projeto e explica ausência de prazos/atividades. A saudação usa a primeira palavra do nome; todos os nomes/títulos permanecem escapados por Twig. O banner de convite continua dependendo do indicador de sessão existente; a elegibilidade é conferida ao abrir/aceitar o convite.

## Minhas Tarefas

`/tasks` consolida atribuições do usuário em todos os projetos acessíveis. O padrão mostra pendentes em projetos ativos; filtros permitem projeto, prioridade, estado (pendentes/iniciadas/concluídas/todas), prazo, intervalo de vencimento e projetos ativos/arquivados/todos.

O intervalo de datas filtra o prazo previsto, não a criação da tarefa. A ordenação prioriza prazos definidos mais próximos, prioridade e identificador; são 25 itens por página. Tarefas atribuídas também a outros usuários aparecem uma única vez. Criar uma tarefa sem atribuí-la ao próprio usuário não a inclui nesta visão.

Cada link abre o quadro com o painel da tarefa correta. Projetos arquivados continuam em modo de leitura. Projetos excluídos ou sem participação atual não aparecem.

## UI de Minhas Tarefas — Fluxo Claro

A lista exibe prioridade, estado textual, prazo, projeto e coluna. Ícones são informativos; não há checkbox de conclusão, que continua ocorrendo pelas colunas do Kanban. O título leva ao painel da tarefa correta; o nome do projeto leva aos seus detalhes.

A navegação **Todas / Pendentes / Iniciadas / Concluídas** usa links GET, preserva os demais critérios e reinicia na primeira página. Iniciadas continuam sendo pendentes com início real, independentemente do nome da coluna. Não há contagens fictícias nos links: o total exibido corresponde à seleção inteira, não apenas aos itens da página.

Filtros de projeto, prioridade, prazo, arquivo e datas ficam em details/summary nativo. Abrem quando há critérios adicionais ativos; o estado fica visível na navegação. Envio GET, limpeza e paginação funcionam sem JS. Detalhes da tarefa continuam dependendo de JS no quadro, como antes. Arquivados recebem aviso de consulta; vazio explica atribuições. Erros 422 usam apresentação própria, mantendo o status e a validação existente.

Estrutura em reports/tasks.html.twig/tasks-error.html.twig, CSS em public/assets/css/my-tasks.css, sem JS novo. Uma coluna de resultados em todos os tamanhos; ações/títulos legíveis no celular. Filtros passam a duas colunas a partir de 640px e três em 1200px. Não houve alteração de SQL, autorização, limites ou ordenação.

## Históricos

- **Meu Histórico** (`/history`): somente ações realizadas pelo usuário em projetos aos quais ele ainda tem acesso. Pode incluir projetos arquivados.
- **Histórico do projeto** (`/projects/{id}/history`): ações de todos os participantes daquele projeto. Acesso pelo projeto ou quadro.
- Filtros: projeto (na visão pessoal), tipo de ação e período UTC inclusivo. São 25 eventos por página, dos mais recentes para os mais antigos, com ID como desempate.
- **Ver alterações** apresenta valores anteriores/novos com nomes de participantes e colunas. Colunas removidas recebem essa indicação. Comentários, títulos e descrições aparecem como texto simples, escapado pelo Twig.
- E-mails e detalhes de convites permanecem restritos ao proprietário e administradores atuais, inclusive nas ações que o próprio usuário realizou quando possuía outro papel. Os demais veem somente ação, autor e contexto.

Valores de criação/exclusão usam “Não definido” no lado ausente. A linha do tempo de uma tarefa continua disponível no painel lateral. Eventos de projetos excluídos permanecem no banco para auditoria, mas não aparecem nestas consultas porque o projeto não pode mais ser acessado.

## Isolamento e implementação

ReportingService exige vínculo atual tanto em tenant_users quanto em project_members em todas as consultas, com igualdade do tenant e projeto nas junções de tarefas, atribuições e eventos. Filtros de projeto revalidam acesso no backend e retornam 404 para projetos alheios. IDs de usuário ou autor enviados por query string não mudam a identidade da consulta.

ActivityPresenter converte apenas campos conhecidos dos snapshots em valores legíveis; não expõe JSON bruto, tokens, hashes ou campos futuros desconhecidos. Nomes são carregados em lote, evitando consultas por evento.

ReportFilters valida tipos, opções, datas reais, intervalo e paginação. Filtros inválidos retornam 422 com uma mensagem e opção de limpar. Filtros e paginação usam GET e preservam as seleções; nenhuma dessas rotas altera dados.

A migration 008 acrescenta índices de período por projeto/tenant e por autor em activity_logs. Não há nova dependência ou variável de ambiente.

## Instalação e testes

Execute `php bin/migrate.php`. Em ambiente **descartável**, conforme AUTHENTICATION.md e KANBAN.md:

```sh
AUTH_TEST_DATABASE=disposable php tests/reports.php
```

O teste verifica contagens e progresso sem duplicação, projetos vazios/arquivados/excluídos, acesso entre tenants, ações próprias versus de outros participantes, privacidade de convites, limites de período UTC, XSS, filtros inválidos, paginação e revogação de acesso/sessão.

Com Chromium e playwright-core no ambiente externo de testes já documentado, execute `node tests/reports-browser.cjs` com as mesmas variáveis. Ele reutiliza a regressão do Kanban e testa navegação, filtros, métricas, snapshots escapados, abertura direta do painel e layout móvel.

Próxima pendência funcional: perfil e configurações pessoais. Depois: revisão final de segurança/desempenho e preparação para produção.


Para validar o incremento de Minhas Tarefas, execute `AUTH_TEST_DATABASE=disposable php tests/my-tasks-ui.php` no ambiente descartável e depois `node tests/my-tasks-browser.cjs`, com as variáveis e ferramentas Chromium/playwright-core/@axe-core/playwright descritas em KANBAN.md. A fixture inclui atribuições próprias/alheias, conta vazia, viewer, projeto privado/arquivado, paginação, prazo e título longo com HTML literal. Previews my-tasks-390.png/my-tasks-1440.png e filtros/vazio em storage/previews, fora do Git.
