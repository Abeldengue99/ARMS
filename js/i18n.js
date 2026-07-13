let traducoesEmCache = {};

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
        const resposta = await fetch(`lang/${codigoDoIdioma}.json`, { cache: 'force-cache' });
        if (!resposta.ok) throw new Error(`HTTP ${resposta.status}`);
        const textos = await resposta.json();
        traducoesEmCache[codigoDoIdioma] = textos;
        return textos;
    } catch (erro) {
        console.error('Falha ao carregar traducoes:', erro);
        return null;
    }
}

async function mudarIdiomaAksanti(codigoDoIdioma = 'pt') {
    localStorage.setItem('arms_idioma', codigoDoIdioma);

    const textosIdioma = await carregarTraducoes(codigoDoIdioma);
    if (!textosIdioma) return;

    document.querySelectorAll('[data-i18n]').forEach((elementoHTML) => {
        const chaveDaGaveta = elementoHTML.getAttribute('data-i18n');
        const valorTraduzido = obterValorTraducao(textosIdioma, chaveDaGaveta);

        if (typeof valorTraduzido === 'string' && valorTraduzido.length > 0) {
            elementoHTML.textContent = valorTraduzido;
        }
    });

    const selectDoIdioma = document.getElementById('select-idioma-aksanti');
    if (selectDoIdioma) selectDoIdioma.value = codigoDoIdioma;
}

document.addEventListener('DOMContentLoaded', () => {
    const idiomaMemorizado = localStorage.getItem('arms_idioma') || 'pt';
    mudarIdiomaAksanti(idiomaMemorizado);

    const selectDoIdioma = document.getElementById('select-idioma-aksanti');
    if (selectDoIdioma) {
        selectDoIdioma.addEventListener('change', (evento) => mudarIdiomaAksanti(evento.target.value));
    }
});
