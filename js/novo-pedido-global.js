// Lógica Global para Criar Novo Pedido
window.abrirModalNovoPedido = function() {
    let utilizadorAtual = {};
    try { utilizadorAtual = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}'); } catch(e) {}

    const modoCliente = utilizadorAtual.tipo === 'CLIENT';
    const modoAdmin = utilizadorAtual.admin === true;
    const modoColaborador = !modoCliente && !modoAdmin;
    const mostrarCamposCliente = modoAdmin;
    const tituloModal = modoColaborador ? 'Criar Pedido Interno' : 'Criar Novo Pedido';
    const labelArea = modoAdmin ? 'Área / Departamento' : 'Departamento interno';
    const destinoInterno = 'Pedido Interno';
    const textoDeadline = modoAdmin
        ? 'Data limite para resposta. Depois deste prazo, o cliente recebe uma notificação de pedido urgente.'
        : 'Data limite para resposta. Depois deste prazo, a área responsável recebe uma notificação de pedido urgente.';
    const estiloCamposCliente = mostrarCamposCliente ? '' : 'display:none;';
    const estiloDestinoInterno = mostrarCamposCliente ? 'display:none;' : '';
    const estiloTipoDestino = modoAdmin ? '' : 'display:none;';

    const formHTML = 
        <div class="formulario-grid">
            <div class="largura-total">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Título do Pedido <span style="color: var(--cor-perigo);">*</span></label>
                <input type="text" id="campo-titulo" class="input-controlo" placeholder="Ex: Consultoria Fiscal 2026">
            </div>
            <div id="grupo-tipo-destino" style=" + estiloTipoDestino + ">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Enviar pedido para <span style="color: var(--cor-perigo);">*</span></label>
                <select id="campo-destino-tipo" class="input-controlo">
                    <option value="CLIENT" selected>Cliente</option>
                    <option value="AKSANTI">Pedido Interno</option>
                </select>
            </div>
            <div id="grupo-recipient-scope" style=" + estiloTipoDestino + ">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Destinatário(s) <span style="color: var(--cor-perigo);">*</span></label>
                <select id="campo-recipient-scope" class="input-controlo">
                    <option value="DEPARTMENT" selected>Departamento(s) inteiro(s)</option>
                    <option value="USER">Utilizador específico</option>
                </select>
            </div>
            <div id="grupo-area" class="largura-total">
                <label id="label-area-pedido" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);"> + labelArea +  <span style="color: var(--cor-perigo);">*</span></label>
                <select id="campo-area" class="input-controlo" multiple style="height: auto; min-height: 120px;">
                    <option value="">A carregar departamentos...</option>
                </select>
                <span style="font-size: 0.8rem; color: var(--texto-secundario);">Mantenha premido Ctrl (Windows) ou Cmd (Mac) para selecionar vários.</span>
            </div>
            <div class="largura-total" id="bloco-destino-interno" style=" + estiloDestinoInterno + ">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Destino</label>
                <div style="border: 1px solid #fed7aa; background: #fff7ed; color: #9a3412; border-radius: 12px; padding: 14px 16px; font-weight: 700;">
                    <span id="texto-destino-interno"> + destinoInterno + </span>
                </div>
            </div>
            <div id="grupo-cliente-pedido" style=" + estiloCamposCliente + ">
                <label id="label-cliente-pedido" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Cliente <span style="color: var(--cor-perigo);">*</span></label>
                <select id="campo-cliente" class="input-controlo">
                    <option value="">A carregar clientes...</option>
                </select>
            </div>
            <div id="grupo-membro-aksanti" style="display:none;">
                <label id="label-membro-aksanti" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Destinatário específico <span style="color: var(--cor-perigo);">*</span></label>
                <select id="campo-membro-aksanti" class="input-controlo">
                    <option value="">Selecionar destinatário específico</option>
                </select>
            </div>
            <div id="grupo-email-cliente" style=" + estiloCamposCliente + ">
                <label id="label-email-cliente" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Email do Cliente <span style="color: var(--cor-perigo);">*</span></label>
                <input type="email" id="campo-email-cliente" class="input-controlo" placeholder="financas@empresa.co.ao">
            </div>
            <div class="campo-deadline-destaque">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Data de Deadline <span style="color: var(--cor-perigo);">*</span></label>
                <input type="date" id="campo-deadline" class="input-controlo">
                <span class="deadline-ajuda"> + textoDeadline + </span>
            </div>
            <div class="largura-total">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--texto-principal);">Descrição <span style="color: var(--cor-perigo);">*</span></label>
                <textarea id="campo-descricao" class="input-controlo-area" placeholder="Descreva os detalhes do pedido..."></textarea>
            </div>
        </div>
        <div id="modal-feedback" style="display:none; padding: 12px 16px; border-radius: var(--raio-borda); margin-top: 16px; font-size: 0.9rem;"></div>
        <div class="formulario-acoes">
            <button class="btn btn-secundario" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-primario" id="btn-submeter-pedido">Guardar Rascunho</button>
        </div>
    ;
    abrirModal(tituloModal, formHTML, { largura: modoAdmin ? '640px' : '580px' });

    fetch('api/formulario-dados.php')
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                const selArea = document.getElementById('campo-area');
                selArea.innerHTML = '';
                data.areas.forEach(a => {
                    selArea.innerHTML += '<option value="' + a.id + '">' + a.name + ' (' + a.code + ')</option>';
                });

                const selCliente = document.getElementById('campo-cliente');
                const emailInput = document.getElementById('campo-email-cliente');
                const selDestinoTipo = document.getElementById('campo-destino-tipo');
                const selRecipientScope = document.getElementById('campo-recipient-scope');
                const selMembroAksanti = document.getElementById('campo-membro-aksanti');
                const grupoCliente = document.getElementById('grupo-cliente-pedido');
                const grupoMembroAksanti = document.getElementById('grupo-membro-aksanti');
                const grupoArea = document.getElementById('grupo-area');
                const grupoEmailCliente = document.getElementById('grupo-email-cliente');
                const labelClientePedido = document.getElementById('label-cliente-pedido');
                const labelEmailCliente = document.getElementById('label-email-cliente');

                if ((data.modo_cliente || data.modo_colaborador) && data.clientes.length) {
                    const clienteAtual = data.clientes[0];
                    const destino = 'Pedido Interno';
                    const destinoEl = document.getElementById('texto-destino-interno');
                    if (destinoEl) destinoEl.textContent = destino;

                    selCliente.innerHTML = '<option value="' + clienteAtual.id + '" selected>' + destino + '</option>';
                    selCliente.disabled = true;
                    emailInput.value = data.modo_colaborador
                        ? (clienteAtual.primary_email || 'admin@aksanti.xyz')
                        : (utilizadorAtual.email || clienteAtual.primary_email || '');
                    emailInput.readOnly = true;
                } else {
                    selCliente.innerHTML = '<option value="">Selecionar cliente</option>';
                    data.clientes.forEach(c => {
                        selCliente.innerHTML += '<option value="' + c.id + '" data-email="' + c.primary_email + '">' + c.name + '</option>';
                    });
                }

                if (selMembroAksanti) {
                    selMembroAksanti.innerHTML = '<option value="">Selecionar destinatário específico</option>';
                    (data.membros_aksanti || []).forEach(m => {
                        const cargo = m.cargo ? ' - ' + m.cargo : '';
                        const perfil = m.is_admin ? ' (Super Admin)' : '';
                        selMembroAksanti.innerHTML += '<option value="' + m.id + '" data-email="' + m.email + '">' + m.full_name + cargo + perfil + '</option>';
                    });
                }

                selCliente.addEventListener('change', () => {
                    const opcao = selCliente.options[selCliente.selectedIndex];
                    if (opcao.dataset.email) emailInput.value = opcao.dataset.email;
                });

                if (selMembroAksanti) {
                    selMembroAksanti.addEventListener('change', () => {
                        const opcao = selMembroAksanti.options[selMembroAksanti.selectedIndex];
                        emailInput.value = opcao && opcao.dataset.email ? opcao.dataset.email : '';
                    });
                }

                const atualizarDestinoPedido = () => {
                    if (!modoAdmin || !selDestinoTipo) return;

                    const destinoAksanti = selDestinoTipo.value === 'AKSANTI';
                    const scope = selRecipientScope ? selRecipientScope.value : 'DEPARTMENT';
                    
                    if (grupoCliente) grupoCliente.style.display = destinoAksanti ? 'none' : '';
                    
                    if (scope === 'USER' && destinoAksanti) {
                        if (grupoMembroAksanti) grupoMembroAksanti.style.display = '';
                    } else {
                        if (grupoMembroAksanti) grupoMembroAksanti.style.display = 'none';
                    }

                    if (grupoEmailCliente) grupoEmailCliente.style.display = '';
                    if (labelClientePedido) labelClientePedido.innerHTML = 'Cliente <span style="color: var(--cor-perigo);">*</span>';
                    if (labelEmailCliente) labelEmailCliente.innerHTML = destinoAksanti
                        ? 'Email do Destinatario <span style="color: var(--cor-perigo);">*</span>'
                        : 'Email do Cliente <span style="color: var(--cor-perigo);">*</span>';

                    emailInput.readOnly = destinoAksanti && scope !== 'USER';
                    if (destinoAksanti) {
                        selCliente.value = '';
                        if (scope === 'USER') {
                            const opcao = selMembroAksanti.options[selMembroAksanti.selectedIndex];
                            emailInput.value = opcao && opcao.dataset.email ? opcao.dataset.email : '';
                        }
                    } else {
                        if (selMembroAksanti) selMembroAksanti.value = '';
                        const opcao = selCliente.options[selCliente.selectedIndex];
                        emailInput.value = opcao && opcao.dataset.email ? opcao.dataset.email : emailInput.value;
                    }
                };

                if (selDestinoTipo) selDestinoTipo.addEventListener('change', atualizarDestinoPedido);
                if (selRecipientScope) selRecipientScope.addEventListener('change', atualizarDestinoPedido);
                atualizarDestinoPedido();
            }
        });

    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('campo-deadline').min = hoje;

    document.getElementById('btn-submeter-pedido').addEventListener('click', () => {
        const feedback = document.getElementById('modal-feedback');
        const elArea = document.getElementById('campo-area');
        const selScope = document.getElementById('campo-recipient-scope');
        const areaIdsSelecionados = Array.from(elArea.selectedOptions).map(opt => opt.value).filter(v => v !== "");
        const scope = selScope ? selScope.value : 'DEPARTMENT';

        const dados = {
            titulo:       document.getElementById('campo-titulo').value.trim(),
            descricao:    document.getElementById('campo-descricao').value.trim(),
            area_id:      areaIdsSelecionados[0] || "",
            area_ids:     areaIdsSelecionados,
            client_id:    document.getElementById('campo-cliente').value,
            client_email: document.getElementById('campo-email-cliente').value.trim(),
            destination_type: modoAdmin ? document.getElementById('campo-destino-tipo').value : 'AKSANTI',
            recipient_scope: scope,
            recipient_user_id: modoAdmin ? document.getElementById('campo-membro-aksanti').value : '',
            deadline:     document.getElementById('campo-deadline').value
        };

        const faltamCamposBase = !dados.titulo || !dados.descricao || !dados.area_id || !dados.deadline;
        const faltamDadosDestino = modoAdmin && (
            dados.destination_type === 'AKSANTI'
                ? ((dados.recipient_scope === 'USER' && !dados.recipient_user_id) || !dados.client_email)
                : (!dados.client_id || !dados.client_email)
        );
        if (faltamCamposBase || faltamDadosDestino) {
            feedback.style.display = 'block';
            feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
            feedback.style.color = '#ef4444';
            feedback.textContent = 'Preencha todos os campos obrigatórios.';
            return;
        }

        feedback.style.display = 'block';
        feedback.style.backgroundColor = 'rgba(229,138,19,0.1)';
        feedback.style.color = 'var(--aksanti-gold)';
        feedback.textContent = 'A guardar rascunho...';

        fetch('api/criar-pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(res => res.json())
        .then(resultado => {
            if (resultado.sucesso) {
                feedback.style.backgroundColor = 'rgba(34,197,94,0.1)';
                feedback.style.color = '#22c55e';
                feedback.textContent = resultado.mensagem + ' Ref: ' + resultado.pedido.reference;
                setTimeout(() => {
                    fecharModal();
                    if (!modoAdmin && resultado.pedido && resultado.pedido.reference) {
                        window.location.href = 'pedido-detalhe.html?ref=' + encodeURIComponent(resultado.pedido.reference);
                    } else {
                        location.reload();
                    }
                }, 1500);
            } else {
                feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
                feedback.style.color = '#ef4444';
                feedback.textContent = 'Erro: ' + resultado.erro;
            }
        })
        .catch(err => {
            feedback.style.backgroundColor = 'rgba(239,68,68,0.1)';
            feedback.style.color = '#ef4444';
            feedback.textContent = 'Erro de rede ao submeter o pedido.';
        });
    });
};
