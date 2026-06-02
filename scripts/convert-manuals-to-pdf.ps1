# Convert markdown manuals to PDF using marked (npx) + MS Word COM
# Outputs to docs/manual/*.pdf

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path $PSScriptRoot -Parent
$manualDir = Join-Path $projectRoot "docs\manual"

# HTML template with Thai font support
$htmlTemplate = @'
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>{TITLE}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap');
body {
  font-family: 'Sarabun', 'TH Sarabun New', 'Tahoma', sans-serif;
  font-size: 11pt;
  line-height: 1.55;
  color: #1f2937;
  max-width: 820px;
  margin: 0 auto;
  padding: 24px 32px;
}
h1 { color: #1e3a8a; font-size: 22pt; border-bottom: 3px solid #1e40af; padding-bottom: 8px; margin-top: 0; }
h2 { color: #1e40af; font-size: 16pt; margin-top: 28px; border-left: 5px solid #2563eb; padding-left: 10px; }
h3 { color: #1d4ed8; font-size: 13pt; margin-top: 20px; }
h4 { color: #2563eb; font-size: 12pt; }
table { border-collapse: collapse; width: 100%; margin: 14px 0; font-size: 10.5pt; }
th, td { border: 1px solid #cbd5e1; padding: 7px 10px; text-align: left; vertical-align: top; }
th { background: #dbeafe; color: #1e3a8a; font-weight: 600; }
tr:nth-child(even) { background: #f8fafc; }
code { background: #f1f5f9; color: #be185d; padding: 1px 6px; border-radius: 3px; font-family: 'Consolas', monospace; font-size: 10pt; }
pre { background: #1e293b; color: #e2e8f0; padding: 14px; border-radius: 6px; overflow-x: auto; font-size: 9.5pt; line-height: 1.4; }
pre code { background: transparent; color: #e2e8f0; padding: 0; }
blockquote { border-left: 4px solid #3b82f6; background: #eff6ff; padding: 8px 14px; margin: 12px 0; color: #1e40af; border-radius: 0 4px 4px 0; }
ul, ol { padding-left: 24px; }
li { margin: 4px 0; }
hr { border: none; border-top: 2px dashed #cbd5e1; margin: 20px 0; }
strong { color: #1e40af; }
a { color: #2563eb; text-decoration: none; }
</style>
</head>
<body>
{BODY}
<hr>
<p style="text-align:center; color:#94a3b8; font-size:9pt; margin-top:30px;">
  Welfare Korat 2569 - Document - Generated from Markdown
</p>
</body>
</html>
'@

# Step 1: Find all .md files and convert to HTML
$mdFiles = Get-ChildItem -Path $manualDir -Filter "*.md"
Write-Host ("Found " + $mdFiles.Count + " MD files") -ForegroundColor Cyan

$htmlPaths = @()
foreach ($md in $mdFiles) {
    Write-Host ("  -> " + $md.Name + " to HTML...") -ForegroundColor Yellow
    $title = $md.BaseName
    $bodyHtml = & npx -y --package marked@latest -- marked --input $md.FullName 2>&1

    if ($LASTEXITCODE -ne 0) {
        Write-Host "    marked failed" -ForegroundColor Red
        continue
    }
    $bodyText = $bodyHtml -join "`n"
    $fullHtml = $htmlTemplate.Replace('{TITLE}', $title).Replace('{BODY}', $bodyText)
    $htmlPath = Join-Path $manualDir ($title + ".html")
    $fullHtml | Out-File -FilePath $htmlPath -Encoding utf8
    $htmlPaths += $htmlPath
}

# Step 2: Open HTML in Word and SaveAs PDF
Write-Host ""
Write-Host "Opening MS Word - converting HTML to PDF..." -ForegroundColor Cyan
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

foreach ($htmlPath in $htmlPaths) {
    $name = [System.IO.Path]::GetFileNameWithoutExtension($htmlPath)
    Write-Host ("  -> " + $name + ".pdf") -ForegroundColor Yellow
    try {
        $doc = $word.Documents.Open($htmlPath, $false, $true)
        $pdfPath = Join-Path $manualDir ($name + ".pdf")
        # Use SaveAs2 with positional args (17 = wdFormatPDF)
        $doc.SaveAs2($pdfPath, 17)
        $doc.Close($false)
    } catch {
        Write-Host ("    Error: " + $_) -ForegroundColor Red
    }
}
$word.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null

# Step 3: Clean up temp HTML files
foreach ($htmlPath in $htmlPaths) {
    Remove-Item $htmlPath -ErrorAction SilentlyContinue
}

# Summary
$pdfs = Get-ChildItem -Path $manualDir -Filter "*.pdf"
Write-Host ""
Write-Host ("=== Done - Created " + $pdfs.Count + " PDF files ===") -ForegroundColor Green
foreach ($pdf in $pdfs) {
    $sizeKB = [math]::Round($pdf.Length / 1024, 1)
    Write-Host ("  [OK] " + $pdf.Name + " (" + $sizeKB + " KB)") -ForegroundColor Green
}
