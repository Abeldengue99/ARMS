$files = Get-ChildItem -Path . -Filter *.html
foreach ($file in $files) {
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
        $text = [System.Text.Encoding]::UTF8.GetString($bytes)
        $bytes2 = [System.Text.Encoding]::GetEncoding(1252).GetBytes($text)
        [System.IO.File]::WriteAllBytes($file.FullName, $bytes2)
        Write-Host "Fixed $($file.Name)"
    } catch {
        Write-Host "Error fixing $($file.Name): $_"
    }
}
