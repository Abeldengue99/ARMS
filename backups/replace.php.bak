<?php
$content = file_get_contents("pedido-detalhe.html");
$old = "                    if (p.status !== 'DRAFT') {
                        document.getElementById('btn-editar-pedido').style.display = 'none';
                        document.getElementById('btn-enviar-pedido').style.display = 'none';
                    }";

$new = "                    const ud = JSON.parse(localStorage.getItem('arms_utilizador_dados') || '{}');
                    const isClientRole = ud.tipo === 'CLIENT';

                    const statusEditaveis = ['DRAFT', 'CLIENT_RESPONDED', 'REJECTED'];
                    if (!statusEditaveis.includes(p.status) || isClientRole) {
                        document.getElementById('btn-editar-pedido').style.display = 'none';
                    } else {
                        document.getElementById('btn-editar-pedido').style.display = 'inline-block';
                    }

                    if (p.status !== 'DRAFT') {
                        document.getElementById('btn-enviar-pedido').style.display = 'none';
                    }";

$content = str_replace(str_replace("\r\n", "\n", $old), $new, str_replace("\r\n", "\n", $content));
file_put_contents("pedido-detalhe.html", $content);
echo "Done";
?>
