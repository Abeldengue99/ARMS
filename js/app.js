// Estou a ligar as atenções (aos event listeners) no carregamento base.
document.addEventListener('DOMContentLoaded', () => {
    
    // Iniciar a verificação de sessão assim que possível
    if (typeof ArmsSessao !== 'undefined') {
        ArmsSessao.verificar();
    } else {
        // Fallback antigo caso o script tempo-real.js ainda não esteja na página
        const donoDoLogin = localStorage.getItem('arms_utilizador_logado');
        if (!donoDoLogin && !window.location.pathname.endsWith('index.html')) {
            window.location.href = 'index.html';
        }
    }

    carregarIdentidadePlataforma();

    document.body.addEventListener('click', (e) => {
        const targetBtn = e.target.closest('#btn-sair, #btn-sair-mobile, .menu-sair, .btn-sair-icone');
        if (!targetBtn) return;
        
        e.preventDefault();
        let nome = '';
        try {
            const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
            if (ud.nome) {
                nome = '<strong>' + ud.nome.split(' ')[0] + '</strong>, ';
            }
        } catch(e) {}
        
        const titulo = (typeof window.t === 'function') ? window.t('nav.sair', 'Terminar Sessão') : 'Terminar Sessão';
        const fallbackMsg = nome + 'tens a certeza que queres terminar a sessão?';
        const msg = (typeof window.t === 'function') ? window.t('comum.confirmar_sair_nome', fallbackMsg) : fallbackMsg;
        
        if (typeof confirmarAcao === 'function') {
            confirmarAcao(titulo, msg, () => {
                if (typeof ArmsSessao !== 'undefined') {
                    ArmsSessao.terminar();
                } else {
                    localStorage.removeItem('arms_utilizador_logado');
                    window.location.href = 'index.html';
                }
            });
        } else if (confirm(msg)) {
            if (typeof ArmsSessao !== 'undefined') {
                ArmsSessao.terminar();
            } else {
                localStorage.removeItem('arms_utilizador_logado');
                window.location.href = 'index.html';
            }
        }
    });
    // Pesquisa transferida para dashboard.html para ser 'ao vivo'
});

function carregarIdentidadePlataforma() {
    fetch('api/identidade-plataforma.php')
        .then(r => r.json())
        .then(data => {
            if (data.sucesso && data.dados) {
                const settings = data.dados;
                // 1. Aplicar a Cor Primária (Whitelabel Color Override)
                if (settings.primary_color) {
                    document.documentElement.style.setProperty('--aksanti-gold', settings.primary_color);
                }
                
                // 2. Atualizar todos os Logótipos
                if (settings.logo_url) {
                    document.querySelectorAll('.logo-svg').forEach(img => {
                        img.src = settings.logo_url;
                    });
                }
                
                // 3. Atualizar o nome do sistema no Title
                if (settings.system_name) {
                    document.title = document.title.replace('ARMS', settings.system_name);
                }
            }
        })
        .catch(e => console.error('Falha ao carregar identidade visual da plataforma:', e));
}
