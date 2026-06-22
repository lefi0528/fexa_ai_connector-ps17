<?php

/**
 * Copyright (c) 2025 Fexa AI
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by Fexa AI.
 *
 * Serves the shop's /llms.txt at the domain root from the FEXA_AI_LLMS_TXT
 * Configuration value (stored by the set_llms_txt MCP tool). READ-ONLY: it only
 * reads Configuration and echoes it — it never writes anything and never affects
 * any other page. No content stored → 404.
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
        $content = Configuration::get('FEXA_AI_LLMS_TXT');

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if ($content === false || $content === null || $content === '') {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        header('Cache-Control: public, max-age=86400');
        echo $content;
        exit;
    }
}
