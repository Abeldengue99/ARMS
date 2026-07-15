document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('centro-pendencias-container');
    const grelha = document.getElementById('grelha-pendencias');

    if (!container || !grelha) return;

    // Apenas mostrar a super admins
    try {
        const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
        if (ud.admin !== true) {
            container.style.display = 'none';
            return;
        }
    } catch (e) {
        return;
    }

    fetch('api/pendencias.php')
        .then(r => r.json())
        .then(data => {
            if (!data.sucesso) {
                container.style.display = 'block';
                grelha.innerHTML = `<div style="color: #ef4444; font-size: 0.9rem;">Erro: ${data.erro}</div>`;
                return;
            }

            const p = data.pendencias;
            let html = '';

            const cartoes = [
                { id: 'pedidos-novos', valor: p.pedidos_novos, titulo: 'Pedidos Novos', cor: '#10b981', bg: '#ecfdf5', icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', link: 'pedidos.html' },
                { id: 'pedidos-alteracoes', valor: p.pedidos_alteracoes, titulo: 'Alterações Solicitadas', cor: '#8b5cf6', bg: '#f5f3ff', icon: 'M12 20h9 M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z', link: 'pedidos.html' },
                { id: 'prazo-vencido', valor: p.pedidos_deadline_vencido, titulo: 'Prazos Vencidos', cor: '#ef4444', bg: '#fef2f2', icon: 'M12 2v20 M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', link: 'pedidos.html' },
                { id: 'prazo-proximo', valor: p.pedidos_proximos_prazo, titulo: 'Prazos Próximos (48h)', cor: '#f59e0b', bg: '#fffbeb', icon: 'M12 2v20 M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', link: 'pedidos.html' }
            ];

            cartoes.forEach(c => {
                if (c.valor > 0) {
                    html += `
                        <a href="${c.link}" data-pendencia-id="${c.id}" style="text-decoration: none; display: flex; align-items: center; gap: 12px; background: ${c.bg}; padding: 12px 16px; border-radius: 8px; border: 1px solid ${c.cor}40;">
                            <div style="background: ${c.cor}; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; flex-shrink: 0;" data-contagem="${c.valor}">
                                0
                            </div>
                            <div>
                                <p style="margin: 0; color: ${c.cor}; font-weight: 600; font-size: 0.95rem; line-height: 1.2;">${c.titulo}</p>
                            </div>
                        </a>
                    `;
                }
            });

            if (html === '') {
                if (window.location.pathname.endsWith('pedidos.html')) {
                    container.style.display = 'none';
                } else {
                    container.style.display = 'block';
                    grelha.innerHTML = `<div style="color: #059669; font-size: 0.95rem; font-weight: 500; display: flex; align-items: center; gap: 6px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Tudo em ordem! Não há pendências a relatar.</div>`;
                }
            } else {
                container.style.display = 'block';
                grelha.innerHTML = html;
                
                // Interceptar cliques se estivermos na página de pedidos
                grelha.addEventListener('click', (e) => {
                    const link = e.target.closest('a[data-pendencia-id]');
                    if (link && window.location.pathname.endsWith('pedidos.html')) {
                        e.preventDefault();
                        const pid = link.getAttribute('data-pendencia-id');
                        window.dispatchEvent(new CustomEvent('ArmsFiltrarPendencia', { detail: pid }));
                    }
                });
                
                // Efeito de contagem rápida
                const contadores = grelha.querySelectorAll('[data-contagem]');
                contadores.forEach(el => {
                    const alvo = parseInt(el.getAttribute('data-contagem'));
                    let atual = 0;
                    const incremento = Math.max(1, Math.floor(alvo / 10)); // Dividir por 10 passos
                    const intervalo = setInterval(() => {
                        atual += incremento;
                        if (atual >= alvo) {
                            atual = alvo;
                            clearInterval(intervalo);
                        }
                        el.textContent = atual;
                    }, 30);
                });
            }
        })
        .catch(err => {
            container.style.display = 'block';
            grelha.innerHTML = `<div style="color: #ef4444; font-size: 0.9rem;">Erro de ligação ao obter pendências.</div>`;
        });
});
