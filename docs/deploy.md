# さくらのレンタルサーバ「ビジネス」への設置手順

Composer も Node も使っていないので、**ファイルを置いて設定を1つ書けば動きます**。
ビルド作業や `npm install` はありません。

所要時間の目安：30分（初回）／5分（2回目以降の更新）

---

## 1. サーバ側の準備

### 1-1. PHPのバージョン

コントロールパネル →「スクリプト設定」→「PHPのバージョン」で **8.0 以上** を選びます。
必要な拡張（PDO・pdo_mysql・mbstring）は標準で入っています。

### 1-2. データベース

コントロールパネル →「データベース」→「新規作成」

| 項目 | 値 |
|---|---|
| 文字コード | **UTF-8（utf8mb4）** |
| データベース名 | 例）`xxxxxx_report` |

作成後に表示される **サーバ名（`mysqlXXX.db.sakura.ne.jp`）・ユーザー名・パスワード** を控えます。

### 1-3. SSHを有効にする

コントロールパネル →「サーバ情報」→「SSHの設定」。
テーブル作成と動作確認に使います。使えない場合は「3-2. SSHが使えない場合」を参照してください。

---

## 2. ファイルを置く

FTP（または SCP）で次のように配置します。**`app/` と `data/` は `www` の外**に置くのが肝心です。

```
/home/アカウント名/
├── www/                    ← public/ の中身をここへ
│   ├── index.php
│   ├── .htaccess
│   ├── offline.html
│   ├── sw.js
│   └── assets/
│       ├── css/  (app.css, admin.css, sheet.css)
│       └── js/   (app.js, offline.js, mic.js, sign.js)
│
├── app/                    ← www の外
├── data/                   ← www の外（署名画像・控えの保存先）
├── tools/                  ← www の外
└── tests/                  ← www の外（不要なら置かなくても可）
```

> **なぜ www の外なのか**
> `www` の中に置くと、`app/config.local.php` をブラウザから直接読まれる可能性があります。
> データベースのパスワードが入るファイルなので、必ず外に置いてください。

配置後、`data` とその中の 4 つのフォルダを **書き込み可（755、必要なら 777）** にします。

```
data/
data/signatures/    サイン画像
data/pdf/           PDFの保存先
data/backups/       部品マスタ取り込み前の控え
data/tmp/           取り込み待ちの一時ファイル
```

---

## 3. 設定と初期化

### 3-1. 接続情報を書く

`app/config.local.php` を新しく作ります（Gitには入れないファイルです）。

```php
<?php
return [
    'db_driver' => 'mysql',
    'mysql' => [
        'host'     => 'mysqlXXX.db.sakura.ne.jp',
        'database' => 'xxxxxx_report',
        'user'     => 'xxxxxx',
        'password' => 'ここにパスワード',
        'charset'  => 'utf8mb4',
    ],

    'debug' => false,                    // 本番では必ず false

    'mail' => [
        'from_address' => 'noreply@xxxxxx.sakura.ne.jp',
        'dry_run'      => false,         // 実際に送るなら false
    ],
];
```

`app/config.php` の自社情報（報告書の右上に入ります）も直します。

```php
'company_name'    => '株式会社アイソテック',
'company_address' => '（実際の住所）',
'company_tel'     => '（実際のTEL/FAX）',
'company_branch'  => '（支店があれば）',
```

### 3-2. テーブルを作る

SSHでログインして：

```bash
cd ~
php tools/migrate.php --fresh
```

`schema : 43 statements` と出れば成功です。

> **SSHが使えない場合**
> `tools/migrate.php` を一時的に `www` に置いてブラウザから1回実行し、**すぐ削除**してください。
> （このファイルは CLI 専用の作りなので、`PHP_SAPI !== 'cli'` の判定を外す必要があります。
> 　作業後は必ず元に戻して削除してください。）

### 3-3. 最初の管理者を作る

```bash
php -r '
require "app/lib/helpers.php"; require "app/lib/Database.php";
define("APP_ROOT", getcwd()); Database::boot(config());
Database::insert("admins", [
  "account_id" => "jimukyoku",
  "password_hash" => password_hash("ここに初回パスワード", PASSWORD_DEFAULT),
  "notify_email" => "jimu@example.co.jp",
  "created_at" => date("Y-m-d H:i:s"), "updated_at" => date("Y-m-d H:i:s"),
]);
echo "管理者を作成しました\n";'
```

以降のアカウント発行は管理者サイトから行えます。

### 3-4. 確認事項マスタを入れる

確認署名画面（2-5）のチェック項目です。管理画面を用意していないので、最初に一度だけ入れます。

```bash
php -r '
require "app/lib/helpers.php"; require "app/lib/Database.php";
define("APP_ROOT", getcwd()); Database::boot(config());
$items = [
  "予定の作業はすべて終了しました",
  "作業前の状態に復旧したことを確認しました",
  "作業により発生した廃材・梱包材はすべて撤去しました",
  "設備の運転状態が正常であることを立会者と確認しました",
  "次回点検までの注意事項をご説明しました",
];
foreach ($items as $i => $label) {
  Database::insert("checklist_items", [
    "label" => $label, "sort_order" => ($i+1)*10, "is_active" => 1,
    "created_at" => date("Y-m-d H:i:s"), "updated_at" => date("Y-m-d H:i:s"),
  ]);
}
echo "確認事項を登録しました\n";'
```

### 3-5. 交換部品マスタを入れる

管理者サイト →「交換部品マスタ」→「インポート」から CSV を取り込みます。
列は **部品名・ヨミガナ・単位・優先順位** の4つです。

先に一度「ダウンロード」して、その形式のまま直すのが確実です。

---

## 4. 動作確認

```bash
php tools/preflight.php
```

置き場所・書き込み権限・接続・設定の消し忘れをまとめて見ます。
**NG が0件**になれば公開できます。

続いてブラウザで：

| URL | 確認すること |
|---|---|
| `https://xxxxxx.sakura.ne.jp/login` | ログイン画面が出る |
| `https://xxxxxx.sakura.ne.jp/admin/login` | 管理者ログインが出る |
| `https://xxxxxx.sakura.ne.jp/_dev` | **404になること**（debug=false の確認） |

---

## 5. HTTPS

コントロールパネル →「ドメイン/SSL」→ 無料SSL（Let's Encrypt）を有効にします。

オリジナルドメインを使わない場合は、初期ドメイン（`xxxxxx.sakura.ne.jp`）の共有SSLで問題ありません。

**HTTPSは必須です。** サイン画像や病院名を扱いますし、
マイク入力（音声認識）とオフライン動作（Service Worker）は
**HTTPSでないとブラウザが動かしません**。

`.htaccess` の先頭に次を足すと http でのアクセスを https へ回せます。

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

---

## 6. 2回目以降の更新

変更したファイルを上書きするだけです。

- `public/` … 上書き
- `app/` … 上書き（`config.local.php` は消さない）
- スキーマを変えたとき … `php tools/migrate.php` で差分のSQLを流す

**ブラウザに残ったキャッシュ**：CSS/JSを直したときは、
`app/views/layout_user.php` などの `?v=1` の数字を1つ増やすと、
利用者のタブレットでも確実に新しいものが読まれます。

Service Worker を直したときは `public/sw.js` の `CACHE = 'wcr-v2'` の数字を上げてください。
古いキャッシュが自動で捨てられます。

---

## 7. バックアップ

| 対象 | 方法 |
|---|---|
| データベース | コントロールパネルの phpMyAdmin からエクスポート（月1回程度） |
| サイン画像 | `data/signatures/` を FTP でダウンロード |
| 部品マスタ | 管理者サイトの「ダウンロード」でいつでもCSVに出せます |

交換部品マスタの取り込み前の控えは `data/backups/` に自動で残ります。
溜まってきたら古いものを消して構いません。

---

## 8. 困ったときに

| 症状 | 見るところ |
|---|---|
| ログイン画面しか開かない（他は404） | `.htaccess` が `www` に置かれているか。mod_rewrite が効いているか |
| 画面が真っ白 | `app/config.local.php` の書式。一時的に `debug => true` にするとエラーが出ます |
| サインが保存できない | `data/signatures/` の書き込み権限 |
| マイクのボタンが出ない | HTTPSになっているか。iPadのSafariは非対応（キーボードのマイクをご案内する画面が出ます） |
| 圏外で画面が開かない | HTTPSになっているか。一度オンラインで各画面を開くと端末に取り込まれます |
| PDFの文字が大きすぎ／小さすぎ | `public/assets/css/sheet.css` の `.sheet.d1/d2/d3` の `--sheet-font` |
| メールが届かない | `mail.dry_run` が false か。`mail_logs` テーブルに送信記録が残ります |
