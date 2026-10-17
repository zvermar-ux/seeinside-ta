<?php

return [
    'column_toggle' => [
        'heading' => 'Колони',
    ],
    'columns' => [
        'actions' => [
            'label' => 'Акција|Акции',
        ],
        'text' => [
            'actions' => [
                'collapse_list' => 'Прикажи :count помалку',
                'expand_list' => 'Прикажи уште :count',
            ],
            'more_list_items' => 'и уште :count',
        ],
    ],
    'fields' => [
        'bulk_select_page' => [
            'label' => 'Избери/отштиклирај ги сите ставки за групни акции.',
        ],
        'bulk_select_record' => [
            'label' => 'Избери/отштиклирај ја ставката :key за групни акции.',
        ],
        'bulk_select_group' => [
            'label' => 'Избери/отштиклирај ја групата :title за групни акции.',
        ],
        'search' => [
            'label' => 'Пребарај',
            'placeholder' => 'Пребарај',
            'indicator' => 'Пребарај',
        ],
    ],
    'summary' => [
        'heading' => 'Збир',
        'subheadings' => [
            'all' => 'Сите :label',
            'group' => 'Збир за :group',
            'page' => 'Оваа страница',
        ],
        'summarizers' => [
            'average' => [
                'label' => 'Просек',
            ],
            'count' => [
                'label' => 'Број',
            ],
            'sum' => [
                'label' => 'Збир',
            ],
        ],
    ],
    'actions' => [
        'disable_reordering' => [
            'label' => 'Заврши со подредување',
        ],
        'enable_reordering' => [
            'label' => 'Подреди записи',
        ],
        'filter' => [
            'label' => 'Филтер',
        ],
        'group' => [
            'label' => 'Групирај',
        ],
        'open_bulk_actions' => [
            'label' => 'Групни акции',
        ],
        'toggle_columns' => [
            'label' => 'Прикажи/скриј колони',
        ],
    ],
    'empty' => [
        'heading' => 'Нема :model',
        'description' => 'Креирајте :model за да започнете.',
    ],
    'filters' => [
        'actions' => [
            'apply' => [
                'label' => 'Примени филтри',
            ],
            'remove' => [
                'label' => 'Отстрани филтер',
            ],
            'remove_all' => [
                'label' => 'Отстрани ги сите филтри',
                'tooltip' => 'Отстрани ги сите филтри',
            ],
            'reset' => [
                'label' => 'Ресетирај',
            ],
        ],
        'heading' => 'Филтри',
        'indicator' => 'Активни филтри',
        'multi_select' => [
            'placeholder' => 'Сите',
        ],
        'select' => [
            'placeholder' => 'Сите',
        ],
        'trashed' => [
            'label' => 'Избришани записи',
            'only_trashed' => 'Само избришани',
            'with_trashed' => 'Со избришани',
            'without_trashed' => 'Без избришани',
        ],
    ],
    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'Групирај по',
                'placeholder' => 'Групирај по',
            ],
            'direction' => [
                'label' => 'Насока на групирање',
                'options' => [
                    'asc' => 'Растечки',
                    'desc' => 'Опаѓачки',
                ],
            ],
        ],
    ],
    'reorder_indicator' => 'Повлечете ги записите за да ги подредите.',
    'selection_indicator' => [
        'selected_count' => 'Избран е 1 запис|Избрани се :count записи',
        'actions' => [
            'select_all' => [
                'label' => 'Избери ги сите :count',
            ],
            'deselect_all' => [
                'label' => 'Отштиклирај ги сите',
            ],
        ],
    ],
    'sorting' => [
        'fields' => [
            'column' => [
                'label' => 'Подреди по',
            ],
            'direction' => [
                'label' => 'Насока на подредување',
                'options' => [
                    'asc' => 'Растечки',
                    'desc' => 'Опаѓачки',
                ],
            ],
        ],
    ],
];
