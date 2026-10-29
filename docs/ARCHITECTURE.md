# Arquitetura

PHP ^8.3, Composer e PSR-4 (`App\` → `app/`). A raiz pública é exclusivamente `public/`.

`public/index.php` carrega `bootstrap/app.php`, configura Twig e registra as rotas em `routes/web.php`. Controllers recebem suas dependências explicitamente. Configurações ficam em `config/`, templates em `resources/views/` e acesso PDO em `app/Infrastructure/Database.php`.

Datas e conexão SQL usam UTC. PDO usa prepared statements nativos e exceções; Twig escapa HTML. Erros HTTP são tratados no ponto de entrada, sem expor mensagens de exceções. Logs técnicos usam o destino configurado no PHP.

## Decisões para os próximos incrementos

Banco compartilhado por tenant; acesso aos projetos exigirá também vínculo e permissão no projeto. Cadastro cria usuário, tenant pessoal e vínculo em uma transação. Projetos usam ProjectRepository para consultas com escopo de tenant/projeto, ProjectPolicy para autorização e ProjectService para transações e auditoria. AuthService::currentUser centraliza a validação da sessão e sua versão.

Cadastro, login/logout, validação Rakit, sessões, CSRF e limites implementados; detalhes em [AUTHENTICATION.md](AUTHENTICATION.md). Verificação de e-mail e recuperação de senha implementadas com tokens transacionais, SMTP e revogação de sessões. Projetos implementados conforme [PROJECTS.md](PROJECTS.md). Participantes, convites e transferência de propriedade usam ProjectMemberService, com revalidação de autorização e bloqueio do projeto para serializar mutações. ActivityLogger centraliza a persistência de auditoria dentro das transações dos serviços. Fluxos em [MEMBERS.md](MEMBERS.md). Próximo módulo: quadro Kanban. SortableJS fica para a fase Kanban.
