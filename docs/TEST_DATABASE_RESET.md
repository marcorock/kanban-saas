# Reset do banco local para testes

Utilitário local: `local-tools/reset-database.sh`, que executa `reset-database.php` pelo PHP do host ou pelo container Coruja. A pasta inteira é ignorada pelo Git e fica fora de `public/`; seus arquivos não acompanham novos clones. A documentação e a regra de manutenção permanecem versionadas.

Na raiz do projeto:

```sh
# Prévia: mostra o banco e as tabelas, sem apagar registros.
./local-tools/reset-database.sh

# Apagar todos os registros e reiniciar os IDs no banco local atual.
./local-tools/reset-database.sh --execute --database=kanbansaas
```

O nome deve coincidir com o banco configurado no `.env`. A ferramenta não copia nem exibe senhas. Ambientes permitidos: `local`, `development`, `testing`; produção e argumentos desconhecidos são recusados. Sem `--execute`, nunca apaga registros.

A limpeza remove contas, tokens, projetos, participantes, convites, tarefas, checklists, comentários, notas, auditoria, preferências, notificações e assinaturas push. Preserva as tabelas, índices, constraints e os registros de `schema_migrations`. Os contadores `AUTO_INCREMENT` retornam a 1: o próximo registro recebe ID 1, não 0. Não é necessário reaplicar migrations.

Durante o reset, o lock de migrations impede execução simultânea do migrador e os locks de todas as tabelas impedem acessos concorrentes, inclusive do worker. São liberados ao terminar; falhas não deixam `FOREIGN_KEY_CHECKS` desativado em outras conexões. `TRUNCATE` não tem rollback: se houver falha parcial, inspecione o banco e repita a limpeza após corrigir a causa.

Encerre as sessões nos navegadores antes dos novos cadastros, pois IDs serão reutilizados. Cookies/arquivos de sessão PHP, mensagens do Mailpit, instalação PWA e caches não são apagados por este script. Depois do reset, cadastre e confirme uma conta novamente.

## Manutenção obrigatória a cada mudança no banco

1. Revisar o utilitário e a documentação quando houver tabela, coluna, índice, constraint, migration ou regra de preservação nova/alterada. O inventário é automático; não manter uma lista manual paralela de tabelas.
2. Aplicar e validar as migrations em banco descartável. Verificar reset, FKs, próximo ID 1 e preservação de `schema_migrations`.
3. Depois de aplicar as migrations verificadas ao ambiente local, executar:

   ```sh
   ./local-tools/reset-database.sh --update-schema
   ./local-tools/reset-database.sh
   ```

4. Registrar mudança e validação no CHANGELOG/PROJECT_CONTEXT. A regra permanente está no AGENTS.md.

`--update-schema` não apaga dados. Atualiza `local-tools/schema-registry.json` e acrescenta mudanças ao arquivo `.history.jsonl`, com data, migrations/hashes, tabelas, definições e assinatura do schema. O reset recusa migrations pendentes e schemas diferentes do registro; revise a mudança antes de atualizar. A assinatura ignora apenas o valor corrente do `AUTO_INCREMENT`, permitindo resets repetidos.

Para testes descartáveis, `--registry=/tmp/reset-test.json` evita substituir o inventário local. `KANBAN_PHP_CONTAINER` e `KANBAN_CONTAINER_PROJECT` permitem ajustar container/caminho do wrapper sem modificar credenciais.

Validação inicial em 02/11/2026: 24 tabelas (23 limpas e `schema_migrations` preservada), migrations 001–015, IDs em 1, bloqueio de concorrência, produção/alvo incorreto, argumentos inválidos e detecção/inclusão de tabela nova aprovados.
