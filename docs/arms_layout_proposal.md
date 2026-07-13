# 🎨 ARMS — Proposta de Layout Visual

> Baseada na identidade visual oficial da **Aksanti** ([aksanti.xyz](https://aksanti.xyz/))

---

## Paleta de Cores Extraída do Site Aksanti

| Cor | Hex | Uso no ARMS |
|-----|-----|-------------|
| **Aksanti Gold/Amber** | `#E58A13` | Accent principal: botões, badges activos, KPIs, links |
| **Dark Charcoal** | `#1A1A1A` | Sidebar, header, footer |
| **Off-White** | `#F4F4F4` | Background do conteúdo principal |
| **Pure White** | `#FFFFFF` | Cards, modais, inputs |
| **Medium Grey** | `#666666` | Texto secundário/descrições |
| **Pastel Blue** | `#E9F2F9` | Ícones circulares, highlights suaves |
| **Success Green** | `#10B981` | Status ACCEPTED, CLOSED |
| **Info Blue** | `#3B82F6` | Status RECEIVED, CLIENT_RESPONDED |
| **Danger Red** | `#EF4444` | Status REJECTED |

---

## 1. Dashboard Principal (Aksanti Staff)

![Dashboard ARMS — Vista do staff Aksanti com KPIs, gráficos e pedidos recentes](C:/Users/nee/.gemini/antigravity/brain/2f6e3514-06f8-41c2-845d-975f8dcc0fe3/arms_dashboard_layout_1783124429709.png)

**Elementos-chave:**
- 🟧 **Sidebar escura** (`#1A1A1A`) com navegação e item activo em gold
- 📊 **4 KPI cards** no topo: Total Pedidos, Abertos, Taxa de Resposta, SLA
- 📈 **Gráfico de pedidos por mês** com barras em amber/gold
- 📋 **Tabela de pedidos recentes** com referências `AKS-2026-XXXXX` e badges de status
- 🍩 **Donut chart** de distribuição por área

---

## 2. Página de Detalhe do Pedido

![Detalhe do Pedido AKS-2026-00042 — Timeline, comentários e anexos](C:/Users/nee/.gemini/antigravity/brain/2f6e3514-06f8-41c2-845d-975f8dcc0fe3/arms_request_detail_1783124455421.png)

**Elementos-chave:**
- 🔖 **Header** com referência grande, badge de status, e botões de acção
- 🔐 **Descrição encriptada** com ícone de cadeado (E2E)
- 📍 **Timeline vertical** mostrando cada transição de status do `request_audit_log`
- 💬 **Thread de comentários** com badges `AKSANTI_ONLY` e `AMBOS`
- 📎 **Zona de anexos** com upload drag-and-drop

---

## Princípios de Design

1. **Gold como destaque, não como base** — o amber `#E58A13` aparece em botões, links e dados importantes, nunca em grandes superfícies
2. **Sidebar escura vs conteúdo claro** — contraste forte para foco no conteúdo
3. **Cards brancos com sombra suave** — hierarquia visual clara
4. **Typography mista** — Sans-serif (Inter) para interface + Serif (Playfair Display) para números/KPIs de impacto, como no site da Aksanti
5. **Status sempre visual** — badges coloridos em todas as ocorrências de status
6. **E2E visível** — ícones de cadeado nos campos encriptados para transmitir segurança ao utilizador

---

**Aprovas este estilo visual? Diz "GO" e arrancamos o código! 🚀**
