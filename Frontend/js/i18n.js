/**
 * ARMS — Sistema de Internacionalização (i18n)
 * Motor de tradução global para todas as páginas do sistema.
 * 
 * Funcionalidades:
 * - Traduz elementos HTML com atributo data-i18n
 * - Traduz placeholders com atributo data-i18n-placeholder
 * - Traduz títulos/tooltips com atributo data-i18n-title
 * - Função global t('chave', 'fallback') para uso em JavaScript
 * - Persistência do idioma escolhido no localStorage
 * - Fallback seguro: se a tradução falhar, mostra o texto original em Português
 */

let traducoesEmCache = {};
let idiomaAtual = 'pt';

function obterValorTraducao(objeto, chave) {
    return chave.split('.').reduce((atual, parte) => atual?.[parte], objeto);
}

function lerTraducoesInline(codigoDoIdioma) {
    const blocoInline = document.getElementById(`lang-${codigoDoIdioma}`);
    if (!blocoInline || !blocoInline.textContent.trim()) return null;

    try {
        return JSON.parse(blocoInline.textContent);
    } catch (erro) {
        console.error('Falha ao ler traducoes embutidas:', erro);
        return null;
    }
}

async function carregarTraducoes(codigoDoIdioma) {
    if (traducoesEmCache[codigoDoIdioma]) {
        return traducoesEmCache[codigoDoIdioma];
    }

    const textosInline = lerTraducoesInline(codigoDoIdioma);
    if (textosInline) {
        traducoesEmCache[codigoDoIdioma] = textosInline;
        return textosInline;
    }

    try {
        const resposta = await fetch(`lang/${codigoDoIdioma}.json`, { cache: 'no-cache' });
        if (!resposta.ok) throw new Error(`HTTP ${resposta.status}`);
        const textos = await resposta.json();
        traducoesEmCache[codigoDoIdioma] = textos;
        return textos;
    } catch (erro) {
        console.error('Falha ao carregar traducoes:', erro);
        return null;
    }
}

/**
 * Função global de tradução para uso em JavaScript.
 * @param {string} chave - Chave da tradução (ex: 'pedidos.titulo')
 * @param {string} [fallback] - Texto de fallback (Português) caso a tradução não exista
 * @returns {string} Texto traduzido ou fallback
 */
function t(chave, fallback) {
    const textos = traducoesEmCache[idiomaAtual];
    if (!textos) return fallback || chave;
    const valor = obterValorTraducao(textos, chave);
    return (typeof valor === 'string' && valor.length > 0) ? valor : (fallback || chave);
}

// Expor globalmente
window.t = t;

async function mudarIdiomaAksanti(codigoDoIdioma) {
    if (!codigoDoIdioma) codigoDoIdioma = 'pt';
    idiomaAtual = codigoDoIdioma;
    localStorage.setItem('arms_idioma', codigoDoIdioma);

    const textosIdioma = await carregarTraducoes(codigoDoIdioma);
    if (!textosIdioma) return;

    // Traduzir textContent
    document.querySelectorAll('[data-i18n]').forEach((el) => {
        const chave = el.getAttribute('data-i18n');
        const valor = obterValorTraducao(textosIdioma, chave);
        if (typeof valor === 'string' && valor.length > 0) {
            el.textContent = valor;
        }
    });

    // Traduzir placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
        const chave = el.getAttribute('data-i18n-placeholder');
        const valor = obterValorTraducao(textosIdioma, chave);
        if (typeof valor === 'string' && valor.length > 0) {
            el.placeholder = valor;
        }
    });

    // Traduzir títulos/tooltips
    document.querySelectorAll('[data-i18n-title]').forEach((el) => {
        const chave = el.getAttribute('data-i18n-title');
        const valor = obterValorTraducao(textosIdioma, chave);
        if (typeof valor === 'string' && valor.length > 0) {
            el.title = valor;
        }
    });

    // Sincronizar todos os selects de idioma na página
    document.querySelectorAll('#select-idioma-aksanti, .select-idioma-aksanti').forEach((sel) => {
        sel.value = codigoDoIdioma;
    });

    // Atualizar atributo lang do HTML
    document.documentElement.lang = codigoDoIdioma;
}

// Expor globalmente para uso em scripts inline
window.mudarIdiomaAksanti = mudarIdiomaAksanti;

document.addEventListener('DOMContentLoaded', () => {
    const idiomaMemorizado = localStorage.getItem('arms_idioma') || 'pt';
    mudarIdiomaAksanti(idiomaMemorizado);

    // Listener para todos os selects de idioma
    document.querySelectorAll('#select-idioma-aksanti, .select-idioma-aksanti').forEach((sel) => {
        sel.addEventListener('change', (evento) => mudarIdiomaAksanti(evento.target.value));
    });
});
