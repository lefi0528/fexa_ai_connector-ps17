<?php

/**
 * Copyright (c) 2025 Fexa AI — All Rights Reserved.
 *
 * Hand-rolled MCP (JSON-RPC 2.0) handler for the PrestaShop 1.7 / PHP 7.4 build.
 * Replaces php-mcp/server (which requires PHP 8.1) with a minimal, dependency-free
 * dispatcher for a tools-only server: initialize / ping / tools/list / tools/call.
 * The tool bodies are identical to the flagship module; only the transport differs.
 */

namespace PrestaShop\Module\FexaAiConnector\Mcp;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\FexaAiConnector\Mcp\Tools\ProductTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\CategoryTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\CmsTools;
use PrestaShop\Module\FexaAiConnector\Mcp\Tools\ShopTools;

class McpMethodNotFound extends \Exception
{
}

class JsonRpcHandler
{
    const PROTOCOL_VERSION = '2024-11-05';

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
        $params = (isset($req['params']) && is_array($req['params'])) ? $req['params'] : array();

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
                    $this->respondResult($id, array('tools' => $this->toolList()));

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
            $this->respondError($id, -32000, $e->getMessage(), array(
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'type' => get_class($e),
            ));
        }
    }

    private function callTool(array $params)
    {
        $name = isset($params['name']) ? (string) $params['name'] : '';
        $arguments = (isset($params['arguments']) && is_array($params['arguments'])) ? $params['arguments'] : array();

        $registry = $this->registry();
        if (!isset($registry[$name])) {
            // Unknown tool (e.g. set_structured_data on this lite build) → -32601.
            throw new McpMethodNotFound('Tool not found: ' . $name);
        }

        $entry = $registry[$name];
        $result = $this->invoke($entry[0], $entry[1], $arguments);

        // MCP content envelope: the SaaS unwraps result.content[0].text and JSON-parses it.
        return array(
            'content' => array(
                array('type' => 'text', 'text' => json_encode($result)),
            ),
        );
    }

    /**
     * Map the JSON `arguments` object onto the tool method's named parameters via
     * reflection (PHP 7.4 has no array-spread to named args). Scalar type juggling
     * coerces JSON "5"/5 to int as the typed signatures expect (no strict_types).
     */
    private function invoke($object, $methodName, array $arguments)
    {
        $ref = new \ReflectionMethod($object, $methodName);
        $callArgs = array();

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

        return array(
            'list_languages' => array($shop, 'listLanguages'),
            'list_products' => array($product, 'listProducts'),
            'count_catalog' => array($product, 'countCatalog'),
            'get_product_details' => array($product, 'getProductDetails'),
            'update_product_seo' => array($product, 'updateProductSeo'),
            'update_image_alt' => array($product, 'updateImageAlt'),
            'list_categories' => array($category, 'listCategories'),
            'get_category_details' => array($category, 'getCategoryDetails'),
            'update_category_seo' => array($category, 'updateCategorySeo'),
            'list_cms' => array($cms, 'listCms'),
            'get_cms_details' => array($cms, 'getCmsDetails'),
            'update_cms_seo' => array($cms, 'updateCmsSeo'),
        );
    }

    private function toolList()
    {
        $int = array('type' => 'integer');
        $str = array('type' => 'string');
        $bool = array('type' => 'boolean');

        $schema = function (array $props, array $required = array()) {
            return array('type' => 'object', 'properties' => $props, 'required' => $required);
        };

        return array(
            $this->tool('list_languages', 'Get list of active languages in the shop.', $schema(array())),
            $this->tool('list_products', 'List products with pagination (id, name, meta, url, images).', $schema(array(
                'langId' => $int, 'limit' => $int, 'offset' => $int, 'onlyActive' => $bool, 'idCategoryId' => $int,
            ))),
            $this->tool('count_catalog', 'Exact counts of products, categories and CMS pages.', $schema(array('onlyActive' => $bool))),
            $this->tool('get_product_details', 'Full product details for SEO analysis.', $schema(array(
                'id_product' => $int, 'id_lang' => $int,
            ), array('id_product'))),
            $this->tool('update_product_seo', 'Update SEO fields, name and slug of a product.', $schema(array(
                'id_product' => $int, 'id_lang' => $int, 'name' => $str, 'link_rewrite' => $str, 'update_slug' => $bool,
                'description_short' => $str, 'description' => $str, 'meta_title' => $str, 'meta_description' => $str,
            ), array('id_product'))),
            $this->tool('update_image_alt', 'Update the ALT text (legend) of an image.', $schema(array(
                'id_image' => $int, 'legend' => $str, 'id_lang' => $int,
            ), array('id_image', 'legend'))),
            $this->tool('list_categories', 'List all categories (full tree).', $schema(array(
                'langId' => $int, 'limit' => $int, 'offset' => $int,
            ))),
            $this->tool('get_category_details', 'Full category details for SEO analysis.', $schema(array(
                'id_category' => $int, 'id_lang' => $int,
            ), array('id_category'))),
            $this->tool('update_category_seo', 'Update SEO fields, name and slug of a category.', $schema(array(
                'id_category' => $int, 'id_lang' => $int, 'name' => $str, 'link_rewrite' => $str, 'update_slug' => $bool,
                'description' => $str, 'meta_title' => $str, 'meta_description' => $str,
            ), array('id_category'))),
            $this->tool('list_cms', 'List CMS pages.', $schema(array(
                'langId' => $int, 'limit' => $int, 'offset' => $int,
            ))),
            $this->tool('get_cms_details', 'Full CMS page details for SEO analysis.', $schema(array(
                'id_cms' => $int, 'id_lang' => $int,
            ), array('id_cms'))),
            $this->tool('update_cms_seo', 'Update SEO fields, content and slug of a CMS page.', $schema(array(
                'id_cms' => $int, 'id_lang' => $int, 'content' => $str, 'meta_title' => $str, 'meta_description' => $str,
                'link_rewrite' => $str, 'update_slug' => $bool,
            ), array('id_cms'))),
        );
    }

    private function tool($name, $description, array $inputSchema)
    {
        return array('name' => $name, 'description' => $description, 'inputSchema' => $inputSchema);
    }

    private function initializeResult()
    {
        return array(
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => array('tools' => new \stdClass()),
            'serverInfo' => array('name' => 'Fexa AI Connector (PrestaShop 1.7)', 'version' => $this->serverVersion),
        );
    }

    private function respondResult($id, $result)
    {
        $this->send(array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result));
    }

    private function respondError($id, $code, $message, $data = null)
    {
        $error = array('code' => $code, 'message' => $message);
        if ($data !== null) {
            $error['data'] = $data;
        }
        $this->send(array('jsonrpc' => '2.0', 'id' => $id, 'error' => $error));
    }

    private function send(array $payload)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($payload);
    }
}
