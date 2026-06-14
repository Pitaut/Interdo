@echo off
REM Run test_endpoints.ps1 with temporary bypass of execution policy
REM Usage: double-click this file or run from cmd/powershell in the project root

SET SCRIPT_DIR=%~dp0scripts

IF NOT EXIST "%SCRIPT_DIR%\test_endpoints.ps1" (
  ECHO Script not found: "%SCRIPT_DIR%\test_endpoints.ps1"
  PAUSE
  EXIT /B 1
)

ECHO Running test_endpoints.ps1 ...
ECHO Running test_endpoints.ps1 ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%\test_endpoints.ps1"

ECHO.
ECHO Running test_techniciens.ps1 ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%\test_techniciens.ps1"

ECHO.
ECHO Press any key to close this window...
PAUSE >NUL
