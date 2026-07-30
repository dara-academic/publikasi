/* Pencarian di sisi pengunjung, tanpa server.

   Indeksnya baru diunduh ketika kotak pencarian benar-benar disentuh, jadi
   pengunjung yang tidak berniat mencari tidak ikut menanggung 92 KB itu.

   Penilaian kecocokan: kata yang muncul di judul jauh lebih berarti daripada
   kata yang muncul di badan teks, dan semua kata yang diketik harus ada,
   supaya mengetik dua kata justru mempersempit hasil, bukan melebarkannya. */
(function () {
  var kotak = document.querySelector('.cari-input');
  if (!kotak) return;

  var panel = document.querySelector('.cari-hasil');
  var bungkus = document.querySelector('.cari');
  var indeks = null, memuat = false, pilih = -1, butir = [];
  var naik = kotak.getAttribute('data-naik') || '';

  function muat() {
    if (indeks || memuat) return Promise.resolve();
    memuat = true;
    return fetch(naik + 'assets/cari.json')
      .then(function (r) { return r.json(); })
      .then(function (d) { indeks = d; })
      .catch(function () { indeks = []; });
  }

  function nilai(b, kata) {
    var total = 0;
    for (var i = 0; i < kata.length; i++) {
      var k = kata[i];
      if (b.t.indexOf(k) < 0) return 0;          // semua kata wajib ada
      total += b.j.toLowerCase().indexOf(k) >= 0 ? 10 : 0;
      total += b.k.toLowerCase().indexOf(k) >= 0 ? 3 : 0;
      total += Math.min(b.t.split(k).length - 1, 4);
    }
    return total;
  }

  function sorot(teks, kata) {
    var aman = teks.replace(/[&<>]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
    });
    kata.forEach(function (k) {
      if (k.length < 2) return;
      var pola = new RegExp('(' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
      aman = aman.replace(pola, '<mark>$1</mark>');
    });
    return aman;
  }

  function gambar(kata) {
    if (!butir.length) {
      panel.innerHTML = '<p class="cari-kosong">Tidak ada yang cocok. Coba kata lain, '
        + 'misalnya nama mata kuliah, nama jurnal, atau istilah metodologi.</p>';
      panel.hidden = false;
      return;
    }
    panel.innerHTML = butir.map(function (b, i) {
      return '<a class="cari-butir' + (i === pilih ? ' kini' : '') + '" href="' + naik + b.u + '">'
        + '<span class="cari-kel">' + b.k + '</span>'
        + '<span class="cari-judul">' + sorot(b.j, kata) + '</span>'
        + '<span class="cari-ringkas">' + sorot(b.r, kata) + '</span></a>';
    }).join('');
    panel.hidden = false;
  }

  function cari() {
    var q = kotak.value.trim().toLowerCase();
    pilih = -1;
    if (q.length < 2) { panel.hidden = true; butir = []; return; }
    var kata = q.split(/\s+/).filter(Boolean);
    butir = (indeks || []).map(function (b) { return { b: b, n: nilai(b, kata) }; })
      .filter(function (x) { return x.n > 0; })
      .sort(function (a, b) { return b.n - a.n; })
      .slice(0, 8).map(function (x) { return x.b; });
    gambar(kata);
  }

  var tunda;
  kotak.addEventListener('input', function () {
    clearTimeout(tunda);
    tunda = setTimeout(function () { muat().then(cari); }, 120);
  });
  kotak.addEventListener('focus', muat);

  kotak.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { panel.hidden = true; kotak.blur(); return; }
    if (panel.hidden) return;
    var tautan = panel.querySelectorAll('.cari-butir');
    if (!tautan.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      pilih += e.key === 'ArrowDown' ? 1 : -1;
      if (pilih < 0) pilih = tautan.length - 1;
      if (pilih >= tautan.length) pilih = 0;
      tautan.forEach(function (a, i) { a.classList.toggle('kini', i === pilih); });
      tautan[pilih].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && pilih >= 0) {
      e.preventDefault();
      tautan[pilih].click();
    }
  });

  document.addEventListener('click', function (e) {
    if (!bungkus.contains(e.target)) panel.hidden = true;
  });
})();
