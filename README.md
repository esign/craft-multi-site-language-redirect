# MultiSite Language Redirect

automatically redirects users to the appropriate site in a Craft CMS multisite setup based on their browser language or previously selected language.

## Requirements

This plugin requires Craft CMS 5.6.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project's Control Panel and search for "MultiSite Language Redirect". Then press "Install".

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require esign/craft-multi-site-language-redirect

# tell Craft to install the plugin
./craft plugin/install multi-site-language-redirect
```

## Configuration

### Route Exclusions

You can exclude specific routes from language redirection in two ways:

#### Global Excluded Routes
Routes that should be excluded across all site groups. These are useful for system routes like `/robots.txt`, `/sitemap.xml`, or API endpoints.

#### Site Group Specific Excluded Routes
Routes that should be excluded only for specific site groups. This allows for more granular control when different site groups have different requirements.

#### Wildcard Support
Both global and site group specific exclusions support wildcard matching using `*`:
- `/api/*` - Excludes all routes starting with `/api/`
- `/admin/*` - Excludes all admin routes
- `/special-*` - Excludes routes like `/special-page`, `/special-content`, etc.

#### Configuration via Settings
Configure route exclusions through the plugin settings in the Craft CMS control panel under Settings → Plugins → MultiSite Language Redirect.

#### Configuration via Config File
You can also configure exclusions via the config file `config/multi-site-language-redirect.php`:

```php
return [
    '*' => [
        'globalExcludedRoutes' => [
            ['route' => '/robots.txt'],
            ['route' => '/sitemap.xml'],
            ['route' => '/api/*'],
        ],
        'excludedRoutesByGroupId' => [
            '1' => [
                ['route' => '/special-page'],
                ['route' => '/group-specific/*'],
            ],
        ],
    ],
];
```

### Config Override Detection

The plugin automatically detects when settings are being overridden by a config file and displays appropriate warnings in the settings interface. When a setting is overridden by a config file:

- The form field will show a warning message indicating which config setting is overriding it
- The field will be disabled to prevent conflicts between the config file and database settings
- The warning message will clearly indicate which config file setting is responsible

This helps prevent confusion and ensures that users understand when their settings are being controlled by environment-specific configuration files rather than the database.

#### Example Config File
Copy the `src/config.php` file to your Craft project's `config/` directory as `multi-site-language-redirect.php` and modify the settings as needed:

```php
return [
    '*' => [
        'enabled' => true,
        'cookieName' => 'my_custom_language_cookie',
        'httpMethodsIgnored' => [
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
        ],
        'globalExcludedRoutes' => [
            ['route' => '/robots.txt'],
            ['route' => '/sitemap.xml'],
            ['route' => '/api/*'],
        ],
    ],
    'dev' => [
        'enabled' => false, // Disable in development
    ],
];
```
