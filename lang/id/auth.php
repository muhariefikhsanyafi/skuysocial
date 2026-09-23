<?php

return [
    'failed' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
    'password' => 'Kata sandi yang diberikan tidak benar.',
    'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',

    'common' => [
        'page_title' => 'Autentikasi',
        'name' => 'NAMA',
        'username' => 'NAMA PENGGUNA',
        'email_address' => 'ALAMAT EMAIL',
        'password' => 'KATA SANDI',
        'password_confirmation' => 'KONFIRMASI KATA SANDI',
        'show_password' => 'Tampilkan kata sandi',
        'hide_password' => 'Sembunyikan kata sandi',
        'log_out' => 'KELUAR',
    ],

    'login' => [
        'title' => 'Masuk ke SkuySocial',
        'intro' => 'Lanjutkan percakapan yang kamu ikuti.',
        'identifier_label' => 'Email atau nama pengguna',
        'password_label' => 'Kata sandi',
        'remember_30_days' => 'Ingat Saya selama 30 hari',
        'forgot_password' => 'Lupa kata sandi?',
        'no_account' => 'Belum memiliki akun?',
        'submit' => 'Masuk',
        'register' => 'Buat akun',
    ],

    'register' => [
        'title' => 'Buat akun SkuySocial',
        'intro' => 'Mulai percakapan dan temukan topik yang ingin kamu ikuti.',
        'name_label' => 'Nama',
        'username_label' => 'Nama pengguna',
        'username_hint' => '(tanpa spasi)',
        'username_format_hint' => 'Nama pengguna tidak boleh menggunakan spasi.',
        'email_label' => 'Alamat email',
        'password_label' => 'Kata sandi',
        'password_confirmation_label' => 'Konfirmasi kata sandi',
        'has_account' => 'Sudah punya akun?',
        'sign_in' => 'Masuk',
        'submit' => 'Buat akun',
    ],

    'forgot_password' => [
        'title' => 'Lupa kata sandi?',
        'intro' => 'Masukkan alamat email akunmu. Kami akan mengirim tautan untuk mengatur ulang kata sandi.',
        'email_label' => 'Alamat email',
        'back_to_login' => 'Kembali ke masuk',
        'submit' => 'Kirim tautan reset',
    ],

    'reset_password' => [
        'title' => 'Atur ulang kata sandi',
        'intro' => 'Buat kata sandi baru untuk akunmu.',
        'email_label' => 'Alamat email',
        'new_password' => 'Kata sandi baru',
        'password_confirmation' => 'Konfirmasi kata sandi baru',
        'back_to_login' => 'Kembali ke masuk',
        'submit' => 'Atur ulang kata sandi',
    ],

    'confirm_password' => [
        'title' => 'Konfirmasi Kata Sandi',
        'intro' => 'Ini adalah area aman. Konfirmasikan kata sandi Anda sebelum melanjutkan.',
        'submit' => 'KONFIRMASI',
    ],

    'verify_email' => [
        'title' => 'Verifikasi alamat email',
        'intro' => 'Untuk melanjutkan ke SkuySocial, minta kode 6 digit untuk :email di bawah ini.',
        'sent_intro' => 'Kode verifikasi 6 digit telah dikirim ke :email. Masukkan kode di bawah; masa berlakunya 10 menit.',
        'code_label' => 'Kode 6 digit',
        'code_hint' => 'Masukkan enam digit dari email Anda. Kode hanya dapat digunakan sekali.',
        'verify' => 'Verifikasi email',
        'resend' => 'Kirim kode baru',
        'continue' => 'Lanjutkan ke SkuySocial',
        'resend_cooldown' => 'Anda dapat meminta kode baru dalam :seconds detik.',
        'sent' => 'Kode baru 6 digit telah dikirim ke alamat email Anda.',
        'mail_failed' => 'Kode verifikasi belum dapat dikirim. Akun Anda tetap belum terverifikasi. Silakan coba lagi.',
        'cooldown' => 'Tunggu hingga jeda pengiriman ulang selesai sebelum meminta kode baru.',
        'invalid' => 'Kode tersebut tidak valid. Periksa enam digit dan coba lagi.',
        'expired' => 'Kode tersebut sudah kedaluwarsa. Minta kode baru untuk melanjutkan.',
        'attempt_limit' => 'Terlalu banyak percobaan yang salah. Minta kode baru untuk mencoba lagi.',
        'already_verified' => 'Alamat email Anda sudah terverifikasi.',
        'verified' => 'Alamat email Anda telah terverifikasi.',
        'code_expired' => 'Kode saat ini sudah kedaluwarsa. Minta kode baru untuk melanjutkan.',
        'attempt_limit_reached' => 'Batas percobaan kode ini telah tercapai. Minta kode baru setelah jeda pengiriman.',
        'mail_subject' => 'Kode verifikasi SkuySocial Anda',
        'mail_title' => 'Verifikasi alamat email Anda',
        'mail_intro' => 'Gunakan kode 6 digit ini untuk memverifikasi akun SkuySocial Anda:',
        'mail_expiration' => 'Kode ini kedaluwarsa dalam 10 menit dan hanya dapat digunakan sekali.',
        'mail_ignore' => 'Jika Anda tidak membuat akun ini, abaikan pesan ini.',
        'mail_footer' => 'Verifikasi akun SkuySocial',
    ],

    'security' => [
        'verification_failed' => 'Verifikasi keamanan gagal. Silakan coba lagi.',
    ],
];
