<?php

namespace esign\craftmultisitelanguageredirect\models;

use Craft;
use craft\base\Model;

/**
 * @property bool $enabled
 * @property array $httpMethodsIgnored
 * @property string $cookieName
 * @property array $primarySites
 */
class Settings extends Model
{
    /** @var bool */
    public bool $enabled = false;

    /** @var array */
    public array $httpMethodsIgnored = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string */
    public string $cookieName = 'language';

    /** @var array */
    public array $primarySites = [];

    /** @var array */
    public ?array $enabledSitesByGroupId = null;

    public function init(): void
    {
        parent::init();
        // If enabledSitesByGroupId is null, enable all sites by default
        if (is_null($this->enabledSitesByGroupId)) {
            $sites = Craft::$app->getSites()->getAllSites();
            $this->enabledSitesByGroupId = [];
            
            foreach ($sites as $site) {
                if (!isset($this->enabledSitesByGroupId[$site->groupId])) {
                    $this->enabledSitesByGroupId[$site->groupId] = [];
                }
                $this->enabledSitesByGroupId[$site->groupId][] = $site->id;
            }
        }
    }

    public function rules(): array
    {
        return [
            [['enabled'], 'boolean'],
            [['httpMethodsIgnored'], 'required'],
            [['httpMethodsIgnored'], 'each', 'rule' => ['string']],
            [['cookieName'], 'required'],
            [['cookieName'], 'string', 'min' => 1],
            [['cookieName'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/', 'message' => 'Cookie name must start with a letter and can only contain letters, numbers, and underscores'],
            [['primarySites'], 'each', 'rule' => ['integer']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'enabled' => Craft::t('multi-site-language-redirect', 'Enable Localization'),
            'httpMethodsIgnored' => Craft::t('multi-site-language-redirect', 'Ignored HTTP Methods'),
            'cookieName' => Craft::t('multi-site-language-redirect', 'Cookie Name'),
            'primarySites' => Craft::t('multi-site-language-redirect', 'Primary Sites'),
        ];
    }
}
