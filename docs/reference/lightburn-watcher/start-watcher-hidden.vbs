Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "cmd /c cd /d C:\Users\Production\LightBurn\lightburn-watcher && node lightburn-watcher-service.js", 0, False