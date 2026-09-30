#!/usr/bin/env bash
#
# 展開済みのリリースを有効にする。GitHub Actions から SSM Run Command 経由で root として実行される。
#
#   bash activate.sh /var/www/readlog/releases/<commit sha>
#
set -euo pipefail

RELEASE_DIR="$1"
APP_ROOT=/var/www/readlog
SHARED_DIR="$APP_ROOT/shared"
KEEP_RELEASES=5
SCRIPT_DIR="$RELEASE_DIR/deploy/server"

artisan() {
    sudo -u www-data php "$RELEASE_DIR/artisan" "$@"
}

# provision.sh が前回から変わっていたら（初回を含む）サーバーの設定を反映する
PROVISION_HASH=$(sha256sum "$SCRIPT_DIR/provision.sh" | cut -d' ' -f1)
if [ "$(cat /etc/readlog-provisioned 2>/dev/null || true)" != "$PROVISION_HASH" ]; then
    bash "$SCRIPT_DIR/provision.sh"
    echo "$PROVISION_HASH" > /etc/readlog-provisioned
fi

echo "==> .env を生成"
bash "$SCRIPT_DIR/build-env.sh" > "$SHARED_DIR/.env.new"
chown root:www-data "$SHARED_DIR/.env.new"
chmod 640 "$SHARED_DIR/.env.new"
mv "$SHARED_DIR/.env.new" "$SHARED_DIR/.env"

echo "==> 共有ファイルをリンク"
ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
chown -R www-data:www-data "$RELEASE_DIR/bootstrap/cache"

echo "==> マイグレーションとキャッシュ"
artisan migrate --force
artisan optimize

switch_to() {
    ln -sfn "$1" "$APP_ROOT/current.new"
    mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
    systemctl reload php8.4-fpm
    supervisorctl restart readlog-worker
}

PREVIOUS_RELEASE=$(readlink -f "$APP_ROOT/current" || true)

echo "==> リリースを切り替え"
switch_to "$RELEASE_DIR"

echo "==> ヘルスチェック"
if ! curl -fsS -o /dev/null --retry 5 --retry-delay 2 --retry-all-errors http://127.0.0.1/up; then
    echo "ヘルスチェックに失敗しました。直前のリリースに戻します。" >&2
    if [ -n "$PREVIOUS_RELEASE" ] && [ -d "$PREVIOUS_RELEASE" ]; then
        switch_to "$PREVIOUS_RELEASE"
    fi
    exit 1
fi
echo "OK"

echo "==> 古いリリースを削除"
find "$APP_ROOT/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' |
    sort -rn | tail -n +$((KEEP_RELEASES + 1)) | cut -d' ' -f2- | xargs -r rm -rf

echo "==> デプロイ完了: $(basename "$RELEASE_DIR")"
