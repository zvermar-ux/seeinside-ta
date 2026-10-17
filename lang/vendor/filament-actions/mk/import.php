<?php

return [
    'label' => 'Увези :label',
    'modal' => [
        'heading' => 'Увези :label',
        'form' => [
            'file' => [
                'label' => 'Датотека',
                'placeholder' => 'Прикачи CSV датотека',
                'rules' => [
                    'duplicate_columns' => '{0} Датотеката не смее да содржи повеќе од едно празно заглавие на колона.|{1,*} Датотеката не смее да содржи дупликат заглавија: :columns.',
                ],
            ],
            'columns' => [
                'label' => 'Колони',
                'placeholder' => 'Изберете колона',
            ],
        ],
        'actions' => [
            'download_example' => [
                'label' => 'Преземи пример CSV датотека',
            ],
            'import' => [
                'label' => 'Увези',
            ],
        ],
    ],
    'notifications' => [
        'completed' => [
            'title' => 'Увозот е завршен',
            'actions' => [
                'download_failed_rows_csv' => [
                    'label' => 'Преземи информации за неуспешниот ред|Преземи информации за неуспешните редови',
                ],
            ],
        ],
        'max_rows' => [
            'title' => 'Прикачената CSV датотека е преголема',
            'body' => 'Не може да се увезе повеќе од 1 ред одеднаш.|Не може да се увезат повеќе од :count реда одеднаш.',
        ],
        'started' => [
            'title' => 'Увозот започна',
            'body' => 'Увозот започна и 1 ред ќе се обработи во позадина.|Увозот започна и :count реда ќе се обработат во позадина.',
        ],
    ],
    'example_csv' => [
        'file_name' => ':importer-example',
    ],
    'failure_csv' => [
        'file_name' => 'import-:import_id-:csv_name-failed-rows',
        'error_header' => 'грешка',
        'system_error' => 'Системска грешка, контактирајте ја поддршката.',
        'column_mapping_required_for_new_record' => 'Колоната :attribute не е поврзана со колона од датотеката, а е задолжителна за нови записи.',
    ],
];
