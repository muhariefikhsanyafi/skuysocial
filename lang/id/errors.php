<?php

return [
    '404' => [
        'title' => 'Halaman ini tidak tersedia',
        'body' => 'Tautan yang Anda buka mungkin sudah dipindahkan atau tidak lagi tersedia.',
        'action' => 'Kembali ke beranda',
    ],

    '419' => [
        'title' => 'Sesi Anda telah berakhir',
        'body' => 'Muat ulang halaman dan coba lagi. Jika diperlukan, masuk kembali untuk melanjutkan.',
        'action' => 'Kembali ke beranda',
        'secondary_action' => 'Masuk kembali',
    ],

    '429' => [
        'title' => 'Terlalu banyak permintaan',
        'body' => 'Kami menerima terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar sebelum mencoba lagi.',
        'action' => 'Kembali ke beranda',
    ],

    '500' => [
        'title' => 'Terjadi kesalahan sementara',
        'body' => 'Kami tidak dapat menyelesaikan permintaan ini sekarang. Coba lagi atau kembali ke beranda.',
        'action' => 'Coba lagi',
        'secondary_action' => 'Kembali ke beranda',
    ],
];
