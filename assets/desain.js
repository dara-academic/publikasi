/* Desain penelitian: pemilih jalur dan daftar periksa. Semua penyimpanan di peramban (localStorage). */
(function () {
  var main = document.querySelector('main[data-dp]');
  if (!main) return;
  function baca(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function tulis(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  var tombol = main.querySelectorAll('.dp-jalur-pilih button');
  function pilih(j) {
    main.setAttribute('data-jalur', j);
    for (var i = 0; i < tombol.length; i++) tombol[i].setAttribute('aria-pressed', tombol[i].getAttribute('data-pilih') === j ? 'true' : 'false');
    hitung();
  }
  for (var i = 0; i < tombol.length; i++) {
    tombol[i].addEventListener('click', function () { var j = this.getAttribute('data-pilih'); tulis('dp-jalur', j); pilih(j); });
  }

  var daftar = main.querySelector('.dp-cek');
  var kunci = daftar ? 'dp-cek-' + daftar.getAttribute('data-kunci') : null;
  function simpanan() { try { return JSON.parse(baca(kunci) || '[]'); } catch (e) { return []; } }
  function hitung() {
    if (!daftar) return;
    var semua = daftar.querySelectorAll('li'), n = 0, s = 0;
    for (var i = 0; i < semua.length; i++) {
      if (semua[i].offsetParent === null) continue;
      n++; if (semua[i].querySelector('input').checked) s++;
    }
    var el = main.querySelector('.dp-cek-hitung');
    if (el) el.textContent = s + ' dari ' + n + ' sudah beres';
  }
  if (daftar) {
    var ada = simpanan(), kotak = daftar.querySelectorAll('input[type=checkbox]');
    for (var k = 0; k < kotak.length; k++) {
      kotak[k].checked = ada.indexOf(kotak[k].getAttribute('data-i')) >= 0;
      kotak[k].addEventListener('change', function () {
        var a = simpanan(), id = this.getAttribute('data-i'), p = a.indexOf(id);
        if (this.checked && p < 0) a.push(id); if (!this.checked && p >= 0) a.splice(p, 1);
        tulis(kunci, JSON.stringify(a)); hitung();
      });
    }
    var ulang = main.querySelector('.dp-cek-ulang');
    if (ulang) ulang.addEventListener('click', function () {
      tulis(kunci, '[]'); for (var k = 0; k < kotak.length; k++) kotak[k].checked = false; hitung();
    });
  }
  var awal = baca('dp-jalur');
  pilih(awal === 'sekunder' || awal === 'kualitatif' ? awal : 'primer');
})();
