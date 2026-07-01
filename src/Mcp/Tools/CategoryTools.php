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

namespace PrestaShop\Module\FexaAiConnector\Mcp\Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\FexaAiConnector\Helper\HtmlSanitizer;

class CategoryTools
{
    public function listCategories(?int $langId = null, int $limit = 200, int $offset = 0): array
    {
        $context = \Context::getContext();

        if (!$context) {
            throw new \Exception('PrestaShop Context not initialized.');
        }

        if (!$langId) {
            if (isset($context->language) && isset($context->language->id)) {
                $langId = (int) $context->language->id;
            } else {
                $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
            }
        }

        if (empty($langId)) {
            throw new \Exception('Could not determine Language ID.');
        }

        // Flat SQL query to get ALL categories at all depths (excluding root and home)
        $sql = 'SELECT c.id_category, cl.name, cl.link_rewrite, c.active, c.level_depth,
                    (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'category_product cp WHERE cp.id_category = c.id_category) as product_count
                FROM ' . _DB_PREFIX_ . 'category c
                INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl
                    ON c.id_category = cl.id_category AND cl.id_lang = ' . (int) $langId . '
                WHERE c.active = 1
                    AND c.level_depth > 1
                ORDER BY c.level_depth ASC, cl.name ASC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $rows = \Db::getInstance()->executeS($sql);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(function ($c) use ($langId, $context) {
            return [
                'id' => (int) $c['id_category'],
                'name' => !empty($c['name']) ? $c['name'] : 'Category #' . $c['id_category'],
                'link_rewrite' => $c['link_rewrite'] ?? '',
                'active' => (bool) $c['active'],
                'level_depth' => (int) $c['level_depth'],
                'productCount' => (int) $c['product_count'],
                'url' => $context->link->getCategoryLink((int) $c['id_category'], $c['link_rewrite'] ?? '', $langId),
            ];
        }, $rows);
    }

    public function getCategoryDetails(int $id_category, ?int $id_lang = null): array
    {
        $context = \Context::getContext();
        $idLang = $id_lang ?? $context->language->id;

        $category = new \Category($id_category, $idLang);

        if (!\Validate::isLoadedObject($category)) {
            throw new \Exception("Category with ID $id_category not found.");
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'link_rewrite' => $category->link_rewrite,
            'url' => $context->link->getCategoryLink($category, null, $idLang),
            'active' => $category->active,
            'level_depth' => $category->level_depth,
            'id_parent' => $category->id_parent,
            'nb_products' => (int) $category->getProducts($idLang, 1, 1, null, null, true),
            'has_image' => file_exists(_PS_CAT_IMG_DIR_ . (int) $category->id . '.jpg'),
            'breadcrumb' => $this->buildBreadcrumbTrail((int) $category->id, (int) $idLang, $context),
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
            if (!\Validate::isLoadedObject($cat)) {
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

    public function updateCategorySeo(
        int $id_category,
        ?int $id_lang = null,
        ?string $name = null,
        ?string $link_rewrite = null,
        bool $update_slug = true,
        ?string $description = null,
        ?string $meta_title = null,
        ?string $meta_description = null
    ): array {
        $context = \Context::getContext();
        $id_lang = $id_lang ?? (int) $context->language->id;

        if (!$id_lang) {
            $id_lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        $category = new \Category($id_category);

        if (!\Validate::isLoadedObject($category)) {
            throw new \Exception("Category with ID $id_category not found.");
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
        if ($name !== null) {
            $name = HtmlSanitizer::catalogName($name, 128);
        }
        if ($description !== null) {
            $description = HtmlSanitizer::richHtml($description);
        }
        if ($meta_title !== null) {
            $meta_title = HtmlSanitizer::meta($meta_title, 255);
        }
        if ($meta_description !== null) {
            $meta_description = HtmlSanitizer::meta($meta_description, 512);
        }

        // URL slug: explicit wins; else derive from the (translated) name. Skip empty.
        $slug = null;
        if ($link_rewrite !== null && trim($link_rewrite) !== '') {
            $slug = HtmlSanitizer::slug($link_rewrite);
        } elseif ($name !== null && $name !== '') {
            $slug = HtmlSanitizer::slug($name);
        }
        if ($slug === '' || !$update_slug) {
            $slug = null; // keep existing slug (empty/non-latin, or caller opted out)
        }

        $updateField($category->name, ($name !== null && $name !== '') ? $name : null, 'name');
        $updateField($category->link_rewrite, $slug, 'link_rewrite');
        $updateField($category->description, $description, 'description');
        $updateField($category->meta_title, $meta_title, 'meta_title');
        $updateField($category->meta_description, $meta_description, 'meta_description');

        if (empty($fieldsUpdated)) {
            return ['status' => 'no_changes', 'updated_fields' => []];
        }

        if (!$category->save()) {
            throw new \Exception("Failed to save category ID $id_category");
        }

        return [
            'status' => 'success',
            'category_id' => $id_category,
            'lang_id' => $id_lang,
            'updated_fields' => $fieldsUpdated,
        ];
    }
}
