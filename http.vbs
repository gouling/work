Set ws = CreateObject("WScript.Shell")

ws.Run "taskkill /f /t /im php-cgi.exe", 0
ws.Run "taskkill /f /t /im nginx.exe", 0
ws.Run "taskkill /f /t /im redis-server.exe", 0
wscript.sleep(5*1000)

If(msgbox("已停止服务，是否启动新的服务？", vbYesNo, "HTTP")=vbNo) Then wscript.quit()

ws.CurrentDirectory = "D:\redis-x64-3.2.100"
ws.Run "redis-server redis.conf", 0

ws.CurrentDirectory = "D:\php-7.4.7-Win32-vc15-x64"
For i = 1 To 10
  ws.Run "php-cgi -b 127.0.0.1:9000 -c php.ini", 0
Next

ws.CurrentDirectory = "D:\nginx-1.19.0"
ws.Run "nginx", 0