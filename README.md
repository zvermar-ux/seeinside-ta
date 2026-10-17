# SeeInside Travel

Апликација за туристички агенции (Laravel 12 + Filament 3), МК/EN,
улоги Суперадмин / Админ на агенција / Оператор, увоз од Aries.

## Надградба на серверот

```bash
cd /www/wwwroot/ta.seeinsidesolution.com
bash deploy.sh
```

## Увоз од Aries

```bash
cd /www/wwwroot/ta.seeinsidesolution.com
/www/server/php/84/bin/php artisan aries:import && chown -R www:www storage
```

Опции: `--skip-images`, `--skip-details`, `--only=groups,objects`, `--agency=ID`.
