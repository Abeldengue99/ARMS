# Auditoria de organizacao e limpeza - ARMS

Data: 2026-07-23

Estado: nenhum ficheiro foi apagado ou movido nesta auditoria. A unica alteracao aplicada foi no `.gitignore`, para evitar que backups, logs, temporarios e uploads reais voltem a entrar no Git, mantendo `uploads/.htaccess` rastreavel por seguranca.

## Resumo tecnico

O projeto esta hoje como aplicacao PHP/HTML servida diretamente no XAMPP, sem `composer.json`, `package.json` ou processo de build frontend.

Divisao atual:

- Frontend publico: paginas `.html` na raiz, `css/`, `js/`, `img/`, `lang/`.
- Backend publico: endpoints em `api/*.php`.
- Banco de dados e instalacao: `bd/`.
- Rotinas internas: `scripts/`.
- Storage local/gerado: `uploads/`, `tmp/`.
- Dados mock/fixtures: `dados/`.

Verificacoes executadas:

- `php -l` em todos os ficheiros `api/*.php` e `scripts/*.php`: sem erros de sintaxe.
- JSON em `lang/*.json` e `js/manifest.json`: valido.
- Aviso visto no PHP: Xdebug tenta gravar em `c:/wamp64/logs/xdebug.log`. Isto e configuracao local, nao erro de sintaxe do projeto.

## Regra backend/frontend

Regra recomendada para nao quebrar nada:

- O frontend nunca acede diretamente a base de dados.
- O frontend chama apenas URLs publicos em `api/*.php`.
- Os endpoints de `api/` validam sessao, permissoes e entrada.
- A logica interna do backend deve ir para `app/`, mas os nomes publicos atuais em `api/*.php` devem continuar como ponte.
- `uploads/` e `tmp/` sao storage, nao frontend.
- `bd/` e `scripts/` nao devem ser chamados pelo navegador comum.

Estrutura alvo, para migrar por fases:

```text
app/
  Auth/
  Config/
  Permissions/
  Services/
  Storage/

api/
  endpoints publicos PHP, mantendo os nomes atuais

assets/
  css/
  js/
  img/

database/
  schema/
  migrations/

resources/
  lang/
  fixtures/

storage/
  uploads/
  tmp/
  reports/

tools/
  scripts internos

tests/
  verificacoes automatizadas
```

Importante: nao mover `api/`, `css/`, `js/`, `img/` ou `lang/` logo no inicio. Ha muitas referencias diretas como `fetch('api/...php')`, `<link href="css/...">`, `<script src="js/...">`, `img/...`, `lang/...` e `js/sw.js`.

## Pode limpar depois de aprovacao

Estes itens parecem seguros para remover, desde que o objetivo seja limpar o repositorio e nao manter historico local:

- `backups/**`: ja aparece como removido no Git. Risco runtime baixo, porque sao backups `.bak`, relatorios antigos e scripts de reparacao/teste, nao chamados pelo frontend atual.
- `js/*.bak`: ja aparece como removido no Git. Risco runtime baixo, porque os HTML carregam `js/*.js`, nao `js/*.bak`.
- `empty_dir/`: pasta vazia e nao rastreada. Risco runtime baixo.
- `tmp/sessions/`: pasta vazia. Risco baixo, desde que nao haja sessao ativa dependente desta pasta.

Impacto desses itens: nao deve afetar o projeto em execucao. O principal impacto e perder copias antigas locais. Antes de commit, o Git ainda consegue recuperar os ficheiros rastreados removidos.

## Nao apagar agora

Estes itens nao devem ser apagados sem validacao adicional:

- `uploads/*`: contem anexos reais ou de teste. O backend grava e le por `api/anexo-upload.php`, `api/anexo-atualizar.php` e `api/anexo-download.php`. Apagar pode quebrar anexos de pedidos se a base de dados ainda apontar para os ficheiros.
- `uploads/.htaccess`: importante para bloquear execucao/download indevido de ficheiros perigosos dentro de `uploads/`.
- `tmp/retencao-relatorios/*.json`: relatorios gerados por retencao. Podem estar referenciados em `arms.data_retention_run.report_path`. Apagar so depois de confirmar que nao precisam para auditoria.
- `lang/*.json`: usados dinamicamente por `js/i18n.js`, mesmo sem referencia literal a cada ficheiro.
- `img/icon-192x192.png` e `img/icon-512x512.png`: usados pelo `js/manifest.json`.
- `img/watermark.png`: usado no login via CSS inline.
- `img/favicon.png`: muito grande para favicon, mas esta referenciado em todas as paginas. Deve ser otimizado, nao apagado.

## Candidatos a investigar

Estes itens parecem antigos, incompletos ou nao ligados diretamente, mas nao devem ser removidos sem teste funcional:

- `js/dashboard.js`: nao esta carregado por `dashboard.html`; a dashboard usa logica inline com `js/tempo-real.js`. Candidato a remover depois de confirmar que nao e chamado dinamicamente.
- `api/dashboard-stats.php`: nao apareceu em chamadas diretas do frontend atual. Parece substituido por `api/tempo-real.php` na dashboard.
- `js/pendencias.js`: nao esta carregado por `pedidos.html`, mas procura elementos que existem nessa pagina. Decisao: ou ligar corretamente esse script, ou remover junto com a UI se a funcionalidade nao for desejada.
- `js/validacao.js`: nao apareceu carregado pelos HTML. Candidato a remover ou integrar.
- `dados/areas.js`, `dados/clientes.js`, `dados/notificacoes.js`, `dados/pedidos.js`: dados mock antigos. Nao aparecem carregados diretamente.
- `dados/utilizadores.js`: e carregado por paginas admin, mas nao encontrei uso direto de `mockUtilizadores` nos HTML/JS atuais. Candidato a remover os `<script>` e depois o ficheiro, com teste das paginas admin.
- `api/enviar-pedido.php`: sem chamada direta encontrada. Pode ser endpoint legado, porque `criar-pedido.php` e `pedido-atualizar-status.php` cobrem fluxos atuais.
- `api/eliminar-utilizador.php`: ponte pequena para `alternar-estado-utilizador.php`. Pode ser mantido por compatibilidade.
- `api/setup-tenant.php`: script administrativo/CLI. Nao tratar como morto sem decisao de instalacao/migracao.
- `api/utilizador-convites.php`: sem chamada direta encontrada. Pode ser tela futura ou historico administrativo.
- `img/arms_request_detail_1783124455421.png`: nao encontrei referencia direta. Pode ser imagem de documentacao ou captura antiga.

## Plano de implementacao sem quebra

Fase 1 - Limpeza controlada:

- Confirmar se podemos manter removidos `backups/**` e `js/*.bak`.
- Remover apenas pastas vazias (`empty_dir/`, talvez `tmp/sessions/`) depois de autorizacao.
- Nao tocar em `uploads/` nem `tmp/retencao-relatorios/` sem validar base de dados/auditoria.

Fase 2 - Backend interno:

- Criar `app/` para codigo compartilhado.
- Migrar primeiro servicos sem mudar URL publico:
  - `api/configuracoes-servico.php` para `app/Services/ConfiguracoesServico.php`
  - `api/seguranca-servico.php` para `app/Services/SegurancaServico.php`
  - `api/retencao-servico.php` para `app/Services/RetencaoServico.php`
  - `api/auth.php` para `app/Auth/Auth.php`
  - `api/permissoes.php` para `app/Permissions/Permissoes.php`
- Manter wrappers em `api/` com `require_once`.

Fase 3 - Frontend:

- Manter paginas `.html` na raiz ate haver testes visuais.
- Consolidar JS que esta inline nas paginas grandes, uma pagina por vez.
- So depois mover `css/`, `js/`, `img/` para `assets/`, atualizando paths e service worker.

Fase 4 - Storage:

- Introduzir constantes/configuracoes para caminhos:
  - `ARMS_UPLOAD_DIR`
  - `ARMS_RETENTION_REPORT_DIR`
- So depois mover fisicamente `uploads/` e `tmp/`.

## Conclusao

A limpeza mais segura agora e confirmar a remocao dos backups e `.bak` ja ausentes, e manter o `.gitignore` reforcado. A reorganizacao real deve comecar pelo backend interno, mantendo todos os endpoints publicos em `api/` para nao afetar o frontend.

## Limpeza executada

Executado em 2026-07-23, apos aprovacao:

- Confirmado que `backups/` ja nao existe no disco.
- Confirmado que nao ha ficheiros `*.bak` restantes fora da pasta `.git`.
- Removido `empty_dir/`, que estava vazio.
- Removido `tmp/sessions/`, que estava vazio.

Itens preservados de proposito:

- `uploads/`
- `uploads/.htaccess`
- `tmp/retencao-relatorios/`
- `dados/`
- `lang/`
- endpoints e scripts candidatos a investigacao

## Separacao Frontend/Backend executada

Executado em 2026-07-23, apos aprovacao explicita:

- Movidas as paginas `.html` para `Frontend/`.
- Movidas as pastas `css/`, `js/`, `img/` e `lang/` para `Frontend/`.
- Movidas as pastas `api/`, `bd/`, `scripts/`, `uploads/` e `tmp/` para `Backend/`.
- Criadas regras no `.htaccess` da raiz para manter compatibilidade com URLs antigas:
  - `/index.html` e paginas `.html` continuam a servir ficheiros de `Frontend/`.
  - `/css/...`, `/js/...`, `/img/...` e `/lang/...` continuam a servir ficheiros de `Frontend/`.
  - `/api/...` continua a servir endpoints de `Backend/api/`.
  - `/Frontend/api/...` tambem aponta para `Backend/api/...`, para paginas abertas diretamente dentro de `Frontend/`.
- Criado `Backend/.htaccess` para bloquear acesso direto a `bd/`, `scripts/`, `tmp/` e `uploads/`.
- Ajustado `Backend/api/configuracoes-plataforma.php` para gravar logos personalizados em `Frontend/img/`.

Verificacoes apos a separacao:

- PHP: `php -l` passou em `Backend/api/*.php` e `Backend/scripts/*.php`.
- JSON: `Frontend/lang/*.json` e `Frontend/js/manifest.json` continuam validos.
- HTML: nenhuma referencia interna `src`/`href` quebrada em `Frontend/*.html`.
- Apache local:
  - `/index.html` respondeu `200`.
  - `/css/base.css` respondeu `200`.
  - `/api/senha-util.php` respondeu `200`.
  - `/Frontend/api/senha-util.php` respondeu `200`.
  - `/Backend/bd/arms_schema.sql` respondeu `403`, como esperado.

## Limpeza dos ficheiros de configuracao da raiz

Executado em 2026-07-23:

- Mantido `.git`, porque e o repositorio Git do projeto.
- Mantido `.gitignore`, porque controla backups, temporarios e uploads ignorados.
- Mantido `.htaccess`, porque faz a compatibilidade entre URLs antigas e `Frontend/`/`Backend/`.
- Mantido `Backend/.htaccess`, porque bloqueia acesso direto a pastas internas do backend.
- Removido `.markdownlint.json`, porque era apenas configuracao opcional de lint Markdown e nao afeta a aplicacao.
- Removido `cspell.json`, porque era apenas configuracao opcional de corretor ortografico e nao afeta a aplicacao.

Estrutura atual esperada:

```text
Frontend/
  paginas .html
  css/
  js/
  img/
  lang/

Backend/
  api/
  bd/
  scripts/
  uploads/
  tmp/
```
