#!/usr/bin/env bash
#
# setup.sh で作成した ReadLog の AWS 環境をすべて削除する（課金を止めたいとき）。
# AWS CloudShell で実行する。RDS のデータも消えるので注意。
#
#   bash deploy/aws/teardown.sh
#
set -uo pipefail

export AWS_REGION=ap-northeast-1
export AWS_DEFAULT_REGION=$AWS_REGION
export AWS_PAGER=""

APP=readlog
PARAM_PATH=/readlog/production

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
clean() { [ "$1" = "None" ] && echo "" || echo "$1"; }

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

echo "アカウント $ACCOUNT_ID / リージョン $AWS_REGION の ReadLog 環境（EC2・RDS・VPC・S3・IAM ロール・パラメータ）を削除します。"
echo "RDS のデータは復元できません。"
read -r -p "続けるには delete と入力してください: " answer
[ "$answer" = "delete" ] || { echo "中止しました。"; exit 1; }

log "EC2 と Elastic IP"
INSTANCE_ID=$(clean "$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=${APP}-web" "Name=instance-state-name,Values=pending,running,stopping,stopped" \
    --query 'Reservations[0].Instances[0].InstanceId' --output text)")
ALLOCATION_ID=$(clean "$(aws ec2 describe-addresses --filters "Name=tag:Name,Values=${APP}-web" --query 'Addresses[0].AllocationId' --output text)")
if [ -n "$ALLOCATION_ID" ]; then
    ASSOCIATION_ID=$(clean "$(aws ec2 describe-addresses --allocation-ids "$ALLOCATION_ID" --query 'Addresses[0].AssociationId' --output text)")
    [ -n "$ASSOCIATION_ID" ] && aws ec2 disassociate-address --association-id "$ASSOCIATION_ID"
    aws ec2 release-address --allocation-id "$ALLOCATION_ID" && info "Elastic IP を解放"
fi
if [ -n "$INSTANCE_ID" ]; then
    aws ec2 terminate-instances --instance-ids "$INSTANCE_ID" >/dev/null && info "終了を開始: $INSTANCE_ID"
fi

log "RDS"
if aws rds describe-db-instances --db-instance-identifier "${APP}-db" >/dev/null 2>&1; then
    aws rds delete-db-instance --db-instance-identifier "${APP}-db" --skip-final-snapshot --delete-automated-backups >/dev/null
    info "削除を開始: ${APP}-db（完了まで数分〜10 分）"
fi

[ -n "$INSTANCE_ID" ] && { info "EC2 の終了を待機"; aws ec2 wait instance-terminated --instance-ids "$INSTANCE_ID"; }
if aws rds describe-db-instances --db-instance-identifier "${APP}-db" >/dev/null 2>&1; then
    info "RDS の削除を待機"
    aws rds wait db-instance-deleted --db-instance-identifier "${APP}-db"
fi
aws rds delete-db-subnet-group --db-subnet-group-name "${APP}-db-subnets" 2>/dev/null && info "DB サブネットグループを削除"

log "VPC"
VPC_ID=$(clean "$(aws ec2 describe-vpcs --filters "Name=tag:Name,Values=${APP}-vpc" --query 'Vpcs[0].VpcId' --output text)")
if [ -n "$VPC_ID" ]; then
    for sg in $(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" --query "SecurityGroups[?GroupName!='default'].GroupId" --output text); do
        # 互いに参照しているルールを先に消す
        aws ec2 revoke-security-group-ingress --group-id "$sg" \
            --ip-permissions "$(aws ec2 describe-security-groups --group-ids "$sg" --query 'SecurityGroups[0].IpPermissions' --output json)" >/dev/null 2>&1
    done
    for sg in $(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" --query "SecurityGroups[?GroupName!='default'].GroupId" --output text); do
        aws ec2 delete-security-group --group-id "$sg" && info "セキュリティグループを削除: $sg"
    done
    for rt in $(aws ec2 describe-route-tables --filters "Name=vpc-id,Values=$VPC_ID" \
        --query 'RouteTables[?!(Associations[?Main==`true`])].RouteTableId' --output text); do
        for assoc in $(aws ec2 describe-route-tables --route-table-ids "$rt" \
            --query 'RouteTables[0].Associations[?Main!=`true`].RouteTableAssociationId' --output text); do
            aws ec2 disassociate-route-table --association-id "$assoc"
        done
        aws ec2 delete-route-table --route-table-id "$rt" && info "ルートテーブルを削除: $rt"
    done
    for subnet in $(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" --query 'Subnets[].SubnetId' --output text); do
        aws ec2 delete-subnet --subnet-id "$subnet" && info "サブネットを削除: $subnet"
    done
    for igw in $(aws ec2 describe-internet-gateways --filters "Name=attachment.vpc-id,Values=$VPC_ID" --query 'InternetGateways[].InternetGatewayId' --output text); do
        aws ec2 detach-internet-gateway --internet-gateway-id "$igw" --vpc-id "$VPC_ID"
        aws ec2 delete-internet-gateway --internet-gateway-id "$igw" && info "インターネットゲートウェイを削除: $igw"
    done
    aws ec2 delete-vpc --vpc-id "$VPC_ID" && info "VPC を削除: $VPC_ID"
fi

log "S3（東京のデプロイ用バケット）"
for name in $(aws s3api list-buckets --query "Buckets[?starts_with(Name, '${APP}-deploy')].Name" --output text); do
    location=$(clean "$(aws s3api get-bucket-location --bucket "$name" --query LocationConstraint --output text)")
    if [ "$location" = "$AWS_REGION" ]; then
        aws s3 rb "s3://$name" --force >/dev/null && info "バケットを削除: $name"
    fi
done

log "Parameter Store"
for name in $(aws ssm get-parameters-by-path --path "$PARAM_PATH" --recursive --query 'Parameters[].Name' --output text); do
    aws ssm delete-parameter --name "$name" && info "削除: $name"
done

log "IAM ロール"
for role in "${APP}-github-deploy" "${APP}-ec2-role"; do
    aws iam get-role --role-name "$role" >/dev/null 2>&1 || continue
    for policy in $(aws iam list-role-policies --role-name "$role" --query 'PolicyNames[]' --output text); do
        aws iam delete-role-policy --role-name "$role" --policy-name "$policy"
    done
    for arn in $(aws iam list-attached-role-policies --role-name "$role" --query 'AttachedPolicies[].PolicyArn' --output text); do
        aws iam detach-role-policy --role-name "$role" --policy-arn "$arn"
    done
    if aws iam get-instance-profile --instance-profile-name "$role" >/dev/null 2>&1; then
        aws iam remove-role-from-instance-profile --instance-profile-name "$role" --role-name "$role" 2>/dev/null
        aws iam delete-instance-profile --instance-profile-name "$role"
    fi
    aws iam delete-role --role-name "$role" && info "削除: $role"
done
info "GitHub の OIDC プロバイダは他のリポジトリでも使えるので残しています（不要なら IAM の ID プロバイダから削除）。"

log "削除が完了しました"
