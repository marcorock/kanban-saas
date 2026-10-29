# Banco de dados

MariaDB 11+, InnoDB, utf8mb4 e datas em UTC.

## Schema inicial

| Tabela | Relacionamentos e índices |
|---|---|
| users | PK id; e-mail único; hash de senha e data de verificação |
| tenants | PK id; owner_id referencia users, com índice; tipo pessoal ou organização |
| tenant_users | PK (tenant_id, user_id); FKs para tenants/users; índice (user_id, tenant_id) |
| schema_migrations | Versão única e data de aplicação |

O vínculo ao tenant não representará autorização automática aos projetos.

## Migrations

Configure `.env` e crie previamente o banco. Execute:

```sh
php bin/migrate.php
```

O executor aplica arquivos SQL em ordem de nome, registra versões e usa bloqueio no MariaDB para impedir execuções simultâneas. Não execute via HTTP. Reexecute para verificar que versões aplicadas são ignoradas.

DDL no MariaDB implica commits automáticos: uma falha pode deixar alterações parciais. Corrija o estado antes de repetir; não há rollback automático de schema. Migrations aplicadas não devem ser editadas. Nenhuma migration foi executada contra produção.

As tabelas de projetos, tarefas, permissões, auditoria e tokens serão adicionadas nos incrementos correspondentes.
