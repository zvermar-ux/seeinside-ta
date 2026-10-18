<?php

/*
| AI providers, suggested models and default prices (USD). Prices change: every agency can correct
| them in Settings → AI (the spending limits are calculated from these prices).
| Text: [input, output] per 1 million tokens.  Images: price per image.
*/
return [
    'providers' => [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'ChatGPT (OpenAI)',
        'gemini' => 'Gemini (Google)',
    ],

    'text_models' => [
        'anthropic' => [
            'claude-haiku-5-5' => [1.00, 5.00],
            'claude-sonnet-5-5' => [3.00, 15.00],
            'claude-opus-5-5' => [5.00, 25.00],
        ],
        'openai' => [
            'gpt-5.4-mini' => [0.75, 4.50],
            'gpt-5.4-nano' => [0.20, 1.25],
            'gpt-5' => [1.25, 10.00],
            'gpt-4.1-mini' => [0.40, 1.60],
        ],
        'gemini' => [
            'gemini-3.5-flash-lite' => [0.15, 1.25],
            'gemini-3.6-flash' => [0.50, 3.00],
            'gemini-3.8-flash' => [0.50, 3.00],
            'gemini-4-argon' => [2.00, 10.00],
        ],
    ],

    'image_providers' => ['openai', 'gemini'],

    'image_models' => [
        'openai' => ['gpt-image-1' => 0.07, 'gpt-image-1-mini' => 0.02],
        'gemini' => ['gemini-3.1-flash-image' => 0.04, 'gemini-3.1-flash-lite-image' => 0.02, 'gemini-2.5-flash-image' => 0.04],
    ],

    'defaults' => [
        'assistant' => ['anthropic' => 'claude-sonnet-5-5', 'openai' => 'gpt-5.4-mini', 'gemini' => 'gemini-3.6-flash'],
        'chat' => ['anthropic' => 'claude-haiku-5-5', 'openai' => 'gpt-5.4-nano', 'gemini' => 'gemini-3.5-flash-lite'],
        'image' => ['openai' => 'gpt-image-1', 'gemini' => 'gemini-3.1-flash-image'],
    ],

    'endpoints' => [
        'openai' => env('AI_OPENAI_URL', 'https://api.openai.com/v1'),
        'anthropic' => env('AI_ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        'gemini' => env('AI_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'timeout' => (int) env('AI_TIMEOUT', 90),
];
