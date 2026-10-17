<?php

return [
    'label' => 'Навигација по страници',
    'overview' => '{1} Прикажан 1 резултат|[2,*] Прикажани :first до :last од вкупно :total',
    'fields' => [
        'records_per_page' => [
            'label' => 'По страница',
            'options' => [
                'all' => 'Сите',
            ],
        ],
    ],
    'actions' => [
        'first' => [
            'label' => 'Прва',
        ],
        'go_to_page' => [
            'label' => 'Оди на страница :page',
        ],
        'last' => [
            'label' => 'Последна',
        ],
        'next' => [
            'label' => 'Следно',
        ],
        'previous' => [
            'label' => 'Претходна',
        ],
    ],
];
