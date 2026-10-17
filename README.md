# SeeInside Travel

Апликација за туристички агенции (Laravel 12 + Filament 3), МК/EN,
улоги Суперадмин / Админ на агенција / Оператор, увоз од Aries.

## Модули

- Организирани тури (програма по денови, датуми и места на поаѓање, доплати, First/Last minute)
- Настани (концерти, спорт…) со типови билети
- Хотели и апартмани со соби, ценовник по периоди и содржини
- Визи со прашалник и прикачување документи
- Резервации и барања за визи од веб-страницата
- Увезена понуда од Aries (со претворање во тура/хотел)
- API + webhooks за WordPress — види [docs/API.md](docs/API.md)

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
