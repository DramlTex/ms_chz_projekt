# Примеры конфигурации

## Apache Configuration

### .htaccess (в корне проекта)

```apache
# Включаем mod_rewrite
RewriteEngine On

# HTTPS редирект (обязательно для КриптоПро)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Настройки PHP
<Files "api.php">
    php_flag display_errors off
    php_flag log_errors on
    php_value error_log /var/log/php_errors.log
</Files>

# CORS headers (если нужно)
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "POST, GET, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type"
</IfModule>

# Кэширование статических файлов
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/html "access plus 0 seconds"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
</IfModule>

# Безопасность
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

### VirtualHost конфигурация

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Логи
    ErrorLog ${APACHE_LOG_DIR}/integration_error.log
    CustomLog ${APACHE_LOG_DIR}/integration_access.log combined
</VirtualHost>
```

## Nginx Configuration

### Основная конфигурация (/etc/nginx/sites-available/integration)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    # Редирект на HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /var/www/html;
    index index.html;
    
    # SSL
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Безопасность
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # CORS
    add_header Access-Control-Allow-Origin "*" always;
    add_header Access-Control-Allow-Methods "POST, GET, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type" always;
    
    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Скрываем ошибки PHP
        fastcgi_param PHP_VALUE "display_errors=off";
        fastcgi_param PHP_VALUE "log_errors=on";
    }
    
    # Статические файлы
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # HTML не кэшируем
    location ~* \.html$ {
        add_header Cache-Control "no-store, no-cache, must-revalidate";
    }
    
    # Логи
    access_log /var/log/nginx/integration_access.log;
    error_log /var/log/nginx/integration_error.log;
}
```

## PHP Configuration

### Рекомендуемые настройки php.ini

```ini
# Базовые настройки
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 50M
upload_max_filesize = 50M

# Ошибки (production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

# Сессии
session.save_handler = files
session.save_path = "/var/lib/php/sessions"
session.gc_maxlifetime = 3600
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Strict

# Безопасность
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

# Часовой пояс
date.timezone = Europe/Moscow

# CURL
extension=curl
```

## Docker Configuration (опционально)

### Dockerfile

```dockerfile
FROM php:7.4-apache

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# Включаем необходимые модули Apache
RUN a2enmod rewrite headers ssl

# Копируем файлы приложения
COPY . /var/www/html/

# Права доступа
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Настройка SSL (самоподписанный сертификат для разработки)
RUN mkdir -p /etc/apache2/ssl \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=RU/ST=Moscow/L=Moscow/O=Company/CN=localhost"

# Копируем конфигурацию Apache
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 443

CMD ["apache2-foreground"]
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "443:443"
      - "80:80"
    volumes:
      - .:/var/www/html
      - php-sessions:/var/lib/php/sessions
    environment:
      - PHP_DISPLAY_ERRORS=Off
      - PHP_LOG_ERRORS=On
    restart: unless-stopped

volumes:
  php-sessions:
```

## Systemd Service (для production)

### /etc/systemd/system/integration.service

```ini
[Unit]
Description=Integration Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/sbin/apache2ctl -D FOREGROUND
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Команды:
```bash
sudo systemctl enable integration
sudo systemctl start integration
sudo systemctl status integration
```

## Firewall Configuration

### UFW (Ubuntu)

```bash
# Разрешаем HTTP и HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Включаем firewall
sudo ufw enable
```

### iptables

```bash
# HTTP
iptables -A INPUT -p tcp --dport 80 -j ACCEPT

# HTTPS
iptables -A INPUT -p tcp --dport 443 -j ACCEPT

# Сохраняем правила
iptables-save > /etc/iptables/rules.v4
```

## Логирование

### Скрипт ротации логов

```bash
# /etc/logrotate.d/integration

/var/log/nginx/integration_*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null 2>&1
    endscript
}

/var/log/php_errors.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

## Мониторинг

### Простой health check скрипт

```bash
#!/bin/bash
# /usr/local/bin/integration-health-check.sh

URL="https://your-domain.com/"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$URL")

if [ $STATUS -eq 200 ]; then
    echo "OK: Service is running"
    exit 0
else
    echo "ERROR: Service returned $STATUS"
    exit 1
fi
```

### Cron задача для проверки

```bash
# В crontab (crontab -e)
*/5 * * * * /usr/local/bin/integration-health-check.sh >> /var/log/integration-health.log 2>&1
```

## Резервное копирование

### Скрипт backup

```bash
#!/bin/bash
# /usr/local/bin/integration-backup.sh

BACKUP_DIR="/backup/integration"
DATE=$(date +%Y%m%d_%H%M%S)

# Создаем директорию
mkdir -p "$BACKUP_DIR"

# Архивируем файлы приложения
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" \
    -C /var/www/html \
    --exclude='*.log' \
    .

# Архивируем логи
tar -czf "$BACKUP_DIR/logs_$DATE.tar.gz" \
    /var/log/nginx/integration_*.log \
    /var/log/php_errors.log

# Удаляем старые бэкапы (старше 30 дней)
find "$BACKUP_DIR" -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

### Cron для backup

```bash
# Ежедневный бэкап в 2:00
0 2 * * * /usr/local/bin/integration-backup.sh >> /var/log/backup.log 2>&1
```

## Troubleshooting

### Проверка конфигурации

```bash
# Apache
apache2ctl configtest

# Nginx
nginx -t

# PHP
php -i | grep "Configuration File"
php -m  # Список модулей
```

### Проверка логов

```bash
# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/integration_error.log

# PHP
tail -f /var/log/php_errors.log
```

### Тестирование SSL

```bash
# Проверка сертификата
openssl s_client -connect your-domain.com:443 -servername your-domain.com

# Тест SSL конфигурации
curl -v https://your-domain.com/
```