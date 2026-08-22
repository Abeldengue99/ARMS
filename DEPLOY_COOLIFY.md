# Deploy seguro no Coolify

## Base de dados unica

No Coolify, configure a aplicacao para apontar para a base PostgreSQL hospedada:

```env
ARMS_DB_HOST=<host-do-postgres>
ARMS_DB_PORT=5432
ARMS_DB_NAME=arms_db
ARMS_DB_USER=<utilizador>
ARMS_DB_PASS=<senha>
```

Para usar a mesma base no ambiente local, crie um ficheiro `.env` na raiz do projeto com os mesmos valores. O `.env` esta ignorado no Git para evitar expor credenciais.

## Correcao de sessoes em producao

O erro "Nao autenticado" no pedido hospedado pode acontecer quando a sessao PHP fica guardada em ficheiro dentro do container. Em deploy, reinicio ou multiplas instancias, o navegador ainda tem o cookie, mas o PHP nao encontra os dados da sessao.

Antes de ativar sessoes no PostgreSQL, execute na base `arms_db`:

```sql
\i Backend/bd/arms_php_sessions.sql
```

Depois, no Coolify, adicione:

```env
ARMS_SESSION_DRIVER=database
ARMS_SESSION_NAME=PHPSESSID
```

Impacto esperado: os utilizadores que ja estavam logados podem precisar iniciar sessao novamente uma vez apos o deploy. Os pedidos, utilizadores e historico nao sao apagados.

Rollback rapido: remova ou altere a variavel para:

```env
ARMS_SESSION_DRIVER=files
```

Depois faca redeploy.
