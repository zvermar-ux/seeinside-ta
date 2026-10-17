<?php

return [
    'title' => 'Најава',
    'heading' => 'Најави се',
    'actions' => [
        'register' => [
            'before' => 'или',
            'label' => 'регистрирајте сметка',
        ],
        'request_password_reset' => [
            'label' => 'Ја заборавивте лозинката?',
        ],
    ],
    'form' => [
        'email' => [
            'label' => 'Е-пошта',
        ],
        'password' => [
            'label' => 'Лозинка',
        ],
        'remember' => [
            'label' => 'Запамети ме',
        ],
        'actions' => [
            'authenticate' => [
                'label' => 'Најави се',
            ],
        ],
    ],
    'messages' => [
        'failed' => 'Погрешна е-пошта или лозинка.',
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Премногу обиди за најава',
            'body' => 'Обидете се повторно за :seconds секунди.',
        ],
    ],
];
