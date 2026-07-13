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
        if (!document.getElementById('btn-menu-mobile')) {
            const btn = createMenuButton();
            cabecalho.insertBefore(btn, cabecalho.firstChild);
        }
    }

    function ensureMobileMenuHeader() {
        if (!document.querySelector('.menu-mobile-header')) {
            const mobileHeader = document.createElement('div');
            mobileHeader.className = 'menu-mobile-header';
            mobileHeader.style.display = 'none';
            mobileHeader.style.width = '100%';
            mobileHeader.style.padding = '16px 20px';
            mobileHeader.style.boxSizing = 'border-box';
            mobileHeader.style.justifyContent = 'space-between';
            mobileHeader.style.alignItems = 'center';
            mobileHeader.style.backgroundColor = 'var(--aksanti-dark)';
            mobileHeader.style.position = 'absolute';
            mobileHeader.style.top = '0';
            mobileHeader.style.left = '0';
            mobileHeader.style.zIndex = '1002';
            mobileHeader.innerHTML = `
                <span style="color: white; font-weight: 600;">Menu</span>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button id="btn-fechar-menu-mobile" type="button" aria-label="Voltar" style="border:none;background:transparent;color:white;font-size:0.95rem;cursor:pointer;">Voltar</button>
                </div>`;
            barraLateral.insertBefore(mobileHeader, barraLateral.firstChild);
        }
    }

    function updateMobileUI() {
        const mobileToggleButton = document.getElementById('btn-menu-mobile');
        const mobileHeader = document.querySelector('.menu-mobile-header');
        const isMobile = window.innerWidth <= 1024;

        if (mobileToggleButton) {
            mobileToggleButton.style.display = isMobile ? 'inline-flex' : 'none';
            mobileToggleButton.style.width = '44px';
            mobileToggleButton.style.height = '44px';
            mobileToggleButton.style.minWidth = '44px';
            mobileToggleButton.style.minHeight = '44px';
        }

        if (mobileHeader) {
            mobileHeader.style.display = isMobile ? 'flex' : 'none';
            mobileHeader.style.width = '100%';
        }

        if (!isMobile && barraLateral.classList.contains('aberta')) {
            barraLateral.classList.remove('aberta');
        }
    }

    function bindSidebarEvents() {
        const mobileToggleButton = document.getElementById('btn-menu-mobile');
        const mobileCloseButton = document.getElementById('btn-fechar-menu-mobile');

        if (mobileToggleButton) {
            mobileToggleButton.addEventListener('click', () => {
                barraLateral.classList.toggle('aberta');
            });
        }

        if (mobileCloseButton) {
            mobileCloseButton.addEventListener('click', () => {
                barraLateral.classList.remove('aberta');
            });
        }
    }

    function closeSidebarOnOutsideClick(event) {
        const mobileToggleButton = document.getElementById('btn-menu-mobile');
        if (window.innerWidth <= 1024 && barraLateral.classList.contains('aberta')) {
            if (!barraLateral.contains(event.target) && !(mobileToggleButton && mobileToggleButton.contains(event.target))) {
                barraLateral.classList.remove('aberta');
            }
        }
    }

    function markActiveMenuItem() {
        const linksDaSidebar = document.querySelectorAll('.menu-item');
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

    ensureMenuButton();
    ensureMobileMenuHeader();
    bindSidebarEvents();
    updateMobileUI();
    document.addEventListener('click', closeSidebarOnOutsideClick);
    window.addEventListener('resize', updateMobileUI);
    markActiveMenuItem();
    document.body.classList.add('sidebar-js-loaded');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarMobile);
} else {
    initSidebarMobile();
}
