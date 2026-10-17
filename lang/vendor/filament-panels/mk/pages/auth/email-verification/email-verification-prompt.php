<?php

return [
    'title' => 'Потврдете ја е-поштата',
    'heading' => 'Потврдете ја е-поштата',
    'actions' => [
        'resend_notification' => [
            'label' => 'Испрати повторно',
        ],
    ],
    'messages' => [
        'notification_not_received' => 'Не ја добивте пораката?',
        'notification_sent' => 'Испративме порака на :email со упатство за потврда на е-поштата.',
    ],
    'notifications' => [
        'notification_resent' => [
            'title' => 'Пораката е испратена повторно.',
        ],
        'notification_resend_throttled' => [
            'title' => 'Премногу обиди за повторно испраќање',
            'body' => 'Обидете се повторно за :seconds секунди.',
        ],
    ],
];
