# Autenticação

## Rotas

| Método | Caminho | Acesso |
|---|---|---|
| GET | /register, /login | Formulários públicos |
| POST | /register | Cadastro com CSRF e validação |
| POST | /login | Login com CSRF e validação |
| POST | /logout | Encerramento da sessão com CSRF |
| GET | /dashboard | Exige sessão válida e e-mail confirmado |
| GET/POST | /email/resend | Solicitar nova confirmação |
| GET/POST | /email/verify | Exibir confirmação / consumir token com CSRF |
| GET/POST | /password/forgot | Solicitar recuperação |
| GET/POST | /password/reset | Exibir formulário / redefinir senha com CSRF |

O cadastro normaliza e-mail e cria usuário, tenant pessoal e vínculo em transação. A senha deve ter ao menos 12 caracteres, até 72 bytes e não conter byte nulo; usa password_hash/password_verify. Senhas nunca são repopuladas nos formulários. O dashboard atual é uma página de confirmação de acesso; métricas serão implementadas posteriormente.

## Sessões e proteção

Cookies HttpOnly e SameSite=Lax, com Secure quando APP_URL usa HTTPS ou a conexão PHP recebe HTTPS. Configure APP_URL com o endereço HTTPS de produção. Cabeçalhos de proxy não são confiados automaticamente; o terminador TLS deve encaminhar corretamente o protocolo ao servidor PHP.

ID e CSRF são renovados no login e logout. Sessões autenticadas expiram após 30 minutos de inatividade ou 12 horas desde o login. Todas as respostas da aplicação usam Cache-Control: no-store. A redefinição incrementa `users.session_version`, invalidando sessões anteriores na próxima consulta ao dashboard ou a projetos; não autentica automaticamente. O login utiliza a versão lida junto com o hash da senha.

Limite fixo de 15 minutos: login permite 30 tentativas por IP e 10 por e-mail; cadastro permite 10 por IP e por e-mail. Tentativas válidas e inválidas contam. O banco armazena apenas hashes das chaves dos limites; não confia em X-Forwarded-For. Em proxy, configure REMOTE_ADDR no servidor para refletir o IP real de forma confiável.

Para manutenção, execute periodicamente no banco: `DELETE FROM auth_rate_limits WHERE expires_at < UTC_TIMESTAMP()`. A expiração funciona mesmo antes da limpeza.

## Tokens e envio

Cadastro envia confirmação automaticamente. Contas não verificadas podem entrar, mas recebem aviso com opção de reenvio ao acessar o painel (HTTP 403); não recebem o conteúdo protegido. A confirmação é necessária também para contas existentes antes desta migration. Sessões abertas antes desta atualização exigem novo login, pois ainda não possuem a versão de sessão.

Tokens aleatórios de 32 bytes, armazenados somente como SHA-256, com um token ativo por usuário e finalidade. Confirmação expira em 24 horas; recuperação em 30 minutos. Reenvio invalida o link anterior. Abrir um link por GET não o consome: a ação exige POST e CSRF. A confirmação não altera a sessão de outro usuário que eventualmente esteja conectado.

O consumo usa transação e bloqueios na ordem usuário → token. Uso concorrente só permite um consumo. A recuperação não marca o e-mail como confirmado. Uma falha de banco reverte alteração de senha e consumo juntos.

Solicitações e confirmações permitem 20 tentativas por IP e por rota em 15 minutos. Solicitações de envio permitem 3 por e-mail e finalidade no mesmo período. A resposta de envio é a mesma para e-mail inexistente, já confirmado ou limitado. Envios são síncronos, portanto o tempo de resposta pode variar conforme o SMTP.

Envio SMTP usa [PHPMailer](https://github.com/PHPMailer/PHPMailer), instalado via Composer. Configure as variáveis MAIL_* de `.env.example`; não há envio por Gmail ou outra conta conectada. O exemplo usa Mailpit local (porta 1025, sem TLS); no Docker Coruja, configure `MAIL_HOST=mail` (o nome do serviço na rede Docker), `MAIL_PORT=1025`, `MAIL_ENCRYPTION=none` e deixe usuário/senha vazios. Fora do Docker, use o endereço publicado pelo Mailpit. Produção deve usar credenciais SMTP reais, remetente autorizado, `MAIL_ENCRYPTION=tls` (587) ou `ssl` (465). A validação dos certificados permanece habilitada. APP_URL é a origem dos links, sem usar o cabeçalho Host da requisição.

Falhas SMTP geram apenas registro técnico genérico; a conta e o token continuam existentes, e o usuário pode solicitar outro link. Nenhum token ou corpo de mensagem é registrado pela aplicação. As páginas de token usam Referrer-Policy: no-referrer. Configure logs de acesso do servidor/proxy sem query strings nessas rotas para não armazenar os tokens presentes nos links.

Para manutenção, remova tokens vencidos de `email_verification_tokens` e `password_reset_tokens` pelo campo `expires_at`. A validação de expiração funciona independentemente da limpeza.

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
AUTH_TEST_DATABASE=disposable MAILPIT_TEST_URL=http://127.0.0.1:8025 php tests/account.php
```

Os testes criam contas, alteram limites e criam temporariamente triggers para validar rollback; exigem permissão de trigger. `tests/account.php` exige Mailpit isolado com SMTP configurado no mesmo ambiente e acesso à API HTTP na porta 8025; também verifica concorrência com dois processos PHP e falha SMTP. Não use caixas reais nos testes. Use APP_URL HTTP no ambiente HTTP de teste. Descarte o banco ao terminar.

## Próximos módulos

Projetos e autorização por projeto implementados conforme [PROJECTS.md](PROJECTS.md). Convites e participantes implementados conforme [MEMBERS.md](MEMBERS.md). Perfil e configurações pessoais implementados em `/settings`.

## Perfil e configurações pessoais

`GET /settings` e `POST /settings` exigem autenticação e e-mail confirmado. A navegação contém Configurações. Nome entre 2 e 120 caracteres; e-mail exibido somente para consulta. Identidade vem exclusivamente da sessão, sem aceitar user_id ou alterações de e-mail/papel pelo formulário.

Troca de senha exige senha atual, nova senha de 12 caracteres até 72 bytes e confirmação. A transação bloqueia primeiro o usuário, revalida session_version, altera o hash, exclui tokens de recuperação e incrementa a versão das sessões. A sessão atual é regenerada com novo CSRF; as demais são rejeitadas na próxima requisição. Encerrar demais sessões exige senha atual, incrementa a versão e mantém a sessão atual sem mudar a senha.

Todos os formulários exigem CSRF. Limites por usuário e ação: 30 alterações de nome ou 10 tentativas de senha/sessões por 15 minutos. Senhas nunca retornam ao HTML. Nome é escapado por Twig. Falhas no banco revertem a operação inteira. Nenhuma migration adicional é necessária; o nome do tenant pessoal não é alterado junto ao nome do usuário.

Teste em banco descartável com servidor HTTP no mesmo ambiente: `AUTH_TEST_DATABASE=disposable php tests/profile.php`. Cobre autorização, validação, CSRF, campos protegidos, XSS, senha atual, revogação, tokens e rollback. Não há alteração de e-mail, avatar, preferências de tema/fuso ou listagem individual de dispositivos neste incremento.

## Interface de perfil — Fluxo Claro

`/settings` reúne resumo da conta (nome/e-mail verificado), formulário de nome e seções nativas de senha/sessões recolhidas por padrão. E-mail não é editável; avatar é apenas uma inicial, sem upload. A partir de 1100px conta/formulários ficam lado a lado; abaixo, em uma coluna. Não há tema, notificações, fuso ou dispositivos fictícios.

Erro abre a seção correspondente e apresenta resumo textual com foco via JS externo. Em 419, um nome UTF-8 de até 120 caracteres sem controles é reapresentado, mantendo status e rejeição sem escrita; o token atual permite tentar de novo. Senhas nunca são preenchidas de volta. A troca invalida demais sessões/links de recuperação e mantém a atual; encerrar demais sessões não altera a senha, como antes.

Estrutura em resources/views/settings.html.twig/settings/fields.html.twig, apresentação em public/assets/css/settings.css, melhorias em settings.js. Mostrar/ocultar senha tem nome acessível e aria-pressed; estado de envio é restaurado ao voltar pelo histórico do navegador. Sem JS, os toggles ficam ocultos e os formulários/disclosures continuam funcionais. Nenhuma credencial é salva em localStorage.

Teste `tests/settings-ui-browser.cjs` requer Chromium/playwright-core/@axe-core/playwright no ambiente descartável de KANBAN.md. Reutiliza profile-browser.cjs (agora exportável) e acrescenta 320–1440px, axe A/AA, disclosures, toggles, 422/419/foco, nome preservado sem escrita, senhas vazias e POST de perfil/senha/sessões sem JS, com revogação de uma segunda sessão. Previews settings-390.png/settings-1440.png e settings-security-390.png em storage/previews, fora do Git.

## Validação de segurança e instalação

Cabeçalhos e configuração de produção verificados na Fase 6: [roteiro](PRODUCTION.md) e [evidências/limitações](VALIDATION.md). `tests/profile-browser.cjs` cobre agora o perfil em Chromium, incluindo troca de senha e revogação entre navegadores; `tests/security.php` cobre respostas e cookies da política HTTPS em instância descartável. A aplicação não ativa debug Twig nem exibe exceções por APP_DEBUG.

## Interface de acesso — Fluxo Claro

Login, cadastro, recuperação/reset, confirmação/reenvio e aviso de e-mail usam `auth/layout.html.twig`. Estilos e tokens estão em CSS externo e interações em `auth.js`; sem alterar o layout dos módulos internos. Mensagens de sessão expirada mantêm status 419 com formulário e CSRF atual. Botões de senha têm nome acessível, envio tem estado de progresso e os formulários funcionam sem JavaScript. Veja [design system e teste visual](DESIGN_SYSTEM.md).

Testes Chromium com axe A/AA verificam 320/390/768/1024/1440px, teclado, foco de erro, contraste, cadastro/SMTP/confirmação, recuperação/reset e login sem JS. Previews descartáveis ficam em storage/previews (fora do Git); não contêm dados reais. Não foi realizado teste com leitor de tela ou teclado virtual de aparelhos físicos.
