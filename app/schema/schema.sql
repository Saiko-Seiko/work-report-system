-- 作業完了報告書SYSTEM スキーマ
--
-- {{PK}} / {{TAIL}} は tools/migrate.php がドライバに応じて置換する。
--   SQLite : INTEGER PRIMARY KEY AUTOINCREMENT            / (なし)
--   MySQL  : INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY / ENGINE=InnoDB ...
-- VARCHAR / DATETIME / TINYINT は SQLite でもそのまま通るので統一して使っている。

-- ============================================================
-- 認証・組織
-- ============================================================

-- 管理者（事務局）
CREATE TABLE admins (
  id            {{PK}},
  account_id    VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  notify_email  VARCHAR(255) NULL,            -- 報告書の受信メールアドレス（K-7-1）
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_admins_account_id ON admins (account_id);

-- 利用者アカウント。概要書どおり「各作業人ではなく会社毎」に発行する
CREATE TABLE accounts (
  id            {{PK}},
  account_id    VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  company_name  VARCHAR(255) NOT NULL,
  email         VARCHAR(255) NULL,
  failed_count  INT          NOT NULL DEFAULT 0,   -- 3回でロック
  is_locked     TINYINT      NOT NULL DEFAULT 0,   -- 解除は事務局のみ
  locked_at     DATETIME     NULL,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_accounts_account_id ON accounts (account_id);

-- ログイン試行の記録。アカウント単位のロックとは別に、
-- 同一IPからの総当たりも検知できるようにしておく（提案時の改善点）
CREATE TABLE login_attempts (
  id          {{PK}},
  login_kind  VARCHAR(16)  NOT NULL,          -- user | admin
  account_id  VARCHAR(64)  NULL,
  ip          VARCHAR(64)  NULL,
  succeeded   TINYINT      NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_login_attempts_ip ON login_attempts (ip, created_at);

-- 「ユーザーID,パスワードを保持」（1-1 / K-1）の実体。
-- パスワードそのものは端末にもサーバーにも残さず、
-- 使い捨ての自動ログイン用トークンを端末のCookieに置く方式にしている。
-- 使うたびに値を作り替えるので、盗まれた古いトークンは即座に無効になる。
CREATE TABLE remember_tokens (
  id             {{PK}},
  actor_kind     VARCHAR(16)  NOT NULL,          -- user | admin
  actor_id       INT          NOT NULL,
  selector       VARCHAR(32)  NOT NULL,          -- Cookie前半（検索用）
  validator_hash VARCHAR(255) NOT NULL,          -- Cookie後半のSHA-256
  user_agent     VARCHAR(255) NULL,
  expires_at     DATETIME     NOT NULL,
  last_used_at   DATETIME     NULL,
  created_at     DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_remember_selector ON remember_tokens (selector);
CREATE INDEX ix_remember_actor ON remember_tokens (actor_kind, actor_id);

-- 通信が切れている間に端末へ溜めた操作の受付記録。
-- 同じ op_id が二度届いても一度しか処理しないための台帳（二重登録防止の本体）。
CREATE TABLE sync_ops (
  id         {{PK}},
  op_id      VARCHAR(64)  NOT NULL,
  account_id INT          NULL,
  path       VARCHAR(255) NULL,
  created_at DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_sync_ops_op_id ON sync_ops (op_id);

-- 誰が・いつ・何を触ったか。IDが会社単位なので運用上ここが効く
CREATE TABLE audit_logs (
  id          {{PK}},
  actor_kind  VARCHAR(16)  NOT NULL,          -- user | admin | system
  actor_id    VARCHAR(64)  NULL,
  action      VARCHAR(64)  NOT NULL,
  target      VARCHAR(128) NULL,
  detail      TEXT         NULL,
  ip          VARCHAR(64)  NULL,
  created_at  DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_audit_logs_created ON audit_logs (created_at);

-- ============================================================
-- マスタ
-- ============================================================

-- 作業者テーブル（5-2 / K-3 の作業者1〜5）
CREATE TABLE workers (
  id         {{PK}},
  account_id INT          NOT NULL,
  name       VARCHAR(128) NOT NULL,
  kana       VARCHAR(128) NULL,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_workers_account ON workers (account_id, deleted_at);

-- 機種名マスタ（K-5）。作業内容・測定値の型式で共用する
CREATE TABLE machine_models (
  id         {{PK}},
  name       VARCHAR(128) NOT NULL,
  kana       VARCHAR(128) NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_machine_models_sort ON machine_models (sort_order, id);

-- 交換部品マスタ（K-4）。1万点規模。
-- kana は提案でお願いした「ヨミガナ」列。これが無いと50音ソートができない
CREATE TABLE parts (
  id         {{PK}},
  name       VARCHAR(191) NOT NULL,
  kana       VARCHAR(191) NULL,
  unit       VARCHAR(16)  NOT NULL DEFAULT '個',
  priority   INT          NOT NULL DEFAULT 0,   -- 優先順位（大きいほど上に出す）
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_parts_name ON parts (name);          -- 重複エラー検知用
CREATE INDEX ix_parts_kana ON parts (kana);                 -- 50音ソート
CREATE INDEX ix_parts_priority ON parts (priority, id);     -- よく使う部品を先頭に

-- 報告事項テーブル（5-3 = 会社ごと / K-6 = 事務局が登録した全社共通）
CREATE TABLE report_texts (
  id         {{PK}},
  account_id INT          NULL,                 -- NULL = 全社共通（事務局登録）
  body       TEXT         NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_report_texts_account ON report_texts (account_id, deleted_at);

-- 確認署名画面のチェック項目（2-5）。
-- 概要書では「確認事項3〜5」が未定だったので、文言を後から変えられるようにした
CREATE TABLE checklist_items (
  id         {{PK}},
  label      VARCHAR(255) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT      NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL
){{TAIL}};

-- ============================================================
-- 報告書（客先提出用）
-- ============================================================

CREATE TABLE reports (
  id             {{PK}},
  report_no      INT          NOT NULL,          -- 一覧に出す No.
  account_id     INT          NOT NULL,
  -- タブレット側で発行する固有キー。同じものが何度届いても1件にまとめる（二重登録防止）
  client_uuid    VARCHAR(64)  NOT NULL,

  status         VARCHAR(16)  NOT NULL DEFAULT 'draft', -- draft|submitted|completed
  created_date   DATE         NULL,               -- 作成日
  hospital_name  VARCHAR(255) NULL,
  work_date      DATE         NULL,               -- 作業日（開始）
  work_date_end  DATE         NULL,               -- 複数日にまたがる場合
  work_place     VARCHAR(255) NULL,
  work_title     VARCHAR(255) NULL,
  workers_text   VARCHAR(255) NULL,               -- 表示用に確定した作業者名

  work_note      TEXT         NULL,               -- 2-2 任意入力
  parts_note     TEXT         NULL,               -- 2-3 自由記述
  report_body    TEXT         NULL,               -- 2-4 報告事項

  checked_ids    VARCHAR(255) NULL,               -- チェック済 checklist_items.id（カンマ区切り）
  submitter_name VARCHAR(128) NULL,               -- 2-5 下段の「作業者」＝報告書PDFの「担当」
  signature_file VARCHAR(255) NULL,               -- 2-6 で書いたサイン画像（data/ 配下）
  signature_at   DATETIME     NULL,

  pdf_at         DATETIME     NULL,               -- 提出用PDFを作った日時（一覧のPDF●）
  mail_count     INT          NOT NULL DEFAULT 0, -- 一覧のMail列
  submitted_at   DATETIME     NULL,
  completed_at   DATETIME     NULL,               -- 一覧の状態「完」

  device_saved_at DATETIME    NULL,               -- 端末で保存した時刻
  synced_at       DATETIME    NULL,               -- サーバーに届いた時刻
  created_at      DATETIME    NOT NULL,
  updated_at      DATETIME    NOT NULL,
  deleted_at      DATETIME    NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_reports_client_uuid ON reports (client_uuid);
CREATE UNIQUE INDEX ux_reports_no ON reports (report_no);
CREATE INDEX ix_reports_list ON reports (account_id, work_date, id);
CREATE INDEX ix_reports_hospital ON reports (hospital_name);

-- 作業者（複数選択可・自由入力も可）
CREATE TABLE report_workers (
  id         {{PK}},
  report_id  INT          NOT NULL,
  worker_id  INT          NULL,                  -- NULL = 自由入力
  name       VARCHAR(128) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_workers ON report_workers (report_id, sort_order);

-- 2-2 作業内容（機種と台数）
CREATE TABLE report_models (
  id         {{PK}},
  report_id  INT          NOT NULL,
  model_id   INT          NULL,
  model_name VARCHAR(128) NOT NULL,              -- マスタが変わっても報告書は当時の名前を残す
  qty        INT          NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_models ON report_models (report_id, sort_order);

-- 2-3 交換部品
CREATE TABLE report_parts (
  id         {{PK}},
  report_id  INT          NOT NULL,
  part_id    INT          NULL,
  part_name  VARCHAR(191) NOT NULL,
  unit       VARCHAR(16)  NOT NULL DEFAULT '個',
  qty        INT          NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_parts ON report_parts (report_id, sort_order);

-- 2-4 測定値
CREATE TABLE report_measurements (
  id               {{PK}},
  report_id        INT          NOT NULL,
  room_name        VARCHAR(128) NULL,
  model_name       VARCHAR(128) NULL,
  cumulative_hours INT          NULL,            -- 0〜100000
  serial_no        VARCHAR(32)  NULL,            -- 製造No（6桁）
  manufactured_ym  VARCHAR(7)   NULL,            -- YYYY-MM
  sort_order       INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_measurements ON report_measurements (report_id, sort_order);

-- ============================================================
-- 社内用報告書（4-1〜4-8）
-- ============================================================

CREATE TABLE internal_reports (
  id             {{PK}},
  report_id      INT          NOT NULL,
  created_date   DATE         NULL,
  hospital_name  VARCHAR(255) NULL,
  work_date      DATE         NULL,
  work_place     VARCHAR(255) NULL,
  workers_text   VARCHAR(255) NULL,
  work_title     VARCHAR(255) NULL,

  remaining_work TEXT         NULL,              -- 4-2 今回作業時の残作業
  travel_out_from VARCHAR(5)  NULL,              -- 4-4 移動(往)
  travel_out_to   VARCHAR(5)  NULL,
  work_from       VARCHAR(5)  NULL,              -- 作業
  work_to         VARCHAR(5)  NULL,
  travel_back_from VARCHAR(5) NULL,              -- 移動(復)
  travel_back_to   VARCHAR(5) NULL,
  sales_approach TEXT         NULL,              -- 4-5 客先への営業アプローチ
  remarks        TEXT         NULL,              -- 4-5 備考（社内への報告事項）

  pdf_at         DATETIME     NULL,              -- 一覧の「社内用」●
  completed_at   DATETIME     NULL,              -- 「完了」ボタン（請求済）
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_internal_reports_report ON internal_reports (report_id);

-- 4-3 再手配の必要な部材
CREATE TABLE internal_report_parts (
  id                 {{PK}},
  internal_report_id INT          NOT NULL,
  part_id            INT          NULL,
  part_name          VARCHAR(191) NOT NULL,
  unit               VARCHAR(16)  NOT NULL DEFAULT '個',
  qty                INT          NOT NULL DEFAULT 0,
  sort_order         INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_internal_report_parts ON internal_report_parts (internal_report_id, sort_order);

-- ============================================================
-- メール送信ログ（2-10）
-- ============================================================

CREATE TABLE mail_logs (
  id         {{PK}},
  report_id  INT          NOT NULL,
  kind       VARCHAR(16)  NOT NULL DEFAULT 'report', -- report | internal
  to_addr    VARCHAR(255) NOT NULL,
  cc_addr    VARCHAR(512) NULL,
  subject    VARCHAR(255) NULL,
  body       TEXT         NULL,
  is_dry_run TINYINT      NOT NULL DEFAULT 0,
  sent_at    DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_mail_logs_report ON mail_logs (report_id, sent_at);
