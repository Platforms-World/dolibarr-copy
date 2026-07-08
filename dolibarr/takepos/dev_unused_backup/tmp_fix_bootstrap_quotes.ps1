$path = 'C:\Users\deyaa_h\OneDrive - miyahuna.com.jo\codex_ERP\takepos\api\v1\bootstrap.php'  
$content = Get-Content -Raw -Path $path 
$content = $content.Replace('\\\"', [string][char]34^) & >> tmp_fix_bootstrap_quotes.ps1 echo Set-Content -Path $path -Value $content -Encoding UTF8 & C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe -ExecutionPolicy Bypass -NoProfile -File tmp_fix_bootstrap_quotes.ps1 & del /q tmp_fix_bootstrap_quotes.ps1
