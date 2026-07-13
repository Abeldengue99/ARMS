document.addEventListener('DOMContentLoaded', () => {
    let clientesCarregados = [];
    let termoAtualPesquisa = '';

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
        if (!corpoTabela) return;

        corpoTabela.innerHTML = '';

        if (!clientesFiltrados.length) {
            corpoTabela.innerHTML = '<tr><td colspan="6" style="padding: 28px 16px; color: var(--texto-secundario); text-align: center;">Nenhum cliente encontrado.</td></tr>';
            return;
        }

        clientesFiltrados.forEach((cliente) => {
            const corBadge = cliente.status === 'ACTIVE' ? 'badge-sucesso' : 'badge-perigo';
            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td style="padding: 16px; font-weight: 700; color: var(--texto-principal);">${escaparHtml(cliente.company_name)}</td>
                    <td style="padding: 16px;">${escaparHtml(cliente.tax_id || '-')}</td>
                    <td style="padding: 16px; color: var(--texto-secundario);">${escaparHtml(cliente.location || '-')}</td>
                    <td style="padding: 16px;">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600;">${escaparHtml(cliente.contact_name || '-')}</span>
                            <span style="font-size: 0.85rem; color: var(--texto-secundario);">${escaparHtml(cliente.contact_email || '-')}</span>
                        </div>
                    </td>
                    <td style="padding: 16px;">
                        <span class="badge ${corBadge}">${estadoLegivel(cliente)}</span>
                    </td>
                    <td style="padding: 16px; text-align: right;">
                        <button type="button" onclick="abrirEditarCliente('${escaparHtml(cliente.id)}')" style="color: var(--aksanti-gold); font-weight: 700; font-size: 0.9rem; text-decoration: none; background: transparent; border: 0; cursor: pointer;">Editar Conta</button>
                    </td>
                </tr>
            `;
            corpoTabela.insertAdjacentHTML('beforeend', linhaHTML);
        });
    }

    function aplicarFiltros() {
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
});
