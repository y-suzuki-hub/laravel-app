#!/usr/bin/env bash
#
# 本番用の .env の内容を標準出力に書き出す。
# env.production（固定値）と、Parameter Store の /readlog/production/ 以下の値を結合する。
#
set -euo pipefail

PARAMETER_PATH=/readlog/production/

# provision.sh が入れる AWS CLI の場所（SSM Run Command の PATH に含まれない場合に備える）
export PATH="/usr/local/bin:$PATH"

# インスタンスメタデータ（IMDSv2）からリージョンを取得する
TOKEN=$(curl -fsS -X PUT http://169.254.169.254/latest/api/token -H 'X-aws-ec2-metadata-token-ttl-seconds: 60')
REGION=$(curl -fsS -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/placement/region)

cat "$(dirname "$0")/env.production"
echo
echo "# ---- Parameter Store ($PARAMETER_PATH) ----"

aws ssm get-parameters-by-path \
    --region "$REGION" \
    --path "$PARAMETER_PATH" \
    --recursive \
    --with-decryption \
    --query 'Parameters[].[Name,Value]' \
    --output text |
while IFS=$'\t' read -r name value; do
    key="${name##*/}"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    printf '%s="%s"\n' "$key" "$value"
done
