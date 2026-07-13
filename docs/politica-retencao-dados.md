# Politica de Retencao de Dados do ARMS

Data de referencia: 2026-07-10

## Objetivo

Esta politica define por quanto tempo o ARMS deve guardar dados operacionais, dados de auditoria, comentarios, anexos e rascunhos locais. A regra principal e simples: informacao oficial fica no servidor e na base de dados; rascunhos ainda nao enviados ficam apenas no navegador do utilizador por pouco tempo.

## Onde cada informacao fica guardada

| Informacao | Onde fica salvaguardada | Tempo de retencao recomendado |
| --- | --- | --- |
| Rascunhos de comentarios ainda nao enviados | `localStorage` do navegador, chaves `arms:comentario-rascunho:v1:*` | 48 horas |
| Rascunhos de edicao de comentarios | `localStorage` do navegador, chaves `arms:comentario-edicao:v1:*` | 48 horas |
| Comentarios enviados | PostgreSQL, tabela `arms.request_comment` | 7 anos apos encerramento do pedido |
| Versoes antigas de comentarios editados | PostgreSQL, tabela `arms.request_comment_revision` | 7 anos apos encerramento do pedido |
| Anexos atuais | Metadados em `arms.attachment`; ficheiros em `uploads/{storage_key}` | 7 anos apos encerramento do pedido |
| Versoes antigas de anexos atualizados | Metadados em `arms.attachment_version`; ficheiros antigos continuam em `uploads/{storage_key}` | 7 anos apos encerramento do pedido |
| Respostas formais do cliente/Aksanti | PostgreSQL, tabela `arms.request_response` | 7 anos apos encerramento do pedido |
| Timeline e auditoria de estado | PostgreSQL, tabela `arms.request_audit_log` | 10 anos apos encerramento do pedido |
| Notificacoes internas | PostgreSQL, tabela `arms.notification` | 180 dias para lidas; 365 dias para nao lidas |
| Ficheiros temporarios de sessao | `tmp/arms-sessions` do servidor | Ate terminar a sessao/expirar no servidor |
| Backups diarios | Pasta/servico de backup cifrado; em desenvolvimento pode ser `backups/` | 35 dias |
| Backups mensais | Storage externo cifrado em producao | 12 meses |
| Backups anuais | Storage externo cifrado em producao | 7 anos |

## Regras de seguranca e auditoria

1. Comentarios enviados nao dependem do cache do navegador. Depois de enviados, ficam na tabela `arms.request_comment`.
2. Quando um comentario e editado, o texto anterior deve ser guardado em `arms.request_comment_revision` antes da atualizacao.
3. Quando um anexo e atualizado, a versao anterior deve ser guardada em `arms.attachment_version` e o ficheiro antigo deve permanecer em `uploads/`.
4. A timeline em `arms.request_audit_log` e historico de auditoria. Nao deve ser editada nem apagada manualmente.
5. Rascunhos locais nao entram em auditoria porque ainda nao foram submetidos ao sistema.
6. Em caso de disputa, auditoria, processo legal ou investigacao interna, a limpeza automatica deve ser suspensa para os pedidos envolvidos.

## Politica de limpeza

Nenhuma limpeza destrutiva deve correr sem relatorio previo. O processo correto e:

1. Gerar relatorio dos registos candidatos a expiracao.
2. Validar se nao existe bloqueio legal/auditoria.
3. Criar backup antes da eliminacao.
4. Executar limpeza.
5. Guardar log da limpeza por 7 anos.

## Estado atual implementado

- Cache de rascunho de comentarios: implementado em `pedido-detalhe.html`, com retencao de 48 horas no navegador.
- Historico de comentarios editados: implementado em `api/comentario-editar.php`, usando `arms.request_comment_revision`.
- Historico de anexos atualizados: implementado em `api/anexo-atualizar.php`, usando `arms.attachment_version`.
- Dados oficiais continuam guardados no PostgreSQL e os ficheiros continuam em `uploads/`.
- Painel administrativo de retencao: implementado em `admin-utilizadores.html`, visivel apenas para administradores.
- API de relatorio e limpeza controlada: implementada em `api/retencao.php`.
- Historico de relatorios/limpezas: guardado em `arms.data_retention_run`.
- Relatorios JSON: preferencialmente `backups/retencao/`; em desenvolvimento, se a pasta `backups` nao estiver gravavel, o sistema usa `tmp/retencao-relatorios/`.
- Rotina agendada de relatorio: `php scripts/retencao-relatorio-agendado.php`.

## Como a limpeza deve funcionar

1. A rotina agendada gera apenas o relatorio de candidatos expirados.
2. O Super Admin valida o relatorio no painel administrativo.
3. Ao autorizar a limpeza, o sistema gera novo relatorio previo e remove automaticamente apenas notificacoes expiradas.
4. Pedidos, comentarios, anexos, respostas formais e timeline ficam preservados e apenas sinalizados para revisao manual.
