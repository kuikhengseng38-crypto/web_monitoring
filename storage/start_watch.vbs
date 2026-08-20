Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = "C:\Users\ASUS\Downloads\web_monitoring"
sh.Run """C:\php\php-win.exe"" ""C:\Users\ASUS\Downloads\web_monitoring\cron\watch.php""", 0, False
