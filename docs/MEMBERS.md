# Participantes e convites

No projeto, abra **Participantes**. Todos os participantes podem consultar nomes e papéis; apenas proprietário e administradores veem e-mails e convites pendentes. Participantes e convites são paginados em grupos de 25, usando a mesma página.

## Convidar e aceitar

1. Informe o e-mail e o papel do destinatário. Somente o proprietário pode convidar administradores; administrador pode convidar membro ou visualizador.
2. O destinatário recebe uma mensagem pelo SMTP configurado (`MAIL_*`; Mailpit no ambiente local).
3. Ao abrir o link, o destinatário entra ou cria uma conta com **o mesmo e-mail** e confirma o endereço.
4. O convite é preservado durante login/cadastro. Após confirmar o e-mail, pode acessar **Ver convite pendente** no painel ou abrir novamente o link recebido.
5. O acesso ao tenant e ao projeto é concedido somente ao clicar em **Aceitar convite**.

Abrir o link não concede acesso. Participar de um tenant não concede acesso aos demais projetos dele. Um usuário pode manter seu tenant pessoal e participar de projetos de outros tenants.

Links expiram em sete dias, têm uso único e são armazenados no banco somente como hash SHA-256. O token recebido é mantido temporariamente na sessão para continuar o fluxo após autenticação; a URL é limpa com redirecionamento e usa Referrer-Policy: no-referrer. Logout e expiração da sessão removem esse contexto; nesse caso, abra novamente o link do e-mail. Configure os logs de acesso sem query strings na rota de convite, como nas rotas de tokens de conta.

Na lista de pendentes, **Reenviar** substitui o token anterior; **Cancelar** inutiliza o link. Convites expirados não aparecem na lista: envie outro pelo formulário. Ao aceitar, o backend revalida e-mail verificado, token, estado do projeto e autoridade atual do remetente. Projetos arquivados ou excluídos não aceitam convites. A permissão de quem convidou precisa continuar válida para o papel proposto.

Em falha SMTP, o convite permanece salvo e a tela informa que o envio falhou; nenhum acesso é concedido. Corrija a configuração e reenvie.

## Gerenciar papéis e propriedade

| Operação | Proprietário | Administrador | Membro/Visualizador |
|---|---|---|---|
| Convidar/gerenciar membro ou visualizador | Sim | Sim | Não |
| Convidar/gerenciar administrador | Sim | Não | Não |
| Cancelar/reenviar convite de administrador | Sim | Não | Não |
| Transferir propriedade | Sim | Não | Não |
| Remover/rebaixar proprietário | Não | Não | Não |

A remoção exige confirmação e revoga o acesso ao projeto imediatamente, mas preserva o vínculo ao tenant para não afetar outros projetos ou a conta pessoal.

A transferência exige confirmação e um participante com e-mail verificado. O novo proprietário recebe controle total; o anterior passa a administrador. O tenant e os demais projetos permanecem os mesmos. Não há edição de participantes em projetos arquivados.

Criação/reenvio/cancelamento/aceite de convite, inclusão/remoção de membro, mudança de papel e transferência de propriedade geram auditoria transacional. Logs não armazenam tokens ou seus hashes. Falhas de auditoria revertem as alterações.

## Rotas e limites

| Método | Caminho | Operação |
|---|---|---|
| GET | /projects/{id}/members | Listar participantes e pendências; `page=N` |
| POST | /projects/{id}/invitations | Enviar/reenviar; `email`, `role` |
| POST | /projects/{id}/invitations/cancel | Cancelar; `invitation_id` |
| POST | /projects/{id}/members/role | Alterar papel; `user_id`, `role` |
| POST | /projects/{id}/members/remove | Remover; `user_id`, `confirm=remove` |
| POST | /projects/{id}/transfer | Transferir; `user_id`, `confirm=transfer` |
| GET | /invitations/accept?token=... | Guardar contexto e redirecionar à URL sem token |
| GET/POST | /invitations/accept | Exibir/aceitar convite da sessão |

Toda alteração exige `_token` CSRF e conta verificada. IDs e papéis enviados pelo cliente são validados; no aceite, identidade e papel vêm da conta autenticada e do convite persistido.

Limites por 15 minutos: envio permite 30 solicitações por usuário/IP e 3 por projeto/e-mail; aceite permite 20 por usuário e 30 por IP. Reenvios e tentativas inválidas contam. Como em autenticação, configure o IP real de forma confiável no servidor quando houver proxy.

## Instalação e testes

Aplique `php bin/migrate.php` (migration 005). Não há nova variável de ambiente nem dependência Composer neste incremento.

Em banco **descartável**, com servidor PHP e Mailpit isolado configurados conforme [AUTHENTICATION.md](AUTHENTICATION.md):

```sh
AUTH_TEST_DATABASE=disposable MAILPIT_TEST_URL=http://127.0.0.1:8025 php tests/members.php
```

O teste cria usuários/projetos, envia somente ao Mailpit, simula expiração, verifica papéis/IDOR/CSRF/limites e usa triggers e processos simultâneos para testar rollback e consumo concorrente. Não execute no banco local de uso diário ou em produção.

Quadro Kanban disponível conforme [KANBAN.md](KANBAN.md). Próximo módulo: gestão detalhada de tarefas. Não há gerenciamento de organizações ou planos neste incremento.
