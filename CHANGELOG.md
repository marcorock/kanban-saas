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

### Corrigido
- Configuração local de e-mail direcionada ao Mailpit na rede Docker, preservando suporte a SMTP de produção.

### Alterado
- Painel passa a exigir e-mail confirmado, inclusive em contas preexistentes.
- Organização dos templates e remoção da apresentação genérica do gerador.
