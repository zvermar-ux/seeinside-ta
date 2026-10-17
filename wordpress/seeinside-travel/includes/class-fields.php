<?php

defined('ABSPATH') || exit;

/**
 * ACF field groups (registered in code, so they are identical on every site and need no import).
 * Without ACF the same values are still saved as post meta and are available to any builder.
 */
class STA_Fields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'register_acf']);
        add_action('init', [self::class, 'register_meta'], 20);
    }

    public static function acf_pro(): bool
    {
        return function_exists('acf_get_field_type') && (bool) acf_get_field_type('repeater');
    }

    public static function key(string $postType, string $path): string
    {
        return 'field_'.$postType.'_'.str_replace('.', '_', $path);
    }

    protected static function to_acf(array $f, string $postType, string $parent = ''): array
    {
        $path = $parent ? $parent.'.'.$f['name'] : $f['name'];
        $acf = [
            'key' => self::key($postType, $path),
            'name' => $f['name'],
            'label' => $f['label'],
            'type' => $f['type'],
        ];

        switch ($f['type']) {
            case 'tab':
                $acf['placement'] = 'top';
                break;
            case 'date':
                $acf['type'] = 'date_picker';
                $acf['display_format'] = 'd.m.Y';
                $acf['return_format'] = 'Y-m-d';
                break;
            case 'datetime':
                $acf['type'] = 'date_time_picker';
                $acf['display_format'] = 'd.m.Y H:i';
                $acf['return_format'] = 'Y-m-d H:i:s';
                break;
            case 'true_false':
                $acf['ui'] = 1;
                break;
            case 'image':
                $acf['return_format'] = 'array';
                $acf['preview_size'] = 'thumbnail';
                break;
            case 'gallery':
                $acf['return_format'] = 'array';
                $acf['preview_size'] = 'thumbnail';
                break;
            case 'wysiwyg':
                $acf['tabs'] = 'all';
                $acf['toolbar'] = 'basic';
                $acf['media_upload'] = 0;
                break;
            case 'textarea':
                $acf['rows'] = 3;
                break;
            case 'repeater':
                $acf['layout'] = count($f['sub_fields']) > 6 ? 'block' : 'table';
                $acf['button_label'] = __('Add row', 'seeinside-travel');
                $acf['sub_fields'] = array_map(fn ($s) => self::to_acf($s, $postType, $path), $f['sub_fields']);
                break;
        }
        if (! empty($f['readonly'])) {
            $acf['readonly'] = 1;
        }

        return $acf;
    }

    public static function register_acf(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }
        $pro = self::acf_pro();

        foreach (STA_Schema::post_types() as $type => $pt) {
            $fields = STA_Schema::fields($type);
            if (! $pro) {
                // ACF free has no repeater / gallery: those stay available as post meta and in the templates
                $fields = array_values(array_filter($fields, fn ($f) => ! in_array($f['type'], ['repeater', 'gallery'], true)));
            }

            acf_add_local_field_group([
                'key' => 'group_'.$type,
                'title' => sprintf(__('%s – data from SeeInside Travel', 'seeinside-travel'), $pt['singular']),
                'fields' => array_map(fn ($f) => self::to_acf($f, $type), $fields),
                'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => $type]]],
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'active' => true,
                'show_in_rest' => 1,
                'description' => __('Filled automatically by the SeeInside Travel sync.', 'seeinside-travel'),
            ]);
        }
    }

    /** Simple fields as registered meta (REST / builders that read custom fields) */
    public static function register_meta(): void
    {
        foreach (array_keys(STA_Schema::post_types()) as $type) {
            foreach (STA_Schema::fields($type) as $f) {
                if (in_array($f['type'], ['tab', 'repeater', 'gallery'], true)) {
                    continue;
                }
                register_post_meta($type, $f['name'], [
                    'single' => true,
                    'show_in_rest' => true,
                    'type' => match ($f['type']) {
                        'number' => 'number',
                        'true_false' => 'boolean',
                        'image' => 'integer',
                        default => 'string',
                    },
                    'auth_callback' => fn () => current_user_can('edit_posts'),
                ]);
            }
        }
    }

    /** ACF stores dates as Ymd; booleans as 1/0 (also inside repeaters) */
    protected static function for_acf(array $field, $value)
    {
        if ($value === null) {
            return $field['type'] === 'repeater' || $field['type'] === 'gallery' ? [] : '';
        }

        return match ($field['type']) {
            'date' => $value ? gmdate('Ymd', strtotime((string) $value)) : '',
            'datetime' => $value ? wp_date('Y-m-d H:i:s', strtotime((string) $value)) : '',
            'true_false' => $value ? 1 : 0,
            'repeater' => array_map(function ($row) use ($field) {
                $out = [];
                foreach ($field['sub_fields'] as $sub) {
                    $out[$sub['name']] = self::for_acf($sub, $row[$sub['name']] ?? null);
                }

                return $out;
            }, array_values((array) $value)),
            default => $value,
        };
    }

    /**
     * Save one value: ACF when available (correct format for repeaters/galleries), otherwise plain meta.
     */
    public static function save(int $postId, string $postType, array $field, $value): void
    {
        if (function_exists('update_field') && ($field['type'] !== 'repeater' && $field['type'] !== 'gallery' || self::acf_pro())) {
            update_field(self::key($postType, $field['name']), self::for_acf($field, $value), $postId);

            return;
        }
        if ($field['type'] === 'true_false') {
            $value = $value ? 1 : 0;
        }
        update_post_meta($postId, $field['name'], $value);
    }
}
