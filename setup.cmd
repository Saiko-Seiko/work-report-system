@echo off
rem DBを作り直してデモデータを入れる
rem 開発機のphp.iniを触らずに済むよう、必要な拡張は -d で読み込む
setlocal
set PHP_EXT=-d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd
php %PHP_EXT% "%~dp0tools\migrate.php" --fresh --seed
endlocal
