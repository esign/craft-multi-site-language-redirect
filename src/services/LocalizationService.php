<?php

namespace esign\craftmultisitelanguageredirect\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\models\Site;
use craft\validators\LanguageValidator;
use esign\craftmultisitelanguageredirect\Plugin;
use yii\web\Cookie;

class LocalizationService extends Component
{
    private ?array $_enabledSites = null;
    private ?array $_excludedRoutes = null;
    private ?array $_supportedLanguages = null;

    /**
     * Checks if the current URL is already a translated route for the current site
     */
    public function isTranslatedRoute(): bool
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $currentUrl = trim(Craft::$app->getRequest()->absoluteUrl, '/');
        $siteBaseUrl = trim($currentSite->baseUrl, '/');
        
        return StringHelper::startsWith($currentUrl, $siteBaseUrl);
    }

    /**
     * Sets the appropriate site based on the current URL and primary site settings
     */
    public function setSite(bool $checkCookie = true): void
    {
        if ($this->isTranslatedRoute()) {
            return;
        }

        $hostInfo = Craft::$app->getRequest()->getHostInfo();
        $matchingSites = $this->getSitesMatchingHost($hostInfo);

        if (empty($matchingSites)) {
            return;
        }

        // Try to get language from cookie first
        if ($checkCookie) {
            $cookieLanguage = $this->getLanguageFromCookie();
            if ($cookieLanguage) {
                $site = $this->findSiteByLanguage($cookieLanguage, $matchingSites);
                if ($site) {
                    Craft::$app->getSites()->setCurrentSite($site);
                    return;
                }
            }
        }

        $primarySite = $this->findPrimarySiteForGroup($matchingSites[0]->groupId);
        
        if ($primarySite && $this->siteMatchesHost($primarySite, $hostInfo)) {
            Craft::$app->getSites()->setCurrentSite($primarySite);
            return;
        }

        // Fallback to first matching site if no primary site is found or doesn't match
        Craft::$app->getSites()->setCurrentSite($matchingSites[0]);
    }

    /**
     * Gets all sites that match the given host
     *
     * @return Site[]
     */
    private function getSitesMatchingHost(string $hostInfo): array
    {
        $disabledSitesByGroupId = Plugin::getInstance()->getSettings()->disabledSitesByGroupId;
        $disabledSitesIds = [];
        
        if ($disabledSitesByGroupId) {
            // Flatten the multidimensional array to get all disabled site IDs
            foreach ($disabledSitesByGroupId as $groupId => $siteIds) {
                if (is_array($siteIds)) {
                    $disabledSitesIds = array_merge($disabledSitesIds, $siteIds);
                }
            }
        }

        return array_values(array_filter(
            Craft::$app->getSites()->allSites,
            function(Site $site) use ($hostInfo, $disabledSitesIds): bool {
                // Only include sites that are enabled for redirection (not in disabled list)
                if (!empty($disabledSitesIds) && in_array($site->id, $disabledSitesIds, true)) {
                    return false;
                }
                return $this->siteMatchesHost($site, $hostInfo);
            }
        ));
    }

    /**
     * Gets the enabled sites from settings
     *
     * @return Site[]
     */
    public function getEnabledSites(): array
    {
        if ($this->_enabledSites === null) {
            $currentGroupId = Craft::$app->getSites()->getCurrentSite()->groupId;
            $disabledSites = Plugin::getInstance()->getSettings()->disabledSitesByGroupId[$currentGroupId] ?? [];
            $sites = Craft::$app->getSites()->getSitesByGroupId($currentGroupId);

            if (empty($disabledSites)) {
                $this->_enabledSites = $sites;
            } else {
                $this->_enabledSites = array_values(array_filter($sites, fn(Site $site) => !in_array($site->id, $disabledSites, true)));
            }
        }

        return $this->_enabledSites;
    }

    /**
     * Checks if a site's base URL matches the given host
     */
    private function siteMatchesHost(Site $site, string $hostInfo): bool
    {
        return StringHelper::startsWith($site->baseUrl, $hostInfo);
    }

    /**
     * Finds the primary site for a given group ID
     */
    private function findPrimarySiteForGroup(int $groupId): ?Site
    {
        $settings = Plugin::getInstance()->getSettings();
        
        if (!isset($settings->primarySites[$groupId])) {
            return null;
        }

        return Craft::$app->getSites()->getSiteById($settings->primarySites[$groupId]);
    }

    /**
     * Gets all supported languages from sites in the current group
     *
     * @return string[]
     */
    public function getSupportedLanguages(): array
    {
        if ($this->_supportedLanguages === null) {
            $this->_supportedLanguages = array_map(
                fn(Site $site) => $site->language,
                $this->getEnabledSites()
            );
        }

        return $this->_supportedLanguages;
    }

    /**
     * Gets the preferred language from supported languages, prioritizing the primary language
     */
    public function getPreferredLanguage(?string $language = null): string
    {
        // Try to get language from cookie first
        $cookieLanguage = $this->getLanguageFromCookie();
        if ($cookieLanguage && in_array($cookieLanguage, $this->getSupportedLanguages(), true)) {
            return $cookieLanguage;
        }

        $supportedLanguages = $language ? [$language] : $this->getSupportedLanguages();
        $primaryLanguage = Craft::$app->getSites()->getCurrentSite()->language;

        // Ensure primary language is first in the list
        if (in_array($primaryLanguage, $supportedLanguages, true)) {
            $supportedLanguages = array_merge(
                [$primaryLanguage],
                array_diff($supportedLanguages, [$primaryLanguage])
            );
        }

        return Craft::$app->getRequest()->getPreferredLanguage($supportedLanguages);
    }

    /**
     * Validates if a given string is a valid language code
     */
    public function isValidLanguageCode(?string $language): bool
    {
        if (!$language) {
            return false;
        }

        $validator = new LanguageValidator();
        $validator->onlySiteLanguages = false;

        return $validator->validate($language);
    }

    /**
     * Gets the language from the cookie
     */
    private function getLanguageFromCookie(): ?string
    {
        $cookieName = Plugin::getInstance()->getSettings()->cookieName;
        return Craft::$app->getRequest()->getCookies()->getValue($cookieName);
    }

    /**
     * Sets the language cookie
     */
    public function setLanguageCookie(string $language): void
    {
        $cookieName = Plugin::getInstance()->getSettings()->cookieName;
        $cookie = new Cookie([
            'name' => $cookieName,
            'value' => $language,
            'expire' => time() + 31536000, // 1 year
            'path' => '/',
            'secure' => Craft::$app->getRequest()->getIsSecureConnection(),
            'httpOnly' => true,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ]);

        Craft::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * Finds a site by language code from a list of sites
     */
    private function findSiteByLanguage(string $language, array $sites): ?Site
    {
        foreach ($sites as $site) {
            if ($site->language === $language) {
                return $site;
            }
        }
        return null;
    }

    /**
     * Checks if the current route should be excluded from redirection
     */
    public function isRouteExcluded(?string $route = null): bool
    {
        if ($route === null) {
            $route = Craft::$app->getRequest()->getUrl();
        }

        $excludedRoutes = $this->getExcludedRoutes();
        
        foreach ($excludedRoutes as $excludedRoute) {
            if ($this->matchesRoute($route, $excludedRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets all excluded routes for the current site group combined with global excluded routes
     *
     * @return string[]
     */
    public function getExcludedRoutes(): array
    {
        if ($this->_excludedRoutes === null) {
            $settings = Plugin::getInstance()->getSettings();
            $currentGroupId = Craft::$app->getSites()->getCurrentSite()->groupId;
            
            $excludedRoutes = [];
            
            // Add global excluded routes
            if (!empty($settings->globalExcludedRoutes)) {
                foreach ($settings->globalExcludedRoutes as $routeData) {
                    if (isset($routeData['route']) && !empty(trim($routeData['route']))) {
                        $excludedRoutes[] = trim($routeData['route']);
                    }
                }
            }
            
            // Add site group specific excluded routes
            if (!empty($settings->excludedRoutesByGroupId[$currentGroupId])) {
                foreach ($settings->excludedRoutesByGroupId[$currentGroupId] as $routeData) {
                    if (isset($routeData['route']) && !empty(trim($routeData['route']))) {
                        $excludedRoutes[] = trim($routeData['route']);
                    }
                }
            }
            
            $this->_excludedRoutes = array_unique($excludedRoutes);
        }

        return $this->_excludedRoutes;
    }

    /**
     * Checks if a route matches an excluded route pattern
     */
    private function matchesRoute(string $route, string $pattern): bool
    {
        // Normalize routes by removing trailing slashes and ensuring leading slash
        $route = '/' . trim($route, '/');
        $pattern = '/' . trim($pattern, '/');
        
        // Handle root route
        if ($pattern === '/') {
            return $route === '/';
        }
        
        // Support wildcard matching with *
        if (str_contains($pattern, '*')) {
            $regexPattern = str_replace(['/', '*'], ['\/', '.*'], $pattern);
            return preg_match('/^' . $regexPattern . '$/', $route) === 1;
        }
        
        // Exact match
        return $route === $pattern;
    }
}
