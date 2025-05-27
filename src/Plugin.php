<?php

namespace esign\craftmultisitelanguageredirect;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\Application;
use esign\craftmultisitelanguageredirect\models\Settings;
use esign\craftmultisitelanguageredirect\services\LocalizationService;
use yii\base\Event;

/**
 * MultiSite Language Redirect plugin
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
        
        // Early return if plugin is disabled
        if (!$this->getSettings()->enabled) {
            return;
        }

        // Only attach event handlers for site requests
        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            $this->attachEventHandlers();
        }
    }

    /**
     * Attach event handlers for request processing
     */
    private function attachEventHandlers(): void
    {
        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            [$this, 'handleRequest']
        );
    }

    /**
     * Handle the incoming request and perform necessary redirects
     */
    public function handleRequest(Event $event): void
    {
        $request = Craft::$app->getRequest();
        
        // Early returns for non-site requests and specific paths
        if (!$request->isSiteRequest || $request->getUrl() === '/robots.txt') {
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
        $this->redirectToPreferredLanguage();
    }

    /**
     * Redirect to the site with the preferred language
     */
    private function redirectToPreferredLanguage(): void
    {
        $localizationService = $this->localizationService;
        $preferredLanguage = $localizationService->getPreferredLanguage();
        $sites = $localizationService->getSitesInCurrentGroup();

        // Find the site matching the preferred language
        foreach ($sites as $site) {
            if ($site->language === $preferredLanguage) {
                // Set the language cookie before redirecting
                $localizationService->setLanguageCookie($preferredLanguage);
                Craft::$app->getResponse()->redirect($site->baseUrl, 302);
                Craft::$app->end();
                return;
            }
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('multi-site-language-redirect/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
