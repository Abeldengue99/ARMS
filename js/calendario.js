const ArmsCalendario = (function() {
    'use strict';

    const NOMES_MESES = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    let dataFoco = new Date();
    let dataSelecionada = chaveData(new Date());
    let modo = 'mensal';
    let filtro = '';
    let eventos = [];
    let carregando = false;

    function $(id) {
        return document.getElementById(id);
    }

    function chaveData(data) {
        const ano = data.getFullYear();
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const dia = String(data.getDate()).padStart(2, '0');
        return `${ano}-${mes}-${dia}`;
    }

    function dataDeChave(chave) {
        const [ano, mes, dia] = chave.split('-').map(Number);
        return new Date(ano, mes - 1, dia);
    }

    function adicionarDias(data, quantidade) {
        const copia = new Date(data);
        copia.setDate(copia.getDate() + quantidade);
        return copia;
    }

    function inicioDaSemana(data) {
        return adicionarDias(data, -data.getDay());
    }

    function fimDaSemana(data) {
        return adicionarDias(inicioDaSemana(data), 6);
    }

    function inicioDoMesVisivel(data) {
        const primeiro = new Date(data.getFullYear(), data.getMonth(), 1);
        return inicioDaSemana(primeiro);
    }

    function fimDoMesVisivel(data) {
        const ultimo = new Date(data.getFullYear(), data.getMonth() + 1, 0);
        return fimDaSemana(ultimo);
    }

    function intervaloVisivel() {
        if (modo === 'semanal') {
            return {
                inicio: chaveData(inicioDaSemana(dataFoco)),
                fim: chaveData(fimDaSemana(dataFoco))
            };
        }

        return {
            inicio: chaveData(inicioDoMesVisivel(dataFoco)),
            fim: chaveData(fimDoMesVisivel(dataFoco))
        };
    }

    function escaparHtml(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function formatarDataLonga(chave) {
        const data = dataDeChave(chave);
        return `${DIAS_SEMANA[data.getDay()]}, ${String(data.getDate()).padStart(2, '0')} de ${NOMES_MESES[data.getMonth()]} de ${data.getFullYear()}`;
    }

    function textoPeriodo() {
        if (modo === 'semanal') {
            const inicio = inicioDaSemana(dataFoco);
            const fim = fimDaSemana(dataFoco);
            if (inicio.getMonth() === fim.getMonth()) {
                return `${String(inicio.getDate()).padStart(2, '0')} - ${String(fim.getDate()).padStart(2, '0')} ${NOMES_MESES[fim.getMonth()]}, ${fim.getFullYear()}`;
            }
            return `${String(inicio.getDate()).padStart(2, '0')} ${NOMES_MESES[inicio.getMonth()]} - ${String(fim.getDate()).padStart(2, '0')} ${NOMES_MESES[fim.getMonth()]}, ${fim.getFullYear()}`;
        }

        return `${NOMES_MESES[dataFoco.getMonth()]}, ${dataFoco.getFullYear()}`;
    }

    function textoFiltroAtual() {
        const select = $('filtro-calendario');
        if (!select) return 'Todos os eventos';

        return select.options[select.selectedIndex]?.textContent || 'Todos os eventos';
    }

    function eventoVisivel(evento) {
        if (!filtro) return true;
        if (filtro === 'decisoes') return ['aceite', 'rejeitado', 'alteracao'].includes(evento.categoria);
        if (filtro === 'movimentos') return ['enviado', 'recebido', 'estado'].includes(evento.categoria);
        if (filtro === 'prazos') return ['deadline', 'urgente'].includes(evento.categoria);
        return evento.categoria === filtro || evento.tipo === filtro;
    }

    function eventosFiltrados() {
        return eventos.filter(eventoVisivel);
    }

    function eventosPorDia() {
        const mapa = new Map();
        eventosFiltrados().forEach((evento) => {
            if (!mapa.has(evento.data)) mapa.set(evento.data, []);
            mapa.get(evento.data).push(evento);
        });

        mapa.forEach((lista) => {
            lista.sort((a, b) => (a.prioridade - b.prioridade) || a.inicio.localeCompare(b.inicio));
        });

        return mapa;
    }

    function abrirEvento(eventoId) {
        const evento = eventos.find((item) => item.id === eventoId);
        if (evento && evento.url) {
            window.location.href = evento.url;
        }
    }

    function renderizarEvento(evento, compacto = true) {
        const titulo = compacto && evento.titulo.length > 42
            ? evento.titulo.slice(0, 39) + '...'
            : evento.titulo;

        return `
            <button type="button"
                    class="calendario-evento"
                    data-categoria="${escaparHtml(evento.categoria)}"
                    onclick="event.stopPropagation(); ArmsCalendario.abrirEvento('${escaparHtml(evento.id)}')">
                ${escaparHtml(evento.hora)} · ${escaparHtml(titulo)}
                <small>${escaparHtml(evento.area || evento.cliente || evento.referencia)}</small>
            </button>
        `;
    }

    function renderizarCabecalhoSemana() {
        return `<div class="calendario-semana-cabecalho">${DIAS_SEMANA.map((dia) => `<span>${dia}</span>`).join('')}</div>`;
    }

    function renderizarMensal() {
        const mapa = eventosPorDia();
        const inicio = inicioDoMesVisivel(dataFoco);
        const hoje = chaveData(new Date());
        const dias = [];

        for (let i = 0; i < 42; i += 1) {
            const data = adicionarDias(inicio, i);
            const chave = chaveData(data);
            const lista = mapa.get(chave) || [];
            const foraMes = data.getMonth() !== dataFoco.getMonth();
            const classes = [
                'calendario-dia',
                foraMes ? 'fora-mes' : '',
                chave === hoje ? 'hoje' : '',
                chave === dataSelecionada ? 'selecionado' : ''
            ].filter(Boolean).join(' ');

            dias.push(`
                <div class="${classes}" onclick="ArmsCalendario.selecionarDia('${chave}')">
                    <div class="calendario-dia-topo">
                        <span class="calendario-dia-numero">${data.getDate()}</span>
                        ${lista.length ? `<span class="calendario-dia-contador">${lista.length}</span>` : ''}
                    </div>
                    <div class="calendario-eventos">
                        ${lista.slice(0, 3).map((evento) => renderizarEvento(evento)).join('')}
                        ${lista.length > 3 ? `<span class="calendario-mais">+${lista.length - 3} eventos</span>` : ''}
                    </div>
                </div>
            `);
        }

        $('calendario-corpo').innerHTML = renderizarCabecalhoSemana() + `<div class="calendario-grid">${dias.join('')}</div>`;
    }

    function renderizarSemanal() {
        const mapa = eventosPorDia();
        const inicio = inicioDaSemana(dataFoco);
        const hoje = chaveData(new Date());
        const colunas = [];

        for (let i = 0; i < 7; i += 1) {
            const data = adicionarDias(inicio, i);
            const chave = chaveData(data);
            const lista = mapa.get(chave) || [];
            const classes = [
                'calendario-semanal-dia',
                chave === hoje ? 'hoje' : '',
                chave === dataSelecionada ? 'selecionado' : ''
            ].filter(Boolean).join(' ');

            colunas.push(`
                <div class="${classes}" onclick="ArmsCalendario.selecionarDia('${chave}')">
                    <h3>${DIAS_SEMANA[data.getDay()]} · ${String(data.getDate()).padStart(2, '0')}</h3>
                    <div class="calendario-eventos">
                        ${lista.length ? lista.map((evento) => renderizarEvento(evento, false)).join('') : '<div class="calendario-vazio">Sem eventos.</div>'}
                    </div>
                </div>
            `);
        }

        $('calendario-corpo').innerHTML = `<div class="calendario-semanal">${colunas.join('')}</div>`;
    }

    function renderizarPainelDia() {
        const lista = eventosFiltrados()
            .filter((evento) => evento.data === dataSelecionada)
            .sort((a, b) => (a.prioridade - b.prioridade) || a.inicio.localeCompare(b.inicio));

        $('calendario-dia-titulo').textContent = formatarDataLonga(dataSelecionada);
        $('calendario-dia-resumo').textContent = lista.length
            ? `${lista.length} evento${lista.length > 1 ? 's' : ''} neste dia.`
            : 'Sem eventos para este dia.';

        $('calendario-lista-dia').innerHTML = lista.length
            ? lista.map((evento) => renderizarEvento(evento, false)).join('')
            : '<div class="calendario-vazio">Escolhe outro dia ou altera o filtro.</div>';
    }

    function renderizarResumo() {
        const lista = eventosFiltrados();
        const totalUrgentes = lista.filter((evento) => evento.categoria === 'urgente').length;
        const totalPrazos = lista.filter((evento) => ['deadline', 'urgente'].includes(evento.categoria)).length;
        const totalDecisoes = lista.filter((evento) => ['aceite', 'rejeitado', 'alteracao'].includes(evento.categoria)).length;

        $('calendario-kpi-total').textContent = lista.length;
        $('calendario-kpi-prazos').textContent = totalPrazos;
        $('calendario-kpi-urgentes').textContent = totalUrgentes;
        $('calendario-kpi-decisoes').textContent = totalDecisoes;
    }

    function renderizar() {
        $('calendario-periodo').textContent = textoPeriodo();
        $('btn-modo-mensal').classList.toggle('ativo', modo === 'mensal');
        $('btn-modo-semanal').classList.toggle('ativo', modo === 'semanal');

        if (carregando) {
            $('calendario-corpo').innerHTML = '<div class="calendario-carregando">A carregar calendário...</div>';
            return;
        }

        if (modo === 'mensal') renderizarMensal();
        else renderizarSemanal();

        renderizarResumo();
        renderizarPainelDia();
    }

    function carregarEventos() {
        const intervalo = intervaloVisivel();
        carregando = true;
        renderizar();

        return fetch(`api/calendario.php?acao=listar&inicio=${intervalo.inicio}&fim=${intervalo.fim}`, { cache: 'no-store' })
            .then((res) => res.json())
            .then((data) => {
                if (!data.sucesso) {
                    throw new Error(data.erro || 'Erro ao carregar calendário.');
                }

                eventos = Array.isArray(data.eventos) ? data.eventos : [];
            })
            .catch((erro) => {
                console.error('Erro calendário:', erro);
                eventos = [];
                $('calendario-corpo').innerHTML = '<div class="calendario-carregando">Não foi possível carregar o calendário.</div>';
            })
            .finally(() => {
                carregando = false;
                renderizar();
            });
    }

    function navegar(direcao) {
        if (modo === 'mensal') {
            dataFoco = new Date(dataFoco.getFullYear(), dataFoco.getMonth() + direcao, 1);
        } else {
            dataFoco = adicionarDias(dataFoco, direcao * 7);
        }

        dataSelecionada = chaveData(dataFoco);
        carregarEventos();
    }

    function selecionarDia(chave) {
        dataSelecionada = chave;
        renderizar();
    }

    function mudarModo(novoModo) {
        if (modo === novoModo) return;
        modo = novoModo;
        carregarEventos();
    }

    function irHoje() {
        dataFoco = new Date();
        dataSelecionada = chaveData(dataFoco);
        carregarEventos();
    }

    function vincularCalendario() {
        const intervalo = intervaloVisivel();
        window.location.href = `api/calendario.php?acao=ics&inicio=${intervalo.inicio}&fim=${intervalo.fim}`;
    }

    function baixarPDFCalendario() {
        const linhas = eventosFiltrados()
            .slice()
            .sort((a, b) => a.inicio.localeCompare(b.inicio));

        if (!linhas.length) {
            if (typeof mostrarMensagem === 'function') {
                mostrarMensagem('Calendário sem eventos', 'Não existem eventos para exportar no período e filtro selecionados.');
            } else {
                alert('Não existem eventos para exportar no período e filtro selecionados.');
            }
            return;
        }

        const opcoes = {
            titulo: 'Calendário de Pedidos',
            subtitulo: `ARMS - ${textoPeriodo()}`,
            nomeArquivo: 'calendario-arms',
            filtros: {
                Período: textoPeriodo(),
                Visualização: modo === 'mensal' ? 'Mensal' : 'Semanal',
                Filtro: textoFiltroAtual()
            },
            colunas: [
                { titulo: 'Data', valor: (evento) => formatarDataLonga(evento.data) },
                { titulo: 'Hora', chave: 'hora' },
                { titulo: 'Tipo', valor: (evento) => evento.categoria || evento.tipo },
                { titulo: 'Pedido', chave: 'referencia' },
                { titulo: 'Evento', chave: 'titulo' },
                { titulo: 'Cliente', chave: 'cliente' },
                { titulo: 'Área', chave: 'area' },
                { titulo: 'Descrição', chave: 'descricao' }
            ],
            linhas
        };

        if (window.ArmsExportacoes && typeof ArmsExportacoes.baixarPDF === 'function') {
            ArmsExportacoes.baixarPDF(opcoes);
            return;
        }

        window.print();
    }

    function iniciar() {
        $('btn-calendario-anterior').addEventListener('click', () => navegar(-1));
        $('btn-calendario-proximo').addEventListener('click', () => navegar(1));
        $('btn-calendario-hoje').addEventListener('click', irHoje);
        $('btn-modo-mensal').addEventListener('click', () => mudarModo('mensal'));
        $('btn-modo-semanal').addEventListener('click', () => mudarModo('semanal'));
        $('btn-vincular-calendario').addEventListener('click', vincularCalendario);
        $('btn-baixar-pdf-calendario').addEventListener('click', baixarPDFCalendario);
        $('filtro-calendario').addEventListener('change', (evento) => {
            filtro = evento.target.value;
            renderizar();
        });

        carregarEventos();
    }

    return {
        iniciar,
        abrirEvento,
        selecionarDia,
        baixarPDFCalendario
    };
})();

document.addEventListener('DOMContentLoaded', ArmsCalendario.iniciar);
