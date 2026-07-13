// Estou a ordenar o teu grande sistema a fechar as portões da rua à chave e a abri-los só mesmo quando o esqueleto inteiro e completo da Dashboard estiver de pé e desenhado à perfeição na página! (Graças à escuta do evento DOMContentLoaded)
document.addEventListener('DOMContentLoaded', () => {
    
    // Estou a invocar a suprema e divina magia de introdução do espetáculo do Preloader animado, com o poder extraído de forma descarada mas genial dos amigos do site Cacimbo, antes de as tuas listagens feias do sistema começarem sequer a chatear e aparecer no branco!
    
    // Estou a gerar de raiz e forjar ao ar livre com o poder do código JavaScript puro (Um document Create), a fabulosa placa preta do vazio (a etiqueta "div" solta) a voar e preparar as suas garras para te tapar o sol do ecrã inteiro
    const telaLoading = document.createElement('div');
    // Estou a vestir e assinalar àquela escura e grande tela vazia, as nossas mais bonitas e apertadas fardas e vestidos oficiais dourados da ISAF e da Aksanti, a dar a classe do CSS de marca "preloader-aksanti" (Que dura à volta de uns longos 1s ou 2s até se vaporizar e desaparecer pelo ar!)
    telaLoading.className = 'preloader-aksanti';
    // Estou a gravar na pedra e a escrever entalhando à força e à unha, a mítica palavra em ouros a pulsar "Aksanti" lá cravada precisamente no meio exato e centro dessa enorme e sombria placa e tela div!
    telaLoading.innerHTML = '<span class="preloader-texto">Aksanti</span>';
    // Estou a atirar à força e espetar finalmente esse grande quadro na primeiríssima fila das cadeiras do teu enorme corpo HTML ("No Body ali pendurada numa corrente ao fundo") para reinar sendo ela sim, a anfitriã da belíssima entrada de gala da tua vida na ARMS!
    document.body.appendChild(telaLoading);

    // ==============================================================
    // A Lógica Superior para injetar à força os teus falsos dados Mock da grande base de PostgreSQL em frente aos olhos de todos no Ecrã!
    // ==============================================================

    // Estou a ir procurar as grandes caixas e armários brancos (Os tais dos teus maravilhosos Cards) dos amados indicadores KPI. Quero meter as garras neles, que têm as dezenas e números vazios dos falsos valores, onde os mesmos dormem e moram lá sem utilidade agora, achando-os e agarrando logo os vizinhos das letras (Os 'nextElementSibling' a caçar do elemento colado a si)!
    const domTotalPedidos = document.querySelector('[data-i18n="dashboard.total_pedidos"]')?.nextElementSibling;
    const domTaxaResposta = document.querySelector('[data-i18n="dashboard.taxa_resposta"]')?.nextElementSibling;
    
    // Estou a questionar e puxar para a minha própria cabeça as certezas: ao perguntar ao nosso código se todos tais belos letreiros de luz afinal andam vivos e existem em pé pelas vitrines de venda ali na feira (A vaguear no ar de todo o teu grande e belo Dashboard Aksanti)... Se me garantir sim, avanço e vou manchar as chapas que eles têm com muita e pesada tinta e dados da vida!
    if (domTotalPedidos && typeof mockPedidos !== 'undefined') {
        // Estou a meter a minha grandíssima e gigante mão inteira até aos braços nos buracos lá ao fundo e puxar para fora da nossa cartola das magias "O array do mockPedidos", e saco fora apenas o número contável ali de elementos mágicos dentro do cesto! Despejo a conta e passo a pente na conta espetando-os aos vivos nos contentores do letreiro visivelmente e a encorpar nos "Textos".
        domTotalPedidos.textContent = mockPedidos.length;
        
        // Estou a encorpar contas da pura matemática e o suor da nossa álgebra fina para te arrancar a fantástica taxa de todas respostas (O vosso KPI simulado da estatística) contando e caçando com luvas microscópicas e vasculhando nas águas da base a quantidade bruta e nua em que o teu chato cliente de facto teve as bolas e vos devolveu nos prazos, ou foi respondido a tempo, quer aceitou, rejeitou, e deu resposta de volta das linhas!
        const pedidosRespondidos = mockPedidos.filter(p => p.status === 'CLIENT_RESPONDED' || p.status === 'ACCEPTED' || p.status === 'REJECTED');
        // Estou a moer as cabeças numa máquina pesadíssima a fazer rolos do cálculo da vossa bela percentagem real de conversão em respostas! Cortar os números inteiros e limpos à taxa da tua fabulosa e imensa Aksanti e fechar e encerar sem rodeios as portas num fecho a redondo "E que te devolva na fita apenas as redondas e perfeitas casas ao Math.Round e inteirinhos aos 100!"
        const taxaMock = Math.round((pedidosRespondidos.length / mockPedidos.length) * 100);
        // Estou a passar a água a escaldar da esfregona nas velhas memórias, a esborratar à pressa e com os dedos a borrar aquele e antigo letreiro mentiroso chumbado falso do "98%" antigo do chão nas velhas chapas da parede! Puxo dele lá e pego naquele belo letreiro e pumba! A pendurar ali o teu glorioso número final com valor % com que os belos dados quentes do sangue e vivos me entregaram de verdade nas mão! O Teu Taxa Mock cru lá na rua!
        if(domTaxaResposta) domTaxaResposta.textContent = `${taxaMock}%`;
    }

    // Estou a caçar à espingarda, a varrer no crivo da tua enorme grelha com a lupa, para pescar e matar num a um todos e belos elementos quadrados da imponente tabela de cima do painel enorme, aos quais tu lá colaste com cuspo da boca aquela tua bem velhinha e obsoleta animação da roupa antiga (Da classe do tal animacao-entrar na altura)... Eu decidi dar cabo deles um a um e puxá-los, atirando à cara deles a tal vida de atuar espetáculos aos seus fantásticos deslizes nos slides gloriosos e lindos vindos lá daquelas ideias roubadas à beleza natural da ISAF da grande Cacimbo ali!
    document.querySelectorAll('.animacao-entrar').forEach(cartaoAksanti => {
        // Estou a despistar rápido, a arrancar lhes bruscamente e apagar com garras essas mesquinhas e aborrecidas fardas nas roupas das antiquadas, velhas marcas e animações passadas que estavam lá manchadas sem cor ali cravadas nas tuas etiquetas das costas deles e removo num safanão (Remove)!
        cartaoAksanti.classList.remove('animacao-entrar');
        // Estou a forjar a sua bela honra nas glórias a passá-los a ferros da roupa ao de enfeitar à purista. Agora forço a ataviar no duro em cima do mesmo cartão num fabuloso e esvoaçante manto vestido no corpo de seda de pura cor clara magia da majestosa ISAF Cacimbo! Uma maravilha imponente! Atiro do céu e prego com chapas do CSS no topo e fundo da 'deslizar-cima-isaf', que ao correr magicamente na vista te escorrega suave e belamente aos ventos da brisa e que suave levitam puxados invisível das raízes por cima a crescer vinda forte desde as raízes enterradas do chão sujo de encontro aos teus lindos e puros olhos na janela dos clientes!
        cartaoAksanti.classList.add('deslizar-cima-isaf');
    });
});
