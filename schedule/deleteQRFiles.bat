:: command for deleting the generated qr files that hadn't been deleted
:: for now the command will check every 1 hour for files that already 30 minutes long since created and then deletes them
:: php code located in app/console/command
cd E:\other\New folder\qrlogin-app-web
E:
php artisan schedule:run