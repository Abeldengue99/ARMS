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
});
