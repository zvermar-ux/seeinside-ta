<?php

return [
    'title' => 'Ресетирајте ја лозинката',
    'heading' => 'Ресетирајте ја лозинката',
    'form' => [
        'email' => [
            'label' => 'Е-пошта',
        ],
        'password' => [
            'label' => 'Лозинка',
            'validation_attribute' => 'лозинка',
        ],
        'password_confirmation' => [
            'label' => 'Потврди лозинка',
        ],
        'actions' => [
            'reset' => [
                'label' => 'Ресетирај лозинка',
            ],
        ],
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Премногу обиди за ресетирање',
            'body' => 'Обидете се повторно за :seconds секунди.',
        ],
    ],
];
