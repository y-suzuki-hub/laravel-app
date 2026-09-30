# 読書記録SNS 設計書

仮称: **ReadLog**（読んだ本・読んでいる本を記録し、感想を共有する SNS）

## 1. コンセプト

- 本を検索して「読みたい / 読んでいる / 読了 / 中断」のステータスで本棚に登録する
- 読書中のメモや読了後の感想を投稿し、タグで整理する
- 他のユーザーをフォローして、タイムラインで感想を見る・いいねする

### 想定ユーザー

- 技術書やビジネス書を読む社会人・学生
- 読書の進捗を見える化して継続したい人

## 2. 技術スタック

| 分類 | 採用技術 | 理由 |
|---|---|---|
| バックエンド | Laravel（最新版） / PHP 8.4 | 学習対象 |
| フロントエンド | Blade + Livewire（クラスベースコンポーネント） | PHP 中心で SPA 風の操作を実現できる |
| CSS | Tailwind CSS | スターターキット標準 |
| 認証 | 公式 Livewire スターターキット | ログイン・登録・パスワードリセットを標準の実装で用意 |
| DB | 開発: SQLite / MySQL（Sail） → 本番: Amazon RDS for MySQL | 手軽に始めて、本番はマネージド RDB に |
| テスト | Pest | Laravel 標準で、読みやすい |
| 外部 API | Google Books API | 書籍情報を入力せずに取得できる |
| キュー / スケジューラ | 開発: database ドライバ → 本番: Amazon SQS / cron | まず仕組みを学び、本番は AWS のマネージドキューに切り替える |
| ファイル保存 | 開発: local（または MinIO） → 本番: Amazon S3 | Laravel の Storage で保存先を切り替えられる |
| メール | 開発: log / Mailpit → 本番: Amazon SES | 通知メールの送信 |
| インフラ | AWS（詳細は「7. インフラ構成（AWS）」） | 実務で最も使われるクラウドを経験する |
| 静的解析 / 整形 | Laravel Pint、Larastan | 品質の担保 |
| CI / CD | GitHub Actions | テストと Pint をプッシュごとに実行、main へのマージで AWS に自動デプロイ |
| IaC | Terraform | AWS リソースをコードで管理（Step 2 で導入） |

## 3. 機能一覧とフェーズ

小さく動くものを作り、段階的に機能を足していく。

### Phase 1: MVP（本棚と投稿）

| # | 機能 | 学べること |
|---|---|---|
| 1-1 | ユーザー登録・ログイン・プロフィール編集 | 認証、スターターキットの構造 |
| 1-2 | 書籍検索（Google Books API） | HTTP クライアント、サービスクラス、キャッシュ、`Http::fake` によるテスト |
| 1-3 | 本棚への登録・ステータス変更・評価（★1〜5） | CRUD、Enum、バリデーション、Livewire |
| 1-4 | 感想・メモの投稿（作成 / 編集 / 削除） | CRUD、FormRequest / Livewire Form |
| 1-5 | 投稿へのタグ付け | 多対多（`belongsToMany`） |
| 1-6 | 他人の投稿は編集・削除できない | Policy による認可 |
| 1-7 | Feature テスト | Pest、Factory、RefreshDatabase |

### Phase 2: SNS 機能

| # | 機能 | 学べること |
|---|---|---|
| 2-1 | ユーザーページ（本棚・投稿一覧） | ルートモデルバインディング（username） |
| 2-2 | フォロー / フォロー解除 | 自己参照の多対多 |
| 2-3 | タイムライン（フォロー中ユーザーの投稿） | サブクエリ、Eager Loading（N+1 対策）、ページネーション |
| 2-4 | いいね（ページ遷移なし） | Livewire のアクション、ユニーク制約 |
| 2-5 | タグ別・書籍別の投稿一覧 | スコープ、クエリの組み立て |
| 2-6 | ネタバレ投稿の折りたたみ表示 | Alpine.js（Livewire 同梱）との連携 |

### Phase 3: 発展機能

| # | 機能 | 学べること |
|---|---|---|
| 3-1 | コメント | リレーションのネスト |
| 3-2 | 通知（フォローされた・いいね・コメント） | Notification（database / mail）、キュー |
| 3-3 | 週間人気の本ランキング | スケジューラ、集計クエリ、キャッシュ |
| 3-4 | アバター画像のアップロード | Storage、Livewire のファイルアップロード、画像バリデーション |
| 3-5 | 読書統計（月ごとの読了冊数グラフ） | 集計、グラフ表示 |

### Phase 4: 仕上げ

- GitHub Actions（Pest、Pint、Larastan）
- Laravel Sail（Docker）で起動できるようにする
- AWS へのデプロイ（「7. インフラ構成（AWS）」の Step 1 → Step 2 → Step 3）
- README（画面キャプチャ、ER 図、工夫した点）

## 4. ER 図

```mermaid
erDiagram
    users ||--o{ reading_records : "本棚に登録"
    books ||--o{ reading_records : "登録される"
    users ||--o{ posts : "投稿"
    books ||--o{ posts : "対象"
    posts }o--o{ tags : "post_tag"
    users ||--o{ likes : "いいね"
    posts ||--o{ likes : "いいねされる"
    users ||--o{ follows : "follower"
    users ||--o{ follows : "followee"
    users ||--o{ comments : "コメント"
    posts ||--o{ comments : "コメントされる"

    users {
        bigint id PK
        string name
        string username UK "URL用 (/@username)"
        string email UK
        string password
        text bio "nullable"
        string avatar_path "nullable"
        timestamps timestamps
    }
    books {
        bigint id PK
        string google_books_id UK
        string isbn_13 "nullable, index"
        string title
        string authors "カンマ区切り表示用"
        string publisher "nullable"
        string published_date "nullable (年のみの場合あり)"
        text description "nullable"
        string thumbnail_url "nullable"
        int page_count "nullable"
        timestamps timestamps
    }
    reading_records {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        string status "want / reading / finished / dropped"
        tinyint rating "nullable, 1-5"
        int current_page "nullable"
        date started_on "nullable"
        date finished_on "nullable"
        timestamps timestamps
    }
    posts {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        text body
        boolean has_spoiler
        timestamps timestamps
    }
    tags {
        bigint id PK
        string name UK
        timestamps timestamps
    }
    post_tag {
        bigint post_id FK
        bigint tag_id FK
    }
    likes {
        bigint id PK
        bigint user_id FK
        bigint post_id FK
        timestamp created_at
    }
    follows {
        bigint id PK
        bigint follower_id FK
        bigint followee_id FK
        timestamp created_at
    }
    comments {
        bigint id PK
        bigint user_id FK
        bigint post_id FK
        text body
        timestamps timestamps
    }
```

### 制約・インデックス

| テーブル | 制約 | 目的 |
|---|---|---|
| reading_records | UNIQUE(user_id, book_id) | 同じ本を二重に登録しない |
| reading_records | INDEX(user_id, status) | 「読書中の本」など本棚の絞り込み |
| posts | INDEX(user_id, created_at) | タイムライン・ユーザーページの新着順 |
| post_tag | PRIMARY(post_id, tag_id) | 重複タグ付けを防ぐ |
| likes | UNIQUE(user_id, post_id) | 二重いいねを防ぐ |
| follows | UNIQUE(follower_id, followee_id) | 二重フォローを防ぐ |
| 各 FK | `cascadeOnDelete()` | ユーザー・投稿削除時に関連データも削除 |

### 設計上の判断

- **books は全ユーザー共通のマスタ**。Google Books の検索結果は保存せず、ユーザーが本棚に登録した時点で `google_books_id` をキーに `firstOrCreate` する。同じ本を複数ユーザーが登録しても 1 レコードで済み、「この本を読んでいる人」「本ごとの感想一覧」が作れる。
- **ステータスは PHP の Backed Enum**（`App\Enums\ReadingStatus`）で表現し、DB は string カラムにする。ステータスの追加が容易で、DB 製品に依存しない。
- **投稿は reading_records ではなく book に紐づける**。本棚から外しても感想は残せる。
- **likes / follows は中間テーブルにも id を持たせる**。Eloquent の `belongsToMany` で扱いつつ、将来の通知などで個別レコードを参照しやすくする。
- **authors は正規化しない**（MVP では表示用の文字列）。著者で検索する要件が出たら `authors` テーブルに分離する。

## 5. 画面一覧・ルーティング

| 画面 | URL | 認証 | 実装 |
|---|---|---|---|
| トップ（未ログイン） | `/` | 不要 | Blade |
| ログイン / 登録 など | `/login` `/register` … | 不要 | スターターキット |
| ホーム（Phase 1: 読書中の本・新着の感想 → Phase 2: タイムライン） | `/dashboard` | 必要 | Livewire |
| 書籍検索 | `/books/search` | 必要 | Livewire（入力に応じてリアルタイム検索） |
| 書籍詳細（感想一覧） | `/books/{book}` | 不要 | Livewire |
| 自分の本棚 | `/shelf` | 必要 | Livewire（ステータスのタブ切替） |
| 投稿作成 | `/books/{book}/posts/create` | 必要 | Livewire |
| 投稿詳細 | `/posts/{post}` | 不要 | Livewire（いいね・コメント） |
| 投稿編集 | `/posts/{post}/edit` | 必要（本人のみ） | Livewire |
| タグ別一覧 | `/tags/{tag:name}` | 不要 | Livewire |
| ユーザーページ | `/@{user:username}` | 不要 | Livewire（本棚 / 投稿タブ、フォローボタン） |
| プロフィール設定 | `/settings/profile` | 必要 | スターターキットを拡張 |
| 通知 | `/notifications` | 必要 | Livewire（Phase 3） |

## 6. ディレクトリ構成（主要部分）

```
app/
├── Enums/ReadingStatus.php
├── Livewire/                 # 画面・部品ごとの Livewire コンポーネント
│   ├── Books/Search.php
│   ├── Shelf/Index.php
│   ├── Posts/{Create,Edit,Show}.php
│   ├── Timeline.php
│   ├── LikeButton.php
│   └── FollowButton.php
├── Models/{User,Book,ReadingRecord,Post,Tag,Comment}.php
├── Policies/{PostPolicy,ReadingRecordPolicy,CommentPolicy}.php
├── Services/GoogleBooks/
│   ├── GoogleBooksClient.php # API 呼び出し（Http ファサード）
│   └── BookData.php          # API レスポンスを詰める DTO
└── Notifications/            # Phase 3
```

### 実装方針

- **外部 API はサービスクラスに閉じ込める**。Livewire コンポーネントから直接 `Http::get` しない。テストでは `Http::fake()` で API をモックする。
- **検索結果は短時間キャッシュする**（同じキーワードで API を叩きすぎない）。
- **認可は Policy に集約**し、Livewire 側では `$this->authorize()` を呼ぶ。
- **一覧は必ず Eager Loading**（`with(['user', 'book', 'tags'])`、`withCount('likes')`）。開発環境では `Model::preventLazyLoading()` を有効にして N+1 を検出する。
- **テストの粒度**: 画面・操作ごとに Feature テスト（Livewire テスト）、Enum やサービスは Unit テスト。

## 7. インフラ構成（AWS）

### 方針

- **いきなり完成形を作らず、3 段階で育てる**。まずはコンソールで手作業で構築して各サービスの役割を理解し、次にコード化（Terraform）、最後にコンテナ化する。
- **費用を最小限に抑える**。個人の学習用なので、常時課金が大きいサービス（NAT Gateway、ALB など）は Step 3 まで使わない。
- **アクセスキーを作らない**。EC2 には IAM ロール、GitHub Actions には OIDC で権限を渡す。

### Step 1: シンプルな構成で公開する（コンソールで構築）

```mermaid
flowchart LR
    U[ユーザー] -->|HTTPS| R53[Route 53<br/>独自ドメイン]
    R53 --> EC2
    subgraph VPC
        subgraph public[パブリックサブネット]
            EC2["EC2<br/>Nginx + PHP-FPM<br/>queue:work / cron"]
        end
        subgraph private[プライベートサブネット]
            RDS[(RDS for MySQL)]
        end
    end
    EC2 --> RDS
    EC2 -->|IAM ロール| S3[(S3<br/>アバター画像)]
    EC2 -->|IAM ロール| SQS[[SQS<br/>ジョブキュー]]
    EC2 -->|IAM ロール| SES[SES<br/>通知メール]
    EC2 --> CW[CloudWatch Logs]
    GH[GitHub Actions] -->|OIDC + SSM| EC2
```

| サービス | 用途 | Laravel 側の設定 |
|---|---|---|
| VPC | ネットワーク。EC2 はパブリック、RDS はプライベートサブネットに置く | - |
| EC2 | Web サーバー（Nginx + PHP-FPM）。キューワーカーは Supervisor、スケジューラは cron で動かす | - |
| RDS for MySQL | データベース。EC2 のセキュリティグループからのみ接続を許可 | `DB_CONNECTION=mysql` |
| S3 | アバター画像の保存。バケットは非公開にし、署名付き URL か CloudFront で配信 | `FILESYSTEM_DISK=s3` |
| SQS | 通知メール・ランキング集計などのジョブキュー | `QUEUE_CONNECTION=sqs` |
| SES | 通知メールの送信（最初はサンドボックス＝検証済みアドレスのみに送信） | `MAIL_MAILER=ses` |
| Systems Manager | Parameter Store で秘密情報（DB パスワード、API キー）を管理。Session Manager で SSH ポートを開けずにサーバーへ接続 | `.env` をデプロイ時に生成 |
| CloudWatch | ログ収集、CPU などのアラーム | `LOG_CHANNEL=stderr` またはファイルを CloudWatch Agent で転送 |
| Route 53 + Let's Encrypt | 独自ドメインと HTTPS 化（ALB を使わない分、証明書は EC2 上で発行） | - |
| AWS Budgets | 予算アラート（想定額を超えたらメール） | - |

**デプロイの流れ（GitHub Actions）**

1. main ブランチにマージされたらテストを実行
2. OIDC で AWS の IAM ロールを引き受ける（アクセスキー不要）
3. SSM Run Command で EC2 上のデプロイスクリプトを実行（`git pull` → `composer install --no-dev` → `php artisan migrate --force` → キャッシュ再生成 → `queue:restart`）

### Step 2: Terraform でコード化する

- Step 1 で手作業で作ったリソースを Terraform で書き直し、`infra/` ディレクトリで管理する
- Terraform の state は S3 に保存する
- 一度環境を削除し、`terraform apply` だけで同じ構成を再現できることを確認する
- **面接でのアピール点**: 「手作業で理解してからコード化した」「環境を再現できる」

### Step 3: コンテナ化（発展、任意）

```mermaid
flowchart LR
    U[ユーザー] --> CF[CloudFront] --> ALB
    ALB --> ECS["ECS Fargate<br/>web / worker / scheduler"]
    ECS --> RDS[(RDS)]
    ECS --> S3[(S3)]
    ECS --> SQS[[SQS]]
    GH[GitHub Actions] -->|イメージを push| ECR[ECR] --> ECS
```

- Docker イメージを ECR に置き、ECS Fargate で Web・キューワーカー・スケジューラを別々のタスクとして動かす
- ALB と ACM で HTTPS 化、CloudFront で静的ファイルと画像を配信
- **注意**: ALB と Fargate は常時課金になり、月に数千円かかる。試したら `terraform destroy` で削除する運用にする

### 費用・セキュリティの注意

- ルートユーザーは MFA を設定して普段は使わない。作業用の IAM ユーザー（または IAM Identity Center）を作る
- **最初に AWS Budgets で予算アラートを設定する**（例: 月 $10 を超えたら通知）
- 無料枠やクレジットの条件はアカウントの作成時期で異なるため、始める前に最新の条件を確認する
- EC2・RDS は小さいインスタンス（t4g.micro / db.t4g.micro など）を選び、使わない期間は停止する
- NAT Gateway は作らない（Step 1 では EC2 をパブリックサブネットに置くことで不要にしている）
- `.env` や秘密情報はリポジトリに含めず、Parameter Store で管理する

### ローカル開発で AWS を再現する

| 本番 | ローカル（Sail） |
|---|---|
| RDS for MySQL | MySQL コンテナ |
| S3 | MinIO コンテナ（S3 互換） |
| SQS | database ドライバ（動作確認時のみ SQS を使う） |
| SES | Mailpit コンテナ（送信メールをブラウザで確認） |

Laravel は `.env` の切り替えだけで保存先・キュー・メールの送信方法を変えられるため、アプリのコードは環境ごとに変えない。

## 8. 次のステップ

1. Laravel プロジェクトを作成（Livewire スターターキット、Pest）
2. マイグレーション・モデル・Factory を作成（ER 図の Phase 1 範囲）
3. Google Books API クライアントと書籍検索画面
4. 本棚（登録・ステータス変更）
5. 投稿 CRUD とタグ、Policy
6. Phase 1 の Feature テストを揃える
7. Phase 1 が動いた時点で AWS の Step 1 を構築し、早めに公開する（公開後に機能を追加していく）
