<?php

namespace esign\craftmultisitelanguageredirect\services;

use Craft;
use craft\base\Component;
use craft\models\Site;
use esign\craftmultisitelanguageredirect\Plugin;

/**
 * Handles automatic language redirection for multi-site setups
 */
class RedirectService extends Component
{
    /**
     * Main redirect logic - determines if and where to redirect users
     */
    public function redirect(): void
    {
        if ($this->shouldSkipRedirect()) {
            return;
        }

        $localizationService = Plugin::getInstance()->getLocalization();
        $request = Craft::$app->getRequest();
        $language = $request->getSegment(1);

        // Handle already translated routes
        if ($localizationService->isTranslatedRoute()) {
            $this->setLanguageCookieForCurrentSite();
            return;
        }

        // Handle valid language codes that aren't supported
        if ($localizationService->isValidLanguageCode($language)) {
            return;
        }

        // Handle supported languages
        if ($this->isSupportedLanguage($language)) {
            $localizationService->setLanguageCookie($language);
            return;
        }

        // Redirect to preferred language
        $this->redirectToPreferredLanguage($localizationService);
    }

    /**
     * Determines if the redirect should be skipped based on request conditions
     */
    private function shouldSkipRedirect(): bool
    {
        $request = Craft::$app->getRequest();
        $localizationService = Plugin::getInstance()->getLocalization();

        // Skip if not a site request or an action request
        if (!$request->isSiteRequest || $request->isActionRequest) {
            return true;
        }

        // Check if route is excluded from redirection
        if ($localizationService->isRouteExcluded()) {
            return true;
        }

        // Skip if request method is in the ignored methods list
        if ($this->isIgnoredHttpMethod($request)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current HTTP method should be ignored
     */
    private function isIgnoredHttpMethod($request): bool
    {
        $ignoredMethods = Plugin::getInstance()->getSettings()->httpMethodsIgnored;
        return in_array($request->getMethod(), $ignoredMethods, true);
    }

    /**
     * Checks if the given language is supported
     */
    private function isSupportedLanguage(?string $language): bool
    {
        if (!$language) {
            return false;
        }

        return in_array($language, Plugin::getInstance()->getLocalization()->getSupportedLanguages(), true);
    }

    /**
     * Sets the language cookie for the current site
     */
    private function setLanguageCookieForCurrentSite(): void
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $localizationService = Plugin::getInstance()->getLocalization();
        $localizationService->setLanguageCookie($currentSite->language);
    }

    /**
     * Redirects to the preferred language site
     */
    private function redirectToPreferredLanguage(): void
    {
        $preferredLanguage = Plugin::getInstance()->getLocalization()->getPreferredLanguage();
        $targetSite = $this->findSiteByLanguage($preferredLanguage);

        if ($targetSite) {
            $this->performRedirect($targetSite, $preferredLanguage);
        }
    }

    /**
     * Finds a site by language code
     */
    private function findSiteByLanguage(string $language): ?Site
    {
        $sites = Plugin::getInstance()->getLocalization()->getEnabledSites();
        
        foreach ($sites as $site) {
            if ($site->language === $language) {
                return $site;
            }
        }

        return null;
    }

    /**
     * Performs the actual redirect to the target site
     */
    private function performRedirect(Site $targetSite, string $language): void
    {
        $localizationService = Plugin::getInstance()->getLocalization();
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Set the language cookie before redirecting
        $localizationService->setLanguageCookie($language);

        // Build the redirect URL
        $redirectUrl = $targetSite->baseUrl . $request->pathInfo;

        // remove trailing slash
        $redirectUrl = rtrim($redirectUrl, '/');

        // Perform the redirect
        $response->redirect($redirectUrl, 302);
        Craft::$app->end();
    }
}
