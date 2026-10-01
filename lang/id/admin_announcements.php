<?php

return [
    'navigation' => [
        'announcements' => 'Pengumuman',
    ],
    'labels' => [
        'announcement' => 'Pengumuman',
        'announcements' => 'Pengumuman',
    ],
    'fields' => [
        'title_id' => 'Judul (Bahasa Indonesia)',
        'title_en' => 'Judul (Bahasa Inggris)',
        'body_id' => 'Isi (Bahasa Indonesia)',
        'body_en' => 'Isi (Bahasa Inggris)',
        'audience' => 'Audiens',
        'targets' => 'Pengguna tertentu',
        'recipient_count' => 'Penerima yang memenuhi syarat',
        'status' => 'Status',
        'push_status' => 'Pengiriman push',
    ],
    'audience' => [
        'all' => 'Semua pengguna yang memenuhi syarat',
        'targeted' => 'Pengguna tertentu',
    ],
    'status' => ['draft' => 'Draf', 'sent' => 'Terkirim'],
    'push' => [
        'queued' => 'Dalam antrean / diproses',
        'sent' => 'Terkirim',
        'no_subscribers' => 'Tidak ada pelanggan',
        'failed' => 'Gagal',
        'unknown' => 'Tidak diketahui / ambigu',
    ],
    'actions' => [
        'create' => 'Buat pengumuman',
        'send' => 'Kirim pengumuman',
        'delete' => 'Hapus draf',
        'save' => 'Simpan draf',
    ],
    'confirm' => [
        'heading' => 'Kirim pengumuman ini?',
        'description' => 'Pengumuman akan diterbitkan kepada :count pengguna yang memenuhi syarat dan tidak dapat diedit atau ditarik kembali.',
        'body' => 'Pastikan audiens dan jumlah penerima. Pengiriman push diantrekan secara terpisah dan dapat gagal untuk sebagian penerima.',
    ],
    'notifications' => [
        'sent' => 'Pengumuman diterbitkan; pengiriman push telah masuk antrean.',
        'deleted' => 'Draf dihapus.',
    ],
    'validation' => [
        'copy' => 'Isi judul dan isi dalam Bahasa Indonesia serta Inggris sesuai batas panjang.',
        'audience' => 'Pilih audiens yang didukung.',
        'targets' => 'Semua target yang dipilih harus valid dan memenuhi syarat.',
        'no_recipients' => 'Pengumuman ini tidak memiliki penerima yang memenuhi syarat.',
    ],
];
