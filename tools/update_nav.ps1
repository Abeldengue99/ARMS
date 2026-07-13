$files = Get-ChildItem -Path . -Filter *.html

foreach ($file in $files) {
    $content = Get-Content -Raw -Path $file.FullName
    
    # Remover Notificações da sidebar (se existir)
    $content = $content -replace '(?s)<a href="notificacoes\.html"[^>]*>.*?</a>\s*', ''
    
    # Remover Perfil da sidebar (se existir)
    $content = $content -replace '(?s)<a href="perfil\.html"[^>]*>.*?</a>\s*', ''
    
    # Novo bloco do cabeçalho
    $novoCabecalho = @'
<div class="cabecalho-acoes" style="display: flex; align-items: center; gap: 16px;">
                    <a href="notificacoes.html" style="color: var(--texto-secundario); position: relative; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background-color: #f4f4f5; transition: var(--transicao);" onmouseover="this.style.backgroundColor='#e4e4e7'" onmouseout="this.style.backgroundColor='#f4f4f5'" title="Notificações">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span style="position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background-color: var(--cor-perigo); border-radius: 50%; border: 2px solid #f4f4f5;"></span>
                    </a>
                    
                    <a href="perfil.html" style="text-decoration: none;" title="O Meu Perfil">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background-color: var(--aksanti-gold); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            PA
                        </div>
                    </a>

                    <div style="width: 1px; height: 24px; background-color: #e4e4e7; margin: 0 8px;"></div>
'@

    # Substituir o cabeçalho-acoes vazio pelo novo
    $content = $content -replace '<div class="cabecalho-acoes">', $novoCabecalho
    
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8
}
