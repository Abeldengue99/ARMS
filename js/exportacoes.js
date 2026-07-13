window.ArmsExportacoes = (() => {
    function texto(valor) {
        return String(valor ?? '');
    }

    function escaparHtml(valor) {
        return texto(valor).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function nomeFicheiro(base, extensao) {
        const data = new Date().toISOString().slice(0, 10);
        const limpo = texto(base || 'relatorio-arms')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/gi, '-')
            .replace(/^-+|-+$/g, '')
            .toLowerCase();

        return `${limpo}-${data}.${extensao}`;
    }

    function normalizarLinhas(linhas, colunas) {
        return linhas.map((linha) => colunas.map((coluna) => {
            if (typeof coluna.valor === 'function') {
                return coluna.valor(linha);
            }

            return linha[coluna.chave];
        }));
    }

    function montarFiltrosHtml(filtros = {}) {
        const entradas = Object.entries(filtros).filter(([, valor]) => texto(valor).trim());

        if (!entradas.length) {
            return '<span>Sem filtros aplicados</span>';
        }

        return entradas.map(([nome, valor]) => `<span>${escaparHtml(nome)}: ${escaparHtml(valor)}</span>`).join('');
    }

    function montarTabelaHtml(colunas, linhasNormalizadas) {
        return `
            <table>
                <thead>
                    <tr>
                        ${colunas.map((coluna) => `<th>${escaparHtml(coluna.titulo)}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${linhasNormalizadas.map((linha) => `
                        <tr>
                            ${linha.map((valor) => `<td>${escaparHtml(valor || '-')}</td>`).join('')}
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function montarDocumento({ titulo, subtitulo, colunas, linhas, filtros, modo }) {
        const linhasNormalizadas = normalizarLinhas(linhas, colunas);
        const geradoEm = new Date().toLocaleString('pt-PT');

        return `<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>${escaparHtml(titulo)}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; color: #18181b; font-family: "Segoe UI", Arial, sans-serif; background: #ffffff; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 20px; margin-bottom: 22px; border-bottom: 3px solid #e58a13; }
        .brand { color: #e58a13; font-size: 28px; font-weight: 900; letter-spacing: 0; }
        h1 { margin: 0; color: #111827; font-size: 25px; line-height: 1.25; }
        .subtitle { margin-top: 5px; color: #64748b; font-size: 13px; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; color: #475569; font-size: 12px; }
        .meta span { padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th { padding: 11px 12px; color: #374151; text-align: left; background: #f8fafc; border: 1px solid #e5e7eb; font-weight: 800; text-transform: uppercase; }
        td { padding: 10px 12px; border: 1px solid #edf2f7; vertical-align: top; }
        tbody tr:nth-child(even) { background: #fbfdff; }
        .footer { margin-top: 28px; padding-top: 14px; color: #94a3b8; font-size: 11px; text-align: center; border-top: 1px solid #e2e8f0; }
        ${modo === 'excel' ? 'td, th { mso-number-format:"\\@"; }' : '@media print { body { padding: 18px; } }'}
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>${escaparHtml(titulo)}</h1>
            <div class="subtitle">${escaparHtml(subtitulo || 'Aksanti Request Management System')}</div>
        </div>
        <div class="brand">ARMS</div>
    </div>
    <div class="meta">
        <span>Gerado em: ${escaparHtml(geradoEm)}</span>
        <span>Total: ${linhas.length}</span>
        ${montarFiltrosHtml(filtros)}
    </div>
    ${montarTabelaHtml(colunas, linhasNormalizadas)}
    <div class="footer">ARMS - Aksanti Request Management System &copy; ${new Date().getFullYear()}</div>
</body>
</html>`;
    }

    function baixarExcel(opcoes) {
        const html = montarDocumento({ ...opcoes, modo: 'excel' });
        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const link = document.createElement('a');

        link.href = URL.createObjectURL(blob);
        link.download = nomeFicheiro(opcoes.nomeArquivo || opcoes.titulo, 'xls');
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    }

    function baixarPDF(opcoes) {
        const html = montarDocumento({ ...opcoes, modo: 'pdf' });
        const janela = window.open('', '_blank');

        if (!janela) {
            if (typeof mostrarMensagem === 'function') {
                mostrarMensagem('Atenção', 'Não foi possível abrir a janela de impressão. Verifique se o bloqueador de pop-ups está ativo.');
            } else {
                console.warn('Não foi possível abrir a janela de impressão. Verifique se o bloqueador de pop-ups está ativo.');
            }
            return;
        }

        janela.document.write(html + '<script>window.onload = function() { window.print(); }<\/script>');
        janela.document.close();
    }

    return {
        baixarExcel,
        baixarPDF
    };
})();
