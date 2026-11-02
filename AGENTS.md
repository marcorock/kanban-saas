# Regras de continuidade

- Responder em português brasileiro e trabalhar sobre o estado real do repositório.
- Consultar PROJECT_CONTEXT.md no início; registrar estado, validação, limitações e próximo incremento ao concluir trabalho relevante.
- Quando o usuário aprovar um incremento concluído, essa aprovação autoriza criar o commit das alterações aprovadas (Conventional Commits) e iniciar o próximo incremento planejado, sem pedir nova confirmação.
- Se o usuário pedir explicitamente para não seguir, parar ou interromper, respeitar a restrição. A restrição de continuidade não impede o commit do incremento aprovado, salvo se também proibir o commit.
- Não incluir trabalho incompleto ou ainda não aprovado no commit do incremento anterior. Novos incrementos aguardam sua própria aprovação para commit.
- Manter README, CHANGELOG e documentação afetada coerentes com o implementado. PROJECT_CONTEXT.md deve indicar o último incremento aprovado, o trabalho atual e o próximo passo; Git mantém os hashes exatos das mudanças.
- Não fazer push, merge, deploy em produção ou alterações destrutivas apenas por aprovação de um incremento. Preservar credenciais e configurações locais fora do Git.
- Testar mudanças de autorização, isolamento de tenant, transações e concorrência em banco descartável; aplicar migrations verificadas ao ambiente local para permitir testes do usuário.
- Nas novas telas e revisões de UI, manter HTML em Twig, estilos em CSS externo e comportamento em JavaScript externo; não introduzir estilos ou scripts inline.

- A cada alteração no banco/schema ou migration, revisar o reset local em local-tools/reset-database.php, validar em banco descartável, atualizar seu registro com local-tools/reset-database.sh --update-schema e documentar a alteração conforme docs/TEST_DATABASE_RESET.md. A pasta local-tools/ é intencionalmente ignorada pelo Git; nunca versionar scripts locais, inventários ou credenciais nela.
