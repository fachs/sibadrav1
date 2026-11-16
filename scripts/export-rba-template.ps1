param(
    [string] $InputPath = (Join-Path $PSScriptRoot '../resources/documents/rba/template-rba.docx'),
    [string] $OutputPath = (Join-Path $PSScriptRoot '../resources/documents/rba/template-rba.pdf')
)

$ErrorActionPreference = 'Stop'
$sourcePath = (Resolve-Path -LiteralPath $InputPath).Path
$pdfPath = [System.IO.Path]::GetFullPath($OutputPath)
$sourceHash = (Get-FileHash -LiteralPath $sourcePath -Algorithm SHA256).Hash
$wordApp = $null
$wordDocument = $null

try {
    $wordApp = New-Object -ComObject Word.Application
    $wordApp.Visible = $false
    $wordApp.DisplayAlerts = 0
    $wordApp.AutomationSecurity = 3
    $wordDocument = $wordApp.Documents.Open($sourcePath, $false, $true, $false)
    $wordDocument.ExportAsFixedFormat($pdfPath, 17)
    Write-Output "PDF exported: $pdfPath"
}
finally {
    if ($null -ne $wordDocument) {
        $wordDocument.Close(0)
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($wordDocument)
    }
    if ($null -ne $wordApp) {
        $saveChanges = 0
        try {
            $wordApp.Quit([ref]$saveChanges)
        }
        finally {
            [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($wordApp)
        }
    }
}

if ((Get-FileHash -LiteralPath $sourcePath -Algorithm SHA256).Hash -ne $sourceHash) {
    throw 'The source template was modified during export.'
}
if (!(Test-Path -LiteralPath $pdfPath) -or (Get-Item -LiteralPath $pdfPath).Length -eq 0) {
    throw 'PDF export did not produce a file.'
}
