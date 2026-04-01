# DevSquad Sidecar

DevSquad Sidecar lets developers and QA test Laravel apps directly from the browser.

## Requirements

- **PHP:** `^8.2`
- **Laravel:** `^11`

---

### 1 — Install

```bash
composer require elitedevsquad/sidecar-laravel --dev
```

### 2 — Publish Config

```bash
php artisan vendor:publish --tag="devsquad-sidecar"
```

This creates `config/devsquad-sidecar.php`, where you can customize options.

### 3 — Configure `.env`

Add the following:

```env
DS_SIDECAR_ENABLED=true

VITE_DS_SIDECAR_ENABLED="${DS_SIDECAR_ENABLED}"
DS_SIDECAR_TINKER_ENABLED=true
DS_SIDECAR_LINK_ENVOYER=https://envoyer.io/projects/xxxxxx
DS_SIDECAR_LINK_MAIL=https://xxx-mail.sbx.devsquad.app
DS_SIDECAR_ALLOWED_IPS="127.0.0,192.168,10"
DS_SIDECAR_BRANCH_URL=https://bitbucket.org/elitedevsquad/project-here/branches/
DS_SIDECAR_TINKER_USE_BATCH=true
```

### 4 — Add CSRF Meta Tag

In your main layout (resources/views/layouts/app.blade.php), add:

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Reference: https://laravel.com/docs/12.x/csrf#csrf-x-csrf-token

### 5 — User Mapping

In `AppServiceProvider.php`:

```php
use EliteDevSquad\SidecarLaravel\Sidecar;

public function boot(): void
{
    Sidecar::$userMap = [
        'id'    => 'id',
        'name'  => 'first_name', // adjust to your column
        'role'  => 'role.name',  // adjust if you have a role attribute
        'email' => 'email',
    ];
    
    Sidecar::$userBuilder = \App\Models\User::with('role');
}
```

### 6 — Frontend Setup

```bash
touch resources/js/devsquad-sidecar.js
```

```javascript
// resources/js/devsquad-sidecar.js
import { Sidecar } from "../../vendor/devsquad-sidecar/resources/js/index.js";

if (import.meta.env.VITE_DS_SIDECAR_ENABLED === "true") {
    document.addEventListener("DOMContentLoaded", () => new Sidecar());
}
```

```javascript
// resources/js/app.js
import "./devsquad-sidecar";
```

Then build your assets:

```bash
npm run build
```

### Step 7 — Fill Branch on Servers Without Git

For servers like Envoyer without a Git repo, add a release hook (envoyer example):

```html
cd {{ release }}

sed -i '' -e '/HEADER_BRANCH_NAME/d' .env
echo HEADER_BRANCH_NAME="{{branch}}" >> .env
```

### Usage

After setup, a Sidecar icon will appear on your site. Click it to open the tool.

**Authentication:**
- IP address restrictions apply to execute commands (Tinker, Commands, Fake Clock).
- Configure `DS_SIDECAR_ALLOWED_IPS` in your `.env` to whitelist specific IPs.
- If `DS_SIDECAR_ALLOWED_IPS` is empty, all authenticated users can execute commands.

**IP Matching Rules:**
- **Exact match**: `127.0.0.1` matches only `127.0.0.1`
- **CIDR notation**: `192.168.1.0/24` matches `192.168.1.0` through `192.168.1.255`
- **Prefix match**: `192.168.1` matches `192.168.1.x` (but not `192.168.10.x`)

**Example IP configurations:**
```env
# Single IP
DS_SIDECAR_ALLOWED_IPS="127.0.0.1"

# CIDR notation for IP range
DS_SIDECAR_ALLOWED_IPS="192.168.1.0/24"

# Prefix match
DS_SIDECAR_ALLOWED_IPS="192.168.1"

# Multiple IPs/patterns
DS_SIDECAR_ALLOWED_IPS="127.0.0.1,192.168.1.0/24,10.0.0"
```
