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

namespace PrestaShop\Module\FexaAiConnector\Mcp;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\FexaAiConnector\Mcp\Tools\CategoryTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\CmsTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\LlmsTxtTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\ProductTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\ShopTools;

class McpMethodNotFound extends \Exception
{
}

class JsonRpcHandler
{
    public const PROTOCOL_VERSION = '2024-11-05';

    /** @var string */
    private $serverVersion;

    public function __construct($serverVersion = '1.0.0')
    {
        $this->serverVersion = (string) $serverVersion;
    }

    public function handle()
    {
        $raw = file_get_contents('php://input');
        $req = json_decode($raw, true);

        if (!is_array($req) || !isset($req['method'])) {
            $this->respondError(null, -32600, 'Invalid Request: a JSON-RPC 2.0 object with a "method" is required.');

            return;
        }

        $id = array_key_exists('id', $req) ? $req['id'] : null;
        $method = (string) $req['method'];
        $params = (isset($req['params']) && is_array($req['params'])) ? $req['params'] : [];

        // Notifications carry no id and expect no response body.
        if ($id === null && strpos($method, 'notifications/') === 0) {
            http_response_code(202);

            return;
        }

        try {
            switch ($method) {
                case 'initialize':
                    $this->respondResult($id, $this->initializeResult());

                    return;
                case 'ping':
                    $this->respondResult($id, new \stdClass());

                    return;
                case 'tools/list':
                    $this->respondResult($id, ['tools' => $this->toolList()]);

                    return;
                case 'tools/call':
                    $this->respondResult($id, $this->callTool($params));

                    return;
                default:
                    $this->respondError($id, -32601, 'Method not found: ' . $method);

                    return;
            }
        } catch (McpMethodNotFound $e) {
            // -32601 lets the SaaS gracefully disable optional tools (e.g. structured data).
            $this->respondError($id, -32601, $e->getMessage());
        } catch (\Throwable $e) {
            $this->respondError($id, -32000, $e->getMessage(), [
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'type' => get_class($e),
            ]);
        }
    }

    private function callTool(array $params)
    {
        $name = isset($params['name']) ? (string) $params['name'] : '';
        $arguments = (isset($params['arguments']) && is_array($params['arguments'])) ? $params['arguments'] : [];

        $registry = $this->registry();
        if (!isset($registry[$name])) {
            // Unknown tool (e.g. set_structured_data on this lite build) → -32601.
            throw new McpMethodNotFound('Tool not found: ' . $name);
        }

        $entry = $registry[$name];
        $result = $this->invoke($entry[0], $entry[1], $arguments);

        // MCP content envelope: the SaaS unwraps result.content[0].text and JSON-parses it.
        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($result)],
            ],
        ];
    }

    /**
     * Map the JSON `arguments` object onto the tool method's named parameters via
     * reflection (PHP 7.4 has no array-spread to named args). Scalar type juggling
     * coerces JSON "5"/5 to int as the typed signatures expect (no strict_types).
     */
    private function invoke($object, $methodName, array $arguments)
    {
        $ref = new \ReflectionMethod($object, $methodName);
        $callArgs = [];

        foreach ($ref->getParameters() as $p) {
            $pname = $p->getName();
            if (array_key_exists($pname, $arguments)) {
                $callArgs[] = $arguments[$pname];
            } elseif ($p->isDefaultValueAvailable()) {
                $callArgs[] = $p->getDefaultValue();
            } elseif ($p->allowsNull()) {
                $callArgs[] = null;
            } else {
                throw new \Exception('Missing required argument "' . $pname . '" for tool.');
            }
        }

        return $ref->invokeArgs($object, $callArgs);
    }

    /** name => [toolObject, methodName] */
    private function registry()
    {
        $product = new ProductTools();
        $category = new CategoryTools();
        $cms = new CmsTools();
        $shop = new ShopTools();
        $llms = new LlmsTxtTools();

        return [
            'list_languages' => [$shop, 'listLanguages'],
            'list_products' => [$product, 'listProducts'],
            'count_catalog' => [$product, 'countCatalog'],
            'get_product_details' => [$product, 'getProductDetails'],
            'update_product_seo' => [$product, 'updateProductSeo'],
            'update_image_alt' => [$product, 'updateImageAlt'],
            'list_categories' => [$category, 'listCategories'],
            'get_category_details' => [$category, 'getCategoryDetails'],
            'update_category_seo' => [$category, 'updateCategorySeo'],
            'list_cms' => [$cms, 'listCms'],
            'get_cms_details' => [$cms, 'getCmsDetails'],
            'update_cms_seo' => [$cms, 'updateCmsSeo'],
            'set_llms_txt' => [$llms, 'setLlmsTxt'],
            'get_llms_txt' => [$llms, 'getLlmsTxt'],
            'delete_llms_txt' => [$llms, 'deleteLlmsTxt'],
        ];
    }

    private function toolList()
    {
        $int = ['type' => 'integer'];
        $str = ['type' => 'string'];
        $bool = ['type' => 'boolean'];

        $schema = function (array $props, array $required = []) {
            return ['type' => 'object', 'properties' => $props, 'required' => $required];
        };

        return [
            $this->tool('list_languages', 'Get list of active languages in the shop.', $schema([])),
            $this->tool('set_llms_txt', 'Store the shop /llms.txt markdown, served at the domain root (/llms.txt). Must contain a markdown H1.', $schema([
                'content' => $str, 'id_shop' => $int, 'id_lang' => $int,
            ], ['content'])),
            $this->tool('get_llms_txt', 'Read the stored /llms.txt for a shop.', $schema([
                'id_shop' => $int, 'id_lang' => $int,
            ])),
            $this->tool('delete_llms_txt', 'Remove the stored /llms.txt for a shop (the route then returns 404).', $schema([
                'id_shop' => $int, 'id_lang' => $int,
            ])),
            $this->tool('list_products', 'List products with pagination (id, name, meta, url, images).', $schema([
                'langId' => $int, 'limit' => $int, 'offset' => $int, 'onlyActive' => $bool, 'idCategoryId' => $int,
            ])),
            $this->tool('count_catalog', 'Exact counts of products, categories and CMS pages.', $schema(['onlyActive' => $bool])),
            $this->tool('get_product_details', 'Full product details for SEO analysis.', $schema([
                'id_product' => $int, 'id_lang' => $int,
            ], ['id_product'])),
            $this->tool('update_product_seo', 'Update SEO fields, name and slug of a product.', $schema([
                'id_product' => $int, 'id_lang' => $int, 'name' => $str, 'link_rewrite' => $str, 'update_slug' => $bool,
                'description_short' => $str, 'description' => $str, 'meta_title' => $str, 'meta_description' => $str,
            ], ['id_product'])),
            $this->tool('update_image_alt', 'Update the ALT text (legend) of an image.', $schema([
                'id_image' => $int, 'legend' => $str, 'id_lang' => $int,
            ], ['id_image', 'legend'])),
            $this->tool('list_categories', 'List all categories (full tree).', $schema([
                'langId' => $int, 'limit' => $int, 'offset' => $int,
            ])),
            $this->tool('get_category_details', 'Full category details for SEO analysis.', $schema([
                'id_category' => $int, 'id_lang' => $int,
            ], ['id_category'])),
            $this->tool('update_category_seo', 'Update SEO fields, name and slug of a category.', $schema([
                'id_category' => $int, 'id_lang' => $int, 'name' => $str, 'link_rewrite' => $str, 'update_slug' => $bool,
                'description' => $str, 'meta_title' => $str, 'meta_description' => $str,
            ], ['id_category'])),
            $this->tool('list_cms', 'List CMS pages.', $schema([
                'langId' => $int, 'limit' => $int, 'offset' => $int,
            ])),
            $this->tool('get_cms_details', 'Full CMS page details for SEO analysis.', $schema([
                'id_cms' => $int, 'id_lang' => $int,
            ], ['id_cms'])),
            $this->tool('update_cms_seo', 'Update SEO fields, content and slug of a CMS page.', $schema([
                'id_cms' => $int, 'id_lang' => $int, 'content' => $str, 'meta_title' => $str, 'meta_description' => $str,
                'link_rewrite' => $str, 'update_slug' => $bool,
            ], ['id_cms'])),
        ];
    }

    private function tool($name, $description, array $inputSchema)
    {
        return ['name' => $name, 'description' => $description, 'inputSchema' => $inputSchema];
    }

    private function initializeResult()
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => new \stdClass()],
            'serverInfo' => ['name' => 'Fexa AI Connector (PrestaShop 1.7)', 'version' => $this->serverVersion],
        ];
    }

    private function respondResult($id, $result)
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function respondError($id, $code, $message, $data = null)
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error]);
    }

    private function send(array $payload)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($payload);
    }
}
