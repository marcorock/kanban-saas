# Projetos — primeiro incremento

Após confirmar o e-mail, acesse **Painel → Meus projetos** ou `/projects`.

- **Novo projeto:** nome obrigatório (até 120 caracteres), descrição opcional (até 5.000), data inicial e previsão de término opcionais. O término não pode anteceder o início.
- O projeto é criado no tenant pessoal do usuário, que recebe o papel de proprietário. Tenant, proprietário e papel não são escolhidos pelo formulário.
- A listagem mostra projetos próprios e compartilhados acessíveis, com filtro de ativos/arquivados e paginação de 25 itens.
- **Editar:** disponível ao proprietário e administrador enquanto o projeto estiver ativo.
- **Arquivar/restaurar:** somente proprietário. Arquivar mantém a consulta, mas impede edição dos dados.
- **Excluir:** somente proprietário, mediante confirmação explícita. A exclusão é lógica; o registro e a auditoria permanecem no banco, mas o projeto fica indisponível a todos os participantes. Não há restauração de excluídos na interface.

## Acesso

Toda rota exige sessão válida e e-mail confirmado. A versão da sessão é consultada no banco, de forma que redefinir a senha também revoga acesso às rotas de projetos.

Consultas exigem simultaneamente vínculo no tenant e no projeto. Participar do tenant não concede acesso automático. Um ID alheio ou excluído retorna 404; uma ação sem permissão sobre projeto acessível retorna 403. Campos de tenant, proprietário e papel enviados pelo cliente são ignorados.

Neste incremento, os papéis têm estas permissões sobre **dados do projeto**:

| Papel | Consultar | Editar projeto ativo | Arquivar/restaurar/excluir |
|---|---|---|---|
| Proprietário | Sim | Sim | Sim |
| Administrador | Sim | Sim | Não |
| Membro | Sim | Não | Não |
| Visualizador | Sim | Não | Não |

Convites, gerenciamento de participantes e transferência de propriedade estão disponíveis conforme [MEMBERS.md](MEMBERS.md). Criação e gerenciamento de organizações ainda não possuem interface ou endpoints. Permissões de tarefas e colunas serão implementadas nesses módulos.

## Rotas

| Método | Caminho | Operação |
|---|---|---|
| GET | /projects | Listagem; `status=archived`, `page=N` |
| GET | /projects/new | Formulário de criação |
| POST | /projects | Criar |
| GET | /projects/{id} | Consultar |
| GET | /projects/{id}/edit | Formulário de edição |
| POST | /projects/{id} | Atualizar |
| POST | /projects/{id}/archive | Arquivar |
| POST | /projects/{id}/restore | Restaurar arquivado |
| POST | /projects/{id}/delete | Excluir logicamente; exige `confirm=delete` |

Todas as alterações exigem `_token` CSRF. Criação e alterações usam transações; cada mudança efetiva gera `activity_logs` com tenant, projeto, autor, ação, valores anteriores/novos e data UTC. Repetir arquivamento sem mudança não duplica o evento. O histórico do projeto e as visões consolidadas estão disponíveis conforme [REPORTS.md](REPORTS.md).

## Instalação e teste

Aplique `php bin/migrate.php` para criar as tabelas da migration 004. Em ambiente **descartável**, com as migrations aplicadas e o servidor HTTP de teste iniciado conforme [AUTHENTICATION.md](AUTHENTICATION.md):

```sh
AUTH_TEST_DATABASE=disposable AUTH_TEST_URL=http://127.0.0.1:8000 php tests/projects.php
```

O teste cria usuários/projetos, atribui papéis diretamente no banco, valida FKs, revoga sessões e cria trigger temporário para testar rollback. Não execute no banco de uso diário ou produção.

## UI da listagem — Fluxo Claro

`/projects` e `/projects?status=archived` usam cards mobile first, com nome, papel atual, indicação de projeto próprio/compartilhado, prévia de descrição e prazo. A prévia tem até 160 caracteres e duas linhas visuais; o conteúdo completo permanece nos detalhes. Nomes ficam completos e podem quebrar linha. Não são exibidos progresso, participantes ou contagens de tarefas simulados.

Ativos e Arquivados são links GET com indicação do estado selecionado; não exigem JavaScript. Cards oferecem quadro e detalhes. Arquivados deixam explícito que o quadro é de consulta; restaurar/editar continua dependendo das políticas e das telas existentes. O total mostra somente o filtro atual. Permanecem 25 projetos por página, ordem decrescente de ID e paginação preservando o filtro, inclusive ao voltar de página dois.

Estados vazios diferenciam ausência de ativos e de arquivados. O aviso de sucesso da listagem tem componente próprio; formulários, detalhes e participantes mantêm os componentes anteriores neste incremento. HTML em Twig e estilo em projects-list.css; nenhum script novo ou estilo inline. Repository, queries e autorização não foram alterados.

`tests/projects-list.php` integra o runner PHP e prepara contas/projetos fictícios em banco descartável. Verifica os quatro papéis, compartilhamento entre tenants, tenant sem participação no projeto, arquivados, exclusão lógica, paginação, XSS e estados vazios. Escreve somente dados de teste em storage/previews/projects-fixture.json (ignorado pelo Git), usados por `tests/projects-list-browser.cjs`.

Para Chromium, após rodar esse teste PHP no mesmo ambiente descartável, instale playwright-core e @axe-core/playwright no ambiente de testes e defina AUTH_TEST_DATABASE=disposable/NODE_PATH. O teste usa AUTH_TEST_URL/CHROMIUM_PATH quando fornecidos; PROJECTS_UI_FIXTURE pode indicar o JSON de fixture e UI_PREVIEW_DIR o destino dos screenshots. Não use fixtures no banco diário ou contas reais. Arquivos temporários podem ser removidos ao descartar o ambiente.

Próximo incremento: formulários de criação/edição; detalhes em entrega posterior. Homologação operacional permanece adiada.
