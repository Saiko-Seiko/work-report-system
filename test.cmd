@echo off
rem 通し試験。先に serve.cmd で開発サーバを起動しておくこと
setlocal
set PHP_EXT=-d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd
php %PHP_EXT% "%~dp0tests\run.php" %1
endlocal
