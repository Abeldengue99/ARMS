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
    let filtroEspecialPendencia = '';
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
        AKSANTI_RESPONDED: 'Respondido pela equipa interna',
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
            return 'Respondido pela equipa interna';
        }

        if (pedido) {
            const souRemetente = String(utilizadorAtual.id) === String(pedido.created_by_id);
            const souCliente = utilizadorAtual.tipo === 'CLIENT';
            const destType = texto(pedido.destination_type).toUpperCase();
            const destinatarioSouEu = souCliente ? (destType !== 'AKSANTI' && !souRemetente) : (destType === 'AKSANTI' && !souRemetente);

            if (status === 'SENT') {
                return destinatarioSouEu ? 'Recebido' : 'Enviado';
            }
            if (status === 'RECEIVED') {
                return destinatarioSouEu ? 'Em Análise' : 'Lido pelo Destinatário';
            }
        }

        return etiquetasEstado[status] || texto(status) || '-';
    }

    function obterNomeParceiroPedido(pedido) {
        return isClientRole ? 'Pedido Interno' : (pedido.client_name || '-');
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
            if (filtroStatusAtual === 'ABERTOS') {
                resultado = resultado.filter((pedido) => ['SENT', 'RECEIVED', 'CLIENT_RESPONDED'].includes(pedido.status));
            } else if (filtroStatusAtual === 'VENCIDOS') {
                resultado = resultado.filter((pedido) => valorAtivo(pedido.deadline_expirado));
            } else {
                resultado = resultado.filter((pedido) => pedido.status === filtroStatusAtual);
            }
        }

        if (filtroDataDe) {
            resultado = resultado.filter((pedido) => dataParaISO(pedido.date) >= filtroDataDe);
        }

        if (filtroDataAte) {
            resultado = resultado.filter((pedido) => dataParaISO(pedido.date) <= filtroDataAte);
        }

        if (filtroEspecialPendencia) {
            if (filtroEspecialPendencia === 'pedidos-novos') {
                resultado = resultado.filter(p => p.status === 'RECEIVED');
            } else if (filtroEspecialPendencia === 'pedidos-alteracoes') {
                resultado = resultado.filter(p => alteracaoSolicitada(p));
            } else if (filtroEspecialPendencia === 'prazo-vencido') {
                resultado = resultado.filter(p => valorAtivo(p.deadline_expirado));
            } else if (filtroEspecialPendencia === 'prazo-proximo') {
                resultado = resultado.filter(p => {
                    if (valorAtivo(p.deadline_expirado) || !['SENT', 'RECEIVED', 'CLIENT_RESPONDED', 'AKSANTI_RESPONDED'].includes(p.status)) return false;
                    const dIso = dataParaISO(p.deadline);
                    if (!dIso || dIso === '-') return false;
                    const deadline = new Date(dIso);
                    const agora = new Date();
                    const limite = new Date();
                    limite.setDate(limite.getDate() + 3);
                    return deadline >= agora && deadline <= limite;
                });
            }
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

    let paginaAtualPedidos = 0;
    const TAMANHO_PAGINA_PEDIDOS = 15;

    function renderizarTabelaPedidos(pedidosFiltrados) {
        const corpoTabela = document.getElementById('tabela-corpo-pedidos');
        const contadorEl = document.getElementById('pedidos-contador');
        const btnRecuar = document.getElementById('btn-pedidos-recuar');
        const btnAvancar = document.getElementById('btn-pedidos-avancar');
        const indicador = document.getElementById('pedidos-indicador');
        const navegacao = document.getElementById('pedidos-navegacao');

        if (!corpoTabela) return;

        corpoTabela.innerHTML = '';

        if (!pedidosFiltrados.length) {
            corpoTabela.innerHTML = `<tr><td colspan="7" style="padding: 32px; text-align: center; color: var(--texto-secundario);">${window.t('pedidos.sem_pedidos', 'Nenhum pedido encontrado com os filtros selecionados.')}</td></tr>`;
            if (contadorEl) contadorEl.textContent = '0 pedidos';
            if (navegacao) navegacao.style.display = 'none';
            return;
        }

        const totalPaginas = Math.ceil(pedidosFiltrados.length / TAMANHO_PAGINA_PEDIDOS);
        if (paginaAtualPedidos >= totalPaginas) paginaAtualPedidos = Math.max(0, totalPaginas - 1);

        const inicio = paginaAtualPedidos * TAMANHO_PAGINA_PEDIDOS;
        const fim = Math.min(inicio + TAMANHO_PAGINA_PEDIDOS, pedidosFiltrados.length);
        const pedidosPagina = pedidosFiltrados.slice(inicio, fim);

        pedidosPagina.forEach((pedido) => {
            const referenciaValor = texto(pedido.id_str || pedido.reference);
            const referencia = escaparHtml(referenciaValor);
            const urlDetalhe = 'pedido-detalhe.html?ref=' + encodeURIComponent(referenciaValor);
            const estadoBadge = `<span class="badge ${classeBadgeEstado(pedido.status, pedido)}">${escaparHtml(obterEstadoLegivel(pedido.status, pedido))}</span>`;
            
            const linhaHTML = `
                <tr style="border-bottom: 1px solid #f4f4f5; transition: background-color 0.2s; cursor: pointer;" onclick="window.location.href='${urlDetalhe}'" onmouseover="this.style.backgroundColor='#fafafa'" onmouseout="this.style.backgroundColor='transparent'">
                    <td data-label="${window.t('tabela.referencia', 'Referência')}" style="padding: 16px; font-weight: 600;">${referencia}</td>
                    <td data-label="${window.t('tabela.destino', 'Destino')}" style="padding: 16px;">${escaparHtml(obterNomeParceiroPedido(pedido))}</td>
                    <td data-label="${window.t('tabela.area', 'Área')}" style="padding: 16px;">${escaparHtml(pedido.area_name || '-')}</td>
                    <td data-label="${window.t('tabela.status', 'Estado')}" style="padding: 16px;">${estadoBadge}</td>
                    <td data-label="${window.t('tabela.data', 'Data')}" style="padding: 16px;">${escaparHtml(pedido.date || '-')}</td>
                    <td data-label="${window.t('tabela.deadline', 'Deadline')}" style="padding: 16px;">${renderizarDeadline(pedido)}</td>
                    <td data-label="${window.t('tabela.acoes', 'Ações')}" style="padding: 16px; text-align: right;">
                        <a href="${urlDetalhe}" style="color: var(--aksanti-gold); font-weight: 700; font-size: 0.9rem; text-decoration: none;">${window.t('acoes.ver_detalhes', 'Ver Detalhes')}</a>
                    </td>
                </tr>
            `;
            corpoTabela.insertAdjacentHTML('beforeend', linhaHTML);
        });

        if (contadorEl) {
            contadorEl.textContent = `${inicio + 1} - ${fim} ${window.t('comum.de', 'de')} ${pedidosFiltrados.length} ${window.t('comum.resultados', 'resultados')}`;
        }

        if (navegacao && btnRecuar && btnAvancar && indicador) {
            navegacao.style.display = totalPaginas > 1 ? 'flex' : 'none';
            indicador.textContent = `${paginaAtualPedidos + 1} / ${totalPaginas}`;
            btnRecuar.disabled = paginaAtualPedidos === 0;
            btnAvancar.disabled = paginaAtualPedidos >= totalPaginas - 1;
        }
    }

    function aplicarFiltros() {
        paginaAtualPedidos = 0;
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
        inputFiltro.placeholder = window.t('pedidos.placeholder_pesquisa', 'Referência, destino, estado...');
    }

    if (q) {
        termoAtualPesquisa = q.toLowerCase();
        if (inputFiltro) inputFiltro.value = q;
    }

    const urlFiltro = (urlParams.get('filtro') || urlParams.get('estado') || urlParams.get('status') || '').toLowerCase();
    if (urlFiltro === 'vencidos' || urlFiltro === 'vencido' || urlFiltro === 'prazo-vencido') {
        filtroEspecialPendencia = 'prazo-vencido';
        if (selectStatus) selectStatus.value = 'VENCIDOS';
    } else if (urlFiltro === 'abertos' || urlFiltro === 'aberto') {
        filtroStatusAtual = 'ABERTOS';
        if (selectStatus) selectStatus.value = 'ABERTOS';
    } else if (urlFiltro) {
        const statusUpper = urlFiltro.toUpperCase();
        filtroStatusAtual = statusUpper;
        if (selectStatus) selectStatus.value = statusUpper;
    }

    if (urlParams.get('action') === 'novo') {
        const btnCriarPedido = document.getElementById('btn-criar-pedido');
        if (btnCriarPedido) {
            // Pequeno delay para garantir que a UI carregou
            setTimeout(() => btnCriarPedido.click(), 100);
        }
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
            const val = evento.target.value;
            if (val === 'VENCIDOS') {
                filtroStatusAtual = '';
                filtroEspecialPendencia = 'prazo-vencido';
            } else {
                filtroStatusAtual = val;
                filtroEspecialPendencia = '';
            }
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
            filtroEspecialPendencia = '';
            if (inputFiltro) inputFiltro.value = '';
            if (selectStatus) selectStatus.value = '';
            if (inputDataDe) inputDataDe.value = '';
            if (inputDataAte) inputDataAte.value = '';
            aplicarFiltros();
        });
    }

    const btnRecuar = document.getElementById('btn-pedidos-recuar');
    if (btnRecuar) {
        btnRecuar.addEventListener('click', () => {
            if (paginaAtualPedidos > 0) {
                paginaAtualPedidos--;
                renderizarTabelaPedidos(obterPedidosFiltrados());
                document.querySelector('.tabela-pedidos-responsiva').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    const btnAvancar = document.getElementById('btn-pedidos-avancar');
    if (btnAvancar) {
        btnAvancar.addEventListener('click', () => {
            const totalPaginas = Math.ceil(obterPedidosFiltrados().length / TAMANHO_PAGINA_PEDIDOS);
            if (paginaAtualPedidos < totalPaginas - 1) {
                paginaAtualPedidos++;
                renderizarTabelaPedidos(obterPedidosFiltrados());
                document.querySelector('.tabela-pedidos-responsiva').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    window.addEventListener('ArmsFiltrarPendencia', (e) => {
        filtroEspecialPendencia = e.detail;
        
        // Limpar outros filtros para não confundir
        termoAtualPesquisa = '';
        filtroStatusAtual = '';
        filtroDataDe = '';
        filtroDataAte = '';
        if (inputFiltro) inputFiltro.value = '';
        if (selectStatus) selectStatus.value = '';
        if (inputDataDe) inputDataDe.value = '';
        if (inputDataAte) inputDataAte.value = '';
        
        aplicarFiltros();
        
        // Fazer scroll até à tabela
        const tabela = document.querySelector('.card.deslizar-cima-isaf');
        if (tabela) {
            tabela.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    const btnPDF = document.getElementById('btn-exportar-pdf');
    if (btnPDF) {
        btnPDF.addEventListener('click', () => ArmsExportacoes.baixarPDF(opcoesExportacaoPedidos()));
    }

    const btnExcel = document.getElementById('btn-exportar-excel');
    if (btnExcel) {
        btnExcel.addEventListener('click', () => ArmsExportacoes.baixarExcel(opcoesExportacaoPedidos()));
    }
});
