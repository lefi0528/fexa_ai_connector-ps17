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

class CmsTools
{
    public function listCms(?int $langId = null, int $limit = 100, int $offset = 0): array
    {
        $context = \Context::getContext();
        $idLang = $langId ?? $context->language->id;

        if (!$idLang) {
            $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        $cmsPages = \CMS::getCMSPages($idLang, null, true); // Active only

        // Apply pagination (limit and offset)
        if ($offset > 0 || count($cmsPages) > $limit) {
            $cmsPages = array_slice($cmsPages, $offset, $limit);
        }

        return array_map(function ($c) use ($idLang, $context) {
            return [
                'id' => (int) $c['id_cms'],
                'name' => !empty($c['meta_title']) ? $c['meta_title'] : 'CMS #' . $c['id_cms'],
                'meta_title' => $c['meta_title'] ?? '',
                'link_rewrite' => $c['link_rewrite'] ?? '',
                'url' => $context->link->getCMSLink((int) $c['id_cms'], $c['link_rewrite'] ?? '', null, $idLang),
                'active' => isset($c['active']) ? (bool) $c['active'] : true,
                'type' => 'cms',
            ];
        }, $cmsPages);
    }

    public function getCmsDetails(int $id_cms, ?int $id_lang = null): array
    {
        $context = \Context::getContext();
        $idLang = $id_lang ?? $context->language->id;

        $cms = new \CMS($id_cms, $idLang);

        if (!\Validate::isLoadedObject($cms)) {
            throw new \Exception("CMS page with ID $id_cms not found.");
        }

        return [
            'id' => $cms->id,
            'name' => $cms->meta_title,
            'content' => $cms->content,
            'meta_title' => $cms->meta_title,
            'meta_description' => $cms->meta_description,
            'link_rewrite' => $cms->link_rewrite,
            'url' => $context->link->getCMSLink($cms, null, null, $idLang),
            'active' => $cms->active,
            'indexation' => $cms->indexation,
        ];
    }

    public function updateCmsSeo(int $id_cms, ?int $id_lang = null, ?string $content = null, ?string $meta_title = null, ?string $meta_description = null, ?string $link_rewrite = null, bool $update_slug = true): array
    {
        $context = \Context::getContext();
        $id_lang = $id_lang ?? (int) $context->language->id;

        if (!$id_lang) {
            $id_lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        $cms = new \CMS($id_cms);

        if (!\Validate::isLoadedObject($cms)) {
            throw new \Exception("CMS with ID $id_cms not found.");
        }

        $fieldsUpdated = [];

        // Helper to update multi-lang field
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
        if ($content !== null) {
            $content = HtmlSanitizer::richHtml($content);
        }
        if ($meta_title !== null) {
            $meta_title = HtmlSanitizer::meta($meta_title, 255);
        }
        if ($meta_description !== null) {
            $meta_description = HtmlSanitizer::meta($meta_description, 512);
        }

        // URL slug: only when explicitly provided (the SaaS sends the translated title
        // here and Tools::str2url turns it into a slug). Not derived from meta_title
        // implicitly, so ordinary SEO optimization never changes existing CMS URLs.
        $slug = null;
        if ($update_slug && $link_rewrite !== null && trim($link_rewrite) !== '') {
            $slug = HtmlSanitizer::slug($link_rewrite);
            if ($slug === '') {
                $slug = null;
            }
        }

        $updateField($cms->content, $content, 'content');
        $updateField($cms->meta_title, $meta_title, 'meta_title');
        $updateField($cms->meta_description, $meta_description, 'meta_description');
        $updateField($cms->link_rewrite, $slug, 'link_rewrite');

        if (empty($fieldsUpdated)) {
            return ['status' => 'no_changes'];
        }

        if (!$cms->save()) {
            throw new \Exception("Failed to save CMS ID $id_cms");
        }

        return [
            'status' => 'success',
            'cms_id' => $id_cms,
            'lang_id' => $id_lang,
            'updated_fields' => $fieldsUpdated,
        ];
    }
}
