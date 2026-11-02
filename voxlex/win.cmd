@echo off
chcp 65001 >nul
title VoxLex av Chromo - Hurtiginstallasjon (uten SmartScreen)
echo =========================================================
echo   VoxLex - Lese- og skrivestotte for norsk skole
echo   Laster ned og klargjor siste versjon...
echo =========================================================
set "DEST=%LOCALAPPDATA%\Programs"
set "ZIP=%TEMP%\VoxLex-Portable.zip"
if not exist "%DEST%" mkdir "%DEST%"
taskkill /F /IM VoxLex.exe /T >nul 2>&1
taskkill /F /IM voxlex.exe /T >nul 2>&1
taskkill /F /IM piper.exe /T >nul 2>&1
curl.exe -fSL --ssl-no-revoke "https://voxcuriosa.no/voxlex/VoxLex-v1.2.75-Windows.zip" -o "%ZIP%" || curl.exe -fSL --ssl-no-revoke "https://voxcuriosa.no/voxlex/VoxLex-v1.2.74-Windows.zip" -o "%ZIP%"
if exist "%ZIP%" (
    echo Pakker ut VoxLex til %DEST%\VoxLex...
    tar.exe -xf "%ZIP%" -C "%DEST%"
    del /F /Q "%ZIP%" >nul 2>&1
)
if exist "%DEST%\VoxLex\VoxLex.exe" (
    echo Starter VoxLex...
    start "" "%DEST%\VoxLex\VoxLex.exe"
) else (
    echo Kunne ikke laste ned VoxLex. Sjekk nettforbindelsen.
    pause
)
