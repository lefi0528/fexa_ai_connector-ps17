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

        // Security: verify API key from header or query string.
        $storedKey = (string) Configuration::get('FEXA_AI_API_KEY');

        $apiKey = '';
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            $apiKey = $matches[1];
        }
        if (empty($apiKey) && !empty($_SERVER['HTTP_X_MCP_API_KEY'])) {
            $apiKey = $_SERVER['HTTP_X_MCP_API_KEY'];
        }
        if (empty($apiKey)) {
            // Cast to string: Tools::getValue returns false when absent; false to
            // hash_equals() is a fatal TypeError on PHP 7.x/8.x.
            $apiKey = (string) (Tools::getValue('token') ?: Tools::getValue('api_key'));
        }

        if (!$storedKey || !hash_equals($storedKey, $apiKey)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid API Key']);
            exit;
        }

        $version = $this->module->version ?: '1.0.0';
        $handler = new JsonRpcHandler($version);
        $handler->handle();
        exit;
    }
}
