document.addEventListener('DOMContentLoaded', () => {
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
            corpoTabela.innerHTML = `<tr><td colspan="6" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">${window.t('clientes.sem_clientes', 'Nenhum cliente encontrado.')}</td></tr>`;
            return;
        }

        const inicio = (paginaAtualClientes - 1) * TAMANHO_PAGINA_CLIENTES;
        const fim = inicio + TAMANHO_PAGINA_CLIENTES;
        const clientesPaginados = clientesFiltrados.slice(inicio, fim);

        clientesPaginados.forEach((cliente) => {
            const iconeEditar = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`;
            const corBadge = cliente.status === 'ACTIVE' ? 'badge-sucesso' : 'badge-perigo';
            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td data-label="${window.t('clientes.nome_empresa', 'Empresa')}" style="padding: 16px; font-weight: 700; color: var(--texto-principal);">${escaparHtml(cliente.company_name)}</td>
                    <td data-label="${window.t('clientes.nif', 'NIF')}" style="padding: 16px;">${escaparHtml(cliente.tax_id || '-')}</td>
                    <td data-label="${window.t('clientes.morada', 'Localização')}" style="padding: 16px; color: var(--texto-secundario);">${escaparHtml(cliente.location || '-')}</td>
                    <td data-label="${window.t('clientes.contacto_principal', 'Contacto')}" style="padding: 16px;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600;">${escaparHtml(cliente.contact_name || '-')}</span>
                            <span style="font-size: 0.85rem; color: var(--texto-secundario);">${escaparHtml(cliente.contact_email || '-')}</span>
                        </div>
                    </td>
                    <td data-label="${window.t('tabela.status', 'Estado')}" style="padding: 16px;">
                        <span class="badge ${corBadge}">${estadoLegivel(cliente)}</span>
                    </td>
                    <td data-label="${window.t('tabela.acoes', 'Ações')}" style="padding: 16px; text-align: right;">
                        <div style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: nowrap;">
                            <button type="button" onclick="abrirEditarCliente('${escaparHtml(cliente.id)}')" title="${window.t('clientes.editar_cliente', 'Editar Conta')}" style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:6px; background:rgba(229,138,19,0.1); color:var(--aksanti-gold); border:none; cursor:pointer; transition:background 0.2s; padding:0;">${iconeEditar}</button>
                        </div>
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
        return fetch('api/clientes.php')
            .then((res) => res.json())
            .then((data) => {
                if (!data.sucesso) throw new Error(data.erro || 'Erro ao carregar clientes.');
                clientesCarregados = data.dados || [];
                aplicarFiltros();
            })
            .catch((err) => {
                console.error('Erro ao carregar clientes:', err);
                const corpoTabela = document.getElementById('tabela-corpo-clientes');
                if (corpoTabela) {
                    corpoTabela.innerHTML = '<tr><td colspan="6" style="padding: 28px 16px; color: var(--cor-perigo); text-align: center;">Erro ao carregar clientes.</td></tr>';
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
    } else {
        carregarClientesViaApi();
    }

    const inputFiltro = document.getElementById('filtro-clientes');
    if (inputFiltro) {
        inputFiltro.addEventListener('input', (evento) => {
            termoAtualPesquisa = evento.target.value.trim().toLowerCase();
            aplicarFiltros();
        });
    }

    const btnPdf = document.getElementById('btn-exportar-pdf-clientes');
    if (btnPdf) {
        btnPdf.addEventListener('click', () => ArmsExportacoes.baixarPDF(opcoesExportacaoClientes()));
    }

    const btnExcel = document.getElementById('btn-exportar-excel-clientes');
    if (btnExcel) {
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
});
