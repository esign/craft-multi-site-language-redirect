<?php

namespace esign\craftmultisitelanguageredirect\models;

use Craft;
use craft\base\Model;

/**
 * @property bool $enabled
 * @property array $httpMethodsIgnored
 * @property string $cookieName
 * @property array $primarySites
 * @property array $enabledSites
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
    public array $enabledSites = [];

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
            [['enabledSites'], 'each', 'rule' => ['integer']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'enabled' => Craft::t('multi-site-language-redirect', 'Enable Localization'),
            'httpMethodsIgnored' => Craft::t('multi-site-language-redirect', 'Ignored HTTP Methods'),
            'cookieName' => Craft::t('multi-site-language-redirect', 'Cookie Name'),
            'primarySites' => Craft::t('multi-site-language-redirect', 'Primary Sites'),
            'enabledSites' => Craft::t('multi-site-language-redirect', 'Enabled Sites for Redirection'),
        ];
    }
}
