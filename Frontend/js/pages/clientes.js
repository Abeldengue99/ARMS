function initPaginaClientes() {
    let clientesCarregados = [];
    let termoAtualPesquisa = '';
    let paginaAtualClientes = 1;
    const TAMANHO_PAGINA_CLIENTES = 15;

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

    function estadoLegivel(cliente) {
        return cliente.status === 'ACTIVE' ? 'Ativo' : 'Inativo';
    }

    function obterClientesFiltrados() {
        let resultado = [...clientesCarregados];

        if (termoAtualPesquisa) {
            resultado = resultado.filter((cliente) =>
                textoBusca(cliente.company_name).includes(termoAtualPesquisa) ||
                textoBusca(cliente.tax_id).includes(termoAtualPesquisa) ||
                textoBusca(cliente.location).includes(termoAtualPesquisa) ||
                textoBusca(cliente.contact_name).includes(termoAtualPesquisa) ||
                textoBusca(cliente.contact_email).includes(termoAtualPesquisa)
            );
        }

        return resultado;
    }

    function renderizarTabelaClientes(clientesFiltrados) {
        const corpoTabela = document.getElementById('tabela-corpo-clientes');
        const totalPaginas = Math.ceil(clientesFiltrados.length / TAMANHO_PAGINA_CLIENTES) || 1;
        
        if (paginaAtualClientes > totalPaginas) {
            paginaAtualClientes = totalPaginas;
        }

        const btnRecuar = document.getElementById('btn-clientes-recuar');
        const btnAvancar = document.getElementById('btn-clientes-avancar');
        const indicador = document.getElementById('clientes-indicador');
        
        if (btnRecuar) btnRecuar.disabled = paginaAtualClientes === 1;
        if (btnAvancar) btnAvancar.disabled = paginaAtualClientes === totalPaginas;
        if (indicador) indicador.textContent = `${paginaAtualClientes} / ${totalPaginas}`;

        if (!corpoTabela) return;

        corpoTabela.innerHTML = '';

        if (!clientesFiltrados.length) {
            const msgSemClientes = (typeof window.t === 'function') ? window.t('clientes.sem_clientes', 'Nenhum cliente encontrado.') : 'Nenhum cliente encontrado.';
            corpoTabela.innerHTML = `<tr><td colspan="6" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">${msgSemClientes}</td></tr>`;
            return;
        }

        const inicio = (paginaAtualClientes - 1) * TAMANHO_PAGINA_CLIENTES;
        const fim = inicio + TAMANHO_PAGINA_CLIENTES;
        const clientesPaginados = clientesFiltrados.slice(inicio, fim);

        clientesPaginados.forEach((cliente) => {
            const corBadge = cliente.status === 'ACTIVE' ? 'badge-sucesso' : 'badge-perigo';
            const labelEmpresa = (typeof window.t === 'function') ? window.t('clientes.nome_empresa', 'Empresa') : 'Empresa';
            const labelNif = (typeof window.t === 'function') ? window.t('clientes.nif', 'NIF') : 'NIF';
            const labelMorada = (typeof window.t === 'function') ? window.t('clientes.morada', 'Localização') : 'Localização';
            const labelContacto = (typeof window.t === 'function') ? window.t('clientes.contacto_principal', 'Contacto') : 'Contacto';
            const labelEstado = (typeof window.t === 'function') ? window.t('tabela.status', 'Estado') : 'Estado';
            const labelAcoes = (typeof window.t === 'function') ? window.t('tabela.acoes', 'Ações') : 'Ações';
            const labelEditar = (typeof window.t === 'function') ? window.t('clientes.editar_cliente', 'Editar Conta') : 'Editar Conta';

            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td data-label="${labelEmpresa}" style="padding: 16px; font-weight: 700; color: var(--texto-principal);">${escaparHtml(cliente.company_name)}</td>
                    <td data-label="${labelNif}" style="padding: 16px;">${escaparHtml(cliente.tax_id || '-')}</td>
                    <td data-label="${labelMorada}" style="padding: 16px; color: var(--texto-secundario);">${escaparHtml(cliente.location || '-')}</td>
                    <td data-label="${labelContacto}" style="padding: 16px;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600;">${escaparHtml(cliente.contact_name || '-')}</span>
                            <span style="font-size: 0.85rem; color: var(--texto-secundario);">${escaparHtml(cliente.contact_email || '-')}</span>
                        </div>
                    </td>
                    <td data-label="${labelEstado}" style="padding: 16px;">
                        <span class="badge ${corBadge}">${estadoLegivel(cliente)}</span>
                    </td>
                    <td data-label="${labelAcoes}" style="padding: 16px; text-align: right;">
                        <button type="button" onclick="window.abrirEditarCliente('${escaparHtml(cliente.id)}')" style="color: var(--aksanti-gold); font-weight: 700; font-size: 0.9rem; text-decoration: none; background: transparent; border: 0; cursor: pointer;">${labelEditar}</button>
                    </td>
                </tr>
            `;
            corpoTabela.insertAdjacentHTML('beforeend', linhaHTML);
        });
    }

    function aplicarFiltros() {
        paginaAtualClientes = 1;
        renderizarTabelaClientes(obterClientesFiltrados());
    }

    function carregarClientesViaApi() {
        const corpoTabela = document.getElementById('tabela-corpo-clientes');
        if (corpoTabela && (!corpoTabela.children.length || corpoTabela.querySelector('td')?.textContent?.includes('mock'))) {
            corpoTabela.innerHTML = '<tr><td colspan="6" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">A carregar clientes...</td></tr>';
        }

        return fetch('api/clientes.php')
            .then((res) => res.json())
            .then((data) => {
                if (!data.sucesso) throw new Error(data.erro || 'Erro ao carregar clientes.');
                clientesCarregados = data.dados || [];
                aplicarFiltros();
            })
            .catch((err) => {
                console.error('Erro ao carregar clientes:', err);
                if (corpoTabela) {
                    corpoTabela.innerHTML = '<tr><td colspan="6" style="padding: 28px 16px; color: var(--cor-perigo); text-align: center;">Erro de ligação ao servidor.</td></tr>';
                }
            });
    }

    function opcoesExportacaoClientes() {
        return {
            titulo: 'Relatório de Clientes',
            subtitulo: 'Aksanti Request Management System',
            nomeArquivo: 'relatorio-clientes-arms',
            filtros: {
                Pesquisa: termoAtualPesquisa
            },
            colunas: [
                { titulo: 'Empresa', valor: (c) => c.company_name || '-' },
                { titulo: 'NIF', valor: (c) => c.tax_id || '-' },
                { titulo: 'Localização', valor: (c) => c.location || '-' },
                { titulo: 'Pessoa de Contacto', valor: (c) => c.contact_name || '-' },
                { titulo: 'E-mail', valor: (c) => c.contact_email || '-' },
                { titulo: 'Estado', valor: (c) => estadoLegivel(c) }
            ],
            linhas: obterClientesFiltrados()
        };
    }

    if (typeof ArmsTempoReal !== 'undefined') {
        ArmsTempoReal.iniciar('clientes', (data) => {
            if (data.clientes) {
                clientesCarregados = data.clientes;
                aplicarFiltros();
            }
        });
    }

    carregarClientesViaApi();

    const inputFiltro = document.getElementById('filtro-clientes');
    if (inputFiltro) {
        inputFiltro.addEventListener('input', (evento) => {
            termoAtualPesquisa = evento.target.value.trim().toLowerCase();
            aplicarFiltros();
        });
    }

    const btnPdf = document.getElementById('btn-exportar-pdf-clientes');
    if (btnPdf && typeof ArmsExportacoes !== 'undefined') {
        btnPdf.addEventListener('click', () => ArmsExportacoes.baixarPDF(opcoesExportacaoClientes()));
    }

    const btnExcel = document.getElementById('btn-exportar-excel-clientes');
    if (btnExcel && typeof ArmsExportacoes !== 'undefined') {
        btnExcel.addEventListener('click', () => ArmsExportacoes.baixarExcel(opcoesExportacaoClientes()));
    }

    const btnRecuar = document.getElementById('btn-clientes-recuar');
    if (btnRecuar) {
        btnRecuar.addEventListener('click', () => {
            if (paginaAtualClientes > 1) {
                paginaAtualClientes--;
                renderizarTabelaClientes(obterClientesFiltrados());
            }
        });
    }

    const btnAvancar = document.getElementById('btn-clientes-avancar');
    if (btnAvancar) {
        btnAvancar.addEventListener('click', () => {
            const clientesFiltrados = obterClientesFiltrados();
            const totalPaginas = Math.ceil(clientesFiltrados.length / TAMANHO_PAGINA_CLIENTES) || 1;
            if (paginaAtualClientes < totalPaginas) {
                paginaAtualClientes++;
                renderizarTabelaClientes(clientesFiltrados);
            }
        });
    }

    // Modal de adicionar cliente
    const btnAddCliente = document.getElementById('btn-adicionar-cliente');
    if (btnAddCliente) {
        btnAddCliente.addEventListener('click', () => {
            const formHTML = `
                <div class="formulario-grid">
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome da Empresa <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-nome-empresa" class="input-controlo" placeholder="Ex: Empresa XYZ, Lda.">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email Principal <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="email" id="campo-email-empresa" class="input-controlo" placeholder="geral@empresa.co.ao">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">NIF <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-nif-empresa" class="input-controlo" placeholder="Número de Identificação Fiscal">
                    </div>
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Localização <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="campo-localizacao-empresa" class="input-controlo" placeholder="Ex: Luanda, Angola">
                    </div>
                    
                    <div class="largura-total" style="margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e4e4e7; padding-bottom: 8px;">
                            <h4 style="font-size: 1rem; color: var(--texto-principal);">Representantes <span style="color: var(--cor-perigo); font-size: 0.8rem; font-weight: normal;">(Pelo menos 1 obrigatório)</span></h4>
                            <button type="button" id="btn-add-representante" class="btn btn-secundario" style="padding: 4px 12px; font-size: 0.85rem;">+ Adicionar</button>
                        </div>
                        
                        <div id="lista-representantes">
                            <div class="representante-item" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0; position: relative;">
                                <div class="formulario-grid">
                                    <div class="largura-total">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Representante <span style="color: var(--cor-perigo);">*</span></label>
                                        <input type="text" class="input-controlo rep-nome" placeholder="Nome do representante">
                                    </div>
                                    <div>
                                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Contacto <span style="color: var(--cor-perigo);">*</span></label>
                                        <input type="email" class="input-controlo rep-email" placeholder="email@exemplo.com">
                                    </div>
                                    <div>
                                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Contacto Telefónico <span style="color: var(--cor-perigo);">*</span></label>
                                        <input type="text" class="input-controlo rep-telefone" placeholder="+244 900 000 000">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="modal-feedback-cliente" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
                <div class="formulario-acoes">
                    <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-guardar-cliente">Guardar Cliente</button>
                </div>
            `;
            abrirModal('Adicionar Novo Cliente', formHTML, { largura: '600px' });

            document.getElementById('btn-add-representante').addEventListener('click', () => {
                const lista = document.getElementById('lista-representantes');
                const div = document.createElement('div');
                div.className = 'representante-item';
                div.style.cssText = 'background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0; position: relative;';
                div.innerHTML = `
                    <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; color: var(--cor-perigo); cursor: pointer; padding: 4px;">&times; Remover</button>
                    <div class="formulario-grid" style="margin-top: 16px;">
                        <div class="largura-total">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Representante <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="text" class="input-controlo rep-nome" placeholder="Nome do representante secundário">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Contacto <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="email" class="input-controlo rep-email" placeholder="email@exemplo.com">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Contacto Telefónico <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="text" class="input-controlo rep-telefone" placeholder="+244 900 000 000">
                        </div>
                    </div>
                `;
                lista.appendChild(div);
            });

            document.getElementById('btn-guardar-cliente').addEventListener('click', () => {
                const feedback = document.getElementById('modal-feedback-cliente');
                const representantes = [];
                document.querySelectorAll('#lista-representantes .representante-item').forEach(item => {
                    const n = item.querySelector('.rep-nome').value.trim();
                    const e = item.querySelector('.rep-email').value.trim();
                    const t = item.querySelector('.rep-telefone').value.trim();
                    if (n || e || t) {
                        representantes.push({ nome: n, email: e, telefone: t });
                    }
                });

                const dados = {
                    nome:          document.getElementById('campo-nome-empresa').value.trim(),
                    email:         document.getElementById('campo-email-empresa').value.trim(),
                    nif:           document.getElementById('campo-nif-empresa').value.trim(),
                    localizacao:   document.getElementById('campo-localizacao-empresa').value.trim(),
                    representantes: representantes
                };

                if (!dados.nome || !dados.email || !dados.nif || !dados.localizacao) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Por favor, preencha todos os dados da empresa (Nome, Email, NIF, Localização).';
                    return;
                }

                if (representantes.length === 0 || !representantes[0].nome || !representantes[0].email || !representantes[0].telefone) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Tem de preencher todos os dados de pelo menos um Representante (Nome, Email e Contacto).';
                    return;
                }

                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
                feedback.style.color = 'var(--aksanti-gold)';
                feedback.textContent = 'A guardar cliente...';

                fetch('api/criar-cliente.php', {
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
                            if (typeof ArmsTempoReal !== 'undefined') {
                                ArmsTempoReal.forcarAtualizacao();
                            }
                            carregarClientesViaApi();
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPaginaClientes);
} else {
    initPaginaClientes();
}

// Modal de editar cliente no escopo global
window.abrirEditarCliente = function(clienteId) {
    fetch('api/clientes.php')
        .then(res => res.json())
        .then(data => {
            if (!data.sucesso) return;
            const cliente = data.dados.find(c => c.id === clienteId);
            if (!cliente) {
                if (typeof mostrarMensagem === 'function') mostrarMensagem('Atenção', 'Cliente não encontrado.');
                else alert('Cliente não encontrado.');
                return;
            }

            const nifVal = cliente.tax_id === '?' ? '' : cliente.tax_id;
            const locVal = cliente.location === '?' ? '' : cliente.location;
            const isAtivo = cliente.status === 'ACTIVE';
            
            let repHTML = '';
            if (cliente.representantes && cliente.representantes.length > 0) {
                cliente.representantes.forEach((r, idx) => {
                    const isFirst = (idx === 0);
                    repHTML += `
                        <div class="representante-item" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0; position: relative;">
                            ${!isFirst ? '<button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; color: var(--cor-perigo); cursor: pointer; padding: 4px;">&times; Remover</button>' : ''}
                            <div class="formulario-grid" style="${!isFirst ? 'margin-top: 16px;' : ''}">
                                <div class="largura-total">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Representante ${isFirst ? '<span style="color: var(--cor-perigo);">*</span>' : ''}</label>
                                    <input type="text" class="input-controlo rep-nome" placeholder="Nome do representante" value="${r.nome || ''}">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Contacto ${isFirst ? '<span style="color: var(--cor-perigo);">*</span>' : ''}</label>
                                    <input type="email" class="input-controlo rep-email" placeholder="email@exemplo.com" value="${r.email && !r.email.startsWith('no-reply') ? r.email : ''}">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Contacto Telefónico ${isFirst ? '<span style="color: var(--cor-perigo);">*</span>' : ''}</label>
                                    <input type="text" class="input-controlo rep-telefone" placeholder="+244 900 000 000" value="${r.telefone || ''}">
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                repHTML = `
                    <div class="representante-item" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0; position: relative;">
                        <div class="formulario-grid">
                            <div class="largura-total">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Representante <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="text" class="input-controlo rep-nome" placeholder="Nome do representante">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Contacto <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="email" class="input-controlo rep-email" placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Contacto Telefónico <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="text" class="input-controlo rep-telefone" placeholder="+244 900 000 000">
                            </div>
                        </div>
                    </div>
                `;
            }

            const formHTML = `
                <div class="formulario-grid">
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome da Empresa <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="editar-nome" class="input-controlo" value="${cliente.company_name}">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email Principal <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="email" id="editar-email" class="input-controlo" value="${cliente.contact_email}">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">NIF <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="editar-nif" class="input-controlo" value="${nifVal}" placeholder="Número de Identificação Fiscal">
                    </div>
                    <div class="largura-total">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Localização <span style="color: var(--cor-perigo);">*</span></label>
                        <input type="text" id="editar-localizacao" class="input-controlo" value="${locVal}" placeholder="Ex: Luanda, Angola">
                    </div>
                    
                    <div class="largura-total" style="margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e4e4e7; padding-bottom: 8px;">
                            <h4 style="font-size: 1rem; color: var(--texto-principal);">Representantes <span style="color: var(--cor-perigo); font-size: 0.8rem; font-weight: normal;">(Pelo menos 1 obrigatório)</span></h4>
                            <button type="button" id="btn-add-representante-edit" class="btn btn-secundario" style="padding: 4px 12px; font-size: 0.85rem;">+ Adicionar</button>
                        </div>
                        <div id="lista-representantes-edit">
                            ${repHTML}
                        </div>
                    </div>
                    
                    <div class="largura-total" style="margin-top: 8px; padding-top: 16px; border-top: 1px dashed #e4e4e7;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Estado da Empresa</label>
                        <select id="editar-estado" class="input-controlo" style="width: auto;">
                            <option value="true" ${isAtivo ? 'selected' : ''}>Ativo</option>
                            <option value="false" ${!isAtivo ? 'selected' : ''}>Inativo (Desativado)</option>
                        </select>
                    </div>
                </div>
                <div id="modal-feedback-editar" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
                <div class="formulario-acoes">
                    <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-salvar-edicao">Guardar Alterações</button>
                </div>
            `;
            abrirModal('Editar Cliente', formHTML, { largura: '600px' });

            document.getElementById('btn-add-representante-edit').addEventListener('click', () => {
                const lista = document.getElementById('lista-representantes-edit');
                const div = document.createElement('div');
                div.className = 'representante-item';
                div.style.cssText = 'background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0; position: relative;';
                div.innerHTML = `
                    <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; color: var(--cor-perigo); cursor: pointer; padding: 4px;">&times; Remover</button>
                    <div class="formulario-grid" style="margin-top: 16px;">
                        <div class="largura-total">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Nome do Representante <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="text" class="input-controlo rep-nome" placeholder="Nome do representante secundário">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Contacto <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="email" class="input-controlo rep-email" placeholder="email@exemplo.com">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Contacto Telefónico <span style="color: var(--cor-perigo);">*</span></label>
                            <input type="text" class="input-controlo rep-telefone" placeholder="+244 900 000 000">
                        </div>
                    </div>
                `;
                lista.appendChild(div);
            });

            document.getElementById('btn-salvar-edicao').addEventListener('click', () => {
                const feedback = document.getElementById('modal-feedback-editar');
                const representantes = [];
                document.querySelectorAll('#lista-representantes-edit .representante-item').forEach(item => {
                    const n = item.querySelector('.rep-nome').value.trim();
                    const e = item.querySelector('.rep-email').value.trim();
                    const t = item.querySelector('.rep-telefone').value.trim();
                    if (n || e || t) {
                        representantes.push({ nome: n, email: e, telefone: t });
                    }
                });

                const dados = {
                    id:            clienteId,
                    nome:          document.getElementById('editar-nome').value.trim(),
                    email:         document.getElementById('editar-email').value.trim(),
                    nif:           document.getElementById('editar-nif').value.trim(),
                    localizacao:   document.getElementById('editar-localizacao').value.trim(),
                    representantes: representantes,
                    ativo:         document.getElementById('editar-estado').value === 'true'
                };

                if (!dados.nome || !dados.email || !dados.nif || !dados.localizacao) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Por favor, preencha todos os dados da empresa (Nome, Email, NIF, Localização).';
                    return;
                }

                if (representantes.length === 0 || !representantes[0].nome || !representantes[0].email || !representantes[0].telefone) {
                    feedback.style.display = 'block';
                    feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                    feedback.style.color = '#ef4444';
                    feedback.textContent = 'Tem de preencher todos os dados de pelo menos um Representante (Nome, Email e Contacto).';
                    return;
                }

                feedback.style.display = 'block';
                feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
                feedback.style.color = 'var(--aksanti-gold)';
                feedback.textContent = 'A guardar alterações...';

                fetch('api/editar-cliente.php', {
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
                            if (typeof ArmsTempoReal !== 'undefined') {
                                ArmsTempoReal.forcarAtualizacao();
                            }
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
};
