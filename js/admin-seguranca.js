let paginaAtualHistorico = 1;
const TAMANHO_PAGINA_HISTORICO = 15;
let historicoSegurancaCarregado = [];

document.addEventListener('DOMContentLoaded', () => {
    // Verificar se tem permissão (Admin)
    try {
        const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
        if (ud.admin !== true) {
            window.location.href = 'dashboard.html';
            return;
        }
    } catch (e) {
        window.location.href = 'index.html';
    }

    carregarDadosSeguranca();

    const btnRecuar = document.getElementById('btn-historico-recuar');
    if (btnRecuar) {
        btnRecuar.addEventListener('click', () => {
            if (paginaAtualHistorico > 1) {
                paginaAtualHistorico--;
                renderizarHistorico();
            }
        });
    }

    const btnAvancar = document.getElementById('btn-historico-avancar');
    if (btnAvancar) {
        btnAvancar.addEventListener('click', () => {
            const totalPaginas = Math.ceil(historicoSegurancaCarregado.length / TAMANHO_PAGINA_HISTORICO) || 1;
            if (paginaAtualHistorico < totalPaginas) {
                paginaAtualHistorico++;
                renderizarHistorico();
            }
        });
    }
});

function carregarDadosSeguranca() {
    const formatData = (d) => d || '-';

    fetch('api/seguranca.php?acao=resumo')
        .then(r => r.json())
        .then(data => {
            // Alertas
            const tabelaAlertas = document.getElementById('tabela-alertas');
            if (data.alertas && data.alertas.length > 0) {
                let html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                data.alertas.forEach(a => {
                    const icon = a.severity === 'HIGH' ? '🔴' : '🟠';
                    html += `<li style="padding: 12px; border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 4px;">
                        <div style="font-weight: 600; font-size: 0.9rem;">${icon} ${a.message}</div>
                        <div style="font-size: 0.8rem; color: #64748b;">${a.email} (${a.ip_address}) - ${a.created_at}</div>
                    </li>`;
                });
                html += '</ul>';
                tabelaAlertas.innerHTML = html;
            } else {
                tabelaAlertas.innerHTML = '<div style="color: #10b981; font-weight: 500;">Nenhum alerta ativo.</div>';
            }

            // Bloqueios
            const tabelaBloqueios = document.getElementById('tabela-bloqueios');
            if (data.bloqueios && data.bloqueios.length > 0) {
                let html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                data.bloqueios.forEach(b => {
                    html += `<li style="padding: 12px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; font-size: 0.9rem; color: #b45309;">${b.email}</div>
                            <div style="font-size: 0.8rem; color: #64748b;">${b.ip_address} | Expira em ${b.minutos_restantes}m</div>
                        </div>
                        <button onclick="desbloquear('${b.email}', '${b.ip_address}')" style="background: #f59e0b; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Desbloquear</button>
                    </li>`;
                });
                html += '</ul>';
                tabelaBloqueios.innerHTML = html;
            } else {
                tabelaBloqueios.innerHTML = '<div style="color: #64748b;">Nenhum bloqueio ativo.</div>';
            }

            // Sessões
            const tabelaSessoes = document.getElementById('tabela-sessoes');
            if (data.sessoes && data.sessoes.length > 0) {
                let html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                data.sessoes.forEach(s => {
                    html += `<li style="padding: 12px; border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 4px;">
                        <div style="font-weight: 600; font-size: 0.9rem;">${s.nome}</div>
                        <div style="font-size: 0.8rem; color: #64748b;">${s.email} | ${s.ip_address}</div>
                        <div style="font-size: 0.8rem; color: #64748b;">Início: ${s.started_at} | Visto: ${s.last_seen_at}</div>
                    </li>`;
                });
                html += '</ul>';
                tabelaSessoes.innerHTML = html;
            } else {
                tabelaSessoes.innerHTML = '<div style="color: #64748b;">Nenhuma sessão ativa encontrada.</div>';
            }

            // Histórico
            if (data.historico) {
                historicoSegurancaCarregado = data.historico;
            }
            renderizarHistorico();

        })
        .catch(err => {
            console.error(err);
            alert('Erro ao obter dados de segurança.');
        });
}

function renderizarHistorico() {
    const tabelaHistorico = document.getElementById('tabela-historico');
    if (!tabelaHistorico) return;

    const totalPaginas = Math.ceil(historicoSegurancaCarregado.length / TAMANHO_PAGINA_HISTORICO) || 1;
    if (paginaAtualHistorico > totalPaginas) {
        paginaAtualHistorico = totalPaginas;
    }

    const btnRecuar = document.getElementById('btn-historico-recuar');
    const btnAvancar = document.getElementById('btn-historico-avancar');
    const indicador = document.getElementById('historico-indicador');

    if (btnRecuar) btnRecuar.disabled = paginaAtualHistorico === 1;
    if (btnAvancar) btnAvancar.disabled = paginaAtualHistorico === totalPaginas;
    if (indicador) indicador.textContent = `${paginaAtualHistorico} / ${totalPaginas}`;

    if (historicoSegurancaCarregado && historicoSegurancaCarregado.length > 0) {
        let html = '';
        const inicio = (paginaAtualHistorico - 1) * TAMANHO_PAGINA_HISTORICO;
        const fim = inicio + TAMANHO_PAGINA_HISTORICO;
        const historicoPaginado = historicoSegurancaCarregado.slice(inicio, fim);

        historicoPaginado.forEach(h => {
            const status = h.success ? '<span style="color: #10b981; font-weight: 600;">Sucesso</span>' : '<span style="color: #ef4444; font-weight: 600;">Falhou</span>';
            html += `<tr>
                <td data-label="Data">${h.created_at}</td>
                <td data-label="Utilizador">${h.nome}<br><small style="color: #64748b;">${h.email}</small></td>
                <td data-label="IP">${h.ip_address}</td>
                <td data-label="Evento">${h.event_type}</td>
                <td data-label="Status">${status}</td>
                <td data-label="Motivo">${h.reason || '-'}</td>
            </tr>`;
        });
        tabelaHistorico.innerHTML = html;
    } else {
        tabelaHistorico.innerHTML = '<tr><td colspan="6" style="text-align: center;">Sem registos.</td></tr>';
    }
}

function desbloquear(email, ip) {
    if (!confirm(`Deseja remover o bloqueio temporário de ${email} (${ip})?`)) return;

    fetch('api/seguranca.php?acao=desbloquear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, ip_address: ip })
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            alert('Bloqueio removido com sucesso!');
            carregarDadosSeguranca();
        } else {
            alert('Erro: ' + data.erro);
        }
    })
    .catch(err => {
        alert('Erro de comunicação.');
    });
}
