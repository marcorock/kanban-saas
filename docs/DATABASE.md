# Banco de dados

MariaDB 11+, InnoDB, utf8mb4 e datas em UTC.

## Schema inicial

| Tabela | Relacionamentos e índices |
|---|---|
| users | PK id; e-mail único; hash de senha e data de verificação |
| tenants | PK id; owner_id referencia users, com índice; tipo pessoal ou organização |
| tenant_users | PK (tenant_id, user_id); FKs para tenants/users; índice (user_id, tenant_id) |
| email_verification_tokens | PK/FK user_id; token_hash único; índice expires_at; validade de 24 horas |
| password_reset_tokens | PK/FK user_id; token_hash único; índice expires_at; validade de 30 minutos |
| projects | PK id; único (tenant_id, id); FK composta para o proprietário em tenant_users; índice de tenant/estado/id; restrição de datas |
| project_members | PK (project_id, user_id); FKs compostas impedem misturar tenant/projeto/usuário; índice por usuário/tenant/projeto |
| kanban_columns | FK composta para tenant/projeto; índice de ordem; flag is_done independente do nome |
| tasks | FK composta para tenant/projeto/coluna; criador, título, posição e completed_at; índice de ordenação |
| task_assignees | FKs compostas para tarefa e participante; múltiplos responsáveis; remoção de membro limpa atribuições |
| task_checklists | FK composta para tarefa; título e posição |
| task_checklist_items | FK composta para lista/tarefa, remoção em cascata; título, posição e conclusão |
| task_comments | FK composta para tarefa; autor, texto, timestamps e exclusão lógica; índice para paginação |
| project_invitations | Únicos (project_id, email) e token_hash; FK composta para tenant/projeto; remetente, papel, validade e datas de aceite/cancelamento |
| activity_logs | FKs para projeto/tenant e autor; índices por projeto e autor; snapshots JSON |
| auth_rate_limits | PK bucket (hash do escopo e identidade); índice de expiração; contagem atômica |
| schema_migrations | Versão única e data de aplicação |

O vínculo ao tenant não representará autorização automática aos projetos.

## Migrations

Configure `.env` e crie previamente o banco. Execute:

```sh
php bin/migrate.php
```

O executor aplica arquivos SQL em ordem de nome, registra versões e usa bloqueio no MariaDB para impedir execuções simultâneas. Não execute via HTTP. Reexecute para verificar que versões aplicadas são ignoradas.

DDL no MariaDB implica commits automáticos: uma falha pode deixar alterações parciais. Corrija o estado antes de repetir; não há rollback automático de schema. Migrations aplicadas não devem ser editadas. Nenhuma migration foi executada contra produção.

A migration 003 acrescenta `users.session_version` (padrão 1), utilizado para revogar sessões após redefinição de senha, e as tabelas de tokens. Chaves estrangeiras de tokens usam ON DELETE CASCADE.

A migration 004 cria projetos, participantes e auditoria de projetos. Datas previstas são DATE; timestamps persistem em UTC. Exclusão de projeto é lógica (`deleted_at`); arquivamento usa `archived_at`.

A migration 005 cria `project_invitations`. Vínculos ao tenant/projeto são criados somente no aceite, em uma transação com consumo do token e auditoria. Transferência altera o proprietário do projeto e os papéis, preservando o tenant.

A migration 006 cria colunas e tarefas básicas, acrescenta `projects.board_version` e `activity_logs.task_id` (FK composta e índice). Colunas iniciais são criadas para projetos existentes não excluídos; novos projetos inicializam o quadro na transação de criação. Posições são normalizadas pelo serviço com bloqueio do projeto e controle de versão. A migration 007 acrescenta descrição, prioridade e datas às tarefas e cria responsáveis, checklists, itens e comentários com FKs compostas e índices de consulta. Responsáveis só podem ser participantes do projeto. A aplicação audita explicitamente a limpeza das atribuições ao remover participantes.

A migration 008 acrescenta índices `(tenant_id, project_id, created_at, id)` e `(actor_id, created_at, id)` a activity_logs para os filtros de período nos históricos. Relatórios revalidam vínculos no tenant/projeto e usam paginação; métricas e progresso do dashboard são calculados em uma transação de leitura.
