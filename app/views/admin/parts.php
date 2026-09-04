<?php
/**
 * K-4 交換部品マスター 登録画面
 * @var array $rows @var array $pager @var string $q @var string $sort @var string $dir
 * @var array|null $dialog @var array|null $diff @var array|null $notice
 */
$keep = ['q' => $q];
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/parts', $keep, $key, $label, $sort, $dir);
?>
<?php if ($notice): ?>
<div class="alert alert--<?= h($notice['kind']) ?>"><?= h($notice['message']) ?></div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn" href="/admin/parts/download">ダウンロード</a>

  <form method="post" action="/admin/parts/import" enctype="multipart/form-data" class="import-form">
    <?= csrf_field() ?>
    <input class="input" type="file" name="file" accept=".csv,text/csv" required>
    <button class="btn" type="submit">インポート</button>
  </form>

  <a class="btn btn--green" href="/admin/parts?new=1">＋追加</a>

  <span class="toolbar__spacer"></span>

  <form method="get" action="/admin/parts" style="display:flex; gap:8px; align-items:center">
    <input class="input" type="search" name="q" value="<?= h($q) ?>" placeholder="部品名・ヨミガナ">
    <button class="btn btn--ghost" type="submit">検索</button>
  </form>

  <span class="pager">
    <span><?= $pager['from'] ?>-<?= $pager['to'] ?>/<?= number_format($pager['total']) ?></span>
<?php if ($pager['page'] > 1): ?>
    <a href="<?= h(admin_page_url('/admin/parts', $keep + ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] - 1)) ?>">＜</a>
<?php else: ?><span class="muted">＜</span><?php endif; ?>
<?php if ($pager['page'] < $pager['pages']): ?>
    <a href="<?= h(admin_page_url('/admin/parts', $keep + ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] + 1)) ?>">＞</a>
<?php else: ?><span class="muted">＞</span><?php endif; ?>
  </span>
</div>

<?php if ($diff && empty($diff['ok'])): ?>
<div class="alert alert--error">
  <strong>取り込めませんでした。</strong>次の点を直してからもう一度お試しください。
  <ul class="import-errors">
<?php foreach ($diff['errors'] as $e): ?>
    <li><?= h($e) ?></li>
<?php endforeach; ?>
<?php if ($diff['more'] > 0): ?>
    <li class="muted">ほか <?= (int) $diff['more'] ?>件</li>
<?php endif; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($diff && !empty($diff['ok'])): ?>
<div class="import-preview">
  <h2>取り込む内容の確認</h2>
  <p class="muted">
    ファイル：<?= h($diff['filename']) ?>（<?= number_format($diff['total']) ?>行）<br>
    実行してよければ「この内容で取り込む」を押してください。実行前に現在のマスタを自動で控えます。
  </p>

  <div class="diff-row">
    <div class="diff-box diff-box--add">
      <b><?= number_format($diff['add']) ?></b><span>追加</span>
      <p><?= h(implode('、', $diff['samples']['add'])) ?><?= $diff['add'] > 5 ? ' …' : '' ?></p>
    </div>
    <div class="diff-box diff-box--update">
      <b><?= number_format($diff['update']) ?></b><span>変更</span>
      <p><?= h(implode('、', $diff['samples']['update'])) ?><?= $diff['update'] > 5 ? ' …' : '' ?></p>
    </div>
    <div class="diff-box diff-box--remove">
      <b><?= number_format($diff['remove']) ?></b><span>削除（隠す）</span>
      <p><?= h(implode('、', $diff['samples']['remove'])) ?><?= $diff['remove'] > 5 ? ' …' : '' ?></p>
    </div>
    <div class="diff-box">
      <b><?= number_format($diff['keep']) ?></b><span>変更なし</span>
      <p class="muted">そのまま</p>
    </div>
  </div>

<?php if ($diff['remove'] > 0): ?>
  <p class="alert alert--warn" style="margin-top:12px">
    削除になる <?= number_format($diff['remove']) ?>件は、消さずに一覧から隠すだけです。
    過去の報告書に載っている部品名はそのまま残ります。
  </p>
<?php endif; ?>

  <div class="import-actions">
    <form method="post" action="/admin/parts/import/cancel">
      <?= csrf_field() ?>
      <button class="btn btn--ghost" type="submit">やめる</button>
    </form>
    <form method="post" action="/admin/parts/import/apply">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= h($diff['token']) ?>">
      <button class="btn btn--green" type="submit">この内容で取り込む</button>
    </form>
  </div>
</div>
<?php endif; ?>

<table class="table">
  <thead>
    <tr>
      <th style="width:74px"><?= $link('no', 'No.') ?></th>
      <th><?= $link('name', '部品名') ?></th>
      <th style="width:220px"><?= $link('kana', 'ヨミガナ') ?></th>
      <th style="width:72px"><?= $link('unit', '単位') ?></th>
      <th style="width:110px"><?= $link('created', '登録日') ?></th>
      <th style="width:110px"><?= $link('priority', '優先順位') ?></th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $p): ?>
    <tr>
      <td class="num"><?= (int) $p['id'] ?></td>
      <td><a class="link" href="/admin/parts?edit=<?= (int) $p['id'] ?>"><?= h($p['name']) ?></a></td>
      <td class="muted"><?= h((string) $p['kana']) ?></td>
      <td><?= h((string) $p['unit']) ?></td>
      <td><?= h(ymd_slash((string) $p['created_at'])) ?></td>
      <td class="num"><?= number_format((int) $p['priority']) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<div class="help-box">
  <h3>入れ替えのしかた</h3>
  <ol>
    <li>「ダウンロード」で現在の登録内容をCSVで受け取る（エクセルでそのまま開けます）</li>
    <li>エクセルで直して、CSV（UTF-8）のまま保存する</li>
    <li>「インポート」でファイルを選ぶと、<strong>追加・変更・削除の件数</strong>が出ます</li>
    <li>中身を確かめて「この内容で取り込む」を押す</li>
  </ol>
  <p class="muted">
    列は「部品名・ヨミガナ・単位・優先順位」の4つです。<strong>部品名が同じものは同じ部品</strong>として扱い、
    重複があるとその場でお知らせします。ヨミガナは現場画面の50音順に使います。<br>
    取り込みは途中で失敗しても丸ごと元に戻ります。実行前の内容は <code>data/backups/</code> に控えます。
  </p>
</div>

<?php if ($dialog): ?>
<div class="modal-backdrop">
  <div class="modal">
    <div class="modal__close"><a href="/admin/parts">［×閉じる］</a></div>
    <h2 class="modal__title">
      <?= $dialog['mode'] === 'new' ? '交換部品の追加登録' : '交換部品の修正' ?>
    </h2>

<?php if (!empty($dialog['errors'])): ?>
    <div class="alert alert--error">
<?php foreach ($dialog['errors'] as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" action="/admin/parts/save">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $dialog['id'] ?>">

      <div class="modal__row">
        <label for="name">部品名：</label>
        <input class="input" type="text" id="name" name="name" value="<?= h($dialog['name']) ?>">
      </div>
      <div class="modal__row">
        <label for="kana">ヨミガナ：</label>
        <input class="input" type="text" id="kana" name="kana" value="<?= h($dialog['kana']) ?>"
               placeholder="現場画面の50音順に使います">
      </div>
      <div class="modal__row">
        <label for="unit">単位：</label>
        <input class="input" type="text" id="unit" name="unit" value="<?= h($dialog['unit']) ?>"
               style="max-width:120px">
      </div>
      <div class="modal__row">
        <label for="priority">優先順位：</label>
        <input class="input" type="number" id="priority" name="priority"
               value="<?= (int) $dialog['priority'] ?>" min="0" max="999999" style="max-width:160px">
      </div>
      <p class="modal__note">優先順位が大きいものほど、現場の「よく使う順」で先頭に出ます。</p>

      <div class="modal__actions">
<?php if ($dialog['mode'] === 'edit'): ?>
        <button class="btn btn--danger" type="submit"
                formaction="/admin/parts/delete">削除</button>
<?php endif; ?>
        <a class="btn btn--ghost" href="/admin/parts">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
