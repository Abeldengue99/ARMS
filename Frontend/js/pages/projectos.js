function initPaginaProjectos() {
    let projectosCarregados = [];
    let termoAtualPesquisa = '';
    let paginaAtualProjectos = 1;
    const TAMANHO_PAGINA_PROJECTOS = 15;

    function texto(valor) {
        return String(valor ?? '');
    }

    function textoBusca(valor) {
        return texto(valor).toLowerCase();
    }

    function escaparHtml(valor) {
        return texto(valor).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function estadoLegivel(projecto) {
        return projecto.status === 'ACTIVE' ? 'Ativo' : 'Inativo';
    }

    function obterResponsavel(projecto) {
        if (projecto.client_name) return projecto.client_name + ' (Cliente)';
        if (projecto.owner_name) return projecto.owner_name + ' (Equipa)';
        return '-';
    }

    function obterProjectosFiltrados() {
        let resultado = [...projectosCarregados];

        if (termoAtualPesquisa) {
            resultado = resultado.filter((projecto) =>
                textoBusca(projecto.name).includes(termoAtualPesquisa) ||
                textoBusca(projecto.description).includes(termoAtualPesquisa) ||
                textoBusca(projecto.client_name).includes(termoAtualPesquisa) ||
                textoBusca(projecto.owner_name).includes(termoAtualPesquisa)
            );
        }

        return resultado;
    }

    function renderizarTabelaProjectos(projectosFiltrados) {
        const corpoTabela = document.getElementById('tabela-corpo-projectos');
        const totalPaginas = Math.ceil(projectosFiltrados.length / TAMANHO_PAGINA_PROJECTOS) || 1;

        if (paginaAtualProjectos > totalPaginas) {
            paginaAtualProjectos = totalPaginas;
        }

        if (!corpoTabela) return;

        corpoTabela.innerHTML = '';

        if (!projectosFiltrados.length) {
            corpoTabela.innerHTML = `<tr><td colspan="5" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">Nenhum projecto encontrado.</td></tr>`;
            return;
        }

        const inicio = (paginaAtualProjectos - 1) * TAMANHO_PAGINA_PROJECTOS;
        const fim = inicio + TAMANHO_PAGINA_PROJECTOS;
        const projectosPaginados = projectosFiltrados.slice(inicio, fim);

        projectosPaginados.forEach((projecto) => {
            const corBadge = projecto.status === 'ACTIVE' ? 'badge-sucesso' : 'badge-perigo';
            const descricaoCurta = texto(projecto.description).length > 60
                ? texto(projecto.description).substring(0, 60) + '...'
                : texto(projecto.description) || '-';

            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td data-label="Projecto" style="padding: 16px; font-weight: 700; color: var(--texto-principal);">${escaparHtml(projecto.name)}</td>
                    <td data-label="Descrição" style="padding: 16px; color: var(--texto-secundario);">${escaparHtml(descricaoCurta)}</td>
                    <td data-label="Responsável" style="padding: 16px;">${escaparHtml(obterResponsavel(projecto))}</td>
                    <td data-label="Estado" style="padding: 16px;">
                        <span class="badge ${corBadge}">${estadoLegivel(projecto)}</span>
                    </td>
                    <td data-label="Ações" style="padding: 16px; text-align: right;">
                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                            <button type="button" onclick="window.abrirEditarProjecto('${escaparHtml(projecto.id)}')" style="color: var(--aksanti-gold); font-weight: 700; font-size: 0.9rem; background: transparent; border: 0; cursor: pointer;">Editar</button>
                            <button type="button" onclick="window.confirmarEliminarProjecto('${escaparHtml(projecto.id)}', '${escaparHtml(projecto.name)}')" style="color: var(--cor-perigo); font-weight: 700; font-size: 0.9rem; background: transparent; border: 0; cursor: pointer;">Eliminar</button>
                        </div>
                    </td>
                </tr>
            `;
            corpoTabela.insertAdjacentHTML('beforeend', linhaHTML);
        });
    }

    function aplicarFiltros() {
        paginaAtualProjectos = 1;
        renderizarTabelaProjectos(obterProjectosFiltrados());
    }

    function carregarProjectosViaApi() {
        const corpoTabela = document.getElementById('tabela-corpo-projectos');
        if (corpoTabela) {
            corpoTabela.innerHTML = '<tr><td colspan="5" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">A carregar projectos...</td></tr>';
        }

        return fetch('api/projectos.php')
            .then((res) => res.json())
            .then((data) => {
                if (!data.sucesso) throw new Error(data.erro || 'Erro ao carregar projectos.');
                projectosCarregados = data.dados || [];
                aplicarFiltros();
            })
            .catch((err) => {
                console.error('Erro ao carregar projectos:', err);
                if (corpoTabela) {
                    corpoTabela.innerHTML = '<tr><td colspan="5" style="padding: 28px 16px; color: var(--cor-perigo); text-align: center;">Erro de ligação ao servidor.</td></tr>';
                }
            });
    }

    function opcoesExportacaoProjectos() {
        return {
            titulo: 'Relatório de Projectos',
            subtitulo: 'Aksanti Request Management System',
            nomeArquivo: 'relatorio-projectos-arms',
            filtros: {
                Pesquisa: termoAtualPesquisa
            },
            colunas: [
                { titulo: 'Nome', valor: (p) => p.name || '-' },
                { titulo: 'Descrição', valor: (p) => p.description || '-' },
                { titulo: 'Cliente / Responsável', valor: (p) => obterResponsavel(p) },
                { titulo: 'Estado', valor: (p) => estadoLegivel(p) }
            ],
            linhas: obterProjectosFiltrados()
        };
    }

    carregarProjectosViaApi();

    const inputFiltro = document.getElementById('filtro-projectos');
    if (inputFiltro) {
        inputFiltro.addEventListener('input', (evento) => {
            termoAtualPesquisa = evento.target.value.trim().toLowerCase();
            aplicarFiltros();
        });
    }

    const btnPdf = document.getElementById('btn-exportar-pdf-projectos');
    if (btnPdf && typeof ArmsExportacoes !== 'undefined') {
        btnPdf.addEventListener('click', () => ArmsExportacoes.baixarPDF(opcoesExportacaoProjectos()));
    }

    const btnExcel = document.getElementById('btn-exportar-excel-projectos');
    if (btnExcel && typeof ArmsExportacoes !== 'undefined') {
        btnExcel.addEventListener('click', () => ArmsExportacoes.baixarExcel(opcoesExportacaoProjectos()));
    }

    // Modal de adicionar projecto
    const btnAddProjecto = document.getElementById('btn-adicionar-projecto');
    if (btnAddProjecto) {
        btnAddProjecto.addEventListener('click', () => {
            const formHTML = `
                <div class="formulario-grid">
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Projecto <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-nome-projecto" class="input-controlo" placeholder="Ex: Auditoria Fiscal 2026">
                    </div>
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Descrição</label>
                        <textarea id="campo-descricao-projecto" class="input-controlo-area" rows="3" placeholder="Breve descrição do projecto..."></textarea>
                    </div>
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Associar a <span style="color: var(--cor-perigo);">*</span></label>
                        <select id="campo-tipo-associacao" class="input-controlo">
                            <option value="CLIENT">Cliente</option>
                            <option value="MEMBER">Membro da Equipa Interna</option>
                        </select>
                    </div>
                    <div id="grupo-cliente-projecto">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Cliente <span style="color: var(--cor-perigo);">*</span></label>
                        <select id="campo-cliente-projecto" class="input-controlo">
                            <option value="">A carregar...</option>
                        </select>
                    </div>
                    <div id="grupo-membro-projecto" style="display:none;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Membro da Equipa <span style="color: var(--cor-perigo);">*</span></label>
                        <select id="campo-membro-projecto" class="input-controlo">
                            <option value="">A carregar...</option>
                        </select>
                    </div>
                </div>
                <div id="modal-feedback-projecto" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
                <div class="formulario-acoes">
                    <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-guardar-projecto">Guardar Projecto</button>
                </div>
            `;
            abrirModal('Novo Projecto', formHTML, { largura: '560px' });

            // Carregar clientes e membros
            fetch('api/formulario-dados.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.sucesso) return;

                    const selCliente = document.getElementById('campo-cliente-projecto');
                    selCliente.innerHTML = '<option value="">Selecionar cliente</option>';
                    (data.clientes || []).forEach(c => {
                        selCliente.innerHTML += `<option value="${c.id}">${escaparHtml(c.name)}</option>`;
                    });

                    const selMembro = document.getElementById('campo-membro-projecto');
                    selMembro.innerHTML = '<option value="">Selecionar membro</option>';
                    (data.membros_aksanti || []).forEach(m => {
                        const cargo = m.cargo ? ' - ' + m.cargo : '';
                        selMembro.innerHTML += `<option value="${m.id}">${escaparHtml(m.full_name)}${escaparHtml(cargo)}</option>`;
                    });
                })
                .catch(() => {
                    const selCliente = document.getElementById('campo-cliente-projecto');
                    if (selCliente) selCliente.innerHTML = '<option value="">Erro ao carregar</option>';
                });

            // Toggle visibilidade
            document.getElementById('campo-tipo-associacao').addEventListener('change', (e) => {
                const isClient = e.target.value === 'CLIENT';
                document.getElementById('grupo-cliente-projecto').style.display = isClient ? '' : 'none';
                document.getElementById('grupo-membro-projecto').style.display = isClient ? 'none' : '';
            });

            // Guardar
            document.getElementById('btn-guardar-projecto').addEventListener('click', () => {
                const feedback = document.getElementById('modal-feedback-projecto');
                const tipoAssociacao = document.getElementById('campo-tipo-associacao').value;
                const dados = {
                    name: document.getElementById('campo-nome-projecto').value.trim(),
                    description: document.getElementById('campo-descricao-projecto').value.trim(),
                    client_id: tipoAssociacao === 'CLIENT' ? document.getElementById('campo-cliente-projecto').value : null,
                    owner_user_id: tipoAssociacao === 'MEMBER' ? document.getElementById('campo-membro-projecto').value : null
                };

                if (!dados.name) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'O nome do projecto é obrigatório.';
                    return;
                }

                if (!dados.client_id && !dados.owner_user_id) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Selecione um cliente ou membro da equipa.';
                    return;
                }

                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
                feedback.style.color = 'var(--aksanti-gold)';
                feedback.textContent = 'A guardar projecto...';

                fetch('api/criar-projecto.php', {
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
                            carregarProjectosViaApi();
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

    // Funções globais para edição e eliminação
    window.abrirEditarProjecto = function(projectoId) {
        const projecto = projectosCarregados.find(p => p.id === projectoId);
        if (!projecto) {
            if (typeof mostrarMensagem === 'function') mostrarMensagem('Atenção', 'Projecto não encontrado.');
            return;
        }

        const tipoAtual = projecto.client_id ? 'CLIENT' : 'MEMBER';

        const formHTML = `
            <div class="formulario-grid">
                <div class="largura-total">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Projecto <span style="color: var(--cor-perigo);">*</span></label>
                    <input type="text" id="edit-nome-projecto" class="input-controlo" value="${escaparHtml(projecto.name)}">
                </div>
                <div class="largura-total">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Descrição</label>
                    <textarea id="edit-descricao-projecto" class="input-controlo-area" rows="3">${escaparHtml(projecto.description || '')}</textarea>
                </div>
                <div class="largura-total">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Associar a <span style="color: var(--cor-perigo);">*</span></label>
                    <select id="edit-tipo-associacao" class="input-controlo">
                        <option value="CLIENT" ${tipoAtual === 'CLIENT' ? 'selected' : ''}>Cliente</option>
                        <option value="MEMBER" ${tipoAtual === 'MEMBER' ? 'selected' : ''}>Membro da Equipa Interna</option>
                    </select>
                </div>
                <div id="edit-grupo-cliente-projecto" style="${tipoAtual === 'CLIENT' ? '' : 'display:none;'}">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Cliente <span style="color: var(--cor-perigo);">*</span></label>
                    <select id="edit-cliente-projecto" class="input-controlo">
                        <option value="">A carregar...</option>
                    </select>
                </div>
                <div id="edit-grupo-membro-projecto" style="${tipoAtual === 'MEMBER' ? '' : 'display:none;'}">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Membro da Equipa <span style="color: var(--cor-perigo);">*</span></label>
                    <select id="edit-membro-projecto" class="input-controlo">
                        <option value="">A carregar...</option>
                    </select>
                </div>
            </div>
            <div id="modal-feedback-edit-projecto" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
            <div class="formulario-acoes">
                <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                <button class="btn btn-primario" id="btn-salvar-edit-projecto">Guardar Alterações</button>
            </div>
        `;
        abrirModal('Editar Projecto', formHTML, { largura: '560px' });

        // Carregar clientes e membros
        fetch('api/formulario-dados.php')
            .then(res => res.json())
            .then(data => {
                if (!data.sucesso) return;

                const selCliente = document.getElementById('edit-cliente-projecto');
                selCliente.innerHTML = '<option value="">Selecionar cliente</option>';
                (data.clientes || []).forEach(c => {
                    const selected = (String(c.id) === String(projecto.client_id)) ? 'selected' : '';
                    selCliente.innerHTML += `<option value="${c.id}" ${selected}>${escaparHtml(c.name)}</option>`;
                });

                const selMembro = document.getElementById('edit-membro-projecto');
                selMembro.innerHTML = '<option value="">Selecionar membro</option>';
                (data.membros_aksanti || []).forEach(m => {
                    const selected = (String(m.id) === String(projecto.owner_user_id)) ? 'selected' : '';
                    const cargo = m.cargo ? ' - ' + m.cargo : '';
                    selMembro.innerHTML += `<option value="${m.id}" ${selected}>${escaparHtml(m.full_name)}${escaparHtml(cargo)}</option>`;
                });
            })
            .catch(() => {});

        // Toggle visibilidade
        document.getElementById('edit-tipo-associacao').addEventListener('change', (e) => {
            const isClient = e.target.value === 'CLIENT';
            document.getElementById('edit-grupo-cliente-projecto').style.display = isClient ? '' : 'none';
            document.getElementById('edit-grupo-membro-projecto').style.display = isClient ? 'none' : '';
        });

        // Guardar edição
        document.getElementById('btn-salvar-edit-projecto').addEventListener('click', () => {
            const feedback = document.getElementById('modal-feedback-edit-projecto');
            const tipoAssociacao = document.getElementById('edit-tipo-associacao').value;
            const dados = {
                id: projectoId,
                name: document.getElementById('edit-nome-projecto').value.trim(),
                description: document.getElementById('edit-descricao-projecto').value.trim(),
                client_id: tipoAssociacao === 'CLIENT' ? document.getElementById('edit-cliente-projecto').value : null,
                owner_user_id: tipoAssociacao === 'MEMBER' ? document.getElementById('edit-membro-projecto').value : null
            };

            if (!dados.name) {
                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                feedback.style.color = '#ef4444';
                feedback.textContent = 'O nome do projecto é obrigatório.';
                return;
            }

            if (!dados.client_id && !dados.owner_user_id) {
                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                feedback.style.color = '#ef4444';
                feedback.textContent = 'Selecione um cliente ou membro da equipa.';
                return;
            }

            feedback.style.display = 'block';
            feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
            feedback.style.color = 'var(--aksanti-gold)';
            feedback.textContent = 'A guardar alterações...';

            fetch('api/editar-projecto.php', {
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
                        carregarProjectosViaApi();
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

    window.confirmarEliminarProjecto = function(projectoId, projectoNome) {
        if (typeof confirmarAcao === 'function') {
            confirmarAcao('Eliminar Projecto', `Tem a certeza de que deseja desativar o projecto "${projectoNome}"? Os pedidos associados não serão afetados.`, () => {
                fetch('api/eliminar-projecto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: projectoId })
                })
                .then(res => res.json())
                .then(resultado => {
                    if (resultado.sucesso) {
                        if (typeof mostrarMensagem === 'function') {
                            mostrarMensagem('Sucesso', resultado.mensagem);
                        }
                        carregarProjectosViaApi();
                    } else {
                        if (typeof mostrarMensagem === 'function') {
                            mostrarMensagem('Erro', resultado.erro || 'Erro ao eliminar projecto.');
                        }
                    }
                })
                .catch(() => {
                    if (typeof mostrarMensagem === 'function') {
                        mostrarMensagem('Erro', 'Erro de ligação ao servidor.');
                    }
                });
            });
        }
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPaginaProjectos);
} else {
    initPaginaProjectos();
}
