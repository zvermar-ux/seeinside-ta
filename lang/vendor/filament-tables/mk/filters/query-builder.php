<?php

return [
    'label' => 'Напредно пребарување',
    'form' => [
        'operator' => [
            'label' => 'Оператор',
        ],
        'or_groups' => [
            'label' => 'Групи',
            'block' => [
                'label' => 'Дисјункција (ИЛИ)',
                'or' => 'ИЛИ',
            ],
        ],
        'rules' => [
            'label' => 'Правила',
            'item' => [
                'and' => 'И',
            ],
        ],
    ],
    'no_rules' => '(Нема правила)',
    'item_separators' => [
        'and' => 'И',
        'or' => 'ИЛИ',
    ],
    'operators' => [
        'is_filled' => [
            'label' => [
                'direct' => 'Е пополнето',
                'inverse' => 'Е празно',
            ],
            'summary' => [
                'direct' => ':attribute е пополнето',
                'inverse' => ':attribute е празно',
            ],
        ],
        'boolean' => [
            'is_true' => [
                'label' => [
                    'direct' => 'Е точно',
                    'inverse' => 'Е неточно',
                ],
                'summary' => [
                    'direct' => ':attribute е точно',
                    'inverse' => ':attribute е неточно',
                ],
            ],
        ],
        'date' => [
            'is_after' => [
                'label' => [
                    'direct' => 'Е после',
                    'inverse' => 'Не е после',
                ],
                'summary' => [
                    'direct' => ':attribute е после :date',
                    'inverse' => ':attribute не е после :date',
                ],
            ],
            'is_before' => [
                'label' => [
                    'direct' => 'Е пред',
                    'inverse' => 'Не е пред',
                ],
                'summary' => [
                    'direct' => ':attribute е пред :date',
                    'inverse' => ':attribute не е пред :date',
                ],
            ],
            'is_date' => [
                'label' => [
                    'direct' => 'Е датум',
                    'inverse' => 'Не е датум',
                ],
                'summary' => [
                    'direct' => ':attribute е :date',
                    'inverse' => ':attribute не е :date',
                ],
            ],
            'is_month' => [
                'label' => [
                    'direct' => 'Е месец',
                    'inverse' => 'Не е месец',
                ],
                'summary' => [
                    'direct' => ':attribute е :month',
                    'inverse' => ':attribute не е :month',
                ],
            ],
            'is_year' => [
                'label' => [
                    'direct' => 'Е година',
                    'inverse' => 'Не е година',
                ],
                'summary' => [
                    'direct' => ':attribute е :year',
                    'inverse' => ':attribute не е :year',
                ],
            ],
            'form' => [
                'date' => [
                    'label' => 'Датум',
                ],
                'month' => [
                    'label' => 'Месец',
                ],
                'year' => [
                    'label' => 'Година',
                ],
            ],
        ],
        'number' => [
            'equals' => [
                'label' => [
                    'direct' => 'Еднакво',
                    'inverse' => 'Не е еднакво',
                ],
                'summary' => [
                    'direct' => ':attribute е еднакво на :number',
                    'inverse' => ':attribute не е еднакво на :number',
                ],
            ],
            'is_max' => [
                'label' => [
                    'direct' => 'Е најмногу',
                    'inverse' => 'Е поголемо од',
                ],
                'summary' => [
                    'direct' => ':attribute е најмногу :number',
                    'inverse' => ':attribute е поголемо од :number',
                ],
            ],
            'is_min' => [
                'label' => [
                    'direct' => 'Е најмалку',
                    'inverse' => 'Е помало од',
                ],
                'summary' => [
                    'direct' => ':attribute е најмалку :number',
                    'inverse' => ':attribute е помало од :number',
                ],
            ],
            'aggregates' => [
                'average' => [
                    'label' => 'Просек',
                    'summary' => 'Просек на :attribute',
                ],
                'max' => [
                    'label' => 'Макс.',
                    'summary' => 'Макс. :attribute',
                ],
                'min' => [
                    'label' => 'Мин.',
                    'summary' => 'Мин. :attribute',
                ],
                'sum' => [
                    'label' => 'Збир',
                    'summary' => 'Збир на :attribute',
                ],
            ],
            'form' => [
                'aggregate' => [
                    'label' => 'Агрегат',
                ],
                'number' => [
                    'label' => 'Број',
                ],
            ],
        ],
        'relationship' => [
            'equals' => [
                'label' => [
                    'direct' => 'Има',
                    'inverse' => 'Нема',
                ],
                'summary' => [
                    'direct' => 'Има :count :relationship',
                    'inverse' => 'Нема :count :relationship',
                ],
            ],
            'has_max' => [
                'label' => [
                    'direct' => 'Има најмногу',
                    'inverse' => 'Има повеќе од',
                ],
                'summary' => [
                    'direct' => 'Има најмногу :count :relationship',
                    'inverse' => 'Има повеќе од :count :relationship',
                ],
            ],
            'has_min' => [
                'label' => [
                    'direct' => 'Има најмалку',
                    'inverse' => 'Има помалку од',
                ],
                'summary' => [
                    'direct' => 'Има најмалку :count :relationship',
                    'inverse' => 'Има помалку од :count :relationship',
                ],
            ],
            'is_empty' => [
                'label' => [
                    'direct' => 'Е празно',
                    'inverse' => 'Не е празно',
                ],
                'summary' => [
                    'direct' => ':relationship е празно',
                    'inverse' => ':relationship не е празно',
                ],
            ],
            'is_related_to' => [
                'label' => [
                    'single' => [
                        'direct' => 'Е',
                        'inverse' => 'Не е',
                    ],
                    'multiple' => [
                        'direct' => 'Содржи',
                        'inverse' => 'Не содржи',
                    ],
                ],
                'summary' => [
                    'single' => [
                        'direct' => ':relationship е :values',
                        'inverse' => ':relationship не е :values',
                    ],
                    'multiple' => [
                        'direct' => ':relationship содржи :values',
                        'inverse' => ':relationship не содржи :values',
                    ],
                    'values_glue' => [
                        0 => ', ',
                        'final' => ' или ',
                    ],
                ],
                'form' => [
                    'value' => [
                        'label' => 'Вредност',
                    ],
                    'values' => [
                        'label' => 'Вредности',
                    ],
                ],
            ],
            'form' => [
                'count' => [
                    'label' => 'Број',
                ],
            ],
        ],
        'select' => [
            'is' => [
                'label' => [
                    'direct' => 'Е',
                    'inverse' => 'Не е',
                ],
                'summary' => [
                    'direct' => ':attribute е :values',
                    'inverse' => ':attribute не е :values',
                    'values_glue' => [
                        0 => ', ',
                        'final' => ' или ',
                    ],
                ],
                'form' => [
                    'value' => [
                        'label' => 'Вредност',
                    ],
                    'values' => [
                        'label' => 'Вредности',
                    ],
                ],
            ],
        ],
        'text' => [
            'contains' => [
                'label' => [
                    'direct' => 'Содржи',
                    'inverse' => 'Не содржи',
                ],
                'summary' => [
                    'direct' => ':attribute содржи :text',
                    'inverse' => ':attribute не содржи :text',
                ],
            ],
            'ends_with' => [
                'label' => [
                    'direct' => 'Завршува со',
                    'inverse' => 'Не завршува со',
                ],
                'summary' => [
                    'direct' => ':attribute завршува со :text',
                    'inverse' => ':attribute не завршува со :text',
                ],
            ],
            'equals' => [
                'label' => [
                    'direct' => 'Еднакво',
                    'inverse' => 'Не е еднакво',
                ],
                'summary' => [
                    'direct' => ':attribute е еднакво на :text',
                    'inverse' => ':attribute не е еднакво на :text',
                ],
            ],
            'starts_with' => [
                'label' => [
                    'direct' => 'Почнува со',
                    'inverse' => 'Не почнува со',
                ],
                'summary' => [
                    'direct' => ':attribute почнува со :text',
                    'inverse' => ':attribute не почнува со :text',
                ],
            ],
            'form' => [
                'text' => [
                    'label' => 'Текст',
                ],
            ],
        ],
    ],
    'actions' => [
        'add_rule' => [
            'label' => 'Додај правило',
        ],
        'add_rule_group' => [
            'label' => 'Додај група правила',
        ],
    ],
];
