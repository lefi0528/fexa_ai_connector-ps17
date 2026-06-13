<?php

namespace PrestaShop\Module\FexaAiConnector\Mcp\Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Context;
use Validate;
use PrestaShop\Module\FexaAiConnector\Helper\HtmlSanitizer;

class ProductTools
{
    public function listProducts(?int $langId = null, int $limit = 50, int $offset = 0, bool $onlyActive = true, ?int $idCategoryId = null): array
    {
        $context = Context::getContext();

        if (!$context) {
            throw new \Exception('PrestaShop Context not initialized.');
        }

        if (!$langId) {
            if (isset($context->language) && isset($context->language->id)) {
                $langId = (int)$context->language->id;
            } else {
                $langId = (int)\Configuration::get('PS_LANG_DEFAULT');
            }
        }

        if (empty($langId)) {
            throw new \Exception('Could not determine Language ID.');
        }

        $idCategory = $idCategoryId ? (int)$idCategoryId : false;
        $products = \Product::getProducts($langId, $offset, $limit, 'id_product', 'ASC', $idCategory, $onlyActive);

        if (!is_array($products)) {
            return [];
        }

        return array_map(function ($p) use ($langId, $context) {
            $images = \Image::getImages($langId, (int)$p['id_product']);
            $nbImages = is_array($images) ? count($images) : 0;
            $missingAlt = 0;
            if ($nbImages > 0) {
                foreach ($images as $img) {
                    if (empty($img['legend']) || $img['legend'] === $p['name']) {
                        $missingAlt++;
                    }
                }
            }

            return [
                'id' => (int)$p['id_product'],
                'name' => !empty($p['name']) ? $p['name'] : 'Product #' . $p['id_product'],
                'reference' => isset($p['reference']) ? $p['reference'] : '',
                'active' => (bool)$p['active'],
                'category_default' => $p['id_category_default'] ?? null,
                'nb_images' => $nbImages,
                'missing_alt' => $missingAlt,
                'description' => $p['description'] ?? '',
                'description_short' => $p['description_short'] ?? '',
                'meta_title' => $p['meta_title'] ?? '',
                'link_rewrite' => $p['link_rewrite'] ?? '',
                'url' => $context->link->getProductLink((int)$p['id_product'], $p['link_rewrite'] ?? '', $p['id_category_default'] ?? null, null, $langId),
                'manufacturer_name' => $p['manufacturer_name'] ?? '',
                'price' => isset($p['price']) ? (float)$p['price'] : 0.0,
                'date_upd' => $p['date_upd'] ?? null,
            ];
        }, $products);
    }

    public function countCatalog(bool $onlyActive = true): array
    {
        $context = Context::getContext();
        $idShop = ($context && isset($context->shop) && isset($context->shop->id))
            ? (int) $context->shop->id
            : (int) \Configuration::get('PS_SHOP_DEFAULT');

        $db = \Db::getInstance();
        $p = _DB_PREFIX_;

        $products = (int) $db->getValue(
            'SELECT COUNT(DISTINCT p.id_product) FROM `' . $p . 'product` p '
            . 'INNER JOIN `' . $p . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop
            . ($onlyActive ? ' WHERE ps.active = 1' : '')
        );

        $categories = (int) $db->getValue(
            'SELECT COUNT(DISTINCT c.id_category) FROM `' . $p . 'category` c '
            . 'INNER JOIN `' . $p . 'category_shop` cs ON cs.id_category = c.id_category AND cs.id_shop = ' . $idShop
            . ($onlyActive ? ' WHERE c.active = 1' : '')
        );

        $cms = (int) $db->getValue(
            'SELECT COUNT(DISTINCT m.id_cms) FROM `' . $p . 'cms` m '
            . 'INNER JOIN `' . $p . 'cms_shop` mshop ON mshop.id_cms = m.id_cms AND mshop.id_shop = ' . $idShop
            . ($onlyActive ? ' WHERE m.active = 1' : '')
        );

        return [
            'products' => $products,
            'categories' => $categories,
            'cms' => $cms,
        ];
    }

    public function getProductDetails(int $id_product, ?int $id_lang = null): array
    {
        $context = Context::getContext();
        $idLang = $id_lang ?? (int)$context->language->id;

        $product = new \Product($id_product, false, $idLang);

        if (!Validate::isLoadedObject($product)) {
            throw new \Exception("Product with ID $id_product not found.");
        }

        $features = \Product::getFrontFeaturesStatic($idLang, $id_product);
        $formattedFeatures = [];
        foreach ($features as $f) {
            $formattedFeatures[] = [
                'name' => $f['name'],
                'value' => $f['value'],
            ];
        }

        $attributes = $product->getAttributesGroups($idLang);
        $formattedCombinations = [];
        foreach ($attributes as $a) {
            $combId = (int)$a['id_product_attribute'];
            if (!isset($formattedCombinations[$combId])) {
                $formattedCombinations[$combId] = [
                    'id' => $combId,
                    'reference' => $a['reference'],
                    'attributes' => [],
                ];
            }
            $formattedCombinations[$combId]['attributes'][] = [
                'group' => $a['group_name'],
                'name' => $a['attribute_name'],
            ];
        }

        $category = new \Category($product->id_category_default, $idLang);
        $categoryName = Validate::isLoadedObject($category) ? $category->name : '';

        $images = \Image::getImages($idLang, $product->id);
        $formattedImages = [];
        if (is_array($images)) {
            foreach ($images as $img) {
                $imageUrl = $context->link->getImageLink($product->link_rewrite[$idLang] ?? $product->name[$idLang], $img['id_image']);
                $formattedImages[] = [
                    'id' => $img['id_image'],
                    'cover' => (bool)$img['cover'],
                    'legend' => $img['legend'],
                    'position' => $img['position'],
                    'url' => strpos($imageUrl, 'http') === 0 ? $imageUrl : 'http://' . $imageUrl,
                ];
            }
        }

        // --- Product schema enrichment: identifiers, stock, currency ---
        $quantity = (int) \StockAvailable::getQuantityAvailableByProduct((int) $product->id);
        $availableForOrder = (bool) $product->available_for_order;
        $availability = ($quantity > 0 && $availableForOrder)
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $currencyIso = '';
        try {
            $defaultCurrency = \Currency::getDefaultCurrency();
            if ($defaultCurrency && isset($defaultCurrency->iso_code)) {
                $currencyIso = (string) $defaultCurrency->iso_code;
            }
        } catch (\Exception $e) {
            // leave empty — caller omits offers without a currency
        }

        $priceTaxIncl = (float) $product->price;
        try {
            $priceTaxIncl = (float) \Product::getPriceStatic((int) $product->id, true);
        } catch (\Exception $e) {
            // fall back to the base price already assigned
        }

        // Breadcrumb trail: category ancestry + the product itself as the last node.
        $breadcrumb = $this->buildBreadcrumbTrail((int) $product->id_category_default, (int) $idLang, $context);
        $breadcrumb[] = [
            'name' => is_string($product->name) ? $product->name : '',
            'url' => $context->link->getProductLink($product, null, null, null, $idLang),
        ];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description_short' => $product->description_short,
            'description' => $product->description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'link_rewrite' => $product->link_rewrite,
            'url' => $context->link->getProductLink($product, null, null, null, $idLang),
            'reference' => $product->reference,
            'active' => (bool)$product->active,
            'manufacturer_name' => $product->manufacturer_name ?: '',
            'category_name' => $categoryName,
            'features' => $formattedFeatures,
            'combinations' => array_values($formattedCombinations),
            'nb_images' => count($formattedImages),
            'associations' => [
                'images' => $formattedImages,
            ],
            'price_tax_excl' => (float)$product->price,
            'price_tax_incl' => $priceTaxIncl,
            'currency' => $currencyIso,
            'on_sale' => (bool)$product->on_sale,
            'ean13' => isset($product->ean13) ? (string) $product->ean13 : '',
            'isbn' => isset($product->isbn) ? (string) $product->isbn : '',
            'upc' => isset($product->upc) ? (string) $product->upc : '',
            'mpn' => isset($product->mpn) ? (string) $product->mpn : '',
            'condition' => isset($product->condition) ? (string) $product->condition : '',
            'quantity' => $quantity,
            'availability' => $availability,
            'available_for_order' => $availableForOrder,
            'breadcrumb' => $breadcrumb,
        ];
    }

    /**
     * Build a breadcrumb trail (Home → … → category) as [{name, url}] from a
     * category's ancestry. Best-effort: returns [] on any failure.
     */
    private function buildBreadcrumbTrail(int $idCategory, int $idLang, $context): array
    {
        $trail = [];
        try {
            $cat = new \Category($idCategory, $idLang);
            if (!Validate::isLoadedObject($cat)) {
                return $trail;
            }
            $parents = $cat->getParentsCategories($idLang); // current → … → root
            if (!is_array($parents)) {
                return $trail;
            }
            foreach (array_reverse($parents) as $p) {
                $idc = isset($p['id_category']) ? (int) $p['id_category'] : 0;
                if ($idc <= 1) {
                    continue; // skip the technical root category
                }
                $name = isset($p['name']) ? (string) $p['name'] : '';
                if ($name === '') {
                    continue;
                }
                $trail[] = [
                    'name' => $name,
                    'url' => $context->link->getCategoryLink($idc, isset($p['link_rewrite']) ? $p['link_rewrite'] : null, $idLang),
                ];
            }
        } catch (\Exception $e) {
            // best-effort
        }

        return $trail;
    }

    public function updateProductSeo(
        int $id_product,
        ?int $id_lang = null,
        ?string $name = null,
        ?string $link_rewrite = null,
        bool $update_slug = true,
        ?string $description_short = null,
        ?string $description = null,
        ?string $meta_title = null,
        ?string $meta_description = null
    ): array {
        $context = Context::getContext();
        $id_lang = $id_lang ?? (int)$context->language->id;

        if (!$id_lang) {
            $id_lang = (int)\Configuration::get('PS_LANG_DEFAULT');
        }

        $product = new \Product($id_product);

        if (!Validate::isLoadedObject($product)) {
            throw new \Exception("Product with ID $id_product not found.");
        }

        $fieldsUpdated = [];

        $updateField = function (&$fieldArray, $newValue, $fieldName) use ($id_lang, &$fieldsUpdated) {
            if ($newValue !== null) {
                if (!is_array($fieldArray)) {
                    $fieldArray = [$id_lang => $fieldArray];
                }
                $fieldArray[$id_lang] = $newValue;
                $fieldsUpdated[] = $fieldName;
            }
        };

        // Defense in depth: clean AI content before it reaches the shop.
        if ($name !== null) $name = HtmlSanitizer::catalogName($name, 128);
        if ($description_short !== null) $description_short = HtmlSanitizer::richHtml($description_short);
        if ($description !== null) $description = HtmlSanitizer::richHtml($description);
        if ($meta_title !== null) $meta_title = HtmlSanitizer::meta($meta_title, 255);
        if ($meta_description !== null) $meta_description = HtmlSanitizer::meta($meta_description, 512);

        // URL slug: explicit value wins; otherwise derive from the (translated) name.
        // Skip writing an empty slug (e.g. fully non-latin input) to avoid breaking URLs.
        $slug = null;
        if ($link_rewrite !== null && trim($link_rewrite) !== '') {
            $slug = HtmlSanitizer::slug($link_rewrite);
        } elseif ($name !== null && $name !== '') {
            $slug = HtmlSanitizer::slug($name);
        }
        if ($slug === '' || !$update_slug) {
            $slug = null; // keep existing slug (empty/non-latin, or caller opted out)
        }

        $updateField($product->name, ($name !== null && $name !== '') ? $name : null, 'name');
        $updateField($product->link_rewrite, $slug, 'link_rewrite');
        $updateField($product->description_short, $description_short, 'description_short');
        $updateField($product->description, $description, 'description');
        $updateField($product->meta_title, $meta_title, 'meta_title');
        $updateField($product->meta_description, $meta_description, 'meta_description');

        if (empty($fieldsUpdated)) {
            return ['status' => 'no_changes', 'updated_fields' => []];
        }

        if (!$product->save()) {
            throw new \Exception("Failed to save product ID $id_product");
        }

        return [
            'status' => 'success',
            'product_id' => $id_product,
            'lang_id' => $id_lang,
            'updated_fields' => $fieldsUpdated,
        ];
    }

    public function updateImageAlt(int $id_image, string $legend, ?int $id_lang = null): array
    {
        $context = Context::getContext();
        $id_lang = $id_lang ?? (int)$context->language->id;

        if (!$id_lang) {
            $id_lang = (int)\Configuration::get('PS_LANG_DEFAULT');
        }

        $image = new \Image($id_image);
        if (!Validate::isLoadedObject($image)) {
            throw new \Exception("Image with ID $id_image not found.");
        }

        if (!is_array($image->legend)) {
            $image->legend = [$id_lang => $legend];
        } else {
            $image->legend[$id_lang] = $legend;
        }

        if (!$image->save()) {
            throw new \Exception("Failed to save image legend for ID $id_image");
        }

        return [
            'status' => 'success',
            'id_image' => $id_image,
            'id_lang' => $id_lang,
            'legend' => $legend,
        ];
    }
}
