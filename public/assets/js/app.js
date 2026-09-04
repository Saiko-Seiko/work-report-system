/* ============================================================
   ユーザーサイト共通のちょっとした動き。
   フレームワークは使わない。JavaScript が止まっていても
   画面の入力・送信自体は成立するように作ってある。
   ============================================================ */
(function () {
  'use strict';

  /* ---------- [-] 0 [+] の数量ボタン ---------- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-step]') : null;
    if (!btn) return;

    var box = btn.closest('[data-counter]');
    if (!box) return;
    var input = box.querySelector('.counter__value');
    if (!input) return;

    var min = parseInt(input.dataset.min || '0', 10);
    var max = parseInt(input.dataset.max || '9999', 10);
    var now = parseInt((input.value || '0').replace(/[^0-9-]/g, ''), 10);
    if (isNaN(now)) now = 0;

    var next = now + parseInt(btn.dataset.step, 10);
    input.value = Math.max(min, Math.min(max, next));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* 手入力されたときも範囲に収める */
  document.addEventListener('blur', function (ev) {
    var input = ev.target;
    if (!input.classList || !input.classList.contains('counter__value')) return;
    var min = parseInt(input.dataset.min || '0', 10);
    var max = parseInt(input.dataset.max || '9999', 10);
    var n = parseInt((input.value || '0').replace(/[^0-9-]/g, ''), 10);
    input.value = isNaN(n) ? min : Math.max(min, Math.min(max, n));
  }, true);

  /* ---------- 作業日の曜日表示（概要書 2-1-3） ---------- */
  var DOW = ['日', '月', '火', '水', '木', '金', '土'];
  function renderDow(input) {
    var target = document.getElementById(input.dataset.dow);
    if (!target) return;
    var v = input.value;
    if (!v) { target.textContent = ''; return; }
    var d = new Date(v + 'T00:00:00');
    if (isNaN(d.getTime())) { target.textContent = ''; return; }
    target.textContent = d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日('
      + DOW[d.getDay()] + ')';
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-dow]'), function (input) {
    renderDow(input);
    input.addEventListener('change', function () { renderDow(input); });
    input.addEventListener('input', function () { renderDow(input); });
  });

  /* ---------- 2-5 チェックが全部つくまで作業者を触らせない ---------- */
  var checklist = document.getElementById('js-checklist');
  if (checklist) {
    var picker = document.querySelector('.picker');

    function syncLock() {
      var boxes = checklist.querySelectorAll('input[type="checkbox"]');
      var all = boxes.length > 0;
      Array.prototype.forEach.call(boxes, function (b) { if (!b.checked) all = false; });
      if (!picker) return;

      picker.classList.toggle('is-locked', !all);
      var head = picker.querySelector('.picker__head input[readonly]');
      if (head) {
        head.placeholder = all ? '未選択' : '確認事項にチェックを入れてください';
      }
      Array.prototype.forEach.call(
        picker.querySelectorAll('input:not([readonly]), summary'),
        function (el) {
          if (el.tagName === 'SUMMARY') {
            el.classList.toggle('is-disabled', !all);
          } else {
            el.disabled = !all;
          }
        }
      );
      var details = picker.querySelector('details');
      if (details && !all) details.open = false;
    }

    checklist.addEventListener('change', syncLock);
    syncLock();
  }

  /* ---------- 新規作成のとき、端末側で固有キーを発行する ----------
     この値をサーバーに渡しておくと、送信が重なっても報告書が
     二重に作られない（同じキーなら1件にまとめる）。 */
  function newUuid() {
    if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return Date.now().toString(16) + '-' + Math.random().toString(16).slice(2, 10);
  }

  var newLink = document.getElementById('js-new-report');
  if (newLink) {
    newLink.addEventListener('click', function (ev) {
      ev.preventDefault();
      location.href = '/report/new?uuid=' + encodeURIComponent(newUuid());
    });
  }

  /* 一覧の「複製」も同じ考え方。二度押しても報告書が2件できないようにする */
  Array.prototype.forEach.call(document.querySelectorAll('form[data-copy]'), function (form) {
    var field = form.querySelector('input[name="client_uuid"]');
    if (field && !field.value) { field.value = newUuid(); }

    form.addEventListener('submit', function () {
      var btn = form.querySelector('button');
      if (btn) { btn.disabled = true; }
    });
  });

  /* ---------- 選択した作業者を上の欄に映す ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('.picker'), function (picker) {
    var head = picker.querySelector('.picker__head input[readonly]');
    if (!head) return;

    picker.addEventListener('change', function () {
      var names = [];
      Array.prototype.forEach.call(
        picker.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked'),
        function (b) { if (b.dataset.name) names.push(b.dataset.name); }
      );
      var free = picker.querySelector('.picker__free input');
      if (free && free.value.trim() !== '') names.push(free.value.trim());
      head.value = names.join('、');
    });
  });
}());
