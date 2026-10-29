# Changelog

## [Unreleased]

### Adicionado
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

### Corrigido
- Configuração local de e-mail direcionada ao Mailpit na rede Docker, preservando suporte a SMTP de produção.

### Alterado
- Painel passa a exigir e-mail confirmado, inclusive em contas preexistentes.
- Organização dos templates e remoção da apresentação genérica do gerador.
