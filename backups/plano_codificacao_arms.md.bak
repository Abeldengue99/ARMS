# 🏗️ ARMS — Plano de Codificação Profissional

> **Aksanti Request Management System**
> Schema: `arms` | PostgreSQL 14+ | **BD FINALIZADA — NÃO MEXER**

---

## 1. Princípios de Desenvolvimento

| Princípio | Regra |
|-----------|-------|
| **Separação total** | CSS numa pasta, JS noutra, HTML noutra — nunca misturar |
| **Comentários obrigatórios** | Cada linha de código tem um comentário em PT explicando o porquê |
| **Fidelidade à BD** | Toda estrutura frontend espelha exactamente as tabelas do schema `arms` |
| **Sem conexão (por agora)** | Dados mockados em JS — amanhã ligamos ao PostgreSQL |
| **i18n nativo** | 3 idiomas: Português, Inglês, Francês |
| **Multi-moeda** | 3 moedas: Kwanza (AOA), Dólar (USD), Euro (EUR) |
| **Código limpo** | Nomes descritivos, zero abreviações crípticas |

---

## 2. Estrutura de Pastas

```
ARMS — Aksanti Request Management System/
│
├── index.html                          # Página de Login
├── dashboard.html                      # Dashboard principal
├── pedidos.html                        # Lista de pedidos
├── pedido-detalhe.html                 # Detalhe de um pedido
├── clientes.html                       # Gestão de clientes
├── areas.html                          # Gestão de áreas/departamentos
├── notificacoes.html                   # Centro de notificações
├── perfil.html                         # Perfil do utilizador
├── admin-utilizadores.html             # Admin: gestão de utilizadores
│
├── css/                                # TODOS os estilos aqui
│   ├── reset.css                       # Reset/normalize do browser
│   ├── variaveis.css                   # Tokens de design (cores, fonts, espaçamentos)
│   ├── base.css                        # Estilos globais (body, links, tipografia)
│   ├── layout.css                      # Sidebar, header, grid principal
│   ├── componentes.css                 # Botões, cards, badges, modais, inputs
│   ├── tabelas.css                     # DataTables e listas
│   ├── formularios.css                 # Inputs, selects, file upload
│   ├── dashboard.css                   # Estilos específicos do dashboard
│   ├── animacoes.css                   # Transições e micro-animações
│   └── responsivo.css                  # Media queries para mobile/tablet
│
├── js/                                 # TODA a lógica aqui
│   ├── app.js                          # Inicialização global da aplicação
│   ├── i18n.js                         # Sistema de internacionalização (PT/EN/FR)
│   ├── moeda.js                        # Sistema de multi-moeda (AOA/USD/EUR)
│   ├── sidebar.js                      # Lógica da sidebar e navegação
│   ├── auth.js                         # Login, logout, sessão (mock por agora)
│   ├── dashboard.js                    # Lógica do dashboard e gráficos
│   ├── pedidos.js                      # CRUD de pedidos (mock data)
│   ├── clientes.js                     # CRUD de clientes (mock data)
│   ├── notificacoes.js                 # Lógica de notificações
│   ├── tabela.js                       # Componente de tabela reutilizável
│   ├── modal.js                        # Componente de modal reutilizável
│   ├── validacao.js                    # Validação de formulários
│   └── utils.js                        # Funções utilitárias gerais
│
├── lang/                               # Ficheiros de tradução
│   ├── pt.json                         # Português (idioma padrão)
│   ├── en.json                         # Inglês
│   └── fr.json                         # Francês
│
├── assets/                             # Recursos estáticos
│   ├── img/                            # Imagens e ícones
│   │   ├── logo-aksanti.png            # Logo da Aksanti
│   │   ├── logo-aksanti-branco.png     # Logo branco para sidebar escura
│   │   └── avatar-padrao.png           # Avatar padrão dos utilizadores
│   └── fonts/                          # Fontes locais (se necessário)
│
├── dados/                              # Dados mock (substitui BD temporariamente)
│   ├── utilizadores.js                 # Mock: tabela auth_user + user_profile
│   ├── areas.js                        # Mock: tabela area (CONTAB, AUDIT, etc.)
│   ├── clientes.js                     # Mock: tabela client + client_contact
│   ├── pedidos.js                      # Mock: tabela request + responses
│   └── notificacoes.js                 # Mock: tabela notification
│
├── docs/                               # Documentação do projecto
│   ├── arms_implementation_plan.md     # Plano original de implementação
│   └── arms_layout_proposal.md         # Proposta de layout visual
│
└── sql/                                # Referência da BD (READ-ONLY)
    └── arms_hybrid_fusion.sql          # Schema completo — NÃO EXECUTAR DAQUI
```

---

## 3. Mapeamento BD → Frontend

> [!IMPORTANT]
> Cada ficheiro de dados mock replica **exactamente** a estrutura da tabela correspondente no schema `arms`.

### Tabelas → Mock Data

| Tabela `arms.` | Ficheiro Mock | Campos-chave replicados |
|---|---|---|
| `auth_user` | `dados/utilizadores.js` | `id`, `email`, `user_type` (AKSANTI/CLIENT), `is_admin`, `is_active` |
| `user_profile` | `dados/utilizadores.js` | `first_name`, `last_name`, `phone`, `preferred_language` |
| `area` | `dados/areas.js` | `id`, `name`, `code` (CONTAB, AUDIT, FIN, RH, TECH, CONSULT, EDU, LEGAL) |
| `client` | `dados/clientes.js` | `id`, `company_name`, `tax_id`, `status` (ACTIVE/SUSPENDED/TERMINATED) |
| `client_contact` | `dados/clientes.js` | `id`, `client_id`, `full_name`, `email`, `is_primary` |
| `request` | `dados/pedidos.js` | `id`, `reference` (AKS-2026-XXXXX), `status`, `area_id`, `client_id`, `priority` |
| `request_response` | `dados/pedidos.js` | `id`, `request_id`, `encrypted_body`, `decision` |
| `request_comment` | `dados/pedidos.js` | `id`, `request_id`, `visibility` (BOTH/AKSANTI_ONLY) |
| `notification` | `dados/notificacoes.js` | `id`, `user_id`, `channel` (IN_APP/EMAIL), `is_read` |

### Estado dos Pedidos (State Machine do PostgreSQL)

```
DRAFT → SENT → RECEIVED → CLIENT_RESPONDED → ACCEPTED/REJECTED → CLOSED
```

> [!NOTE]
> No frontend usamos badges coloridos para cada estado. A validação de transições vive no PostgreSQL (trigger `trg_request_valid_transition`).

---

## 4. Sistema de Internacionalização (i18n)

### Arquitectura

O ficheiro `js/i18n.js` carrega o JSON do idioma seleccionado e aplica as traduções via atributo `data-i18n` no HTML.

### Exemplo do HTML
```html
<!-- O atributo data-i18n liga ao JSON de tradução -->
<h1 data-i18n="dashboard.titulo">Painel de Controlo</h1>
<button data-i18n="acoes.criar_pedido">Criar Pedido</button>
```

### Exemplo do `lang/pt.json`
```json
{
  "nav": {
    "dashboard": "Painel de Controlo",
    "pedidos": "Pedidos",
    "clientes": "Clientes",
    "areas": "Áreas",
    "notificacoes": "Notificações",
    "perfil": "Perfil",
    "admin": "Administração",
    "sair": "Sair"
  },
  "dashboard": {
    "titulo": "Painel de Controlo",
    "total_pedidos": "Total de Pedidos",
    "pedidos_abertos": "Pedidos Abertos",
    "taxa_resposta": "Taxa de Resposta",
    "sla_medio": "SLA Médio"
  },
  "status": {
    "DRAFT": "Rascunho",
    "SENT": "Enviado",
    "RECEIVED": "Recebido",
    "CLIENT_RESPONDED": "Cliente Respondeu",
    "ACCEPTED": "Aceite",
    "REJECTED": "Rejeitado",
    "CLOSED": "Fechado"
  },
  "acoes": {
    "criar_pedido": "Criar Pedido",
    "guardar": "Guardar",
    "cancelar": "Cancelar",
    "editar": "Editar",
    "eliminar": "Eliminar",
    "enviar": "Enviar",
    "aceitar": "Aceitar",
    "rejeitar": "Rejeitar"
  },
  "moeda": {
    "AOA": "Kwanza",
    "USD": "Dólar",
    "EUR": "Euro"
  }
}
```

### Selector de idioma no header
```
🇵🇹 PT  |  🇬🇧 EN  |  🇫🇷 FR
```

---

## 5. Sistema de Multi-Moeda

### Arquitectura (`js/moeda.js`)

| Moeda | Código | Símbolo | Formato |
|-------|--------|---------|---------|
| Kwanza | AOA | Kz | `1.250.000,00 Kz` |
| Dólar | USD | $ | `$12,500.00` |
| Euro | EUR | € | `€12.500,00` |

### Lógica
- A moeda activa fica guardada em `localStorage`
- Taxas de câmbio fixas (por agora), amanhã API real
- Selector de moeda no header ao lado do idioma

---

## 6. Padrão de Comentários

> [!IMPORTANT]
> **Cada linha de código deve ter um comentário em português**, escrito de forma humana e natural.

### Exemplo CSS
```css
/* Estou a definir a cor principal da Aksanti para usar em toda a plataforma */
--cor-aksanti-gold: #E58A13;

/* Estou a usar esta cor escura porque a sidebar da Aksanti precisa de contraste forte */
--cor-sidebar: #1A1A1A;

/* Estou a dar cantos arredondados nos cards para parecer mais moderno e premium */
border-radius: 12px;
```

### Exemplo JS
```javascript
// Estou a buscar o idioma que o utilizador escolheu e guardou no browser
const idioma = localStorage.getItem('arms_idioma') || 'pt';

// Estou a carregar o ficheiro JSON do idioma escolhido para poder traduzir a página
const traducoes = await fetch(`/lang/${idioma}.json`).then(r => r.json());

// Estou a percorrer todos os elementos que têm o atributo data-i18n para trocar o texto
document.querySelectorAll('[data-i18n]').forEach(elemento => {
    // Estou a buscar a chave de tradução que está no atributo do elemento
    const chave = elemento.getAttribute('data-i18n');
    // Estou a aplicar o texto traduzido se existir, senão deixo o texto original
    elemento.textContent = obterTraducao(traducoes, chave) || elemento.textContent;
});
```

### Exemplo HTML
```html
<!-- Estou a criar o card de KPI que mostra o total de pedidos no dashboard -->
<div class="card-kpi" id="kpi-total-pedidos">
    <!-- Estou a usar o ícone de lista para representar visualmente os pedidos -->
    <i class="icon-lista"></i>
    <!-- Estou a mostrar o número grande que vem dos dados mock por agora -->
    <span class="kpi-valor" id="valor-total-pedidos">0</span>
    <!-- Estou a usar data-i18n para que este título mude conforme o idioma -->
    <span class="kpi-titulo" data-i18n="dashboard.total_pedidos">Total de Pedidos</span>
</div>
```

---

## 7. Design System — Paleta Aksanti

| Token CSS | Valor | Uso |
|-----------|-------|-----|
| `--aksanti-gold` | `#E58A13` | Botões, links activos, KPIs, accent |
| `--aksanti-gold-hover` | `#D07A0F` | Hover nos botões gold |
| `--aksanti-dark` | `#1A1A1A` | Sidebar, header |
| `--aksanti-dark-hover` | `#2A2A2A` | Hover nos items da sidebar |
| `--bg-principal` | `#F4F4F4` | Fundo do conteúdo |
| `--bg-card` | `#FFFFFF` | Cards e modais |
| `--texto-principal` | `#1A1A1A` | Texto principal |
| `--texto-secundario` | `#666666` | Descrições, labels |
| `--cor-sucesso` | `#10B981` | Status ACCEPTED, CLOSED |
| `--cor-aviso` | `#F59E0B` | Status DRAFT, SENT |
| `--cor-perigo` | `#EF4444` | Status REJECTED |
| `--cor-info` | `#3B82F6` | Status RECEIVED, CLIENT_RESPONDED |
| `--fonte-corpo` | `'Inter', sans-serif` | Texto geral |
| `--fonte-display` | `'Outfit', sans-serif` | Títulos e KPIs |
| `--raio-borda` | `12px` | Cards e botões |
| `--sombra-card` | `0 2px 12px rgba(0,0,0,0.08)` | Elevação dos cards |

---

## 8. Fases de Codificação

### FASE 1 — Fundação e Layout ⬅️ `COMEÇAMOS AQUI`

| # | Tarefa | Ficheiros |
|---|--------|-----------|
| 1.1 | Criar estrutura de pastas completa | Todas as pastas |
| 1.2 | `css/reset.css` — normalizar browser | CSS |
| 1.3 | `css/variaveis.css` — design tokens Aksanti | CSS |
| 1.4 | `css/base.css` — tipografia, links, corpo | CSS |
| 1.5 | `css/layout.css` — sidebar + header + grid | CSS |
| 1.6 | `css/componentes.css` — botões, cards, badges | CSS |
| 1.7 | `css/animacoes.css` — transições suaves | CSS |
| 1.8 | `css/responsivo.css` — mobile/tablet | CSS |
| 1.9 | `js/app.js` — inicialização global | JS |
| 1.10 | `js/sidebar.js` — navegação da sidebar | JS |
| 1.11 | `lang/pt.json`, `en.json`, `fr.json` — traduções | JSON |
| 1.12 | `js/i18n.js` — motor de tradução | JS |
| 1.13 | `js/moeda.js` — motor de moeda | JS |
| 1.14 | `index.html` — página de login premium | HTML |
| 1.15 | `dashboard.html` — layout com sidebar e KPIs vazios | HTML |

✅ **Resultado:** Login visual → Dashboard com sidebar navegável, i18n e moeda funcionais.

---

### FASE 2 — Dados Mock e Dashboard

| # | Tarefa | Ficheiros |
|---|--------|-----------|
| 2.1 | `dados/areas.js` — 8 áreas seed (CONTAB, AUDIT, etc.) | Mock |
| 2.2 | `dados/utilizadores.js` — 5 utilizadores mock | Mock |
| 2.3 | `dados/clientes.js` — 3 clientes mock com contactos | Mock |
| 2.4 | `dados/pedidos.js` — 10 pedidos mock com referências AKS-2026-XXXXX | Mock |
| 2.5 | `dados/notificacoes.js` — notificações mock | Mock |
| 2.6 | `js/dashboard.js` — popular KPIs com dados mock | JS |
| 2.7 | `css/dashboard.css` — gráficos e cards KPI | CSS |

✅ **Resultado:** Dashboard com KPIs reais (mock), gráficos, e tabela de pedidos recentes.

---

### FASE 3 — Páginas CRUD

| # | Tarefa | Ficheiros |
|---|--------|-----------|
| 3.1 | `pedidos.html` + `js/pedidos.js` — lista com filtros e paginação | HTML + JS |
| 3.2 | `pedido-detalhe.html` — timeline, comentários, respostas | HTML + JS |
| 3.3 | `clientes.html` + `js/clientes.js` — lista e detalhe de clientes | HTML + JS |
| 3.4 | `areas.html` — visualização de áreas | HTML |
| 3.5 | `css/tabelas.css` + `js/tabela.js` — componente DataTable | CSS + JS |
| 3.6 | `css/formularios.css` + `js/validacao.js` — inputs e validação | CSS + JS |
| 3.7 | `js/modal.js` + estilos — modal reutilizável | JS + CSS |

✅ **Resultado:** Todas as páginas navegáveis com dados mock, filtros e modais.

---

### FASE 4 — Auth, Notificações e Admin

| # | Tarefa | Ficheiros |
|---|--------|-----------|
| 4.1 | `js/auth.js` — login/logout com sessão mock (`localStorage`) | JS |
| 4.2 | `js/notificacoes.js` — bell icon, dropdown, marcar como lido | JS |
| 4.3 | `notificacoes.html` — centro de notificações | HTML |
| 4.4 | `perfil.html` — perfil do utilizador (idioma, moeda) | HTML |
| 4.5 | `admin-utilizadores.html` — gestão de utilizadores (só admin) | HTML |

✅ **Resultado:** Sistema completo com auth mock, notificações e painel admin.

---

### FASE 5 — Conexão BD (AMANHÃ)

| # | Tarefa |
|---|--------|
| 5.1 | Substituir dados mock por chamadas à API/backend |
| 5.2 | Conectar ao PostgreSQL via backend (PHP/Laravel) |
| 5.3 | Implementar auth real com bcrypt e sessões |
| 5.4 | Activar E2EE nos campos `encrypted_*` |

---

## 9. Funcionalidades por Tipo de Utilizador

| Funcionalidade | AKSANTI Staff | CLIENT Admin | CLIENT Employee |
|---|:---:|:---:|:---:|
| Ver Dashboard Aksanti | ✅ | ❌ | ❌ |
| Ver Dashboard Cliente | ❌ | ✅ | ✅ |
| Criar Pedidos | ✅ | ❌ | ❌ |
| Ver Pedidos (próprios) | ✅ | ✅ | ✅ |
| Responder a Pedidos | ❌ | ✅ | ✅ |
| Aceitar/Rejeitar Respostas | ✅ | ❌ | ❌ |
| Comentários AKSANTI_ONLY | ✅ | ❌ | ❌ |
| Comentários BOTH | ✅ | ✅ | ✅ |
| Gerir Clientes | ✅ | ❌ | ❌ |
| Gerir Utilizadores | ✅ (admin) | ✅ (do seu client) | ❌ |
| Mudar idioma/moeda | ✅ | ✅ | ✅ |

---

## 10. Regras Absolutas

> [!CAUTION]
> 1. **A BD está finalizada** — não mexer no schema `arms`
> 2. **Cada linha de código tem comentário em PT** — sem excepção
> 3. **CSS e JS sempre em pastas separadas** — nunca inline
> 4. **Nomes de variáveis e ficheiros em português** — excepto palavras técnicas universais
> 5. **Mock data replica exactamente a BD** — mesmos nomes de campos, mesmos tipos
> 6. **i18n em todas as strings visíveis** — usar `data-i18n` no HTML
> 7. **Paleta Aksanti** — Gold `#E58A13` + Dark `#1A1A1A` + Off-white `#F4F4F4`

---

**Analisa o plano e diz "GO" quando estiveres pronto para arrancar!** 🚀
