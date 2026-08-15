        const INTERVALO_LISTA_NOTIFICACOES = 3000;
        let listaNotificacoesInicializada = false;
        let ultimaNotificacaoRenderizada = null;

        function escaparHtmlNotif(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function iconeNotificacao(nome) {
            const icones = {
                file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h6"></path>',
                refresh: '<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M3 21v-5h5"></path><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M16 8h5V3"></path>',
                check: '<path d="M20 6 9 17l-5-5"></path>',
                x: '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
                edit: '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
                clock: '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
                message: '<path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>',
                paperclip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>',
                bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>'
            };

            return `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${icones[nome] || icones.bell}</svg>`;
        }

        function atualizarResumoNotificacoes(total, naoLidas) {
            const resumo = document.getElementById('notif-resumo');

            if (total === 0) {
                resumo.textContent = 'Nenhuma notificação por agora.';
                return;
            }

            resumo.textContent = naoLidas > 0
                ? `${naoLidas} notificação${naoLidas > 1 ? 'ées' : ''} nova${naoLidas > 1 ? 's' : ''} de ${total} no total.`
                : `Todas as ${total} notificações foram lidas.`;
        }

        function renderizarNotificacao(n) {
            const lida = Boolean(n.is_read);
            const categoria = escaparHtmlNotif(n.categoria || 'sistema');
            const titulo = escaparHtmlNotif(n.titulo || n.message || 'Notificação');
            const descricao = escaparHtmlNotif(n.descricao || '');
            const etiqueta = escaparHtmlNotif(n.etiqueta || 'Sistema');
            const data = escaparHtmlNotif(n.data_formatada || '');
            const url = escaparHtmlNotif(n.target_url || (n.pedido_ref ? `pedido-detalhe.html?ref=${encodeURIComponent(n.pedido_ref)}` : ''));
            const icone = iconeNotificacao(n.icone || 'bell');

            return `
                <button type="button"
                        class="notif-card ${lida ? '' : 'nao-lida'}"
                        data-id="${escaparHtmlNotif(n.id)}"
                        data-url="${url}"
                        data-lida="${lida ? 'true' : 'false'}"
                        data-categoria="${categoria}"
                        onclick="abrirNotificacao(this)">
                    <span class="notif-icone" aria-hidden="true">${icone}</span>
                    <span class="notif-corpo">
                        <span class="notif-meta">
                            <span class="notif-etiqueta">${etiqueta}</span>
                            <span class="notif-data">${data}</span>
                        </span>
                        <span class="notif-titulo">${titulo}</span>
                        ${descricao ? `<span class="notif-descricao">${descricao}</span>` : ''}
                    </span>
                    <span class="notif-seta" aria-hidden="true">${lida ? '›' : '<span class="notif-ponto"></span>'}</span>
                </button>
            `;
        }

        function carregarNotificacoes(tocarSeNovas = false) {
            const lista = document.getElementById('lista-notificacoes');

            fetch('api/notificacoes.php?acao=listar', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (!data.sucesso) {
                    lista.innerHTML = '<div class="notif-vazia"><strong>Não foi possível carregar.</strong><span>' + escaparHtmlNotif(data.erro || 'Erro ao consultar notificações.') + '</span></div>';
                    return;
                }

                const notifs = Array.isArray(data.dados) ? data.dados : [];
                const naoLidas = Number(data.nao_lidas || 0);
                const maisRecente = notifs[0]?.created_at || null;
                const chegouNova = tocarSeNovas
                    && listaNotificacoesInicializada
                    && naoLidas > 0
                    && maisRecente
                    && maisRecente !== ultimaNotificacaoRenderizada;

                if (chegouNova) {
                    lista.classList.remove('deslizar-cima-isaf');
                    void lista.offsetWidth;
                    lista.classList.add('deslizar-cima-isaf');
                }

                listaNotificacoesInicializada = true;
                ultimaNotificacaoRenderizada = maisRecente;
                atualizarResumoNotificacoes(notifs.length, naoLidas);
                document.getElementById('btn-marcar-todas').style.display = naoLidas > 0 ? 'inline-flex' : 'none';

                if (notifs.length === 0) {
                    lista.innerHTML = `
                        <div class="notif-vazia">
                            ${iconeNotificacao('bell')}
                            <strong>Tudo limpo.</strong>
                            <span>Não existem notificações para apresentar.</span>
                        </div>`;
                    return;
                }

                lista.innerHTML = `<div class="notificacoes-lista">${notifs.map(renderizarNotificacao).join('')}</div>`;
            })
            .catch(err => {
                console.error('Erro notificações:', err);
                lista.innerHTML = '<div class="notif-vazia"><strong>Erro de ligação.</strong><span>O servidor não respondeu ao carregar notificações.</span></div>';
            });
        }

        function abrirNotificacao(elemento) {
            const id = elemento.dataset.id;
            const destino = elemento.dataset.url || '';
            const navegar = (url) => {
                if (url) {
                    window.location.href = url;
                } else {
                    carregarNotificacoes();
                }
            };

            if (!id) {
                navegar(destino);
                return;
            }

            fetch('api/notificacoes.php?acao=marcar_lida', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    elemento.dataset.lida = 'true';
                    elemento.classList.remove('nao-lida');
                    const ponto = elemento.querySelector('.notif-ponto');
                    if (ponto) ponto.remove();

                    if (typeof atualizarContagemNotificacoes === 'function') {
                        atualizarContagemNotificacoes();
                    }

                    navegar(data.target_url || destino);
                    return;
                }

                navegar(destino);
            })
            .catch(() => {
                navegar(destino);
            });
        }

        function marcarLida(id, elemento, ref) {
            elemento.dataset.url = ref ? 'pedido-detalhe.html?ref=' + encodeURIComponent(ref) : (elemento.dataset.url || '');
            abrirNotificacao(elemento);
        }

        function reporBotaoMarcarTodas(btn) {
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;"><polyline points="20 6 9 17 4 12"></polyline></svg>Marcar todas como lidas';
            btn.disabled = false;
        }

        function marcarTodasLidas() {
            const btn = document.getElementById('btn-marcar-todas');
            btn.textContent = 'A marcar...';
            btn.disabled = true;

            fetch('api/notificacoes.php?acao=marcar_todas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    carregarNotificacoes();
                    if (typeof atualizarContagemNotificacoes === 'function') {
                        atualizarContagemNotificacoes();
                    }
                }
            })
            .catch(() => {
                if (typeof mostrarMensagem === 'function') {
                    mostrarMensagem('Erro de ligação', 'Não foi possível marcar as notificações como lidas.');
                }
            })
            .finally(() => {
                reporBotaoMarcarTodas(btn);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            carregarNotificacoes();
            window.setInterval(() => carregarNotificacoes(true), INTERVALO_LISTA_NOTIFICACOES);
            window.addEventListener('arms:notificacoes-novas', () => carregarNotificacoes(true));
        });