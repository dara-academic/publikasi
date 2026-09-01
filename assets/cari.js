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

/* Batang kemajuan baca, hanya dipasang di halaman yang memang panjang.
   Di halaman pendek batang seperti ini tidak memberi tahu apa pun dan hanya
   menambah gerakan yang mengganggu. */
(function () {
  if (document.documentElement.scrollHeight < window.innerHeight * 3) return;
  var batang = document.createElement('div');
  batang.className = 'baca-maju';
  document.body.appendChild(batang);
  var tunggu = false;
  function gambar() {
    var tinggi = document.documentElement.scrollHeight - window.innerHeight;
    batang.style.width = (tinggi > 0 ? (window.scrollY / tinggi) * 100 : 0) + '%';
    tunggu = false;
  }
  window.addEventListener('scroll', function () {
    if (!tunggu) { tunggu = true; requestAnimationFrame(gambar); }
  }, { passive: true });
  gambar();
})();

/* Pengirim catatan kunjungan.

   Memakai gambar satu piksel, bukan permintaan jaringan biasa, supaya tidak
   terhalang penyekat iklan yang lazim dan tidak menunda pemuatan halaman.
   Tidak ada kuki, tidak ada penyimpanan di peramban, dan tidak ada data yang
   pergi ke pihak ketiga. */
(function () {
  if (location.hostname === 'localhost' || location.protocol === 'file:') return;
  try {
    var akar = document.querySelector('.cari-input');
    var naik = akar ? akar.getAttribute('data-naik') || '' : '';
    var g = new Image(1, 1);
    g.src = naik + 'catat.php?h=' + encodeURIComponent(location.pathname)
          + '&r=' + encodeURIComponent(document.referrer || '')
          + '&w=' + window.innerWidth
          + '&_=' + Math.random().toString(36).slice(2);
  } catch (e) { /* pencatatan tidak boleh mengganggu halaman */ }
})();

/* ------------------------------------------------------------------
   Tombol kembali ke atas. Dibuat dari sini, bukan ditulis di markup,
   supaya 98 halaman tidak perlu disunting satu per satu. Muncul hanya
   setelah pembaca melewati kira-kira dua layar, karena di halaman
   pendek ia cuma jadi hiasan yang menghalangi sudut baca.
   ------------------------------------------------------------------ */
(function () {
  var b = document.createElement('button');
  b.className = 'ke-atas';
  b.setAttribute('aria-label', 'Kembali ke atas');
  b.innerHTML = '↑';
  document.body.appendChild(b);
  b.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  var tunda = false;
  window.addEventListener('scroll', function () {
    if (tunda) return;
    tunda = true;
    requestAnimationFrame(function () {
      b.classList.toggle('tampil', window.scrollY > window.innerHeight * 2);
      tunda = false;
    });
  }, { passive: true });
})();

/* ------------------------------------------------------------------
   Gerak muncul saat digulir. Elemen bertanda .muncul memudar naik saat
   masuk layar. Dimatikan bila pengguna meminta gerak dikurangi, dan
   diberi tanda tampil langsung bila IntersectionObserver tak tersedia.
   Kelas .muncul dibubuhkan dari sini pada blok-blok utama, supaya tidak
   perlu menyunting markup tiap halaman.
   ------------------------------------------------------------------ */
(function () {
  var kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var sasaran = document.querySelectorAll(
    '.ak-utama > h2, .ak-utama .ak-pintu, .ak-utama .ak-angka, .ak-utama .ak-sumber,' +
    '.ak-utama .mt-kartu, .ak-utama .book, .ak-utama .rs-kartu, .ak-utama .ws-kartu,' +
    '.ak-utama .card, .ak-utama .glos-group, .ak-utama .prog-pil, .ak-utama .bm-kartu');
  if (!sasaran.length) return;
  if (kurangiGerak || !('IntersectionObserver' in window)) {
    sasaran.forEach(function (el) { el.classList.add('muncul', 'tampil'); });
    return;
  }
  sasaran.forEach(function (el) { el.classList.add('muncul'); });
  var io = new IntersectionObserver(function (masuk) {
    masuk.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('tampil'); io.unobserve(e.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
  sasaran.forEach(function (el) { io.observe(el); });
  /* Jaring pengaman: apa pun yang belum tampil dalam 1,2 detik dipaksa
     muncul, supaya observer yang meleset tak pernah menyembunyikan konten. */
  setTimeout(function () {
    sasaran.forEach(function (el) { el.classList.add('tampil'); });
  }, 1200);
})();

/* ------------------------------------------------------------------
   Pengalih tema terang/gelap. Pilihan disimpan di localStorage supaya
   bertahan antarhalaman dan antar kunjungan. Untuk mencegah kedip putih
   saat mode gelap, penerapan awal sebaiknya sedini mungkin; di sini
   dijalankan begitu skrip termuat, dan tombol memutar ikonnya.
   ------------------------------------------------------------------ */
(function () {
  var akar = document.documentElement;
  function pasang(tema) {
    if (tema === 'dark') akar.setAttribute('data-theme', 'dark');
    else akar.removeAttribute('data-theme');
    document.querySelectorAll('[data-tema-tombol]').forEach(function (b) {
      b.textContent = tema === 'dark' ? '☀️' : '🌙';
      b.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
    });
  }
  var tersimpan;
  try { tersimpan = localStorage.getItem('tema'); } catch (e) {}
  pasang(tersimpan === 'dark' ? 'dark' : 'terang');
  document.querySelectorAll('[data-tema-tombol]').forEach(function (b) {
    b.addEventListener('click', function () {
      var gelap = akar.getAttribute('data-theme') === 'dark';
      var baru = gelap ? 'terang' : 'dark';
      pasang(baru);
      try { localStorage.setItem('tema', baru); } catch (e) {}
    });
  });
})();

/* ------------------------------------------------------------------
   Angka menghitung naik. Elemen .hitung dengan data-akhir memanjat dari
   nol saat masuk layar, sekali saja. Dimatikan bila gerak dikurangi.
   ------------------------------------------------------------------ */
(function () {
  var angka = document.querySelectorAll('.hitung');
  if (!angka.length) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    return; /* biarkan angka akhir apa adanya */
  }
  function jalan(el) {
    var akhir = parseInt(el.dataset.akhir, 10) || 0, t0 = null;
    function tik(t) {
      if (!t0) t0 = t;
      var p = Math.min((t - t0) / 900, 1);
      el.textContent = Math.round(akhir * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tik);
    }
    el.textContent = '0'; requestAnimationFrame(tik);
  }
  var io = new IntersectionObserver(function (masuk) {
    masuk.forEach(function (e) { if (e.isIntersecting) { jalan(e.target); io.unobserve(e.target); } });
  }, { threshold: 0.4 });
  angka.forEach(function (el) { io.observe(el); });
})();

/* Ketukan kunjungan halaman: tanpa cookie, tanpa data pribadi, hanya
   menambah hitungan per path di server. */
(function () {
  try {
    var p = location.pathname || '/';
    if (navigator.sendBeacon) {
      var fd = new FormData();
      fd.append('p', p);
      navigator.sendBeacon('/hit.php', fd);
    }
  } catch (e) {}
})();
