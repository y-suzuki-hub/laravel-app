#!/usr/bin/env bash
#
# EC2（Ubuntu 24.04）に Nginx・PHP-FPM・Supervisor などを入れて、アプリを動かせる状態にする。
# デプロイのたびに activate.sh から呼ばれるが、このファイルが変わったときだけ実際に実行される。
# root で実行する前提。
#
set -euo pipefail

APP_ROOT=/var/www/readlog
PHP_VERSION=8.4
APT="apt-get -y -o DPkg::Lock::Timeout=600"

export DEBIAN_FRONTEND=noninteractive

echo "==> パッケージのインストール"
$APT update
$APT install software-properties-common curl unzip
if ! grep -rqs "ondrej/php" /etc/apt/sources.list.d/; then
    add-apt-repository -y ppa:ondrej/php
    $APT update
fi
$APT install nginx supervisor mysql-client \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl"

echo "==> AWS CLI"
if ! command -v aws >/dev/null 2>&1; then
    curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-$(uname -m).zip" -o /tmp/awscliv2.zip
    unzip -qo /tmp/awscliv2.zip -d /tmp
    /tmp/aws/install
    rm -rf /tmp/aws /tmp/awscliv2.zip
fi

echo "==> スワップ（メモリ 1GB のインスタンス向け）"
if [ ! -f /swapfile ]; then
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> ディレクトリ"
mkdir -p "$APP_ROOT/releases" \
    "$APP_ROOT/shared/storage/app/public" \
    "$APP_ROOT/shared/storage/framework/cache/data" \
    "$APP_ROOT/shared/storage/framework/sessions" \
    "$APP_ROOT/shared/storage/framework/views" \
    "$APP_ROOT/shared/storage/logs"
chown -R www-data:www-data "$APP_ROOT/shared/storage"

echo "==> Nginx"
cat > /etc/nginx/sites-available/readlog <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${APP_ROOT}/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;
        # current はシンボリックリンクなので、実体のパスを渡してリリース切り替えを確実に反映させる
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sfn /etc/nginx/sites-available/readlog /etc/nginx/sites-enabled/readlog
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl reload nginx

echo "==> キューワーカー（Supervisor）"
cat > /etc/supervisor/conf.d/readlog-worker.conf <<SUPERVISOR
[program:readlog-worker]
command=php ${APP_ROOT}/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=${APP_ROOT}/shared/storage/logs/worker.log
SUPERVISOR
systemctl enable --now supervisor
supervisorctl reread
supervisorctl update

echo "==> スケジューラ（cron）"
cat > /etc/cron.d/readlog <<CRON
* * * * * www-data cd ${APP_ROOT}/current && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/readlog

echo "==> プロビジョニング完了"
