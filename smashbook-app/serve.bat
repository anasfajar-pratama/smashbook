@echo off
setlocal
cd /d "%~dp0"

set "PHP_EXE=C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"

"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000 %*
