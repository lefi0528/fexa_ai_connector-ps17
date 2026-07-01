<?php
/**
 * Copyright (c) 2025 Fexa AI
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by Fexa AI.
 *
 * @author    Fexa AI <support@fexaai.com>
 * @copyright 2025 Fexa AI
 * @license   Proprietary
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Fexa_ai_connectorLlmsModuleFrontController extends ModuleFrontController
{
    /** Public machine file — no customer authentication. */
    public $auth = false;

    public function initContent()
    {
        // Configuration::get resolves the current shop's value, falling back to global.
        // The value is stored base64-encoded (so PrestaShop's HTML sanitiser can't corrupt
        // the markdown) — decode it; a legacy raw value (not base64) is served as-is.
        $raw = Configuration::get('FEXA_AI_LLMS_TXT');
        $content = false;
        if ($raw !== false && $raw !== null && $raw !== '') {
            $decoded = base64_decode((string) $raw, true);
            $content = ($decoded !== false && $decoded !== '') ? $decoded : (string) $raw;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if ('' === (string) $content) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        header('Cache-Control: public, max-age=86400');
        echo $content;
        exit;
    }
}
