/* Mesin kuis periksa diri, berjalan sepenuhnya di peramban.

   Soalnya tertanam di halaman sebagai JSON, jadi tidak ada server, tidak ada
   pengiriman jawaban ke mana pun, dan tidak ada nilai yang dicatat siapa pun.
   Ini alat periksa diri untuk mahasiswa, bukan alat penilaian untuk dosen,
   dan batas itu disengaja: jawaban salah di sini harus terasa aman.

   Tiap jawaban langsung diberi umpan balik beserta pembahasannya, karena
   umpan balik yang tertunda sampai akhir kuis kehilangan konteks soalnya. */
(function () {
  var sumber = document.getElementById('kuis-data');
  var wadah = document.getElementById('kuis');
  if (!sumber || !wadah) return;

  var soal;
  try { soal = JSON.parse(sumber.textContent); } catch (e) { return; }

  var benar = 0, dijawab = 0;

  function huruf(i) { return String.fromCharCode(65 + i); }

  soal.forEach(function (q, idx) {
    var blok = document.createElement('div');
    blok.className = 'kz-soal';
    var tanya = document.createElement('p');
    tanya.className = 'kz-tanya';
    tanya.textContent = (idx + 1) + '. ' + q.t;
    blok.appendChild(tanya);

    var daftar = document.createElement('div');
    daftar.className = 'kz-pilihan';
    q.p.forEach(function (teks, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'kz-opsi';
      b.innerHTML = '<b>' + huruf(i) + '</b> ' + teks;
      b.addEventListener('click', function () {
        if (blok.classList.contains('kz-terkunci')) return;
        blok.classList.add('kz-terkunci');
        dijawab++;
        var betul = i === q.b;
        if (betul) benar++;
        daftar.querySelectorAll('.kz-opsi').forEach(function (x, j) {
          x.disabled = true;
          if (j === q.b) x.classList.add('kz-benar');
        });
        if (!betul) b.classList.add('kz-salah');
        var bahas = document.createElement('p');
        bahas.className = 'kz-bahas' + (betul ? ' kz-bahas-benar' : '');
        bahas.innerHTML = (betul ? 'Benar. ' : 'Jawaban yang tepat <b>' + huruf(q.b) + '</b>. ') + q.j;
        blok.appendChild(bahas);
        if (dijawab === soal.length) selesai();
      });
      daftar.appendChild(b);
    });
    blok.appendChild(daftar);
    wadah.appendChild(blok);
  });

  function selesai() {
    var kotak = document.createElement('div');
    kotak.className = 'kz-hasil';
    var pesan;
    var nisbah = benar / soal.length;
    if (nisbah === 1) pesan = 'Semua benar. Materinya sudah melekat.';
    else if (nisbah >= 0.7) pesan = 'Sudah kokoh. Baca ulang pembahasan yang meleset, lalu lanjut.';
    else if (nisbah >= 0.4) pesan = 'Separuh jalan. Unduh lagi materi pertemuannya dan ulangi kuisnya besok.';
    else pesan = 'Belum melekat, dan itu wajar di percobaan pertama. Baca materinya dulu, kuisnya menunggu.';
    kotak.innerHTML = '<b>' + benar + ' dari ' + soal.length + ' benar.</b> ' + pesan;
    wadah.appendChild(kotak);
    kotak.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
})();
