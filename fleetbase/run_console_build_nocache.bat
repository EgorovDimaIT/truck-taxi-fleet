@echo off
cd /d E:\fleetbase\fleetbase
docker compose build console --no-cache --progress=plain > console_build_nocache.log 2>&1
echo BUILD_DONE_EXITCODE=%ERRORLEVEL% >> console_build_nocache.log