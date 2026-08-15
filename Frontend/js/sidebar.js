// Sidebar mobile helpers: cria botão hambúrguer no header e adiciona o cabeçalho mobile dentro da sidebar.
function initSidebarMobile() {
    const barraLateral = document.getElementById('barra-lateral');
    const cabecalho = document.querySelector('.cabecalho-principal');

    if (!barraLateral || !cabecalho) {
        return;
    }

    function createMenuButton() {
        const button = document.createElement('button');
        button.id = 'btn-menu-mobile';
        button.type = 'button';
        button.className = 'btn-menu-mobile mobile-only';
        button.setAttribute('aria-label', 'Abrir menu');
        button.style.display = 'none';
        button.style.alignItems = 'center';
        button.style.justifyContent = 'center';
        button.style.width = '44px';
        button.style.height = '44px';
        button.style.minWidth = '44px';
        button.style.minHeight = '44px';
        button.style.borderRadius = '999px';
        button.style.border = 'none';
        button.style.backgroundColor = 'var(--aksanti-dark)';
        button.style.color = 'white';
        button.style.cursor = 'pointer';
        button.style.transition = 'transform 0.2s ease-in-out';
        button.style.zIndex = '1001';
        button.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;display:block;">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>`;
        return button;
    }

    function ensureMenuButton() {
        // O botão de menu já não é injetado no cabeçalho.
        // Em vez disso, usamos o botão "Menu" na barra inferior (bottom-nav).
    }

    function ensureMobileMenuHeader() {
        // Removido para que o logótipo fique naturalmente no topo (sem redundância de botão Voltar)
    }

    function updateMobileUI() {
        const mobileHeader = document.querySelector('.menu-mobile-header');
        const isMobile = window.innerWidth <= 1024;

        if (mobileHeader) {
            mobileHeader.style.display = isMobile ? 'flex' : 'none';
            mobileHeader.style.width = '100%';
        }

        if (!isMobile && barraLateral.classList.contains('aberta')) {
            barraLateral.classList.remove('aberta');
        }
    }

    function bindSidebarEvents() {
        // Nova referência para o botão na barra inferior
        const mobileToggleButton = document.getElementById('btn-bottom-menu');
        
        if (mobileToggleButton) {
            mobileToggleButton.addEventListener('click', (e) => {
                e.preventDefault(); // Evita navegar para #
                barraLateral.classList.toggle('aberta');
            });
        }
    }

    function setupDesktopSidebarToggle() {
        const toggleBtn = document.getElementById('btn-toggle-sidebar');
        const barraLateral = document.getElementById('barra-lateral');

        if (!toggleBtn || !barraLateral) return;

        function enforceSidebarState() {
            const isMinimized = localStorage.getItem('arms_sidebar_minimized') === 'true';
            if (isMinimized && window.innerWidth > 1024) {
                barraLateral.classList.add('minimized');
                document.documentElement.classList.add('sidebar-minimized');
            } else if (window.innerWidth > 1024) {
                barraLateral.classList.remove('minimized');
                document.documentElement.classList.remove('sidebar-minimized');
            }
        }

        // Recuperar o estado do localStorage e forçar imediatamente
        enforceSidebarState();

        // Reforçar o estado após o carregamento completo do DOM para evitar bugs no Safari/Chrome
        window.addEventListener('load', enforceSidebarState);
        // Em navegadores SPA-like ou bfcache, garantir a recuperação de estado
        window.addEventListener('pageshow', enforceSidebarState);

        toggleBtn.addEventListener('click', () => {
            const areaConteudo = document.querySelector('.area-conteudo');
            barraLateral.classList.add('is-toggling');
            if(areaConteudo) areaConteudo.classList.add('is-toggling');
            
            barraLateral.classList.toggle('minimized');
            document.documentElement.classList.toggle('sidebar-minimized');
            const minimizedNow = barraLateral.classList.contains('minimized');
            localStorage.setItem('arms_sidebar_minimized', minimizedNow);

            setTimeout(() => {
                barraLateral.classList.remove('is-toggling');
                if(areaConteudo) areaConteudo.classList.remove('is-toggling');
                window.dispatchEvent(new Event('resize'));
            }, 320);
        });
    }

    function closeSidebarOnOutsideClick(event) {
        const mobileToggleButton = document.getElementById('btn-bottom-menu');
        if (window.innerWidth <= 1024 && barraLateral.classList.contains('aberta')) {
            if (!barraLateral.contains(event.target) && !(mobileToggleButton && mobileToggleButton.contains(event.target))) {
                barraLateral.classList.remove('aberta');
            }
        }
    }

    function markActiveMenuItem() {
        const linksDaSidebar = document.querySelectorAll('.menu-item, .bottom-nav-item');
        const caminhoDaNossaPaginaAtual = window.location.pathname;

        linksDaSidebar.forEach(link => {
            const href = link.getAttribute('href');
            if (href && caminhoDaNossaPaginaAtual.includes(href)) {
                link.classList.add('activo');
            } else {
                link.classList.remove('activo');
            }
        });
    }

    function moveSearchBar() {
        const barraPesquisa = document.getElementById('barra-pesquisa-topo');
        const cabecalhoAcoes = document.querySelector('.cabecalho-acoes');
        if (barraPesquisa && cabecalhoAcoes) {
            // Remove margin-left para alinhar bem na direita
            barraPesquisa.style.marginLeft = '0';
            cabecalhoAcoes.insertBefore(barraPesquisa, cabecalhoAcoes.firstChild);
        }
    }

    moveSearchBar();
    ensureMenuButton();
    ensureMobileMenuHeader();
    bindSidebarEvents();
    setupDesktopSidebarToggle();
    updateMobileUI();
    document.addEventListener('click', closeSidebarOnOutsideClick);
    window.addEventListener('resize', updateMobileUI);
    markActiveMenuItem();
    document.body.classList.add('sidebar-js-loaded');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.documentElement.classList.remove('preload');
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarMobile);
} else {
    initSidebarMobile();
}

// Intercetar cliques no botão '+' (Novo Pedido) em qualquer ecrã
document.addEventListener('click', function(e) {
    const btnNovo = e.target.closest('a[href*="action=novo"], #btn-criar-pedido');
    if (btnNovo) {
        e.preventDefault();
        
        // Função para abrir o modal
        const abrir = () => {
            if (typeof window.abrirModalNovoPedido === 'function') {
                window.abrirModalNovoPedido();
            }
        };

        // Se o JS global do modal já estiver carregado (ou seja, novo-pedido-global.js)
        if (typeof window.abrirModalNovoPedido === 'function') {
            abrir();
        } else {
            // Carregar modal.css se não existir
            if (!document.querySelector('link[href="css/modal.css"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'css/modal.css';
                document.head.appendChild(link);
            }

            const carregarNovoPedido = () => {
                const s2 = document.createElement('script');
                s2.src = 'js/novo-pedido-global.js';
                s2.onload = abrir;
                document.body.appendChild(s2);
            };

            // Verificar se o modal.js básico já está carregado na página
            if (typeof window.abrirModal === 'function') {
                carregarNovoPedido();
            } else {
                // Carregar modal.js primeiro e depois o novo-pedido-global.js
                const s1 = document.createElement('script');
                s1.src = 'js/modal.js';
                s1.onload = carregarNovoPedido;
                document.body.appendChild(s1);
            }
        }
    }
});

// Garantir que todas as telas têm o mesmo cabeçalho (barra de pesquisa) no telemóvel
const headerEsq = document.querySelector('.cabecalho-principal > div:first-child');
if (headerEsq) {
    headerEsq.style.flex = '1';
    
    // Se não tiver a barra de pesquisa, injectamos
    if (!document.getElementById('barra-pesquisa-topo')) {
        const barraHtml = `<div style="position: relative; margin-left: 0; width: 300px; display: none;" id="barra-pesquisa-topo">
            <input type="text" id="input-pesquisa-geral" class="input-controlo" placeholder="Pesquisar pedidos..." style="padding-left: 40px; border-radius: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; width: 100%;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 14px; top: 11px;">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </div>`;
        headerEsq.insertAdjacentHTML('beforeend', barraHtml);
        
        const h2 = headerEsq.querySelector('h2');
        if (h2) {
            h2.classList.add('titulo-pagina-desktop');
        }
    }
}
