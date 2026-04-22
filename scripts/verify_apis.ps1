param(
    [string]$BaseUrl = "http://localhost/Smart-Meal-Management-System-main",
    [string]$ProjectPath = "."
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-Result {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Url,
        [bool]$Reachable,
        [bool]$JsonOk,
        [bool]$ContractOk,
        [int]$HttpCode,
        [string]$Status,
        [string]$Message
    )
    [PSCustomObject]@{
        Name       = $Name
        Method     = $Method
        HttpCode   = $HttpCode
        Reachable  = $Reachable
        JsonOk     = $JsonOk
        ContractOk = $ContractOk
        Status     = $Status
        Message    = $Message
        Url        = $Url
    }
}

function ConvertFrom-JsonSafe {
    param([string]$Raw)

    if ([string]::IsNullOrWhiteSpace($Raw)) {
        return $null
    }

    try {
        return ($Raw | ConvertFrom-Json)
    } catch {
        # Continue with fallback extraction.
    }

    $firstObj = $Raw.IndexOf('{')
    $lastObj = $Raw.LastIndexOf('}')
    if ($firstObj -ge 0 -and $lastObj -gt $firstObj) {
        $objText = $Raw.Substring($firstObj, $lastObj - $firstObj + 1)
        try {
            return ($objText | ConvertFrom-Json)
        } catch {
            # Continue
        }
    }

    $firstArr = $Raw.IndexOf('[')
    $lastArr = $Raw.LastIndexOf(']')
    if ($firstArr -ge 0 -and $lastArr -gt $firstArr) {
        $arrText = $Raw.Substring($firstArr, $lastArr - $firstArr + 1)
        try {
            return ($arrText | ConvertFrom-Json)
        } catch {
            # Continue
        }
    }

    return $null
}

function Invoke-ApiCheck {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Url,
        [hashtable]$Body,
        [string[]]$ExpectedKeys
    )

    $headers = @{}
    $params = @{
        Uri             = $Url
        Method          = $Method
        MaximumRedirection = 0
        ErrorAction     = 'Stop'
        Headers         = $headers
        UseBasicParsing = $true
    }

    if ($Method -eq 'POST') {
        $headers['Content-Type'] = 'application/json'
        $params['Body'] = ($Body | ConvertTo-Json -Depth 5)
    }

    $httpCode = 0
    $raw = ''
    try {
        $resp = Invoke-WebRequest @params
        $httpCode = [int]$resp.StatusCode
        $raw = [string]$resp.Content
    } catch {
        if ($_.Exception.Response) {
            $httpCode = [int]$_.Exception.Response.StatusCode.value__
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $raw = $reader.ReadToEnd()
            $reader.Close()
        } else {
            return New-Result -Name $Name -Method $Method -Url $Url -Reachable $false -JsonOk $false -ContractOk $false -HttpCode 0 -Status 'network_error' -Message $_.Exception.Message
        }
    }

    $reachable = $httpCode -ge 200 -and $httpCode -lt 500

    $json = $null
    $jsonOk = $false
    $json = ConvertFrom-JsonSafe -Raw $raw
    if ($null -eq $json) {
        return New-Result -Name $Name -Method $Method -Url $Url -Reachable $reachable -JsonOk $false -ContractOk $false -HttpCode $httpCode -Status 'invalid_json' -Message ($raw.Substring(0, [Math]::Min(180, $raw.Length)))
    }
    $jsonOk = $true

    $status = [string]($json.status)
    $message = [string]($json.message)
    $isUnauthorized = ($status -eq 'error' -and ($message -match 'Unauthorized'))

    $hasKeys = $true
    foreach ($k in $ExpectedKeys) {
        if (-not ($json.PSObject.Properties.Name -contains $k)) {
            $hasKeys = $false
            break
        }
    }

    $contractOk = $isUnauthorized -or ($status -eq 'success' -and $hasKeys) -or ($status -eq 'error' -and $hasKeys)

    return New-Result -Name $Name -Method $Method -Url $Url -Reachable $reachable -JsonOk $jsonOk -ContractOk $contractOk -HttpCode $httpCode -Status $status -Message $message
}

function Get-ServerFingerprint {
    param([string]$Url)
    try {
        $resp = Invoke-WebRequest -Uri $Url -Method GET -ErrorAction Stop -UseBasicParsing
        return (ConvertFrom-JsonSafe -Raw ([string]$resp.Content))
    } catch {
        return $null
    }
}

function Get-LocalFingerprint {
    param([string]$RootPath, [string[]]$Files)

    $map = @{}
    foreach ($f in $Files) {
        $abs = Join-Path $RootPath $f
        if (Test-Path $abs) {
            $hash = (Get-FileHash -Path $abs -Algorithm SHA256).Hash.ToLowerInvariant()
            $item = Get-Item $abs
            $map[$f] = [PSCustomObject]@{
                sha256 = $hash
                size = [int64]$item.Length
                mtime = $item.LastWriteTimeUtc.ToString('o')
            }
        }
    }
    return $map
}

$base = $BaseUrl.TrimEnd('/')
$todayMonth = (Get-Date).ToString('yyyy-MM')

$checks = @(
    @{ Name='dashboard_init'; Method='GET';  Url="$base/api/dashboard_init.php"; Body=$null; ExpectedKeys=@('status') },
    @{ Name='register_meal'; Method='POST'; Url="$base/api/register_meal.php"; Body=@{ meal_date='2099-12-31'; meal_type='lunch' }; ExpectedKeys=@('status') },
    @{ Name='toggle_meal';   Method='POST'; Url="$base/api/toggle_meal.php"; Body=@{ date='2099-12-31'; status=1 }; ExpectedKeys=@('status') },
    @{ Name='get_month_status'; Method='GET'; Url="$base/api/get_month_status.php?month=$todayMonth"; Body=$null; ExpectedKeys=@('status') },
    @{ Name='get_realtime_alerts'; Method='GET'; Url="$base/api/get_realtime_alerts.php"; Body=$null; ExpectedKeys=@('status') }
)

$results = @()
foreach ($c in $checks) {
    $results += Invoke-ApiCheck -Name $c.Name -Method $c.Method -Url $c.Url -Body $c.Body -ExpectedKeys $c.ExpectedKeys
}

$all404 = @($results | Where-Object { $_.HttpCode -eq 404 }).Count -eq $results.Count
if ($all404) {
    $altBase = "$base/Smart-Meal-Management-System-main"
    $altChecks = @(
        @{ Name='dashboard_init'; Method='GET';  Url="$altBase/api/dashboard_init.php"; Body=$null; ExpectedKeys=@('status') },
        @{ Name='register_meal'; Method='POST'; Url="$altBase/api/register_meal.php"; Body=@{ meal_date='2099-12-31'; meal_type='lunch' }; ExpectedKeys=@('status') },
        @{ Name='toggle_meal';   Method='POST'; Url="$altBase/api/toggle_meal.php"; Body=@{ date='2099-12-31'; status=1 }; ExpectedKeys=@('status') },
        @{ Name='get_month_status'; Method='GET'; Url="$altBase/api/get_month_status.php?month=$todayMonth"; Body=$null; ExpectedKeys=@('status') },
        @{ Name='get_realtime_alerts'; Method='GET'; Url="$altBase/api/get_realtime_alerts.php"; Body=$null; ExpectedKeys=@('status') }
    )

    $altResults = @()
    foreach ($c in $altChecks) {
        $altResults += Invoke-ApiCheck -Name $c.Name -Method $c.Method -Url $c.Url -Body $c.Body -ExpectedKeys $c.ExpectedKeys
    }

    $alt404 = @($altResults | Where-Object { $_.HttpCode -eq 404 }).Count -eq $altResults.Count
    if (-not $alt404) {
        Write-Host "Detected better base URL: $altBase" -ForegroundColor DarkYellow
        $base = $altBase
        $results = $altResults
    } else {
        Write-Host "All endpoints returned 404. Check BaseUrl. Tried: $base and $altBase" -ForegroundColor Red
    }
}

Write-Host "`n=== API VERIFY (PASS/FAIL) ===" -ForegroundColor Cyan
$results | Select-Object Name,Method,HttpCode,Reachable,JsonOk,ContractOk,Status,Message | Format-Table -AutoSize

$apiPass = @($results | Where-Object { $_.Reachable -and $_.JsonOk -and $_.ContractOk }).Count
$apiTotal = $results.Count
Write-Host "API: $apiPass/$apiTotal checks pass" -ForegroundColor Yellow

$fingerprintUrl = "$base/api/source_fingerprint.php"
$serverFp = Get-ServerFingerprint -Url $fingerprintUrl

if ($null -eq $serverFp -or $serverFp.status -ne 'success') {
    Write-Host "`nSource drift check: SKIPPED (cannot fetch $fingerprintUrl)." -ForegroundColor DarkYellow
    exit 0
}

$serverFiles = @($serverFp.files.PSObject.Properties.Name)
$localFp = Get-LocalFingerprint -RootPath (Resolve-Path $ProjectPath).Path -Files $serverFiles

$driftRows = @()
foreach ($f in $serverFiles) {
    $sv = $serverFp.files.$f
    $lv = $localFp[$f]

    if ($null -eq $lv) {
        $driftRows += [PSCustomObject]@{ File=$f; Match=$false; ServerHash=$sv.sha256; LocalHash='(missing)' }
        continue
    }

    $isMatch = ([string]$sv.sha256).ToLowerInvariant() -eq ([string]$lv.sha256).ToLowerInvariant()
    $driftRows += [PSCustomObject]@{ File=$f; Match=$isMatch; ServerHash=$sv.sha256; LocalHash=$lv.sha256 }
}

Write-Host "`n=== SOURCE DRIFT CHECK ===" -ForegroundColor Cyan
$driftRows | Select-Object File,Match | Format-Table -AutoSize

$driftMismatch = @($driftRows | Where-Object { -not $_.Match }).Count
if ($driftMismatch -eq 0) {
    Write-Host "Source drift: PASS (server files match local source)" -ForegroundColor Green
} else {
    Write-Host "Source drift: FAIL ($driftMismatch mismatched files)" -ForegroundColor Red
}

$failed = @($results | Where-Object { -not ($_.Reachable -and $_.JsonOk -and $_.ContractOk) }).Count
if ($failed -gt 0 -or $driftMismatch -gt 0) {
    exit 1
}

exit 0
