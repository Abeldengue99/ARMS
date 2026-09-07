/**
 * ArmsMentions - Componente de menções para textareas
 * Permite digitar @Nome e selecionar um utilizador para inserir @[Nome](uuid)
 */
class ArmsMentions {
    constructor(textareaId) {
        this.textarea = document.getElementById(textareaId);
        if (!this.textarea) return;

        this.container = null;
        this.listElement = null;
        this.query = '';
        this.isActive = false;
        this.cursorPosition = 0;
        this.mentionStartIndex = -1;
        this.results = [];
        this.selectedIndex = 0;
        this.debounceTimer = null;
        this.loading = false;
        
        this.init();
    }

    init() {
        // Criar o container do dropdown no DOM
        this.container = document.createElement('div');
        this.container.className = 'arms-mentions-container';
        this.container.style.display = 'none';
        document.body.appendChild(this.container);

        this.listElement = document.createElement('ul');
        this.listElement.className = 'arms-mentions-list';
        this.container.appendChild(this.listElement);

        // Eventos
        this.textarea.addEventListener('input', this.handleInput.bind(this));
        this.textarea.addEventListener('keydown', this.handleKeydown.bind(this));
        this.textarea.addEventListener('click', this.closeDropdown.bind(this));
        this.textarea.addEventListener('blur', () => {
            // Delay pequeno para permitir o clique nos itens da lista
            setTimeout(() => this.closeDropdown(), 200);
        });
        
        // Esconder ao clicar fora
        document.addEventListener('click', (e) => {
            if (this.isActive && e.target !== this.textarea && !this.container.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }

    handleInput(e) {
        const text = this.textarea.value;
        this.cursorPosition = this.textarea.selectionStart;

        // Procurar o último '@' antes do cursor
        let atIndex = text.lastIndexOf('@', this.cursorPosition - 1);
        
        if (atIndex !== -1) {
            // Verificar se há espaço ou início de linha antes do @
            const isStart = atIndex === 0;
            const hasSpaceBefore = isStart || text[atIndex - 1] === ' ' || text[atIndex - 1] === '\n';
            
            if (hasSpaceBefore) {
                const textAfterAt = text.substring(atIndex + 1, this.cursorPosition);
                // Se o texto não contém espaços, é uma query válida
                if (!/\s/.test(textAfterAt)) {
                    this.mentionStartIndex = atIndex;
                    this.query = textAfterAt;
                    
                    if (this.query.length >= 1) { // Pesquisa a partir de 1 caractere
                        this.openDropdown();
                        this.searchUsers();
                        return;
                    }
                }
            }
        }
        
        this.closeDropdown();
    }

    handleKeydown(e) {
        if (!this.isActive) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
            this.renderList();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
            this.renderList();
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            if (this.results.length > 0) {
                this.selectUser(this.results[this.selectedIndex]);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            this.closeDropdown();
        }
    }

    searchUsers() {
        this.loading = true;
        this.renderList();

        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`api/mencoes-pesquisar.php?q=${encodeURIComponent(this.query)}`)
                .then(res => res.json())
                .then(data => {
                    this.loading = false;
                    if (data.sucesso) {
                        this.results = data.dados || [];
                        this.selectedIndex = 0;
                        this.renderList();
                    } else {
                        this.results = [];
                        this.renderList();
                    }
                })
                .catch(() => {
                    this.loading = false;
                    this.results = [];
                    this.renderList();
                });
        }, 300);
    }

    renderList() {
        this.listElement.innerHTML = '';
        
        if (this.loading) {
            this.listElement.innerHTML = '<div class="arms-mentions-loading">A procurar utilizadores...</div>';
            return;
        }

        if (this.results.length === 0) {
            this.listElement.innerHTML = '<div class="arms-mentions-empty">Nenhum utilizador encontrado</div>';
            return;
        }

        this.results.forEach((user, index) => {
            const li = document.createElement('li');
            li.className = 'arms-mentions-item' + (index === this.selectedIndex ? ' active' : '');
            
            // Destacar o termo pesquisado no nome
            const nameRegex = new RegExp(`(${this.escapeRegExp(this.query)})`, 'gi');
            const highlightedName = this.escapeHtml(user.full_name).replace(nameRegex, '<strong>$1</strong>');
            
            li.innerHTML = `
                <div class="arms-mentions-name">${highlightedName}</div>
                <div class="arms-mentions-email">${this.escapeHtml(user.email)}</div>
            `;
            
            li.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Impede que o textarea perca o foco
                this.selectUser(user);
            });
            
            this.listElement.appendChild(li);
        });
        
        // Auto-scroll se o item ativo não estiver visível
        const activeItem = this.listElement.querySelector('.active');
        if (activeItem) {
            const listRect = this.listElement.getBoundingClientRect();
            const itemRect = activeItem.getBoundingClientRect();
            
            if (itemRect.bottom > listRect.bottom) {
                this.listElement.scrollTop += itemRect.bottom - listRect.bottom;
            } else if (itemRect.top < listRect.top) {
                this.listElement.scrollTop -= listRect.top - itemRect.top;
            }
        }
    }

    selectUser(user) {
        const text = this.textarea.value;
        const beforeMention = text.substring(0, this.mentionStartIndex);
        const afterMention = text.substring(this.cursorPosition);
        
        // Inserir token formatado @[Nome](uuid) + espaço
        const mentionToken = `@[${user.full_name}](${user.id}) `;
        
        this.textarea.value = beforeMention + mentionToken + afterMention;
        
        // Reposicionar o cursor após a menção
        const newCursorPos = beforeMention.length + mentionToken.length;
        this.textarea.focus();
        this.textarea.setSelectionRange(newCursorPos, newCursorPos);
        
        // Disparar evento input para atualizar caches (ex: rascunho de comentário)
        this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
        
        this.closeDropdown();
    }

    openDropdown() {
        this.isActive = true;
        this.container.style.display = 'block';
        this.positionDropdown();
    }

    closeDropdown() {
        this.isActive = false;
        this.container.style.display = 'none';
        this.results = [];
        this.query = '';
    }

    positionDropdown() {
        // Ignorar no mobile (CSS lida com a posição fixa em baixo)
        if (window.innerWidth <= 767) return;
        
        const coords = this.getCaretCoordinates();
        const textareaRect = this.textarea.getBoundingClientRect();
        
        // Posicionar relativamente à viewport para evitar problemas de scroll/z-index parents
        let top = textareaRect.top + coords.top + 20 + window.scrollY;
        let left = textareaRect.left + coords.left + window.scrollX;
        
        // Garantir que não sai da janela
        const maxLeft = window.innerWidth - 340; // Largura max do dropdown + margem
        if (left > maxLeft) left = maxLeft;
        
        this.container.style.top = `${top}px`;
        this.container.style.left = `${left}px`;
    }

    // Adaptado de vários scripts de textarea-caret-position
    getCaretCoordinates() {
        const div = document.createElement('div');
        const style = div.style;
        const computed = window.getComputedStyle(this.textarea);
        
        style.whiteSpace = 'pre-wrap';
        style.wordWrap = 'break-word';
        style.position = 'absolute';
        style.visibility = 'hidden';
        
        const properties = [
            'direction', 'boxSizing', 'width', 'height', 'overflowX', 'overflowY',
            'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
            'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
            'fontStyle', 'fontVariant', 'fontWeight', 'fontStretch', 'fontSize',
            'fontSizeAdjust', 'lineHeight', 'fontFamily', 'textAlign', 'textTransform',
            'textIndent', 'textDecoration', 'letterSpacing', 'wordSpacing'
        ];
        
        properties.forEach(prop => style[prop] = computed[prop]);
        
        // Ocultar barras de scroll do clone
        if (computed.overflowY === 'auto') {
            style.overflowY = 'hidden';
            if (this.textarea.scrollHeight > this.textarea.clientHeight) {
                style.overflowY = 'scroll';
            }
        }
        
        const textToCaret = this.textarea.value.substring(0, this.mentionStartIndex);
        div.textContent = textToCaret;
        
        const span = document.createElement('span');
        span.textContent = this.textarea.value.substring(this.mentionStartIndex) || '.';
        div.appendChild(span);
        
        document.body.appendChild(div);
        
        const coordinates = {
            top: span.offsetTop - this.textarea.scrollTop,
            left: span.offsetLeft - this.textarea.scrollLeft
        };
        
        document.body.removeChild(div);
        return coordinates;
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(match) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return map[match];
        });
    }

    escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
    }
}
