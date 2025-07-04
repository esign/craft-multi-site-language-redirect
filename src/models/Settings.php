<?php

namespace esign\craftmultisitelanguageredirect\models;

use Craft;
use craft\base\Model;

/**
 * @property bool $enabled
 * @property array $httpMethodsIgnored
 * @property string $cookieName
 * @property array $primarySites
 * @property array $globalExcludedRoutes
 * @property array $excludedRoutesByGroupId
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
    public ?array $disabledSitesByGroupId = null;

    /** @var array */
    public array $globalExcludedRoutes = [
        ['route' => '/robots.txt'],
    ];

    /** @var array */
    public array $excludedRoutesByGroupId = [];

    public function init(): void
    {
        parent::init();
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
            [['globalExcludedRoutes'], 'validateRoutes'],
            [['excludedRoutesByGroupId'], 'validateGroupRoutes'],
        ];
    }

    public function validateRoutes($attribute, $params): void
    {
        if (!is_array($this->$attribute)) {
            return;
        }

        foreach ($this->$attribute as $index => $route) {
            if (!is_array($route) || !isset($route['route']) || empty(trim($route['route']))) {
                $this->addError($attribute, "Route at position " . ($index + 1) . " is invalid or empty.");
                continue;
            }

            $routePath = trim($route['route']);
            if (!str_starts_with($routePath, '/')) {
                $this->addError($attribute, "Route '{$routePath}' must start with a forward slash (/).");
            }
        }
    }

    public function validateGroupRoutes($attribute, $params): void
    {
        if (!is_array($this->$attribute)) {
            return;
        }

        foreach ($this->$attribute as $groupId => $routes) {
            if (!is_array($routes)) {
                continue;
            }

            foreach ($routes as $index => $route) {
                if (!is_array($route) || !isset($route['route']) || empty(trim($route['route']))) {
                    $this->addError($attribute, "Route at position " . ($index + 1) . " for group {$groupId} is invalid or empty.");
                    continue;
                }

                $routePath = trim($route['route']);
                if (!str_starts_with($routePath, '/')) {
                    $this->addError($attribute, "Route '{$routePath}' for group {$groupId} must start with a forward slash (/).");
                }
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'enabled' => Craft::t('multi-site-language-redirect', 'Enable Localization'),
            'httpMethodsIgnored' => Craft::t('multi-site-language-redirect', 'Ignored HTTP Methods'),
            'cookieName' => Craft::t('multi-site-language-redirect', 'Cookie Name'),
            'primarySites' => Craft::t('multi-site-language-redirect', 'Primary Sites'),
            'globalExcludedRoutes' => Craft::t('multi-site-language-redirect', 'Global Excluded Routes'),
            'excludedRoutesByGroupId' => Craft::t('multi-site-language-redirect', 'Excluded Routes by Site Group'),
        ];
    }
}
