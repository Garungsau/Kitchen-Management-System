# API Verify Script

## Chuc nang
- Kiem tra PASS/FAIL cho 5 API chinh:
  - api/dashboard_init.php
  - api/register_meal.php
  - api/toggle_meal.php
  - api/get_month_status.php
  - api/get_realtime_alerts.php
- Kiem tra lech source giua local source va file server dang phuc vu thong qua `api/source_fingerprint.php`.

## Cach chay (PowerShell)
```powershell
Set-Location C:\xampp\htdocs\Smart-Meal-Management-System-main
powershell -ExecutionPolicy Bypass -File .\scripts\verify_apis.ps1 -BaseUrl "http://localhost/Smart-Meal-Management-System-main" -ProjectPath "."
```

## Ket qua
- In bang PASS/FAIL theo tung API.
- In bang SOURCE DRIFT CHECK (Match true/false theo file).
- Exit code:
  - `0`: tat ca API pass va khong lech source
  - `1`: co API fail hoac lech source

## Luu y
- `api/source_fingerprint.php` chi cho phep truy cap tu localhost (127.0.0.1/::1).
- Mot so API can session dang nhap; script van danh gia hop dong response thong qua JSON + status/Unauthorized.

## Verify success flow co dang nhap (khuyen nghi)
Script nay dang nhap test account va verify luong thanh cong thuc te.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify_apis_success.ps1 -BaseUrl "http://localhost/Smart-Meal-Management-System-main" -Email "employee1@cpc1.local" -Password "123456"
```

Ket qua: in PASS/FAIL cho cac buoc dang nhap, dashboard_init, register_meal, toggle_meal, get_month_status, get_realtime_alerts.
