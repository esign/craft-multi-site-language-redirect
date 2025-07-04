<?php

namespace esign\craftmultisitelanguageredirect;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\Application;
use esign\craftmultisitelanguageredirect\assets\SettingsAsset;
use esign\craftmultisitelanguageredirect\models\Settings;
use esign\craftmultisitelanguageredirect\services\LocalizationService;
use yii\base\Event;

/**
 * Multi Site Language Redirect plugin
 *
 * @property-read LocalizationService $localizationService
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author dieter.vanhove@outlook.com <support.web@dynamate.be>
 * @copyright dieter.vanhove@outlook.com
 * @license MIT
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'localizationService' => LocalizationService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        if (!$this->getSettings()->enabled) {
            return;
        }

        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            // Check if route is excluded from redirection
            if ($this->localizationService->isRouteExcluded()) {
                $this->localizationService->setSite(false);
            } else {
                $this->localizationService->setSite();
            }
        }

        $this->attachEventHandlers();
    }

    /**
     * Attach event handlers for request processing
     */
    private function attachEventHandlers(): void
    {
        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            function(Event $event) {
                $request = Craft::$app->getRequest();
                if (!$request->isSiteRequest) {
                    return;
                }
        
                // Check if route is excluded from redirection
                if ($this->localizationService->isRouteExcluded()) {
                    return;
                }

                // Skip if request method is in the ignored methods list
                if (in_array($request->getMethod(), $this->getSettings()->httpMethodsIgnored)) {
                    return;
                }
        
                $localizationService = $this->localizationService;
        
                // Skip if already a translated route
                if ($localizationService->isTranslatedRoute()) {
                    $localizationService->setLanguageCookie(Craft::$app->getSites()->getCurrentSite()->language);
                    return;
                }
        
                $language = $request->getSegment(1);
                
                // Skip if language is valid but not supported
                if ($localizationService->isValidLanguageCode($language)) {
                    return;
                }
        
                // Skip if language is supported
                if (in_array($language, $localizationService->getSupportedLanguages())) {
                    // Set the language cookie when a supported language is selected
                    $localizationService->setLanguageCookie($language);
                    return;
                }
        
                // Redirect to the preferred language site
                $localizationService = $this->localizationService;
                $preferredLanguage = $localizationService->getPreferredLanguage();
                $sites = $localizationService->getEnabledSites();
        
                // Find the site matching the preferred language
                $targetSite = current(array_filter($sites, function($site) use ($preferredLanguage) {
                    return $site->language === $preferredLanguage;
                }));
        
                if ($targetSite) {
                    // Set the language cookie before redirecting
                    $localizationService->setLanguageCookie($preferredLanguage);
                    Craft::$app->getResponse()->redirect($targetSite->baseUrl . Craft::$app->getRequest()->pathInfo, 302);
                    Craft::$app->end();
                }
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        // Register the asset bundle for the settings page
        Craft::$app->getView()->registerAssetBundle(SettingsAsset::class);
        
        return Craft::$app->view->renderTemplate('multi-site-language-redirect/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('multi-site-language-redirect'),
        ]);
    }
}
