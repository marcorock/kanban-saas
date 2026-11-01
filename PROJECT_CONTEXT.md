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
- Banco local configurado no .env (não versionado). Migrations 001–015 aplicadas; nenhum teste com fixtures foi executado no banco de uso diário.
- Mailpit local: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; interface http://mail.localhost. Produção segue configurável por MAIL_*.
- Branch de trabalho atual: post-its (estado encontrado ao implementar Brainstorm; sem troca automática). Não mudar/mesclar branches automaticamente.

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

## Trabalho atual — Notas Rápidas (Brainstorm)

- Retomada autorizada pelo pedido de implementação. Último commit de notificações: 0d25219 (B e correções), autorizado mesmo com testes manuais incompletos; validação real de Push permanece pendente. Incremento Brainstorm e revisão aprovados pelo usuário em 31/10/2026; commit de fechamento autorizado, com hash registrado pelo Git.
- Captura global em todas as páginas autenticadas: header desktop junto ao sino e bottom bar mobile, atalhos Ctrl+K (também Cmd+K) ou N fora de campos editáveis/outros diálogos. Modal Post-it com foco automático, texto de até 5.000 caracteres, projetos ativos acessíveis (inclusive visualizador) e padrão Inbox pessoal. Enter salva, Shift+Enter quebra linha; erro preserva rascunho e envio em curso bloqueia duplicação/fechamento acidental. Formulário /notes/new funciona sem JS.
- Minhas Tarefas: Brainstorm abaixo da listagem, somente notas pessoais sem projeto. Detalhes do projeto: seção entre Sobre e Gerenciar, somente notas explicitamente vinculadas ao projeto. Notas são privadas do autor em ambos os lugares, com grid responsivo, 20 por página e exclusão. Arquivados mantêm consulta/exclusão, sem criação/conversão.
- Conversão de Inbox escolhe projeto gravável e coluna; nota vinculada só converte naquele projeto. Visualizador pode guardar nota privada, mas não criar tarefa. Conversão atribui tarefa ao autor, gera título de até 200 caracteres e mantém texto integral na descrição; remove nota, grava auditoria/atribuição e incrementa board_version na mesma transação. Locks de projeto/nota impedem conversão duplicada e preservam ordenação/concorrência do Kanban.
- Migration 012 quick_notes com FKs de tenant/autor e tenant/projeto, validada em banco descartável e aplicada localmente sem limpar dados existentes. API com autenticação/e-mail confirmado, CSRF, validação de IDs/texto, PDO parametrizado, revalidação de acesso/papel/coluna e escape de texto. Twig/CSS/JS externos no padrão Fluxo Claro.
- Testes notes.php: privacidade entre usuários/tenants, projetos/colunas forjados, papéis, arquivamento, revogação, CSRF, validação, rollback, duas conversões concorrentes, versão do quadro e paginação aprovados. notes-browser.cjs: 320/390/768/1024/1440px, axe, posição das seções, foco/Escape, teclado, Enter/Shift+Enter, erro/rascunho/envio único, conversão/exclusão e fallback sem JS aprovados. Suítes afetadas de quadro/tarefas/relatórios/shell/segurança e preflight como www-data aprovados. Regressão PHP completa de fechamento aprovada em banco descartável novo com migrations 001–012; sem fixtures no banco local.
- Alteração preexistente em .env.example encontrada ao iniciar: preservada e fora do escopo deste incremento; não incluir no commit do Brainstorm. Nenhuma alteração de credenciais, deploy, push ou merge.
- Próximo passo: aguardar novas instruções após o commit aprovado, sem iniciar outro incremento; preservar pendência dos testes manuais de notificações.

## Revisão do Brainstorm — edição e exclusão pelo criador

- Retorno do usuário em 31/10/2026 incorporado ao incremento aprovado no fechamento: edição privada pelo ícone de lápis no Inbox/projeto, no mesmo modal e com as mesmas regras de texto/CSRF. Destino é mantido; não é possível editar ou excluir nota alheia, inclusive por parâmetros forjados. Projeto arquivado continua permitindo manutenção da própria nota privada.
- Tarefas criadas diretamente ou pela conversão agora têm Excluir tarefa no painel do Kanban, somente para created_by igual ao usuário autenticado. Dono/admin não excluem tarefas de terceiros. Autor que passou a visualizador mantém apenas essa exclusão sobre sua tarefa; os demais controles continuam de leitura. Acesso atual ao projeto é obrigatório; arquivados precisam ser restaurados.
- Exclusão exige confirmação explícita, CSRF e versão atual do quadro; remove tarefa/listas/itens/comentários/atribuições, recompõe posições e atualiza board_version na transação serializada do Kanban. Mantém histórico do projeto com evento task.deleted e remove vínculos de auditoria/notificações para a tarefa removida; avisos pendentes são cancelados. Sem restauração automática da nota original após excluir tarefa convertida.
- Nenhuma migration adicional necessária além da 012 já aplicada. Testes notes-revision.php incluem edição, texto convertido, propriedade, criador visualizador, rollback com dependências/outbox/histórico, CSRF e exclusões concorrentes; suites de quadro/tarefas/relatórios/notificações aprovadas em banco descartável. Regressão PHP completa aprovada em banco descartável novo (001–012), incluindo notes-revision.php. UI/axe 320–1440px aprovada com edição no Inbox/projeto, exclusão de tarefa convertida e exclusão pelo autor que virou visualizador; outro usuário não vê a ação. Preflight local executado como www-data aprovado; sem alteração de credenciais ou fixtures no banco local.
- Próximo passo: revisão aprovada; aguardar novas instruções após o commit, conforme pedido explícito do usuário. Preservar alteração preexistente em .env.example fora do escopo.

## Fechamento do Brainstorm e pausa

- Brainstorm, edição privada e exclusão de tarefas pelo criador aprovados para commit em 31/10/2026. Validações completas registradas acima; nenhum novo incremento iniciado. Aguardar instruções. Alteração preexistente em .env.example preservada fora deste commit; sem push, merge ou deploy.

## Refatoração dos Post-its — planejamento inicial

- Documento x_brainstorm/IMPLEMENTACAO_POSTIT_REFACTOR.md lido; por instrução explícita dele, nenhuma implementação iniciada antes da aprovação do planejamento. Base existente: commit c36b863, QuickNoteService/Controller, Twig e assets externos.
- Planejar título/cor/visibilidade com migração compatível (notas atuais privadas/amarelas), propriedade atual separada do criador original, auditoria privada, transferência no mesmo projeto e conversão somente de notas vinculadas com título confirmado. Sem Workspaces neste incremento.
- Decisão pendente: Público não pode significar todos os usuários nem automaticamente todos de tenant_users, pois inclui convidados de projetos diferentes. Recomendação inicial: Privado e Projeto funcionais, Público indisponível até aprovação de uma audiência explícita e segura. Envolvidos: criador/proprietário e destinatários registrados nas transferências, apenas se ainda autorizados; sem nova associação de colaboradores.
- Proposta em três incrementos: título/cores/visual; visibilidade/movimentação/auditoria; transferência/conversão/avatares. Imagem referenciada não consta no diretório consultado; proposta visual baseada na descrição do documento. Próximo passo: aprovação do planejamento e do escopo de Público.
- Alterações preexistentes encontradas em composer.json e .vscode/ preservadas; nenhum código, banco ou credencial alterado nesta leitura/planejamento.

## Post-its — incremento 1 implementado, aguardando aprovação

- Planejamento aprovado com imagem em 31/10/2026. Implementado somente o primeiro incremento: título opcional (200 caracteres), corpo preservado (5.000), urgências baixa/verde, média/amarelo (padrão) e alta/laranja e composição do card com cabeçalho/data, rolagem interna, menu, ações compactas e inicial do proprietário. Twig, CSS e JS externos; formulário sem JavaScript também oferece título e cores.
- Migration 013 compatível com notas existentes (título vazio, amarelo); aplicada no ambiente local após validação em banco descartável. Atualizações de clientes antigos preservam título/cor; conversão aproveita título existente e mantém descrição integral.
- Privacidade e permissões permanecem as atuais. Menu oferece edição; a inicial representa apenas o proprietário real. Visibilidade/movimentação/auditoria ficam no incremento 2; transferência, envolvidos derivados do histórico e conversão restrita a projeto com título confirmado ficam no 3. Por isso, a conversão no Inbox permanece temporariamente com o fluxo anterior. Público continua indisponível; sem Workspaces.
- Alterações preexistentes em composer.json e .vscode/ preservadas. Sem commit deste incremento antes da aprovação; sem push, merge ou deploy.
- Validação: regressão PHP completa aprovada em banco descartável novo (001–013), incluindo migração de linha antiga, limites/UTF-8, cores, compatibilidade de atualização, XSS/CSRF, autorização, tenant, rollback e concorrência. Chromium/axe do Brainstorm aprovado em 320–1440px, incluindo atalhos, foco, conversão, exclusão e fallback sem JS. Teste específico de aparência validou persistência/edição das cores, rolagem e ações dentro do card; capturas locais em storage/previews/postit-mobile.png e postit-desktop.png. Corrigidos contraste do texto de ajuda e prioridade das cinco colunas da navegação mobile. Preflight local como www-data aprovado com migration 013; nenhuma fixture no banco local.
- Próximo passo: aprovação deste incremento para commit; depois iniciar o incremento 2 (visibilidade, movimentação e auditoria), mantendo Público indisponível.

### Ajuste solicitado antes da aprovação — urgência dos Post-its

- As cores representam urgência: Verde · Baixa (“Pode ser realizada sem pressa.”), Amarelo · Média (“Requer atenção e planejamento.”) e Laranja · Alta (“Precisa ser resolvida em breve.”). Amarelo/média permanece selecionado por padrão, inclusive após salvar ou sair da edição. O card identifica a urgência também por texto.
- Migration 014 mantém o padrão amarelo e converte as notas azuis existentes em laranja, preservando título, corpo e propriedade. Validada em banco descartável e aplicada localmente; testes PHP de aparência/Brainstorm aprovados e checagem local como www-data aprovada.
- O incremento 1 continua aguardando aprovação; nenhum commit ou avanço de incremento neste ajuste. Validação Chromium/axe aprovada em 320–1440px, com persistência de laranja/verde, padrão amarelo, rótulos e contraste das três urgências. Regressão completa não repetida neste ajuste; apenas as suítes afetadas.

### Simplificação visual das opções de urgência

- Ajuste solicitado antes da aprovação: seletor compacto em uma linha, somente Baixa, Média e Alta, com as cores correspondentes e Média/amarelo selecionada por padrão. Removidos os nomes das cores e as explicações dos formulários de criação e edição, incluindo o fallback sem JS. Mantidos alvos de toque de 44px, foco e radios nativos.
- Alteração apenas em apresentação, sem mudança de dados ou migration adicional. Incremento 1 continua aguardando aprovação; sem commit e sem iniciar o incremento 2.
- Validação deste ajuste: renderização Twig dos dois formulários confirmou os três rótulos, ausência das explicações e seleção única de Média/amarelo; git diff --check aprovado. Teste Chromium existente atualizado aos novos rótulos; não reexecutado neste ajuste de apresentação.

### Restauração do seletor original

- A tentativa de grid deixou o legend do fieldset ocupar uma das colunas, quebrando a disposição. Restaurados o flex com quebra responsiva e as dimensões dos controles da primeira implementação, retirando as regras posteriores de expansão/centralização. Mantido o título original “Cor da nota”; somente os rótulos das opções passam a Média, Alta e Baixa, com amarelo selecionado por padrão. Cores e dados preservados.
- Incremento 1 segue aguardando aprovação, sem commit nem avanço para o próximo incremento.
- Removido também o texto adicional de urgência no card, introduzido após a primeira implementação, para manter a composição original.
- Validação em Chromium com Twig real e folhas de estilo do app, sem banco/fixtures de usuários: opções alinhadas na mesma linha em 320, 390, 768 e 1024px, alvos de toque de 44px, padrão amarelo e ausência de overflow horizontal. Captura revisada em storage/previews/postit-selector-restored.png.

## Aprovação do incremento 1 dos Post-its

- Incremento aprovado pelo usuário, incluindo o título “Prioridade” editado por ele no seletor. Confirmada a renderização dos dois formulários, com Média/amarelo como padrão; teste visual existente ajustado ao título final. Layout e rótulos simples preservados.
- Commit autorizado exclusivamente para título, prioridade/cores e visual do incremento 1 e documentação/testes correspondentes. composer.json e .vscode/ continuam fora do escopo.
- Próximo trabalho autorizado após o commit: incremento 2, com Privado/Projeto, movimentação entre destinos acessíveis e auditoria privada/transacional. Público permanece indisponível. Transferência e confirmação da conversão ficam no incremento 3.
- Fechamento aprovado: regressão PHP completa executada novamente em banco descartável novo, migrations 001–014, todas as suítes aprovadas. Incremento 1 concluído; próximo incremento autorizado conforme acima.

## Post-its — incremento 2 implementado, aguardando aprovação

- Último incremento aprovado e commitado: título, Prioridade (nome alterado pelo usuário), cores e visual dos Post-its, commit cceafaa. composer.json e .vscode/ preservados fora do commit.
- Trabalho atual: Privado por padrão; Projeto visível apenas aos membros com acesso válido ao projeto/tenant. Público indisponível. Somente o proprietário atual pode editar, mover, excluir ou converter; leitores não recebem botões de mutação. As iniciais identificam o proprietário real da nota.
- Edição permite mover entre Inbox e projetos acessíveis, inclusive de outro tenant somente com associação válida ao destino. Inbox exige Privado; origem/destino arquivados bloqueiam movimentação. Edição simples/exclusão de nota própria arquivada mantém o comportamento anterior. Permissões de criação de notas próprias dos visualizadores preservadas; conversão continua exigindo papel de escrita.
- Migration 015: created_by preserva o criador original, visibility padrão private, revision e índice para compartilhamento; quick_note_events guarda auditoria privada de criação/edição/movimentação/exclusão/conversão e importação das notas anteriores. Registros ficam na mesma transação, preservados após remover a nota, sem copiar título/corpo para o histórico público ou para a auditoria privada. Não há nova tela de histórico neste incremento.
- Projetos são bloqueados em ordem estável antes da nota; identidade/tenant/destino são revalidados sob lock. A interface envia revision na edição/exclusão para rejeitar formulários obsoletos. Movimentação concorrente invalidada pelo MariaDB (1020) retorna conflito 409 com orientação para recarregar, preservando a operação vencedora.
- Testes afetados aprovados em banco descartável limpo: autorização/tenant, Privado/Projeto, CSRF/IDOR, público inválido, revogação, defaults, migração das notas existentes, movimentação entre tenants autorizados, arquivos, versões, rollback de criação/edição/exclusão/conversão por falha da auditoria e movimentos concorrentes/cruzados. Chromium/axe do Brainstorm e teste específico de visibilidade aprovados em 320–1440px, incluindo leitores sem ações, proprietário correto, privatização, seletor original de prioridade e criação compartilhada sem JS. Preflight local como www-data aprovado com migration 015; fixtures somente nos bancos descartáveis.
- Próximo passo: concluir regressão final e aguardar aprovação do incremento 2 para commit. Depois, incremento 3: transferência, confirmação de título na conversão restrita a projeto e indicadores dos envolvidos autorizados. Sem push, merge ou deploy.
- Fechamento do incremento 2: regressão PHP completa aprovada em novo banco descartável (001–015), incluindo a suíte notes-visibility e as suítes anteriores. Chromium/axe de aparência também aprovado após as mudanças de visibilidade, preservando o título Prioridade, layout, edição e três cores. Aguarda aprovação para commit; próximo incremento ainda não iniciado.

## Aprovação do incremento 2

- Incremento 2 aprovado pelo usuário: commit autorizado com validações completas registradas acima. Próxima etapa autorizada: transferência no mesmo projeto, conversão com título confirmado apenas em projeto e envolvidos derivados da auditoria. Preservar composer.json e .vscode/ fora do escopo.

## Post-its — incremento 3 implementado, aguardando aprovação

- Último incremento aprovado e commitado: Privado/Projeto, movimentação e auditoria, commit 02d78bd. Alterações preexistentes de composer.json e .vscode/ preservadas fora do commit.
- Transferência: menu de nota própria vinculada a projeto ativo, seleção de outro membro verificado do mesmo projeto, confirmação explícita e versão obrigatórias. Servidor reconfere remetente/destinatário/tenant/projeto na transação; mantém texto/título/prioridade/visibilidade/data/criador e registra transferred na auditoria. Antigo proprietário perde edição/exclusão/transferência/conversão e só continua vendo se a visibilidade permitir. Visualizador pode receber/editar/transferir nota própria, preservando as permissões anteriores; não pode converter em tarefa.
- Conversão: removida do Inbox também no backend. Exige vínculo ao projeto informado, coluna daquele projeto, título confirmado obrigatório (200 caracteres) e revisão atual. Modal compacto com título pré-preenchido quando existe e coluna; sem escolha de outro projeto. Texto integral na descrição, tarefa atribuída ao proprietário atual, versão do Kanban/auditoria/outbox na transação existente, nota removida só ao concluir.
- Envolvidos: criador original, proprietário e destinatários de transferências, deduplicados a partir de created_by/user_id/quick_note_events. Exibe apenas quem pode acessar a nota agora: Privado/Inbox somente proprietário; Projeto membros atuais e verificados, com tenant_users válido. Prioriza proprietário, mostra até duas iniciais e +N; cards estreitos usam uma inicial e +N. Identificação por nome/papel em aria-label/title, sem e-mails; sem relação nova de colaboradores.
- Backend/rotas: GET /notes/{id}/recipients e POST /notes/{id}/transfer, somente proprietário, CSRF em mutações e sem copiar conteúdo da nota para a auditoria privada. Sem migration adicional à 015. Deadlocks de transferências são repetidos até três tentativas com rollback; leitura invalidada pelo MariaDB (1020) na nota/projeto retorna 409, sem sobrescrever o vencedor.
- Testes afetados de backend aprovados em banco descartável: confirmação, remetente/destinatário, conta não verificada, projeto/tenant, perda de poderes, visibilidade, envolvidos removidos após revogação, revisões, título obrigatório/UTF-8, projeto/coluna fixos, criador versus proprietário, atribuição, CSRF, rollback da transferência e concorrência transferência/transferência, transferência/conversão e transferências opostas. Suítes anteriores adaptadas para mover explicitamente ao projeto e confirmar título antes de converter.
- UI do Brainstorm com Chromium/axe aprovada em 320–1440px, incluindo captura/edição, Inbox sem conversão, conversão com título, exclusão, foco/atalhos e fallback sem JS. Preflight local como www-data aprovado, sem fixtures no banco local.
- Próximo passo: concluir validação visual específica e regressão completa; aguardar aprovação do incremento 3 para commit. Público permanece indisponível até definir audiência segura; Workspaces fora do escopo. Nenhum novo incremento será inventado após concluir esta refatoração.
- Fechamento: regressão PHP completa aprovada em banco descartável novo (001–015), incluindo notes-ownership e todas as suítes anteriores. Chromium/axe específico aprovado em 320–1440px para avatares/+N, transferência confirmada, perda/retorno de propriedade, destinatário visualizador, Inbox sem conversão, título pré-preenchido/editável e bloqueio de título vazio. Capturas revisadas em storage/previews/postit-involved-mobile.png e postit-transfer-mobile.png; layout compacto preservado.
- Estado final: incremento 3 pronto para validação do usuário e ainda sem commit. Próximo passo após aprovação: commit e definição dos próximos detalhes/da audiência de Público; não iniciar Workspaces automaticamente. Nenhum dado de teste no banco local e nenhuma credencial alterada.

## Aprovação e fechamento do incremento 3

- Incremento 3 aprovado pelo usuário, incluindo correção do botão Nova nota. Causa reproduzida em Chromium: regra .project-details a sobrepunha a cor do botão e deixava texto/ícone e fundo em rgb(25,79,67). A regra do botão primário dentro do Brainstorm ganhou especificidade suficiente para manter texto/ícone brancos, inclusive no hover/foco.
- Correção validada com Twig real, ordem das folhas de estilo do app e contexto de projeto em 320, 390, 768, 1024 e 1440px: cor computada branca no texto/ícone, estados normal/hover/foco e contraste axe aprovados. Captura revisada em storage/previews/note-button-fixed.png. Sem mudanças de backend após a regressão PHP completa aprovada do incremento 3; a pequena correção visual recebeu validação dirigida, sem repetir suítes não afetadas.
- Incremento 3 concluído e autorizado para commit com transferência/conversão/envolvidos e correção do botão. composer.json e .vscode/ permanecem fora do escopo. Sem migrations adicionais à 015, credenciais alteradas, fixtures locais, push, merge ou deploy.
- Próximo passo: validação operacional pelo usuário e definição dos próximos detalhes/da audiência de Público, que continua indisponível. Refatoração planejada concluída; não iniciar Workspaces ou outro incremento sem escopo definido.

## Sincronização automática — incremento 1 (Kanban)

- Documento x_brainstorm/SINCRONIZACAO_AUTOMATICA_SEM_REFRASH.md lido conforme solicitação do usuário. Sua execução define implementar somente Kanban neste incremento. Base aprovada anterior: refatoração dos Post-its concluída no commit 2b310bf. composer.json e .vscode/ continuam fora do escopo.
- Implementados live-sync.js reutilizável e adaptador em board.js. Intervalo inicial 7.000ms, configurável por data-sync-interval entre 5–10s; uma requisição de polling por vez, timeout 15s, backoff até 60s, pausa/abort em abas inativas/offline, retomada imediata ao voltar/online/BFCache e interrupção em 401/403/404. Sem refresh integral ou dependências novas.
- GET /projects/{id}/board/version reconfere autenticação/verificação e associação ao projeto/tenant com um SELECT para o token além da autenticação, retornando apenas versão/token. Token inclui board_version, projeto, papel, arquivo, nome e dia UTC, cobrindo mudanças que não incrementam board_version. GET /projects/{id}/board/state reaproveita snapshot transacional existente; respostas privadas/no-store. Não há nova migration; banco local permanece 001–015.
- Snapshot só é buscado/aplicado quando token muda ou em atualização manual. Apenas o componente Kanban é reconstruído pelo render existente; filtros válidos, rolagem horizontal/vertical, disclosures e foco são restaurados. Polling não inicia durante salvamento/arraste/carregamento de tarefa; antes de aplicar, reconfere rascunhos/edição e versão para não usar resposta antiga.
- Formulários de tarefas/colunas mantêm rascunhos em falhas/409; outras edições bloqueiam ações que os sobrescreveriam. Atualização manual via Fetch pede confirmação antes de descartar rascunhos. Painel aberto não é substituído automaticamente; mantém versão própria para que salvar detalhes antigos não use indevidamente a versão nova do quadro. Atualizar detalhes continua sendo uma ação explícita. Revogação limpa a visualização e desativa as mutações.
- Sem JS permanece a consulta Twig do quadro e o link de atualização. Post-its, sino, comentários/checklists no painel e Dashboard não receberam sincronização automática neste incremento; os contadores já existentes no snapshot do quadro acompanham naturalmente board_version.
- Validação atual: testes de endpoints/um SELECT, cache, tenant/projeto, papéis, arquivo, revogação e conflitos aprovados em banco descartável. Scheduler Node aprovado para intervalo, suspensão/retorno, offline/BFCache, sobreposição, timeout/backoff, busy e parada por autorização. Cenário Chromium de dois usuários aprovado para tarefas/colunas, filtros/foco/rolagem/painel, rascunhos/409, atualização explícita, reconexão, aba inativa, revogação e ausência de navegações integrais; axe/layout 320–1440px aprovado. Regressão PHP completa aprovada em novo banco descartável (001–015). Revisões visuais anteriores sendo concluídas; sem fixtures no banco local.
- Próximo passo: concluir verificações visuais afetadas e aguardar validação/aprovação para commit. Os próximos incrementos do documento (Post-its, tarefas/notificações e Dashboard) ficam pendentes, sem iniciar agora.
- Fechamento do incremento 1: cenários completos de dois usuários concluídos, inclusive recuperação online e atualização explícita de detalhes com rascunho confirmado. Suítes Chromium existentes do Kanban e painel aprovadas sequencialmente com fixtures próprias, incluindo drag-and-drop real, filtros, consulta sem JS, checklist, comentários, rascunhos, retry, quatro papéis e projetos arquivados; axe A/AA e layout 320–1440px aprovados. Preflight local como www-data aprovado; git diff --check aprovado.
- Estado final: incremento 1 do Kanban aprovado pelo usuário e incluído neste commit, com as validações acima concluídas. Nenhuma alteração em dados/credenciais locais; composer.json e .vscode/ preservados fora do commit.

## Ponto de parada — sincronização automática

- Usuário solicitou registrar o fechamento, criar o commit aprovado e pausar. Trabalho pausado após a conclusão da sincronização automática do Kanban; nenhum novo incremento iniciado.
- Ao retomar por instrução do usuário: iniciar o incremento 2, sincronização dos Post-its (criação, edição, exclusão, transferência e visibilidade), conforme o documento de implementação. Sincronização de tarefas/notificações e Dashboard permanece para os ciclos posteriores.
- Validações concluídas: regressão PHP em banco descartável, scheduler Node, cenários Chromium entre usuários, regressões do Kanban/painel e axe/layout 320–1440px. Sem nova migration, push, merge ou deploy. Hash de fechamento disponível no histórico Git.
