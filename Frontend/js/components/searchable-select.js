/**
 * Componente: Searchable Select (Proxy Component)
 * Descrição: Envolve um <select> nativo e cria uma UI avançada com pesquisa.
 * Garante compatibilidade total com a lógica existente via MutationObserver e Value Setter Proxy.
 */
class SearchableSelect {
    constructor(selectSelector, options = {}) {
        this.selectEl = document.querySelector(selectSelector);
        if (!this.selectEl) return;

        this.options = {
            placeholderText: options.placeholderText || 'Pesquisar...',
            noResultsText: options.noResultsText || 'Sem resultados',
            ...options
        };

        // Estado interno
        this.isOpen = false;
        this.customOptions = [];
        this.defaultOptionData = null;

        this.init();
    }

    init() {
        // Guardar o display actual do select. Usa getComputedStyle para maior fiabilidade
        // bem como verificação explícita do atributo style inline.
        const computedDisplay = window.getComputedStyle(this.selectEl).display;
        const inlineStyle = this.selectEl.getAttribute('style') || '';
        const isHidden = computedDisplay === 'none' || inlineStyle.replace(/\s/g, '').includes('display:none');

        // Construir UI
        this.buildUI();

        // Propagar o display inicial para o container custom
        if (isHidden) {
            this.container.style.display = 'none';
        }

        // Esconder o select nativo permanentemente
        this.selectEl.setAttribute('style', 'display: none !important;');
        
        // Povoar UI inicialmente
        this.syncOptionsFromNative();

        // Bind Eventos
        this.bindEvents();

        // Configurar Observadores para Reatividade Automática
        this.setupObservers();
        
        // Sincronizar o valor inicial
        this.updateTriggerText();
    }

    buildUI() {
        // Container Principal
        this.container = document.createElement('div');
        this.container.className = 'searchable-select-container';
        
        // Ocultar da tabulação se quisermos focar só no custom, mas como é um select oculto não precisa
        this.selectEl.parentNode.insertBefore(this.container, this.selectEl);
        this.container.appendChild(this.selectEl);

        // Gatilho (Botão)
        this.trigger = document.createElement('div');
        this.trigger.className = 'searchable-select-trigger';
        this.trigger.tabIndex = 0; // Acessível
        
        this.triggerText = document.createElement('div');
        this.triggerText.className = 'searchable-select-trigger-text';
        this.triggerText.textContent = 'A carregar...'; // Será substituído rápido
        
        // Ícone Seta SVG
        const iconSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        iconSvg.setAttribute('viewBox', '0 0 24 24');
        iconSvg.setAttribute('fill', 'none');
        iconSvg.setAttribute('stroke-width', '2');
        iconSvg.setAttribute('stroke', 'currentColor');
        iconSvg.setAttribute('stroke-linecap', 'round');
        iconSvg.setAttribute('stroke-linejoin', 'round');
        iconSvg.classList.add('searchable-select-trigger-icon');
        const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        polyline.setAttribute('points', '6 9 12 15 18 9');
        iconSvg.appendChild(polyline);

        this.trigger.appendChild(this.triggerText);
        this.trigger.appendChild(iconSvg);

        // Popover (Dropdown)
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown';

        // Input de Pesquisa
        this.searchWrapper = document.createElement('div');
        this.searchWrapper.className = 'searchable-select-search-wrapper';
        
        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'searchable-select-search';
        this.searchInput.placeholder = this.options.placeholderText;

        this.searchWrapper.appendChild(this.searchInput);

        // Opção Padrão Fixa (Topo)
        this.defaultOptionEl = document.createElement('div');
        this.defaultOptionEl.className = 'searchable-select-default-option';
        
        // Lista de Opções Dinâmicas
        this.optionsList = document.createElement('ul');
        this.optionsList.className = 'searchable-select-options-list';

        // Estado Vazio
        this.emptyState = document.createElement('div');
        this.emptyState.className = 'searchable-select-empty';
        this.emptyState.textContent = this.options.noResultsText;

        // Montagem
        this.dropdown.appendChild(this.searchWrapper);
        this.dropdown.appendChild(this.defaultOptionEl);
        this.dropdown.appendChild(this.optionsList);
        this.dropdown.appendChild(this.emptyState);

        this.container.appendChild(this.trigger);
        this.container.appendChild(this.dropdown);
    }

    // Lê os <option> do <select> nativo e recria a lista visual
    syncOptionsFromNative() {
        this.optionsList.innerHTML = '';
        this.customOptions = [];
        this.defaultOptionData = null;

        const nativeOptions = Array.from(this.selectEl.options);
        
        nativeOptions.forEach((opt, index) => {
            // A primeira opção com value vazio ou a primeira absoluta é considerada a Padrão (Ex: "Todas as Empresas")
            if (opt.value === '' || (index === 0 && nativeOptions.length > 0 && !opt.value)) {
                this.defaultOptionData = { value: opt.value, text: opt.text, index: index };
                this.defaultOptionEl.innerHTML = `<span>${opt.text}</span>` + this.getCheckIcon();
                this.defaultOptionEl.dataset.value = opt.value;
                this.defaultOptionEl.style.display = 'flex';
                return; // Não adiciona à lista com scroll
            }

            // Para as restantes, cria LI normais
            const li = document.createElement('li');
            li.className = 'searchable-select-option';
            li.dataset.value = opt.value;
            li.innerHTML = `<span>${opt.text}</span>` + this.getCheckIcon();
            
            this.optionsList.appendChild(li);
            
            this.customOptions.push({
                element: li,
                text: opt.text,
                value: opt.value
            });
        });

        // Se não existir opção padrão explícita, oculta a div de opção padrão fixa
        if (!this.defaultOptionData) {
            this.defaultOptionEl.style.display = 'none';
        }

        this.updateTriggerText();
        this.updateSelectedStateUI();
    }

    getCheckIcon() {
        return `<svg class="searchable-select-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
    }

    bindEvents() {
        // Clicar no Trigger abre/fecha
        this.trigger.addEventListener('click', () => {
            this.toggleDropdown();
        });

        // Fechar ao clicar fora
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.closeDropdown();
            }
        });

        // Clicar na Opção Padrão
        this.defaultOptionEl.addEventListener('click', () => {
            this.selectValue(this.defaultOptionEl.dataset.value);
        });

        // Delegação de eventos para as opções da lista (performance melhorada)
        this.optionsList.addEventListener('click', (e) => {
            const li = e.target.closest('.searchable-select-option');
            if (li) {
                this.selectValue(li.dataset.value);
            }
        });

        // Digitar na pesquisa
        this.searchInput.addEventListener('input', (e) => {
            this.filterOptions(e.target.value);
        });
    }

    setupObservers() {
        // 1. MutationObserver: Captura adições de <option> vindas de fetch() no dashboard.js
        const observer = new MutationObserver((mutations) => {
            let shouldSync = false;
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    shouldSync = true;
                }
            });
            if (shouldSync) {
                this.syncOptionsFromNative();
            }
        });

        observer.observe(this.selectEl, { childList: true });

        // 2. Monkey Patch setter do 'value' (Intercepta o botão Limpar)
        const proto = window.HTMLSelectElement.prototype;
        const originalSetter = Object.getOwnPropertyDescriptor(proto, "value").set;
        
        Object.defineProperty(this.selectEl, 'value', {
            configurable: true,
            set: (val) => {
                originalSetter.call(this.selectEl, val);
                this.updateTriggerText();
                this.updateSelectedStateUI();
            },
            get: () => {
                return Object.getOwnPropertyDescriptor(proto, "value").get.call(this.selectEl);
            }
        });

        // 3. Interceptar style.display do select nativo
        // Quando o dashboard.js faz selectCliente.style.display = 'block' ou 'none',
        // redirecionamos essa visibilidade para o container custom.
        // O select nativo permanece SEMPRE oculto (display: none).
        const nativeStyle = this.selectEl.style;
        const container = this.container;
        const selectElement = this.selectEl;

        Object.defineProperty(nativeStyle, 'display', {
            configurable: true,
            set: (val) => {
                // Propagar visibilidade ao container custom
                if (val === 'none' || val === '') {
                    container.style.display = 'none';
                } else {
                    container.style.display = val;
                }
                // O select nativo NUNCA fica visível
                // Usar setAttribute no elemento para evitar recursão no setter
                selectElement.setAttribute('style', 'display: none !important;');
            },
            get: () => {
                // Devolver o display do container (para que o dashboard.js "veja" o estado correto)
                return container.style.display;
            }
        });
    }

    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    openDropdown() {
        this.isOpen = true;
        this.container.classList.add('is-open');
        this.searchInput.value = ''; // Limpa pesquisa ao abrir
        this.filterOptions(''); // Mostra todos
        
        // Focar no campo de pesquisa para tipagem rápida
        setTimeout(() => this.searchInput.focus(), 50);
    }

    closeDropdown() {
        this.isOpen = false;
        this.container.classList.remove('is-open');
    }

    selectValue(val) {
        // 1. Atualizar o DOM Nativo
        this.selectEl.value = val;
        
        // 2. Disparar evento para o dashboard.js detetar
        this.selectEl.dispatchEvent(new Event('change', { bubbles: true }));

        // 3. Fechar menu e atualizar UI
        this.closeDropdown();
        this.updateTriggerText();
        this.updateSelectedStateUI();
    }

    updateTriggerText() {
        const selectedIndex = this.selectEl.selectedIndex;
        if (selectedIndex >= 0 && this.selectEl.options[selectedIndex]) {
            this.triggerText.textContent = this.selectEl.options[selectedIndex].text;
        } else if (this.defaultOptionData) {
            this.triggerText.textContent = this.defaultOptionData.text;
        }
    }

    updateSelectedStateUI() {
        const val = this.selectEl.value;

        // Atualizar classe na opção default
        if (this.defaultOptionEl.dataset.value === val) {
            this.defaultOptionEl.classList.add('is-selected');
        } else {
            this.defaultOptionEl.classList.remove('is-selected');
        }

        // Atualizar classes nas opções da lista
        this.customOptions.forEach(opt => {
            if (opt.value === val) {
                opt.element.classList.add('is-selected');
            } else {
                opt.element.classList.remove('is-selected');
            }
        });
    }

    filterOptions(query) {
        const term = query.toLowerCase().trim();
        let matchCount = 0;

        this.customOptions.forEach(opt => {
            if (opt.text.toLowerCase().includes(term)) {
                opt.element.classList.remove('is-hidden');
                matchCount++;
            } else {
                opt.element.classList.add('is-hidden');
            }
        });

        if (matchCount === 0 && this.customOptions.length > 0) {
            this.emptyState.style.display = 'block';
            this.optionsList.style.display = 'none';
        } else {
            this.emptyState.style.display = 'none';
            this.optionsList.style.display = 'block';
        }
    }
}
