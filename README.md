# Setup Guide: DevSquad Sidecar

DevSquad Sidecar is a tool to help developers and QA test Laravel applications directly from the browser.

## Requirements

Before you begin, ensure your project and environment meet the following requirements:

* **PHP:** `^8.2` (8.2 or higher)
* **Laravel Framework:** `^11.0` or `^12.0`

## Setup

Setup has two parts: the Laravel package and the browser extension.

### Part 1: Laravel Setup

Follow these steps in your project's terminal and code editor.

**1. Install the Composer Package**

```bash
composer require elitedevsquad/sidecar-extension
```

**2. Publish Config Files**

```bash
php artisan vendor:publish --tag="devsquad-sidecar"
```

**3. Customize the Configuration (Optional)**

This creates a `config/devsquad-sidecar.php` file. You can edit this file to customize which features are available. While many settings use your `.env` file by default, you can change them here.

Here is a summary of the options you can change:

| Option             | Default Value (from `.env`)   | Description                                                    |
|--------------------|-------------------------------|----------------------------------------------------------------|
| `enabled`          | `DS_SIDECAR_ENABLED`          | Turns the entire Sidecar tool on (`true`) or off (`false`).    |
| `auth_token`       | `DS_SIDECAR_AUTH_TOKEN`       | Sets the secret token for authentication.                      |
| `commands_enabled` | `DS_SIDECAR_COMMANDS_ENABLED` | Allows or disallows running Artisan commands.                  |
| `tinker_enabled`   | `DS_SIDECAR_TINKER_ENABLED`   | Enables or disables the Tinker console feature.                |
| `links`            | `DS_SIDECAR_LINK_MAIL`, etc.  | Defines the list of quick links shown in the Sidecar panel.    |
| `commands`         | (Array of commands)           | Defines a list of pre-approved Artisan commands for the panel. |
| `branch_name`      | `HEADER_BRANCH_NAME`          | Specifies the Git branch name to display.                      |
| `branch_url`       | `DS_SIDECAR_BRANCH_URL`       | Sets a URL for the branch name (e.g., link to GitHub).         |

**4. Update Your `.env` File**

Add the following variables to your `.env` file. These are the most common settings you will need to configure.

> **Important:** Create a strong, random token for `DS_SIDECAR_AUTH_TOKEN`. **Never** commit it to Git.

```env
DS_SIDECAR_ENABLED=true
DS_SIDECAR_TINKER_ENABLED=true
DS_SIDECAR_LINK_ENVOYER=[https://envoyer.io/projects/xxxxxx](https://envoyer.io/projects/xxxxxx)
DS_SIDECAR_LINK_MAIL=[https://xxx-mail.sbx.devsquad.app](https://xxx-mail.sbx.devsquad.app)
DS_SIDECAR_AUTH_TOKEN=your-auth-token-here
```

**5. Configure Authorization & User Mapping**

In your `app/Providers/AppServiceProvider.php` file, add the following code to the `boot()` method. This configures which user attributes are displayed in Sidecar and who is authorized to use its features.

First, make sure to import the necessary classes at the top of the file:

```php
use Illuminate\Support\Facades\Gate;
use EliteDevsquad\SidecarExtensionBridge\SidecarBridge;
```

Then, add this logic inside the `boot()` method:

```php
public function boot(): void
{
    /**
     * Map the fields from your User model to be displayed in Sidecar.
     * The key is the Sidecar field, and the value is the column name in your `users` table.
     * Adjust 'first_name' and 'role' to match your User model's attributes.
     */
    SidecarBridge::$userMap = [
        'id'    => 'id',
        'name'  => 'first_name', // Example: change if your name column is 'name'
        'role'  => 'role',       // Example: change if you have a role attribute
        'email' => 'email',
    ];
}
```

**6. Update Your `resources/js/app.js`**

Add the following code to your main JavaScript file (`resources/js/app.js`) to initialize the Sidecar script. This allows the tool to load on your site.

```javascript
import { Sidecar } from "../../vendor/devsquad-sidecar/resources/js/index.js";

document.addEventListener("DOMContentLoaded", () => new Sidecar());
```

**7. Build Assets**

Now, build your frontend assets. This step compiles the JavaScript you just added and makes the auth token available.

```bash
npm run build
```

---

### Part 2: Browser Extension Setup

You will need to install the extension from a `.zip` file.

#### Google Chrome

1.  Unzip the `.zip` file into a folder.
2.  Open Chrome and go to `chrome://extensions`.
3.  Enable **Developer mode**.
4.  Click **Load unpacked**.
5.  Select the folder you extracted.

#### Safari

1.  Unzip the `.zip` file.
2.  Open Safari and go to **Settings > Advanced**.
3.  Check the box for **"Show features for web developers"**.
4.  In the top menu bar, go to **Develop > Unpacked Extensions...**
5.  Select the folder you extracted.

## Usage

After setup, a Sidecar icon will appear on your site. Click it to open the tool. On first use, you will need to enter your `DS_SIDECAR_AUTH_TOKEN` in the extension's settings to authenticate.
