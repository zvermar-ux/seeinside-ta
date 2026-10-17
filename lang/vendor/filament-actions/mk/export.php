<?php

return [
    'label' => 'Извези :label',
    'modal' => [
        'heading' => 'Извези :label',
        'form' => [
            'columns' => [
                'label' => 'Колони',
                'form' => [
                    'is_enabled' => [
                        'label' => ':column вклучено',
                    ],
                    'label' => [
                        'label' => ':column ознака',
                    ],
                ],
            ],
        ],
        'actions' => [
            'export' => [
                'label' => 'Извези',
            ],
        ],
    ],
    'notifications' => [
        'completed' => [
            'title' => 'Извозот е завршен',
            'actions' => [
                'download_csv' => [
                    'label' => 'Преземи .csv',
                ],
                'download_xlsx' => [
                    'label' => 'Преземи .xlsx',
                ],
            ],
        ],
        'max_rows' => [
            'title' => 'Извозот е преголем',
            'body' => 'Не може да се извезе повеќе од 1 ред одеднаш.|Не може да се извезат повеќе од :count реда одеднаш.',
        ],
        'started' => [
            'title' => 'Извозот започна',
            'body' => 'Извозот започна и 1 ред ќе се обработи во позадина. Ќе добиете известување со линк за преземање кога ќе заврши.|Извозот започна и :count реда ќе се обработат во позадина. Ќе добиете известување со линк за преземање кога ќе заврши.',
        ],
    ],
    'file_name' => 'export-:export_id-:model',
];
