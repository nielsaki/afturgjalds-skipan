# Afturgjald — skipan

WordPress plugin fyri at taka ímóti fráboðanum um afturgjald (koyring, útreiðslur o.a.) til FSS. Eitt submit kann hava fleiri linjur og ymisk sløg, og hvør linja kann hava eina valfría viðhefting (t.d. reikning ella kvittan).

## Innihald

```
afturgjald-skipan/
├── drive-reimbursement-form.php   # plugin entrypoint (bootstrap)
├── includes/
│   ├── class-afs-plugin.php       # hooks + menu
│   ├── class-afs-form.php         # renders form + line template
│   ├── class-afs-submission.php   # validates + sends
│   ├── class-afs-mail.php         # wp_mail wrapper + test mode
│   ├── class-afs-logger.php       # log-fíla í próvingarhami
│   ├── class-afs-settings.php     # admin settings page
│   ├── class-afs-types.php        # type registry
│   └── types/
│       ├── class-afs-type.php            # abstract base
│       ├── class-afs-type-driving.php    # Koyring (km + tunnlar)
│       ├── class-afs-type-expense.php    # Útreiðsla (upphædd + viðhefting)
│       └── class-afs-type-other.php      # Annað
├── assets/
│   ├── css/drf.css
│   └── js/drf.js
├── tests/                         # standalone PHP test suite + preview
│   ├── bootstrap.php
│   ├── wp-stubs.php
│   ├── test-cases.php
│   ├── run-tests.php
│   └── serve.php                  # localhost preview (uses PHP built-in server)
└── readme.md
```

## Shortcode

- `[afturgjald_form]` (nýggj) — rennur formin.
- `[drive_reimbursement_form]` (gamal) — alias fyri sama form; er her fyri ikki at broyta eldri síður.

## Stillingar

Innstillingar-síða: **Stillingar → Afturgjald**. Her kanst tú seta gjald pr. km og síggja log-fíluna í próvingarhami.

## Próvingarham (lokalt / staging)

Legg hetta í `wp-config.php` fyri lokalt umhvørvi ella staging:

```php
define('DRF_EMAIL_TEST_MODE', true);
define('DRF_EMAIL_TEST_TO',   'tín@epost.fo'); // valfrítt — annars admin-teldupostur
define('DRF_EMAIL_DRY_RUN',   true);            // valfrítt — einki teldupostur verður í roynd og veru sendur
define('DRF_EMAIL_LOG_FILE',  WP_CONTENT_DIR . '/afs-email.log'); // valfrítt
```

Í próvingarhami:

- Teldupostur verður **ikki** sendur til `bokhald@fss.fo` ella avsendara.
- Hvør teldupostur fær ein bannara omanfyri sum sigur hvat upphavliga móttakara eitur og hvat slag av telduposti tað er (Bókhald ella Kvittan).
- Alt verður skrivað í log-fíluna (`wp-content/uploads/afturgjald-skipan/email-test.log` sum default).
- Um `DRF_EMAIL_DRY_RUN` er `true`: einki `wp_mail()` verður kallað — alt fer bert í loggin.

Innstillingar-síðan vísir seinasta partin av logginum beinleiðis.

## Lokal próving í kaga (uttan WordPress)

Frá plugin-mappuni:

```bash
php -S localhost:8080 -t . tests/serve.php
```

Opna síðani `http://localhost:8080/` í kaganum. Tá sæst:

- Vinstru megin: Formurin (sama form sum í WordPress).
- Høgru megin: Log-fílan við hvørjum teldupostum, sum vildi verið sendir.

Dry-run er settur frá byrjan, so **einki verður í roynd og veru sent**. Trýst á "Tøm log" fyri at byrja umaftur.

## Lokal próving í WordPress (fulla skipanin)

Um tú brúkar [Local](https://localwp.com/) (mappan `~/Local Sites/` varð síggj í heimamappuni):

1. Stovna eitt nýtt site í Local.
2. Symlink ella copyer `afturgjald-skipan/` inn í `app/public/wp-content/plugins/`:
   ```bash
   ln -s "/Users/nielsakimork/Library/CloudStorage/Dropbox/Føroya Styrkisamband (FSS)/10 Heimasíðan/2 SelfMade_Plugins/afturgjald-skipan" \
         ~/Local\ Sites/DIN-SITE/app/public/wp-content/plugins/afturgjald-skipan
   ```
3. Virka pluginið undir `Plugins` → `Afturgjald — skipan`.
4. Legg test-mode konstantirnar í `app/public/wp-config.php` (sí undir).
5. Stovna eina síðu og set inn shortcodu `[afturgjald_form]`.

## Automatiskar royningar (CLI)

```bash
cd /path/til/afturgjald-skipan
php tests/run-tests.php
```

Hetta koyrir eina rekku av test-cases sum:

- Valid og ógildig submissions
- Upprokningar (koyring × km × tunnlar, útreiðsla, blandaðar linjur)
- Nonce, honeypot, og goodtakingar-krav
- Viðhefting í email-kroppi og loggi
- Dry-run skipan

Royningarnar brúka stubs fyri WordPress-funktiónir (sí `tests/wp-stubs.php`), so tær kunnu koyrast uttan WordPress installeraða.

## Leggja eitt nýtt slag afturat

```php
add_filter('afs_types', function ($types) {
    require_once __DIR__ . '/class-my-per-diem.php';
    $types['per_diem'] = new My_Per_Diem_Type();
    return $types;
});
```

Nýggja klassan arvir `AFS_Type` og implementerar `id()`, `label()`, `validate()`, `amount()`, `format_for_email()`, og `render_form_fields()`.

## Leggja tunnlar afturat

```php
add_filter('afs_tunnels', function ($tunnels) {
    $tunnels['Nýggi Tunnilin'] = 30;
    return $tunnels;
});
```

## Broyta móttakara

```php
add_filter('afs_recipient', function ($to) { return 'ein-annar@fss.fo'; });
```
