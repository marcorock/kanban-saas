# Instruções para o Codex — Kanban SaaS

- Responda em português (pt-BR). Trabalhe apenas no estado real da branch atual e preserve alterações preexistentes.
- Antes de atuar, leia **somente** a seção "Estado de continuidade" de `PROJECT_CONTEXT.md`; consulte outras seções e `docs/` apenas quando pertinentes à tarefa. Não faça varredura completa do repositório por padrão.
- Limite alterações aos arquivos necessários. Não crie funcionalidades, refatore módulos ou instale dependências fora do escopo solicitado. Se uma alteração adjacente for indispensável, explique objetivamente.
- Preserve autenticação, CSRF, autorização, isolamento por tenant/projeto, consultas parametrizadas, transações, controle de concorrência e compatibilidade sem JavaScript.
- UI: HTML em Twig; CSS e JavaScript em arquivos externos, sem estilos/scripts inline.
- Valide primeiro com verificações direcionadas aos arquivos e fluxos modificados. Para mudanças de autorização, isolamento, transações, concorrência ou schema, teste em banco **descartável**. Execute regressão completa quando o risco justificar ou quando solicitada, não automaticamente após cada ajuste visual.
- Se houver migration/schema: valide em banco descartável, revise `local-tools/reset-database.php`, atualize o registro via `local-tools/reset-database.sh --update-schema` e siga `docs/TEST_DATABASE_RESET.md`. Nunca versionar `local-tools/`, inventários ou credenciais. Aplicar migration local somente após verificação e quando necessário ao teste da mudança.
- Não limpar dados, alterar credenciais, trocar de branch, fazer push/merge/deploy ou outras operações destrutivas sem autorização explícita. Não incluir alterações preexistentes em commits.
- Ao concluir uma tarefa relevante, atualize apenas a seção "Estado de continuidade" de `PROJECT_CONTEXT.md` com o resultado e as pendências. Atualize README/CHANGELOG/docs **somente quando o comportamento documentado mudar**, sem duplicar histórico de testes.
- Um incremento aprovado autoriza commit **somente das alterações aprovadas**, com Conventional Commits. **Não iniciar automaticamente um novo incremento**; aguarde a solicitação do usuário. Pedidos de pausa prevalecem.
- Entrega: resumo curto do que mudou, testes executados e pendências, sem reproduzir arquivos inteiros nem logs extensos salvo solicitação.
