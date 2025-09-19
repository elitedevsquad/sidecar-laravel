# DevSquad Sidecar

DevSquad Sidecar enables developers and QA teams to inspect environment info, view the current Git branch, and run Artisan commands directly from the browser.

## Requirements

* **PHP:** `^8.2`
* **Laravel:** `^11.0`

---

### Install Package

```bash
composer require elitedevsquad/sidecar-laravel

php artisan vendor:publish --tag="devsquad-sidecar"
```

### Configure Options

Edit `config/devsquad-sidecar.php` or set values in your `.env`:

| Option             | Default (`.env`)              | Description                                  |
|--------------------|-------------------------------|----------------------------------------------|
| `enabled`          | `DS_SIDECAR_ENABLED`          | Enable or disable Sidecar.                   |
| `auth_token`       | `DS_SIDECAR_AUTH_TOKEN`       | Secret token for authentication.             |
| `commands_enabled` | `DS_SIDECAR_COMMANDS_ENABLED` | Allow running Artisan commands.              |
| `tinker_enabled`   | `DS_SIDECAR_TINKER_ENABLED`   | Enable Tinker console in the browser.        |
| `links`            | `DS_SIDECAR_LINK_*`           | Quick links displayed in the panel.          |
| `commands`         | (Array of commands)           | Pre-approved Artisan commands.               |
| `branch_name`      | `HEADER_BRANCH_NAME`          | Git branch name to display.                  |
| `branch_url`       | `DS_SIDECAR_BRANCH_URL`       | URL for the branch (e.g., GitHub/Bitbucket). |

### Update `.env`

```env
DS_SIDECAR_ENABLED=true
DS_SIDECAR_TINKER_ENABLED=true
DS_SIDECAR_COMMANDS_ENABLED=true
DS_SIDECAR_LINK_ENVOYER="https://envoyer.io/projects/xxxxxx"
DS_SIDECAR_LINK_MAIL="https://xxx-mail.sbx.devsquad.app"
DS_SIDECAR_BRANCH_URL="https://bitbucket.org/elitedevsquad/xxxxxx/branches/"
DS_SIDECAR_AUTH_TOKEN="your-auth-token-here"
```

---

### Map User Fields

In `app/Providers/AppServiceProvider.php`, add inside `boot()`:

```php
use EliteDevSquad\SidecarLaravel\Sidecar;

public function boot(): void
{
    Sidecar::$userMap = [
        'id'    => 'id',
        'name'  => 'first_name', // adjust to your column
        'role'  => 'role',       // adjust if you have a role attribute
        'email' => 'email',
    ];
    
    Sidecar::$userBuilder = \App\Models\User::with('role');
}
```

> Maps your User model fields to Sidecar display fields. Adjust keys to match your model.

---

### Frontend Setup

**Import Sidecar JS in `resources/js/app.js`:**

```javascript
import { Sidecar } from "../../vendor/elitedevsquad/sidecar-laravel/resources/js/index.js";

document.addEventListener("DOMContentLoaded", () => new Sidecar());
```

**Build Assets**

```bash
npm run build
```
