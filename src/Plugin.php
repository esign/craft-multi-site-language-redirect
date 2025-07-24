<?php

declare(strict_types=1);

namespace esign\craftmultisitelanguageredirect;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\Application;
use esign\craftmultisitelanguageredirect\assets\SettingsAsset;
use esign\craftmultisitelanguageredirect\models\Settings;
use esign\craftmultisitelanguageredirect\services\LocalizationService;
use esign\craftmultisitelanguageredirect\services\RedirectService;
use yii\base\Event;

/**
 * Multi Site Language Redirect plugin
 *
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
                'localization' => LocalizationService::class,
                'redirect' => RedirectService::class,
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
            if ($this->getLocalization()->isRouteExcluded()) {
                $this->getLocalization()->setSite(false);
            } else {
                $this->getLocalization()->setSite();
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
            [$this->getRedirect(), 'redirect']
        );
    }

    public function getRedirect(): RedirectService
    {
        return $this->get('redirect');
    }

    public function getLocalization(): LocalizationService
    {
        return $this->get('localization');
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): string
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
