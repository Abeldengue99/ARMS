# 🎯 SETUP - Página de Detalhes do Pedido

## ✅ Arquivos Corrigidos e Simplificados

- **pedido-detalhe.html** - Novo, limpo e funcional ✨
- **api/pedido-detalhe.php** - Simplificado com queries otimizadas
- **api/comentario-criar.php** - Limpo e funcional
- **api/anexo-upload.php** - Sem erros, suporta todos os tipos
- **api/anexo-download.php** - Simples e direto
- **api/pedido-atualizar-status.php** - Básico e funcional

## 📋 Como Testar

### 1️⃣ Inserir dados de teste no PostgreSQL:

```bash
psql -U postgres -d "ARMS — Aksanti Request Management System" -f bd/seed_teste_detalhes.sql
```

Ou copie manualmente o conteúdo de `bd/seed_teste_detalhes.sql` e execute no pgAdmin.

### 2️⃣ Criar pasta de uploads:

```bash
mkdir "c:\xampp\htdocs\ARMS — Aksanti Request Management System\uploads"
```

### 3️⃣ Acessar a página:

```
http://localhost/ARMS/pedido-detalhe.html?id=AKS-2026-00001
```

## 🔍 O que Funciona

✅ Timeline completa com histórico  
✅ Comentários em tempo real  
✅ Upload de arquivos (drag-and-drop)  
✅ Download de anexos  
✅ Status do pedido com badges coloridas  
✅ Edição de pedidos em rascunho  
✅ Envio de pedidos (muda status DRAFT → SENT)  

## 🚨 IDs de Teste

Pedidos disponíveis para testar:
- `AKS-2026-00001` (RECEIVED - Auditoria)
- `AKS-2026-00002` (DRAFT - Implementação)
- `AKS-2026-00003` (CLIENT_RESPONDED - Consultoria)

## 📁 Estrutura de Arquivos

```
uploads/          ← Arquivos enviados (criado automaticamente)
api/
  ├─ pedido-detalhe.php
  ├─ comentario-criar.php
  ├─ anexo-upload.php
  ├─ anexo-download.php
  └─ pedido-atualizar-status.php
bd/
  └─ seed_teste_detalhes.sql
pedido-detalhe.html   ← Página principal
```

## ⚙️ Configurações

### User ID (para testes)
Em `pedido-detalhe.html` linha ~100:
```javascript
let usuarioId = '550e8400-e29b-41d4-a716-446655440001';
```

Este é o ID de João Silva (admin de teste).

### Limite de Upload
Máximo: **50MB** por arquivo (configurável em `anexo-upload.php`)

## 🐛 Se Algo Não Funcionar

1. Verifique se a pasta `/uploads` existe
2. Verifique permissões: `chmod 755 uploads`
3. Verifique dados no PostgreSQL: `SELECT * FROM arms.request;`
4. Abra console do navegador (F12) para ver erros

## 📝 Próximas Melhorias (Futuro)

- [ ] Autenticação real (usar $_SESSION)
- [ ] Validações de permissão por user_type
- [ ] Notificações por email
- [ ] Preview de imagens em anexos
- [ ] Pesquisa e filtros
