/**
 * ARMS - Motor de Pedidos
 * Renderiza a tabela de pedidos, aplica filtros e exporta relatórios.
 */
document.addEventListener('DOMContentLoaded', () => {
    let pedidosCarregados = [];
    let termoAtualPesquisa = '';
    let filtroStatusAtual = '';
    let filtroDataDe = '';
    let filtroDataAte = '';
    let utilizadorAtual = {};

    try {
        utilizadorAtual = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
    } catch (e) {
        utilizadorAtual = {};
    }

    const isExternalClient = utilizadorAtual.tipo === 'CLIENT';
    const isAdminRole = utilizadorAtual.admin === true;
    const isClientRole = isExternalClient || !isAdminRole;

    const etiquetasEstado = {
        DRAFT: 'Rascunho',
        SENT: 'Enviado',
        RECEIVED: 'Recebido',
        CLIENT_RESPONDED: 'Resposta recebida',
        AKSANTI_RESPONDED: 'Respondido pela Aksanti',
        ACCEPTED: 'Aceite',
        REJECTED: 'Rejeitado',
        CLOSED: 'Fechado'
    };

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

    function valorAtivo(valor) {
        return valor === true ||
            valor === 1 ||
            ['1', 't', 'true', 'sim', 'yes'].includes(texto(valor).trim().toLowerCase());
    }

    function alteracaoSolicitada(pedido) {
        return pedido &&
            pedido.status === 'CLIENT_RESPONDED' &&
            texto(pedido.latest_response_decision).toUpperCase() === 'PENDING';
    }

    function obterEstadoLegivel(status, pedido = null) {
        if (alteracaoSolicitada(pedido)) {
            return isClientRole ? 'Solicitaste alteração' : 'Alteração solicitada';
        }

        if (status === 'CLIENT_RESPONDED' && pedido && texto(pedido.latest_response_actor_type).toUpperCase() === 'AKSANTI') {
            return 'Respondido pela Aksanti';
        }

        return etiquetasEstado[status] || texto(status) || '-';
    }

    function obterNomeParceiroPedido(pedido) {
        return isClientRole ? 'Aksanti' : (pedido.client_name || '-');
    }

    function obterRotuloEntidadePedido() {
        if (isAdminRole) return 'Cliente';
        if (isExternalClient) return 'Parceiro';
        return 'Destino';
    }

    function renderizarDeadline(pedido) {
        const deadline = pedido.deadline || '-';
        const urgente = valorAtivo(pedido.deadline_expirado);
        const classeUrgente = urgente ? ' urgente' : '';
        const sufixoUrgente = urgente ? ' · urgente' : '';

        return `<span class="deadline-tabela${classeUrgente}">${escaparHtml(deadline)}${sufixoUrgente}</span>`;
    }

    function dataParaISO(valor) {
        const data = texto(valor).trim();
        const dataPT = data.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
        if (dataPT) return `${dataPT[3]}-${dataPT[2]}-${dataPT[1]}`;

        const dataISO = data.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (dataISO) return `${dataISO[1]}-${dataISO[2]}-${dataISO[3]}`;

        return data.substring(0, 10);
    }

    function obterPedidosFiltrados() {
        let resultado = [...pedidosCarregados];

        if (termoAtualPesquisa) {
            resultado = resultado.filter((pedido) =>
                textoBusca(pedido.id_str).includes(termoAtualPesquisa) ||
                textoBusca(pedido.status).includes(termoAtualPesquisa) ||
                textoBusca(obterEstadoLegivel(pedido.status, pedido)).includes(termoAtualPesquisa) ||
                textoBusca(obterNomeParceiroPedido(pedido)).includes(termoAtualPesquisa) ||
                textoBusca(pedido.area_name).includes(termoAtualPesquisa) ||
                textoBusca(pedido.deadline).includes(termoAtualPesquisa)
            );
        }

        if (filtroStatusAtual) {
            resultado = resultado.filter((pedido) => pedido.status === filtroStatusAtual);
        }

        if (filtroDataDe) {
            resultado = resultado.filter((pedido) => dataParaISO(pedido.date) >= filtroDataDe);
        }

        if (filtroDataAte) {
            resultado = resultado.filter((pedido) => dataParaISO(pedido.date) <= filtroDataAte);
        }

        return resultado;
    }

    function classeBadgeEstado(status, pedido = null) {
        if (alteracaoSolicitada(pedido)) return 'badge-aviso';
        if (status === 'ACCEPTED' || status === 'CLOSED') return 'badge-sucesso';
        if (status === 'REJECTED') return 'badge-perigo';
        if (status === 'SENT' || status === 'CLIENT_RESPONDED' || status === 'AKSANTI_RESPONDED' || status === 'RECEIVED') return 'badge-info';
        return 'badge-aviso';
    }

    function renderizarTabelaPedidos(pedidosFiltrados) {
        const corpoTabela = document.getElementById('tabela-corpo-pedidos');
        const contadorEl = document.getElementById('pedidos-contador');
        if (!corpoTabela) return;

        corpoTabela.innerHTML = '';

        if (!pedidosFiltrados.length) {
            corpoTabela.innerHTML = `<tr><td colspan="7" style="padding: 32px; text-align: center; color: var(--texto-secundario);">Nenhum pedido encontrado com os filtros selecionados.</td></tr>`;
            if (contadorEl) contadorEl.textContent = '0 pedidos';
            return;
        }

        pedidosFiltrados.forEach((pedido) => {
            const referenciaValor = texto(pedido.id_str || pedido.reference);
            const referencia = escaparHtml(referenciaValor);
            const urlDetalhe = 'pedido-detalhe.html?ref=' + encodeURIComponent(referenciaValor);
            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s; cursor: pointer;" onclick="window.location.href='${urlDetalhe}'" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td style="padding: 16px; font-weight: 600;">${referencia}</td>
                    <td style="padding: 16px;">${escaparHtml(obterNomeParceiroPedido(pedido))}</td>
                    <td style="padding: 16px;">${escaparHtml(pedido.area_name || '-')}</td>
                    <td style="padding: 16px;"><span class="badge ${classeBadgeEstado(pedido.status, pedido)}">${escaparHtml(obterEstadoLegivel(pedido.status, pedido))}</span></td>
                    <td style="padding: 16px;">${escaparHtml(pedido.date || '-')}</td>
                    <td style="padding: 16px;">${renderizarDeadline(pedido)}</td>
                    <td style="padding: 16px; text-align: right;">
                        <a href="${urlDetalhe}" style="color: var(--aksanti-gold); font-weight: 700; font-size: 0.9rem; text-decoration: none;">Ver Detalhes</a>
                    </td>
                </tr>
            `;
            corpoTabela.insertAdjacentHTML('beforeend', linhaHTML);
        });

        if (contadorEl) {
            contadorEl.textContent = pedidosFiltrados.length + ' pedido' + (pedidosFiltrados.length !== 1 ? 's' : '');
        }
    }

    function aplicarFiltros() {
        renderizarTabelaPedidos(obterPedidosFiltrados());
    }

    function opcoesExportacaoPedidos() {
        return {
            titulo: 'Relatório de Pedidos',
            subtitulo: 'Aksanti Request Management System',
            nomeArquivo: 'relatorio-pedidos-arms',
            filtros: {
                Pesquisa: termoAtualPesquisa,
                Estado: filtroStatusAtual ? obterEstadoLegivel(filtroStatusAtual) : '',
                'Data de': filtroDataDe,
                'Data até': filtroDataAte
            },
            colunas: [
                { titulo: 'Referência', valor: (p) => p.id_str || '-' },
                { titulo: obterRotuloEntidadePedido(), valor: (p) => obterNomeParceiroPedido(p) },
                { titulo: 'Área', valor: (p) => p.area_name || '-' },
                { titulo: 'Estado', valor: (p) => obterEstadoLegivel(p.status, p) },
                { titulo: 'Data', valor: (p) => p.date || '-' },
                { titulo: 'Deadline', valor: (p) => p.deadline || '-' },
                { titulo: 'Urgente', valor: (p) => valorAtivo(p.deadline_expirado) ? 'Sim' : 'Não' }
            ],
            linhas: obterPedidosFiltrados()
        };
    }

    const urlParams = new URLSearchParams(window.location.search);
    const q = urlParams.get('q');
    const inputFiltro = document.getElementById('filtro-pedidos');
    const selectStatus = document.getElementById('filtro-status');
    const inputDataDe = document.getElementById('filtro-data-de');
    const inputDataAte = document.getElementById('filtro-data-ate');
    const cabecalhoCliente = document.getElementById('pedidos-entidade-cabecalho') ||
        document.querySelector('[data-i18n="tabela.cliente"]');

    if (cabecalhoCliente) {
        cabecalhoCliente.textContent = obterRotuloEntidadePedido();
    }

    if (inputFiltro) {
        inputFiltro.placeholder = `Referência, ${obterRotuloEntidadePedido()}, Estado...`;
    }

    if (q) {
        termoAtualPesquisa = q.toLowerCase();
        if (inputFiltro) inputFiltro.value = q;
    }

    if (typeof ArmsTempoReal !== 'undefined') {
        ArmsTempoReal.iniciar('pedidos', (data) => {
            if (data.pedidos) {
                pedidosCarregados = data.pedidos;
                aplicarFiltros();
            }
        });
    }

    if (inputFiltro) {
        inputFiltro.addEventListener('input', (evento) => {
            termoAtualPesquisa = evento.target.value.trim().toLowerCase();
            aplicarFiltros();
        });
    }

    if (selectStatus) {
        selectStatus.addEventListener('change', (evento) => {
            filtroStatusAtual = evento.target.value;
            aplicarFiltros();
        });
    }

    if (inputDataDe) {
        inputDataDe.addEventListener('change', (evento) => {
            filtroDataDe = evento.target.value;
            aplicarFiltros();
        });
    }

    if (inputDataAte) {
        inputDataAte.addEventListener('change', (evento) => {
            filtroDataAte = evento.target.value;
            aplicarFiltros();
        });
    }

    const btnLimpar = document.getElementById('btn-limpar-filtros');
    if (btnLimpar) {
        btnLimpar.addEventListener('click', () => {
            termoAtualPesquisa = '';
            filtroStatusAtual = '';
            filtroDataDe = '';
            filtroDataAte = '';
            if (inputFiltro) inputFiltro.value = '';
            if (selectStatus) selectStatus.value = '';
            if (inputDataDe) inputDataDe.value = '';
            if (inputDataAte) inputDataAte.value = '';
            aplicarFiltros();
        });
    }

    const btnPDF = document.getElementById('btn-exportar-pdf');
    if (btnPDF) {
        btnPDF.addEventListener('click', () => ArmsExportacoes.baixarPDF(opcoesExportacaoPedidos()));
    }

    const btnExcel = document.getElementById('btn-exportar-excel');
    if (btnExcel) {
        btnExcel.addEventListener('click', () => ArmsExportacoes.baixarExcel(opcoesExportacaoPedidos()));
    }
});
