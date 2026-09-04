/* ============================================================
   2-6 サイン入力
   Pointer Events で書く。指・スタイラス・マウスを同じ扱いにでき、
   手のひらが画面に触れても線にならないよう主ポインタだけを拾う。
   ============================================================ */
(function () {
  'use strict';

  var canvas = document.getElementById('js-pad');
  if (!canvas) return;

  var form  = document.getElementById('js-sign-form');
  var image = document.getElementById('js-image');
  var save  = document.getElementById('js-save');
  var clear = document.getElementById('js-clear');
  var hint  = document.getElementById('js-hint');

  var ctx, drawing = false, dirty = false, activeId = null, last = null;

  /* 端末の解像度に合わせて実ピクセルを確保する（線がにじまないように） */
  function setup() {
    var rect = canvas.getBoundingClientRect();
    var dpr  = window.devicePixelRatio || 1;
    var data = dirty ? canvas.toDataURL('image/png') : null;

    canvas.width  = Math.round(rect.width * dpr);
    canvas.height = Math.round(rect.height * dpr);

    ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.lineWidth = 3.2;          /* 現場で見て分かる太さ */
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#101820';

    if (data) {
      var img = new Image();
      img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
      img.src = data;
    }
  }

  function pos(ev) {
    var rect = canvas.getBoundingClientRect();
    return { x: ev.clientX - rect.left, y: ev.clientY - rect.top };
  }

  function start(ev) {
    if (activeId !== null) return;            /* 2本目の指は無視する */
    if (ev.pointerType === 'touch' && !ev.isPrimary) return;

    activeId = ev.pointerId;
    drawing = true;
    last = pos(ev);
    canvas.setPointerCapture(ev.pointerId);

    /* 点だけ打った場合も残るように */
    ctx.beginPath();
    ctx.arc(last.x, last.y, ctx.lineWidth / 2, 0, Math.PI * 2);
    ctx.fillStyle = ctx.strokeStyle;
    ctx.fill();

    markDirty();
    ev.preventDefault();
  }

  function move(ev) {
    if (!drawing || ev.pointerId !== activeId) return;
    var p = pos(ev);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    ev.preventDefault();
  }

  function end(ev) {
    if (ev.pointerId !== activeId) return;
    drawing = false;
    activeId = null;
  }

  function markDirty() {
    if (dirty) return;
    dirty = true;
    save.disabled = false;
    if (hint) hint.style.display = 'none';
  }

  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);
  canvas.addEventListener('pointerleave', end);

  clear.addEventListener('click', function () {
    dirty = false;
    setup();
    save.disabled = true;
    if (hint) hint.style.display = '';
  });

  form.addEventListener('submit', function (ev) {
    if (!dirty) { ev.preventDefault(); return; }
    image.value = canvas.toDataURL('image/png');
    save.disabled = true;
    save.textContent = '保存中…';
  });

  /* 端末を回したときも書いた線を保つ */
  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(setup, 150);
  });

  setup();
}());
