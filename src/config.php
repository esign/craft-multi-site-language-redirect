<?php

/**
 * Multi Site Language Redirect config.php
 *
 * This file exists only as a template for the Multi Site Language Redirect settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'multi-site-language-redirect.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    '*' => [
        // With this setting you can enable or disable the plugin.
        // 'enabled' => true,

        // With this setting you can ignore certain HTTP methods from being redirected.
        // 'httpMethodsIgnored' => [
        //     'POST',
        //     'PUT',
        //     'PATCH',
        //     'DELETE'
        // ],

        // With this setting you can set the name of the cookie used to store the user's language preference.
        // 'cookieName' => App::env('MULTI_SITE_LANGUAGE_REDIRECT_COOKIE_NAME') ?? 'language',

        // With this setting you can set the primary sites for each group.
        // 'primarySites' => [
        //     '{{siteGroupId}}' => '{{siteId}}',
        // ],

        // With this setting you can set the sites that are enabled for each group.
        // 'disabledSitesByGroupId' => [
        //     '{{siteGroupId}}' => [
        //         '{{siteId}}',
        //         '{{siteId}}',
        //     ],
        // ],

        // With this setting you can exclude routes globally from language redirection.
        // Supports wildcard matching with * (e.g., '/api/*' excludes all API routes).
        // 'globalExcludedRoutes' => [
        //     ['route' => '/robots.txt'],
        //     ['route' => '/sitemap.xml'],
        //     ['route' => '/api/*'],
        //     ['route' => '/admin/*'],
        // ],

        // With this setting you can exclude routes per site group from language redirection.
        // 'excludedRoutesByGroupId' => [
        //     '{{siteGroupId}}' => [
        //         ['route' => '/special-page'],
        //         ['route' => '/group-specific/*'],
        //     ],
        // ],

        // With this setting you can exclude routes globally from language redirection.
        // 'globalExcludedRoutes' => [
        //     ['route' => '/robots.txt'],
        //     ['route' => '/api/*'],
        // ],

        // With this setting you can exclude routes per site group from language redirection.
        // 'excludedRoutesByGroupId' => [
        //     '{{siteGroupId}}' => [
        //         ['route' => '/special-page'],
        //         ['route' => '/group-specific/*'],
        //     ],
        // ],
    ],
];
