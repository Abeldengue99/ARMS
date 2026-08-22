
        let pedidoAtual = null;
        let usuarioId = null;
        let comentariosAtuais = [];
        let anexosAtuais = [];
        let documentoMaxMb = 50;
        try { const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}'); usuarioId = ud.id || null; } catch(e) {}

        const COMENTARIO_CACHE_PREFIXO = 'arms:comentario-rascunho:v1:';
        const COMENTARIO_EDICAO_CACHE_PREFIXO = 'arms:comentario-edicao:v1:';
        let comentarioCacheTtlMs = 48 * 60 * 60 * 1000;
        let comentarioCacheTimer = null;
        let comentarioCacheInfoTimer = null;
        let comentarioCacheDesativado = false;

        function normalizarChaveCache(valor) {
            return encodeURIComponent(String(valor || 'sem-valor'));
        }

        function chaveCacheComentario(ref = null) {
            const referencia = ref || (pedidoAtual && pedidoAtual.reference) || 'sem-pedido';
            return COMENTARIO_CACHE_PREFIXO + normalizarChaveCache(usuarioId || 'anonimo') + ':' + normalizarChaveCache(referencia);
        }

        function chaveCacheEdicaoComentario(id) {
            return COMENTARIO_EDICAO_CACHE_PREFIXO + normalizarChaveCache(usuarioId || 'anonimo') + ':' + normalizarChaveCache(id);
        }

        function lerCacheJson(chave) {
            try {
                const bruto = localStorage.getItem(chave);
                if (!bruto) return null;

                const dados = JSON.parse(bruto);
                if (!dados || !dados.expira_em || Date.now() > dados.expira_em) {
                    localStorage.removeItem(chave);
                    return null;
                }

                return dados;
            } catch (e) {
                localStorage.removeItem(chave);
                return null;
            }
        }

        function gravarCacheJson(chave, corpo, extras = {}) {
            const body = String(corpo || '');

            if (!body.trim()) {
                localStorage.removeItem(chave);
                return false;
            }

            try {
                localStorage.setItem(chave, JSON.stringify({
                    ...extras,
                    body,
                    user_id: usuarioId || null,
                    guardado_em: new Date().toISOString(),
                    expira_em: Date.now() + comentarioCacheTtlMs
                }));
                return true;
            } catch (e) {
                return false;
            }
        }

        function listarChavesLocalStorage() {
            const chaves = [];
            for (let i = 0; i < localStorage.length; i++) {
                const chave = localStorage.key(i);
                if (chave) chaves.push(chave);
            }
            return chaves;
        }

        function limparCachesExpiradosComentarios() {
            const prefixos = [COMENTARIO_CACHE_PREFIXO, COMENTARIO_EDICAO_CACHE_PREFIXO];

            try {
                listarChavesLocalStorage().forEach((chave) => {
                    if (!prefixos.some((prefixo) => chave.startsWith(prefixo))) return;
                    lerCacheJson(chave);
                });
            } catch (e) {}
        }

        function limparCachesComentarioDoUsuario() {
            comentarioCacheDesativado = true;
            const usuarioAtual = usuarioId || null;
            const prefixos = [COMENTARIO_CACHE_PREFIXO, COMENTARIO_EDICAO_CACHE_PREFIXO];

            try {
                listarChavesLocalStorage().forEach((chave) => {
                    if (!prefixos.some((prefixo) => chave.startsWith(prefixo))) return;
                    const dados = lerCacheJson(chave);
                    if (!dados || String(dados.user_id || '') === String(usuarioAtual || '')) {
                        localStorage.removeItem(chave);
                    }
                });
            } catch (e) {}
        }

        function mostrarEstadoCacheComentario(mensagem) {
            const info = document.getElementById('comentario-cache-info');
            if (!info) return;

            info.textContent = mensagem || '';
            clearTimeout(comentarioCacheInfoTimer);

            if (mensagem) {
                comentarioCacheInfoTimer = setTimeout(() => {
                    info.textContent = '';
                }, 4500);
            }
        }

        function restaurarRascunhoComentario(ref) {
            const textarea = document.getElementById('novo-comentario');
            if (!textarea || textarea.value.trim()) return;

            const cache = lerCacheJson(chaveCacheComentario(ref));
            if (cache && cache.body) {
                textarea.value = cache.body;
                mostrarEstadoCacheComentario('Rascunho recuperado. Este texto estava guardado apenas neste navegador.');
            }
        }

        function guardarRascunhoComentario() {
            const textarea = document.getElementById('novo-comentario');
            if (comentarioCacheDesativado || !textarea || !pedidoAtual) return;

            const gravado = gravarCacheJson(chaveCacheComentario(pedidoAtual.reference), textarea.value, {
                request_reference: pedidoAtual.reference
            });

            const horas = Math.round(comentarioCacheTtlMs / (60 * 60 * 1000));
            mostrarEstadoCacheComentario(gravado ? `Rascunho guardado localmente por ${horas} horas.` : '');
        }

        function limparRascunhoComentario(ref = null) {
            localStorage.removeItem(chaveCacheComentario(ref));
            mostrarEstadoCacheComentario('');
        }

        function configurarCacheNovoComentario() {
            const textarea = document.getElementById('novo-comentario');
            if (!textarea || textarea.dataset.cacheConfigurado === '1') return;

            textarea.dataset.cacheConfigurado = '1';
            textarea.addEventListener('input', () => {
                clearTimeout(comentarioCacheTimer);
                comentarioCacheTimer = setTimeout(guardarRascunhoComentario, 350);
            });
            window.addEventListener('beforeunload', guardarRascunhoComentario);
        }

        function obterCorpoEdicaoComentario(id, corpoOriginal) {
            const cache = lerCacheJson(chaveCacheEdicaoComentario(id));
            return cache && cache.body ? cache.body : corpoOriginal;
        }

        function configurarCacheEdicaoComentario(id) {
            const textarea = document.getElementById('edit-comentario-body');
            if (!textarea) return;

            textarea.addEventListener('input', () => {
                gravarCacheJson(chaveCacheEdicaoComentario(id), textarea.value, {
                    comment_id: id,
                    request_reference: pedidoAtual ? pedidoAtual.reference : null
                });
            });
        }

        function descartarCacheEdicaoComentario(id) {
            localStorage.removeItem(chaveCacheEdicaoComentario(id));
        }

        const escaparHtmlPedido = (valor) => String(valor ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));

        const valorPedido = (valor) => {
            const texto = String(valor ?? '').trim();
            return texto ? texto : 'Não informado';
        };

        const valorAtivoPedido = (valor) => valor === true ||
            valor === 1 ||
            ['1', 't', 'true', 'sim', 'yes'].includes(String(valor ?? '').trim().toLowerCase());

        function iniciarCarregamentoPedido() {
            if (pedidoAtual) return;
            const loading = document.getElementById('pedido-loading');
            const conteudo = document.getElementById('pedido-conteudo-real');
            if (loading) {
                loading.className = 'card pedido-loading-card';
                loading.innerHTML = '<span class="pedido-loading-spinner" aria-hidden="true"></span><span>A carregar detalhes do pedido...</span>';
                loading.hidden = false;
            }
            if (conteudo) conteudo.hidden = true;
        }

        function mostrarConteudoPedido() {
            const loading = document.getElementById('pedido-loading');
            const conteudo = document.getElementById('pedido-conteudo-real');
            if (loading) loading.hidden = true;
            if (conteudo) conteudo.hidden = false;
        }

        function mostrarErroPedido(mensagem) {
            const loading = document.getElementById('pedido-loading');
            const conteudo = document.getElementById('pedido-conteudo-real');
            if (conteudo) conteudo.hidden = true;
            if (!loading) return;

            loading.hidden = false;
            loading.className = 'card pedido-loading-card pedido-erro-card';
            loading.innerHTML = `<span style="color: var(--cor-perigo); font-weight: 800;">Atenção</span><span>${escaparHtmlPedido(mensagem)}</span>`;
        }

        function formatarBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function atualizarLimiteAnexoInfo() {
            const info = document.getElementById('limite-anexo-info');
            if (info) {
                info.textContent = `Máx ${documentoMaxMb}MB por arquivo`;
            }
        }

        function ficheiroExcedeLimite(arquivo) {
            return arquivo && arquivo.size > documentoMaxMb * 1024 * 1024;
        }

        function obterEventosTimelineVisiveis(data, isReceiver) {
            const eventos = Array.isArray(data) ? data : [];
            if (!isReceiver) {
                return eventos;
            }

            const temRespostaFinal = eventos.some((evt) => ['ACCEPTED', 'REJECTED'].includes(evt.to_status));

            return eventos.filter((evt) => {
                if (evt.to_status === 'DRAFT') return false;
                if (evt.to_status === 'SENT') return true; // O destinatário deve ver quem e quando enviou
                if (evt.to_status === 'RECEIVED') return false;
                if (evt.to_status === 'ACCEPTED' || evt.to_status === 'REJECTED') return true;
                return evt.to_status === 'CLIENT_RESPONDED' && !temRespostaFinal;
            });
        }

        function decisaoResposta(valor) {
            return String(valor || '').trim().toUpperCase();
        }

        function pedidoComAlteracaoSolicitada(pedido) {
            return pedido &&
                pedido.status === 'CLIENT_RESPONDED' &&
                decisaoResposta(pedido.latest_response_decision) === 'PENDING';
        }

        function rotuloStatusTimeline(evt, isClientRole = false) {
            const status = evt && evt.to_status ? evt.to_status : 'Atualização';
            return String(status).toUpperCase();
        }

        function descricaoTimeline(evt, isClientRole, isReceiver) {
            const nome = escaparHtmlPedido(valorPedido(evt.actor_name));
            const dataHora = escaparHtmlPedido(valorPedido(evt.data_hora));

            if (evt.to_status === 'SENT') {
                return 'Enviado por ' + nome + ' em ' + dataHora;
            }

            if (evt.to_status === 'RECEIVED') {
                return 'Visualizado por ' + nome + ' em ' + dataHora;
            }

            if (evt.to_status === 'CLIENT_RESPONDED') {
                const decisao = decisaoResposta(evt.response_decision);
                if (decisao === 'PENDING') {
                    return nome + ' solicitou alteração em ' + dataHora;
                }

                return 'Resposta registada por ' + nome + ' em ' + dataHora;
            }

            if (evt.to_status === 'ACCEPTED') {
                return nome + ' aceitou oficialmente o pedido em ' + dataHora;
            }

            if (evt.to_status === 'REJECTED') {
                return nome + ' rejeitou o pedido em ' + dataHora;
            }

            if (evt.to_status === 'DRAFT') {
                return 'Criado por ' + nome + ' em ' + dataHora;
            }

            return nome + ' em ' + dataHora;
        }

        function rotuloStatusPedido(status, timeline, pedido = null, isClientRole = false) {
            if (status !== 'CLIENT_RESPONDED') {
                return status;
            }

            if (pedidoComAlteracaoSolicitada(pedido)) {
                return 'Alteration Requested';
            }

            if (pedido && decisaoResposta(pedido.latest_response_actor_type) === 'AKSANTI') {
                return 'Respondido pela Aksanti';
            }

            const eventos = Array.isArray(timeline) ? timeline : [];
            const eventoResposta = [...eventos].reverse().find((evt) => evt.to_status === 'CLIENT_RESPONDED');
            if (eventoResposta && eventoResposta.actor_type === 'AKSANTI') {
                return 'Respondido pela Aksanti';
            }

            return 'Resposta recebida';
        }

        function renderTimeline(data, isReceiver = false, isClientRole = false) {
            const container = document.getElementById('timeline-container');
            const eventos = obterEventosTimelineVisiveis(data, isReceiver);

            if (!eventos.length) {
                container.innerHTML = '<p style="color: var(--texto-secundario);">Sem eventos</p>';
                return;
            }

            let html = '<div class="timeline-container">';
            eventos.forEach(evt => {
                html += '<div class="timeline-item"><div class="timeline-dot"></div><strong>' + escaparHtmlPedido(rotuloStatusTimeline(evt, isClientRole)) + '</strong><p style="margin:4px 0; font-size:0.9rem; color:#666;">' + descricaoTimeline(evt, isClientRole, isReceiver) + '</p></div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function renderComentarios(data) {
            const container = document.getElementById('lista-comentarios');
            comentariosAtuais = Array.isArray(data) ? data : [];

            if (!data || data.length === 0) {
                container.innerHTML = '<p style="color: var(--texto-secundario);">Nenhum comentário</p>';
                return;
            }

            let html = '';
            data.forEach(c => {
                const auditoria = Number(c.edit_count || 0) > 0
                    ? '<span class="comentario-auditoria">Editado por ' + escaparHtmlPedido(valorPedido(c.edited_by_name)) + ' em ' + escaparHtmlPedido(valorPedido(c.edited_at)) + '</span>'
                    : '';
                const acoes = c.can_edit
                    ? '<button type="button" class="btn-link-detalhe" onclick="abrirEdicaoComentario(\'' + escaparHtmlPedido(c.id) + '\')">Editar</button>'
                    : '';

                html += '<div class="comentario-box">' +
                    '<div class="comentario-topo"><strong>' + escaparHtmlPedido(valorPedido(c.author_name)) + '</strong><div class="comentario-acoes">' + acoes + '</div></div>' +
                    '<p style="margin:4px 0; font-size:0.9rem; word-break:break-word;">' + escaparHtmlPedido(c.body || '') + '</p>' +
                    '<div class="comentario-meta">Criado em ' + escaparHtmlPedido(valorPedido(c.data_hora)) + auditoria + '</div>' +
                    '</div>';
            });
            container.innerHTML = html;
        }

        function renderAnexos(data) {
            const container = document.getElementById('lista-anexos');
            anexosAtuais = Array.isArray(data) ? data : [];

            if (!data || data.length === 0) {
                container.innerHTML = '<p style="color: var(--texto-secundario);">Nenhum anexo</p>';
                return;
            }

            let html = '';
            data.forEach(a => {
                const auditoria = Number(a.update_count || 0) > 0
                    ? '<span class="anexo-auditoria">Atualizado por ' + escaparHtmlPedido(valorPedido(a.updated_by_name)) + ' em ' + escaparHtmlPedido(valorPedido(a.updated_at)) + '</span>'
                    : '';
                const botaoAtualizar = a.can_update
                    ? '<button type="button" class="btn-link-detalhe" onclick="abrirAtualizacaoAnexo(\'' + escaparHtmlPedido(a.id) + '\')">Atualizar</button>'
                    : '';

                html += '<div class="anexo-box">' +
                    '<div><div class="anexo-topo"><strong>' + escaparHtmlPedido(valorPedido(a.file_name)) + '</strong></div>' +
                    '<div class="anexo-meta">' + escaparHtmlPedido(formatarBytes(a.size_bytes || 0)) + ' enviado por ' + escaparHtmlPedido(valorPedido(a.uploaded_by_name)) + ' em ' + escaparHtmlPedido(valorPedido(a.data_hora)) + auditoria + '</div></div>' +
                    '<div class="anexo-acoes"><a href="api/anexo-download.php?id=' + encodeURIComponent(a.id) + '" class="btn-link-detalhe">Baixar</a>' + botaoAtualizar + '</div>' +
                    '</div>';
            });
            container.innerHTML = html;
        }

        function abrirEdicaoComentario(id) {
            const comentario = comentariosAtuais.find((item) => String(item.id) === String(id));
            if (!comentario) {
                mostrarMensagem('Atenção', 'Comentário não encontrado nesta tela.');
                return;
            }

            const corpoInicial = obterCorpoEdicaoComentario(id, comentario.body || '');
            const html = `
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario" for="edit-comentario-body">Comentário</label>
                    <textarea id="edit-comentario-body" class="input-controlo" rows="5">${escaparHtmlPedido(corpoInicial)}</textarea>
                </div>
                <p style="color: var(--texto-secundario); font-size: 0.9rem; line-height: 1.5; margin: 12px 0 0;">Esta edição ficará marcada no histórico do comentário para manter a auditoria transparente.</p>
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button class="btn btn-secundario" onclick="descartarCacheEdicaoComentario('${escaparHtmlPedido(id)}'); fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-guardar-comentario-editado">Guardar Alteração</button>
                </div>
            `;

            abrirModal('Editar Comentário', html, { largura: '560px' });
            configurarCacheEdicaoComentario(id);
            document.getElementById('btn-guardar-comentario-editado').addEventListener('click', () => guardarComentarioEditado(id));
        }

        function guardarComentarioEditado(id) {
            const body = document.getElementById('edit-comentario-body').value.trim();
            const btn = document.getElementById('btn-guardar-comentario-editado');

            if (!body) {
                mostrarMensagem('Atenção', 'O comentário não pode ficar vazio.');
                return;
            }

            btn.textContent = 'A guardar...';
            btn.disabled = true;

            fetch('api/comentario-editar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, body })
            })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.sucesso) {
                        btn.textContent = 'Guardar Alteração';
                        btn.disabled = false;
                        mostrarMensagem('Atenção', data.erro || 'Erro ao atualizar o comentário.');
                        return;
                    }

                    descartarCacheEdicaoComentario(id);
                    fecharModal();
                    carregarPedido(pedidoAtual.reference);
                })
                .catch(() => {
                    btn.textContent = 'Guardar Alteração';
                    btn.disabled = false;
                    mostrarMensagem('Atenção', 'Erro de ligação ao servidor.');
                });
        }

        function abrirAtualizacaoAnexo(id) {
            const anexo = anexosAtuais.find((item) => String(item.id) === String(id));
            if (!anexo) {
                mostrarMensagem('Atenção', 'Anexo não encontrado nesta tela.');
                return;
            }

            const html = `
                <p style="color: var(--texto-secundario); line-height: 1.5; margin: 0 0 16px;">Ficheiro atual: <strong>${escaparHtmlPedido(valorPedido(anexo.file_name))}</strong></p>
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario" for="atualizar-anexo-arquivo">Novo ficheiro</label>
                    <input type="file" id="atualizar-anexo-arquivo" class="input-controlo">
                </div>
                <p style="color: var(--texto-secundario); font-size: 0.9rem; line-height: 1.5; margin: 12px 0 0;">O ficheiro será substituído, mas a tela ficará marcada com quem atualizou e quando atualizou.</p>
                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                    <button class="btn btn-primario" id="btn-atualizar-anexo">Atualizar Ficheiro</button>
                </div>
            `;

            abrirModal('Atualizar Ficheiro', html, { largura: '560px' });
            document.getElementById('btn-atualizar-anexo').addEventListener('click', () => atualizarAnexo(id));
        }

        function atualizarAnexo(id) {
            const input = document.getElementById('atualizar-anexo-arquivo');
            const btn = document.getElementById('btn-atualizar-anexo');
            const arquivo = input && input.files ? input.files[0] : null;

            if (!arquivo) {
                mostrarMensagem('Atenção', 'Selecione o novo ficheiro.');
                return;
            }

            if (ficheiroExcedeLimite(arquivo)) {
                mostrarMensagem('Atenção', `O ficheiro não pode ter mais de ${documentoMaxMb}MB.`);
                return;
            }

            const formData = new FormData();
            formData.append('id', id);
            formData.append('arquivo', arquivo);

            btn.textContent = 'A atualizar...';
            btn.disabled = true;

            fetch('api/anexo-atualizar.php', { method: 'POST', body: formData })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.sucesso) {
                        btn.textContent = 'Atualizar Ficheiro';
                        btn.disabled = false;
                        mostrarMensagem('Atenção', data.erro || 'Erro ao atualizar o ficheiro.');
                        return;
                    }

                    fecharModal();
                    carregarPedido(pedidoAtual.reference);
                })
                .catch(() => {
                    btn.textContent = 'Atualizar Ficheiro';
                    btn.disabled = false;
                    mostrarMensagem('Atenção', 'Erro de ligação ao servidor.');
                });
        }

        function renderRespostasFormais(data, isClientRole = false) {
            const container = document.getElementById('historico-respostas');
            if (!data || data.length === 0) {
                container.style.display = 'none';
                return;
            }
            container.style.display = 'block';
            let html = '<h3 class="titulo-secao" style="color: var(--aksanti-gold);">📋 Histórico de Decisões</h3>';
            data.forEach(r => {
                let cor = '#f59e0b';
                let txt = 'Informação registada';
                if (r.status_decision === 'PENDING') { cor = '#f59e0b'; txt = isClientRole ? 'Solicitaste alteração' : 'Solicitou alteração'; }
                if (r.status_decision === 'ACCEPTED') { cor = '#10b981'; txt = isClientRole ? 'Aceitaste oficialmente' : 'Aceitou oficialmente'; }
                if (r.status_decision === 'REJECTED') { cor = '#ef4444'; txt = isClientRole ? 'Rejeitaste' : 'Rejeitou'; }

                html += `<div style="padding: 16px; background: #fff; border: 1px solid #e4e4e7; border-left: 4px solid ${cor}; border-radius: 6px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <strong>${escaparHtmlPedido(valorPedido(r.responded_by_name))} <span style="color: ${cor}; margin-left: 8px;">(${txt})</span></strong>
                                <small style="color: #999;">${escaparHtmlPedido(valorPedido(r.data_hora))}</small>
                            </div>
                            <p style="margin: 0; font-size: 0.95rem; color: #444;">${r.message ? escaparHtmlPedido(r.message) : '<i>Sem comentários adicionais.</i>'}</p>
                         </div>`;
            });
            container.innerHTML = html;
        }

        function submeterDecisao(decisaoStr) {
            const textosDecisao = {
                ACCEPTED: {
                    titulo: 'Aceitar Pedido',
                    confirmacao: 'Tem a certeza de que deseja aceitar este pedido?',
                    sucesso: 'Pedido aceite com sucesso.'
                },
                REJECTED: {
                    titulo: 'Rejeitar Pedido',
                    confirmacao: 'Tem a certeza de que deseja rejeitar este pedido?',
                    sucesso: 'Pedido rejeitado com sucesso.'
                },
                PENDING: {
                    titulo: 'Solicitar Alteração',
                    confirmacao: 'Tem a certeza de que deseja solicitar alteração neste pedido?',
                    sucesso: 'Alteração solicitada com sucesso.'
                }
            };
            const textoDecisao = textosDecisao[decisaoStr] || {
                titulo: 'Confirmar Decisão',
                confirmacao: 'Tem a certeza de que deseja submeter esta decisão formalá',
                sucesso: 'Decisão registada com sucesso.'
            };

            confirmarAcao(textoDecisao.titulo, textoDecisao.confirmacao, () => {
                const msg = document.getElementById('texto-decisao').value.trim();

                fetch('api/sessao.php?acao=verificar', {
                    cache: 'no-store',
                    credentials: 'include'
                })
                .then(r => r.json())
                .then(sessao => {
                    if (!sessao.sucesso || !sessao.autenticado) {
                        throw new Error('Sessão expirada. Inicie sessão novamente antes de responder ao pedido.');
                    }

                    return fetch('api/pedido-responder.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reference: pedidoAtual.reference, decisao: decisaoStr, mensagem: msg })
                    });
                })
                .then(r => r.text())
                .then(textoResposta => {
                    const inicioJson = textoResposta.indexOf('{');
                    const fimJson = textoResposta.lastIndexOf('}');
                    if (inicioJson === -1 || fimJson === -1 || fimJson < inicioJson) {
                        throw new Error('Resposta inválida do servidor.');
                    }

                    return JSON.parse(textoResposta.substring(inicioJson, fimJson + 1));
                })
                .then(data => {
                    if (data.sucesso) {
                        mostrarMensagem('Sucesso', data.mensagem || textoDecisao.sucesso, {
                            aoFechar: () => window.location.reload()
                        });
                    } else {
                        mostrarMensagem('Erro de Sistema', data.erro || 'Não foi possível registar a decisão.');
                    }
                })
                .catch(err => mostrarMensagem('Erro de Sistema', err.message || 'Não foi possível registar a decisão.'));
            });
        }

        function carregarPedido(ref) {
            iniciarCarregamentoPedido();

            fetch('api/pedido-detalhe.php?ref=' + encodeURIComponent(ref))
                .then(r => r.json())
                .then(data => {
                    if (!data.sucesso) {
                        mostrarErroPedido(data.erro || 'Não foi possível carregar os detalhes do pedido.');
                        return;
                    }
                    const p = data.dados;
                    pedidoAtual = p;
                    documentoMaxMb = Number(data.configuracoes?.attachment_max_size_mb || 50);
                    comentarioCacheTtlMs = Math.max(1, Number(data.configuracoes?.comment_draft_cache_days || 2)) * 24 * 60 * 60 * 1000;
                    atualizarLimiteAnexoInfo();
                    restaurarRascunhoComentario(p.reference);
                    const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
                    const isExternalClient = ud.tipo === 'CLIENT';
                    const isSuperAdmin = ud.admin === true;
                    const isClientRole = isExternalClient || ud.admin !== true;
                    const destinoInternoAksanti = String(p.destination_type || '').toUpperCase() === 'AKSANTI' && p.recipient_user_id;
                    const isInternalRecipient = destinoInternoAksanti && String(p.recipient_user_id || '') === String(ud.id || '');
                    const pedidoCriadoPeloUtilizador = String(p.created_by_id || '') === String(ud.id || '');
                    const isReceiver = !pedidoCriadoPeloUtilizador;
                    const alteracaoSolicitada = pedidoComAlteracaoSolicitada(p);
                    const clienteLabel = document.getElementById('pedido-cliente-label');
                    if (clienteLabel) clienteLabel.textContent = isClientRole ? 'Parceiro' : (destinoInternoAksanti ? 'Destinatário' : 'Cliente');

                    if ((isExternalClient || isInternalRecipient) && p.status === 'SENT' && String(p.created_by_id || '') !== String(ud.id || '')) {
                        fetch('api/pedido-atualizar-status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reference: p.reference, novo_status: 'RECEIVED' })
                        })
                            .then(() => {
                                pedidoAtual = null;
                                carregarPedido(p.reference);
                            })
                            .catch(() => {
                                pedidoAtual = null;
                                carregarPedido(p.reference);
                            });
                        return;
                    }

                    document.getElementById('pedido-ref').textContent = valorPedido(p.reference);
                    document.getElementById('pedido-data').textContent = 'Criado: ' + valorPedido(p.date);
                    document.getElementById('pedido-cliente').textContent = isClientRole ? 'Aksanti' : valorPedido(p.client_name);
                    document.getElementById('pedido-area').textContent = valorPedido(p.area_name);
                    document.getElementById('pedido-criado-por').textContent = valorPedido(p.created_by_name);
                    document.getElementById('pedido-email').textContent = valorPedido(p.created_by_email);
                    document.getElementById('pedido-descricao').textContent = valorPedido(p.description);

                    const deadlineExpirado = valorAtivoPedido(p.deadline_expirado);
                    const deadlineBox = document.getElementById('pedido-deadline-box');
                    const deadlineEl = document.getElementById('pedido-deadline');
                    const deadlineAlerta = document.getElementById('pedido-deadline-alerta');

                    if (deadlineEl) deadlineEl.textContent = valorPedido(p.deadline);
                    if (deadlineBox) deadlineBox.classList.toggle('expirado', deadlineExpirado);
                    if (deadlineAlerta) deadlineAlerta.hidden = !deadlineExpirado;

                    const statusEl = document.getElementById('pedido-status');
                    statusEl.textContent = rotuloStatusPedido(p.status, data.timeline, p, isClientRole);
                    if (p.status === 'DRAFT') statusEl.className = 'badge badge-aviso';
                    else if (p.status === 'SENT') statusEl.className = 'badge badge-info';
                    else if (p.status === 'RECEIVED') statusEl.className = 'badge badge-info';
                    else if (p.status === 'ACCEPTED') statusEl.className = 'badge badge-sucesso';
                    else if (p.status === 'REJECTED') statusEl.className = 'badge badge-perigo';
                    else if (p.status === 'CLIENT_RESPONDED') statusEl.className = alteracaoSolicitada ? 'badge badge-aviso' : 'badge badge-info';
                    else if (p.status === 'CLOSED') statusEl.className = 'badge badge-sucesso';
                    else statusEl.className = 'badge badge-neutro';

                    renderTimeline(data.timeline, isReceiver, isClientRole);
                    renderComentarios(data.comentarios);
                    renderAnexos(data.anexos);
                    if (data.respostas) renderRespostasFormais(data.respostas, isClientRole);

                    const statusEditaveis = ['DRAFT', 'CLIENT_RESPONDED'];
                    const criadorPodeGerirPedido = pedidoCriadoPeloUtilizador && (!destinoInternoAksanti || pedidoCriadoPeloUtilizador);
                    const adminPodeGerirPedido = isSuperAdmin && (!destinoInternoAksanti || pedidoCriadoPeloUtilizador);
                    const membroAksantiPodeGerirPedido = ud.user_type === 'AKSANTI';
                    const podeEditarPedido = statusEditaveis.includes(p.status) && (criadorPodeGerirPedido || adminPodeGerirPedido || membroAksantiPodeGerirPedido);
                    document.getElementById('btn-editar-pedido').style.display = podeEditarPedido ? 'inline-block' : 'none';

                    const btnEnviarPedido = document.getElementById('btn-enviar-pedido');
                    const podeEnviarPedido = ['DRAFT', 'CLIENT_RESPONDED'].includes(p.status) && (criadorPodeGerirPedido || adminPodeGerirPedido);
                    btnEnviarPedido.style.display = podeEnviarPedido ? 'inline-block' : 'none';
                    btnEnviarPedido.textContent = p.status === 'DRAFT' ? 'Enviar' : 'Reenviar';

                    // Verifica quem deve responder formalmente ao pedido.
                    const respostaDaAksanti = p.status === 'CLIENT_RESPONDED' && decisaoResposta(p.latest_response_actor_type) === 'AKSANTI';
                    const aguardaDecisao = (p.status === 'SENT' || p.status === 'RECEIVED' || (respostaDaAksanti && !alteracaoSolicitada));
                    const pedidoCriadoPeloUtilizadorAtual = String(p.created_by_id || '') === String(ud.id || '');
                    const pedidoCriadoPorSuperAdmin = valorAtivoPedido(p.created_by_is_admin);
                    const pedidoVeioDeClienteOuColaborador = !pedidoCriadoPorSuperAdmin && !pedidoCriadoPeloUtilizadorAtual;
                    const clientePodeResponder = isExternalClient && !pedidoCriadoPeloUtilizadorAtual && aguardaDecisao;
                    const destinatarioInternoPodeResponder = isInternalRecipient && !pedidoCriadoPeloUtilizadorAtual && aguardaDecisao;
                    const adminPodeResponder = isSuperAdmin && pedidoVeioDeClienteOuColaborador && aguardaDecisao;

                    if (clientePodeResponder || adminPodeResponder || destinatarioInternoPodeResponder) {
                        document.getElementById('painel-decisao-cliente').style.display = 'block';
                        
                    } else {
                        document.getElementById('painel-decisao-cliente').style.display = 'none';
                    }

                    mostrarConteudoPedido();
                })
                .catch(err => mostrarErroPedido('Erro de conexão ao carregar os detalhes do pedido.'));
        }

        document.getElementById('btn-enviar-comentario').addEventListener('click', () => {
            const body = document.getElementById('novo-comentario').value.trim();
            if (!body) {
                mostrarMensagem('Atenção', 'Escreva um comentário antes de enviar.');
                return;
            }
            
            fetch('api/comentario-criar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reference: pedidoAtual.reference, body: body })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        limparRascunhoComentario(pedidoAtual.reference);
                        document.getElementById('novo-comentario').value = '';
                        carregarPedido(pedidoAtual.reference);
                    } else {
                        mostrarMensagem('Atenção', data.erro || 'Erro ao enviar o comentário.');
                    }
                });
        });

        document.getElementById('btn-cancelar-comentario').addEventListener('click', () => {
            limparRascunhoComentario(pedidoAtual ? pedidoAtual.reference : null);
            document.getElementById('novo-comentario').value = '';
            mostrarEstadoCacheComentario('Rascunho descartado.');
        });


        document.getElementById('btn-editar-pedido').addEventListener('click', () => {
            const btnEditar = document.getElementById('btn-editar-pedido');
            const originalText = btnEditar.textContent;
            btnEditar.textContent = 'A carregar...';
            btnEditar.disabled = true;

            fetch('api/formulario-dados.php')
                .then(res => res.json())
                .then(data => {
                    btnEditar.textContent = originalText;
                    btnEditar.disabled = false;
                    
                    if (!data.sucesso) {
                        mostrarMensagem('Atenção', 'Erro ao carregar dados do formulário.');
                        return;
                    }

                    const modoAdmin = data.modo_admin === true;
                    
                    let areasOptions = '<option value="">Selecionar departamento</option>';
                    data.areas.forEach(a => {
                        const selected = (String(a.id) === String(pedidoAtual.area_id)) ? 'selected' : '';
                        areasOptions += `<option value="${a.id}" ${selected}>${escaparHtmlPedido(a.name)} (${escaparHtmlPedido(a.code)})</option>`;
                    });

                    let clientesOptions = '<option value="">Selecionar cliente</option>';
                    data.clientes.forEach(c => {
                        const selected = (String(c.id) === String(pedidoAtual.client_id)) ? 'selected' : '';
                        clientesOptions += `<option value="${c.id}" data-email="${escaparHtmlPedido(c.primary_email)}" ${selected}>${escaparHtmlPedido(c.name)}</option>`;
                    });

                    let membrosOptions = '<option value="">Selecionar membro da Equipa Interna</option>';
                    (data.membros_aksanti || []).forEach(m => {
                        const selected = (String(m.id) === String(pedidoAtual.recipient_user_id)) ? 'selected' : '';
                        const cargo = m.cargo ? ' - ' + m.cargo : '';
                        const perfil = m.is_admin ? ' (Super Admin)' : '';
                        membrosOptions += `<option value="${m.id}" data-email="${escaparHtmlPedido(m.email)}" ${selected}>${escaparHtmlPedido(m.full_name)}${escaparHtmlPedido(cargo)}${escaparHtmlPedido(perfil)}</option>`;
                    });

                    const destinoAtual = String(pedidoAtual.destination_type || '').toUpperCase() === 'AKSANTI' ? 'AKSANTI' : 'CLIENT';
                    const emailAtual = pedidoAtual.raw_client_email || pedidoAtual.client_email || '';
                    
                    const html = `
                        <div class="formulario-grid">
                            <div class="largura-total">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Título do Pedido <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="text" id="edit-titulo" class="input-controlo" value="${escaparHtmlPedido(pedidoAtual.title || '')}">
                            </div>
                            
                            ${modoAdmin ? `
                            <div class="largura-total">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Enviar pedido para <span style="color: var(--cor-perigo);">*</span></label>
                                <select id="edit-destino-tipo" class="input-controlo">
                                    <option value="CLIENT" ${destinoAtual === 'CLIENT' ? 'selected' : ''}>Empresa / Cliente Final</option>
                                    <option value="AKSANTI" ${destinoAtual === 'AKSANTI' ? 'selected' : ''}>Equipa Interna</option>
                                </select>
                            </div>
                            ` : ''}

                            <div id="edit-grupo-cliente" style="${(!modoAdmin || destinoAtual === 'CLIENT') ? '' : 'display:none;'}">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Cliente <span style="color: var(--cor-perigo);">*</span></label>
                                <select id="edit-cliente" class="input-controlo">
                                    ${clientesOptions}
                                </select>
                            </div>

                            <div id="edit-grupo-membro" style="${(modoAdmin && destinoAtual === 'AKSANTI') ? '' : 'display:none;'}">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Equipa Interna <span style="color: var(--cor-perigo);">*</span></label>
                                <select id="edit-membro-aksanti" class="input-controlo">
                                    ${membrosOptions}
                                </select>
                            </div>

                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Área / Departamento <span style="color: var(--cor-perigo);">*</span></label>
                                <select id="edit-area" class="input-controlo">
                                    ${areasOptions}
                                </select>
                            </div>

                            <div>
                                <label id="edit-label-email" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="email" id="edit-email" class="input-controlo" value="${escaparHtmlPedido(emailAtual)}">
                            </div>

                            <div class="campo-deadline-destaque">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Data de Deadline <span style="color: var(--cor-perigo);">*</span></label>
                                <input type="date" id="edit-deadline" class="input-controlo" value="${escaparHtmlPedido(pedidoAtual.deadline_raw || '')}">
                            </div>

                            <div class="largura-total">
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Descrição do Pedido <span style="color: var(--cor-perigo);">*</span></label>
                                <textarea id="edit-descricao" class="input-controlo-area" rows="5">${escaparHtmlPedido(pedidoAtual.description || '')}</textarea>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                            <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
                            <button class="btn btn-primario" id="btn-salvar-edicao">Guardar Alterações</button>
                        </div>
                    `;

                    abrirModal('Editar Pedido', html, { largura: '640px' });

                    const selDestinoTipo = document.getElementById('edit-destino-tipo');
                    const selCliente = document.getElementById('edit-cliente');
                    const selMembro = document.getElementById('edit-membro-aksanti');
                    const inputEmail = document.getElementById('edit-email');
                    const grupoCliente = document.getElementById('edit-grupo-cliente');
                    const grupoMembro = document.getElementById('edit-grupo-membro');
                    
                    if (selDestinoTipo) {
                        selDestinoTipo.addEventListener('change', () => {
                            const isAksanti = selDestinoTipo.value === 'AKSANTI';
                            grupoCliente.style.display = isAksanti ? 'none' : '';
                            grupoMembro.style.display = isAksanti ? '' : 'none';
                            
                            inputEmail.readOnly = isAksanti;
                            if (isAksanti) {
                                selCliente.value = '';
                                const opcao = selMembro.options[selMembro.selectedIndex];
                                inputEmail.value = opcao && opcao.dataset.email ? opcao.dataset.email : '';
                            } else {
                                selMembro.value = '';
                                const opcao = selCliente.options[selCliente.selectedIndex];
                                inputEmail.value = opcao && opcao.dataset.email ? opcao.dataset.email : '';
                            }
                        });
                    }

                    if (selCliente) {
                        selCliente.addEventListener('change', () => {
                            const opcao = selCliente.options[selCliente.selectedIndex];
                            if (opcao && opcao.dataset.email) inputEmail.value = opcao.dataset.email;
                        });
                    }

                    if (selMembro) {
                        selMembro.addEventListener('change', () => {
                            const opcao = selMembro.options[selMembro.selectedIndex];
                            if (opcao && opcao.dataset.email) inputEmail.value = opcao.dataset.email;
                        });
                    }

                    document.getElementById('btn-salvar-edicao').addEventListener('click', () => {
                        const titulo = document.getElementById('edit-titulo').value.trim();
                        const desc = document.getElementById('edit-descricao').value.trim();
                        const areaId = document.getElementById('edit-area').value;
                        const email = document.getElementById('edit-email').value.trim();
                        const deadline = document.getElementById('edit-deadline').value;
                        
                        let clientId = selCliente ? selCliente.value : '';
                        // O editar-pedido.php lida com o cliente, mas na verdade se o destino mudar de CLIENT para AKSANTI 
                        // precisamos que a API consiga atualizar isso, embora o endpoint editar-pedido.php atualmente nao atualize destination_type ou recipient_user_id. 
                        // No entanto, podemos apenas enviar o client_id se for CLIENT. 
                        
                        if(!titulo || !desc || !areaId || !deadline) {
                            mostrarMensagem('Atenção', 'Todos os campos obrigatórios devem ser preenchidos.');
                            return;
                        }
                        
                        document.getElementById('btn-salvar-edicao').textContent = 'Aguarde...';
                        document.getElementById('btn-salvar-edicao').disabled = true;
                        
                        fetch('api/editar-pedido.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                reference: pedidoAtual.reference,
                                titulo: titulo,
                                descricao: desc,
                                area_id: areaId,
                                client_id: clientId,
                                client_email: email,
                                deadline: deadline
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.sucesso) {
                                fecharModal();
                                mostrarMensagem('Sucesso', data.mensagem || 'Pedido atualizado com sucesso!');
                                carregarPedido(pedidoAtual.reference);
                            } else {
                                mostrarMensagem('Atenção', data.erro || 'Erro ao guardar as alterações.');
                                document.getElementById('btn-salvar-edicao').textContent = 'Guardar Alterações';
                                document.getElementById('btn-salvar-edicao').disabled = false;
                            }
                        })
                        .catch(err => {
                            mostrarMensagem('Atenção', 'Erro de ligação ao servidor.');
                            document.getElementById('btn-salvar-edicao').textContent = 'Guardar Alterações';
                            document.getElementById('btn-salvar-edicao').disabled = false;
                        });
                    });
                })
                .catch(err => {
                    btnEditar.textContent = originalText;
                    btnEditar.disabled = false;
                    mostrarMensagem('Atenção', 'Erro ao carregar os dados de edição.');
                });
        });
        document.getElementById('btn-enviar-pedido').addEventListener('click', () => {
            const estaAReenviar = pedidoAtual && pedidoAtual.status === 'CLIENT_RESPONDED';
            confirmarAcao(
                estaAReenviar ? 'Reenviar Pedido' : 'Enviar Pedido',
                estaAReenviar ? 'Tem a certeza de que deseja reenviar este pedido ao cliente?' : 'Tem a certeza de que deseja enviar este pedido?',
                () => {
                fetch('api/pedido-atualizar-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reference: pedidoAtual.reference, novo_status: 'SENT' })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.sucesso) {
                            mostrarMensagem('Sucesso', estaAReenviar ? 'Pedido reenviado com sucesso.' : 'Pedido enviado com sucesso.', {
                                aoFechar: () => window.location.reload()
                            });
                            return;
                        }

                        mostrarMensagem('Atenção', data.erro || 'Erro ao enviar o pedido.');
                    })
                    .catch(() => mostrarMensagem('Atenção', 'Erro de ligação ao servidor.'));
                }
            );
        });

        const dropZone = document.getElementById('drop-zone');
        const inputFile = document.getElementById('input-arquivo');

        dropZone.addEventListener('click', () => inputFile.click());
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('ativo'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('ativo'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('ativo');
            inputFile.files = e.dataTransfer.files;
            fazerUpload(inputFile.files);
        });

        inputFile.addEventListener('change', e => fazerUpload(e.target.files));

        function fazerUpload(files) {
            for (let file of files) {
                if (ficheiroExcedeLimite(file)) {
                    mostrarMensagem('Atenção', `O ficheiro "${file.name}" não pode ter mais de ${documentoMaxMb}MB.`);
                    continue;
                }

                const formData = new FormData();
                formData.append('arquivo', file);
                formData.append('reference', pedidoAtual.reference);

                fetch('api/anexo-upload.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.sucesso) carregarPedido(pedidoAtual.reference);
                        else mostrarMensagem('Atenção', data.erro || 'Erro ao carregar o anexo.');
                    });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            limparCachesExpiradosComentarios();
            configurarCacheNovoComentario();

            const params = new URLSearchParams(window.location.search);
            const ref = params.get('id') || params.get('ref');
            if (!ref) {
                mostrarErroPedido('Nenhuma referência de pedido foi fornecida.');
                return;
            }
            carregarPedido(ref);
});
