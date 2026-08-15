# ARMS — Aksanti Request Management System

## 📖 Sobre o Projeto
O **ARMS (Aksanti Request Management System)** é um sistema web desenvolvido para gerir pedidos, clientes, utilizadores e automações. Foi desenhado para fornecer uma interface rápida e intuitiva, mantendo um backend leve e de fácil manutenção.

## 🚀 Tecnologias Utilizadas
- **Frontend:** HTML5, CSS3 (Vanilla / Custom) e JavaScript Nativo (ES6+).
- **Backend:** PHP Nativo (API RESTful sem frameworks pesados).
- **Banco de Dados:** MySQL (Ficheiros estruturais disponíveis em `/Backend/bd`).
- **Utilitários:** NodeJS e Puppeteer (para a geração automatizada de relatórios/manuais em PDF).

## 📁 Estrutura de Diretórios
O repositório está organizado de forma a separar claramente a lógica de apresentação e a regra de negócios:

```text
/ (Raiz)
 ├── Frontend/          # Todos os arquivos visuais (HTML, CSS, JS, assets)
 ├── Backend/           # Lógica do lado do servidor
 │   ├── api/           # Endpoints em PHP que respondem aos pedidos do Frontend
 │   ├── bd/            # Scripts SQL para criação e manutenção da base de dados
 │   ├── backups/       # Backups do sistema
 │   ├── uploads/       # Ficheiros e anexos enviados pelos utilizadores
 │   └── scripts/       # Scripts internos auxiliares
 ├── api/               # Roteamento auxiliar / Endpoints de acesso rápido
 ├── generate_pdf.js    # Script em NodeJS para exportar manuais (.md) para PDF
 └── package.json       # Dependências NodeJS (Marked, Puppeteer)
```

## ⚙️ Como Instalar e Rodar Localmente (Ambiente de Desenvolvimento)

### 1. Pré-requisitos
- **XAMPP / Laragon** ou equivalente (para rodar Apache e MySQL).
- **PHP 8.0+**
- **Node.js** (Opcional, apenas necessário se for gerar PDFs dos manuais).

### 2. Configuração do Banco de Dados
1. Abra o phpMyAdmin (ou outro gestor MySQL).
2. Crie uma base de dados com o nome apropriado (ex: `arms_db`).
3. Importe os ficheiros SQL localizados na pasta `Backend/bd/`:
   - Primeiro: `arms_schema.sql` (Cria a estrutura base)
   - Segundo: `arms_retencao_auditoria.sql` (Cria tabelas de log e auditoria)
4. Atualize o ficheiro de conexão (normalmente `Backend/api/db.php` ou similar) com as credenciais locais (`localhost`, utilizador `root`, senha em branco, etc.).

### 3. Rodar o Projeto
1. Coloque esta pasta (`ARMS — Aksanti Request Management System`) dentro do diretório `htdocs` do seu XAMPP (ou `www` no WAMP).
2. Inicie os serviços **Apache** e **MySQL** no painel de controlo do XAMPP.
3. No seu navegador, acesse: `http://localhost/ARMS%20—%20Aksanti%20Request%20Management%20System/Frontend/` (ajuste o link de acordo com o nome exato da pasta).

### 4. Geração de PDF (Opcional)
Se precisar gerar ou atualizar os Manuais em PDF a partir dos ficheiros Markdown (.md):
1. Abra o terminal na raiz do projeto.
2. Rode `npm install` para instalar as dependências.
3. Rode `node generate_pdf.js`.

---

## 📝 Regras de Contribuição e Documentação no Código
Para mantermos o projeto limpo e organizado, seguimos as seguintes práticas:
- **Nomenclatura:** Funções e variáveis JS em `camelCase`. Ficheiros PHP em minúsculas separados por hífen (ex: `criar-pedido.php`).
- **Comentários:** Utilizar blocos de JSDoc e PHPDoc nas funções principais. Explicar sempre o "porquê" de um bloco de código complexo existir.
- **Frontend Responsivo:** Ao alterar ou criar novas views (`.html` ou `.css`), certifique-se de manter o padrão visual já existente e validar o layout em ecrãs Mobile, Tablet e Desktop.

---

> **Aviso de Segurança:** Nunca faça commit de ficheiros contendo senhas reais do banco de dados, chaves de API secretas ou credenciais de e-mail (ex: SMTP). Utilize variáveis de ambiente ou ficheiros ignorados pelo `.gitignore`.
