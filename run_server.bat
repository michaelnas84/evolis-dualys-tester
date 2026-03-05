@echo off
title Photo Booth Servers

echo ===================================
echo      Iniciando servidores
echo ===================================
echo.

REM Save root folder
set "ROOT=%cd%"

REM ==============================
REM START PYTHON SERVER
REM ==============================

echo Iniciando servidor Python (remove background)...

start "Python BG Server" cmd /k "py scripts\remove_bg.py"

timeout /t 3 >nul

REM ==============================
REM START CARD HOTFOLDER PRINTER
REM ==============================

echo Iniciando Card Hotfolder Printer...

start "Card Hotfolder Printer" cmd /k py -3.11 C:\card_hotfolder\card_hotfolder_printer.py --root C:\card_hotfolder --printer "Evolis Dualys Series"

timeout /t 2 >nul

REM ==============================
REM START PHP SERVER
REM ==============================

echo Iniciando servidor PHP...

cd public

start "PHP Server" cmd /k "php -S 127.0.0.1:8000"

timeout /t 2 >nul

REM ==============================
REM OPEN BROWSER
REM ==============================

echo Abrindo navegador...

start http://127.0.0.1:8000

echo.
echo ===================================
echo Servidores iniciados
echo ===================================
echo.
echo PHP: http://127.0.0.1:8000
echo Python BG: http://127.0.0.1:5001
echo Hotfolder Printer: C:\card_hotfolder
echo.

pause