/**
 * ARMS — Lógica Dedicada dos Departamentos / Áreas (areas.html)
 */
let areasCarregadas = [];

function exportarAreas(tipoExportacao) {
    const opcoes = {
        titulo: 'Relatório de Departamentos',
        subtitulo: 'Aksanti Request Management System',
        nomeArquivo: 'relatorio-departamentos-arms',
        filtros: {},
        colunas: [
            { titulo: 'Código', valor: (area) => area.code || '-' },
            { titulo: 'Departamento', valor: (area) => area.name || '-' },
            { titulo: 'Pedidos Associados', valor: (area) => area.total_pedidos ?? 0 }
        ],
        linhas: areasCarregadas
    };

    if (tipoExportacao === 'excel') {
        ArmsExportacoes.baixarExcel(opcoes);
        return;
    }

    ArmsExportacoes.baixarPDF(opcoes);
}

document.addEventListener('DOMContentLoaded', () => {
    const grelha = document.getElementById('grelha-areas');
    if (!grelha) return;

    grelha.innerHTML = `
        <div class="card" style="padding: 20px;"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-text"></div></div>
        <div class="card" style="padding: 20px;"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-text"></div></div>
        <div class="card" style="padding: 20px;"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-text"></div></div>
    `;

    // Buscar Áreas reais do PostgreSQL
    fetch('api/areas.php?v=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
            if (!data.sucesso) {
                grelha.innerHTML = '<p style="color:var(--cor-perigo);">Erro da BD: ' + data.erro + '</p>';
                return;
            }
            areasCarregadas = data.dados || [];

            if (!areasCarregadas.length) {
                grelha.innerHTML = '<p style="color:var(--texto-secundario);">Nenhum departamento registado.</p>';
                return;
            }

            grelha.innerHTML = '';
            const iconeEditar = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`;
            const iconeEliminar = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>`;

            areasCarregadas.forEach((area, indice) => {
                const totalPedidos = area.total_pedidos;

                const cartaoHTML = `
                    <div class="card deslizar-cima-isaf" style="animation-delay: ${indice * 0.08}s;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span class="badge" style="background-color: rgba(229, 138, 19, 0.1); color: var(--aksanti-gold); font-weight: 600; font-size: 0.9rem;">${area.code}</span>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" onclick="window.abrirEditarArea('${area.id}', '${area.name.replace(/'/g, "\\'")}', '${area.code}')" title="Editar" style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; background:rgba(229,138,19,0.1); color:var(--aksanti-gold); border:none; cursor:pointer; transition:background 0.2s; padding:0;">${iconeEditar}</button>
                                <button type="button" onclick="window.confirmarEliminarArea('${area.id}', '${area.name.replace(/'/g, "\\'")}')" title="Eliminar" style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; background:transparent; color:var(--texto-secundario); border:1px solid var(--borda-suave); cursor:pointer; transition:all 0.2s; padding:0;" onmouseover="this.style.background='rgba(239,68,68,0.1)'; this.style.color='#ef4444'; this.style.borderColor='transparent';" onmouseout="this.style.background='transparent'; this.style.color='var(--texto-secundario)'; this.style.borderColor='var(--borda-suave)';">${iconeEliminar}</button>
                            </div>
                        </div>
                        <h3 style="font-size: 1.15rem; margin-bottom: 8px;">${area.name}</h3>
                        <p style="color: var(--texto-secundario); font-size: 0.9rem;">${totalPedidos} pedido${totalPedidos != 1 ? 's' : ''} associado${totalPedidos != 1 ? 's' : ''}</p>
                    </div>
                `;
                grelha.insertAdjacentHTML('beforeend', cartaoHTML);
            });
        })
        .catch(err => {
            console.error('Erro no fetch:', err);
            grelha.innerHTML = '<p style="color:var(--cor-perigo);">Erro de ligação ao servidor.</p>';
        });

    // Ligar o modal de adicionar departamento
    const btnAddArea = document.getElementById('btn-adicionar-area');
    if (btnAddArea) {
        btnAddArea.addEventListener('click', () => {
            const formHTML = `
                <div class="formulario-grid">
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Departamento <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-nome-area" class="input-controlo" placeholder="Ex: Contabilidade">
                    </div>
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Código único (Sigla) <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-codigo-area" class="input-controlo" placeholder="Ex: CONTAB (Apenas letras maiúsculas)" style="text-transform: uppercase;">
                    </div>
                    <div class="largura-total" style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                        <input type="checkbox" id="campo-restrito-area" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="campo-restrito-area" style="cursor: pointer; color: var(--texto-principal);">Departamento Restrito (Ex: Recursos Humanos)</label>
                    </div>
                </div>
                <div id="modal-feedback-area" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
                <div class="formulario-acoes">
                    <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-guardar-area">Criar Departamento</button>
                </div>
            `;
            abrirModal('Adicionar Novo Departamento', formHTML, { largura: '480px' });

            document.getElementById('btn-guardar-area').addEventListener('click', () => {
                const feedback = document.getElementById('modal-feedback-area');
                const dados = {
                    nome:     document.getElementById('campo-nome-area').value.trim(),
                    codigo:   document.getElementById('campo-codigo-area').value.trim(),
                    restrito: document.getElementById('campo-restrito-area').checked
                };

                if (!dados.nome || !dados.codigo) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Nome e Código são obrigatórios.';
                    return;
                }

                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
                feedback.style.color = 'var(--aksanti-gold)';
                feedback.textContent = 'A criar departamento...';

                fetch('api/criar-area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                })
                .then(res => res.json())
                .then(resultado => {
                    if (resultado.sucesso) {
                        feedback.style.backgroundColor = 'rgba(34,197,94,0.1)';
                        feedback.style.color = '#22c55e';
                        feedback.textContent = resultado.mensagem;
                        setTimeout(() => {
                            fecharModal();
                            location.reload();
                        }, 1500);
                    } else {
                        feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                        feedback.style.color = '#ef4444';
                        feedback.textContent = 'Erro: ' + resultado.erro;
                    }
                })
                .catch(() => {
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Erro de ligação ao servidor.';
                });
            });
        });
    }

    const btnPdfAreas = document.getElementById('btn-exportar-pdf-areas');
    if (btnPdfAreas) {
        btnPdfAreas.addEventListener('click', () => exportarAreas('pdf'));
    }

    const btnExcelAreas = document.getElementById('btn-exportar-excel-areas');
    if (btnExcelAreas) {
        btnExcelAreas.addEventListener('click', () => exportarAreas('excel'));
    }
});

// Editar departamento
window.abrirEditarArea = function(id, nome, codigo) {
    const formHTML = `
        <div class="formulario-grid">
            <div class="largura-total">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Departamento <span style="color: var(--cor-perigo);">*</span></label>
                <input type="text" id="editar-nome-area" class="input-controlo" value="${nome}">
            </div>
            <div class="largura-total">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Código (Sigla)</label>
                <input type="text" id="editar-codigo-area" class="input-controlo" value="${codigo}" disabled style="opacity: 0.6; cursor: not-allowed;">
                <span style="display: block; margin-top: 4px; font-size: 0.8rem; color: var(--texto-secundario);">O código não pode ser alterado depois de criado.</span>
            </div>
        </div>
        <div id="modal-feedback-editar-area" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
        <div class="formulario-acoes">
            <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-primario" id="btn-guardar-editar-area">Guardar Alterações</button>
        </div>
    `;
    abrirModal('Editar Departamento', formHTML, { largura: '480px' });

    document.getElementById('btn-guardar-editar-area').addEventListener('click', () => {
        const feedback = document.getElementById('modal-feedback-editar-area');
        const novoNome = document.getElementById('editar-nome-area').value.trim();

        if (!novoNome) {
            feedback.style.display = 'block';
            feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
            feedback.style.color = '#ef4444';
            feedback.textContent = 'O nome é obrigatório.';
            return;
        }

        feedback.style.display = 'block';
        feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
        feedback.style.color = 'var(--aksanti-gold)';
        feedback.textContent = 'A guardar...';

        fetch('api/editar-area.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nome: novoNome })
        })
        .then(res => res.json())
        .then(resultado => {
            if (resultado.sucesso) {
                feedback.style.backgroundColor = 'rgba(34,197,94,0.1)';
                feedback.style.color = '#22c55e';
                feedback.textContent = resultado.mensagem || 'Departamento atualizado!';
                setTimeout(() => {
                    fecharModal();
                    location.reload();
                }, 1500);
            } else {
                feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                feedback.style.color = '#ef4444';
                feedback.textContent = 'Erro: ' + resultado.erro;
            }
        })
        .catch(() => {
            feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
            feedback.style.color = '#ef4444';
            feedback.textContent = 'Erro de ligação ao servidor.';
        });
    });
};

// Confirmar e eliminar departamento
window.confirmarEliminarArea = function(id, nome) {
    confirmarAcao(
        'Eliminar Departamento',
        `Tem a certeza de que deseja eliminar <strong>${nome || 'este departamento'}</strong>? Se tiver pedidos associados, a eliminação será bloqueada automaticamente.`,
        () => eliminarArea(id)
    );
};

window.eliminarArea = function(id) {
    fetch('api/eliminar-area.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            mostrarMensagem('Sucesso', data.mensagem);
            fecharModal();
            location.reload();
        } else {
            mostrarMensagem('Atenção', data.erro || 'Não foi possível eliminar o departamento.');
        }
    })
    .catch(() => {
        mostrarMensagem('Erro', 'Erro de ligação ao servidor.');
    });
};
