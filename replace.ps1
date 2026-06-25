$utf8NoBom = New-Object System.Text.UTF8Encoding $false
Get-ChildItem -Path . -Recurse -File | Where-Object { $_.DirectoryName -notmatch '\\.git' -and $_.Extension -notmatch '\.(png|jpg|jpeg|gif|ico|woff2?|ttf|eot|db|sqlite3?|pdf|zip|tar|gz|rar)' } | ForEach-Object {
    $path = $_.FullName
    $text = [System.IO.File]::ReadAllText($path)
    if ($text.Contains('MikhPay')) {
        [System.IO.File]::WriteAllText($path, $text.Replace('MikhPay', 'MikhPay'), $utf8NoBom)
    }
}
