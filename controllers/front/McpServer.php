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
require_once dirname(dirname(__DIR__)) . '/src/autoload.php';

use PrestaShop\Module\FexaAiConnector\Mcp\JsonRpcHandler;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Fexa_ai_connectorMcpServerModuleFrontController extends ModuleFrontController
{
    public $module;

    /**
     * Shop in maintenance mode: a request carrying a VALID API key in a header (Fexa AI itself) is let
     * through, so a shop under construction can still be connected and optimised. Any other request
     * gets the standard PrestaShop maintenance page (503), exactly as before.
     */
    protected function displayMaintenancePage()
    {
        if ($this->hasValidHeaderApiKey()) {
            return;
        }

        parent::displayMaintenancePage();
    }

    /**
     * Same rule for the geolocation "restricted country" page (403): Fexa AI's servers must not be
     * locked out by the shop's country restrictions; everyone else is treated as before.
     */
    protected function displayRestrictedCountryPage()
    {
        if ($this->hasValidHeaderApiKey()) {
            return;
        }

        parent::displayRestrictedCountryPage();
    }

    public function initContent()
    {
        // Restrict CORS to fexaai.com only.
        $allowedOrigins = ['https://fexaai.com', 'https://www.fexaai.com'];
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-MCP-API-KEY, Mcp-Protocol-Version, Mcp-Session-Id, Last-Event-ID');

        // Handle preflight.
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Security: the API key is accepted from a request header only (see hasValidHeaderApiKey()).
        if (!$this->hasValidHeaderApiKey()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid API Key']);
            exit;
        }

        $this->recordLastSeen();

        $version = $this->module->version ?: '1.0.0';
        $handler = new JsonRpcHandler($version);
        $handler->handle();
        exit;
    }

    /**
     * True when the request presents this module's API key in a header — `Authorization: Bearer <key>`
     * or `X-MCP-API-KEY: <key>` (Fexa AI sends both) — compared in constant time with the stored key.
     * A key passed in the URL (?token= / ?api_key=) is no longer accepted: URLs end up in access logs.
     */
    private function hasValidHeaderApiKey()
    {
        $storedKey = (string) Configuration::get('FEXA_AI_API_KEY');
        if ($storedKey === '') {
            return false;
        }

        $candidates = [];
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            $candidates[] = $matches[1];
        }
        if (isset($_SERVER['HTTP_X_MCP_API_KEY'])) {
            $candidates[] = (string) $_SERVER['HTTP_X_MCP_API_KEY'];
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && hash_equals($storedKey, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Closed loop for the module's configuration page ("connected — last exchange X ago"): remember when
     * Fexa AI last authenticated, at most once an hour (read first, so a burst of MCP calls does not turn
     * into a burst of configuration writes).
     */
    private function recordLastSeen()
    {
        try {
            $now = time();
            $lastSeen = (int) Configuration::getGlobalValue('FEXA_AI_LAST_SEEN');
            if ($lastSeen > $now || $now - $lastSeen >= 3600) {
                Configuration::updateGlobalValue('FEXA_AI_LAST_SEEN', (string) $now);
            }
        } catch (Throwable $e) {
            // Bookkeeping only: never fail an authenticated MCP request because of it.
        }
    }
}
