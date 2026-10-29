# Kanban SaaS

Sistema de projetos e tarefas com PHP, MariaDB e arquitetura multi-tenant. Requisitos e planejamento em [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md).

## Estado atual

Base implementada: bootstrap, configuração, roteamento Pecee, controller, Twig e PDO MariaDB em UTC. Migration inicial de usuários e tenants com executor CLI. Cadastro, login/logout, verificação de e-mail, recuperação de senha e dashboard protegido implementados. Projetos disponíveis com criação, consulta, edição, arquivamento e exclusão lógica; veja [uso e permissões](docs/PROJECTS.md). Convites por e-mail, participantes e transferência de propriedade disponíveis; veja [equipes](docs/MEMBERS.md). Quadro Kanban com colunas personalizáveis, tarefas básicas e drag and drop disponível; veja [uso e testes](docs/KANBAN.md). Detalhes de tarefas, múltiplos responsáveis, prioridades, datas, checklists, comentários e filtros disponíveis; veja [tarefas](docs/TASKS.md). Dashboard, Minhas Tarefas e históricos pessoais/por projeto disponíveis; veja [relatórios e regras de contagem](docs/REPORTS.md). Perfil e configurações pessoais disponíveis em `/settings`: nome, troca de senha e encerramento das demais sessões. Verificação CLI, cabeçalhos de segurança e regressão da Fase 6 disponíveis; veja [instalação e preparação para produção](docs/PRODUCTION.md) e [resultados/limitações](docs/VALIDATION.md). Homologação operacional ainda pendente.

Primeiro incremento de UI/UX disponível nas telas de acesso: login, cadastro, recuperação/redefinição de senha e confirmação de e-mail. Identidade Fluxo Claro, mobile first, com Twig, CSS e JavaScript separados; veja o [design system](docs/DESIGN_SYSTEM.md). Telas internas serão redesenhadas em incrementos próprios. A homologação operacional permanece adiada.

Requisitos: PHP ^8.3 com PDO MySQL, Composer, MariaDB 11+ e Apache com mod_rewrite (ou servidor PHP para desenvolvimento).

## Ambiente

Com PHP e Composer disponíveis:

```sh
composer install
composer validate
composer check-platform-reqs
cp .env.example .env
php -S localhost:8000 -t public
```

Copie o exemplo somente se ainda não possuir `.env`. No Apache, configure o DocumentRoot para `public/`; as regras de reescrita estão em `public/.htaccess` (requer AllowOverride).

## Autenticação

Após instalar as dependências e aplicar as migrations, configure SMTP (`MAIL_*`) e APP_URL no `.env`; acesse `/register` ou `/login`. O painel exige confirmação do e-mail. Veja [segurança, testes e pendências](docs/AUTHENTICATION.md).

## Banco e arquitetura

Após configurar o banco, execute `php bin/migrate.php`. Veja [schema e limitações](docs/DATABASE.md) e [arquitetura e próximos incrementos](docs/ARCHITECTURE.md).

Confira a instalação com `php bin/check.php` (somente leitura); em produção, use `php bin/check.php --production` após configurar o ambiente e aplicar as migrations. O ambiente local pode ser validado com a imagem Docker PHP 8.3 disponível; nenhum servidor de produção foi alterado.

## Continuidade

A aprovação de um incremento autoriza seu commit e o início do próximo, salvo pedido explícito para parar. Veja [regras](AGENTS.md), [histórico consolidado](docs/DEVELOPMENT.md) e [estado atual](PROJECT_CONTEXT.md).

## Regressão

Em banco e Mailpit descartáveis, com migrations e servidor HTTP configurados: `AUTH_TEST_DATABASE=disposable php tests/run.php`. O runner executa as suítes sequencialmente e cria fixtures/triggers/usuário SQL temporário; não use o banco de uso diário. Testes de Chromium e resultados estão em [VALIDATION.md](docs/VALIDATION.md).
