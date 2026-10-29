# Instalação e preparação para produção

A Fase 6 acrescenta verificações e um roteiro reproduzível. Nenhum deploy em produção foi realizado. Os resultados de testes locais não equivalem à validação do servidor de produção.

## Requisitos e configuração

- PHP compatível com `^8.3` (8.3 até 8.x), extensões ctype, filter, mbstring, openssl, pdo_mysql e session; Composer verifica também os requisitos transitivos.
- MariaDB 11+, InnoDB, utf8mb4. Datas da aplicação e conexão SQL em UTC.
- Apache com mod_rewrite e DocumentRoot apontando exclusivamente para `public/`. O servidor embutido do PHP serve para desenvolvimento/testes.
- HTTPS e SMTP com certificado válido, configurados pelo operador. Recursos CSS/JS são locais.

Copie `.env.example` para `.env` apenas em instalação nova. Configure `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL=https://seu-dominio`, na raiz do domínio. Rotas não suportam instalação em subdiretório. Configure DB_HOST/PORT/DATABASE/USERNAME/PASSWORD e SMTP MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM_ADDRESS/FROM_NAME; use `tls` (geralmente 587) ou `ssl` (geralmente 465), conforme seu provedor. Relay sem autenticação pode usar usuário vazio, mantendo TLS. Não desative validação de certificado. Mailpit com `none` é destinado ao desenvolvimento.

Use usuário dedicado para a aplicação com SELECT, INSERT, UPDATE e DELETE somente no banco da aplicação. Use outra credencial, fornecida apenas ao processo CLI, para criar o banco e executar migrations (CREATE, ALTER, INDEX e demais permissões necessárias aos SQLs versionados). Os testes precisam de permissões adicionais e nunca usam esse banco.

Mantenha `.env`, backups e logs fora da raiz pública e do Git. O processo web precisa ler código/dependências e gravar sessões; não precisa editar código ou `.env`. Configure o diretório de sessões para escrita pelo PHP e acesso restrito. Para mais de uma instância, configure um armazenamento de sessão compartilhado antes de escalar.

## Instalação ou atualização

Na versão de código que será instalada, preservando o `.env` existente:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
composer audit --locked --no-dev
```

Não execute `composer update` durante a publicação: o lock define as versões revisadas. Execute a auditoria na data da instalação; o resultado pode mudar com novos avisos.

Antes de migrations, faça backup consistente e teste sua restauração em banco separado. Suspenda escritas durante alterações de schema. Com a credencial de migration fornecida ao processo, execute:

```sh
php bin/migrate.php
```

Com a credencial de aplicação, execute:

```sh
php bin/check.php --production
```

O check é uma etapa obrigatória deste roteiro, não um bloqueio automático do processo web. Ele não altera banco, sessões nem arquivos e não envia e-mails. Verifica PHP/extensões, configuração, assets, conexão MariaDB/UTC e correspondência dos registros de migrations. Rejeita produção com HTTP, debug, SMTP sem TLS ou usuário root/senha vazia no banco. Não confere o conteúdo de cada tabela, grants completos, TLS externo, entrega SMTP, backup ou configuração do Apache. Saídas: 0 = verificações aprovadas; 1 = falha; 2 = argumento desconhecido. Não imprime credenciais nem valores de conexão.

No ambiente local, use `php bin/check.php`; não force configurações de produção no `.env` de desenvolvimento. APP_DEBUG continua sem habilitar exibição de erros ou debug Twig no código da aplicação.

## Servidor web

No VirtualHost HTTPS existente, adapte os caminhos:

```apache
DocumentRoot /var/www/kanbansaas/public
<Directory /var/www/kanbansaas>
    Require all denied
</Directory>
<Directory /var/www/kanbansaas/public>
    Require all granted
    Options -Indexes
    AllowOverride All
</Directory>
```

Configure certificado/TLS e redirecionamento de HTTP para HTTPS no servidor ou proxy. Não confie em `X-Forwarded-*` vindo diretamente do cliente; a aplicação usa APP_URL para a política de cookie Secure. Não configure cache compartilhado para respostas dinâmicas ou páginas autenticadas. Assets estáticos podem usar compressão/cache no servidor, invalidando esse cache ao atualizar arquivos sem hash no nome.

A aplicação envia `Cache-Control: no-store`, nosniff, proteção contra frames, Permissions-Policy e CSP. Scripts são permitidos apenas da mesma origem, sem inline/eval; estilos inline permanecem permitidos para Bootstrap, Sortable e dimensões do layout. `application/json` do quadro contém dados escapados, não código executável. Em APP_ENV=production com APP_URL HTTPS, envia HSTS por um ano, sem includeSubDomains/preload. Confirmação, recuperação e convites mantêm a política de referrer mais restrita.

Configure `display_errors=Off`, `log_errors=On`, rotação/retenção de logs, `expose_php=Off` e OPcache no PHP de produção. Reinicie/invalide OPcache após troca de versão conforme a política do servidor. FPM/Apache podem usar INIs diferentes do CLI; confira ambos. Nunca registre corpos de formulários, cookies, cabeçalhos de autorização ou query strings dos links de conta/convite nos logs de acesso do servidor/proxy.

## Validação da instalação

Após instalar em homologação, usando contas de teste:

1. Confira que `/.env`, `/composer.json`, `/database/migrations/001_identity.sql` e `/bin/check.php` retornam 403/404 pelo domínio real, sem conteúdo privado.
2. Verifique HTTPS, cookie Secure/HttpOnly/SameSite e cabeçalhos em sucesso e erro; não deve haver bloqueio CSP dos recursos da aplicação.
3. Confirme cadastro, e-mail entregue pelo SMTP real, confirmação e recuperação de senha. O check não testa entrega.
4. Valide dois usuários, projetos de tenants distintos, convite, remoção de membro, permissão viewer, conflito entre abas e restauração após falha de gravação.
5. Confira configurações pessoais, troca de senha, revogação de outras sessões, dashboard e históricos.
6. Verifique logs e restauração do backup. Autorize a publicação separadamente.

DDL MariaDB pode fazer commit implícito: não há rollback automático de migrations. Se falhar, mantenha escritas suspensas, verifique estado e restaure ou corrija com procedimento revisado. Não remova registros de schema_migrations nem reedite SQL aplicado para contornar erros. Uma reversão de código exige schema compatível e preservação de `.env`/sessões conforme a operação.

## Testes reproduzíveis e desempenho

Prepare um banco MariaDB e Mailpit **descartáveis**, aplique migrations e inicie o servidor HTTP de teste com as mesmas variáveis DB_*/MAIL_* e APP_URL HTTP. Então:

```sh
AUTH_TEST_DATABASE=disposable php tests/run.php
```

O runner executa suítes PHP em processos sequenciais. Os testes criam fixtures/triggers, modificam tokens/limites e o preflight cria um usuário SQL temporário; exigem privilégios de administração **no servidor descartável**. Não execute contra o banco de uso diário. `tests/performance.php` inclui 1.000 tarefas com descrições de 16.000 caracteres; essas descrições são carregadas apenas no painel de detalhes. O teste verifica consultas constantes no quadro, memória, paginação e métricas.

Para navegador, instale Chromium e playwright-core em ambiente de teste separado e execute, com AUTH_TEST_DATABASE=disposable e NODE_PATH adequado: `node tests/profile-browser.cjs`, `node tests/tasks-browser.cjs` e `node tests/reports-browser.cjs`. Todos reutilizam a regressão de Kanban/cadastro/SMTP. Não há dependência Node em produção.

A leitura inicial do quadro ainda retorna todos os cards e responsáveis, e as mutações serializam pelo projeto. Relatórios paginam resultados, mas contagens/agregações e a seleção de projetos podem crescer. Não foi realizado teste de carga HTTP concorrente nem validado um SLA. Meça com dados/volume representativos da instalação antes de definir capacidade; paginação/virtualização de quadros muito grandes e entrega assíncrona de e-mail são evoluções possíveis.

Referências: [sessões PHP](https://www.php.net/manual/en/session.security.ini.php), [CSP](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy), [Composer CLI](https://getcomposer.org/doc/03-cli.md).
