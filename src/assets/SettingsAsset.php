<?php

namespace esign\craftmultisitelanguageredirect\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SettingsAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@esign/craftmultisitelanguageredirect/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/cp.css',
        ];

        parent::init();
    }
} 