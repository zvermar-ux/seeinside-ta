<?php

return [
    'title' => 'Регистрација',
    'heading' => 'Регистрирај се',
    'actions' => [
        'login' => [
            'before' => 'или',
            'label' => 'најавете се на вашата сметка',
        ],
    ],
    'form' => [
        'email' => [
            'label' => 'Е-пошта',
        ],
        'name' => [
            'label' => 'Име',
        ],
        'password' => [
            'label' => 'Лозинка',
            'validation_attribute' => 'лозинка',
        ],
        'password_confirmation' => [
            'label' => 'Потврди лозинка',
        ],
        'actions' => [
            'register' => [
                'label' => 'Регистрирај се',
            ],
        ],
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Премногу обиди за регистрација',
            'body' => 'Обидете се повторно за :seconds секунди.',
        ],
    ],
];
