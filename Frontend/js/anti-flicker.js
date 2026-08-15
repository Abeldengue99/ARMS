/**
 * ARMS — Anti-Flicker & Role Initialization Script
 * Executado síncronamente no <head> para evitar saltos visuais (Flicker)
 * e garantir inicialização segura dos papéis de utilizador sem travamentos (Tela Presa).
 */
(function () {
    try {
        if (localStorage.getItem('arms_sidebar_minimized') === 'true' && window.innerWidth > 1024) {
            document.documentElement.classList.add('sidebar-minimized');
        }
        var dados = JSON.parse(localStorage.getItem('arms_utilizador_dados') || 'null');
        if (!dados || dados.admin !== true) {
            document.documentElement.classList.add('is-client-role');
            document.documentElement.classList.remove('is-admin-role');
        } else {
            document.documentElement.classList.add('is-admin-role');
            document.documentElement.classList.remove('is-client-role');
        }
    } catch (e) {
    }
})();
