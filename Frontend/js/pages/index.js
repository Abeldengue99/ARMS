
      // Toggle mostrar/esconder senha
document.getElementById('btn-ver-senha').addEventListener('click', () => {
    const campoSenha = document.getElementById('senha');
    const olhoAberto = document.getElementById('icone-olho-aberto');
    const olhoFechado = document.getElementById('icone-olho-fechado');
    if (campoSenha.type === 'password') {
        campoSenha.type = 'text';
        olhoAberto.style.display = 'none';
        olhoFechado.style.display = 'block';
    } else {
        campoSenha.type = 'password';
        olhoAberto.style.display = 'block';
        olhoFechado.style.display = 'none';
    }
});

// Modal de recuperação de senha
const modalRecuperar = document.getElementById('modal-recuperar');
document.querySelector('a[data-i18n="login.esqueceu_senha"]').addEventListener('click', (e) => {
    e.preventDefault();
    modalRecuperar.style.display = 'flex';
});
document.getElementById('btn-fechar-recuperar').addEventListener('click', () => {
    modalRecuperar.style.display = 'none';
});
modalRecuperar.addEventListener('click', (e) => {
    if (e.target === modalRecuperar) modalRecuperar.style.display = 'none';
});
document.getElementById('btn-enviar-recuperar').addEventListener('click', () => {
    const emailRecuperar = document.getElementById('email-recuperar').value.trim();
    const feedback = document.getElementById('feedback-recuperar');
    const btn = document.getElementById('btn-enviar-recuperar');
    if (!emailRecuperar) {
        feedback.style.display = 'block';
        feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
        feedback.style.color = '#ef4444';
        feedback.textContent = 'Por favor, introduza o seu e-mail.';
        return;
    }
    btn.textContent = 'A enviar...';
    btn.disabled = true;
     // Apontar diretamente para o Backend no Coolify
   fetch('https://backend.arms.support/api/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, senha })
})
    .then(r => r.json())
    .then(data => {
        feedback.style.display = 'block';
        if (data.sucesso) {
            feedback.style.backgroundColor = 'rgba(34,197,94,0.1)';
            feedback.style.color = '#22c55e';
            feedback.textContent = data.mensagem;
            setTimeout(() => { modalRecuperar.style.display = 'none'; }, 3000);
        } else {
            feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
            feedback.style.color = '#ef4444';
            feedback.textContent = data.erro;
        }
        btn.textContent = 'Enviar Instruções';
        btn.disabled = false;
    })
    .catch(() => {
        feedback.style.display = 'block';
        feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
        feedback.style.color = '#ef4444';
        feedback.textContent = 'Erro de ligação ao servidor.';
        btn.textContent = 'Enviar Instruções';
        btn.disabled = false;
    });
});

// Login - limpar dados antigos e autenticar
document.getElementById('form-login').addEventListener('submit', (evento) => {
    evento.preventDefault();
    
    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value.trim();
    const btn = evento.target.querySelector('button[type="submit"]');
    
    if (!email || !senha) {
        mostrarMensagem('Atenção', 'Por favor, preencha o email e a palavra-passe.');
        return;
    }
    
    const textoOriginal = btn.textContent;
    btn.textContent = 'A entrar...';
    btn.disabled = true;
    
    // IMPORTANTE: Limpar dados da sessão anterior para evitar conflitos entre contas
    localStorage.removeItem('arms_utilizador_logado');
    localStorage.removeItem('arms_utilizador_dados');

    // Apontar diretamente para o Backend no Coolify
   fetch('https://backend.arms.support/api/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, senha })
})
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            localStorage.setItem('arms_utilizador_logado', 'true');
            localStorage.setItem('arms_utilizador_dados', JSON.stringify(data.utilizador));

            if (data.utilizador?.senha_expirada === true || data.utilizador?.password_expired === true) {
                window.location.href = 'perfil.html?senha_expirada=1';
            } else {
                window.location.href = 'dashboard.html';
            }
        } else {
            mostrarMensagem('Atenção', data.erro || 'Não foi possível iniciar sessão.');
            btn.textContent = textoOriginal;
            btn.disabled = false;
        }
    })
    .catch(err => {
        mostrarMensagem('Atenção', 'Erro de ligação ao servidor.');
        btn.textContent = textoOriginal;
        btn.disabled = false;
        console.error(err);
    });
});
