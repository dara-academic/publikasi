/* Unggah berkas per potongan 2 MB, dengan pengulangan per potongan.
   Menghindari galat Safari "request body stream exhausted" (NSURLErrorDomain -1021)
   saat koneksi terputus di tengah unggahan. Berlaku untuk <form data-potong>;
   pasangannya di server ada di potongan.php. Tanpa JavaScript, formulir terkirim biasa. */
(function () {
  if (!window.fetch || !window.FormData || !window.Promise || !window.crypto || !crypto.getRandomValues
      || !window.Blob || !Blob.prototype.slice) return;
  var UKURAN = 2097152, ULANG = 4;

  function idAcak() {
    var a = new Uint8Array(16); crypto.getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }
  function tunggu(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function mb(b) { return Math.round(b / 1048576) + ' MB'; }

  function kirimPotong(url, csrf, id, i, total, blob, coba) {
    var fd = new FormData();
    fd.append('csrf', csrf); fd.append('aksi', 'potong'); fd.append('id', id);
    fd.append('indeks', String(i)); fd.append('total', String(total)); fd.append('potong', blob, 'potong.bin');
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'manual', cache: 'no-store' })
      .then(function (r) {
        if (r.type === 'opaqueredirect' || r.status === 0) {
          throw { tetap: true, pesan: 'Sesi masuk sudah habis. Masuk lagi, lalu ulangi unggahan.' };
        }
        return r.json().catch(function () {
          throw { tetap: r.status < 500, pesan: 'Server menolak unggahan (kode ' + r.status + ').' };
        }).then(function (j) {
          if (!j || !j.ok) throw { tetap: true, pesan: 'Server menolak unggahan: ' + ((j && j.galat) || 'sebab tidak diketahui') + '.' };
        });
      })
      .catch(function (e) {
        if (e && e.tetap) throw e;
        if (coba >= ULANG) throw { pesan: 'Koneksi terputus saat mengunggah. Periksa jaringan, lalu coba lagi.' };
        return tunggu(800 * Math.pow(2, coba)).then(function () { return kirimPotong(url, csrf, id, i, total, blob, coba + 1); });
      });
  }

  function sisip(form, nama, nilai) {
    var x = document.createElement('input');
    x.type = 'hidden'; x.name = nama; x.value = nilai; x.setAttribute('data-potong-isi', '');
    form.appendChild(x);
  }

  Array.prototype.forEach.call(document.querySelectorAll('form[data-potong]'), function (form) {
    var tombol = form.querySelector('button[type=submit]');
    var status = document.createElement('p');
    status.className = 'masuk-bantu unggah-status'; status.setAttribute('aria-live', 'polite');
    tombol.parentNode.insertBefore(status, tombol.nextSibling);
    var jalan = false;

    function pulihkan() {
      jalan = false; tombol.disabled = false;
      Array.prototype.forEach.call(form.querySelectorAll('input[data-potong-isi]'), function (x) { x.parentNode.removeChild(x); });
      Array.prototype.forEach.call(form.querySelectorAll('input[type=file]'), function (x) { x.disabled = false; });
    }
    window.addEventListener('pageshow', function (ev) { if (ev.persisted) { pulihkan(); status.textContent = ''; } });

    form.addEventListener('submit', function (ev) {
      if (jalan) { ev.preventDefault(); return; }
      var masukan = Array.prototype.filter.call(form.querySelectorAll('input[type=file]'), function (x) { return x.files && x.files.length; });
      if (!masukan.length) return;
      ev.preventDefault();

      var tugas = [];
      masukan.forEach(function (inp) {
        var batas = +inp.getAttribute('data-maks') || 31457280;
        Array.prototype.forEach.call(inp.files, function (f) { tugas.push({ inp: inp, f: f, batas: batas }); });
      });
      for (var k = 0; k < tugas.length; k++) {
        if (tugas[k].f.size > tugas[k].batas) { status.textContent = tugas[k].f.name + ' lebih dari ' + mb(tugas[k].batas) + '. Kecilkan atau pecah berkasnya.'; return; }
        if (tugas[k].f.size === 0) { status.textContent = tugas[k].f.name + ' kosong (0 B).'; return; }
      }

      var totalByte = tugas.reduce(function (s, t) { return s + t.f.size; }, 0), terkirim = 0;
      var csrf = form.querySelector('input[name=csrf]').value;
      var url = form.getAttribute('action') || location.pathname;
      jalan = true; tombol.disabled = true;
      status.textContent = 'Mulai mengunggah...';

      var rantai = Promise.resolve();
      tugas.forEach(function (t, urut) {
        rantai = rantai.then(function () {
          var id = idAcak(), total = Math.ceil(t.f.size / UKURAN), i = 0;
          function lanjut() {
            if (i >= total) return Promise.resolve();
            var awal = i * UKURAN, blob = t.f.slice(awal, Math.min(awal + UKURAN, t.f.size));
            return kirimPotong(url, csrf, id, i, total, blob, 0).then(function () {
              terkirim += blob.size; i++;
              status.textContent = 'Mengunggah' + (tugas.length > 1 ? ' berkas ' + (urut + 1) + ' dari ' + tugas.length + ',' : '')
                + ' ' + Math.round(terkirim / totalByte * 100) + '%';
              return lanjut();
            });
          }
          return lanjut().then(function () {
            var ganda = t.inp.multiple, n = t.inp.name.replace(/\[\]$/, ''), akhir = ganda ? '[]' : '';
            sisip(form, n + '_potongan' + akhir, id);
            sisip(form, n + '_total' + akhir, String(total));
            sisip(form, n + '_nama' + akhir, t.f.name);
          });
        });
      });

      rantai.then(function () {
        status.textContent = 'Unggahan selesai, menyimpan materi...';
        masukan.forEach(function (x) { x.disabled = true; });
        HTMLFormElement.prototype.submit.call(form);
      }).catch(function (e) {
        pulihkan();
        status.textContent = (e && e.pesan) || 'Unggahan gagal. Coba lagi.';
      });
    });
  });
})();
