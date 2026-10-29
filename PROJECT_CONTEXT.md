# PROMPT MASTER | SaaS Kanban em PHP 8.3+

## 1. Papel e objetivo

Atue como arquiteto de software e desenvolvedor sênior especializado em PHP, MariaDB e aplicações SaaS.

Desenvolva um sistema web de gerenciamento de projetos e tarefas, inspirado no Trello, com interface **Kanban**, drag and drop, colaboração entre equipes, controle de permissões e histórico persistente.

O sistema deve funcionar para usuários individuais e equipes, com arquitetura multi-tenant desde o início, mas sem complexidade desnecessária.

**Prioridades:** simplicidade, segurança, desempenho, usabilidade, manutenção e economia de recursos.

## 2. Stack obrigatória

- **PHP:** ^8.3, orientação a objetos e PSR-4.
- **Gerenciador:** Composer.
- **Roteamento:** Pecee Simple Router 5.x.
- **Templates:** Twig 3.x.
- **Frontend:** Bootstrap 5.3+, Bootstrap Icons.
- **JavaScript:** Vanilla JS, Fetch API.
- **Kanban:** SortableJS.
- **Banco:** MariaDB 11+, PDO com prepared statements.
- **Ambiente:** vlucas/phpdotenv.
- **Validação:** Rakit Validation.
- **Arquitetura:** MVC modular, Services e Repositories quando necessários.
- **Servidor:** Apache, compatível com Laragon e Docker.

Não utilizar Laravel, Symfony, React, Vue ou frameworks SPA.

Verificar compatibilidade das bibliotecas antes de instalá-las.

## 3. Decisões funcionais definitivas

1. **Cadastro público:** qualquer pessoa pode criar sua própria conta.
2. **Múltiplos responsáveis:** uma tarefa pode ser atribuída a vários participantes do mesmo projeto.
3. **Kanban personalizável:** cada projeto administra suas próprias colunas, nomes e ordenação.
4. **Histórico individual consolidado:** o usuário visualiza suas atividades e tarefas de todos os projetos aos quais possui acesso.

Essas decisões são requisitos fixos. Não solicitar nova confirmação.

## 4. Autenticação e usuários

Implementar:

- Cadastro público, login e logout.
- Verificação de e-mail e recuperação de senha.
- Senhas com `password_hash()` e `password_verify()`.
- Sessões seguras e regeneração do ID no login.
- Perfil e configurações pessoais.
- Cadastro de conta pessoal com tenant próprio.
- Participação do mesmo usuário em diferentes projetos e organizações.

Cada conta recém-criada deve receber automaticamente um tenant pessoal.

## 5. Projetos e equipes

Permitir:

- Criar, editar, arquivar e excluir logicamente projetos.
- Projetos pessoais ou compartilhados.
- Nome, descrição, proprietário, data inicial e previsão de término.
- Convites de participantes por e-mail.
- Gerenciamento de membros e permissões.
- Listagem de projetos próprios e compartilhados.
- Visão consolidada das tarefas atribuídas ao usuário.

### Permissões por projeto

| Perfil | Permissões |
|---|---|
| Proprietário | Controle total e transferência de propriedade |
| Administrador | Gerenciar membros, colunas e tarefas |
| Membro | Criar, editar e movimentar tarefas conforme regras do projeto |
| Visualizador | Somente leitura |

Toda autorização deve ser validada no backend.

Convites não devem conceder acesso antes da aceitação.

## 6. Quadro Kanban

Interface com colunas personalizáveis por projeto.

Colunas iniciais sugeridas:

- Backlog
- A Fazer
- Em Andamento
- Em Revisão
- Concluído

Funcionalidades:

- Criar, renomear e reordenar colunas.
- Arrastar cards entre colunas.
- Reordenar cards dentro da mesma coluna.
- Alterar automaticamente a coluna/status da tarefa ao soltá-la.
- Persistir alterações via Fetch API, sem recarregar a página.
- Registrar movimentações no histórico.
- Restaurar a posição visual caso a gravação falhe.
- Impedir remoção de coluna com tarefas sem tratamento explícito.

Cada coluna deve possuir identificador, posição e indicação configurável de conclusão.

**Não depender de nomes fixos de colunas para identificar tarefas concluídas.**

## 7. Tarefas

Campos e funcionalidades:

- Título e descrição.
- Projeto e coluna.
- Criador e responsáveis múltiplos.
- Prioridade: baixa, normal, alta, urgente.
- Data de início prevista.
- Data de início real.
- Prazo previsto.
- Data de conclusão real.
- Checklist e subtarefas simples.
- Comentários.
- Histórico de alterações.
- Filtros por responsável, prioridade, coluna e prazo.
- Identificação de tarefas atrasadas.
- Edição em modal ou offcanvas Bootstrap.

Os responsáveis devem pertencer ao projeto e possuir autorização para visualizar a tarefa.

Ao entrar em uma coluna de conclusão, registrar a data real de conclusão. Ao sair dela, atualizar o estado de conclusão, preservando o histórico.

## 8. Histórico e auditoria

Registrar automaticamente no backend:

- Criação e edição de projetos.
- Criação e edição de tarefas.
- Alteração de coluna, posição e prioridade.
- Alteração de responsáveis.
- Mudanças de datas.
- Conclusão e reabertura.
- Comentários e alterações de checklist.
- Inclusão e remoção de participantes.

Cada evento deve conter:

- Identificador do tenant.
- Projeto e tarefa, quando aplicáveis.
- Autor da operação.
- Tipo de ação.
- Dados relevantes anteriores e novos.
- Data/hora.

Criar duas visualizações:

**Histórico do projeto:** atividades dos participantes naquele projeto.

**Meu histórico:** linha do tempo consolidada das atividades realizadas pelo usuário em todos os projetos acessíveis, com filtros por projeto, período e tipo de ação.

Exibir apenas informações que o usuário tenha permissão para consultar.

## 9. Dashboard

Criar painel simples com:

- Projetos ativos.
- Minhas tarefas pendentes.
- Tarefas em andamento.
- Tarefas concluídas.
- Tarefas atrasadas.
- Prazos próximos.
- Percentual de conclusão por projeto.
- Atividades recentes.

Utilizar consultas otimizadas, paginação quando necessária e evitar gráficos complexos no MVP.

## 10. Arquitetura SaaS multi-tenant

Adotar inicialmente **banco compartilhado com isolamento lógico por tenant**.

Regras:

- Todo usuário cadastrado recebe um tenant pessoal.
- Cada projeto pertence a um tenant.
- Usuários podem participar de múltiplos tenants.
- Participação em um tenant não concede automaticamente acesso a todos os projetos.
- Aplicar controle de tenant e de projeto em todas as consultas e operações.
- Centralizar autorização em Services ou Policies.
- Garantir integridade entre tenant, projetos, colunas e tarefas.
- Prevenir IDOR e acesso cruzado entre organizações.
- Prever expansão futura para planos, limites e assinaturas.

Não implementar cobrança, pagamentos, filas distribuídas ou microsserviços no MVP.

## 11. Modelagem do MariaDB

Criar migrations versionadas para as seguintes tabelas:

| Tabela | Finalidade |
|---|---|
| `users` | Identidade e autenticação |
| `tenants` | Contas pessoais e organizações |
| `tenant_users` | Vínculos com tenants |
| `projects` | Projetos |
| `project_members` | Usuários e permissões por projeto |
| `kanban_columns` | Colunas personalizadas e ordenação |
| `tasks` | Tarefas, datas, prioridades e posições |
| `task_assignees` | Relação N:N entre tarefas e responsáveis |
| `task_checklists` | Checklists |
| `task_checklist_items` | Itens dos checklists |
| `task_comments` | Comentários |
| `activity_logs` | Histórico e auditoria |
| `project_invitations` | Convites com tokens seguros |
| `password_reset_tokens` | Recuperação de senha |
| `email_verification_tokens` | Verificação de e-mail |

Utilizar:

- Chaves primárias e estrangeiras.
- Índices compostos adequados às consultas.
- Restrições de unicidade.
- `created_at` e `updated_at`.
- Exclusão lógica quando apropriado.
- Datas persistidas em UTC.
- Transações em operações com múltiplas alterações.

As tarefas devem armazenar `column_id` e posição. Evitar duplicar o status textual quando a coluna já representa esse estado.

## 12. Fluxo de drag and drop

Ao movimentar uma tarefa:

1. Receber tarefa, destino e posição.
2. Autenticar usuário.
3. Validar tenant, projeto e permissão.
4. Validar coluna de destino.
5. Iniciar transação.
6. Atualizar coluna e ordenação das tarefas afetadas.
7. Atualizar conclusão real, se necessário.
8. Registrar evento no histórico.
9. Confirmar transação.
10. Retornar JSON com resultado e estado persistido.

Em caso de erro, executar rollback e restaurar a interface.

Evitar inconsistências quando dois usuários movimentarem tarefas simultaneamente, utilizando bloqueios ou controle de concorrência adequado.

## 13. Estrutura de diretórios

```text
app/
  Controllers/
  Models/
  Services/
  Repositories/
  Middlewares/
  Validators/
  Policies/

bootstrap/
config/
database/
  migrations/
  seeders/
public/
  assets/
    css/
    js/
resources/
  views/
routes/
storage/
  logs/
tests/
vendor/
.env.example
composer.json
```

Utilizar PSR-4, namespaces e separação clara de responsabilidades.

Evitar abstrações que não tragam benefício prático.

## 14. Interface

Criar layout Bootstrap responsivo:

- Sidebar: Dashboard, Projetos, Minhas Tarefas, Meu Histórico e Configurações.
- Topbar: projeto atual e usuário.
- Kanban com rolagem horizontal.
- Cards compactos com título, responsáveis, prioridade e prazo.
- Modal ou offcanvas para edição.
- Indicadores discretos de atraso e conclusão.
- Mensagens de sucesso e erro.
- Layout compatível com desktop e dispositivos móveis.

Priorizar rapidez, legibilidade e poucas interações.

## 15. Segurança obrigatória

Implementar:

- Proteção CSRF em operações de alteração.
- Prepared statements com PDO.
- Escape automático no Twig.
- Validação server-side.
- Autorização em cada endpoint.
- Cookies `HttpOnly`, `Secure` em HTTPS e `SameSite`.
- Rate limit para login e recuperação de senha.
- Tokens de convite e recuperação com expiração e armazenamento seguro.
- Tratamento centralizado de exceções.
- Logs técnicos sem dados sensíveis.
- Transações e integridade referencial.

Segurança nunca deve ser sacrificada para reduzir tokens.

## 16. Etapas de desenvolvimento

**Fase 1: Base**
Composer, configuração, roteamento, Twig, conexão MariaDB, migrations e autenticação.

**Fase 2: Multi-tenancy e projetos**
Tenants pessoais, projetos, membros, convites e permissões.

**Fase 3: Kanban**
Colunas personalizáveis, cards, drag and drop e persistência.

**Fase 4: Gestão de tarefas**
Múltiplos responsáveis, datas, prioridades, checklists e comentários.

**Fase 5: Histórico e dashboard**
Auditoria, histórico consolidado, métricas e filtros.

**Fase 6: Validação**
Testes, segurança, desempenho e preparação para produção.

Cada fase deve gerar uma versão funcional e verificável.

## 17. Protocolo obrigatório de economia de tokens

Esta seção rege todas as interações durante o desenvolvimento.

### Comunicação

1. Responder em português brasileiro.
2. Ser direto, técnico e objetivo.
3. Não repetir requisitos ou decisões anteriores.
4. Não explicar conceitos básicos sem solicitação.
5. Não produzir textos introdutórios desnecessários.
6. Não apresentar várias alternativas quando existir uma solução adequada.
7. Agrupar dúvidas essenciais em uma única solicitação.
8. Não pedir confirmação de decisões já estabelecidas.

### Código

9. Gerar apenas o código necessário para a etapa atual.
10. Não antecipar funcionalidades futuras.
11. Não reescrever arquivos completos quando uma alteração localizada for suficiente.
12. Em arquivos existentes, mostrar caminho e trecho alterado, com contexto suficiente para aplicação segura.
13. Se houver acesso direto ao projeto, editar os arquivos em vez de reproduzir seu conteúdo na resposta.
14. Não gerar código ilustrativo ou pseudocódigo quando for solicitado código funcional.
15. Reutilizar componentes, serviços e bibliotecas existentes.
16. Não instalar dependências desnecessárias.
17. Manter consistência de nomes, rotas, estrutura e banco.
18. Não refatorar partes funcionais sem necessidade.
19. Não gerar documentação extensa automaticamente.
20. Não omitir validações, testes essenciais ou tratamento de erros.

### Continuidade e contexto

21. Manter um arquivo `PROJECT_CONTEXT.md` conciso com arquitetura, decisões, módulos concluídos e pendências.
22. Consultar esse arquivo no início de novas sessões, quando estiver disponível.
23. Atualizá-lo somente quando houver mudanças relevantes.
24. Evitar repetir arquivos ou decisões que já estejam registrados no contexto.
25. Trabalhar sempre sobre o estado real e atualizado do projeto.

### Formato padrão de resposta

Para cada etapa, responder preferencialmente com:

**Implementado:** resumo curto.

**Arquivos:** criados ou alterados.

**Teste:** comando ou procedimento necessário.

**Pendências:** somente quando existirem.

Eliminar seções vazias e explicações redundantes.

## 18. Primeira entrega esperada

Antes de programar, apresentar somente:

1. Resumo da arquitetura em até 15 linhas.
2. Modelo relacional do banco, com índices e principais relacionamentos.
3. Estrutura inicial de arquivos.
4. Riscos técnicos relevantes.
5. Plano da Fase 1.

Não gerar o sistema inteiro de uma vez.

Após a arquitetura estar definida, iniciar a implementação incremental pela Fase 1.

## Resultado esperado

Uma aplicação web Kanban simples, rápida, segura e funcional, que permita:

- Usuários criarem suas próprias contas.
- Gerenciar projetos individuais ou em equipe.
- Compartilhar e delegar tarefas para vários responsáveis.
- Personalizar quadros e movimentar cards.
- Registrar todas as atividades no MariaDB.
- Consultar histórico pessoal consolidado.
- Evoluir para um SaaS comercial multi-tenant.

**Princípio central:** entregar o menor código sustentável que resolva corretamente cada requisito, evitando complexidade prematura e desperdício de tokens.

## 18. Versionamento Git, documentação e gestão de mudanças

Todo o desenvolvimento deve ser versionado com Git, com rastreabilidade das alterações e documentação atualizada durante o ciclo de vida do projeto.

### 18.1. Versionamento com Git

- Inicializar e manter um repositório Git desde a Fase 1.
- Utilizar `.gitignore` adequado para PHP, Composer, arquivos temporários, logs, credenciais e configurações locais.
- Nunca versionar `.env`, senhas, tokens ou arquivos sensíveis.
- Versionar `composer.json`, `composer.lock`, migrations e arquivos de configuração de exemplo.
- Adotar **Conventional Commits**:
  - `feat:` nova funcionalidade.
  - `fix:` correção.
  - `refactor:` melhoria interna sem alteração funcional.
  - `docs:` documentação.
  - `test:` testes.
  - `chore:` manutenção.
  - `security:` correções relacionadas à segurança.
- Utilizar a branch `main` como versão estável.
- Criar branches `feature/`, `fix/` e `hotfix/` quando necessário.
- Manter commits pequenos, objetivos e relacionados a uma única alteração.
- Utilizar tags com Semantic Versioning (`v0.1.0`, `v0.2.0`, `v1.0.0`).
- A aprovação de um incremento autoriza seu commit e o início automático do próximo, conforme AGENTS.md. Não fazer push, merge, deploy em produção ou alterações destrutivas sem autorização específica.

### 18.2. Documentação obrigatória

Manter a seguinte estrutura:

```text
README.md
CHANGELOG.md
PROJECT_CONTEXT.md
docs/
  INSTALLATION.md
  USER_GUIDE.md
  ARCHITECTURE.md
  DATABASE.md
  API.md
  DEVELOPMENT.md
  DECISIONS.md
```

Responsabilidades:

- **README.md:** apresentação, funcionalidades, requisitos e início rápido.
- **CHANGELOG.md:** histórico de versões seguindo Keep a Changelog.
- **PROJECT_CONTEXT.md:** estado resumido do desenvolvimento, decisões e próximas etapas, otimizado para continuidade entre sessões de IA.
- **INSTALLATION.md:** instalação e configuração em Laragon, Apache e Docker.
- **USER_GUIDE.md:** manual de utilização para usuários, equipes e administradores, com procedimentos claros.
- **ARCHITECTURE.md:** estrutura MVC, componentes, autenticação, permissões e multi-tenancy.
- **DATABASE.md:** tabelas, relacionamentos, índices e migrations.
- **API.md:** endpoints internos, parâmetros, respostas e regras de autorização.
- **DEVELOPMENT.md:** padrões de código, ambiente, testes e fluxo Git.
- **DECISIONS.md:** registro conciso de decisões arquiteturais relevantes (ADRs simplificados).

Criar cada documento quando sua funcionalidade ou conteúdo estiver definido. Evitar arquivos vazios ou documentação especulativa.

### 18.3. Gestão de mudanças e rastreabilidade

Adotar um fluxo simplificado de gerenciamento de projeto baseado em boas práticas ágeis.

Cada funcionalidade ou correção deve possuir, quando aplicável:

1. Identificador de tarefa ou issue.
2. Descrição objetiva da necessidade.
3. Critérios de aceitação verificáveis.
4. Arquivos e módulos afetados.
5. Implementação e testes.
6. Atualização da documentação correspondente.
7. Registro no CHANGELOG quando relevante para usuários ou operações.
8. Commit com mensagem padronizada, mediante autorização.

Organizar o desenvolvimento em um backlog com status:

**Backlog → A Fazer → Em Andamento → Em Revisão → Concluído.**

Utilizar GitHub Issues ou ferramenta equivalente quando disponível, evitando registros duplicados.

### 18.4. Definition of Done

Uma funcionalidade será considerada concluída quando:

- Estiver implementada e funcional.
- Respeitar as regras de segurança e autorização.
- Possuir testes proporcionais ao risco da alteração.
- Não apresentar regressões conhecidas.
- Tiver migrations versionadas, quando aplicável.
- Possuir documentação atualizada, quando necessária.
- Tiver histórico de mudanças registrado.
- Estiver pronta para revisão e versionamento Git.

### 18.5. Economia de tokens na documentação

- Documentar apenas informações confirmadas e implementadas.
- Atualizar somente as seções afetadas.
- Não reescrever documentos inteiros sem necessidade.
- Não duplicar o mesmo conteúdo em vários arquivos.
- Preferir Markdown objetivo, tabelas e exemplos curtos.
- Manter `PROJECT_CONTEXT.md` conciso, preferencialmente com até 150 linhas.
- Registrar decisões permanentes em `DECISIONS.md`, evitando inflar o contexto de trabalho.
- Gerar mensagens de commit curtas e descritivas.
- Manter documentação de usuário separada da documentação técnica.
- Não incluir todo o conteúdo da documentação nas respostas quando os arquivos puderem ser editados diretamente.

**Regra obrigatória:** nenhuma alteração relevante deve ficar sem rastreabilidade. Git registra a evolução do código; CHANGELOG registra mudanças por versão; documentação explica o funcionamento; PROJECT_CONTEXT.md mantém a continuidade do desenvolvimento.

## Estado em 2026-10-29

- PHP ^8.3 confirmado para acompanhar produção.
- Base iniciada: bootstrap, configuração, MVC com Pecee/Twig, tratamento de erros e PDO em UTC.
- Migration inicial: users, tenants e tenant_users; executor CLI com controle de versões e bloqueio.
- Cadastro/login/logout, tenant pessoal transacional, sessões seguras, CSRF, Rakit e rate limit implementados.
- Testes executados em PHP 8.3.32 e MariaDB 11.8.8 descartável; testes HTTP, rollback e expiração aprovados.
- Verificação de e-mail e recuperação de senha implementadas: PHPMailer SMTP, tokens SHA-256 de uso único, reenvio e rate limit.
- Migration 003: tabelas de tokens e users.session_version; redefinição revoga sessões anteriores.
- Painel exige e-mail confirmado, inclusive de usuários preexistentes. Configurar MAIL_* e APP_URL antes de usar.
- Testes com Mailpit isolado: SMTP, expiração, uso único concorrente, rollback e falha de envio aprovados.
- Ambiente local configurado para Mailpit: MAIL_HOST=mail, porta 1025, sem TLS/autenticação; SMTP de produção segue via MAIL_*.
- Primeiro incremento de projetos implementado: CRUD, arquivar/restaurar, exclusão lógica, paginação, políticas e auditoria transacional.
- Migration 004: projects, project_members e activity_logs com integridade composta entre tenants/projetos/usuários.
- Criação apenas no tenant pessoal; consultas exigem vínculo no tenant e projeto.
- Testes de projetos: papéis, IDOR, CSRF, datas, FKs, paginação e rollback aprovados em banco descartável.
- Convites e participantes implementados: SMTP, hash de token, validade de 7 dias, reenvio/cancelamento, aceite autenticado, papéis e transferência.
- Migration 005: project_invitations. Aceite cria vínculos tenant/projeto apenas para o e-mail verificado correspondente.
- Admin gerencia membro/visualizador; somente proprietário gerencia administradores e transfere propriedade.
- ActivityLogger compartilhado pelos serviços; mutações serializadas por projeto e auditadas na mesma transação.
- Testes de convites: SMTP, cadastro/aceite, e-mail errado, CSRF, papéis, expiração, limites, concorrência e rollback.
- Uso e rotas em docs/MEMBERS.md. Próximo incremento: colunas e quadro Kanban.
- Uso, rotas e limites atuais em docs/PROJECTS.md.
- Autenticação e testes documentados em docs/AUTHENTICATION.md.
- Documentação técnica em docs/ARCHITECTURE.md e docs/DATABASE.md.

## Continuidade e aprovação

- Regra permanente em AGENTS.md: aprovação autoriza commit do incremento e avanço automático, exceto pedido explícito de não seguir/parar/interromper.
- Incremento de convites/participantes aprovado; registrar em commit antes de iniciar Kanban.
- Próximo trabalho: Fase 3, quadro Kanban com colunas, cards básicos e movimentação persistida.
