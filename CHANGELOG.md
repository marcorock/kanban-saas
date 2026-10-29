# Changelog

## [Unreleased]

### Adicionado
- Base PHP 8.3 com configuração, rotas Pecee, controller e templates Twig.
- Conexão PDO MariaDB em UTC e executor CLI de migrations.
- Schema inicial de usuários, tenants e vínculos.

- Cadastro com tenant pessoal transacional, login/logout e página protegida.
- Sessões seguras, CSRF, validação Rakit e limites persistentes por IP/e-mail.
- Testes HTTP de autenticação, rollback e expiração de sessão.

### Alterado
- Organização dos templates e remoção da apresentação genérica do gerador.
