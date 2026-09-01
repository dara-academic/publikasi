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

/* Simpan untuk nanti: bookmark halaman di localStorage, tanpa server. */
(function () {
  var KEY = 'dara_simpan';
  function baca() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function tulis(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
  function esc(x) { var t = document.createElement('div'); t.textContent = x == null ? '' : x; return t.innerHTML; }

  var rak = document.getElementById('tersimpan-rak');
  if (rak) {
    var render = function () {
      var a = baca();
      if (!a.length) { rak.innerHTML = '<p class="admin-kosong">Belum ada yang disimpan. Buka halaman mana pun, lalu tekan tombol "Simpan untuk nanti" di pojok bawah.</p>'; return; }
      rak.innerHTML = a.map(function (it, i) {
        return '<div class="simpan-item"><a href="' + esc(it.url) + '"><b>' + esc(it.judul) + '</b></a>'
          + '<button type="button" class="simpan-hapus" data-i="' + i + '" aria-label="Hapus dari tersimpan">Hapus</button></div>';
      }).join('');
    };
    rak.addEventListener('click', function (e) {
      var b = e.target.closest('.simpan-hapus'); if (!b) return;
      var a = baca(); a.splice(+b.getAttribute('data-i'), 1); tulis(a); render();
    });
    render();
    return;
  }

  var main = document.querySelector('main.ak-utama');
  if (!main || document.querySelector('.admin-band')) return;
  var grup = document.body.getAttribute('data-grup');
  if (grup === 'beranda') return;
  var url = location.pathname;
  var judul = (document.title || url).split(/[,|]/)[0].trim() || document.title;
  function ada() { return baca().some(function (x) { return x.url === url; }); }
  var btn = document.createElement('button');
  btn.type = 'button'; btn.className = 'simpan-apung';
  var sync = function () { var s = ada(); btn.classList.toggle('aktif', s); btn.innerHTML = s ? '✓ Tersimpan' : '♡ Simpan untuk nanti'; };
  btn.addEventListener('click', function () {
    var a = baca();
    if (ada()) a = a.filter(function (x) { return x.url !== url; });
    else a.unshift({ judul: judul, url: url });
    tulis(a); sync();
  });
  sync();
  document.body.appendChild(btn);
})();

/* Tanya jawab: memuat komentar yang disetujui dan mengirim pertanyaan baru. */
(function () {
  var wrap = document.querySelector('.komentar-wrap');
  if (!wrap) return;
  var hal = location.pathname;
  var daftar = wrap.querySelector('.komentar-daftar');
  var form = wrap.querySelector('.komentar-form');
  var pesan = wrap.querySelector('.kf-pesan');
  var esc = function (x) { var t = document.createElement('div'); t.textContent = x == null ? '' : x; return t.innerHTML; };
  var token = '';
  fetch('komentar.php?hal=' + encodeURIComponent(hal)).then(function (r) { return r.json(); }).then(function (d) {
    token = d.token || '';
    if (!d.komentar || !d.komentar.length) { daftar.innerHTML = '<p class="komentar-kosong">Belum ada pertanyaan. Jadilah yang pertama.</p>'; return; }
    daftar.innerHTML = d.komentar.map(function (k) {
      var bal = k.balasan ? '<div class="komentar-balas"><b>Dr. Dara menjawab</b><p>' + esc(k.balasan) + '</p></div>' : '';
      return '<div class="komentar-item"><p class="komentar-kepala"><b>' + esc(k.nama) + '</b><span>' + esc(k.waktu) + '</span></p><p class="komentar-isi">' + esc(k.isi) + '</p>' + bal + '</div>';
    }).join('');
  }).catch(function () {});
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData();
    fd.append('hal', hal);
    fd.append('nama', form.querySelector('.kf-nama').value);
    fd.append('isi', form.querySelector('.kf-isi').value);
    fd.append('web', form.querySelector('.kf-web').value);
    fd.append('csrf', token);
    pesan.textContent = 'Mengirim...';
    fetch('komentar.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      pesan.textContent = d.pesan || (d.ok ? 'Terkirim.' : 'Gagal mengirim.');
      if (d.ok) { form.querySelector('.kf-nama').value = ''; form.querySelector('.kf-isi').value = ''; }
    }).catch(function () { pesan.textContent = 'Gagal mengirim. Coba lagi.'; });
  });
})();

/* ------------------------------------------------------------------
   Tab semester di halaman mata kuliah.

   Materi statis yang sudah ada dianggap Semester 124. Materi unggahan
   dosen (materi-mk.php) dikelompokkan per semester, mis. 125, dan tampil
   sebagai pertemuan bernomor lengkap dengan sampulnya. Halaman kuliah
   tetap statis; tab dan panel semester dibangun di sisi klien.
   ------------------------------------------------------------------ */
(function () {
  var ol = document.querySelector('main .glos-wrap .tl');
  if (!ol) return;
  var cocok = location.pathname.match(/\/mata-kuliah\/([a-z0-9\-]+)\.html$/);
  if (!cocok || cocok[1] === 'index') return;
  var slug = cocok[1];
  var SEM_DASAR = '124';

  function el(tag, kelas) { var e = document.createElement(tag); if (kelas) e.className = kelas; return e; }
  function ukuran(b) {
    if (b >= 1048576) return (Math.round(b / 1048576 * 10) / 10) + ' MB';
    if (b >= 1024) return Math.round(b / 1024) + ' KB';
    return (b || 0) + ' B';
  }
  var IKON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 12v6"/><path d="M9.5 15.5 12 18l2.5-2.5"/></svg>';

  function kartu(it) {
    var li = el('li', 'tl-butir tl-ada tl-unggah');
    var no = el('span', 'tl-no');
    no.textContent = it.pertemuan ? ('P' + it.pertemuan) : '+';
    li.appendChild(no);

    var sampul;
    if (it.sampul) {
      sampul = el('span', 'tl-sampul');
      var img = el('img');
      img.src = '../' + it.sampul;
      img.alt = 'Sampul ' + (it.judul || 'materi');
      img.loading = 'lazy'; img.decoding = 'async';
      sampul.appendChild(img);
    } else {
      sampul = el('span', 'tl-sampul tl-sampul-kosong');
      sampul.setAttribute('aria-hidden', 'true');
      sampul.innerHTML = IKON;
    }
    li.appendChild(sampul);

    var teks = el('div', 'tl-teks');
    var b = el('b'); b.textContent = it.judul || 'Materi'; teks.appendChild(b);
    if (it.deskripsi) { var top = el('span', 'tl-topik'); top.textContent = it.deskripsi; teks.appendChild(top); }
    var a = el('a', 'pk-unduh');
    a.href = '../unduh.php?mk=' + encodeURIComponent(it.mk) + '&f=' + encodeURIComponent(it.berkas);
    a.target = '_blank'; a.rel = 'noopener';
    a.appendChild(document.createTextNode('Unduh PDF '));
    var meta = el('span');
    var mt = ukuran(it.ukuran);
    if (it.unduh_teks) mt += ' · ' + it.unduh_teks + '× diunduh';
    meta.textContent = mt;
    a.appendChild(meta);
    teks.appendChild(a);
    li.appendChild(teks);
    return li;
  }

  function setStat(sem, panel) {
    var sb = document.querySelectorAll('.statbar .s');
    for (var i = 0; i < sb.length; i++) {
      var sp = sb[i].querySelector('span'), b = sb[i].querySelector('b');
      if (!sp || !b) continue;
      if (/semester/i.test(sp.textContent || '')) b.textContent = sem;
      else if (/materi siap unduh/i.test(sp.textContent || '')) b.textContent = String(panel.querySelectorAll('.tl-butir').length);
    }
  }

  fetch('../materi-mk.php?mk=' + encodeURIComponent(slug))
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var daftar = (d && d.materi) || [];
      if (!daftar.length) return;

      var perSem = {};
      daftar.forEach(function (it) {
        var s = it.semester || '125';
        (perSem[s] = perSem[s] || []).push(it);
      });

      var wrap = ol.closest('.glos-wrap');
      var kepala = wrap.querySelector('.section-head');

      var panel124 = el('div', 'sem-panel'); panel124.setAttribute('data-sem', SEM_DASAR);
      var pindah = [], n = ol;
      while (n) { pindah.push(n); n = n.nextElementSibling; }
      pindah.forEach(function (x) { panel124.appendChild(x); });
      if (perSem[SEM_DASAR]) {
        var olStatik = panel124.querySelector('.tl');
        perSem[SEM_DASAR].sort(function (a, b) { return (a.pertemuan || 99) - (b.pertemuan || 99); })
          .forEach(function (it) { olStatik.appendChild(kartu(it)); });
        delete perSem[SEM_DASAR];
      }

      var semua = [SEM_DASAR];
      Object.keys(perSem).forEach(function (s) { if (semua.indexOf(s) < 0) semua.push(s); });
      semua.sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });

      var bar = el('div', 'sem-tabs'); bar.setAttribute('role', 'tablist');
      var panels = {}; panels[SEM_DASAR] = panel124;

      semua.forEach(function (s) {
        if (!panels[s]) {
          var p = el('div', 'sem-panel'); p.setAttribute('data-sem', s);
          var ul = el('ol', 'tl');
          (perSem[s] || []).sort(function (a, b) { return (a.pertemuan || 99) - (b.pertemuan || 99); })
            .forEach(function (it) { ul.appendChild(kartu(it)); });
          p.appendChild(ul);
          panels[s] = p;
        }
        var t = el('button', 'sem-tab'); t.type = 'button';
        t.setAttribute('role', 'tab'); t.setAttribute('data-sem', s);
        t.textContent = 'Semester ' + s;
        bar.appendChild(t);
      });

      kepala.after(bar);
      var titik = bar;
      semua.forEach(function (s) { titik.after(panels[s]); titik = panels[s]; });

      var aktif = semua[semua.length - 1];
      function pilih(s) {
        semua.forEach(function (x) {
          var on = x === s;
          panels[x].hidden = !on;
          var tb = bar.querySelector('.sem-tab[data-sem="' + x + '"]');
          if (tb) { tb.classList.toggle('aktif', on); tb.setAttribute('aria-selected', on ? 'true' : 'false'); }
        });
        setStat(s, panels[s]);
      }
      bar.addEventListener('click', function (e) {
        var t = e.target.closest('.sem-tab');
        if (t) pilih(t.getAttribute('data-sem'));
      });
      pilih(aktif);
    })
    .catch(function () {});
})();
