# Autenticação

## Rotas

| Método | Caminho | Acesso |
|---|---|---|
| GET | /register, /login | Formulários públicos |
| POST | /register | Cadastro com CSRF e validação |
| POST | /login | Login com CSRF e validação |
| POST | /logout | Encerramento da sessão com CSRF |
| GET | /dashboard | Exige sessão e usuário existente |

O cadastro normaliza e-mail e cria usuário, tenant pessoal e vínculo em transação. A senha deve ter ao menos 12 caracteres, até 72 bytes e não conter byte nulo; usa password_hash/password_verify. Senhas nunca são repopuladas nos formulários. O dashboard atual é uma página de confirmação de acesso; métricas serão implementadas posteriormente.

## Sessões e proteção

Cookies HttpOnly e SameSite=Lax, com Secure quando APP_URL usa HTTPS ou a conexão PHP recebe HTTPS. Configure APP_URL com o endereço HTTPS de produção. Cabeçalhos de proxy não são confiados automaticamente; o terminador TLS deve encaminhar corretamente o protocolo ao servidor PHP.

ID e CSRF são renovados no login e logout. Sessões autenticadas expiram após 30 minutos de inatividade ou 12 horas desde o login. Todas as respostas da aplicação usam Cache-Control: no-store.

Limite fixo de 15 minutos: login permite 30 tentativas por IP e 10 por e-mail; cadastro permite 10 por IP e por e-mail. Tentativas válidas e inválidas contam. O banco armazena apenas hashes das chaves dos limites; não confia em X-Forwarded-For. Em proxy, configure REMOTE_ADDR no servidor para refletir o IP real de forma confiável.

Para manutenção, execute periodicamente no banco: `DELETE FROM auth_rate_limits WHERE expires_at < UTC_TIMESTAMP()`. A expiração funciona mesmo antes da limpeza.

## Instalação

Execute `composer install` e `php bin/migrate.php` antes de acessar os formulários. Bootstrap é servido localmente em `public/assets/css/bootstrap.min.css`, copiado da versão do Composer; preserve a licença incluída no CSS ao atualizá-lo.

## Testes

Com PHP 8.3:

```sh
php tests/session.php
```

Para integração, configure um MariaDB **descartável**, aplique as migrations e inicie `php -S 127.0.0.1:8000 -t public` em outro terminal com o mesmo ambiente. Em seguida:

```sh
AUTH_TEST_DATABASE=disposable AUTH_TEST_URL=http://127.0.0.1:8000 php tests/auth.php
```

O teste cria contas, altera limites e cria temporariamente um trigger para validar rollback; exige permissão de trigger. Use APP_URL HTTP no ambiente HTTP de teste. Descarte o banco ao terminar.

## Pendências da Fase 1

Verificação de e-mail, recuperação de senha e integração de envio de mensagens ainda não implementadas. O cadastro atual permite login sem e-mail verificado. Não há edição de perfil, projetos ou autorização por projeto neste incremento.
