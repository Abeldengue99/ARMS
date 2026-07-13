// Estou a criar a função principal de validação que verifica se todos os campos obrigatórios estão preenchidos
function validarFormulario(idDoFormulario) {
    // Estou a buscar o formulário pelo ID para poder percorrer os seus campos
    const formulario = document.getElementById(idDoFormulario);
    // Estou a verificar se o formulário existe na página antes de continuar
    if (!formulario) return true;

    // Estou a iniciar a variável que diz se o formulário está válido ou não
    let formularioValido = true;
    // Estou a limpar todos os erros anteriores antes de fazer uma nova validação
    limparErrosFormulario(idDoFormulario);

    // Estou a procurar todos os campos que têm o atributo 'required' (obrigatório) dentro do formulário
    const camposObrigatorios = formulario.querySelectorAll('[required]');

    // Estou a percorrer cada campo obrigatório para verificar se está preenchido
    camposObrigatorios.forEach(campo => {
        // Estou a verificar se o valor do campo está vazio (depois de remover espaços nas pontas)
        if (!campo.value.trim()) {
            // Estou a marcar o formulário como inválido porque encontrei um campo vazio
            formularioValido = false;
            // Estou a mostrar o erro visual neste campo específico
            mostrarErroCampo(campo, 'Este campo é obrigatório');
        }
    });

    // Estou a procurar campos de email para validar o formato do endereço
    const camposEmail = formulario.querySelectorAll('input[type="email"]');
    camposEmail.forEach(campo => {
        // Estou a verificar se o campo de email tem conteúdo e se está num formato válido
        if (campo.value.trim() && !validarFormatoEmail(campo.value)) {
            formularioValido = false;
            mostrarErroCampo(campo, 'Formato de e-mail inválido');
        }
    });

    // Estou a devolver o resultado final: true se tudo está bem, false se há erros
    return formularioValido;
}

// Estou a criar a função que mostra visualmente o erro debaixo de um campo específico
function mostrarErroCampo(campo, mensagem) {
    // Estou a pintar a borda do campo de vermelho para indicar visualmente que há um problema
    campo.style.borderColor = 'var(--cor-perigo)';
    // Estou a adicionar uma sombra vermelha suave à volta do campo para reforçar o aviso
    campo.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.15)';

    // Estou a criar o elemento de texto que vai mostrar a mensagem de erro debaixo do campo
    const erroElemento = document.createElement('span');
    // Estou a dar uma classe específica para poder limpar estes erros depois
    erroElemento.className = 'erro-validacao';
    // Estou a estilizar a mensagem de erro para ser pequena e vermelha
    erroElemento.style.cssText = 'color: var(--cor-perigo); font-size: 0.8rem; margin-top: 4px; display: block;';
    // Estou a colocar a mensagem de erro no elemento
    erroElemento.textContent = mensagem;

    // Estou a inserir a mensagem de erro logo depois do campo no HTML
    campo.parentNode.appendChild(erroElemento);
}

// Estou a criar a função que remove todos os erros visuais de um formulário
function limparErrosFormulario(idDoFormulario) {
    // Estou a buscar o formulário pelo ID
    const formulario = document.getElementById(idDoFormulario);
    if (!formulario) return;

    // Estou a remover todas as mensagens de erro que foram adicionadas anteriormente
    formulario.querySelectorAll('.erro-validacao').forEach(erro => erro.remove());

    // Estou a restaurar os estilos normais de todos os inputs que foram marcados com erro
    formulario.querySelectorAll('input, textarea, select').forEach(campo => {
        // Estou a devolver a borda original ao campo
        campo.style.borderColor = '';
        // Estou a remover a sombra vermelha de erro
        campo.style.boxShadow = '';
    });
}

// Estou a criar uma função auxiliar que verifica se um endereço de email tem o formato correcto
function validarFormatoEmail(email) {
    // Estou a usar uma expressão regular simples para verificar se o email tem o formato basico (algo@algo.algo)
    const padrao = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    // Estou a testar o email contra o padrão e devolver true ou false
    return padrao.test(email);
}
