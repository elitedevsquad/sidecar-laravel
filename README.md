# DevSquad Sidecar

A Laravel library that lets developers and QA test Laravel apps directly from the browser — run Tinker code, artisan commands, manipulate the fake clock, and impersonate users.

**Requirements:** PHP `^8.2`, Laravel `^11`

---

## Install

Run from inside your Laravel project root:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/elitedevsquad/sidecar-laravel/main/install.sh)
```

The script handles everything automatically:
- `composer require elitedevsquad/sidecar-laravel --dev`
- Publishes `config/devsquad-sidecar.php`
- Writes all `.env` variables (auto-detects APP_URL, mail server, git remote)
- Mirrors keys into `.env.example`
- Injects the CSRF meta tag into any blade layout that has `<html>`, `<body>` and `<meta>`

After running, complete the **User Mapping** step below.

---

## User Mapping

In `AppServiceProvider.php`, configure Sidecar inside a `class_exists()` guard so the app does not break when the package is absent (e.g. production with `--no-dev`):

```php
public function boot(): void
{
    if (class_exists(\EliteDevSquad\SidecarLaravel\Sidecar::class)) {
        \EliteDevSquad\SidecarLaravel\Sidecar::$userMap = [
            'id'    => 'id',
            'name'  => 'first_name',
            'role'  => 'role.name',
            'email' => 'email',
        ];

        \EliteDevSquad\SidecarLaravel\Sidecar::$userBuilder = \App\Models\User::with('role');
    }
}
```

---

## Environment Variables

The install script writes these automatically. Reference for manual setup or code review:

```env
# DevSquad Sidecar
DS_SIDECAR_ENABLED=true
VITE_DS_SIDECAR_ENABLED="${DS_SIDECAR_ENABLED}"
DS_SIDECAR_TINKER_ENABLED=true
DS_SIDECAR_TINKER_USE_BATCH=true
DS_SIDECAR_COMMANDS_ENABLED=true
DS_SIDECAR_FAKE_CLOCK_ENABLED=true
DS_SIDECAR_ALLOWED_IPS="127.0.0.1"
DS_SIDECAR_BRANCH_URL=https://github.com/your-org/your-repo/tree/
DS_SIDECAR_LINK_MAIL=http://localhost:8025
DS_SIDECAR_LINK_ENVOYER=""
```

**`DS_SIDECAR_ALLOWED_IPS`** supports exact IPs, CIDR ranges, and prefix matching:
```env
DS_SIDECAR_ALLOWED_IPS="127.0.0.1,192.168.1.0/24,10.0.0"
```

**`HEADER_BRANCH_NAME`** — on servers without git (e.g. Envoyer), inject via release hook:
```bash
cd {{ release }}
sed -i '' -e '/HEADER_BRANCH_NAME/d' .env
echo HEADER_BRANCH_NAME="{{ branch }}" >> .env
```

---

## Frontend

**Laravel apps:** no action needed. The Sidecar JS is injected automatically before `</body>` on every non-production HTML response.

**External apps (Next.js, Nuxt, etc.):** the bundle is served by the Laravel route `GET /__devsquad-sidecar/assets/js` and reads `window.__sidecarBaseUrl` at runtime to prefix all API calls.

```javascript
// e.g. pages/_app.js, app/layout.js, plugins/sidecar.js
if (process.env.NODE_ENV !== 'production') {
    window.__sidecarBaseUrl = process.env.NEXT_PUBLIC_APP_URL ?? 'https://your-laravel-app.com';
    const s = document.createElement('script');
    s.src = window.__sidecarBaseUrl + '/__devsquad-sidecar/assets/js';
    s.defer = true;
    document.head.appendChild(s);
}
```

---

## Manual Setup

If you prefer not to use the install script:

```bash
composer require elitedevsquad/sidecar-laravel --dev
php artisan vendor:publish --tag="devsquad-sidecar"
```

Then add the env variables above, configure user mapping, and ensure the CSRF meta tag is in your main layout:

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```
