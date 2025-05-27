<?php

namespace esign\craftmultisitelanguageredirect\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\models\Site;
use craft\validators\LanguageValidator;
use esign\craftmultisitelanguageredirect\Plugin;

class LocalizationService extends Component
{
    private ?array $_enabledSites = null;

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
        $enabledSites = $this->getEnabledSites();


        return array_values(array_filter(
            Craft::$app->getSites()->allSites,
            function($site) use ($hostInfo, $enabledSites) {
                // Only include sites that are enabled for redirection
                if (!empty($enabledSites) && !in_array($site->id, $enabledSites)) {
                    return false;
                }
                return $this->siteMatchesHost($site, $hostInfo);
            }
        ));
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
        $currentGroupId = Craft::$app->getSites()->getCurrentSite()->groupId;
        $enabledSites = $this->getEnabledSites();

        $sites = Craft::$app->getSites()->getSitesByGroupId($currentGroupId);

        return array_values(array_filter($sites, fn($site) => in_array($site->id, $enabledSites)));
    }

    /**
     * Gets all supported languages from sites in the current group
     */
    public function getSupportedLanguages(): array
    {
        return array_map(
            fn($site) => $site->language,
            $this->getSitesInCurrentGroup()
        );
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
        $cookie = new \yii\web\Cookie([
            'name' => $cookieName,
            'value' => $language,
            'expire' => time() + 31536000, // 1 year
            'path' => '/',
            'secure' => Craft::$app->getRequest()->getIsSecureConnection(),
            'httpOnly' => true,
            'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
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
