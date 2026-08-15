# Manual Oficial de Desenvolvimento - ARMS

Este documento serve como a referência oficial de engenharia para o projeto ARMS. Qualquer agente de IA ou desenvolvedor deve seguir rigorosamente as regras aqui estabelecidas para garantir consistência, segurança e escalabilidade.

> [!IMPORTANT]
> **Filosofia Principal (Indispensável): Toda alteração deve deixar o projeto igual ou melhor do que estava antes. É proibido introduzir dívida técnica, reduzir a qualidade do código, quebrar padrões existentes ou comprometer a arquitetura em nome da rapidez. Quando houver conflito entre velocidade e qualidade, deve prevalecer a qualidade, salvo instrução explícita do utilizador.**

---

## 01. Missão e Objetivos do Agente

- Desenvolver código limpo, legível, robusto, altamente seguro e otimizado.
- Garantir que todas as alterações respeitem a integridade e os padrões arquiteturais existentes no projeto.
- Minimizar a dívida técnica e focar em soluções sustentáveis a longo prazo.

## 02. Regras Globais

- Sempre que criar ficheiros temporários ou de teste, estes devem ser apagados após o seu uso ou ao finalizar a tarefa atual, para manter o diretório do projeto limpo.
- Todos os novos desenvolvimentos de interface (frontend) devem respeitar as diretrizes estéticas globais (Estilo Apple, Glassmorphism, detalhes Premium e bem estruturado).

## 03. Arquitetura do Projeto

### 03.1 Princípios Gerais

- Nunca misturar código de backend com frontend.
- Todo código deve pertencer a um módulo ou domínio específico.
- Cada módulo deve ser independente e de baixa acoplagem.
- Não criar estruturas paralelas que dupliquem responsabilidades.
- Sempre seguir o padrão arquitetural já existente no projeto.
- Nenhum ficheiro deve existir na raiz do projeto, exceto ficheiros de configuração (README, LICENSE, .gitignore, docker-compose, etc.) e páginas HTML (no caso de projetos PHP tradicionais).

### 03.2 Separação Backend/Frontend

Para **projetos com framework SPA** (React, Vue, Next.js, etc.) com API REST separada:

- Deve existir obrigatoriamente uma pasta `/backend` e uma pasta `/frontend`.

Para **projetos PHP tradicionais servidos por Apache/XAMPP** (como o ARMS):

- A separação deve ser **lógica**, com cada tipo de recurso na sua pasta dedicada.
- Não é necessária uma pasta física `/backend` e `/frontend`, desde que:
  1. Todo o código PHP (backend) esteja isolado na pasta `api/` e `scripts/`
  2. Todo o CSS, JS, imagens e HTML (frontend) estejam nas suas pastas dedicadas
  3. Não exista mistura de lógica PHP dentro de ficheiros HTML/JS
  4. A separação de responsabilidades seja clara e documentada
- Esta excepção existe porque forçar a reestruturação física num projeto PHP tradicional quebra centenas de caminhos relativos sem benefício funcional, uma vez que o Apache serve tudo a partir da mesma raiz.

## 04. Organização de Pastas

O agente deve respeitar rigorosamente a organização de diretórios existente, nunca criando novas pastas na raiz ou em subníveis sem uma justificação clara e autorização prévia.

### 04.1 Estrutura do ARMS (PHP Tradicional)

```text
ARMS/
 ├── api/              ← BACKEND: Endpoints PHP, lógica de negócio, autenticação
 ├── bd/               ← BACKEND: Schema SQL, migrações
 ├── scripts/          ← BACKEND: Utilitários e scripts agendados PHP
 ├── css/              ← FRONTEND: Ficheiros de estilos (design system)
 ├── js/               ← FRONTEND: Lógica cliente, componentes, serviços
 ├── dados/            ← FRONTEND: Dados estáticos / mock
 ├── img/              ← FRONTEND: Assets visuais (imagens, ícones, logos)
 ├── lang/             ← FRONTEND: Internacionalização (pt, en, fr)
 ├── *.html            ← FRONTEND: Páginas da aplicação
 ├── uploads/          ← STORAGE: Ficheiros enviados por utilizadores
 ├── .htaccess         ← CONFIG: Regras do servidor Apache
 └── .gitignore        ← CONFIG: Controlo de versão
```

### 04.2 Estrutura Genérica para Projetos SPA (Referência)

**Backend:**

```text
backend/
 ├── app/
 ├── config/
 ├── controllers/
 ├── services/
 ├── repositories/
 ├── models/
 ├── middleware/
 ├── routes/
 ├── database/
 ├── helpers/
 ├── storage/
 ├── tests/
 └── docs/
```

**Frontend:**

```text
frontend/
 ├── assets/
 ├── components/
 ├── pages/
 ├── layouts/
 ├── services/
 ├── hooks/
 ├── contexts/
 ├── styles/
 ├── public/
 ├── utils/
 └── tests/
```

## 05. Convenções de Nomenclatura

- Utilizar camelCase para variáveis, funções e métodos em Javascript/TypeScript.
- Utilizar PascalCase para classes, componentes, interfaces e tipos.
- Utilizar snake_case para nomes de tabelas e colunas no banco de dados.
- Nomes de arquivos devem refletir fielmente o seu conteúdo e seguir a convenção de caso do ecossistema correspondente.

## 06. Regras de Programação & Padrões

- Nunca duplicar lógica.
- Nunca copiar e colar código existente quando puder reutilizá-lo.
- Toda funcionalidade deve ter uma única responsabilidade (Single Responsibility Principle).
- Toda classe deve representar um único conceito.
- Toda função deve executar apenas uma tarefa.
- Preferir composição à herança sempre que possível.
- Utilizar injeção de dependências quando aplicável.
- Evitar dependências cíclicas.

## 07. Segurança

- Nunca expor chaves de API, credenciais ou dados sensíveis nos repositórios. Utilizar variáveis de ambiente.
- Validar e sanitizar todas as entradas de dados no backend para prevenir injeções de SQL, XSS e outros ataques comuns.
- Garantir controlo de acesso adequado em todas as rotas e endpoints.

## 08. Banco de Dados

- Todas as alterações de esquema devem ser feitas via migrações (migrations) ordenadas e versionadas, nunca diretamente no banco de dados de produção.
- Utilizar índices apropriados para otimização de consultas de leitura comuns.
- Evitar consultas complexas desnecessárias (N+1 query problem).

## 09. APIs

- Seguir os princípios RESTful para o design de APIs HTTP, utilizando códigos de status HTTP corretos.
- Formatar todas as respostas de erro de forma padronizada.
- Documentar adequadamente os endpoints criados.

## 10. Frontend

- Garantir responsividade e compatibilidade entre navegadores modernos.
- Priorizar a acessibilidade (a11y) utilizando elementos HTML semânticos.
- Adotar micro-interações e transições suaves para melhorar a experiência do utilizador.
- **Separação Rigorosa de CSS:** Nunca misturar código de estilo (CSS) diretamente no HTML ou inline via JavaScript/frameworks (a menos que seja estritamente necessário para posicionamento dinâmico baseado em JS). Toda estilização deve estar contida em arquivos CSS/SCSS separados e organizados na pasta apropriada (ex: `frontend/styles/` ou `css/`).

## 11. Backend

- Tratar exceções de forma adequada a nível global, evitando que o servidor sofra crash.
- Implementar logs úteis e estruturados para facilitar a depuração em produção.

## 12. Git e Versionamento

- Escrever mensagens de commit claras, concisas e seguindo o padrão Conventional Commits (ex: `feat:`, `fix:`, `refactor:`).
- Nunca commitar código inacabado ou com erros de sintaxe na branch principal.

## 13. Testes e Qualidade

- Escrever testes unitários e de integração para regras de negócio críticas.
- Manter um código limpo e livre de avisos de linters e analisadores estáticos.

## 14. Documentação

- Manter documentação de código atualizada (docstrings, comentários concisos explicando o "porquê", não o "quê").
- Atualizar os guias de configuração se novas dependências forem introduzidas.

## 15. Fluxo de Trabalho Obrigatório

1. Analisar o estado atual antes de modificar qualquer ficheiro.
2. Procurar reutilização de módulos e funções antes de escrever código do zero.
3. Implementar estritamente o necessário para cumprir o requisito.
4. Validar o impacto das mudanças nas dependências existentes antes de guardar.

## 16. Checklist Final

- Antes de dar uma tarefa por concluída, rever todas as alterações efetuadas em busca de depurações perdidas (como `console.log`), formatação inconsistente ou vulnerabilidades básicas.

## 17. Regras Exclusivas para IA

- Nunca afirmar que executou testes sem realmente os executar.
- Nunca afirmar que um ficheiro existe sem o confirmar.
- Nunca assumir o conteúdo de um ficheiro não fornecido.
- Sempre distinguir claramente entre factos confirmados e hipóteses.
- Quando existir incerteza, pedir esclarecimentos antes de modificar o código.
- Explicar sempre o motivo das alterações significativas.

---

# Manual de Otimização e Performance - ARMS

## 18. Regras de Otimização e Performance

### 18.1 Mentalidade de Performance

Toda funcionalidade deve ser desenvolvida considerando desempenho, escalabilidade, consumo de memória, tempo de resposta, experiência do utilizador e facilidade de manutenção. Nunca implementar uma solução apenas porque funciona; sempre procurar uma solução eficiente.

### 18.2 Não Otimizar Prematuramente

O agente nunca deve fazer otimizações desnecessárias. Antes de otimizar, deve identificar se existe realmente um problema, qual é o gargalo e qual será o impacto. Nunca complicar o código sem necessidade.

### 18.3 Medir Antes de Alterar

Antes de sugerir otimizações, o agente deve identificar o tempo de carregamento, tempo de resposta, utilização de memória, utilização do CPU, número de consultas SQL, tamanho dos ficheiros e número de pedidos HTTP. Nunca assumir que algo é lento sem evidências.

### 18.4 Evitar Código Morto

Sempre procurar por funções não utilizadas, componentes abandonados, imports desnecessários, CSS/JavaScript não utilizados, imagens não utilizadas e bibliotecas sem utilização. Nunca remover automaticamente; sempre apresentar a lista ao utilizador.

### 18.5 JavaScript

- **Sempre utilizar:** Lazy Loading, Code Splitting, Dynamic Imports, Tree Shaking, Debounce, Throttle, Async/Await e Event Delegation.
- **Evitar:** Loops pesados, listeners duplicados, manipulações repetidas do DOM e bibliotecas desnecessárias.

### 18.6 CSS

- **Sempre utilizar:** Reutilização de classes, redução de seletores complexos, variáveis CSS, minimização de ficheiros em produção.
- **Evitar:** `!important`, seletores muito profundos, CSS inline e CSS duplicado.

### 18.7 HTML

- **Sempre utilizar:** HTML semântico, acessibilidade (ARIA quando necessário), estrutura limpa.
- **Evitar:** Criar divs e elementos redundantes ou desnecessários.

### 18.8 Imagens

- **Sempre utilizar:** WebP ou AVIF quando possível, Lazy Loading, compressão, dimensões corretas e `srcset` para responsividade. Nunca carregar imagens maiores do que o necessário.

### 18.9 Fontes

- **Sempre utilizar:** Preload quando necessário, limitar famílias, limitar pesos e utilizar formatos modernos (WOFF2). Evitar carregar dezenas de variantes da mesma fonte.

### 18.10 Backend

- **Sempre utilizar:** Reutilização de conexões, evitar consultas repetidas, cache quando aplicável, paginação e processamento assíncrono quando necessário. Nunca fazer processamento pesado durante a resposta HTTP quando puder ser delegado.

### 18.11 Banco de Dados

- **Sempre utilizar:** Índices adequados, joins otimizados, paginação, prepared statements e transações apenas quando necessárias.
- **Evitar:** `SELECT *`, consultas N+1 e subqueries desnecessárias.

### 18.12 Cache

- **Sempre avaliar:** Cache HTTP, cache de consultas, cache de ficheiros, cache de componentes e cache de configuração. Nunca armazenar dados sensíveis em cache sem proteção.

### 18.13 APIs

- **Sempre utilizar:** Paginação, compressão, respostas consistentes, payload mínimo e versionamento. Nunca devolver informação desnecessária.

### 18.14 Recursos Estáticos

- **Sempre utilizar:** Minificação de CSS e JS, compressão Gzip ou Brotli, cache de navegador e versionamento de assets.

### 18.15 Carregamento Inicial

- **Priorizar:** Conteúdo acima da dobra, CSS crítico, JavaScript diferido, Lazy Loading e preload apenas quando necessário.

### 18.16 Requisições HTTP

- **Sempre reduzir:** Número de pedidos, tamanho das respostas, redirecionamentos e downloads desnecessários.

### 18.17 Renderização

- **Evitar:** Reflows constantes, repaints desnecessários e manipulação repetida do DOM. Sempre agrupar alterações no DOM quando possível.

### 18.18 Dependências

- **Antes de instalar qualquer biblioteca o agente deve perguntar:** Existe algo semelhante já instalado? É possível utilizar JavaScript nativo? Vale realmente a pena adicionar esta dependência? Qual o impacto no bundle? Nunca instalar bibliotecas por conveniência.

### 18.19 Monitorização

- **Sempre incentivar:** Logs, métricas, performance, tempo de resposta, erros e utilização de memória. Nunca desenvolver sem possibilidade de monitorização.

### 18.20 Metas Lighthouse & Core Web Vitals

- Procurar atingir: Performance ≥ 90, Accessibility ≥ 95, Best Practices ≥ 95, SEO ≥ 95.
- Preservar um LCP baixo, INP baixo e CLS próximo de zero. Nunca implementar algo que degrade significativamente estes indicadores.

### 18.21 Escalabilidade

- Perguntar mentalmente: Como isto se comportará com 100, 1.000, 10.000 ou 100.000 utilizadores? Se a solução não escalar, procurar uma alternativa.

### 18.22 Checklist Obrigatório de Otimização

Antes de concluir qualquer tarefa:

- [ ] Existem consultas SQL desnecessárias?
- [ ] Existe código duplicado?
- [ ] Existem imports não utilizados?
- [ ] Existem ficheiros demasiado grandes?
- [ ] Existe JavaScript desnecessário?
- [ ] Existe CSS duplicado?
- [ ] Existem imagens pesadas?
- [ ] Existem bibliotecas sem utilização?
- [ ] Existe possibilidade de Lazy Loading?
- [ ] Existe possibilidade de cache?
- [ ] O bundle aumentou significativamente?
- [ ] A solução mantém a arquitetura?

---

## 19. Regra Especial de Otimização Proativa (Obrigatória)

> **Sempre que o utilizador solicitar uma nova funcionalidade, o agente deve avaliar automaticamente se existem oportunidades de otimização relacionadas com os ficheiros que serão modificados. Essas otimizações devem ser listadas numa secção separada chamada "Sugestões de Otimização" e nunca aplicadas automaticamente sem autorização do utilizador.**

---

# Manual de Responsividade e Compatibilidade - ARMS

## 20. Responsividade e Compatibilidade Multiplataforma

### 20.1 Compatibilidade Obrigatória

Toda funcionalidade desenvolvida deve ser totalmente compatível com Computadores (Desktop), Portáteis (Laptop), Tablets, Smartphones Android e iPhones (iOS). Nenhuma funcionalidade deve ser considerada concluída sem garantir a sua adaptação aos diferentes tamanhos de ecrã.

### 20.2 Mobile First

Sempre desenvolver seguindo a filosofia **Mobile First**. A ordem de desenvolvimento deve ser: 1. Smartphone, 2. Tablet, 3. Desktop. Nunca desenvolver apenas para Desktop e adaptar depois.

### 20.3 Responsividade Obrigatória

Todo componente deve adaptar-se automaticamente aos diferentes tamanhos de ecrã. Evitar: larguras fixas, alturas fixas desnecessárias, elementos que provoquem scroll horizontal, conteúdo cortado e texto sobreposto.

### 20.4 Layout Flexível

Sempre utilizar tecnologias modernas como CSS Grid, Flexbox, Media Queries e unidades relativas (`rem`, `%`, `vw`, `vh`, `clamp()`). Evitar posicionamento absoluto quando não for necessário e medidas fixas em `px` para layouts completos.

### 20.5 Breakpoints Padronizados

Os layouts devem ser concebidos considerando, no mínimo:

- Mobile: até 767px
- Tablet: 768px – 1023px
- Desktop: 1024px – 1439px
- Desktop Grande: 1440px ou superior

Os valores podem ser ajustados conforme o projeto, mas a adaptação deve existir.

### 20.6 Componentes Responsivos

Todo componente deve adaptar largura, altura, margens, espaçamentos, tipografia, botões, ícones e imagens. Nunca depender de um único tamanho de ecrã.

### 20.7 Imagens Responsivas

Sempre utilizar `srcset` quando aplicável, definir dimensões corretas, manter proporções, utilizar `object-fit` quando necessário e aplicar Lazy Loading em imagens não críticas.

### 20.8 Tipografia Adaptativa

As fontes devem escalar corretamente entre dispositivos. Preferir `rem`, `em` e `clamp()`. Evitar tamanhos fixos que prejudiquem a leitura.

### 20.9 Botões e Áreas de Toque

Elementos clicáveis devem ser adequados para dispositivos táteis. Garantir área de toque confortável, espaçamento suficiente entre botões e feedback visual ao toque. Evitar botões demasiado pequenos ou muito próximos.

### 20.10 Navegação

Menus devem adaptar-se automaticamente (ex: Desktop: menu horizontal; Mobile: menu hambúrguer ou equivalente). Nunca esconder funcionalidades importantes apenas em dispositivos móveis.

### 20.11 Formulários

Todos os formulários devem funcionar corretamente em Desktop, Tablet e Smartphone. Garantir campos legíveis, teclado adequado ao tipo de entrada, mensagens de erro claras e botões acessíveis.

### 20.12 Tabelas

Sempre prever adaptação de tabelas para dispositivos móveis. Quando necessário, utilizar scroll horizontal controlado, visualização em cartões ou colunas recolhíveis. Nunca permitir que tabelas quebrem o layout.

### 20.13 Compatibilidade de Navegadores

O sistema deve funcionar corretamente nos principais navegadores modernos: Google Chrome, Microsoft Edge, Mozilla Firefox e Safari. Evitar funcionalidades incompatíveis sem alternativa.

### 20.14 Acessibilidade

Garantir contraste adequado, navegação por teclado, foco visível, atributos ARIA quando necessários e textos alternativos para imagens. A responsividade não deve comprometer a acessibilidade.

### 20.15 Performance em Dispositivos Móveis

O agente deve otimizar o carregamento inicial, consumo de dados, utilização de memória, animações e processamento JavaScript. Evitar funcionalidades que prejudiquem dispositivos com menor capacidade.

### 20.16 Teste Mental Obrigatório

Antes de concluir uma funcionalidade, verificar:

- [ ] O layout funciona em Desktop?
- [ ] O layout funciona em Laptop?
- [ ] O layout funciona em Tablet?
- [ ] O layout funciona em Android?
- [ ] O layout funciona em iPhone?
- [ ] Existe scroll horizontal?
- [ ] Os botões são fáceis de tocar?
- [ ] O texto continua legível?
- [ ] As imagens adaptam-se corretamente?
- [ ] Os formulários continuam utilizáveis?

---

## 21. Regra de Ouro da Responsividade (Obrigatória)

> **Nenhuma funcionalidade, página ou componente deve ser considerado concluído sem garantir compatibilidade com Desktop, Laptop, Tablet e Smartphone. Toda interface deve ser responsiva, acessível, otimizada e manter a mesma qualidade de experiência em qualquer dispositivo suportado.**

<!-- -->

> **Sempre que o agente criar uma nova página, componente ou funcionalidade visual, deve incluir automaticamente a adaptação para Desktop, Tablet e Smartphone no mesmo desenvolvimento. É proibido deixar a responsividade para uma tarefa futura, salvo autorização explícita do utilizador.**

---

# Práticas de Engenharia e Integridade de Software

## 22. Regras de Integridade do Projeto

- Nunca apagar código sem autorização.
- Nunca alterar funcionalidades não relacionadas com a tarefa.
- Nunca alterar ficheiros que não sejam necessários.
- Nunca modificar configurações globais sem aprovação.
- Nunca alterar dependências sem explicar o impacto.
- Sempre preservar a compatibilidade do projeto.
- Sempre manter o projeto compilável e executável após cada alteração.

## 23. Regras de Desenvolvimento Incremental

- O agente nunca deve fazer alterações gigantes; deve sempre trabalhar em pequenas etapas.
- Fluxo obrigatório de desenvolvimento: **Analisar -> Planejar -> Implementar -> Validar -> Explicar**
- Nunca alterar dezenas de ficheiros ao mesmo tempo sem necessidade.

## 24. Regras para Refatoração

- Nunca refatorar apenas porque "fica mais bonito".
- Só refatorar quando: melhorar desempenho, reduzir duplicação, corrigir arquitetura, aumentar legibilidade ou corrigir problemas reais.
- Nunca refatorar durante a aplicação de correções simples.

## 25. Regras para Componentização

- Sempre avaliar se o componente pode ser reutilizado. Se sim, criar componente reutilizável; se não, criar componente local.
- Evitar criação de componentes gigantes.

## 26. Regras para Escalabilidade e Consistência

- O agente deve conceber soluções arquiteturais limpas prevendo o crescimento do projeto (ex: dezenas de páginas, centenas de usuários e milhares de registros).
- **Consistência Visual:** Nunca criar cores, botões, ícones, tipografias ou espaçamentos diferentes dos já pré-definidos no Design System do projeto.

## 27. Regras para Configuração, Segurança e Erros

- **Segurança:** Nunca colocar senhas, tokens, URLs de produção, credenciais ou API Keys dentro do código. Utilizar sempre `.env` ou variáveis de ambiente dedicadas.
- **Erros:** Nunca deixar erros silenciosos. Tratar sempre com `try/catch` contendo tratamento adequado, log interno e mensagens amigáveis ao usuário.
- **Logging:** Logs devem ser úteis para resolver problemas. Nunca registrar dados pessoais ou informações críticas (passwords, tokens, cookies, JWT, dados bancários).

## 28. UX, SEO e Acessibilidade

- **UX:** Toda funcionalidade deve considerar feedback visual, estados de carregamento (loading), mensagens claras de erro/sucesso e confirmações para evitar interfaces inativas ("mortas").
- **Acessibilidade:** Garantir contraste adequado, navegação por teclado, atributos ARIA, textos alternativos (`alt`) em imagens, labels nos formulários e foco visível.
- **SEO:** Manter title, description, Open Graph, Canonical, hierarquia de headings correta e Schema Markup quando aplicável.

## 29. Internacionalização (i18n)

- Nunca colocar textos fixos no código (hardcoded) se o sistema puder ter vários idiomas. Estruturar arquivos de tradução (ex: Português, Inglês e Francês).

## 30. Regras para IA e Revisão Automática

- **Nível de Confiança:** O agente deve indicar o seu nível de confiança nas propostas de modificação (Alta, Média ou Baixa confiança) e pedir confirmação caso seja Baixa.
- **Revisão Automática:** Antes de submeter ou concluir qualquer tarefa, realizar a checklist mental:
  - Existe código duplicado ou morto?
  - A solução mantém a arquitetura, é segura, escalável, responsiva, acessível e reutilizável?
  - Respeita todas as regras e padrões existentes no projeto?
- **Regra Anti-"Gambiarra":** Nunca implementar soluções temporárias como permanentes ou código apenas para "funcionar". Toda solução deve ser suficientemente robusta para ambiente de produção.
- **Regra de Padrão do Projeto:** Sempre analisar e seguir o padrão adotado na base de código atual para que o projeto pareça desenvolvido por uma única equipe.
- **Regra de Evolução Contínua:** Sempre que modificar um ficheiro, identificar e propor oportunidades de melhoria ao usuário em uma seção separada ("Oportunidades de melhoria encontradas"), sem aplicá-las automaticamente.

---

# Regras de Carregamento e Renderização - ARMS

## 31. Carregamento e Renderização Estável

### 31.1 Regra 1 — Carregamento sem Bloqueios

Nunca bloquear a renderização da página. Evitar:
- CSS desnecessário
- JavaScript bloqueante
- Chamadas síncronas
- Processamento pesado antes da renderização

A interface deve aparecer o mais rapidamente possível.

### 31.2 Regra 2 — Evitar Flash Visual (Flickering & Layout Shifts)

Nunca permitir:
- Conteúdo a saltar
- Elementos a mudar de posição
- Menus a aparecer depois
- Textos a alterar tamanho
- Imagens a empurrar conteúdo

A página deve parecer estável e reservada desde o primeiro momento.

### 31.3 Regra 3 — Não Carregar Recursos Desnecessários

Antes de importar qualquer recurso, perguntar:
- É realmente necessário nesta página?
- Está a ser utilizado?
- Pode ser carregado depois?

Nunca importar CSS, JavaScript, fontes ou bibliotecas que não sejam utilizados na página atual.

### 31.4 Regra 4 — Carregamento por Página

Cada página deve carregar apenas os recursos que utiliza. Nunca carregar globalmente recursos exclusivos de uma única página.
*Exemplo:* A página "Relatórios" não deve carregar gráficos se o utilizador estiver na página "Login".

### 31.5 Regra 5 — Lazy Loading Inteligente

Carregar imediatamente apenas o essencial acima da dobra. Carregar posteriormente:
- Imagens
- Gráficos
- Tabelas pesadas
- Módulos secundários
- Componentes abaixo da dobra

### 31.6 Regra 6 — CSS Crítico

O CSS necessário para a primeira renderização deve ter prioridade. CSS secundário pode ser carregado depois. Nunca bloquear o carregamento inicial com folhas de estilo que não influenciam a primeira visualização.

### 31.7 Regra 7 — JavaScript Não Bloqueante

Nunca executar JavaScript pesado durante o carregamento inicial. Priorizar:
- `defer`
- `async`
- `dynamic import`

Executar apenas quando necessário.

### 31.8 Regra 8 — Importações Inteligentes

Nunca importar bibliotecas inteiras quando apenas uma pequena funcionalidade é utilizada. Preferir importações específicas.

### 31.9 Regra 9 — Avaliação de Dependências

Antes de adicionar qualquer dependência, perguntar:
- Já existe outra que faz o mesmo?
- É possível utilizar JavaScript nativo?
- Vale o custo em desempenho?

### 31.10 Regra 10 — Layout Estável e Reservado

Sempre reservar espaço para imagens, vídeos, banners e componentes dinâmicos. Evitar deslocamentos (layout shifts) durante o carregamento.

### 31.11 Regra 11 — Fontes Otimizadas

As fontes nunca devem provocar alterações bruscas na interface. Utilizar:
- Preload quando necessário
- `font-display: swap` ou estratégia adequada ao projeto
- Formatos modernos (WOFF2)

### 31.12 Regra 12 — Animações Suaves

Nunca iniciar animações antes da renderização completa dos elementos. As animações não devem bloquear a interação do utilizador.

### 31.13 Regra 13 — Navegação Entre Páginas

As transições devem ser suaves. Evitar:
- Ecrãs brancos prolongados
- Reconstrução completa da interface quando desnecessária
- Recarregamentos completos em aplicações que suportam navegação parcial

### 31.14 Regra 14 — Componentes Persistentes

Quando apropriado, manter componentes comuns entre páginas (menu, cabeçalho, barra lateral, rodapé). Evitar recriar componentes idênticos a cada navegação.

### 31.15 Regra 15 — Estado da Aplicação

Preservar estados quando possível. Evitar recarregar preferências, configurações e dados que já existem em memória.

### 31.16 Regra 16 — Evitar Requisições Repetidas

Nunca fazer várias chamadas iguais durante a mesma navegação. Reutilizar dados quando possível.

### 31.17 Regra 17 — Ordem Estrita de Carregamento

Prioridade de carregamento:
1. Estrutura HTML
2. CSS crítico
3. Conteúdo principal
4. Componentes visíveis
5. JavaScript essencial
6. Conteúdo secundário
7. Recursos opcionais

### 31.18 Regra 18 — Skeleton Loading

Quando o conteúdo demorar mais do que o esperado, preferir "skeletons" ou indicadores de carregamento em vez de deixar áreas vazias.

### 31.19 Regra 19 — Pré-carregamento Inteligente

Sempre que possível:
- Prefetch de páginas prováveis
- Preload de recursos críticos
- Preconnect para serviços externos

Nunca exagerar para não desperdiçar largura de banda.

### 31.20 Regra 20 — Revisão Obrigatória de Importações

Antes de concluir qualquer funcionalidade, verificar:
- [ ] Existem CSS não utilizados?
- [ ] Existem scripts não utilizados?
- [ ] Existem imagens carregadas sem necessidade?
- [ ] Existem bibliotecas pesadas que podem ser substituídas?
- [ ] Existem importações duplicadas?
- [ ] Existem pedidos HTTP desnecessários?
- [ ] Existem ficheiros carregados antes do momento certo?

---

## 32. Regra de Ouro de Carregamento e Renderização (Obrigatória)

> **Nenhuma página deve carregar recursos que não sejam necessários para a sua renderização. Todo CSS, JavaScript, imagem, fonte ou biblioteca deve ser carregado apenas quando necessário, priorizando uma renderização rápida, estável e sem bloqueios visuais.**

<!-- -->

> **Antes de adicionar uma nova importação (`import`, `require`, `<script>`, `<link>`, fontes ou bibliotecas), o agente deve verificar se esse recurso já está carregado globalmente, se pode ser reutilizado ou se pode ser carregado de forma diferida. É proibido duplicar recursos ou aumentar o custo de carregamento sem uma justificação técnica.**

---

# Separação Obrigatória de Responsabilidades (HTML, CSS e JavaScript)

## 33. Regra 1 — Separação Obrigatória

É proibido concentrar HTML, CSS e JavaScript no mesmo ficheiro quando a arquitetura do projeto permitir a separação.

Cada tecnologia deve possuir o seu próprio ficheiro.

Estrutura obrigatória:

```text
frontend/
pages/
    dashboard/
        dashboard.html
        dashboard.css
        dashboard.js
    login/
        login.html
        login.css
        login.js
```

Nunca criar páginas com centenas de linhas contendo HTML, CSS e JavaScript misturados.

---

## 34. Regra 2 — CSS Externo

Todo CSS deve ficar em ficheiros próprios.

É proibido utilizar:

```html
<style>
...
</style>
```

Exceto:

- CSS crítico muito específico (quando tecnicamente justificado)
- Emails HTML
- Situações excecionais autorizadas

---

## 35. Regra 3 — JavaScript Externo

Todo JavaScript deve permanecer em ficheiros próprios.

Nunca utilizar:

```html
<script>
...
</script>
```

Exceto quando existir uma justificação técnica clara.

---

## 36. Regra 4 — Proibição de Inline

Nunca utilizar atributos de eventos inline no HTML:

```html
onclick=""
onchange=""
onload=""
onkeyup=""
```

Sempre utilizar:

```javascript
addEventListener()
```

Isto melhora:

- organização
- manutenção
- segurança
- reutilização

---

## 37. Regra 5 — Organização por Funcionalidade (Feature-First Architecture)

Cada página deverá possuir os seus próprios ficheiros.
Exemplo:

```text
dashboard/
  dashboard.html
  dashboard.css
  dashboard.js
```

Em vez de organizar por tipo de ficheiro (arquitetura tradicional):

```text
pages/
  dashboard.html
css/
  dashboard.css
js/
  dashboard.js
```

Porque tudo relacionado com a "dashboard" fica agrupado.

Exemplo de arquitetura orientada a funcionalidades (Feature-First) para projetos maiores:

```text
frontend/
  features/
    dashboard/
      dashboard.html
      dashboard.css
      dashboard.js
      dashboard.service.js
      dashboard.constants.js
    users/
      users.html
      users.css
      users.js
      users.service.js
    reports/
  components/
  layouts/
  assets/
    css/
    js/
    images/
    fonts/
  shared/
```

Da mesma forma, no backend:

```text
backend/
  features/
    users/
      controller.php
      service.php
      repository.php
      validator.php
      routes.php
    auth/
    reports/
  config/
  database/
  middleware/
  shared/
```

Esta abordagem é obrigatória para qualquer nova feature no projeto. Para o código já existente, utilizar uma **estratégia de migração gradual**: não refatorar o sistema inteiro de uma vez; manter a arquitetura atual estável e migrar os módulos existentes de forma incremental apenas quando eles forem modificados, e apenas se for seguro fazê-lo sem afetar funcionalidades não relacionadas.
