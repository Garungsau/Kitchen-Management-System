param(
    [string]$BaseUrl = "http://localhost/Smart-Meal-Management-System-main",
    [string]$Email = "employee1@cpc1.local",
    [string]$Password = "123456"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function ConvertFrom-JsonSafe {
    param([string]$Raw)
    if ([string]::IsNullOrWhiteSpace($Raw)) { return $null }

    try { return ($Raw | ConvertFrom-Json) } catch {}

    $firstObj = $Raw.IndexOf('{')
    $lastObj = $Raw.LastIndexOf('}')
    if ($firstObj -ge 0 -and $lastObj -gt $firstObj) {
        $objText = $Raw.Substring($firstObj, $lastObj - $firstObj + 1)
        try { return ($objText | ConvertFrom-Json) } catch {}
    }

    $firstArr = $Raw.IndexOf('[')
    $lastArr = $Raw.LastIndexOf(']')
    if ($firstArr -ge 0 -and $lastArr -gt $firstArr) {
        $arrText = $Raw.Substring($firstArr, $lastArr - $firstArr + 1)
        try { return ($arrText | ConvertFrom-Json) } catch {}
    }

    return $null
}

function Invoke-Api {
    param(
        [string]$Method,
        [string]$Url,
        [object]$Body,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [switch]$FormEncoded
    )

    $params = @{
        Uri = $Url
        Method = $Method
        ErrorAction = 'Stop'
        UseBasicParsing = $true
        MaximumRedirection = 0
        WebSession = $Session
    }

    if ($Method -eq 'POST') {
        if ($FormEncoded) {
            $params['Body'] = $Body
        } else {
            $params['Headers'] = @{ 'Content-Type' = 'application/json' }
            $params['Body'] = ($Body | ConvertTo-Json -Depth 10)
        }
    }

    $http = 0
    $raw = ''
    try {
        $resp = Invoke-WebRequest @params
        $http = [int]$resp.StatusCode
        $raw = [string]$resp.Content
    } catch {
        if ($_.Exception.Response) {
            $http = [int]$_.Exception.Response.StatusCode.value__
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $raw = $reader.ReadToEnd()
            $reader.Close()
        } else {
            throw
        }
    }

    $json = ConvertFrom-JsonSafe -Raw $raw
    [PSCustomObject]@{ HttpCode = $http; Raw = $raw; Json = $json }
}

function New-Check {
    param([string]$Step, [bool]$Pass, [string]$Detail)
    [PSCustomObject]@{ Step = $Step; Pass = $Pass; Detail = $Detail }
}

function Get-Text {
    param($Value, [string]$Fallback = '')
    if ($null -eq $Value) { return $Fallback }
    $s = [string]$Value
    if ([string]::IsNullOrWhiteSpace($s)) { return $Fallback }
    return $s
}

function Get-JsonField {
    param($Obj, [string]$Name, [string]$Fallback = '')
    if ($null -eq $Obj) { return $Fallback }
    $prop = $Obj.PSObject.Properties[$Name]
    if ($null -eq $prop) { return $Fallback }
    return (Get-Text $prop.Value $Fallback)
}

$base = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$checks = @()

# 1) Login
$login = Invoke-Api -Method 'POST' -Url "$base/api/login.php" -Body @{ email = $Email; password = $Password } -Session $session -FormEncoded
$loginOk = ($login.Json -ne $null -and $login.Json.status -eq 'success')
$checks += New-Check -Step 'login' -Pass $loginOk -Detail (Get-JsonField $login.Json 'message' 'No JSON response')
if (-not $loginOk) {
    Write-Host "`n=== AUTH SUCCESS FLOW VERIFY ===" -ForegroundColor Cyan
    $checks | Format-Table -AutoSize
    exit 1
}

# 2) dashboard_init
$init = Invoke-Api -Method 'GET' -Url "$base/api/dashboard_init.php" -Body $null -Session $session
$initOk = ($init.Json -ne $null -and $init.Json.status -eq 'success' -and $init.Json.dates -ne $null)
$checks += New-Check -Step 'dashboard_init' -Pass $initOk -Detail (Get-JsonField $init.Json 'message' 'ok')

# 3) choose approved menu date for registration test
$candidateDate = $null
for ($i = 1; $i -le 7; $i++) {
    $d = (Get-Date).AddDays($i).ToString('yyyy-MM-dd')
    $m = Invoke-Api -Method 'GET' -Url "$base/api/get_menu.php?date=$d" -Body $null -Session $session
    if ($m.Json -and $m.Json.status -eq 'success' -and $m.Json.menu_status -eq 'approved') {
        $candidateDate = $d
        break
    }
}
if (-not $candidateDate) {
    $candidateDate = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
}
$checks += New-Check -Step 'find_menu_date' -Pass $true -Detail "candidate=$candidateDate"

# 4) register_meal success or already-registered is acceptable for stable flow
$candidateMonth = ([DateTime]::ParseExact($candidateDate, 'yyyy-MM-dd', $null)).ToString('yyyy-MM')
$preMonth = Invoke-Api -Method 'GET' -Url "$base/api/get_month_status.php?month=$candidateMonth" -Body $null -Session $session
$preRegistered = $false
if ($preMonth.Json -and $preMonth.Json.data) {
    try {
        $preVal = $preMonth.Json.data.$candidateDate
        if ($null -ne $preVal -and [int]$preVal -eq 1) {
            $preRegistered = $true
        }
    } catch {
        $preRegistered = $false
    }
}

$reg = Invoke-Api -Method 'POST' -Url "$base/api/register_meal.php" -Body @{ meal_date = $candidateDate; meal_type = 'lunch' } -Session $session
$regStatus = Get-JsonField $reg.Json 'status'
$regMsg = Get-JsonField $reg.Json 'message'
$regOk = ($reg.Json -ne $null) -and (($regStatus -eq 'success') -or ($preRegistered -and $regStatus -eq 'error'))
$checks += New-Check -Step 'register_meal' -Pass $regOk -Detail ($regMsg)

# 5) toggle_meal (cancel)
$toggle = Invoke-Api -Method 'POST' -Url "$base/api/toggle_meal.php" -Body @{ date = $candidateDate; status = 0 } -Session $session
$toggleStatus = Get-JsonField $toggle.Json 'status'
$toggleOk = ($toggle.Json -ne $null -and $toggleStatus -eq 'success')
$checks += New-Check -Step 'toggle_meal' -Pass $toggleOk -Detail (Get-JsonField $toggle.Json 'message')

# 6) get_month_status
$month = (Get-Date).ToString('yyyy-MM')
$monthResp = Invoke-Api -Method 'GET' -Url "$base/api/get_month_status.php?month=$month" -Body $null -Session $session
$monthStatus = Get-JsonField $monthResp.Json 'status'
$monthOk = ($monthResp.Json -ne $null -and $monthStatus -eq 'success')
$checks += New-Check -Step 'get_month_status' -Pass $monthOk -Detail (Get-JsonField $monthResp.Json 'message')

# 7) get_realtime_alerts
$alert = Invoke-Api -Method 'GET' -Url "$base/api/get_realtime_alerts.php" -Body $null -Session $session
$alertStatus = Get-JsonField $alert.Json 'status'
$alertOk = ($alert.Json -ne $null -and $alertStatus -eq 'success')
$checks += New-Check -Step 'get_realtime_alerts' -Pass $alertOk -Detail (Get-JsonField $alert.Json 'message')

# 8) logout
$null = Invoke-Api -Method 'GET' -Url "$base/api/logout.php" -Body $null -Session $session

Write-Host "`n=== AUTH SUCCESS FLOW VERIFY ===" -ForegroundColor Cyan
$checks | Format-Table -AutoSize

$passCount = @($checks | Where-Object { $_.Pass }).Count
$total = $checks.Count
Write-Host "Flow: $passCount/$total steps pass" -ForegroundColor Yellow

if ($passCount -ne $total) { exit 1 }
exit 0
