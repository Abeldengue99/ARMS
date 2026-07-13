/**
 * ARMS — Motor de Tempo Real (Real-Time Engine)
 * Faz polling ao servidor a cada intervalo definido
 * Atualiza a interface automaticamente quando há dados novos
 * 
 * Uso: ArmsTempoReal.iniciar('dashboard', callback, intervalo)
 */
const ArmsTempoReal = (function() {
    'use strict';

    const CONFIG = {
        URL_BASE: 'api/tempo-real.php',
        INTERVALO_PADRAO: 8000,   // 8 segundos
        INTERVALO_RAPIDO: 3000,    // 3 segundos (após ação do utilizador)
        INTERVALO_LENTO: 15000,    // 15 segundos (quando inativo)
        MAX_ERROS: 5
    };

    let _intervaloId = null;
    let _ultimoTimestamp = null;
    let _modulo = 'geral';
    let _callback = null;
    let _errosConsecutivos = 0;
    let _ativo = true;
    let _ref = null; // Para pedido-detalhe
    let _intervaloAtual = CONFIG.INTERVALO_PADRAO;
    let _emConsulta = false;

    /**
     * Inicia o polling em tempo real para um módulo específico
     */
    function iniciar(modulo, callback, intervalo) {
        _modulo = modulo || 'geral';
        _callback = callback;
        _intervaloAtual = intervalo || CONFIG.INTERVALO_PADRAO;
        _errosConsecutivos = 0;
        _ativo = true;

        // Parar qualquer polling anterior
        if (_intervaloId) {
            clearInterval(_intervaloId);
            _intervaloId = null;
        }

        // Primeira consulta imediata
        consultar();

        // Polling periódico
        _intervaloId = setInterval(consultar, _intervaloAtual);

        // Detectar quando a aba está visível/oculta para ajustar a frequência
        document.addEventListener('visibilitychange', _aoMudarVisibilidade);

    }

    /**
     * Para o polling
     */
    function parar() {
        if (_intervaloId) {
            clearInterval(_intervaloId);
            _intervaloId = null;
        }
        document.removeEventListener('visibilitychange', _aoMudarVisibilidade);
    }

    /**
     * Define a referência para pedido-detalhe
     */
    function definirReferencia(ref) {
        _ref = ref;
    }

    /**
     * Força uma atualização imediata (ex: após criar um pedido)
     */
    function forcarAtualizacao() {
        _ultimoTimestamp = null; // Resetar para buscar tudo
        consultar();
    }

    /**
     * Consulta o servidor por atualizações
     */
    function consultar() {
        if (!_ativo || _emConsulta) return;
        _emConsulta = true;

        let url = `${CONFIG.URL_BASE}?modulo=${_modulo}`;
        if (_ultimoTimestamp) url += `&desde=${encodeURIComponent(_ultimoTimestamp)}`;
        if (_ref) url += `&ref=${encodeURIComponent(_ref)}`;

        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                _errosConsecutivos = 0;

                if (data.sucesso && data.atualizacoes) {
                    _ultimoTimestamp = data.timestamp;

                    // Chamar o callback com os dados atualizados
                    if (_callback && typeof _callback === 'function') {
                        _callback(data.atualizacoes);
                    }
                }
            })
            .catch(err => {
                _errosConsecutivos++;
                console.warn(`[ARMS-RT] Erro #${_errosConsecutivos}:`, err.message);

                if (_errosConsecutivos >= CONFIG.MAX_ERROS) {
                    console.error('[ARMS-RT] Muitos erros consecutivos. Polling parado.');
                    parar();
                }
            })
            .finally(() => {
                _emConsulta = false;
            });
    }

    /**
     * Ajusta a frequência quando a aba fica oculta/visível
     */
    function _aoMudarVisibilidade() {
        if (_intervaloId) {
            clearInterval(_intervaloId);
            _intervaloId = null;
        }

        if (document.hidden) {
            // Aba oculta: reduzir frequência
            _intervaloId = setInterval(consultar, CONFIG.INTERVALO_LENTO);
        } else {
            // Aba visível: restaurar frequência normal e atualizar já
            forcarAtualizacao();
            _intervaloId = setInterval(consultar, _intervaloAtual);
        }
    }

    // API pública
    return {
        iniciar,
        parar,
        forcarAtualizacao,
        definirReferencia
    };

})();

/**
 * ARMS — Verificação de Sessão
 * Protege páginas internas verificando a autenticação via API
 * Carrega dados do utilizador na interface
 */
const ArmsSessao = (function() {
    'use strict';

    let _utilizador = null;

    function _primeiroCaractere(texto) {
        return Array.from(String(texto || '').trim())[0] || '';
    }

    function _calcularIniciais(utilizador) {
        if (!utilizador) return '';

        let base = String(utilizador.nome || utilizador.email || '').trim();
        if (!base || base.toLowerCase() === 'utilizador') {
            base = String(utilizador.email || '').trim();
        }

        if (!base) return '';

        if (base.includes('@')) {
            base = base.split('@')[0];
        }

        base = base.replace(/\s*\([^)]*\)\s*/gu, ' ');
        const partes = base.split(/[\s._-]+/u).filter(Boolean);
        if (!partes.length) {
            return typeof utilizador.iniciais === 'string' ? utilizador.iniciais.trim().toUpperCase() : '';
        }

        return (_primeiroCaractere(partes[0]) + (partes.length > 1 ? _primeiroCaractere(partes[partes.length - 1]) : '')).toUpperCase();
    }

    function _permissoes(utilizador) {
        return Array.isArray(utilizador?.permissoes) ? utilizador.permissoes : [];
    }

    function _temPermissao(utilizador, permissoes) {
        if (utilizador?.admin === true) return true;
        const lista = _permissoes(utilizador);
        const necessarias = Array.isArray(permissoes) ? permissoes : [permissoes];
        return necessarias.some((permissao) => lista.includes(permissao));
    }

    function _temAcessoAdminParcial(utilizador) {
        return _temPermissao(utilizador, [
            'clientes.ver',
            'clientes.gerir',
            'areas.ver',
            'areas.gerir',
            'qualidade.ver',
            'seguranca.gerir',
            'automacao.gerir',
            'retencao.gerir'
        ]);
    }

    function _utilizadorRestrito(utilizador) {
        return !utilizador || (utilizador.admin !== true && !_temAcessoAdminParcial(utilizador));
    }

    function _paginaAdministrativa() {
        const paginaAtual = window.location.pathname;
        if (paginaAtual.includes('clientes.html')) return ['clientes.ver', 'clientes.gerir'];
        if (paginaAtual.includes('areas.html')) return ['areas.ver', 'areas.gerir'];
        if (paginaAtual.includes('admin-utilizadores.html')) return ['qualidade.ver', 'seguranca.gerir', 'automacao.gerir', 'retencao.gerir'];
        return null;
    }

    function _validarAcessoPagina() {
        const permissaoPagina = _paginaAdministrativa();
        if (permissaoPagina && !_temPermissao(_utilizador, permissaoPagina)) {
            window.location.href = 'dashboard.html';
            return false;
        }

        return true;
    }

    /**
     * Verifica a sessão e redireciona para login se necessário
     */
    function verificar() {
        return fetch('api/sessao.php?acao=verificar')
            .then(res => res.json())
            .then(data => {
                if (!data.sucesso || !data.autenticado) {
                    // Sessão PHP expirou ou é inválida, forçar re-autenticação real
                    localStorage.removeItem('arms_utilizador_logado');
                    localStorage.removeItem('arms_utilizador_dados');
                    window.location.href = 'index.html';
                    return null;
                }

                _utilizador = data.utilizador;
                data.utilizador.iniciais = _calcularIniciais(data.utilizador);
                // Sincronizar com localStorage
                localStorage.setItem('arms_utilizador_logado', 'true');
                localStorage.setItem('arms_utilizador_dados', JSON.stringify(_utilizador));

                // Cliente e colaborador Aksanti sem perfil de Super Admin ficam restritos.
                if (!_validarAcessoPagina()) {
                    return null;
                }

                _atualizarUI();
                return _utilizador;
            })
            .catch(() => {
                localStorage.removeItem('arms_utilizador_logado');
                localStorage.removeItem('arms_utilizador_dados');

                if (!window.location.pathname.endsWith('index.html')) {
                    window.location.href = 'index.html';
                }
                return null;
            });
    }

    /**
     * Termina a sessão
     */
    function terminar() {
        return fetch('api/sessao.php?acao=logout')
            .finally(() => {
                localStorage.removeItem('arms_utilizador_logado');
                localStorage.removeItem('arms_utilizador_dados');
                window.location.href = 'index.html';
            });
    }

    /**
     * Devolve o utilizador actual
     */
    function obterUtilizador() {
        return _utilizador;
    }

    /**
     * Atualiza a UI com os dados do utilizador
     */
    function _atualizarUI() {
        if (!_utilizador) return;

        document.documentElement.classList.remove('role-pending');

        // Atualizar iniciais no avatar
        const iniciais = _calcularIniciais(_utilizador);
        const avatares = document.querySelectorAll('[data-arms-avatar]');
        avatares.forEach(el => {
            el.textContent = iniciais || '';
            el.setAttribute('aria-label', _utilizador.nome || _utilizador.email || 'Utilizador');
        });

        // Atualizar nome do utilizador onde existir
        const nomes = document.querySelectorAll('[data-arms-nome]');
        nomes.forEach(el => {
            el.textContent = _utilizador.nome || 'Utilizador';
        });

        // Garantir visualmente no DOM pós-renderização (como backup ao script anti-flicker)
        if (_utilizadorRestrito(_utilizador)) {
            document.documentElement.classList.add('is-client-role');
            document.documentElement.classList.remove('is-admin-role');
        } else {
            document.documentElement.classList.remove('is-client-role');
            document.documentElement.classList.add('is-admin-role');
        }

        const regrasLinks = [
            ['a[href="clientes.html"]', ['clientes.ver', 'clientes.gerir']],
            ['a[href="areas.html"]', ['areas.ver', 'areas.gerir']],
            ['a[href="admin-utilizadores.html"]', ['qualidade.ver', 'seguranca.gerir', 'automacao.gerir', 'retencao.gerir']]
        ];

        regrasLinks.forEach(([seletor, permissoes]) => {
            document.querySelectorAll(seletor).forEach((el) => {
                el.style.display = _temPermissao(_utilizador, permissoes) ? '' : 'none';
            });
        });

        document.querySelectorAll('.menu-super-admin-only').forEach((el) => {
            el.style.display = _utilizador.admin === true ? '' : 'none';
        });

        document.querySelectorAll('[data-requer-permissao]').forEach((el) => {
            const permissoes = String(el.getAttribute('data-requer-permissao') || '').split(',').map((item) => item.trim()).filter(Boolean);
            el.style.display = _temPermissao(_utilizador, permissoes) ? '' : 'none';
        });

        document.querySelectorAll('hr.menu-admin-only').forEach((el) => {
            el.style.display = _temAcessoAdminParcial(_utilizador) ? '' : 'none';
        });
    }

    return {
        verificar,
        terminar,
        obterUtilizador
    };

})();
