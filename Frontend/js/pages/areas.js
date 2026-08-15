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
            areasCarregadas.forEach((area, indice) => {
                const totalPedidos = area.total_pedidos;

                const cartaoHTML = `
                    <div class="card deslizar-cima-isaf" style="animation-delay: ${indice * 0.08}s;">
                        <div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: 16px;">
                            <span class="badge" style="background-color: rgba(229, 138, 19, 0.1); color: var(--aksanti-gold); font-weight: 600; font-size: 0.9rem;">${area.code}</span>
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
