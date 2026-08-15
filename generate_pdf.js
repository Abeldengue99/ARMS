const puppeteer = require('puppeteer-core');
const marked = require('marked');
const fs = require('fs');
const path = require('path');

function replaceImagesWithBase64(html) {
    return html.replace(/<img[^>]+src="([^">]+)"/g, (match, src) => {
        try {
            // Check if it's already a data URI
            if (src.startsWith('data:')) return match;
            
            // Clean up the path just in case
            let filePath = src.replace('file:///', '');
            filePath = decodeURIComponent(filePath);
            
            if (fs.existsSync(filePath)) {
                const ext = path.extname(filePath).toLowerCase().substring(1) || 'png';
                const base64 = fs.readFileSync(filePath, 'base64');
                return match.replace(src, `data:image/${ext};base64,${base64}`);
            } else {
                console.warn("Image not found: " + filePath);
            }
        } catch(e) {
            console.error("Error encoding image:", e);
        }
        return match;
    });
}

async function convert(mdPath, pdfPath) {
    const md = fs.readFileSync(mdPath, 'utf8');
    let htmlContent = marked.parse(md);
    
    htmlContent = replaceImagesWithBase64(htmlContent);
    
    // Wrap in a simple HTML template with some basic styling
    const fullHtml = `
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; color: #333; }
            h1, h2, h3 { color: #d97706; margin-top: 24px; }
            img { max-width: 100%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 20px 0; }
            code { background: #f4f4f4; padding: 2px 5px; border-radius: 4px; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 8px; overflow-x: auto; }
            blockquote { border-left: 4px solid #d97706; padding-left: 15px; color: #666; margin-left: 0; }
        </style>
    </head>
    <body>
        ${htmlContent}
    </body>
    </html>
    `;

    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        headless: true
    });
    const page = await browser.newPage();
    await page.setContent(fullHtml, { waitUntil: 'networkidle0' });
    await page.pdf({ path: pdfPath, format: 'A4', margin: { top: '20mm', bottom: '20mm', left: '20mm', right: '20mm' }});
    await browser.close();
    console.log(`Generated ${pdfPath}`);
}

async function main() {
    try {
        await convert('manual_utilizador.md', 'Manual_do_Utilizador_Admin.pdf');
        await convert('manual_cliente_colaborador.md', 'Manual_Cliente_Colaborador.pdf');
        console.log('All done!');
    } catch (e) {
        console.error(e);
    }
}
main();
