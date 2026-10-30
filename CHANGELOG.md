# Changelog

## [Unreleased]

### Adicionado
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
- Configuração local de e-mail direcionada ao Mailpit na rede Docker, preservando suporte a SMTP de produção.

### Alterado
- Painel passa a exigir e-mail confirmado, inclusive em contas preexistentes.
- Organização dos templates e remoção da apresentação genérica do gerador.
