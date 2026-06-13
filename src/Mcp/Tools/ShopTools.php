<?php

namespace PrestaShop\Module\FexaAiConnector\Mcp\Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Context;
use Language;

class ShopTools
{
    public function listLanguages(): array
    {
        $languages = Language::getLanguages(true, Context::getContext()->shop->id);

        return array_map(function ($l) {
            return [
                'id' => (int)$l['id_lang'],
                'name' => $l['name'],
                'iso_code' => $l['iso_code'],
                'language_code' => $l['language_code'],
                'is_default' => (int)$l['id_lang'] === (int)\Configuration::get('PS_LANG_DEFAULT'),
            ];
        }, $languages);
    }
}
