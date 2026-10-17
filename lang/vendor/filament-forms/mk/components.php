<?php

return [
    'builder' => [
        'actions' => [
            'clone' => [
                'label' => 'Клонирај',
            ],
            'add' => [
                'label' => 'Додај во :label',
                'modal' => [
                    'heading' => 'Додај во :label',
                    'actions' => [
                        'add' => [
                            'label' => 'Додај',
                        ],
                    ],
                ],
            ],
            'add_between' => [
                'label' => 'Вметни меѓу блоковите',
                'modal' => [
                    'heading' => 'Додај во :label',
                    'actions' => [
                        'add' => [
                            'label' => 'Додај',
                        ],
                    ],
                ],
            ],
            'delete' => [
                'label' => 'Избриши',
            ],
            'edit' => [
                'label' => 'Уреди',
                'modal' => [
                    'heading' => 'Уреди блок',
                    'actions' => [
                        'save' => [
                            'label' => 'Зачувај ги промените',
                        ],
                    ],
                ],
            ],
            'reorder' => [
                'label' => 'Премести',
            ],
            'move_down' => [
                'label' => 'Помести надолу',
            ],
            'move_up' => [
                'label' => 'Помести нагоре',
            ],
            'collapse' => [
                'label' => 'Собери',
            ],
            'expand' => [
                'label' => 'Прошири',
            ],
            'collapse_all' => [
                'label' => 'Собери ги сите',
            ],
            'expand_all' => [
                'label' => 'Прошири ги сите',
            ],
        ],
    ],
    'checkbox_list' => [
        'actions' => [
            'deselect_all' => [
                'label' => 'Отштиклирај ги сите',
            ],
            'select_all' => [
                'label' => 'Избери ги сите',
            ],
        ],
    ],
    'file_upload' => [
        'editor' => [
            'actions' => [
                'cancel' => [
                    'label' => 'Откажи',
                ],
                'drag_crop' => [
                    'label' => 'Режим на влечење „сечење“',
                ],
                'drag_move' => [
                    'label' => 'Режим на влечење „поместување“',
                ],
                'flip_horizontal' => [
                    'label' => 'Преврти ја сликата хоризонтално',
                ],
                'flip_vertical' => [
                    'label' => 'Преврти ја сликата вертикално',
                ],
                'move_down' => [
                    'label' => 'Помести ја сликата надолу',
                ],
                'move_left' => [
                    'label' => 'Помести ја сликата лево',
                ],
                'move_right' => [
                    'label' => 'Помести ја сликата десно',
                ],
                'move_up' => [
                    'label' => 'Помести ја сликата нагоре',
                ],
                'reset' => [
                    'label' => 'Ресетирај',
                ],
                'rotate_left' => [
                    'label' => 'Ротирај ја сликата налево',
                ],
                'rotate_right' => [
                    'label' => 'Ротирај ја сликата надесно',
                ],
                'set_aspect_ratio' => [
                    'label' => 'Постави сооднос :ratio',
                ],
                'save' => [
                    'label' => 'Зачувај',
                ],
                'zoom_100' => [
                    'label' => 'Зумирај на 100%',
                ],
                'zoom_in' => [
                    'label' => 'Зумирај',
                ],
                'zoom_out' => [
                    'label' => 'Одзумирај',
                ],
            ],
            'fields' => [
                'height' => [
                    'label' => 'Висина',
                    'unit' => 'px',
                ],
                'rotation' => [
                    'label' => 'Ротација',
                    'unit' => 'степени',
                ],
                'width' => [
                    'label' => 'Ширина',
                    'unit' => 'px',
                ],
                'x_position' => [
                    'label' => 'X',
                    'unit' => 'px',
                ],
                'y_position' => [
                    'label' => 'Y',
                    'unit' => 'px',
                ],
            ],
            'aspect_ratios' => [
                'label' => 'Соодноси',
                'no_fixed' => [
                    'label' => 'Слободно',
                ],
            ],
            'svg' => [
                'messages' => [
                    'confirmation' => 'Уредувањето SVG датотеки не се препорачува бидејќи може да се изгуби квалитет при скалирање.\\n Дали сте сигурни дека сакате да продолжите?',
                    'disabled' => 'Уредувањето SVG датотеки е оневозможено бидејќи може да се изгуби квалитет при скалирање.',
                ],
            ],
        ],
    ],
    'key_value' => [
        'actions' => [
            'add' => [
                'label' => 'Додај ред',
            ],
            'delete' => [
                'label' => 'Избриши ред',
            ],
            'reorder' => [
                'label' => 'Подреди ред',
            ],
        ],
        'fields' => [
            'key' => [
                'label' => 'Клуч',
            ],
            'value' => [
                'label' => 'Вредност',
            ],
        ],
    ],
    'markdown_editor' => [
        'toolbar_buttons' => [
            'attach_files' => 'Прикачи датотеки',
            'blockquote' => 'Цитат',
            'bold' => 'Задебелено',
            'bullet_list' => 'Листа со точки',
            'code_block' => 'Блок код',
            'heading' => 'Наслов',
            'italic' => 'Закосено',
            'link' => 'Линк',
            'ordered_list' => 'Нумерирана листа',
            'redo' => 'Повтори',
            'strike' => 'Прецртано',
            'table' => 'Табела',
            'undo' => 'Врати назад',
        ],
    ],
    'radio' => [
        'boolean' => [
            'true' => 'Да',
            'false' => 'Не',
        ],
    ],
    'repeater' => [
        'actions' => [
            'add' => [
                'label' => 'Додај во :label',
            ],
            'add_between' => [
                'label' => 'Вметни помеѓу',
            ],
            'delete' => [
                'label' => 'Избриши',
            ],
            'clone' => [
                'label' => 'Клонирај',
            ],
            'reorder' => [
                'label' => 'Премести',
            ],
            'move_down' => [
                'label' => 'Помести надолу',
            ],
            'move_up' => [
                'label' => 'Помести нагоре',
            ],
            'collapse' => [
                'label' => 'Собери',
            ],
            'expand' => [
                'label' => 'Прошири',
            ],
            'collapse_all' => [
                'label' => 'Собери ги сите',
            ],
            'expand_all' => [
                'label' => 'Прошири ги сите',
            ],
        ],
    ],
    'rich_editor' => [
        'dialogs' => [
            'link' => [
                'actions' => [
                    'link' => 'Линк',
                    'unlink' => 'Отстрани линк',
                ],
                'label' => 'URL',
                'placeholder' => 'Внесете URL',
            ],
        ],
        'toolbar_buttons' => [
            'attach_files' => 'Прикачи датотеки',
            'blockquote' => 'Цитат',
            'bold' => 'Задебелено',
            'bullet_list' => 'Листа со точки',
            'code_block' => 'Блок код',
            'h1' => 'Наслов',
            'h2' => 'Наслов',
            'h3' => 'Поднаслов',
            'italic' => 'Закосено',
            'link' => 'Линк',
            'ordered_list' => 'Нумерирана листа',
            'redo' => 'Повтори',
            'strike' => 'Прецртано',
            'underline' => 'Подвлечено',
            'undo' => 'Врати назад',
        ],
    ],
    'select' => [
        'actions' => [
            'create_option' => [
                'label' => 'Креирај',
                'modal' => [
                    'heading' => 'Креирај',
                    'actions' => [
                        'create' => [
                            'label' => 'Креирај',
                        ],
                        'create_another' => [
                            'label' => 'Креирај и додај уште',
                        ],
                    ],
                ],
            ],
            'edit_option' => [
                'label' => 'Уреди',
                'modal' => [
                    'heading' => 'Уреди',
                    'actions' => [
                        'save' => [
                            'label' => 'Зачувај',
                        ],
                    ],
                ],
            ],
        ],
        'boolean' => [
            'true' => 'Да',
            'false' => 'Не',
        ],
        'loading_message' => 'Се вчитува...',
        'max_items_message' => 'Може да се изберат само :count.',
        'no_search_results_message' => 'Нема опции што одговараат на пребарувањето.',
        'placeholder' => 'Изберете опција',
        'searching_message' => 'Се пребарува...',
        'search_prompt' => 'Почнете да пишувате за пребарување...',
    ],
    'tags_input' => [
        'placeholder' => 'Нова ознака',
    ],
    'text_input' => [
        'actions' => [
            'hide_password' => [
                'label' => 'Скриј лозинка',
            ],
            'show_password' => [
                'label' => 'Прикажи лозинка',
            ],
        ],
    ],
    'toggle_buttons' => [
        'boolean' => [
            'true' => 'Да',
            'false' => 'Не',
        ],
    ],
    'wizard' => [
        'actions' => [
            'previous_step' => [
                'label' => 'Назад',
            ],
            'next_step' => [
                'label' => 'Следно',
            ],
        ],
    ],
];
