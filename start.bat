@echo off
set "PATH=F:\carbonova-admin\.node\node-v22.14.0-win-x64;%PATH%"
cd /d "%~dp0"
echo Starting Carbonova Admin Angular Application on http://localhost:4200/ ...
call npm start
pause
