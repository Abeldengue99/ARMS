/**
 * ARMS — Lógica Dedicada do Painel de Controlo (Dashboard)
 */

// Mostrar barra de pesquisa apenas se não estivermos no telemóvel
if (window.innerWidth > 768) {
    const barraPesquisaTopo = document.getElementById('barra-pesquisa-topo');
    if (barraPesquisaTopo) {
        barraPesquisaTopo.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', () => {

    function prepararCanvas(canvas) {
        const contentor = canvas.parentElement;
        const larguraBase = Math.floor(contentor.getBoundingClientRect().width || contentor.clientWidth || 320);
        const larguraMaxima = canvas.id === 'grafico-areas-donut' ? 520 : larguraBase;
        const largura = Math.max(260, Math.min(larguraBase, larguraMaxima));
        const altura = Math.max(180, contentor.clientHeight || 240);
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.floor(largura * dpr);
        canvas.height = Math.floor(altura * dpr);
        canvas.style.width = largura + 'px';
        canvas.style.height = altura + 'px';
        canvas.style.maxWidth = '100%';
        canvas.style.display = 'block';
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, largura, altura };
    }

    function animarCanvas(canvas, chave, desenharFrame) {
        if (canvas.dataset.graficoChave === chave) {
            desenharFrame(1);
            return;
        }

        canvas.dataset.graficoChave = chave;
        if (canvas._armsAnimacao) {
            cancelAnimationFrame(canvas._armsAnimacao);
        }

        const inicio = performance.now();
        const duracao = 760;

        const executar = (agora) => {
            const bruto = Math.min(1, (agora - inicio) / duracao);
            const progresso = 1 - Math.pow(1 - bruto, 3);
            desenharFrame(progresso);

            if (bruto < 1) {
                canvas._armsAnimacao = requestAnimationFrame(executar);
            }
        };

        canvas._armsAnimacao = requestAnimationFrame(executar);
    }

    function desenharRetanguloArredondado(ctx, x, y, largura, altura, raio) {
        const r = Math.min(raio, largura / 2, Math.abs(altura) / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + largura - r, y);
        ctx.quadraticCurveTo(x + largura, y, x + largura, y + r);
        ctx.lineTo(x + largura, y + altura - r);
        ctx.quadraticCurveTo(x + largura, y + altura, x + largura - r, y + altura);
        ctx.lineTo(x + r, y + altura);
        ctx.quadraticCurveTo(x, y + altura, x, y + altura - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function truncarTextoCanvas(ctx, texto, larguraMaxima) {
        const valor = String(texto || '');
        if (ctx.measureText(valor).width <= larguraMaxima) return valor;

        let cortado = valor;
        while (cortado.length > 4 && ctx.measureText(cortado + '...').width > larguraMaxima) {
            cortado = cortado.slice(0, -1);
        }

        return cortado.length > 4 ? cortado + '...' : valor.slice(0, 4) + '...';
    }

    /**
     * Desenha barras horizontais com cores, labels e valores.
     * Cada barra é clicável (navegação para pedidos filtrados).
     */
    function desenharGraficoBarrasHorizontais(canvas, itens, progresso = 1) {
        const { ctx, largura, altura } = prepararCanvas(canvas);
        const total = itens.reduce((soma, item) => soma + Number(item.valor || 0), 0);
        const margemEsquerda = 120;
        const margemDireita = 50;
        const barraAltura = 28;
        const espacamento = 16;
        const areaLargura = largura - margemEsquerda - margemDireita;

        ctx.clearRect(0, 0, largura, altura);

        if (!total) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '600 13px Inter, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados disponíveis', largura / 2, altura / 2);
            return;
        }

        const maximo = Math.max(1, ...itens.map(i => Number(i.valor || 0)));
        const totalAltura = itens.length * (barraAltura + espacamento) - espacamento;
        const inicioY = Math.max(10, (altura - totalAltura) / 2);

        // Guardar áreas clicáveis para o canvas
        canvas._armsAreas = [];

        itens.forEach((item, indice) => {
            const y = inicioY + indice * (barraAltura + espacamento);
            const valorNum = Number(item.valor || 0);
            const proporcao = (valorNum / maximo) * progresso;
            const barraLargura = Math.max(4, areaLargura * proporcao);

            // Label à esquerda
            ctx.fillStyle = '#475569';
            ctx.font = '600 13px Inter, Arial, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(item.label, margemEsquerda - 14, y + barraAltura / 2 + 4);

            // Fundo da barra (track)
            ctx.fillStyle = '#f1f5f9';
            desenharRetanguloArredondado(ctx, margemEsquerda, y, areaLargura, barraAltura, 8);
            ctx.fill();

            // Barra colorida
            ctx.fillStyle = item.cor;
            desenharRetanguloArredondado(ctx, margemEsquerda, y, barraLargura, barraAltura, 8);
            ctx.fill();

            // Valor numérico à direita
            if (progresso > 0.85) {
                ctx.fillStyle = '#1e293b';
                ctx.font = '700 14px Inter, Arial, sans-serif';
                ctx.textAlign = 'left';
                ctx.fillText(String(valorNum), margemEsquerda + barraLargura + 10, y + barraAltura / 2 + 5);
            }

            // Guardar área clicável
            if (item.href) {
                canvas._armsAreas.push({ x: margemEsquerda, y, w: areaLargura, h: barraAltura, href: item.href });
            }
        });

        // Event listener para cliques (uma única vez)
        if (!canvas._armsClickRegistado) {
            canvas._armsClickRegistado = true;
            canvas.style.cursor = 'pointer';
            canvas.addEventListener('click', (evento) => {
                const rect = canvas.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;
                const mx = (evento.clientX - rect.left);
                const my = (evento.clientY - rect.top);
                const areas = canvas._armsAreas || [];
                for (const area of areas) {
                    if (mx >= area.x && mx <= area.x + area.w && my >= area.y && my <= area.y + area.h) {
                        window.location.href = area.href;
                        break;
                    }
                }
            });
        }
    }

    function desenharGraficoBarras(canvas, labels, valores, progresso = 1) {
        const { ctx, largura, altura } = prepararCanvas(canvas);
        const margem = { topo: 18, direita: 14, baixo: 38, esquerda: 34 };
        const areaLargura = largura - margem.esquerda - margem.direita;
        const areaAltura = altura - margem.topo - margem.baixo;
        const maximo = Math.max(1, ...valores.map(Number));
        let passo = areaLargura / Math.max(1, valores.length);
        const barraLargura = Math.min(62, passo * 0.55);
        
        let offsetX = 0;
        const maxPasso = 120;
        if (passo > maxPasso) {
            passo = maxPasso;
            const larguraTotalBarras = passo * valores.length;
            offsetX = (areaLargura - larguraTotalBarras) / 2;
        }

        ctx.clearRect(0, 0, largura, altura);
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(margem.esquerda, margem.topo);
        ctx.lineTo(margem.esquerda, margem.topo + areaAltura);
        ctx.lineTo(largura - margem.direita, margem.topo + areaAltura);
        ctx.stroke();

        ctx.font = '12px Inter, Arial, sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.textAlign = 'right';
        ctx.fillText(String(maximo), margem.esquerda - 8, margem.topo + 8);
        ctx.fillText('0', margem.esquerda - 8, margem.topo + areaAltura);

        valores.forEach((valor, indice) => {
            const alturaBarra = (Number(valor) / maximo) * (areaAltura - 10) * progresso;
            const x = margem.esquerda + offsetX + indice * passo + (passo - barraLargura) / 2;
            const y = margem.topo + areaAltura - alturaBarra;

            ctx.fillStyle = '#e58a13';
            desenharRetanguloArredondado(ctx, x, y, barraLargura, alturaBarra, 7);
            ctx.fill();

            if (progresso > 0.92) {
                ctx.fillStyle = '#334155';
                ctx.textAlign = 'center';
                ctx.font = '700 12px Segoe UI, Arial, sans-serif';
                ctx.fillText(String(valor), x + barraLargura / 2, y - 6);
            }

            ctx.fillStyle = '#64748b';
            ctx.font = '12px Segoe UI, Arial, sans-serif';
            ctx.fillText(labels[indice] || '', x + barraLargura / 2, altura - 12);
        });
    }

    function desenharGraficoBarrasHorizontais(canvas, itens, progresso = 1) {
        const { ctx, largura, altura } = prepararCanvas(canvas);
        ctx.clearRect(0, 0, largura, altura);

        const margem = { esquerda: 110, direita: 40, topo: 16, fundo: 16 };
        const areaLargura = largura - margem.esquerda - margem.direita;
        const areaAltura = altura - margem.topo - margem.fundo;
        const totalItens = itens.length;
        const espacamento = 14;
        const alturaBarra = Math.max(12, (areaAltura - (totalItens - 1) * espacamento) / totalItens);

        // Encontrar valor máximo para escala
        const valorMaximo = Math.max(...itens.map(i => i.valor), 1);

        // Adicionar event listener para clique apenas uma vez
        if (!canvas.dataset.hasClickListener) {
            canvas.dataset.hasClickListener = 'true';
            canvas.addEventListener('click', (e) => {
                const rect = canvas.getBoundingClientRect();
                const xClique = (e.clientX - rect.left) * (largura / rect.width);
                const yClique = (e.clientY - rect.top) * (altura / rect.height);

                itens.forEach((item, indice) => {
                    const y = margem.topo + indice * (alturaBarra + espacamento);
                    if (yClique >= y && yClique <= y + alturaBarra && xClique >= margem.esquerda) {
                        window.location.href = item.href;
                    }
                });
            });

            // Efeito Hover (mudar cursor)
            canvas.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                const xClique = (e.clientX - rect.left) * (largura / rect.width);
                const yClique = (e.clientY - rect.top) * (altura / rect.height);
                let noBotao = false;

                itens.forEach((item, indice) => {
                    const y = margem.topo + indice * (alturaBarra + espacamento);
                    if (yClique >= y && yClique <= y + alturaBarra && xClique >= margem.esquerda) {
                        noBotao = true;
                    }
                });
                canvas.style.cursor = noBotao ? 'pointer' : 'default';
            });
        }

        itens.forEach((item, indice) => {
            const y = margem.topo + indice * (alturaBarra + espacamento);
            const larguraMaxBarra = (item.valor / valorMaximo) * areaLargura;
            const larguraAtualBarra = larguraMaxBarra * progresso;

            // 1. Desenhar a label (Alinhamento à direita no espaço esquerdo)
            ctx.fillStyle = '#475569';
            ctx.font = '600 13px Inter, Arial, sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText(item.label, margem.esquerda - 14, y + alturaBarra / 2);

            // 2. Desenhar a barra de fundo (Track cinzento suave)
            ctx.fillStyle = '#f1f5f9';
            desenharRetanguloArredondado(ctx, margem.esquerda, y, areaLargura, alturaBarra, alturaBarra / 2);
            ctx.fill();

            // 3. Desenhar a barra de progresso colorida
            if (larguraAtualBarra > 0) {
                ctx.fillStyle = item.cor;
                desenharRetanguloArredondado(ctx, margem.esquerda, y, larguraAtualBarra, alturaBarra, alturaBarra / 2);
                ctx.fill();
            }

            // 4. Desenhar o valor à frente da barra
            if (progresso > 0.9) {
                ctx.fillStyle = '#1e293b';
                ctx.font = '700 13px Inter, Arial, sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(String(item.valor), margem.esquerda + Math.max(larguraAtualBarra + 10, 10), y + alturaBarra / 2);
            }
        });
    }

    function desenharGraficoDonut(canvas, labels, valores, progresso = 1) {
        const { ctx, largura, altura } = prepararCanvas(canvas);
        const total = valores.reduce((soma, valor) => soma + Number(valor || 0), 0);
        const cores = ['#3b82f6', '#d97706', '#ef4444', '#10b981', '#6366f1', '#f97316', '#06b6d4', '#8b5cf6'];
        const legendaDireita = largura >= 500;
        const raio = Math.min(
            legendaDireita ? largura * 0.22 : largura * 0.28,
            legendaDireita ? altura * 0.34 : altura * 0.30,
            132
        );
        const centroX = legendaDireita ? largura * 0.34 : largura / 2;
        const centroY = legendaDireita ? altura / 2 : Math.max(96, raio + 22);
        let angulo = -Math.PI / 2;

        ctx.clearRect(0, 0, largura, altura);

        if (!total) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '600 14px Inter, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sem dados', largura / 2, altura / 2);
            return;
        }

        valores.forEach((valor, indice) => {
            const fatia = (Number(valor) / total) * Math.PI * 2 * progresso;
            ctx.beginPath();
            ctx.moveTo(centroX, centroY);
            ctx.arc(centroX, centroY, raio, angulo, angulo + fatia);
            ctx.closePath();
            ctx.fillStyle = cores[indice % cores.length];
            ctx.fill();
            angulo += fatia;
        });

        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(centroX, centroY, raio * 0.58, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';

        ctx.fillStyle = '#111827';
        ctx.font = '800 22px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(String(total), centroX, centroY + 6);

        ctx.textAlign = 'left';
        labels.slice(0, 5).forEach((label, indice) => {
            const linha = legendaDireita ? indice : Math.floor(indice / 2);
            const coluna = legendaDireita ? 0 : indice % 2;
            const xBase = legendaDireita ? largura * 0.66 : 12 + coluna * (largura / 2);
            const y = legendaDireita ? 28 + indice * 25 : altura - 70 + linha * 24;
            const larguraTexto = legendaDireita
                ? Math.max(80, largura - xBase - 22)
                : Math.max(80, largura / 2 - 42);
            ctx.fillStyle = cores[indice % cores.length];
            ctx.fillRect(xBase, y - 10, 12, 12);
            ctx.fillStyle = '#475569';
            ctx.font = '12px Segoe UI, Arial, sans-serif';
            const textoLegenda = `${label}: ${valores[indice]}`;
            ctx.fillText(truncarTextoCanvas(ctx, textoLegenda, larguraTexto), xBase + 18, y);
        });
    }

    // 1. Data dinâmica do período apresentado no dashboard
    function obterLocaleDashboard() {
        const idiomaAtual = localStorage.getItem('arms_idioma') ||
            document.getElementById('select-idioma-aksanti')?.value ||
            'pt';

        return {
            pt: 'pt-PT',
            en: 'en-US',
            fr: 'fr-FR'
        }[idiomaAtual] || 'pt-PT';
    }

    function capitalizarPrimeiraLetra(texto) {
        if (!texto) return '';
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function atualizarDataCorrenteDashboard() {
        const dataCorrenteEl = document.getElementById('data-corrente');
        if (!dataCorrenteEl) return;

        const dataAtual = new Date();
        const locale = obterLocaleDashboard();
        const mesAtual = new Intl.DateTimeFormat(locale, { month: 'long' }).format(dataAtual);
        dataCorrenteEl.textContent = `${capitalizarPrimeiraLetra(mesAtual)}, ${dataAtual.getFullYear()}`;
    }

    atualizarDataCorrenteDashboard();
    setInterval(atualizarDataCorrenteDashboard, 60 * 60 * 1000);

    function obterUtilizadorDashboard() {
        try {
            return JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
        } catch (e) {
            return {};
        }
    }

    const utilizadorDashboard = obterUtilizadorDashboard();
    const dashboardClienteExterno = utilizadorDashboard.tipo === 'CLIENT';
    const dashboardAdmin = utilizadorDashboard.admin === true;

    // Configurar filtros se for admin
    if (dashboardAdmin) {
        const containerFiltros = document.getElementById('dashboard-filtros-container');
        const selectTipo = document.getElementById('filtro-dashboard-tipo');
        const selectCliente = document.getElementById('filtro-dashboard-cliente');
        const selectArea = document.getElementById('filtro-dashboard-area');
        const selectFuncionario = document.getElementById('filtro-dashboard-funcionario');
        const btnLimpar = document.getElementById('btn-limpar-filtros-dashboard');

        if (containerFiltros && selectTipo && selectCliente && selectArea) {
            containerFiltros.style.display = 'flex';

            // Carregar clientes
            fetch('api/clientes.php')
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso && data.dados) {
                        data.dados.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = c.company_name || c.name;
                            selectCliente.appendChild(opt);
                        });
                    }
                });

            // Carregar áreas
            fetch('api/areas.php')
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso && data.dados) {
                        data.dados.forEach(a => {
                            const opt = document.createElement('option');
                            opt.value = a.id;
                            opt.textContent = `${a.name} (${a.code})`;
                            selectArea.appendChild(opt);
                        });
                    }
                });

            // Carregar funcionários (AKSANTI)
            fetch('api/utilizadores.php')
                .then(res => res.json())
                .then(data => {
                    if (data.sucesso && data.dados) {
                        data.dados.filter(u => u.user_type === 'AKSANTI').forEach(u => {
                            const opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = u.full_name || u.email;
                            selectFuncionario.appendChild(opt);
                        });
                    }
                });

            function atualizarVisibilidadeFiltros() {
                const tipo = selectTipo.value;
                const cliente = selectCliente.value;
                const area = selectArea.value;
                const funcionario = selectFuncionario.value;

                if (tipo === 'empresas') {
                    selectCliente.style.display = 'block';
                    selectFuncionario.style.display = 'none';
                    selectFuncionario.value = '';
                    if (cliente) {
                        selectArea.style.display = 'block';
                    } else {
                        selectArea.style.display = 'none';
                        selectArea.value = '';
                    }
                } else if (tipo === 'interna') {
                    selectCliente.style.display = 'none';
                    selectCliente.value = '';
                    selectArea.style.display = 'none';
                    selectArea.value = '';
                    selectFuncionario.style.display = 'block';
                } else {
                    selectCliente.style.display = 'none';
                    selectCliente.value = '';
                    selectArea.style.display = 'none';
                    selectArea.value = '';
                    selectFuncionario.style.display = 'none';
                    selectFuncionario.value = '';
                }

                if (tipo !== 'geral' || cliente || area || funcionario) {
                    btnLimpar.style.display = 'flex';
                } else {
                    btnLimpar.style.display = 'none';
                }
            }

            function aplicarFiltros() {
                const tipoVal = selectTipo.value;
                const clienteVal = selectCliente.value;
                const areaVal = selectArea.value;
                const funcionarioVal = selectFuncionario.value;

                const filtros = {};
                if (tipoVal === 'interna') {
                    filtros.filter_destination_type = 'AKSANTI';
                    if (funcionarioVal) {
                        filtros.filter_recipient_user_id = funcionarioVal;
                    }
                } else if (tipoVal === 'empresas') {
                    if (clienteVal) {
                        filtros.filter_client_id = clienteVal;
                    } else {
                        filtros.filter_destination_type = 'CLIENT';
                    }
                }

                if (areaVal) {
                    filtros.filter_area_id = areaVal;
                }

                if (typeof ArmsTempoReal !== 'undefined' && typeof ArmsTempoReal.definirFiltros === 'function') {
                    ArmsTempoReal.definirFiltros(filtros);
                    ArmsTempoReal.forcarAtualizacao();
                }
            }

            selectTipo.addEventListener('change', () => {
                atualizarVisibilidadeFiltros();
                aplicarFiltros();
            });

            selectCliente.addEventListener('change', () => {
                atualizarVisibilidadeFiltros();
                aplicarFiltros();
            });

            selectArea.addEventListener('change', () => {
                aplicarFiltros();
            });

            selectFuncionario.addEventListener('change', () => {
                aplicarFiltros();
            });

            btnLimpar.addEventListener('click', () => {
                selectTipo.value = 'geral';
                selectCliente.value = '';
                selectArea.value = '';
                selectFuncionario.value = '';
                atualizarVisibilidadeFiltros();
                aplicarFiltros();
            });
        }
    }

    function rotuloEntidadePedidoDashboard() {
        if (dashboardAdmin) return 'Cliente';
        if (dashboardClienteExterno) return 'Parceiro';
        return 'Destino';
    }

    function nomeEntidadePedidoDashboard(pedido) {
        if (dashboardAdmin) {
            return pedido.client_name || '-';
        }
        return 'Aksanti';
    }

    function aplicarRotuloEntidadeDashboard() {
        const cabecalhoCliente = document.getElementById('dashboard-entidade-cabecalho') ||
            document.querySelector('[data-i18n="tabela.cliente"]');
        if (cabecalhoCliente) {
            cabecalhoCliente.textContent = rotuloEntidadePedidoDashboard();
        }
    }

    aplicarRotuloEntidadeDashboard();

    const TAMANHO_PAGINA_RECENTES = 5;
    let pedidosRecentesDashboard = [];
    let paginaRecentesDashboard = 0;

    function escaparTextoDashboard(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[caractere]));
    }

    function atualizarNavegacaoRecentes() {
        const navegacao = document.getElementById('dashboard-recentes-navegacao');
        const indicador = document.getElementById('dashboard-recentes-indicador');
        const btnRecuar = document.getElementById('btn-recentes-recuar');
        const btnAvancar = document.getElementById('btn-recentes-avancar');
        const totalPaginas = Math.max(1, Math.ceil(pedidosRecentesDashboard.length / TAMANHO_PAGINA_RECENTES));

        if (!navegacao || !indicador || !btnRecuar || !btnAvancar) return;

        navegacao.hidden = pedidosRecentesDashboard.length <= TAMANHO_PAGINA_RECENTES;
        indicador.textContent = `${Math.min(paginaRecentesDashboard + 1, totalPaginas)} / ${totalPaginas}`;
        btnRecuar.disabled = paginaRecentesDashboard <= 0;
        btnAvancar.disabled = paginaRecentesDashboard >= totalPaginas - 1;
    }

    function renderizarPedidosRecentes() {
        const tbody = document.getElementById('tabela-corpo-recentes');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!pedidosRecentesDashboard.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="padding: 24px; text-align: center; color: var(--texto-secundario);">Sem pedidos recentes</td></tr>';
            atualizarNavegacaoRecentes();
            return;
        }

        const totalPaginas = Math.max(1, Math.ceil(pedidosRecentesDashboard.length / TAMANHO_PAGINA_RECENTES));
        paginaRecentesDashboard = Math.min(Math.max(0, paginaRecentesDashboard), totalPaginas - 1);

        pedidosRecentesDashboard
            .slice(paginaRecentesDashboard * TAMANHO_PAGINA_RECENTES, (paginaRecentesDashboard + 1) * TAMANHO_PAGINA_RECENTES)
            .forEach(r => {
                const row = document.createElement('tr');
                let statusClass = 'badge-info';
                if (r.status === 'ACCEPTED' || r.status === 'CLOSED') statusClass = 'badge-sucesso';
                if (r.status === 'REJECTED') statusClass = 'badge-perigo';
                if (r.status === 'DRAFT') statusClass = 'badge-aviso';
                if (r.status === 'CLIENT_RESPONDED') statusClass = 'badge-info';

                let textoEstado = r.status;
                if (window.t) textoEstado = window.t('status.' + r.status, r.status);
                
                const utilizadorAtual = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
                const souRemetente = String(utilizadorAtual.id) === String(r.created_by_id);
                const souCliente = utilizadorAtual.tipo === 'CLIENT';
                const destType = (r.destination_type || '').toUpperCase();
                const destinatarioSouEu = souCliente ? (destType !== 'AKSANTI' && !souRemetente) : (destType === 'AKSANTI' && !souRemetente);

                if (r.status === 'SENT') {
                    textoEstado = destinatarioSouEu ? 'Recebido' : 'Enviado';
                }
                if (r.status === 'RECEIVED') {
                    textoEstado = destinatarioSouEu ? 'Em Análise' : 'Lido pelo Destinatário';
                }

                row.style.cursor = 'pointer';
                row.onclick = () => window.location.href = `pedido-detalhe.html?ref=${encodeURIComponent(r.reference)}`;
                row.innerHTML = `<td style="padding: 16px; font-weight: 600;">${escaparTextoDashboard(r.reference)}</td>
                    <td style="padding: 16px; color: var(--texto-secundario);">${escaparTextoDashboard(nomeEntidadePedidoDashboard(r))}</td>
                    <td style="padding: 16px; color: var(--texto-secundario);">${escaparTextoDashboard(r.area_name || '-')}</td>
                    <td style="padding: 16px;"><span class="badge ${statusClass}">${escaparTextoDashboard(textoEstado)}</span></td>`;
                tbody.appendChild(row);
            });

        atualizarNavegacaoRecentes();

        if (typeof mudarIdiomaAksanti === 'function') {
            const idiomaMemorizado = localStorage.getItem('arms_idioma') || 'pt';
            mudarIdiomaAksanti(idiomaMemorizado);
            aplicarRotuloEntidadeDashboard();
        }
    }

    const selectIdiomaDashboard = document.getElementById('select-idioma-aksanti');
    if (selectIdiomaDashboard) {
        selectIdiomaDashboard.addEventListener('change', () => {
            atualizarDataCorrenteDashboard();
            setTimeout(aplicarRotuloEntidadeDashboard, 0);
        });
    }

    // 2. Funcionalidade de Pesquisa Ao Vivo
    const barraPesquisa = document.getElementById('input-pesquisa-geral');
    if (barraPesquisa) {
        barraPesquisa.addEventListener('input', () => {
            const termo = barraPesquisa.value.trim().toLowerCase();
            
            if (dashboardAdmin && termo.length > 1) {
                let encontrouAlvo = false;
                const selTipo = document.getElementById('dashboard-filtro-tipo');
                const selCl = document.getElementById('dashboard-filtro-cliente');
                const selFn = document.getElementById('dashboard-filtro-funcionario');
                
                if (selTipo && selCl && selFn) {
                    const cl = Array.from(selCl.options).find(opt => opt.text.toLowerCase().includes(termo));
                    if (cl && cl.value) {
                        selTipo.value = 'empresas';
                        selCl.style.display = 'block';
                        selFn.style.display = 'none';
                        selFn.value = '';
                        selCl.value = cl.value;
                        if (typeof aplicarFiltros === 'function') aplicarFiltros();
                        encontrouAlvo = true;
                    }
                    
                    if (!encontrouAlvo) {
                        const fn = Array.from(selFn.options).find(opt => opt.text.toLowerCase().includes(termo));
                        if (fn && fn.value) {
                            selTipo.value = 'interna';
                            selCl.style.display = 'none';
                            selCl.value = '';
                            selFn.style.display = 'block';
                            selFn.value = fn.value;
                            if (typeof aplicarFiltros === 'function') aplicarFiltros();
                        }
                    }
                }
            }

            const linhasRecentes = document.querySelectorAll('#tabela-corpo-recentes tr');
            linhasRecentes.forEach(linha => {
                const texto = linha.textContent.toLowerCase();
                if (texto.includes(termo)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        });

        barraPesquisa.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                const termo = barraPesquisa.value.trim();
                if (termo) {
                    window.location.href = `pedidos.html?q=${encodeURIComponent(termo)}`;
                }
            }
        });
    }

    document.getElementById('btn-recentes-recuar')?.addEventListener('click', () => {
        if (paginaRecentesDashboard > 0) {
            paginaRecentesDashboard -= 1;
            renderizarPedidosRecentes();
        }
    });

    document.getElementById('btn-recentes-avancar')?.addEventListener('click', () => {
        const totalPaginas = Math.ceil(pedidosRecentesDashboard.length / TAMANHO_PAGINA_RECENTES);
        if (paginaRecentesDashboard < totalPaginas - 1) {
            paginaRecentesDashboard += 1;
            renderizarPedidosRecentes();
        }
    });

    if (typeof ArmsTempoReal !== 'undefined') {
        ArmsTempoReal.iniciar('dashboard', (data) => {
            // 1. Atualizar KPIs (5 cartões)
            if (data.kpis) {
                const kpis = data.kpis;
                const elTotal = document.getElementById('kpi-total');
                const elAbertos = document.getElementById('kpi-abertos');
                const elRecebidos = document.getElementById('kpi-recebidos');
                const elVencidos = document.getElementById('kpi-vencidos');
                const elTaxa = document.getElementById('kpi-taxa');

                if (elTotal) elTotal.textContent = kpis.total_pedidos ?? 0;
                if (elAbertos) elAbertos.textContent = kpis.pedidos_abertos ?? 0;
                if (elRecebidos) elRecebidos.textContent = kpis.pedidos_recebidos ?? 0;
                if (elVencidos) elVencidos.textContent = Number(kpis.pedidos_vencidos || 0);
                if (elTaxa) elTaxa.textContent = (kpis.taxa_resposta ?? 0) + '%';

                // Gráfico de barras horizontais: Aceites, Rejeitados, Com Alteração
                const ctxEstados = document.getElementById('grafico-estados');
                if (ctxEstados) {
                    const itensEstados = [
                        { label: 'Aceites', valor: Number(kpis.pedidos_aceites || 0), cor: '#16a34a', href: 'pedidos.html?filtro=ACCEPTED' },
                        { label: 'Rejeitados', valor: Number(kpis.pedidos_rejeitados || 0), cor: '#e11d48', href: 'pedidos.html?filtro=REJECTED' },
                        { label: 'Com Alteração', valor: Number(kpis.pedidos_alteracoes || 0), cor: '#9333ea', href: 'pedidos.html?filtro=CLIENT_RESPONDED' }
                    ];
                    const chaveEstados = `estados:${itensEstados.map(i => i.valor).join('|')}:${ctxEstados.parentElement?.clientWidth || 0}`;
                    animarCanvas(ctxEstados, chaveEstados, (progresso) => {
                        desenharGraficoBarrasHorizontais(ctxEstados, itensEstados, progresso);
                    });
                }
            }

            // 2. Preencher tabela de pedidos recentes
            pedidosRecentesDashboard = Array.isArray(data.recentes) ? data.recentes : [];
            renderizarPedidosRecentes();

            // 3. Gráfico: Pedidos por Mês
            const ctxMeses = document.getElementById('grafico-meses');
            if (ctxMeses && data.por_mes && data.por_mes.length > 0) {
                const labels = data.por_mes.map(m => m.mes_nome);
                const valores = data.por_mes.map(m => Number(m.total || 0));
                const chaveGrafico = `meses:${labels.join('|')}:${valores.join('|')}:${ctxMeses.parentElement?.clientWidth || 0}`;
                animarCanvas(ctxMeses, chaveGrafico, (progresso) => {
                    desenharGraficoBarras(ctxMeses, labels, valores, progresso);
                });
            }

            // 4. Gráfico: Distribuição por Área (Doughnut)
            const ctxArea = document.getElementById('grafico-areas-donut');
            if (ctxArea && data.por_area && data.por_area.length > 0) {
                const areasComDados = data.por_area.filter(a => Number(a.total || 0) > 0);
                if (areasComDados.length > 0) {
                    const labels = areasComDados.map(a => a.area_name);
                    const valores = areasComDados.map(a => Number(a.total || 0));
                    const chaveGrafico = `areas:${labels.join('|')}:${valores.join('|')}:${ctxArea.parentElement?.clientWidth || 0}`;
                    animarCanvas(ctxArea, chaveGrafico, (progresso) => {
                        desenharGraficoDonut(ctxArea, labels, valores, progresso);
                    });
                }
            }
        });
    }

});
