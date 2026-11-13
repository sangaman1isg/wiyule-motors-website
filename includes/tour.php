<?php
/**
 * includes/tour.php — lightweight, dependency-free guided-tour engine.
 *
 * Include once near the end of a page's <body>, then register a tour:
 *
 *   <?php include __DIR__ . '/includes/tour.php'; ?>
 *   <script>
 *     WMTour.define('home', [
 *       { el:'#book-btn', title:'Book in seconds', text:'Tap here whenever...' },
 *       { title:'That\'s it!', text:'Enjoy.' }   // no `el` = centered card
 *     ]);
 *   </script>
 *
 * Behaviour: a gentle first-visit prompt (never forced), a floating "?" button to
 * replay any time, remembers completion in localStorage, spotlights each target,
 * and drops to a bottom-sheet tooltip on phones. Steps whose element is missing
 * are skipped, so the same tour config is safe across page variations.
 */
if (!defined('WM_TOUR_RENDERED')) {
    define('WM_TOUR_RENDERED', true);
?>
<style>
  .wmt-help { position: fixed; left: 18px; bottom: 18px; z-index: 900;
      width: 46px; height: 46px; border-radius: 50%; border: 0; cursor: pointer;
      background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; font-weight: 800; font-size: 20px;
      box-shadow: 0 8px 22px rgba(220,38,38,.38); display: none; align-items: center; justify-content: center;
      font-family: 'Sora', system-ui, sans-serif; transition: transform .15s; }
  .wmt-help:hover { transform: scale(1.06); }
  .wmt-help.show { display: flex; }

  .wmt-prompt { position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%) translateY(140%);
      z-index: 901; background: #0f172a; color: #fff; border-radius: 16px; padding: 12px 14px 12px 18px;
      display: flex; align-items: center; gap: 14px; box-shadow: 0 16px 40px rgba(15,23,42,.35);
      max-width: calc(100vw - 28px); font-family: 'Sora', system-ui, sans-serif;
      transition: transform .4s cubic-bezier(.2,.9,.3,1.2); }
  .wmt-prompt.show { transform: translateX(-50%) translateY(0); }
  .wmt-prompt p { margin: 0; font-size: 13.5px; line-height: 1.35; }
  .wmt-prompt p b { font-weight: 700; }
  .wmt-prompt .wmt-p-actions { display: flex; gap: 8px; flex-shrink: 0; }
  .wmt-prompt button { font-family: inherit; cursor: pointer; border-radius: 10px; font-weight: 700; font-size: 13px; padding: 8px 12px; border: 0; }
  .wmt-prompt .wmt-go { background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; }
  .wmt-prompt .wmt-no { background: transparent; color: #94a3b8; }
  @media (max-width: 520px) {
    .wmt-prompt { flex-direction: column; align-items: stretch; left: 14px; right: 14px; transform: translateY(140%); text-align: center; }
    .wmt-prompt.show { transform: translateY(0); }
    .wmt-prompt .wmt-p-actions { justify-content: center; }
  }

  .wmt-hole { position: absolute; z-index: 9000; border-radius: 14px; pointer-events: none;
      box-shadow: 0 0 0 9999px rgba(15,23,42,.72); transition: all .3s cubic-bezier(.4,0,.2,1); }
  .wmt-block { position: fixed; inset: 0; z-index: 8999; }
  .wmt-pop { position: absolute; z-index: 9001; width: min(320px, calc(100vw - 32px));
      background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(15,23,42,.35);
      padding: 18px; font-family: 'Sora', system-ui, sans-serif; opacity: 0; transition: opacity .2s; }
  .wmt-pop.show { opacity: 1; }
  .wmt-pop .wmt-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
      color: #dc2626; margin-bottom: 7px; }
  .wmt-pop h4 { margin: 0 0 6px; font-size: 16px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
  .wmt-pop p { margin: 0; font-size: 13.5px; line-height: 1.5; color: #475569; }
  .wmt-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; }
  .wmt-dots { display: flex; gap: 5px; }
  .wmt-dots i { width: 6px; height: 6px; border-radius: 50%; background: #e2e8f0; transition: background .2s, width .2s; }
  .wmt-dots i.on { background: #dc2626; width: 16px; border-radius: 3px; }
  .wmt-btns { display: flex; gap: 8px; }
  .wmt-btns button { font-family: inherit; cursor: pointer; border-radius: 10px; font-weight: 700; font-size: 13px; padding: 8px 14px; border: 0; }
  .wmt-back { background: #f1f5f9; color: #475569; }
  .wmt-next { background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; }
  .wmt-skip { position: absolute; top: 14px; right: 14px; background: none; border: 0; cursor: pointer;
      color: #94a3b8; font-size: 12px; font-weight: 600; font-family: inherit; }
  .wmt-pop .wmt-arrow { position: absolute; width: 14px; height: 14px; background: #fff; transform: rotate(45deg); }
  @media (max-width: 520px) {
    .wmt-pop { position: fixed !important; left: 14px !important; right: 14px !important; width: auto !important;
        bottom: 16px !important; top: auto !important; transform: none !important; }
    .wmt-pop .wmt-arrow { display: none; }
  }
</style>
<script>
(function () {
  if (window.WMTour) return;
  var LSK = 'wm_tour_v1_';
  var tours = {}, curId = null, steps = [], i = 0;
  var hole, block, pop, helpBtn;

  function seen(id)      { try { return localStorage.getItem(LSK + id) === '1'; } catch (e) { return false; } }
  function markSeen(id)  { try { localStorage.setItem(LSK + id, '1'); } catch (e) {} }
  function $(sel)        { try { return document.querySelector(sel); } catch (e) { return null; } }

  function ensureHelp() {
    if (helpBtn) return;
    helpBtn = document.createElement('button');
    helpBtn.className = 'wmt-help'; helpBtn.setAttribute('aria-label', 'Take a tour of this page');
    helpBtn.textContent = '?';
    helpBtn.addEventListener('click', function () { if (curId) start(curId); });
    document.body.appendChild(helpBtn);
  }

  function showPrompt(id) {
    var p = document.createElement('div');
    p.className = 'wmt-prompt';
    p.innerHTML = '<p>👋 <b>New here?</b> Take a quick 30-second tour of this page.</p>' +
      '<div class="wmt-p-actions"><button class="wmt-go">Show me</button><button class="wmt-no">Maybe later</button></div>';
    document.body.appendChild(p);
    requestAnimationFrame(function () { p.classList.add('show'); });
    p.querySelector('.wmt-go').addEventListener('click', function () { p.remove(); start(id); });
    p.querySelector('.wmt-no').addEventListener('click', function () { markSeen(id); p.classList.remove('show'); setTimeout(function(){ p.remove(); }, 400); });
  }

  // Keep only steps whose target exists (or that are centered cards with no `el`).
  function resolve(list) {
    return list.filter(function (s) { return !s.el || $(s.el); });
  }

  function start(id) {
    var def = tours[id]; if (!def) return;
    steps = resolve(def); if (!steps.length) return;
    curId = id; i = 0;
    if (!block) {
      block = document.createElement('div'); block.className = 'wmt-block';
      hole  = document.createElement('div'); hole.className = 'wmt-hole';
      pop   = document.createElement('div'); pop.className = 'wmt-pop';
      document.body.appendChild(block); document.body.appendChild(hole); document.body.appendChild(pop);
      block.addEventListener('click', end);
      window.addEventListener('resize', reposition);
      window.addEventListener('scroll', reposition, true);
      document.addEventListener('keydown', onKey);
    }
    block.style.display = hole.style.display = pop.style.display = 'block';
    render();
  }

  function onKey(e) {
    if (e.key === 'Escape') end();
    else if (e.key === 'ArrowRight') go(1);
    else if (e.key === 'ArrowLeft') go(-1);
  }

  function go(d) { var n = i + d; if (n < 0) return; if (n >= steps.length) { end(); return; } i = n; render(); }

  function end() {
    if (curId) markSeen(curId);
    if (block) { block.style.display = hole.style.display = pop.style.display = 'none'; }
    ensureHelp(); helpBtn.classList.add('show');
  }

  function render() {
    var s = steps[i];
    var el = s.el ? $(s.el) : null;
    pop.classList.remove('show');

    var dots = steps.map(function (_, k) { return '<i class="' + (k === i ? 'on' : '') + '"></i>'; }).join('');
    var last = (i === steps.length - 1);
    pop.innerHTML =
      (s.eyebrow ? '<div class="wmt-eyebrow">' + esc(s.eyebrow) + '</div>' : '') +
      '<h4>' + esc(s.title || '') + '</h4>' +
      '<p>' + esc(s.text || '') + '</p>' +
      '<button class="wmt-skip">Skip</button>' +
      '<div class="wmt-foot"><div class="wmt-dots">' + dots + '</div>' +
      '<div class="wmt-btns">' +
        (i > 0 ? '<button class="wmt-back">Back</button>' : '') +
        '<button class="wmt-next">' + (last ? 'Done' : 'Next') + '</button>' +
      '</div></div>' +
      '<div class="wmt-arrow" style="display:none"></div>';

    pop.querySelector('.wmt-skip').addEventListener('click', end);
    pop.querySelector('.wmt-next').addEventListener('click', function () { go(1); });
    var back = pop.querySelector('.wmt-back'); if (back) back.addEventListener('click', function () { go(-1); });

    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      setTimeout(function () { place(el); pop.classList.add('show'); }, 320);
    } else {
      // Centered card, no spotlight.
      hole.style.width = hole.style.height = '0'; hole.style.boxShadow = '0 0 0 9999px rgba(15,23,42,.72)';
      hole.style.left = '50%'; hole.style.top = '45%';
      pop.style.left = '50%'; pop.style.top = '50%'; pop.style.transform = 'translate(-50%,-50%)';
      pop.querySelector('.wmt-arrow').style.display = 'none';
      pop.classList.add('show');
    }
  }

  function place(el) {
    var r = el.getBoundingClientRect(), pad = 8;
    var sx = window.scrollX, sy = window.scrollY;
    hole.style.transform = 'none';
    hole.style.left = (r.left + sx - pad) + 'px';
    hole.style.top  = (r.top + sy - pad) + 'px';
    hole.style.width  = (r.width + pad * 2) + 'px';
    hole.style.height = (r.height + pad * 2) + 'px';

    if (window.innerWidth <= 520) return; // bottom-sheet handles position via CSS

    var pw = 320, gap = 16, below = r.bottom + gap + 170 < window.innerHeight;
    var top = below ? (r.bottom + sy + gap) : (r.top + sy - gap);
    var left = Math.min(Math.max(r.left + sx + r.width / 2 - pw / 2, sx + 12), sx + window.innerWidth - pw - 12);
    pop.style.transform = below ? 'none' : 'translateY(-100%)';
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';

    var arrow = pop.querySelector('.wmt-arrow');
    arrow.style.display = 'block';
    arrow.style.left = Math.min(Math.max(r.left + sx + r.width / 2 - left - 7, 14), pw - 28) + 'px';
    if (below) { arrow.style.top = '-7px'; arrow.style.boxShadow = '-3px -3px 6px rgba(15,23,42,.04)'; }
    else { arrow.style.bottom = '-7px'; arrow.style.top = 'auto'; arrow.style.boxShadow = '3px 3px 6px rgba(15,23,42,.04)'; }
  }

  function reposition() {
    if (!steps.length || !block || block.style.display === 'none') return;
    var s = steps[i]; var el = s && s.el ? $(s.el) : null; if (el) place(el);
  }

  function esc(t) {
    return String(t).replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  window.WMTour = {
    define: function (id, list, opts) {
      opts = opts || {};
      tours[id] = list; curId = id;
      ensureHelp(); helpBtn.classList.add('show');
      if (!seen(id) && opts.auto !== false) {
        // Give the page a beat to settle before inviting them.
        setTimeout(function () { if (!seen(id)) showPrompt(id); }, opts.delay || 1400);
      }
    },
    start: start,
    reset: function (id) { try { localStorage.removeItem(LSK + id); } catch (e) {} }
  };
})();
</script>
<?php } ?>
