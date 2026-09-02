<?php
/* Halaman daftar materi berdiri sendiri sudah dipensiunkan. Materi kini
   tampil menyatu di halaman tiap mata kuliah (tab Semester ini/lalu), jadi
   permintaan lama ke sini diarahkan ke halaman Pengajaran. */
header('Location: /mengajar.html', true, 301);
exit;
