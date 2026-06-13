<?php

/**
 * Copyright (c) 2025 Fexa AI
 *
 * All Rights Reserved.
 */

namespace PrestaShop\Module\FexaAiConnector\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Defense-in-depth sanitiser for content written by the update_*_seo tools.
 *
 * The Fexa AI SaaS already sanitises HTML before sending, but cleaning here too
 * means the module never writes unsafe markup to the shop regardless of caller.
 * Uses PrestaShop's bundled HTMLPurifier (Tools::purifyHTML) for rich HTML.
 */
class HtmlSanitizer
{
    /** Sanitise rich HTML (description / content) — strips scripts/styles, balances tags. */
    public static function richHtml($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        return trim((string) \Tools::purifyHTML($html));
    }

    /** Plain text + length cap for meta / short fields (HTML in meta breaks save()). */
    public static function meta($text, $maxLen)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        if (function_exists('mb_strlen')) {
            return mb_strlen($text) > $maxLen ? trim(mb_substr($text, 0, $maxLen)) : $text;
        }

        return strlen($text) > $maxLen ? trim(substr($text, 0, $maxLen)) : $text;
    }

    /**
     * Plain product/category name: strip tags, remove characters PrestaShop's
     * Validate::isCatalogName forbids (<>;=#{}), and cap length. Keeps save() from
     * rejecting AI-translated names.
     */
    public static function catalogName($text, $maxLen = 128)
    {
        $text = self::meta($text, $maxLen);
        $text = preg_replace('/[<>;=#{}]/u', '', $text);

        return trim((string) $text);
    }

    /**
     * URL slug (link_rewrite) from arbitrary text using PrestaShop's locale-aware
     * Tools::str2url. Returns '' when the input has no URL-safe characters (e.g. a
     * fully non-latin string and PS_ALLOW_ACCENTED_CHARS_URL disabled) so the caller
     * can decide to keep the existing slug rather than write an empty one.
     */
    public static function slug($text)
    {
        $slug = (string) \Tools::str2url((string) $text);

        return \Validate::isLinkRewrite($slug) ? $slug : '';
    }
}
