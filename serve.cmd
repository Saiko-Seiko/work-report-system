@echo off
rem 開発サーバ起動  →  http://localhost:8080/_dev
setlocal
set PHP_EXT=-d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd
set PORT=8080
if not "%1"=="" set PORT=%1
echo.
echo   ユーザーサイト : http://localhost:%PORT%/login
echo   管理者サイト   : http://localhost:%PORT%/admin/login
echo   開発インデックス: http://localhost:%PORT%/_dev
echo.
php %PHP_EXT% -S 0.0.0.0:%PORT% -t "%~dp0public" "%~dp0public\index.php"
endlocal
