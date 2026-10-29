# Arquitetura

PHP ^8.3, Composer e PSR-4 (`App\` → `app/`). A raiz pública é exclusivamente `public/`.

`public/index.php` carrega `bootstrap/app.php`, configura Twig e registra as rotas em `routes/web.php`. Controllers recebem suas dependências explicitamente. Configurações ficam em `config/`, templates em `resources/views/` e acesso PDO em `app/Infrastructure/Database.php`.

Datas e conexão SQL usam UTC. PDO usa prepared statements nativos e exceções; Twig escapa HTML. Erros HTTP são tratados no ponto de entrada, sem expor mensagens de exceções. Logs técnicos usam o destino configurado no PHP.

## Decisões para os próximos incrementos

Banco compartilhado por tenant; acesso aos projetos exigirá também vínculo e permissão no projeto. Cadastro deverá criar usuário, tenant pessoal e vínculo em uma transação. A autenticação está implementada; consultas de projetos ainda não existem.

Cadastro, login/logout, validação Rakit, sessões, CSRF e limites implementados; detalhes em [AUTHENTICATION.md](AUTHENTICATION.md). Próximos passos da Fase 1: verificação de e-mail e recuperação de senha. SortableJS fica para a fase Kanban.
