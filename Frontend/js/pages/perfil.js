
        let perfilUtilizadorAtual = null;

        const valorPerfil = (valor) => {
            if (valor === null || valor === undefined || String(valor).trim() === '') {
                return '—';
            }

            return String(valor);
        };

        const campoPerfil = (id, valor) => {
            const campo = document.getElementById(id);
            if (campo) {
                const valorVazio = valor === null || valor === undefined || String(valor).trim() === '';
                const adminPodeEditar = perfilUtilizadorAtual && perfilUtilizadorAtual.admin && campo.dataset.adminEditavel === 'true';
                const valorFinal = adminPodeEditar && valorVazio ? '' : valorPerfil(valor);

                campo.value = valorFinal;
                campo.title = valorFinal;
            }
        };

        const tipoAcessoLegivel = (tipo) => {
            if (tipo === 'CLIENT') return 'Empresa / Cliente Final';
            if (tipo === 'AKSANTI') return 'Equipa Interna';
            return valorPerfil(tipo);
        };

        function mostrarMensagem(titulo, mensagem) {
            alert(`${titulo}\n\n${mensagem}`);
        }

        function escaparHtml(texto) {
            if (!texto) return '';
            const div = document.createElement('div');
            div.textContent = texto;
            return div.innerHTML;
        }

        function preencherPerfil(utilizador) {
            if (!utilizador) return;

            perfilUtilizadorAtual = utilizador;
            campoPerfil('perfil-nome', utilizador.nome);
            campoPerfil('perfil-email', utilizador.email);
            campoPerfil('perfil-cargo', utilizador.cargo);
            campoPerfil('perfil-tipo', tipoAcessoLegivel(utilizador.tipo));
            campoPerfil('perfil-permissao', utilizador.admin ? 'Administrador' : 'Utilizador');
            campoPerfil('perfil-estado', utilizador.ativo === false ? 'Desativada' : 'Ativa');
            campoPerfil('perfil-idioma', utilizador.locale || 'pt-PT');
            campoPerfil('perfil-id', utilizador.id);

            const blocoDept = document.getElementById('bloco-perfil-departamentos');
            const listaDept = document.getElementById('perfil-departamentos-lista');
            let areasArray = [];
            if (utilizador.area_ids) {
                if (Array.isArray(utilizador.area_ids)) {
                    areasArray = utilizador.area_ids;
                } else if (typeof utilizador.area_ids === 'string') {
                    try {
                        areasArray = JSON.parse(utilizador.area_ids);
                        if (!Array.isArray(areasArray)) areasArray = [];
                    } catch (e) {
                        areasArray = [];
                    }
                }
            }

            if (areasArray.length > 0) {
                blocoDept.hidden = false;
                listaDept.innerHTML = '<span style="color:var(--texto-secundario); font-size:0.9rem;">A carregar departamentos...</span>';
                
                fetch('api/formulario-dados.php')
                    .then(res => res.json())
                    .then(dados => {
                        if (dados.sucesso && dados.areas) {
                            const nomes = areasArray.map(id => {
                                const d = dados.areas.find(a => a.id === id);
                                return d ? d.name : '';
                            }).filter(n => n);

                            if (nomes.length > 0) {
                                listaDept.innerHTML = nomes.map(n => `<span style="background:var(--fundo-primario); padding:6px 12px; border-radius:6px; border:1px solid var(--borda-suave); font-weight:500; color:var(--texto-principal);">${escaparHtml(n)}</span>`).join('');
                            } else {
                                listaDept.innerHTML = '<span style="color:var(--texto-secundario); font-size:0.9rem;">Nenhum departamento encontrado.</span>';
                            }
                        }
                    })
                    .catch(() => {
                        listaDept.innerHTML = '<span style="color:var(--cor-perigo); font-size:0.9rem;">Erro ao carregar departamentos.</span>';
                    });
            } else {
                blocoDept.hidden = true;
            }

            const empresaCard = document.getElementById('perfil-empresa-card');
            const cliente = utilizador.cliente || null;

            if (empresaCard) {
                empresaCard.hidden = !cliente;
            }

            if (cliente) {
                campoPerfil('perfil-empresa', cliente.nome);
                campoPerfil('perfil-empresa-nif', cliente.nif);
                campoPerfil('perfil-empresa-localizacao', cliente.localizacao);
                campoPerfil('perfil-empresa-email', cliente.email);
                campoPerfil('perfil-empresa-estado', cliente.ativo === false ? 'Inativa' : 'Ativa');
                campoPerfil('perfil-empresa-id', cliente.id);
            }

            aplicarPermissoesPerfil(utilizador);
        }

        function aplicarPermissoesPerfil(utilizador) {
            const adminPodeEditar = Boolean(utilizador && utilizador.admin);
            const camposEditaveis = document.querySelectorAll('[data-admin-editavel="true"]');
            const acoesAdmin = document.getElementById('perfil-acoes-admin');
            const nota = document.getElementById('perfil-nota-permissoes');

            camposEditaveis.forEach((campo) => {
                if (adminPodeEditar) {
                    campo.removeAttribute('readonly');
                    return;
                }

                campo.setAttribute('readonly', 'readonly');
            });

            if (acoesAdmin) {
                acoesAdmin.hidden = !adminPodeEditar;
            }

            if (nota) {
                nota.textContent = adminPodeEditar
                    ? 'Como administrador do sistema, pode editar os dados pessoais básicos desta conta. Os dados de acesso, estado e empresa permanecem controlados pelos módulos administrativos.'
                    : 'Os dados pessoais são geridos pela administração. O próprio utilizador pode alterar apenas a senha de acesso.';
            }
        }

        function carregarPerfil() {
            fetch('api/sessao.php?acao=verificar')
                .then((res) => res.json())
                .then((data) => {
                    if (data.sucesso && data.autenticado && data.utilizador) {
                        localStorage.setItem('arms_utilizador_logado', 'true');
                        localStorage.setItem('arms_utilizador_dados', JSON.stringify(data.utilizador));
                        preencherPerfil(data.utilizador);
                        return;
                    }

                    localStorage.removeItem('arms_utilizador_logado');
                    localStorage.removeItem('arms_utilizador_dados');
                    window.location.href = 'index.html';
                })
                .catch(() => {
                    mostrarMensagem('Atenção', 'Não foi possível confirmar a sessão. Inicie sessão novamente.');
                    localStorage.removeItem('arms_utilizador_logado');
                    localStorage.removeItem('arms_utilizador_dados');
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1200);
                });
        }

        document.addEventListener('DOMContentLoaded', carregarPerfil);

        window.guardarPerfilAdmin = function() {
            if (!perfilUtilizadorAtual || !perfilUtilizadorAtual.admin) {
                mostrarMensagem('Atenção', 'Apenas administradores do sistema podem editar dados pessoais.');
                return;
            }

            const btn = document.getElementById('btn-guardar-perfil-admin');
            const dados = {
                full_name: document.getElementById('perfil-nome').value.trim(),
                email: document.getElementById('perfil-email').value.trim(),
                cargo: document.getElementById('perfil-cargo').value.trim()
            };

            if (!dados.full_name || !dados.email) {
                mostrarMensagem('Atenção', 'Nome completo e e-mail são obrigatórios.');
                return;
            }

            if (dados.cargo.length > 160) {
                mostrarMensagem('Atenção', 'O cargo deve ter no máximo 160 caracteres.');
                return;
            }

            btn.textContent = 'A guardar...';
            btn.disabled = true;

            fetch('api/atualizar-perfil.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            })
            .then((res) => res.json())
            .then((data) => {
                btn.textContent = 'Guardar Dados Pessoais';
                btn.disabled = false;

                if (!data.sucesso) {
                    mostrarMensagem('Atenção', data.erro || 'Erro ao guardar os dados pessoais.');
                    return;
                }

                if (data.utilizador) {
                    localStorage.setItem('arms_utilizador_dados', JSON.stringify(data.utilizador));
                    preencherPerfil(data.utilizador);
                }

                mostrarMensagem('Sucesso', data.mensagem || 'Dados pessoais atualizados com sucesso.');
            })
            .catch(() => {
                btn.textContent = 'Guardar Dados Pessoais';
                btn.disabled = false;
                mostrarMensagem('Atenção', 'Erro de comunicação com o servidor.');
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            const btnGuardarPerfil = document.getElementById('btn-guardar-perfil-admin');
            if (btnGuardarPerfil) {
                btnGuardarPerfil.addEventListener('click', window.guardarPerfilAdmin);
            }
        });


        window.alterarSenha = function() {
            if (!perfilUtilizadorAtual || !perfilUtilizadorAtual.id) {
                mostrarMensagem('Atenção', 'A sessão ainda não foi confirmada. Inicie sessão novamente.');
                return;
            }

            const atual = document.getElementById('senha-atual').value.trim();
            const nova = document.getElementById('nova-senha').value.trim();
            const confirmar = document.getElementById('confirmar-senha').value.trim();

            if (!atual || !nova || !confirmar) {
                mostrarMensagem('Atenção', 'Preencha a senha atual, a nova senha e a confirmação.');
                return;
            }

            if (nova.length < 6) {
                mostrarMensagem('Atenção', 'A nova senha deve ter pelo menos 6 caracteres.');
                return;
            }

            if (nova !== confirmar) {
                mostrarMensagem('Atenção', 'A nova senha e a confirmação não coincidem.');
                return;
            }

            const btn = document.getElementById('btn-salvar-senha');
            btn.textContent = 'Aguarde...';
            btn.disabled = true;

            fetch('api/alterar-senha.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ senha_atual: atual, nova_senha: nova, confirmar_senha: confirmar })
            })
            .then(r => r.json())
            .then(data => {
                btn.textContent = 'Alterar Senha';
                btn.disabled = false;
                if (data.sucesso) {
                    mostrarMensagem('Sucesso', data.mensagem || 'Senha alterada com sucesso.');
                    document.getElementById('form-alterar-senha').reset();
                    try {
                        const dados = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
                        dados.senha_expirada = false;
                        dados.password_expired = false;
                        localStorage.setItem('arms_utilizador_dados', JSON.stringify(dados));
                    } catch (erroLocalStorage) {}
                } else {
                    mostrarMensagem('Atenção', data.erro || 'Erro ao alterar a senha.');
                }
            })
            .catch(err => {
                btn.textContent = 'Alterar Senha';
                btn.disabled = false;
                mostrarMensagem('Atenção', 'Erro de comunicação com o servidor.');
            });
        };
