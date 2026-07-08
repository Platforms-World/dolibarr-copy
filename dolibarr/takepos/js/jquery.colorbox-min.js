(function(window, document, $) {
  if (!$ || $.colorbox) return;

  function px(value, total) {
    if (typeof value === 'number') return value + 'px';
    value = (value || '').toString().trim();
    if (!value) return '';
    if (value.slice(-1) === '%') {
      var n = parseFloat(value);
      if (isNaN(n)) return value;
      return Math.max(200, Math.round(total * n / 100)) + 'px';
    }
    return value;
  }

  function ensure() {
    if (document.getElementById('takepos-cbox-overlay')) return;

    var overlay = document.createElement('div');
    overlay.id = 'takepos-cbox-overlay';
    overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99998;';

    var box = document.createElement('div');
    box.id = 'takepos-cbox-box';
    box.style.cssText = 'display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:90vw;height:88vh;background:#fff;border-radius:10px;box-shadow:0 18px 50px rgba(0,0,0,.35);z-index:99999;overflow:hidden;';

    var head = document.createElement('div');
    head.style.cssText = 'height:44px;line-height:44px;padding:0 52px 0 16px;background:#f6f6f6;border-bottom:1px solid #ddd;font-family:Arial,sans-serif;font-size:15px;font-weight:600;color:#333;position:relative;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';

    var title = document.createElement('div');
    title.id = 'takepos-cbox-title';

    var close = document.createElement('button');
    close.type = 'button';
    close.id = 'takepos-cbox-close';
    close.innerHTML = '&#10005;';
    close.style.cssText = 'position:absolute;right:10px;top:7px;width:30px;height:30px;border:none;border-radius:6px;background:#fff;cursor:pointer;font-size:18px;line-height:30px;';

    var frame = document.createElement('iframe');
    frame.id = 'takepos-cbox-iframe';
    frame.setAttribute('frameborder', '0');
    frame.style.cssText = 'width:100%;height:calc(100% - 45px);border:0;display:block;background:#fff;';

    head.appendChild(title);
    head.appendChild(close);
    box.appendChild(head);
    box.appendChild(frame);
    document.body.appendChild(overlay);
    document.body.appendChild(box);

    function closeBox() { $.colorbox.close(); }
    overlay.addEventListener('click', closeBox);
    close.addEventListener('click', closeBox);
    document.addEventListener('keydown', function(ev) {
      if (ev.key === 'Escape') closeBox();
    });
  }

  $.colorbox = function(opts) {
    opts = opts || {};
    ensure();
    var overlay = document.getElementById('takepos-cbox-overlay');
    var box = document.getElementById('takepos-cbox-box');
    var title = document.getElementById('takepos-cbox-title');
    var frame = document.getElementById('takepos-cbox-iframe');
    title.textContent = opts.title || '';
    box.style.width = px(opts.width || '90%', window.innerWidth);
    box.style.height = px(opts.height || '88%', window.innerHeight);
    overlay.style.display = 'block';
    box.style.display = 'block';
    frame.src = opts.href || 'about:blank';
    return box;
  };

  $.colorbox.close = function() {
    var overlay = document.getElementById('takepos-cbox-overlay');
    var box = document.getElementById('takepos-cbox-box');
    var frame = document.getElementById('takepos-cbox-iframe');
    if (frame) frame.src = 'about:blank';
    if (overlay) overlay.style.display = 'none';
    if (box) box.style.display = 'none';
  };
})(window, document, window.jQuery);