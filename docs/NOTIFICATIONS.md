# Notificações

A (sino/núcleo e convites internos) aprovado no commit 5abafbc. B adiciona Web Push e SMTP; commit autorizado pelo usuário com testes manuais ainda incompletos. Continuidade pausada até novas instruções.

## Eventos e privacidade
- Resumo diário de tarefas atribuídas pendentes com prazo hoje, no primeiro ciclo a partir das 08h em America/Sao_Paulo; vencido uma vez por usuário/tarefa/prazo, no primeiro ciclo após vencer.
- Comentário avisa os responsáveis; atribuição avisa os novos responsáveis; mudança de papel avisa o participante. Nunca avisar o autor da própria ação. Texto somente de títulos/links.
- Convites para contas existentes aparecem no sino e permitem aceite interno sem conceder acesso antecipado ao projeto. Cancelamento, expiração, reemissão e aceite invalidam o aviso. O SMTP de convites existente permanece separado; B não duplica esses e-mails.
- Acesso, tenant, projeto, atribuição, validade do evento e preferência são reconferidos antes da entrega. O service worker reconfere a conta atual antes de exibir conteúdo em dispositivo compartilhado.

## Preferências e dispositivo
Três chaves em Configurações: sino ligado, push/e-mail desligados por padrão. Eventos novos entram nos canais externos habilitados naquele momento; desligar antes do envio cancela a entrega pendente. Ativação posterior não reenvia eventos antigos.

Push exige botão por dispositivo; ativar também liga o canal na conta. Desativar um dispositivo preserva os demais; desligar a chave global suspende todos. Até dez dispositivos/conta. HTTPS ou localhost; no iPhone/iPad compatível, Compartilhar → Adicionar à Tela de Início e abrir pelo ícone. Sem solicitação automática de permissão. Sem cache de páginas privadas.

## Operação
`php bin/migrate.php` aplica 009 (núcleo), 010 (convites e recuperação dos pendentes existentes) e 011 (recibos de push por dispositivo).

`php bin/generate-vapid.php` gera o par uma vez no .env, sem exibir chaves ou substituir um par existente. Configure VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY e VAPID_SUBJECT (HTTPS ou mailto de contato), preserve-os entre deploys e mantenha o .env fora do Git. SMTP continua em MAIL_*; local usa Mailpit e produção usa seu servidor SMTP. Composer requer cURL e minishlink/web-push com cliente HTTP Guzzle.

Agende `php bin/notify.php --limit=500` a cada minuto. O worker gera prazos e processa sino/push/e-mail fora das requisições. E-mails de prazos são agregados por destinatário/ciclo (até 100 itens por lote); comentários, atribuições e papéis seguem individuais. Push processa até 20 eventos/destinatário/lote. Excedentes ficam para o próximo ciclo.

No ambiente local, crontab de marco usa Docker coruja-dev-environment-web-1, lote 500 e log storage/logs/notifications.log. Execução automática verificada. Nenhum agendamento de produção foi alterado. O sino consulta a cada 60s com página visível: processamento/contador podem somar cerca de dois minutos.

Outbox transacional com ActivityLogger e dedup por usuário/tipo/tarefa/data/evento/canal. Reservas têm lease de cinco minutos; até cinco tentativas com backoff. Workers externos serializam usuário/canal sem manter locks SQL durante rede. Assinaturas 404/410 são removidas. Recibos impedem reenviar a dispositivos já atendidos após falha parcial. SMTP/push têm semântica de pelo menos uma vez: queda após aceite remoto e antes do registro local ainda pode repetir; tag estabiliza a apresentação do push.

## Validação
Banco e Mailpit descartáveis: notifications, notification-invitations, notifications-channels, segurança/perfil/shell/preflight. notifications-sw.cjs testa sessão/canal, payload e URL; push-browser.cjs cobre clique, persistência e remoção reais na API com provedor/permissão simulados, CSP, manifest e UI/axe 320–1440px. notifications-browser.cjs cobre central e preferências. Gere novamente a fixture notifications-ui.php antes de cada suíte de navegador.

SMTP foi enviado ao Mailpit; respostas push 201/404/410/503 foram simuladas no transporte HTTP da biblioteca real. Entrega em provedor/dispositivo real e iPhone instalado ainda exigem validação operacional. Referências: [biblioteca](https://github.com/web-push-libs/web-push-php), [WebKit](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/).

Fechamento de B: regressão PHP completa aprovada em banco descartável com migrations 001–011, validação local e execução automática do cron com canais externos confirmadas; commit de B autorizado pelo usuário; teste real de Push e demais testes manuais continuam pendentes.

Permissões: o gerador preserva o modo/proprietário/grupo do .env existente; arquivo novo nasce 600 e exige leitura configurada para o usuário HTTP antes do uso. Local: .env de marco/grupo www-data, modo 640, verificado com bin/check.php executado como www-data. Teste tests/vapid.php usa apenas diretórios temporários, inclusive para idempotência e preservação de permissões.
