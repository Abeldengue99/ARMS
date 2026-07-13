// Estou a criar o componente de Modal reutilizável que vai ser usado em toda a plataforma ARMS
// Estou a guardar a referência do modal que está aberto de momento para poder fechá-lo depois
let modalAberto = null;
let modalAoFechar = null;

// Estou a criar a função principal que abre e mostra um modal na tela com título e conteúdo dinâmico
function abrirModal(titulo, conteudoHTML, opcoes = {}) {
    const aoFechar = typeof opcoes.aoFechar === 'function' ? opcoes.aoFechar : null;

    // Estou a fechar qualquer modal que já esteja aberto antes de criar um novo para evitar sobreposições
    if (modalAberto) fecharModal();
    modalAoFechar = aoFechar;

    // Estou a criar a cortina escura semitransparente que cobre toda a página por trás do modal
    const fundo = document.createElement('div');
    // Estou a dar-lhe um ID único para poder encontrá-lo e removê-lo depois
    fundo.id = 'modal-fundo-aksanti';
    // Estou a aplicar os estilos da cortina escura que cobre o ecrã inteiro
    fundo.className = 'modal-fundo-aksanti';

    // Estou a definir a largura máxima do modal baseado nas opções recebidas ou usar 520px como padrão
    const larguraMax = opcoes.largura || '520px';

    // Estou a criar a caixa branca do modal que vai conter o título, o conteúdo e os botões
    const caixa = document.createElement('div');
    // Estou a aplicar os estilos visuais do modal seguindo o design system Aksanti (cantos redondos, sombra, fundo branco)
    caixa.className = 'modal-caixa-aksanti';
    caixa.style.setProperty('--modal-largura', larguraMax);

    // Estou a construir o HTML interno do modal com o cabeçalho, o corpo e o botão de fechar
    caixa.innerHTML = `
        <!-- Estou a criar o cabeçalho do modal com o título e o botão X para fechar -->
        <div class="modal-cabecalho-aksanti">
            <h3 class="modal-titulo-aksanti">${titulo}</h3>
            <button id="modal-btn-fechar" class="modal-fechar-aksanti" type="button" aria-label="Fechar">&times;</button>
        </div>
        <!-- Estou a criar o corpo do modal onde vai o conteúdo dinâmico recebido por parâmetro -->
        <div class="modal-corpo-aksanti" id="modal-corpo-aksanti">
            ${conteudoHTML}
        </div>
    `;

    // Estou a juntar a caixa do modal à cortina escura
    fundo.appendChild(caixa);
    // Estou a colocar toda a estrutura no corpo da página para aparecer por cima de tudo
    document.body.appendChild(fundo);
    // Estou a guardar a referência do modal aberto para poder fechá-lo depois
    modalAberto = fundo;

    // Estou a ligar o botão X para fechar o modal quando clicado
    document.getElementById('modal-btn-fechar').addEventListener('click', fecharModal);

    // Estou a fechar o modal se o utilizador clicar na cortina escura fora da caixa branca
    fundo.addEventListener('click', (evento) => {
        // Estou a verificar se o clique foi directamente na cortina e não dentro da caixa do modal
        if (evento.target === fundo) fecharModal();
    });

    // Estou a fechar o modal se o utilizador carregar na tecla Escape do teclado
    document.addEventListener('keydown', fecharComEscape);
}

// Estou a criar a função que fecha e remove o modal da página de forma limpa
function fecharModal() {
    const aoFechar = modalAoFechar;

    // Estou a verificar se existe algum modal aberto antes de tentar fechar
    if (modalAberto) {
        // Estou a remover o modal inteiro do corpo da página
        modalAberto.remove();
        // Estou a limpar a referência do modal aberto
        modalAberto = null;
    }
    modalAoFechar = null;
    // Estou a remover o ouvinte da tecla Escape para não acumular ouvintes a cada abertura de modal
    document.removeEventListener('keydown', fecharComEscape);

    if (aoFechar) {
        aoFechar();
    }
}

// Estou a criar a função auxiliar que escuta a tecla Escape para fechar o modal
function fecharComEscape(evento) {
    // Estou a verificar se a tecla pressionada é o Escape
    if (evento.key === 'Escape') fecharModal();
}

// Estou a criar uma função de conveniência para mostrar um modal de confirmação com dois botões (Sim/Não)
function confirmarAcao(titulo, mensagem, aoConfirmar) {
    // Estou a construir o conteúdo do modal de confirmação com a mensagem e os dois botões
    const conteudo = `
        <p style="color: var(--texto-secundario); margin-bottom: 24px;">${mensagem}</p>
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-primario" id="modal-btn-confirmar">Confirmar</button>
        </div>
    `;
    // Estou a abrir o modal com o conteúdo de confirmação que acabei de construir
    abrirModal(titulo, conteudo, { largura: '420px' });
    // Estou a ligar o botão de confirmação à função recebida e a fechar o modal depois
    document.getElementById('modal-btn-confirmar').addEventListener('click', () => {
        // Estou a executar a acção de confirmação recebida por parâmetro
        aoConfirmar();
        // Estou a fechar o modal após a confirmação
        fecharModal();
    });
}

function escaparModalHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function mostrarMensagem(titulo, mensagem, opcoes = {}) {
    const texto = escaparModalHtml(mensagem || '').replace(/\r?\n/g, '<br>');
    const botaoTexto = escaparModalHtml(opcoes.botaoTexto || 'OK');

    const conteudo = `
        <p class="modal-mensagem-texto">${texto}</p>
        <div class="modal-mensagem-acoes">
            <button type="button" class="btn btn-primario" id="modal-btn-mensagem-ok">${botaoTexto}</button>
        </div>
    `;

    abrirModal(escaparModalHtml(titulo || 'Mensagem'), conteudo, {
        largura: opcoes.largura || '440px',
        aoFechar: opcoes.aoFechar
    });

    if (modalAberto) {
        modalAberto.classList.add('modal-mensagem-fundo');
    }

    const botaoOk = document.getElementById('modal-btn-mensagem-ok');
    if (botaoOk) {
        botaoOk.addEventListener('click', fecharModal);
        botaoOk.focus();
    }
}

if (!window.alertNativoAksanti && typeof window.alert === 'function') {
    window.alertNativoAksanti = window.alert.bind(window);
}

window.alert = function(mensagem) {
    if (document.body && typeof mostrarMensagem === 'function') {
        mostrarMensagem('Atenção', mensagem);
        return;
    }

    if (window.alertNativoAksanti) {
        window.alertNativoAksanti(mensagem);
    }
};
