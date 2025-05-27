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
    private ?array $_sitesInCurrentGroup = null;
    private ?array $_supportedLanguages = null;
    private ?array $_enabledSites = null;

    /**
     * Checks if the current URL is already a translated route for the current site
     */
    public function isTranslatedRoute(): bool
    {
        static $isTranslatedRoute = null;
        
        if ($isTranslatedRoute !== null) {
            return $isTranslatedRoute;
        }

        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $currentUrl = trim(Craft::$app->getRequest()->absoluteUrl, '/');
        $siteBaseUrl = trim($currentSite->baseUrl, '/');
        $isTranslatedRoute = StringHelper::startsWith($currentUrl, $siteBaseUrl);

        return $isTranslatedRoute;
    }

    /**
     * Sets the appropriate site based on the current URL and primary site settings
     */
    public function setSite($checkCookie = true): void
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
     */
    private function getSitesMatchingHost(string $hostInfo): array
    {
        static $matchingSitesCache = [];
        
        if (isset($matchingSitesCache[$hostInfo])) {
            return $matchingSitesCache[$hostInfo];
        }

        $settings = Plugin::getInstance()->getSettings();
        $enabledSites = $this->getEnabledSites();

        $matchingSitesCache[$hostInfo] = array_values(array_filter(
            Craft::$app->getSites()->allSites,
            function($site) use ($hostInfo, $enabledSites) {
                // Only include sites that are enabled for redirection
                if (!empty($enabledSites) && !in_array($site->id, $enabledSites)) {
                    return false;
                }
                return $this->siteMatchesHost($site, $hostInfo);
            }
        ));

        return $matchingSitesCache[$hostInfo];
    }

    /**
     * Gets the enabled sites from settings
     */
    private function getEnabledSites(): array
    {
        if ($this->_enabledSites === null) {
            $this->_enabledSites = Plugin::getInstance()->getSettings()->enabledSites;
        }
        return $this->_enabledSites;
    }

    /**
     * Checks if a site's base URL matches the given host
     */
    private function siteMatchesHost($site, string $hostInfo): bool
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
     * Gets all sites in the current site's group
     */
    public function getSitesInCurrentGroup(): array
    {
        if ($this->_sitesInCurrentGroup !== null) {
            return $this->_sitesInCurrentGroup;
        }

        $currentGroupId = Craft::$app->getSites()->getCurrentSite()->groupId;
        $enabledSites = $this->getEnabledSites();

        $sites = Craft::$app->getSites()->getSitesByGroupId($currentGroupId);
        
        // Filter sites based on enabledSites setting
        if (!empty($enabledSites)) {
            $sites = array_filter($sites, function($site) use ($enabledSites) {
                return in_array($site->id, $enabledSites);
            });
        }

        $this->_sitesInCurrentGroup = array_values($sites);
        return $this->_sitesInCurrentGroup;
    }

    /**
     * Gets all supported languages from sites in the current group
     */
    public function getSupportedLanguages(): array
    {
        if ($this->_supportedLanguages !== null) {
            return $this->_supportedLanguages;
        }

        $this->_supportedLanguages = array_map(
            fn($site) => $site->language,
            $this->getSitesInCurrentGroup()
        );

        return $this->_supportedLanguages;
    }

    /**
     * Gets the preferred language from supported languages, prioritizing the primary language
     */
    public function getPreferredLanguage(?string $language = null): string
    {
        // Try to get language from cookie first
        $cookieLanguage = $this->getLanguageFromCookie();
        if ($cookieLanguage && in_array($cookieLanguage, $this->getSupportedLanguages())) {
            return $cookieLanguage;
        }

        $supportedLanguages = $language ? [$language] : $this->getSupportedLanguages();
        $primaryLanguage = Craft::$app->getSites()->getCurrentSite()->language;

        // Ensure primary language is first in the list
        if (in_array($primaryLanguage, $supportedLanguages)) {
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

        static $validator = null;
        if ($validator === null) {
            $validator = new LanguageValidator();
            $validator->onlySiteLanguages = false;
        }

        return $validator->validate($language);
    }

    /**
     * Gets the language from the cookie
     */
    private function getLanguageFromCookie(): ?string
    {
        static $cookieName = null;
        if ($cookieName === null) {
            $cookieName = Plugin::getInstance()->getSettings()->cookieName;
        }
        return Craft::$app->getRequest()->getCookies()->getValue($cookieName);
    }

    /**
     * Sets the language cookie
     */
    public function setLanguageCookie(string $language): void
    {
        static $cookieName = null;
        if ($cookieName === null) {
            $cookieName = Plugin::getInstance()->getSettings()->cookieName;
        }

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
}
