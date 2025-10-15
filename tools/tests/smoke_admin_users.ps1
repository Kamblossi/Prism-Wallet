$Base = $env:BASE
if (-not $Base) { $Base = 'http://localhost:8081' }
$Headers = @{ 'Content-Type' = 'application/json' }
if ($env:CSRF) { $Headers['X-CSRF-Token'] = $env:CSRF }

Write-Host 'List users'
Invoke-WebRequest -Method POST -Uri "$Base/endpoints/admin/users/list.php" -Headers $Headers -Body '{"page":1,"per_page":5}' | Select-Object -ExpandProperty Content

Write-Host 'Read user 1'
Invoke-WebRequest -Method GET -Uri "$Base/endpoints/admin/users/read.php?id=1" -Headers @{ } | Select-Object -ExpandProperty Content

