<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dynamic Greeting Configuration
    |--------------------------------------------------------------------------
    |
    | Sapaan dinamis berdasarkan waktu (24 jam) dengan variasi random untuk
    | memberikan kesan "hidup" setiap kali halaman di-reload.
    |
    | Format waktu menggunakan H:i (24-hour format)
    | Jam sholat adalah perkiraan rata-rata untuk Bandung (statis, bukan real-time)
    |
    */

    'time_ranges' => [
        // Dini Hari (00:00 - 04:30)
        [
            'start' => '00:00',
            'end' => '04:30',
            'greeting' => 'Dini Hari',
            'messages' => [
                'Masih di sini?',
                'Belum tidur?',
                'Lembur ya?',
                'Masih ngoding?',
                'Bug nya udah selesai?',
                'Jangan lupa istirahat ya?',
                'Tidur dong, udah larut!',
                'Deployment malam ini?',
            ],
        ],

        // Subuh (04:30 - 05:30)
        [
            'start' => '04:30',
            'end' => '05:30',
            'greeting' => 'Pagi',
            'messages' => [
                'Waktunya sholat Subuh?',
            ],
        ],

        // Pagi (05:30 - 10:00)
        [
            'start' => '05:30',
            'end' => '10:00',
            'greeting' => 'Pagi',
            'messages' => [
                'Waktunya mulai ngoding?',
                'Waktunya ngopi dulu?',
                'Waktunya semangat pagi?',
            ],
        ],

        // Menjelang Siang (10:00 - 11:50)
        [
            'start' => '10:00',
            'end' => '11:50',
            'greeting' => 'Siang',
            'messages' => [
                'Waktunya lanjut kerja?',
                'Waktunya lanjut ngoding?',
                'Waktunya lanjut tersenyum?',
            ],
        ],

        // Dzuhur (11:50 - 12:30)
        [
            'start' => '11:50',
            'end' => '12:30',
            'greeting' => 'Siang',
            'messages' => [
                'Waktunya sholat Dzuhur?',
            ],
        ],

        // Makan Siang (12:30 - 13:00)
        [
            'start' => '12:30',
            'end' => '13:00',
            'greeting' => 'Siang',
            'messages' => [
                'Waktunya makan?',
                'Waktunya scroll sejenak?',
                'Waktunya istirahat?',
            ],
        ],

        // Siang (13:00 - 15:00)
        [
            'start' => '13:00',
            'end' => '15:00',
            'greeting' => 'Siang',
            'messages' => [
                'Waktunya lanjut kerja?',
                'Waktunya lanjut ngoding?',
                'Waktunya lanjut tersenyum?',
            ],
        ],

        // Ashar (15:00 - 15:30)
        [
            'start' => '15:00',
            'end' => '15:30',
            'greeting' => 'Sore',
            'messages' => [
                'Waktunya sholat Ashar?',
            ],
        ],

        // Sore (15:30 - 17:50)
        [
            'start' => '15:30',
            'end' => '17:50',
            'greeting' => 'Sore',
            'messages' => [
                'Waktunya lanjut kerja?',
                'Waktunya lanjut ngoding?',
                'Waktunya lanjut tersenyum?',
            ],
        ],

        // Maghrib (17:50 - 18:10)
        [
            'start' => '17:50',
            'end' => '18:10',
            'greeting' => 'Sore',
            'messages' => [
                'Waktunya sholat Maghrib?',
            ],
        ],

        // Santai Sore (18:10 - 19:00)
        [
            'start' => '18:10',
            'end' => '19:00',
            'greeting' => 'Sore',
            'messages' => [
                'Waktunya santai sejenak?',
                'Waktunya me time?',
            ],
        ],

        // Isya (19:00 - 19:20)
        [
            'start' => '19:00',
            'end' => '19:20',
            'greeting' => 'Malam',
            'messages' => [
                'Waktunya sholat Isya?',
            ],
        ],

        // Malam (19:20 - 24:00)
        [
            'start' => '19:20',
            'end' => '23:59',
            'greeting' => 'Malam',
            'messages' => [
                'Masih semangat?',
                'Waktunya rebahan?',
                'Waktunya nonton dulu?',
                'Masih ngoding?',
                'Masih lembur?',
                'Sudah makan malam?',
                'Jangan begadang terus ya?',
                'Deadline besok?',
            ],
        ],
    ],
];
