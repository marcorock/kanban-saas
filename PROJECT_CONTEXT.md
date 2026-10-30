# Contexto do projeto — Kanban SaaS

## Referências e regras

- Regras permanentes de execução e aprovação: [AGENTS.md](AGENTS.md).
- Requisitos completos preservados: [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md).
- Histórico dos commits aprovados: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).
- Aprovação de incremento autoriza seu commit e início automático do próximo. Pedidos explícitos para não seguir/parar/interromper suspendem o avanço; proibição de commit também deve ser respeitada.
- Mudanças atuais ainda não aprovadas não entram no commit anterior. Não inferir autorização para push, merge, deploy em produção ou operações destrutivas.

## Arquitetura e ambiente

- PHP ^8.3 (mesmo requisito de produção), Composer/PSR-4, Pecee 5, Twig 3 e Bootstrap 5.3.
- Vanilla JS/Fetch e SortableJS local 1.15.7; sem SPA ou etapa de build.
- MariaDB 11+, PDO nativo, UTC e transações; banco compartilhado com isolamento por tenant e projeto.
- Um tenant pessoal por cadastro. Participar do tenant não concede acesso automático aos projetos.
- Controllers, Services, Repository de projetos e Policy; ActivityLogger compartilhado grava eventos dentro das transações.
- DocumentRoot public/; configuração em config/, bootstrap em bootstrap/, templates em resources/views/.
- Ambiente local usa containers Coruja; PHP não está no PATH do host. Executar PHP no container web em /var/www/projects/kanbansaas.
- Banco local configurado no .env (não versionado). Migrations 001–011 aplicadas; nenhum teste com fixtures foi executado no banco de uso diário.
- Mailpit local: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; interface http://mail.localhost. Produção segue configurável por MAIL_*.
- Branch de trabalho atual: Plush-notification (estado encontrado no fechamento de A; sem troca automática). Não mudar/mesclar branches automaticamente.

## Incrementos aprovados e commitados

- e6b15d7 — estrutura PHP, roteamento, Twig, PDO e migrations iniciais.
- a6ccc86 — cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit.
- eaa70ff — confirmação de e-mail e recuperação de senha por SMTP; tokens SHA-256 de uso único e revogação de sessões.
- 0cb3560 — projetos: CRUD, arquivar/restaurar, exclusão lógica, paginação, policies e auditoria.
- c26cb49 — convites, participantes, papéis, transferência de propriedade e regra permanente de continuidade.
- b39e005 — quadro Kanban, colunas/cards, drag and drop, conclusão e controle de concorrência.
- 7d170fe — detalhes das tarefas, responsáveis, prioridades/datas, checklists, comentários e filtros.
- 75927a9 — dashboard, Minhas Tarefas e históricos consolidados.
- aba013c — perfil, troca de senha e encerramento das demais sessões.
- 58c0e2a — validação de segurança/desempenho e preparação para instalação.
- 2f0fd5c — design system Fluxo Claro e telas de acesso.
- 0460acf — estrutura autenticada: header, menus responsivos e footer.
- 756af0e — dashboard Fluxo Claro.
- ace0d17 — listagem de projetos Fluxo Claro.
- 39f7edc — formulários de criação/edição de projetos.
- 47d88da — detalhes do projeto Fluxo Claro.
- b8f5fdb — participantes, convites e aceite Fluxo Claro.
- 01eb9ea — quadro Kanban Fluxo Claro.
- 46785c3 — painel de tarefas e checklist compacto.
- 61b86a4 — Minhas Tarefas Fluxo Claro.
- 4c5d3bc — históricos pessoal e de projeto Fluxo Claro.
- 06a0407 — perfil e configurações pessoais Fluxo Claro.
- 4486b12 — revisão integrada e correção do hover do dashboard.
- 5abafbc — notificações no sino, worker, preferências e convites internos, com correção operacional local.

## Módulos consolidados

- Autenticação, confirmação/reset SMTP e perfil/sessões: docs/AUTHENTICATION.md.
- Projetos, papéis, convites, transferência e auditoria: docs/PROJECTS.md e docs/MEMBERS.md.
- Kanban transacional com board_version, tarefas, responsáveis, checklists e comentários: docs/KANBAN.md e docs/TASKS.md.
- Dashboard, Minhas Tarefas e históricos com escopo de tenant/projeto: docs/REPORTS.md.
- Fase 6 aprovada: preflight somente leitura, segurança, regressão e roteiro operacional em docs/PRODUCTION.md e docs/VALIDATION.md.
- Fluxo Claro já aplicado a acesso, navegação, dashboard, listagem, formulários, detalhes, equipe, quadro, painel, Minhas Tarefas e históricos. Detalhes da evolução em docs/DESIGN_SYSTEM.md e docs/DEVELOPMENT.md.
- Homologação operacional adiada pelo usuário para trabalhar UI/UX em incrementos pequenos. Sem publicação autorizada. Organizações seguem expansão pendente; sem cobrança no MVP.

## Continuidade recente

- Evolução dos incrementos anteriores e suas validações preservadas em docs/DEVELOPMENT.md, docs/DESIGN_SYSTEM.md e Git; os commits aprovados estão listados acima.
- Pausa após aprovação do Kanban (`01eb9ea`) e retomada em 30/10/2026. Painel revisado conforme preview aprovado, concluído/aprovado em `46785c3`.
- A aprovação de Minhas Tarefas autorizou seu commit (`61b86a4`) e início dos históricos. Homologação operacional permanece adiada.

## Entrega anterior — revisão integrada e correção do hover

- Revisão e correção aprovadas; commit de fechamento: `4486b12` — revisão integrada e correção do hover. A revisão inicial não exercitava hover persistente dos indicadores; correção desse estado concluída e validada após relato do usuário.
- Novo teste ui-integration-browser.cjs: 11 rotas, cinco larguras, axe de páginas completas, texto 200%/espaçamento, teclado/jornada, movimento reduzido, assets/CSP e consulta sem JS. Evidência JSON em storage/previews, fora do Git.
- Regressão PHP completa e preflight local aprovados; integração passou com 77 cenários estruturais e 25 auditorias axe. Suite de acesso aprovada (cadastro/confirmação/reset SMTP, teclado, erros e uso sem JS). Detalhes/limites em docs/UI_REVIEW.md.
- Sem migration, alteração do .env ou publicação. Testes em banco/Mailpit descartáveis; homologação operacional segue adiada.
- Correção atual: hover do card Pendentes herdava a cor genérica do link, igual ao fundo. CSS do card agora preserva branco; teste reproduziu a falha antes da alteração e verifica hover/foco, contagem positiva/zero e axe com hover. Chromium 320–1440px e axe com hover aprovados; preview dashboard-hover-1440.png inspecionado visualmente, fora do Git.
- Pausa anterior encerrada pelo plano aprovado de notificações em dois incrementos. Homologação operacional permanece adiada.

## Incremento anterior — notificações A

- Plano A/B aprovado. A implementa núcleo/sino; entrega e correções aprovadas pelo usuário, com regressão completa de fechamento aprovada (migrations 001–010). B só após aprovação/commit de A e terá ciclo próprio.
- Migration 009: preferências (sino ligado; push/e-mail desligados), outbox com dedup por destinatário/evento/prazo/canal, notifications e push_subscriptions por dispositivo. Estrutura/execução em docs/NOTIFICATIONS.md.
- ActivityLogger grava outbox na transação do evento: nova mensagem para responsáveis, novas atribuições e papel alterado (Membro/Visualizador/Administrador), sempre excluindo o autor.
- bin/notify.php gera resumo diário a partir das 08h de São Paulo e vencido único por tarefa/prazo; worker com reserva/lease, cinco tentativas e revalidação de acesso/atribuição/preferências. Sem rede na requisição. A só entrega sino; push/e-mail podem ter preferências salvas, envio reservado a B.
- Central paginada, contador no header e leitura por POST/CSRF; preferências em Configurações. Twig/CSS/JS externos; consulta/leitura/preferências funcionam sem JS. Conteúdo só de títulos/links, sem corpo, descrição ou e-mails de convite.
- Validação concluída: todas as suítes PHP passaram em banco descartável com migrations 001–009; testes específicos de notificações e integração visual/axe em Chromium passaram (320–1440px). Cobertura inclui concorrência, dedup, fuso/08h, rollback, retries/leases, isolamento/CSRF e preferências. O teste de prazos esvazia os lotes da fila para coexistir com fixtures das demais suítes. Migration 009 aplicada no banco local; bin/check.php aprovado.
- Incremento A aprovado e commitado em 5abafbc, após regressão PHP completa em banco descartável com migrations 001–010. Worker local agendado a cada minuto; operação descrita em docs/NOTIFICATIONS.md. Push e e-mail permanecem preparados apenas em schema/preferências; Incremento B começa após aprovação/commit do A.
- Correção após teste do usuário: fila local tinha comentário/atribuição pendentes porque o worker não estava agendado. Processamento inicial executado; cron do usuário marco instalado a cada minuto via Docker, com log em storage/logs/notifications.log. Nenhum cron de produção alterado.
- Migration 010 adiciona convite no sino e recupera convites ainda pendentes para contas existentes. Aviso/link de aceite disponíveis sem participação prévia no tenant; aceite exige conta correspondente verificada, CSRF e permissões atuais do remetente. Reemissão invalida o aviso anterior; cancelamento/expiração/aceite ocultam o convite. Atribuições continuam notificando; comentários notificam responsáveis, exceto autor.
- Correção validada em banco/Mailpit descartáveis nas suítes notifications, notification-invitations e members. Migration 010 e worker aplicados localmente; primeira execução automática do cron confirmada pelo log, sem falhas e sem pendências. Sem alteração do .env ou publicação.
- Próximo passo autorizado: iniciar B (Web Push/VAPID e SMTP), com validação e aprovação próprias antes do commit.

## Última entrega versionada — notificações B

- A aprovado/commitado; B implementa SMTP/Mailpit e Web Push com minishlink/web-push e Guzzle, sem atualizar versões dos pacotes já presentes. Commit de B autorizado explicitamente pelo usuário, com testes manuais ainda incompletos; hash de fechamento registrado no Git.
- Outbox inclui canais externos habilitados no evento; worker reconfere acesso/preferências, agrupa prazos por destinatário/ciclo, retenta até cinco vezes e reserva por lease. Rede ocorre apenas no worker, sem locks SQL durante envio. Recibos por dispositivo evitam repetir sucessos após falhas parciais; 404/410 removem a assinatura.
- Configurações: três chaves globais e ativação explícita por dispositivo, sem permissão automática; manifest/service worker/JS/CSS externos e CSP worker-src/manifest-src self. Service worker confere sessão/conta/canal antes de exibir; orientação de Tela de Início no iPhone. Endpoint de assinatura exige sessão verificada/CSRF, chaves válidas e serviço de push reconhecido; exclusão limitada à própria conta.
- Migration 011 aplicada localmente. VAPID gerado no .env local sem exibir chaves; par preservado pelo gerador, fora do Git. Cron local já processa canais externos; bin/check.php aprovado. Nenhum cron/deploy de produção alterado.
- Testes afetados e UI/axe 320–1440px passaram; SMTP real no Mailpit, 201/404/410/503 simulados no transporte HTTP da biblioteca real, retries, falha parcial, concorrência, preferências, IDOR e lease. Regressão PHP completa de fechamento aprovada em banco descartável criado com migrations 001–011. Suítes de navegador da central e ativação Push/axe, mais testes do service worker, aprovadas. Cron local com canais externos confirmado no log sem falhas.
- Limitação operacional: entrega em provedor/dispositivo real (inclusive iPhone instalado) ainda precisa de teste real; semântica externa é pelo menos uma vez se houver queda após aceite remoto e antes do registro local. Docs/NOTIFICATIONS.md registra operação e limites.
- Próximo passo: aguardar novas instruções, conforme pedido explícito do usuário. Não iniciar outro incremento. Testes manuais completos e validação operacional de Push em dispositivo real permanecem pendentes.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban e protocolo JSON: docs/KANBAN.md. Detalhes, limites e testes de tarefas: docs/TASKS.md.
- Relatórios, regras de contagem e históricos: docs/REPORTS.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
- Preparação para produção: docs/PRODUCTION.md. Registro de validação: docs/VALIDATION.md.
- UI/UX, componentes e navegação: docs/DESIGN_SYSTEM.md.
- Revisão integrada de UI/UX e acessibilidade: docs/UI_REVIEW.md.

- Notificações A/B e operação do worker: docs/NOTIFICATIONS.md.

## Correção operacional após revisão de B

- Gerador VAPID aplicava chmod 600 ao .env existente (proprietário marco), retirando leitura do www-data e causando PDOException nas rotas autenticadas. CLI como root mascarava a falha.
- Configuração local restaurada para proprietário marco/grupo www-data, modo 640; conteúdo/chaves preservados. Gerador agora mantém permissões/proprietário/grupo existentes e usa umask restrita somente para arquivo novo.
- Teste tests/vapid.php executado como www-data em diretório temporário: geração, preservação de permissões/configuração, idempotência e arquivo novo 600 aprovados. Preflight como www-data aprovado e rotas HTTPS locais revalidadas. Commit de B autorizado pelo usuário; próxima validação deve incluir o usuário real do servidor HTTP.

## Reinício dos testes manuais locais

- Por solicitação explícita do usuário, banco local kanbansaas limpo: 21 tabelas de dados vazias e contadores AUTO_INCREMENT reiniciados em 1; 11 migrations preservadas, sem alteração de schema/configurações/chaves. Cron pausado somente durante a operação e restaurado. Próximo teste começa pelo cadastro de usuários; Commit de B autorizado pelo usuário; continuidade pausada por solicitação explícita.

## Fechamento e pausa solicitada

- Usuário autorizou o commit de B e suas correções mesmo sem concluir todos os testes manuais; aguardar novas instruções após versionar. Validação automática completa anterior e teste final do gerador como www-data aprovados. Nenhum novo incremento, push, merge ou deploy autorizado.
