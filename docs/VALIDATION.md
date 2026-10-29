# Registro de validação — 29/10/2026

Incremento da Fase 6, posterior ao perfil aprovado (`aba013c`). Alterações desta fase aprovadas pelo usuário e incluídas no commit `chore: prepara validação e instalação em produção`. Branch real: `perfil-config`; nenhuma troca de branch, push ou publicação foi realizada.

## Ambiente e verificações

PHP 8.3.32, MariaDB 11.8.8, Mailpit e Chromium em containers descartáveis, sem fixtures no banco local de uso diário. O check local foi executado somente em leitura. Nenhuma migration nova ou alteração no `.env` local.

| Verificação | Resultado |
|---|---|
| Composer validate | Válido; aviso preexistente de licença não definida |
| Composer check-platform-reqs | Aprovado; extensões da aplicação agora explícitas no manifesto |
| Composer audit --locked | Nenhum aviso de vulnerabilidade conhecido retornado na execução |
| Sintaxe PHP e git diff --check | Aprovados |
| Migrations 001–008 | Aplicação do zero e reexecução idempotente aprovadas em banco descartável |
| Sessões e autenticação | Cookies, expiração, CSRF, validação, rate limit, rollback e tenant aprovados |
| Conta/SMTP | Confirmação/reset, Mailpit, expiração, uso único, concorrência e logs seguros aprovados |
| Projetos e membros | Isolamento, papéis, convites, remoção, transferência, transações e concorrência aprovados |
| Kanban e tarefas | Ordenação, permissões, versão concorrente, detalhes, responsáveis, comentários e rollback aprovados |
| Relatórios | Métricas, filtros, paginação, privacidade, XSS e revogação de acesso aprovados |
| Perfil | Nome, senha atual, confirmação, CSRF, campos protegidos, tokens, revogação e concorrência aprovados |
| Cabeçalhos HTTP | CSP, frames, privacidade, no-store, erros, arquivos privados e CSRF aprovados |
| Política de produção | HSTS/cookie Secure em instância descartável com APP_URL HTTPS; não testa TLS externo |
| Preflight CLI | Configuração local/produção, códigos de saída, migration pendente e usuário SQL somente leitura aprovados |
| Chromium: perfil | Nome escapado, CSP bloqueando inline, troca de senha, duas sessões e mobile aprovados |
| Chromium: tarefas/relatórios | Regressão de Kanban, filtros, offcanvas, rascunhos, XSS, navegação e mobile aprovada |

As suítes PHP são executadas sequencialmente pelo `tests/run.php`, pois fixtures e triggers temporários compartilham o banco descartável. As suítes novas/alteradas também foram verificadas após os ajustes finais. O guia de reprodução está em [PRODUCTION.md](PRODUCTION.md).

## Desempenho observado

O snapshot do quadro carregava `tasks.*`, incluindo descrições de até 16.000 caracteres, embora o card não as exiba. A consulta agora seleciona somente os campos do card; o painel continua buscando a descrição completa quando solicitado.

Carga sintética com um projeto, um responsável por tarefa e descrições de 16.000 caracteres:

| Tarefas | Consultas para snapshot | Tempo observado | Pico adicional de memória |
|---|---|---|---|
| 100 | 5 | 35,63 ms | 0,42 MiB |
| 1.000 | 5 | 13,66 ms | 1,64 MiB |

São amostras únicas no ambiente local, sujeitas a aquecimento/cache; não demonstram redução de latência proporcional nem definem SLA. O teste verifica ausência de N+1, consumo de memória, paginação de tarefas/histórico, métricas de 1.000 pendências e disponibilidade do índice de ordenação no EXPLAIN.

## Limitações e próximo passo

- Não houve acesso, deploy ou alteração em produção.
- O preflight valida configuração e registros de migrations; não é uma validação completa de schema, servidor web, TLS, grants ou entrega SMTP.
- A revisão usa leitura de código e regressão automatizada; não substitui avaliação independente de segurança.
- Não houve ensaio de carga HTTP concorrente, teste de restauração de backup, failover ou validação do servidor real.
- O quadro ainda carrega todos os cards e serializa escritas por projeto; contagens e seletores de relatórios crescem com o volume.
- A aplicação mantém o envio SMTP síncrono; filas e virtualização de grandes quadros são evoluções futuras.
- Gestão de organizações permanece uma expansão pendente; não há cobrança no MVP.

A homologação operacional foi adiada a pedido do usuário. O usuário definiu a evolução de UI/UX como prioridade anterior à homologação, começando pelos fluxos de acesso. A implementação e validação deste incremento visual estão registradas em [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md); o roteiro de [produção](PRODUCTION.md) permanece pendente de execução operacional. Push, merge e deploy continuam dependendo de autorização explícita.
