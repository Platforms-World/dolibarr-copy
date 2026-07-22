/* ==========================================================================
 * kf_sw.js — Service Worker لصفحة كاشير Kafo POS
 * --------------------------------------------------------------------------
 * الهدف: لو انقطع النت وأعاد الكاشير تحميل الصفحة (F5) أو فتح تبويب جديد،
 * يرجع يشوف نفس واجهة pos.php (من الكاش) بدل صفحة خطأ/تسجيل دخول فاشل.
 *
 * هاد ما بيغيّر ولا بيتحايل على تسجيل الدخول: الجلسة (الكوكيز) لسا هي
 * يلي بتحدد إذا كان مسجل دخول. لو انتهت صلاحية الجلسة فعلياً وانت
 * أوفلاين، لازم اتصال بالسيرفر لتسجيل دخول جديد — هاد خارج نطاق هالملف.
 *
 * ما بيلمس إطلاقاً: ajax/*.php ، invoice.php ، api/* — هاي دايماً
 * بتروح مباشرة للسيرفر وتتعامل معها kf_offline.js لحالها.
 * ========================================================================== */

var CACHE_NAME = 'kfpos-shell-v1';

function shellKeyFor(url) {
  if (/\/takepos\/pos\.php(\?|$)/.test(url)) return 'kfpos-shell-pos';
  if (/\/takepos\/index\.php(\?|$)/.test(url)) return 'kfpos-shell-index';
  return null;
}

self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(names.filter(function (n) { return n !== CACHE_NAME; }).map(function (n) { return caches.delete(n); }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isNavigationRequest(req) {
  return req.mode === 'navigate' ||
    (req.method === 'GET' && req.headers.get('accept') && req.headers.get('accept').indexOf('text/html') > -1);
}
function isShellUrl(url) { return shellKeyFor(url) !== null; }
function isStaticAsset(url) { return /\/takepos\/(js|css)\//.test(url) || /fonts\.googleapis\.com|cdnjs\.cloudflare\.com/.test(url); }
function isDynamicEndpoint(url) { return /\/takepos\/(ajax\/|invoice\.php|api\/)/.test(url); }

self.addEventListener('fetch', function (event) {
  var req = event.request;
  var url = req.url;

  if (req.method !== 'GET') return;          // لا تلمس POST أبداً
  if (isDynamicEndpoint(url)) return;         // ajax/invoice تروح مباشرة للسيرفر دايماً

  if (isShellUrl(url) && isNavigationRequest(req)) {
    var key = shellKeyFor(url);
    event.respondWith(
      fetch(req).then(function (res) {
        var copy = res.clone();
        caches.open(CACHE_NAME).then(function (c) { c.put(key, copy); });
        return res;
      }).catch(function () {
        return caches.open(CACHE_NAME)
          .then(function (c) { return c.match(key); })
          .then(function (cached) {
            return cached || new Response(
              '<!doctype html><html dir="rtl" lang="ar"><meta charset="utf-8">' +
              '<body style="font-family:sans-serif;padding:40px;text-align:center">' +
              '<h2>غير متصل ولا توجد نسخة محفوظة من الصفحة بعد</h2>' +
              '<p>افتح صفحة الكاشير وأنت متصل بالنت مرة واحدة على الأقل حتى تُحفظ.</p>' +
              '</body></html>',
              { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
          });
      })
    );
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(
      caches.open(CACHE_NAME).then(function (c) {
        return c.match(req).then(function (cached) {
          var fetchPromise = fetch(req).then(function (res) { c.put(req, res.clone()); return res; }).catch(function () { return cached; });
          return cached || fetchPromise;
        });
      })
    );
    return;
  }
  // كل شي ثاني: سلوك الشبكة الافتراضي
});
