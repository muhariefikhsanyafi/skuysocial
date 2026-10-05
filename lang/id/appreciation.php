<?php

return [
    'title' => 'Apresiasi Postingan',
    'actions' => [
        'appreciate' => 'Apresiasi',
        'choose_type' => 'Pilih jenis apresiasi',
        'submit' => 'Apresiasi dengan :type',
    ],
    'types' => [
        'bermanfaat' => 'Bermanfaat',
        'inspiratif' => 'Inspiratif',
        'solutif' => 'Solutif',
    ],
    'cost_label' => ':cost :unit',
    'cost_copy' => 'Biaya tepat :cost :unit. Poin ini digunakan dan tidak dikirim kepada pemilik postingan.',
    'count' => '{0} Belum ada apresiasi|{1} :count apresiasi|[2,*] :count apresiasi',
    'selected' => 'Anda mengapresiasi postingan ini dengan :type.',
    'feedback' => [
        'success' => 'Apresiasi :type berhasil dicatat. :cost :unit digunakan.',
        'already_appreciated' => 'Anda sudah mengapresiasi postingan ini dengan :type.',
        'insufficient_points' => 'Anda memerlukan minimal :cost :unit untuk mengapresiasi postingan.',
        'self_appreciation_forbidden' => 'Anda tidak dapat mengapresiasi postingan sendiri.',
    ],
    'validation' => [
        'type_required' => 'Pilih salah satu jenis apresiasi yang tersedia.',
        'type_invalid' => 'Pilih Bermanfaat, Inspiratif, atau Solutif.',
    ],
];
