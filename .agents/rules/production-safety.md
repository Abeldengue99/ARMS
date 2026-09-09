# Regras de Segurança para Produção — ARMS

> [!CAUTION]
> **O projeto ARMS está em PRODUÇÃO com utilizadores ativos. Todas as regras abaixo são OBRIGATÓRIAS e devem ser consultadas ANTES de qualquer alteração.**

---

## 1. Análise Obrigatória Antes de Qualquer Alteração

- Antes de modificar qualquer ficheiro, o agente DEVE analisar profundamente o código existente.
- Deve compreender todas as dependências, fluxos e impactos potenciais da alteração.
- Deve mapear todos os ficheiros que serão afetados direta e indiretamente.
- Nunca assumir que uma alteração é "simples" — sempre verificar.

## 2. Proposta de Implementação Obrigatória

- Após a análise, o agente DEVE apresentar um **plano de implementação detalhado** ao utilizador.
- O plano deve incluir: ficheiros afetados, alterações propostas, riscos identificados e estratégia de mitigação.
- O agente DEVE **aguardar aprovação explícita** do utilizador antes de proceder com qualquer implementação.
- Nunca implementar sem aprovação, mesmo que a alteração pareça trivial.

## 3. Implementação com Máxima Cautela

- Implementar com calma e precisão cirúrgica — sem pressa.
- Não afetar absolutamente NADA do que já funciona no sistema.
- Cada alteração deve ser mínima, focada e reversível quando possível.
- Verificar o impacto em todas as áreas do sistema antes de concluir.
- Preservar toda a lógica, estilos e funcionalidades existentes.

## 4. Tolerância Zero a Erros

- O código implementado deve estar **100% funcional e livre de erros**.
- Nunca introduzir bugs, warnings, erros de sintaxe ou comportamentos inesperados.
- Validar manualmente toda a lógica antes de apresentar como concluído.
- Testar mentalmente todos os cenários possíveis (sucesso, erro, edge cases).

## 5. Commit Apenas com Permissão Explícita

- O agente **NUNCA** deve fazer commit no Git sem perguntar ao utilizador primeiro.
- Após a implementação, o agente deve apresentar um resumo completo das alterações.
- O utilizador fará o deploy imediatamente após o commit — portanto, o código deve estar perfeito.
- Só proceder com o commit após confirmação explícita do utilizador.

## 6. Preservação Total do Sistema

- Não alterar funcionalidades que não estejam relacionadas com a tarefa em curso.
- Não remover, modificar ou reorganizar código existente sem necessidade e autorização.
- Não alterar configurações globais, dependências ou estrutura de pastas sem aprovação.
- O sistema deve continuar a funcionar exatamente como antes, com a nova funcionalidade adicionada.

---

## Fluxo Obrigatório de Trabalho

```
1. CONSULTAR estas regras
2. ANALISAR o estado atual do código
3. APRESENTAR proposta de implementação
4. AGUARDAR aprovação do utilizador
5. IMPLEMENTAR com máxima cautela
6. VERIFICAR que nada foi afetado
7. APRESENTAR resumo das alterações
8. PERGUNTAR antes de fazer commit
9. Commit apenas se 100% sem erros
```

---

> [!IMPORTANT]
> **Estas regras têm prioridade máxima e não podem ser ignoradas em nenhuma circunstância. O projeto está em produção e qualquer erro afeta utilizadores reais.**
