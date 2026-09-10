<?php

return [
    'navigation' => [
        'users' => 'Pengguna',
    ],
    'labels' => [
        'user' => 'Pengguna',
        'users' => 'Pengguna',
    ],
    'columns' => [
        'name' => 'Nama',
        'username' => 'Nama pengguna',
        'email' => 'Alamat email',
        'role' => 'Peran',
        'email_verification' => 'Verifikasi email',
        'public_verification' => 'Verifikasi publik',
        'locale' => 'Bahasa',
        'created_at' => 'Bergabung',
        'updated_at' => 'Diperbarui',
        'bio' => 'Bio',
        'id' => 'ID akun',
    ],
    'filters' => [
        'role' => 'Peran',
        'email_verification' => 'Verifikasi email',
        'public_verification' => 'Verifikasi publik',
        'locale' => 'Bahasa',
    ],
    'values' => [
        'administrator' => 'Administrator',
        'user' => 'Pengguna',
        'unknown_role' => 'Peran tidak dikenal',
        'verified' => 'Terverifikasi',
        'unverified' => 'Belum terverifikasi',
        'not_set' => 'Belum ditentukan',
    ],
    'locales' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'Bahasa Inggris',
    ],
    'actions' => [
        'view' => 'Lihat',
    ],
    'sections' => [
        'identity' => 'Identitas',
        'access' => 'Akses',
        'profile' => 'Profil',
        'metadata' => 'Informasi akun',
    ],
    'table' => [
        'empty_heading' => 'Belum ada pengguna',
        'empty_description' => 'Tidak ada akun yang sesuai dengan pencarian atau filter saat ini.',
    ],
];
