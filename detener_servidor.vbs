Set shell = CreateObject("WScript.Shell")

' Detener PHP
shell.Run "taskkill /IM php.exe /F", 0, True

' Detener Caddy
shell.Run "taskkill /IM caddy.exe /F", 0, True

MsgBox "Servidor detenido correctamente.", vbInformation, "ID-Server"