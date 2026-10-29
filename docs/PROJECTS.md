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
