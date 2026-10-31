# Changelog

## [Unreleased]

### Correção operacional de VAPID
- Gerador preserva permissões do .env existente; restauração de leitura pelo Apache local e teste como www-data evitam falha geral de conexão nas rotas após geração das chaves.

### Adicionado
- Seletor original dos Post-its restaurado, com rótulos Média (padrão), Alta e Baixa, sem nomes das cores ou explicações.
- Post-its (incremento 1): título opcional, urgência baixa (verde), média (amarelo padrão) e alta (laranja), com cores persistidas, cabeçalho com data, anotação com rolagem interna, menu de edição, ações compactas e identificação do proprietário. Migration 013 preserva as notas antigas privadas e amarelas; migration 014 substitui azul por laranja; conversão aproveita o título quando preenchido.
- Edição das próprias notas pelo lápis, preservando destino e privacidade; conversão usa o texto atualizado.
- Exclusão de tarefas exclusivamente pelo criador, incluindo as convertidas de notas, com confirmação, limpeza de dependências, cancelamento de avisos pendentes e preservação do histórico do projeto.
- Notas Rápidas (Brainstorm): captura global no header e bottom bar, Ctrl+K/N, modal Post-it com foco e Enter para salvar (Shift+Enter para nova linha).
- Inbox pessoal e notas privadas por projeto, grid responsivo/paginação, exclusão e conversão atômica em tarefa atribuída ao autor, com coluna selecionável e texto integral na descrição.
- Migration 012, isolamento por tenant/usuário, CSRF, permissões, rollback/concorrência e testes Chromium/axe 320–1440px.
- Notificações B: SMTP com resumo de prazos por ciclo, Web Push por dispositivo, ativação explícita, manifest/service worker e CSP mínima.
- Migration 011 para recibos por dispositivo, remoção 404/410, retries externos e testes Mailpit/concorrência/privacidade/ativação.
- VAPID configurável em .env com gerador que preserva chaves existentes; integração com cron local e preferências por canal.
- Notificações A: sino/central paginada, leitura com CSRF e três preferências por canal (sino ligado; push/e-mail desligados).
- Migration 009 com outbox transacional, deduplicação, notificações, preferências e estrutura de assinaturas por dispositivo; worker CLI com concorrência, leases e retentativas limitadas.
- Avisos de atribuição, comentário e papel excluem o autor; resumo de prazos às 08h de São Paulo e vencido único por tarefa/prazo, com reconferência de tenant/projeto/atribuição e preferências.
- Testes de autorização, rollback, dedup, concorrência, prazos, preferências e UI/axe sem JS; documentação curta em docs/NOTIFICATIONS.md. Push/e-mail entregues no Incremento B.
- Suite integrada de UI/UX com 11 rotas, cinco larguras, axe de páginas completas, texto ampliado/espaçamento, teclado/jornada, movimento reduzido, assets/CSP e consulta sem JS.
- Evidência JSON sem credenciais fora do Git e registro reproduzível da revisão em docs/UI_REVIEW.md, mantendo homologação operacional adiada.
- Perfil/configurações Fluxo Claro: resumo da conta, e-mail somente leitura, nome e disclosures de senha/sessões, com contexto dos efeitos das ações.
- CSS/JS externos próprios, mostrar/ocultar senha, resumo de erro com foco e envio progressivo; POST/CSRF continua sem JS e senhas não são reapresentadas.
- Preservação de nome seguro no retorno 419, mantendo rejeição sem escrita, token atual e seção do erro aberta; testes PHP/Chromium de perfil, revogação e uso sem JS.
- Históricos pessoal/de projeto Fluxo Claro, com linha do tempo, autor/horário UTC, contexto e navegação para projeto/tarefa.
- Filtros nativos recolhidos, totais/paginação GET, vazios, aviso de arquivados e erro 422 próprios; comparação Antes/Depois por campo adaptada ao celular, sem tabela larga.
- CSS externo próprio e remoção de stylesheet/template de paginação legados sem referências, preservando consultas e privacidade administrativa.
- Fixture/teste Chromium/axe dos históricos, snapshots longos, autores e restrição de convites por papel.
- Minhas Tarefas Fluxo Claro: lista compacta, prioridade/estado/prazo, contexto e links diretos ao painel da tarefa.
- Navegação GET por estado com preservação de filtros, filtros nativos recolhidos, total real, paginação, vazios e aviso de arquivados; apresentação própria do erro 422.
- CSS externo próprio, sem JavaScript adicional ou alteração de regras de consulta/atribuição; fixture e teste Chromium/axe de Minhas Tarefas.
- Revisão do painel conforme preview aprovado: fundo branco, resumo e abas Subtarefas/Comentários/Detalhes, com checklist como visualização inicial.
- Marcação direta, edição com confirmação/cancelamento e exclusão de subtarefas na mesma linha; adição compacta e gestão da lista em menu.
- Contagem agregada de subtarefas concluídas/total e percentual no painel/card, com snapshot SQL limitado ao tenant/projeto, sem N+1; conclusão continua pela coluna.
- Painel de tarefas Fluxo Claro, com planejamento, responsáveis, progresso nativo de checklists, comentários e histórico; tela cheia no celular e lateral no desktop.
- Templates Twig para detalhes dinâmicos, CSS externo próprio, retorno do foco ao card ao fechar e restauração de foco/rolagem após salvar.
- Fechamento por Escape com confirmação de descarte, nova tentativa de leitura após falha e paginação identificada por seção.
- Teste Chromium/axe do painel em 320–1440px, foco/rolagem, retry, exclusão, paginação, quatro papéis e consulta de arquivados.
- Quadro Kanban Fluxo Claro: cards/colunas responsivos, filtros recolhidos com contador/limpeza, navegação horizontal e controles de edição por papel.
- Templates Twig nativos para o quadro dinâmico, estilos e comportamento externos; foco e posição horizontal preservados após gravações.
- Teste Chromium/axe do quadro em 320–1440px, alternativas ao arrastar, consulta sem JS e regressão de tarefas/relatórios.
- Participantes/convites Fluxo Claro, com papéis explicados, gestão recolhida, confirmações de remoção/transferência e privacidade administrativa preservada.
- Aceite de convite integrado ao layout de acesso e à área autenticada; erros com foco e preservação do e-mail, envio progressivo e CSS/JS externos.
- Fixture e testes Chromium/axe de equipe, SMTP/reenvio/cancelamento, aceite sem JS, conta incompatível, uso único e transferência.
- Detalhes de projeto Fluxo Claro: quadro em destaque, contexto, planejamento, papel e navegação do projeto.
- Gerenciamento separado para proprietário, exclusão recolhida com confirmação nativa e feedback de sucesso; descrição sem style inline e sem JS adicional.
- Teste Chromium/axe de detalhes, quatro papéis, consulta, ações com CSRF, exclusão confirmada e uso sem JS.
- Formulários de criação/edição Fluxo Claro, com seções, datas opcionais, ajuda, contadores e estado de envio em arquivos Twig/CSS/JS separados.
- Preservação de dados e retorno no próprio formulário em sessão expirada (419), mantendo CSRF e autorização.
- Teste Chromium/axe dos formulários, validação, cancelamento, permissões e envio sem JS.
- Listagem de projetos Fluxo Claro: cards responsivos, papel/vínculo/prazo, filtros ativos/arquivados, paginação e acesso a quadro/detalhes.
- Estados vazios, aviso de consulta de arquivados e feedback da listagem em CSS externo, sem JavaScript adicional.
- Testes PHP/Chromium de listagem, quatro papéis, isolamento, paginação, XSS e navegação sem JS.
- Dashboard Fluxo Claro: indicadores com filtros, prazos em destaque no celular, progresso nativo, atividades compactas e estados vazios.
- CSS externo próprio e remoção do estilo inline nas barras do dashboard, sem JavaScript adicional ou alteração de consultas/métricas.
- Regressão Chromium/axe do dashboard, com progresso sem JS, títulos escapados e conta vazia.
- Shell autenticado Fluxo Claro com header contextual, sidebar desktop, navegação inferior mobile/tablet, painel Mais e footer.
- Navegação com estado ativo por módulo, foco/Escape, alternativa sem JavaScript e saída por POST/CSRF; convites autenticados integrados.
- Testes de shell HTTP/Chromium e preflight dos novos assets; layout público legado sem style inline na largura do container.
- Design system Fluxo Claro: identidade, tokens, componentes e especificação de navegação mobile/tablet/desktop.
- Novo layout de login/cadastro, recuperação/reset e confirmação de e-mail; header/footer próprios e melhorias de mostrar senha, foco e estado de envio.
- Arquivos Twig, CSS e JavaScript separados, com regra permanente de UI registrada no AGENTS.md; preflight verifica os novos assets locais.
- Teste Chromium/axe das telas de acesso, fluxos SMTP, tamanhos de 320 a 1440px e fallback sem JavaScript.
- Fase 6: preflight CLI somente de leitura para instalação/produção, runner sequencial de testes e roteiro operacional com registro de validação.
- CSP, proteção contra frames, Permissions-Policy e HSTS na configuração de produção HTTPS; extensões PHP explícitas no Composer.
- Regressão HTTP/Chromium de segurança e perfil, validação de preflight e carga sintética de 1.000 tarefas.

- Perfil e configurações pessoais: edição do nome, troca autenticada de senha e encerramento das demais sessões; invalidação de links de recuperação na troca de senha.
- Base PHP 8.3 com configuração, rotas Pecee, controller e templates Twig.
- Conexão PDO MariaDB em UTC e executor CLI de migrations.
- Schema inicial de usuários, tenants e vínculos.

- Cadastro com tenant pessoal transacional, login/logout e página protegida.
- Sessões seguras, CSRF, validação Rakit e limites persistentes por IP/e-mail.
- Testes HTTP de autenticação, rollback e expiração de sessão.

- Verificação de e-mail e recuperação de senha por SMTP com tokens de uso único, expiração e armazenamento por hash.
- Reenvio de confirmação, limitação de solicitações e revogação das sessões após redefinição de senha.
- Migration de tokens e testes de envio SMTP, consumo concorrente e rollback.

- Projetos com criação, listagem paginada, consulta, edição, arquivamento/restauração e exclusão lógica.
- Isolamento por tenant/projeto, políticas por papel e auditoria transacional de alterações.
- Migration 004 e testes de permissões, integridade, CSRF e rollback.

- Convites por e-mail com aceite autenticado, expiração, reenvio/cancelamento e limites de envio.
- Gerenciamento de participantes/papéis, transferência de propriedade e auditoria transacional.
- Migration 005 e testes SMTP, permissões, concorrência e rollback de vínculos e propriedade.

- Quadro Kanban com colunas personalizáveis, criação/edição de títulos e drag and drop de tarefas e colunas.
- Conclusão configurável por coluna, reabertura, controle de versão e restauração visual em erro.
- Migration 006, SortableJS local, auditoria por tarefa e testes de concorrência e navegador.
- Regra permanente de aprovação → commit → próximo incremento registrada em AGENTS.md e histórico consolidado em docs/DEVELOPMENT.md.

- Painel lateral de tarefas com descrição, prioridades, datas e múltiplos responsáveis.
- Checklists/subtarefas simples, comentários com autorização por autor e histórico paginado.
- Filtros por responsável, prioridade, coluna e prazo; indicadores de atraso.
- Migration 007, limpeza auditada de atribuições na remoção de participante e testes de backend/navegador.

- Dashboard com indicadores pessoais, progresso por projeto, prazos próximos e atividades recentes.
- Minhas Tarefas e históricos pessoal/por projeto com filtros, paginação e valores anteriores/novos.
- Navegação unificada, links diretos para tarefas, privacidade de convites nos históricos e índices de consulta (migration 008).
- Testes de relatórios, isolamento, contagens, XSS, filtros e navegação no navegador.

### Melhorado
- Snapshot do Kanban consulta apenas campos dos cards, evitando carregar descrições completas a cada leitura/mutação.

### Corrigido
- Número do card Pendentes no dashboard mantém a cor branca no hover, impedindo que a regra genérica de links o torne invisível sobre o fundo verde.
- Configuração local de e-mail direcionada ao Mailpit na rede Docker, preservando suporte a SMTP de produção.

### Alterado
- Painel passa a exigir e-mail confirmado, inclusive em contas preexistentes.
- Organização dos templates e remoção da apresentação genérica do gerador.

### Correção das notificações locais e convites

- Worker local agendado a cada minuto e fila pendente processada; entrega permanece fora das requisições.
- Convite no sino para contas existentes, aceite interno seguro e recuperação de convites pendentes pela migration 010. Cancelamento, expiração, reemissão e aceite invalidam os avisos correspondentes.
- Testes de convites internos, permissões, privacidade, preferências e regressão das suítes afetadas.
