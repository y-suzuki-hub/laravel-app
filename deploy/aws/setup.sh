#!/usr/bin/env bash
#
# ReadLog の AWS 環境（docs/aws/step1-deploy.md の手順 1〜8）をまとめて作成する。
# AWS CloudShell で実行する想定（ログイン中のユーザーの権限で動くのでアクセスキーは不要）。
#
#   git clone https://github.com/y-suzuki-hub/laravel-app.git
#   cd laravel-app
#   bash deploy/aws/setup.sh
#
# 何度実行しても良い。作成済みのリソースは名前（Name タグ）で見つけて再利用する。
#
set -euo pipefail

export AWS_REGION=ap-northeast-1
export AWS_DEFAULT_REGION=$AWS_REGION
export AWS_PAGER=""

APP=readlog
GITHUB_REPO=y-suzuki-hub/laravel-app
PARAM_PATH=/readlog/production
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33m    ! %s\033[0m\n' "$*"; }

# 値が "None" や空なら空文字にする（AWS CLI の --output text 対策）
clean() { [ "$1" = "None" ] && echo "" || echo "$1"; }

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
log "アカウント $ACCOUNT_ID / リージョン $AWS_REGION に作成します"

# ---------------------------------------------------------------------------
# 入力（最初にまとめて聞く）
# ---------------------------------------------------------------------------
DB_INSTANCE_EXISTS=$(clean "$(aws rds describe-db-instances --query "DBInstances[?DBInstanceIdentifier=='${APP}-db'].DBInstanceIdentifier | [0]" --output text)")
DB_PASSWORD_PARAM_EXISTS=$(clean "$(aws ssm get-parameters --names "$PARAM_PATH/DB_PASSWORD" --query 'Parameters[0].Name' --output text)")

DB_PASSWORD=""
if [ -z "$DB_INSTANCE_EXISTS" ] || [ -z "$DB_PASSWORD_PARAM_EXISTS" ]; then
    echo
    if [ -z "$DB_INSTANCE_EXISTS" ]; then
        echo "RDS のマスターパスワードを決めて入力してください（8〜41 文字。/ \" @ と空白は使えません）。"
    else
        echo "RDS（${APP}-db）は作成済みです。作成時に決めたマスターパスワードを入力してください。"
    fi
    while true; do
        read -r -s -p "DB パスワード: " DB_PASSWORD; echo
        read -r -s -p "DB パスワード（確認）: " DB_PASSWORD_CONFIRM; echo
        if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
            warn "一致しません。もう一度入力してください。"
        elif [ ${#DB_PASSWORD} -lt 8 ] || [ ${#DB_PASSWORD} -gt 41 ]; then
            warn "8〜41 文字にしてください。"
        elif [[ "$DB_PASSWORD" =~ [/\"@[:space:]] ]]; then
            warn "/ \" @ と空白は使えません。"
        else
            break
        fi
    done
fi

echo
echo "Google Books の API キーを入力してください（入力は表示されません）。"
echo "空のまま Enter を押すと、登録済みの値をそのまま使います（未登録なら書籍検索が回数制限にかかりやすくなります）。"
read -r -s -p "GOOGLE_BOOKS_API_KEY: " GOOGLE_BOOKS_API_KEY; echo

# ---------------------------------------------------------------------------
# 1. VPC
# ---------------------------------------------------------------------------
log "1. VPC"

find_by_name() { # $1: describe コマンド名, $2: 結果のキー, $3: ID のキー, $4: Name タグ
    clean "$(aws ec2 "$1" --filters "Name=tag:Name,Values=$4" --query "$2[0].$3" --output text)"
}

tag() { aws ec2 create-tags --resources "$1" --tags "Key=Name,Value=$2" "Key=Project,Value=$APP"; }

VPC_ID=$(find_by_name describe-vpcs Vpcs VpcId "${APP}-vpc")
if [ -z "$VPC_ID" ]; then
    VPC_ID=$(aws ec2 create-vpc --cidr-block 10.0.0.0/16 --query Vpc.VpcId --output text)
    tag "$VPC_ID" "${APP}-vpc"
    aws ec2 wait vpc-available --vpc-ids "$VPC_ID"
    aws ec2 modify-vpc-attribute --vpc-id "$VPC_ID" --enable-dns-support '{"Value":true}'
    aws ec2 modify-vpc-attribute --vpc-id "$VPC_ID" --enable-dns-hostnames '{"Value":true}'
    info "作成: $VPC_ID"
else
    info "既存: $VPC_ID"
fi

IGW_ID=$(clean "$(aws ec2 describe-internet-gateways --filters "Name=attachment.vpc-id,Values=$VPC_ID" --query 'InternetGateways[0].InternetGatewayId' --output text)")
if [ -z "$IGW_ID" ]; then
    IGW_ID=$(aws ec2 create-internet-gateway --query InternetGateway.InternetGatewayId --output text)
    tag "$IGW_ID" "${APP}-igw"
    aws ec2 attach-internet-gateway --internet-gateway-id "$IGW_ID" --vpc-id "$VPC_ID"
    info "インターネットゲートウェイを作成: $IGW_ID"
fi

mapfile -t AZS < <(aws ec2 describe-availability-zones --filters Name=state,Values=available Name=zone-type,Values=availability-zone \
    --query 'sort_by(AvailabilityZones,&ZoneName)[].ZoneName' --output text | tr '\t' '\n' | head -n 2)

ensure_subnet() { # $1: 名前, $2: CIDR, $3: AZ
    local id
    id=$(clean "$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=$1" --query 'Subnets[0].SubnetId' --output text)")
    if [ -z "$id" ]; then
        id=$(aws ec2 create-subnet --vpc-id "$VPC_ID" --cidr-block "$2" --availability-zone "$3" --query Subnet.SubnetId --output text)
        tag "$id" "$1"
        info "サブネットを作成: $1 ($3) $id" >&2
    fi
    echo "$id"
}

PUBLIC_SUBNET_1=$(ensure_subnet "${APP}-public-1" 10.0.0.0/20 "${AZS[0]}")
PUBLIC_SUBNET_2=$(ensure_subnet "${APP}-public-2" 10.0.16.0/20 "${AZS[1]}")
PRIVATE_SUBNET_1=$(ensure_subnet "${APP}-private-1" 10.0.128.0/20 "${AZS[0]}")
PRIVATE_SUBNET_2=$(ensure_subnet "${APP}-private-2" 10.0.144.0/20 "${AZS[1]}")

for subnet in "$PUBLIC_SUBNET_1" "$PUBLIC_SUBNET_2"; do
    aws ec2 modify-subnet-attribute --subnet-id "$subnet" --map-public-ip-on-launch
done

PUBLIC_RT=$(clean "$(aws ec2 describe-route-tables --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=${APP}-public-rt" --query 'RouteTables[0].RouteTableId' --output text)")
if [ -z "$PUBLIC_RT" ]; then
    PUBLIC_RT=$(aws ec2 create-route-table --vpc-id "$VPC_ID" --query RouteTable.RouteTableId --output text)
    tag "$PUBLIC_RT" "${APP}-public-rt"
    aws ec2 create-route --route-table-id "$PUBLIC_RT" --destination-cidr-block 0.0.0.0/0 --gateway-id "$IGW_ID" >/dev/null
    info "パブリック用ルートテーブルを作成: $PUBLIC_RT"
fi
for subnet in "$PUBLIC_SUBNET_1" "$PUBLIC_SUBNET_2"; do
    associated=$(clean "$(aws ec2 describe-route-tables --route-table-ids "$PUBLIC_RT" \
        --query "RouteTables[0].Associations[?SubnetId=='$subnet'].RouteTableAssociationId | [0]" --output text)")
    [ -z "$associated" ] && aws ec2 associate-route-table --route-table-id "$PUBLIC_RT" --subnet-id "$subnet" >/dev/null
done
# プライベートサブネットはメインのルートテーブル（VPC 内の通信のみ）を使う

# ---------------------------------------------------------------------------
# 2. セキュリティグループ
# ---------------------------------------------------------------------------
log "2. セキュリティグループ"

ensure_sg() { # $1: 名前, $2: 説明
    local id
    id=$(clean "$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" "Name=group-name,Values=$1" --query 'SecurityGroups[0].GroupId' --output text)")
    if [ -z "$id" ]; then
        id=$(aws ec2 create-security-group --vpc-id "$VPC_ID" --group-name "$1" --description "$2" --query GroupId --output text)
        tag "$id" "$1"
        info "作成: $1 $id" >&2
    fi
    echo "$id"
}

allow() { # 既に同じルールがあればエラーになるので無視する
    aws ec2 authorize-security-group-ingress "$@" >/dev/null 2>&1 || true
}

WEB_SG=$(ensure_sg "${APP}-web" "ReadLog web server")
DB_SG=$(ensure_sg "${APP}-db" "ReadLog database")
allow --group-id "$WEB_SG" --protocol tcp --port 80 --cidr 0.0.0.0/0
allow --group-id "$WEB_SG" --protocol tcp --port 443 --cidr 0.0.0.0/0
allow --group-id "$DB_SG" --protocol tcp --port 3306 --source-group "$WEB_SG"
info "web: $WEB_SG（80/443 を公開、SSH なし）"
info "db : $DB_SG（web からの 3306 のみ）"

# ---------------------------------------------------------------------------
# 3. S3 バケット
# ---------------------------------------------------------------------------
log "3. S3 バケット"

BUCKET=""
for name in $(aws s3api list-buckets --query "Buckets[?starts_with(Name, '${APP}-deploy')].Name" --output text); do
    location=$(clean "$(aws s3api get-bucket-location --bucket "$name" --query LocationConstraint --output text)")
    if [ "$location" = "$AWS_REGION" ]; then
        BUCKET=$name
        info "既存（東京）: $BUCKET"
        break
    fi
    warn "東京以外のリージョンのバケット $name（${location:-us-east-1}）は使いません。不要なら削除してください。"
done
if [ -z "$BUCKET" ]; then
    BUCKET="${APP}-deploy-${ACCOUNT_ID}-${AWS_REGION}"
    aws s3api create-bucket --bucket "$BUCKET" --create-bucket-configuration "LocationConstraint=$AWS_REGION" >/dev/null
    info "作成: $BUCKET"
fi
aws s3api put-public-access-block --bucket "$BUCKET" \
    --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
aws s3api put-bucket-lifecycle-configuration --bucket "$BUCKET" --lifecycle-configuration '{
    "Rules": [{"ID": "expire-releases", "Status": "Enabled", "Filter": {"Prefix": "releases/"}, "Expiration": {"Days": 30}}]
}'
info "パブリックアクセスをブロック、releases/ は 30 日で自動削除"

# ---------------------------------------------------------------------------
# 4. EC2 用の IAM ロール
# ---------------------------------------------------------------------------
log "4. EC2 用の IAM ロール"

EC2_ROLE=${APP}-ec2-role
if ! aws iam get-role --role-name "$EC2_ROLE" >/dev/null 2>&1; then
    aws iam create-role --role-name "$EC2_ROLE" --assume-role-policy-document '{
        "Version": "2012-10-17",
        "Statement": [{"Effect": "Allow", "Principal": {"Service": "ec2.amazonaws.com"}, "Action": "sts:AssumeRole"}]
    }' >/dev/null
    info "作成: $EC2_ROLE"
else
    info "既存: $EC2_ROLE"
fi
aws iam attach-role-policy --role-name "$EC2_ROLE" --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
aws iam put-role-policy --role-name "$EC2_ROLE" --policy-name "${APP}-ec2-app" \
    --policy-document "$(sed "s/ACCOUNT_ID/$ACCOUNT_ID/g" "$SCRIPT_DIR/ec2-role-policy.json")"
info "AmazonSSMManagedInstanceCore と ${APP}-ec2-app（Parameter Store の読み取り）を設定"

if ! aws iam get-instance-profile --instance-profile-name "$EC2_ROLE" >/dev/null 2>&1; then
    aws iam create-instance-profile --instance-profile-name "$EC2_ROLE" >/dev/null
    info "インスタンスプロファイルを作成"
fi
if [ -z "$(clean "$(aws iam get-instance-profile --instance-profile-name "$EC2_ROLE" --query 'InstanceProfile.Roles[0].RoleName' --output text)")" ]; then
    aws iam add-role-to-instance-profile --instance-profile-name "$EC2_ROLE" --role-name "$EC2_ROLE"
    info "インスタンスプロファイルの反映を待機（10 秒）"
    sleep 10
fi

# ---------------------------------------------------------------------------
# 5. RDS（作成に 10 分ほどかかるので、待たずに先へ進む）
# ---------------------------------------------------------------------------
log "5. RDS（MySQL）"

DB_SUBNET_GROUP=${APP}-db-subnets
if ! aws rds describe-db-subnet-groups --db-subnet-group-name "$DB_SUBNET_GROUP" >/dev/null 2>&1; then
    aws rds create-db-subnet-group --db-subnet-group-name "$DB_SUBNET_GROUP" \
        --db-subnet-group-description "ReadLog private subnets" \
        --subnet-ids "$PRIVATE_SUBNET_1" "$PRIVATE_SUBNET_2" >/dev/null
    info "DB サブネットグループを作成: $DB_SUBNET_GROUP"
fi

if [ -z "$DB_INSTANCE_EXISTS" ]; then
    aws rds create-db-instance \
        --db-instance-identifier "${APP}-db" \
        --engine mysql \
        --db-instance-class db.t4g.micro \
        --allocated-storage 20 \
        --storage-type gp3 \
        --master-username admin \
        --master-user-password "$DB_PASSWORD" \
        --db-name readlog \
        --db-subnet-group-name "$DB_SUBNET_GROUP" \
        --vpc-security-group-ids "$DB_SG" \
        --no-publicly-accessible \
        --no-multi-az \
        --backup-retention-period 1 \
        --storage-encrypted \
        --tags "Key=Project,Value=$APP" >/dev/null
    info "作成を開始: ${APP}-db（完了まで 10 分ほど。後でまとめて待つ）"
else
    info "既存: ${APP}-db"
fi

# ---------------------------------------------------------------------------
# 6. EC2 と Elastic IP
# ---------------------------------------------------------------------------
log "6. EC2（Ubuntu 24.04 / t4g.micro）"

INSTANCE_ID=$(clean "$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=${APP}-web" "Name=instance-state-name,Values=pending,running,stopping,stopped" \
    --query 'Reservations[0].Instances[0].InstanceId' --output text)")
if [ -z "$INSTANCE_ID" ]; then
    AMI_ID=$(aws ssm get-parameter --name /aws/service/canonical/ubuntu/server/24.04/stable/current/arm64/hvm/ebs-gp3/ami-id \
        --query Parameter.Value --output text)
    INSTANCE_ID=$(aws ec2 run-instances \
        --image-id "$AMI_ID" \
        --instance-type t4g.micro \
        --subnet-id "$PUBLIC_SUBNET_1" \
        --security-group-ids "$WEB_SG" \
        --iam-instance-profile "Name=$EC2_ROLE" \
        --metadata-options HttpTokens=required,HttpEndpoint=enabled \
        --block-device-mappings 'DeviceName=/dev/sda1,Ebs={VolumeSize=20,VolumeType=gp3,Encrypted=true}' \
        --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${APP}-web},{Key=Project,Value=$APP}]" \
        --query 'Instances[0].InstanceId' --output text)
    info "作成: $INSTANCE_ID（AMI $AMI_ID）"
else
    info "既存: $INSTANCE_ID"
fi
aws ec2 wait instance-running --instance-ids "$INSTANCE_ID"

ALLOCATION_ID=$(find_by_name describe-addresses Addresses AllocationId "${APP}-web")
if [ -z "$ALLOCATION_ID" ]; then
    ALLOCATION_ID=$(aws ec2 allocate-address --domain vpc --query AllocationId --output text)
    tag "$ALLOCATION_ID" "${APP}-web"
    info "Elastic IP を割り当て"
fi
aws ec2 associate-address --allocation-id "$ALLOCATION_ID" --instance-id "$INSTANCE_ID" --allow-reassociation >/dev/null
PUBLIC_IP=$(aws ec2 describe-addresses --allocation-ids "$ALLOCATION_ID" --query 'Addresses[0].PublicIp' --output text)
info "Elastic IP: $PUBLIC_IP"

# ---------------------------------------------------------------------------
# 7. GitHub Actions 用の IAM ロール（OIDC）
# ---------------------------------------------------------------------------
log "7. GitHub Actions 用の IAM ロール"

OIDC_ARN="arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"
if ! aws iam get-open-id-connect-provider --open-id-connect-provider-arn "$OIDC_ARN" >/dev/null 2>&1; then
    aws iam create-open-id-connect-provider \
        --url https://token.actions.githubusercontent.com \
        --client-id-list sts.amazonaws.com \
        --thumbprint-list 6938fd4d98bab03faadb97b34396831e3780aea1 1c58a3a8518e8759bf075b76b750d4f2df264fcd >/dev/null
    info "GitHub の OIDC プロバイダを登録"
fi

GITHUB_ROLE=${APP}-github-deploy
TRUST_POLICY=$(sed -e "s/ACCOUNT_ID/$ACCOUNT_ID/g" -e "s#y-suzuki-hub/laravel-app#$GITHUB_REPO#g" "$SCRIPT_DIR/github-trust-policy.json")
if ! aws iam get-role --role-name "$GITHUB_ROLE" >/dev/null 2>&1; then
    aws iam create-role --role-name "$GITHUB_ROLE" --assume-role-policy-document "$TRUST_POLICY" >/dev/null
    info "作成: $GITHUB_ROLE"
else
    aws iam update-assume-role-policy --role-name "$GITHUB_ROLE" --policy-document "$TRUST_POLICY"
    info "既存: $GITHUB_ROLE（信頼ポリシーを更新）"
fi
aws iam put-role-policy --role-name "$GITHUB_ROLE" --policy-name "$GITHUB_ROLE" \
    --policy-document "$(sed -e "s/ACCOUNT_ID/$ACCOUNT_ID/g" -e "s/BUCKET_NAME/$BUCKET/g" -e "s/INSTANCE_ID/$INSTANCE_ID/g" "$SCRIPT_DIR/github-deploy-policy.json")"
GITHUB_ROLE_ARN=$(aws iam get-role --role-name "$GITHUB_ROLE" --query Role.Arn --output text)

# ---------------------------------------------------------------------------
# 8. RDS の完成を待って Parameter Store に登録
# ---------------------------------------------------------------------------
log "8. RDS の完成を待っています（初回は 10 分ほど）"
aws rds wait db-instance-available --db-instance-identifier "${APP}-db"
DB_HOST=$(aws rds describe-db-instances --db-instance-identifier "${APP}-db" --query 'DBInstances[0].Endpoint.Address' --output text)
info "エンドポイント: $DB_HOST"

log "Parameter Store（$PARAM_PATH）"

put_param() { # $1: キー, $2: 値, $3: String / SecureString
    # --value に直接渡すと http:// や file:// で始まる値を AWS CLI が URL として読みに行くため、JSON で渡す
    aws ssm put-parameter --overwrite --cli-input-json \
        "$(jq -n --arg name "$PARAM_PATH/$1" --arg value "$2" --arg type "$3" '{Name: $name, Value: $value, Type: $type}')" >/dev/null
    info "$1"
}

# APP_KEY は変えるとログイン中のセッションなどが無効になるので、未登録のときだけ作る
if [ -z "$(clean "$(aws ssm get-parameters --names "$PARAM_PATH/APP_KEY" --query 'Parameters[0].Name' --output text)")" ]; then
    put_param APP_KEY "base64:$(openssl rand -base64 32)" SecureString
fi
put_param APP_URL "http://$PUBLIC_IP" String
put_param DB_HOST "$DB_HOST" String
put_param DB_DATABASE readlog String
put_param DB_USERNAME admin String
if [ -n "$DB_PASSWORD" ]; then
    put_param DB_PASSWORD "$DB_PASSWORD" SecureString
fi
if [ -n "$GOOGLE_BOOKS_API_KEY" ]; then
    put_param GOOGLE_BOOKS_API_KEY "$GOOGLE_BOOKS_API_KEY" SecureString
fi

# ---------------------------------------------------------------------------
# 完了
# ---------------------------------------------------------------------------
cat <<DONE

$(printf '\033[1;32m')==> 完了しました$(printf '\033[0m')

GitHub の Settings → Environments → production → Environment variables に、次の 4 つを登録してください。

  AWS_REGION       $AWS_REGION
  AWS_ROLE_ARN     $GITHUB_ROLE_ARN
  AWS_INSTANCE_ID  $INSTANCE_ID
  DEPLOY_BUCKET    $BUCKET

デプロイ後のアプリの URL: http://$PUBLIC_IP

Google Books の API キーを本番用に制限する場合は、「アプリケーションの制限」→「IP アドレス」に
$PUBLIC_IP を登録してください。
DONE
