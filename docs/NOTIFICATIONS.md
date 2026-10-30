# Notificações

## Incremento A

Sino e central em `/notifications`, leitura por POST/CSRF e preferências em Configurações. Conta verificada obrigatória; destinatário vem da sessão. Padrão: sino ligado, push/e-mail desligados. As três preferências podem ser salvas; **push/e-mail ainda não enviam no A** e não solicitam permissão ao navegador.

Outbox gravada pelo ActivityLogger na transação do evento. Comentários avisam responsáveis atuais, atribuições avisam somente novos responsáveis e mudanças de papel avisam o destinatário (Membro/Visualizador/Administrador). Ator excluído; edições de comentário não geram novo aviso. Nenhum corpo, descrição ou e-mail de convite é copiado para notificações.

O worker gera prazos no fuso America/Sao_Paulo (armazenamento UTC): resumo único por usuário/dia no primeiro ciclo a partir de 08h; vencido no primeiro ciclo após a data local vencer, uma vez por usuário/tarefa/prazo. Considera somente tarefas atribuídas, pendentes, em projetos ativos com vínculos atuais de tenant/projeto. Resumo agrega projetos, mostra até cinco títulos/links e oferece filtro para todas as tarefas do dia. Avisos automáticos não têm ator; autoatribuição não gera evento de atribuição para o próprio autor.

Chave SHA-256 inclui destinatário/tipo/tarefa/data/canal e origem: prazo estável ou ID do evento, evitando colapsar comentários/reatribuições distintos no mesmo dia. Preferência é reconferida no envio; desligada cancela pendentes, sem entrega retroativa ao religar. Avisos já entregues continuam consultáveis quando ainda há acesso/atribuição; arquivamento, remoção e mudanças de prazo/papel ocultam avisos incompatíveis.

## Execução

Aplicar `php bin/migrate.php` (009, incluindo a estrutura de assinaturas por dispositivo). Executar `php bin/notify.php --limit=100` por cron, por exemplo a cada minuto, no ambiente PHP com o mesmo banco/configuração do app. O horário do cron pode ser UTC: o worker calcula São Paulo. Cada execução gera até 1.000 destinatários/vencimentos elegíveis; ciclos seguintes continuam os restantes. Limite de entrega aceita 1–500 itens. **Nenhum cron de produção é instalado por esta implementação.**

Reserva concorrente com SKIP LOCKED, token e lease de cinco minutos; até cinco tentativas com backoff de 1/2/4/8 minutos, depois falha definitiva. Lease abandonado é recuperado. Preferências e projetos são bloqueados/reconferidos antes de inserir o sino; inserção e conclusão da entrega são atômicas, com chave única por outbox. Erros persistidos são códigos genéricos, sem credenciais. O sino atualiza o contador no carregamento, retorno à aba e a cada minuto visível; consulta/leitura/preferências funcionam sem JS.

## Validação e próximo passo

Somente banco/Mailpit descartáveis: `php tests/notifications.php` com AUTH_TEST_DATABASE=disposable cobre atomicidade, cinco eventos, fuso, deduplicação, concorrência, leases/limites, acesso/tenant, preferências, CSRF/IDOR e conteúdo mínimo. `tests/notifications-ui.php` prepara `tests/notifications-browser.cjs` (Chromium/playwright-core/axe externos); os demais testes afetados são executados antes da regressão completa de fechamento. Fixtures/previews ficam fora do Git.

Incremento B: minishlink/web-push/VAPID, ativação por botão, service worker/manifest externos e CSP mínima; iPhone com Adicionar à Tela de Início; remover assinatura 404/410. SMTP/templates existentes, prazos agregados por ciclo. Push/SMTP terão entrega externa com retentativas e possível repetição após falha entre envio e confirmação. B somente após aprovação/commit de A, com testes próprios de 410, Mailpit e preferências.

Correção operacional: o ambiente local executa o worker a cada minuto pelo crontab do usuário marco, via container coruja-dev-environment-web-1 (lote de 500). Log em storage/logs/notifications.log; produção exige seu próprio agendamento. Sem worker, os eventos permanecem na outbox e não aparecem no sino.

Migration 010 inclui convites de projeto para contas existentes e recupera os convites pendentes anteriores. O destinatário recebe título/link e aceita pelo sistema, sem token de e-mail exposto e sem acesso ao projeto antes do aceite. Conta verificada correspondente, CSRF, validade do convite e autorização atual do remetente são reconferidos; cancelamento, expiração, reemissão e aceite revogam o aviso anterior. Atribuição de tarefa já notifica o responsável; comentários avisam os responsáveis da tarefa, exceto o autor.

Validação adicional: tests/notification-invitations.php cobre convite entre tenants, privacidade, IDOR, aceite interno, CSRF, reemissão, cancelamento, expiração e canal desligado.

Verificação local: migration 010 aplicada; execução inicial entregou cinco avisos sem falhas. Primeira execução automática do cron confirmada em storage/logs/notifications.log; fila sem pendências. O sino consulta o contador a cada 60 segundos enquanto a página está visível, portanto cron e atualização visual podem somar cerca de dois minutos.
