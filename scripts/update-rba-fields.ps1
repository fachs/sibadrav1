param(
    [Parameter(Mandatory = $true)]
    [string] $InputPath
)

$ErrorActionPreference = 'Stop'
$sourcePath = (Resolve-Path -LiteralPath $InputPath).Path
$wordApp = $null
$wordDocument = $null

try {
    $wordApp = New-Object -ComObject Word.Application
    $wordApp.Visible = $false
    $wordApp.DisplayAlerts = 0
    $wordApp.AutomationSecurity = 3
    $wordDocument = $wordApp.Documents.Open($sourcePath, $false, $false, $false)
    $wordDocument.Repaginate()
    [void] $wordDocument.Fields.Update()
    $wordDocument.Repaginate()
    [void] $wordDocument.Fields.Update()
    $wordDocument.Save()
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
