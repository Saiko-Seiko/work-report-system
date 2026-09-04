/* ============================================================
   マイク入力（概要書「任意のテキスト入力は『マイク入力』ができるようにする」）

   data-mic="1" が付いた入力欄の横にマイクボタンを足す。
   ブラウザの音声認識（Web Speech API）を使うので、
   AndroidタブレットのChromeなら追加費用なしで動く。

   対応していない端末（iPadのSafariなど）では
   ボタンを出さずに、キーボードのマイクボタンを使ってもらう案内を1度だけ出す。
   ============================================================ */
(function () {
  'use strict';

  var fields = document.querySelectorAll('[data-mic="1"]');
  if (!fields.length) return;

  var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  if (!Recognition) {
    showFallbackNote();
    return;
  }

  var active = null;   /* いま録っている欄 */

  Array.prototype.forEach.call(fields, function (field) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mic-btn';
    btn.title = '音声で入力';
    btn.setAttribute('aria-label', '音声で入力');
    btn.innerHTML = '&#127908;';

    if (field.tagName === 'TEXTAREA') {
      var bar = document.createElement('div');
      bar.className = 'mic-bar';
      bar.appendChild(btn);
      var hint = document.createElement('span');
      hint.className = 'mic-bar__hint';
      hint.textContent = '押して話すと文字になります';
      bar.appendChild(hint);
      field.parentNode.insertBefore(bar, field);
    } else {
      field.parentNode.insertBefore(btn, field.nextSibling);
    }

    btn.addEventListener('click', function () {
      if (active && active.field === field) {
        stop();
        return;
      }
      if (active) stop();
      start(field, btn);
    });
  });

  function start(field, btn) {
    var rec;
    try {
      rec = new Recognition();
    } catch (e) {
      note(field, '音声入力を開始できませんでした。');
      return;
    }

    rec.lang = 'ja-JP';
    rec.interimResults = true;
    rec.continuous = true;
    rec.maxAlternatives = 1;

    var base = field.value;
    if (base !== '' && !/\s$/.test(base)) {
      base += (field.tagName === 'TEXTAREA' ? '\n' : '');
    }

    active = { rec: rec, field: field, btn: btn };
    btn.classList.add('is-recording');
    btn.innerHTML = '&#9632;';
    btn.title = '停止';
    note(field, '話してください（もう一度押すと停止）');

    rec.onresult = function (ev) {
      var fixed = '';
      var interim = '';
      for (var i = ev.resultIndex; i < ev.results.length; i++) {
        var t = ev.results[i][0].transcript;
        if (ev.results[i].isFinal) { fixed += t; } else { interim += t; }
      }
      if (fixed) {
        base += fixed;
      }
      field.value = base + interim;
      /* 端末への下書き保存を走らせる */
      field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    rec.onerror = function (ev) {
      if (ev.error === 'not-allowed' || ev.error === 'service-not-allowed') {
        note(field, 'マイクの使用が許可されていません。ブラウザの設定でこのサイトのマイクを許可してください。');
      } else if (ev.error === 'no-speech') {
        note(field, '音声が聞き取れませんでした。もう一度お試しください。');
      } else if (ev.error === 'network') {
        note(field, '音声認識はインターネット接続が必要です。文字入力をお使いください。');
      } else {
        note(field, '音声入力を終了しました。');
      }
      stop();
    };

    rec.onend = function () {
      if (active && active.field === field) reset();
    };

    try {
      rec.start();
    } catch (e) {
      reset();
    }
  }

  function stop() {
    if (!active) return;
    try { active.rec.stop(); } catch (e) {}
    reset();
  }

  function reset() {
    if (!active) return;
    active.btn.classList.remove('is-recording');
    active.btn.innerHTML = '&#127908;';
    active.btn.title = '音声で入力';
    var f = active.field;
    active = null;
    setTimeout(function () { clearNote(f); }, 2500);
  }

  /* ---------------- 案内表示 ---------------- */

  function note(field, message) {
    var host = field.tagName === 'TEXTAREA' ? field.parentNode : field.closest('.form-row') || field.parentNode;
    clearNote(field);
    var el = document.createElement('p');
    el.className = 'mic-note';
    el.dataset.micNote = '1';
    el.textContent = message;
    host.appendChild(el);
  }

  function clearNote(field) {
    var host = field.tagName === 'TEXTAREA' ? field.parentNode : field.closest('.form-row') || field.parentNode;
    if (!host) return;
    var old = host.querySelector('[data-mic-note]');
    if (old) old.remove();
  }

  function showFallbackNote() {
    /* 一度案内したら、同じ端末では出さない */
    try {
      if (localStorage.getItem('wcr.micNoteShown') === '1') return;
    } catch (e) {}

    var main = document.querySelector('.app-main');
    if (!main) return;

    var box = document.createElement('div');
    box.className = 'alert alert--info';
    box.innerHTML = 'この端末のブラウザは音声認識に対応していません。'
      + '文字入力欄をタップしたあと、キーボードのマイクボタン（端末に標準で付いている音声入力）'
      + 'をお使いください。'
      + '<button type="button" class="btn btn--sm btn--ghost" style="margin-top:8px">閉じる</button>';

    box.querySelector('button').addEventListener('click', function () {
      try { localStorage.setItem('wcr.micNoteShown', '1'); } catch (e) {}
      box.remove();
    });

    main.insertBefore(box, main.firstChild);
  }
}());
