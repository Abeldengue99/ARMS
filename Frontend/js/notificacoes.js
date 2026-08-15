/**
 * ARMS - notificações em tempo quase real.
 */
(function() {
    'use strict';

    const POLL_INTERVAL = 3000;
    let ultimaContagem = null;
    let ultimaCriada = null;
    let audioContext = null;
    let audioLiberado = false;

    function garantirEstilosSino() {
        if (document.getElementById('arms-notificacao-estilos')) return;

        const style = document.createElement('style');
        style.id = 'arms-notificacao-estilos';
        style.textContent = `
            @keyframes arms-sino-alerta {
                0%, 100% { transform: rotate(0deg); }
                20% { transform: rotate(14deg); }
                40% { transform: rotate(-12deg); }
                60% { transform: rotate(8deg); }
                80% { transform: rotate(-5deg); }
            }
            .notificacao-tocando svg {
                animation: arms-sino-alerta 0.75s ease both;
                transform-origin: 50% 15%;
            }
            @keyframes arms-sino-piscar {
                0%, 100% {
                    background-color: #fff7ed;
                    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.42);
                }
                50% {
                    background-color: #fee2e2;
                    box-shadow: 0 0 0 9px rgba(239, 68, 68, 0);
                }
            }
            @keyframes arms-badge-piscar {
                0%, 100% {
                    transform: scale(1);
                    background-color: #ef4444;
                }
                50% {
                    transform: scale(1.18);
                    background-color: #f97316;
                }
            }
            a.notificacao-piscando {
                animation: arms-sino-piscar 1.35s ease-in-out infinite;
            }
            a.notificacao-piscando svg {
                color: #ef4444;
            }
            a.notificacao-piscando .notif-badge {
                animation: arms-badge-piscar 0.9s ease-in-out infinite;
                transform-origin: center;
            }
            @media (prefers-reduced-motion: reduce) {
                a.notificacao-piscando,
                a.notificacao-piscando .notif-badge {
                    animation: none;
                }
            }
        `;
        document.head.appendChild(style);
    }

    function prepararAudio() {
        audioLiberado = true;

        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!audioContext) audioContext = new AudioCtx();
            if (audioContext.state === 'suspended') audioContext.resume();
        } catch (erro) {
            audioContext = null;
        }
    }

    function tocarSinoAlerta() {
        if (!audioLiberado) return;

        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!audioContext) audioContext = new AudioCtx();

            const agora = audioContext.currentTime;
            const ganho = audioContext.createGain();
            ganho.gain.setValueAtTime(0.0001, agora);
            ganho.gain.exponentialRampToValueAtTime(0.16, agora + 0.02);
            ganho.gain.exponentialRampToValueAtTime(0.0001, agora + 0.55);
            ganho.connect(audioContext.destination);

            [880, 1175].forEach((frequencia, indice) => {
                const oscilador = audioContext.createOscillator();
                oscilador.type = 'sine';
                oscilador.frequency.setValueAtTime(frequencia, agora + (indice * 0.08));
                oscilador.frequency.exponentialRampToValueAtTime(frequencia * 0.72, agora + 0.45);
                oscilador.connect(ganho);
                oscilador.start(agora + (indice * 0.08));
                oscilador.stop(agora + 0.56);
            });
        } catch (erro) {
            // O navegador pode bloquear áudio sem interação. A animação visual continua.
        }
    }

    function animarSinos() {
        document.querySelectorAll('a[href="notificacoes.html"]').forEach((sinho) => {
            sinho.classList.remove('notificacao-tocando');
            void sinho.offsetWidth;
            sinho.classList.add('notificacao-tocando');
            window.setTimeout(() => sinho.classList.remove('notificacao-tocando'), 850);
        });
    }

    function notificarChegada(data) {
        animarSinos();
        tocarSinoAlerta();
        window.dispatchEvent(new CustomEvent('arms:notificacoes-novas', { detail: data }));
    }

    function atualizarBadges(count) {
        document.querySelectorAll('.notif-badge').forEach((badge) => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
            }
        });

        document.querySelectorAll('a[href="notificacoes.html"]').forEach((sinho) => {
            sinho.classList.toggle('notificacao-piscando', count > 0);
        });
    }

    function atualizarTitulo(count) {
        const tituloBase = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = count > 0 ? `(${count}) ${tituloBase}` : tituloBase;
    }

    function atualizarContagemNotificacoes() {
        return fetch('api/notificacoes.php?acao=contar', { cache: 'no-store' })
            .then((res) => res.json())
            .then((data) => {
                if (!data.sucesso) return null;

                const count = Number(data.nao_lidas || 0);
                const criada = data.ultima_criada || null;
                const primeiraLeitura = ultimaContagem === null;
                const chegouNova = !primeiraLeitura && count > 0 && (
                    count > ultimaContagem || (criada && criada !== ultimaCriada)
                );

                atualizarBadges(count);
                atualizarTitulo(count);

                if (chegouNova) {
                    notificarChegada(data);
                }

                ultimaContagem = count;
                ultimaCriada = criada;

                return data;
            })
            .catch(() => null);
    }

    function injetarBadgesNoSino() {
        document.querySelectorAll('a[href="notificacoes.html"]').forEach((sinho) => {
            sinho.style.position = 'relative';

            const pontoAntigo = sinho.querySelector('span:not(.notif-badge)');
            if (pontoAntigo) pontoAntigo.remove();

            if (sinho.querySelector('.notif-badge')) return;

            const badge = document.createElement('span');
            badge.className = 'notif-badge';
            badge.style.cssText = `
                position: absolute;
                top: 4px;
                right: 2px;
                min-width: 18px;
                height: 18px;
                background-color: #ef4444;
                color: white;
                font-size: 0.65rem;
                font-weight: 700;
                border-radius: 10px;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 0 4px;
                border: 2px solid white;
                line-height: 1;
                font-family: var(--fonte-principal), sans-serif;
            `;
            sinho.appendChild(badge);
        });
    }

    document.addEventListener('click', prepararAudio, { once: true, passive: true });
    document.addEventListener('keydown', prepararAudio, { once: true });

    document.addEventListener('DOMContentLoaded', () => {
        garantirEstilosSino();
        injetarBadgesNoSino();
        atualizarContagemNotificacoes();
        window.setInterval(atualizarContagemNotificacoes, POLL_INTERVAL);
    });

    window.atualizarContagemNotificacoes = atualizarContagemNotificacoes;
    window.tocarSinoNotificacao = tocarSinoAlerta;
})();
