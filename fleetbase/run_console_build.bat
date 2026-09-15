@echo off
cd /d E:\fleetbase\fleetbase
docker compose build console --progress=plain > console_build_patch2.log 2>&1
echo BUILD_DONE_EXITCODE=%ERRORLEVEL% >> console_build_patch2.log