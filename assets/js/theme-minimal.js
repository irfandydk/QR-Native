/**
 * QR-SAR - Tema Minimal Glass: indikator "liquid" pada navigasi sidebar.
 * Pil kaca bergeser halus ke item yang disorot lalu kembali ke item aktif. Tanpa dependensi.
 */
(function () {
  'use strict';
  var nav = document.getElementById('sidebarNavArea');
  if (!nav) return;
  var items = Array.prototype.slice.call(nav.querySelectorAll('.sidebar-nav-item'));
  if (!items.length) return;

  var blob = document.createElement('span');
  blob.className = 'nav-liquid';
  blob.setAttribute('aria-hidden', 'true');
  nav.insertBefore(blob, nav.firstChild);
  nav.classList.add('has-liquid');

  var active = null;
  for (var i = 0; i < items.length; i++) {
    if (items[i].classList.contains('sidebar-active')) { active = items[i]; break; }
  }

  function moveTo(el, instant) {
    if (!el) { blob.style.opacity = '0'; return; }
    if (instant) blob.style.transition = 'none';
    blob.style.opacity = '1';
    blob.style.width = el.offsetWidth + 'px';
    blob.style.height = el.offsetHeight + 'px';
    blob.style.transform = 'translate(' + el.offsetLeft + 'px,' + el.offsetTop + 'px)';
    if (instant) { void blob.offsetHeight; blob.style.transition = ''; }
  }

  moveTo(active, true);
  items.forEach(function (el) {
    el.addEventListener('mouseenter', function () { moveTo(el); });
    el.addEventListener('focus', function () { moveTo(el); });
  });
  nav.addEventListener('mouseleave', function () { moveTo(active); });
  // Lebar sidebar berubah saat diciutkan/dibuka: posisikan ulang tanpa animasi
  if (window.ResizeObserver) new ResizeObserver(function () { moveTo(active, true); }).observe(nav);
})();
