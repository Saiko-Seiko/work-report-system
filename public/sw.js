/* ============================================================
   Service Worker
   病院の機械室や地下で電波が切れても、画面が開いて入力を続けられるようにする。

   考え方
     - CSS/JS などの部品はキャッシュを先に見る（速い・確実）
     - 画面（HTML）は通信を先に試し、だめならキャッシュを出す
     - POST は絶対にキャッシュしない。送信は offline.js が端末に溜める
     - タブレットは会社で共用するので、ログアウト時にキャッシュを全部捨てる
   ============================================================ */

var CACHE = 'wcr-v2';

var SHELL = [
  '/assets/css/app.css',
  '/assets/css/sheet.css',
  '/assets/js/app.js',
  '/assets/js/offline.js',
  '/assets/js/mic.js',
  '/assets/js/sign.js',
  '/offline.html'
];

/* キャッシュしない画面（ログイン周りと管理者サイト） */
function isPrivate(url) {
  return url.pathname === '/login'
      || url.pathname === '/logout'
      || url.pathname.indexOf('/admin') === 0;
}

self.addEventListener('install', function (ev) {
  ev.waitUntil(
    caches.open(CACHE).then(function (c) { return c.addAll(SHELL); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (ev) {
  ev.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.map(function (k) {
          return k === CACHE ? null : caches.delete(k);
        }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (ev) {
  var req = ev.request;

  /* 送信系はそのまま通す。失敗したら offline.js が受け止める */
  if (req.method !== 'GET') return;

  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  /* 画面（HTML）: 通信優先 → だめならキャッシュ → それでもだめならお知らせ */
  if (req.mode === 'navigate') {
    ev.respondWith(
      fetch(req)
        .then(function (res) {
          if (res.ok && !isPrivate(url)) {
            var copy = res.clone();
            caches.open(CACHE).then(function (c) { c.put(req, copy); });
          }
          return res;
        })
        .catch(function () {
          return caches.match(req).then(function (hit) {
            return hit || caches.match('/offline.html');
          });
        })
    );
    return;
  }

  /* 部品（CSS/JS/画像）: キャッシュ優先。?v= の違いは無視して探す */
  ev.respondWith(
    caches.match(req, { ignoreSearch: true }).then(function (hit) {
      if (hit) return hit;
      return fetch(req).then(function (res) {
        if (res.ok) {
          var copy = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, copy); });
        }
        return res;
      });
    })
  );
});

/* ログアウト時などにキャッシュを捨てる */
self.addEventListener('message', function (ev) {
  if (!ev.data || ev.data.type !== 'clear') return;
  ev.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { return caches.delete(k); }));
    })
  );
});
