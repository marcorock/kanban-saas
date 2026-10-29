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

Tabelas de colunas e tarefas e referências de auditoria a tarefas serão adicionadas nos incrementos correspondentes.
