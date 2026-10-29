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
- Banco local configurado no .env (não versionado). Migrations 001–006 aplicadas; nenhum teste com fixtures foi executado no banco de uso diário.
- Mailpit local: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; interface http://mail.localhost. Produção segue configurável por MAIL_*.
- Branch de trabalho atual: Authentication. Não mudar/mesclar branches automaticamente.

## Incrementos aprovados e commitados

- e6b15d7 — estrutura PHP, roteamento, Twig, PDO e migrations iniciais.
- a6ccc86 — cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit.
- eaa70ff — confirmação de e-mail e recuperação de senha por SMTP; tokens SHA-256 de uso único e revogação de sessões.
- 0cb3560 — projetos: CRUD, arquivar/restaurar, exclusão lógica, paginação, policies e auditoria.
- c26cb49 — convites, participantes, papéis, transferência de propriedade e regra permanente de continuidade.

## Incremento atual — Kanban (Fase 3)

- Implementado e validado; em revisão pelo usuário, ainda sem commit.
- Migration 006: kanban_columns, tasks básicas, projects.board_version e activity_logs.task_id com FKs compostas.
- Projetos existentes não excluídos receberam colunas iniciais; novos projetos as criam na própria transação.
- Proprietário/admin gerenciam colunas; membro também cria/renomeia/move tarefas; visualizador e projeto arquivado têm somente leitura.
- Conclusão depende de is_done da coluna, nunca do nome; mover entre concluídas preserva data, sair reabre com histórico.
- BoardService serializa gravações pelo projeto e valida board_version. Conflitos retornam 409; frontend restaura a última posição confirmada em erro e orienta atualização.
- Interface responsiva com rolagem horizontal, drag and drop e alternativas por formulário.
- Pecee 5 trata JSON inválido inadequadamente; public/index.php valida objetos JSON (até 64 KiB) antes do roteamento.
- Regressão PHP aprovada: sessões, autenticação, SMTP, projetos, participantes e Kanban.
- Testes de risco aprovados: IDOR, papéis, CSRF, FKs, datas, conclusão/reabertura, rollback e processos concorrentes.
- Chromium aprovado: cadastro/confirmação, drag de cards e colunas, restauração em erro, conflito entre abas, persistência e layout móvel.
- Testes executados em PHP 8.3.32, MariaDB 11.8.8 e Mailpit isolados/descartáveis; ferramentas de navegador instaladas somente em container de teste.

## Próximo incremento após aprovação

- Fazer commit do Kanban aprovado e iniciar Fase 4: detalhes de tarefa em modal/offcanvas, descrição, múltiplos responsáveis, prioridades, datas, checklists e comentários.
- Depois: histórico visível, dashboard consolidado, filtros e validação final conforme requisitos.
- Ainda pendentes: edição de perfil e gerenciamento de organizações; sem cobrança/pagamentos no MVP.

## Documentação por módulo

- Arquitetura e schema: docs/ARCHITECTURE.md e docs/DATABASE.md.
- Conta e segurança: docs/AUTHENTICATION.md.
- Projetos: docs/PROJECTS.md. Convites e membros: docs/MEMBERS.md.
- Kanban, protocolo JSON, dependência e testes: docs/KANBAN.md.
- Evolução funcional: CHANGELOG.md. Histórico de commits e continuidade: docs/DEVELOPMENT.md.
