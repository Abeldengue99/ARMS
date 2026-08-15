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
    let _filtros = {};
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
     * Define filtros adicionais para as consultas (ex: empresa, departamento)
     */
    function definirFiltros(filtros) {
        _filtros = filtros || {};
        _ultimoTimestamp = null;
    }

    /**
     * Força uma atualização imediata (ex: após criar um pedido ou mudar filtros)
     */
    function forcarAtualizacao() {
        _ultimoTimestamp = null; // Resetar para buscar tudo
        _emConsulta = false; // Forçar que o consultar avance
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
        Object.entries(_filtros).forEach(([chave, valor]) => {
            if (valor) url += `&${encodeURIComponent(chave)}=${encodeURIComponent(valor)}`;
        });
        
        console.log('[ARMS-RT] Fetching URL:', url, 'com filtros:', _filtros);

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
        definirReferencia,
        definirFiltros
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
     * Inicializa a UI imediatamente com os dados em cache no localStorage (0ms latência)
     */
    function inicializarSincrono() {
        try {
            const dadosSalvos = localStorage.getItem('arms_utilizador_dados');
            if (dadosSalvos) {
                _utilizador = JSON.parse(dadosSalvos);
                _atualizarUI();
            }
        } catch (e) {
            console.warn('[ArmsSessao] Erro ao ler cache de utilizador:', e);
        }
        
        // Garantir remoção do role-pending e preload em qualquer circunstância válida
        document.documentElement.classList.remove('role-pending');
        document.documentElement.classList.remove('preload');
        _ativarPrefetchNavegacao();
    }

    /**
     * Pré-carrega páginas ao passar o cursor sobre os links da barra lateral
     */
    function _ativarPrefetchNavegacao() {
        if (window._armsPrefetchAtivo) return;
        window._armsPrefetchAtivo = true;

        document.addEventListener('mouseover', function(e) {
            const link = e.target.closest('a[href$=".html"]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('http') || href.includes('javascript:')) return;

            if (!document.querySelector(`link[rel="prefetch"][href="${href}"]`)) {
                const p = document.createElement('link');
                p.rel = 'prefetch';
                p.href = href;
                document.head.appendChild(p);
            }
        }, { passive: true });
    }

    /**
     * Verifica a sessão com re-validação em segundo plano sem bloquear o ecrã
     */
    function verificar() {
        // Renderização instantânea imediata antes do pedido HTTP
        inicializarSincrono();

        return fetch('api/sessao.php?acao=verificar')
            .then(res => res.json())
            .then(data => {
                if (!data.sucesso || !data.autenticado) {
                    // Sessão PHP expirou ou é inválida, forçar re-autenticação real
                    localStorage.removeItem('arms_utilizador_logado');
                    localStorage.removeItem('arms_utilizador_dados');
                    if (!window.location.pathname.endsWith('index.html')) {
                        window.location.href = 'index.html';
                    }
                    return null;
                }

                _utilizador = data.utilizador;
                data.utilizador.iniciais = _calcularIniciais(data.utilizador);
                // Sincronizar com localStorage
                localStorage.setItem('arms_utilizador_logado', 'true');
                localStorage.setItem('arms_utilizador_dados', JSON.stringify(_utilizador));

                if (_utilizador.senha_expirada === true || _utilizador.password_expired === true) {
                    if (!window.location.pathname.endsWith('perfil.html')) {
                        window.location.href = 'perfil.html?senha_expirada=1';
                        return null;
                    }
                }

                // Cliente e colaborador Aksanti sem perfil de Super Admin ficam restritos.
                if (!_validarAcessoPagina()) {
                    return null;
                }

                _atualizarUI();
                return _utilizador;
            })
            .catch(() => {
                // Se falhar a rede mas tivermos sessão ativa em cache, manter utilizador em modo resiliência
                if (localStorage.getItem('arms_utilizador_logado') === 'true' && _utilizador) {
                    _atualizarUI();
                    return _utilizador;
                }

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
        if (!_utilizador) {
            try {
                const dadosSalvos = localStorage.getItem('arms_utilizador_dados');
                if (dadosSalvos) _utilizador = JSON.parse(dadosSalvos);
            } catch(e) {}
        }
        return _utilizador;
    }

    /**
     * Atualiza a UI com os dados do utilizador
     */
    function _atualizarUI() {
        if (!_utilizador) return;

        document.documentElement.classList.remove('role-pending');

        // Atualizar iniciais no avatar
        let iniciais = _calcularIniciais(_utilizador);
        if (!iniciais) iniciais = 'U'; // Fallback
        
        let isAdmin = _utilizador.is_admin === true || _utilizador.is_admin === 1 || _utilizador.is_admin === '1' || _utilizador.is_admin === 't' || String(_utilizador.is_admin).toLowerCase() === 'true';
        let avatarColor = 'var(--aksanti-gold)'; // Cor padrão (Colaborador Aksanti)
        if (isAdmin) {
            avatarColor = '#3b82f6'; // Azul para Admin
        } else if (_utilizador.user_type === 'CLIENT') {
            avatarColor = '#10b981'; // Verde para Cliente
        }
        
        const avatares = document.querySelectorAll('[data-arms-avatar]');
        avatares.forEach(el => {
            el.textContent = iniciais;
            el.setAttribute('aria-label', _utilizador.nome || _utilizador.email || 'Utilizador');
            el.style.setProperty('background-color', avatarColor, 'important');
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

    // Auto-executar inicialização síncrona se script for carregado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarSincrono);
    } else {
        inicializarSincrono();
    }

    return {
        verificar,
        inicializarSincrono,
        terminar,
        obterUtilizador
    };

})();
