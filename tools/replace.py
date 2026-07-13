import sys

with open("pedido-detalhe.html", "r", encoding="utf-8", errors="ignore") as f:
    content = f.read()

old_str = """                    if (p.status !== 'DRAFT') {
                        document.getElementById('btn-editar-pedido').style.display = 'none';
                        document.getElementById('btn-enviar-pedido').style.display = 'none';
                    }

                    // Verifica se o Utilizador logado"""

new_str = """                    const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
                    const isClientRole = ud.tipo === 'CLIENT';

                    const statusEditaveis = ['DRAFT', 'CLIENT_RESPONDED', 'REJECTED'];
                    if (!statusEditaveis.includes(p.status) || isClientRole) {
                        document.getElementById('btn-editar-pedido').style.display = 'none';
                    } else {
                        document.getElementById('btn-editar-pedido').style.display = 'inline-block';
                    }

                    if (p.status !== 'DRAFT') {
                        document.getElementById('btn-enviar-pedido').style.display = 'none';
                    }

                    // Verifica se o Utilizador logado"""

content = content.replace(old_str, new_str)

with open("pedido-detalhe.html", "w", encoding="utf-8") as f:
    f.write(content)

print("Done")
