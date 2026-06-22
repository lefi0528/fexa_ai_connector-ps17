<?php

/**
 * Copyright (c) 2025 Fexa AI — All Rights Reserved.
 *
 * Fexa AI Connector — PrestaShop 1.7 / PHP 7.4 build.
 *
 * This is a dependency-free variant of the flagship connector for older shops
 * that cannot run PHP 8.1 (required by php-mcp/server). It exposes the SAME MCP
 * endpoint, API key and tools, backed by a hand-rolled JSON-RPC handler. A shop
 * installs EITHER this build OR the PHP 8.1 build — never both.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

class Fexa_ai_connector extends Module
{
    public $version;

    public function __construct()
    {
        $this->name = 'fexa_ai_connector';
        $this->author = 'Fexa AI';
        $this->tab = 'seo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->version = '3.6.5';

        parent::__construct();

        $this->displayName = $this->l('Fexa AI Connector (PrestaShop 1.7)');
        $this->description = $this->l('Connect your store with Fexa AI services. Build for PrestaShop 1.7 / PHP 7.4+.');
        $this->confirmUninstall = $this->l('Do you really want to uninstall Fexa AI Connector?');

        // PrestaShop 1.7.x → 8.x on PHP 7.4+. For PS 9 / PHP 8.1+, use the flagship build.
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => '8.99.99'];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('moduleRoutes')
            && $this->ensureApiKey();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName('FEXA_AI_API_KEY');
    }

    public function isMcpCompliant(): bool
    {
        return true;
    }

    public function getMultistoreCompatibility(): int
    {
        return (int) true;
    }

    public function ensureApiKey(): bool
    {
        if (!Configuration::get('FEXA_AI_API_KEY')) {
            return Configuration::updateValue('FEXA_AI_API_KEY', bin2hex(random_bytes(32)));
        }

        return true;
    }

    public function hookModuleRoutes(): array
    {
        return [
            'fexa_ai_connector-mcp-server' => [
                'controller' => 'McpServer',
                'rule' => 'mcp',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
            // Serve the shop's machine-readable AEO map at the domain root.
            'fexa_ai_connector-llms-txt' => [
                'controller' => 'llms',
                'rule' => 'llms.txt',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
        ];
    }

    public function getContent()
    {
        $apiKey = (string) Configuration::get('FEXA_AI_API_KEY');
        $safeKey = htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8');

        $intro = $this->l('Optimisez automatiquement votre boutique pour le SEO et les moteurs de réponse IA (ChatGPT, Perplexity, Google SGE) : descriptions, méta, balises ALT, traductions et fichier /llms.txt, générés en un clic.');
        $access = $this->l('Accéder à Fexa AI');
        $keyTitle = $this->l('Votre clé API');
        $keyHelp = $this->l('Copiez cette clé et collez-la dans votre tableau de bord Fexa AI pour connecter votre boutique.');
        $copy = $this->l('Copier la clé');
        $badge = $this->l('Édition PrestaShop 1.7 / PHP 7.4');

        $featTitle = $this->l('Ce que Fexa AI optimise pour vous');
        $f1t = $this->l('SEO réécrit par l\'IA');
        $f1d = $this->l('Titres, méta-descriptions, descriptions et balises ALT optimisés automatiquement.');
        $f2t = $this->l('Traductions multilingues');
        $f2d = $this->l('Tout votre catalogue traduit et optimisé pour chaque langue, en un clic.');
        $f3t = $this->l('Fichier /llms.txt (nouveau)');
        $f3d = $this->l('Une carte de votre boutique lisible par les IA, servie automatiquement à la racine (/llms.txt).');
        $f4t = $this->l('Moteurs de réponse IA');
        $f4d = $this->l('Contenu prêt pour ChatGPT, Perplexity et Google SGE — votre boutique citée par les IA.');

        return <<<HTML
<div style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:16px;padding:32px;margin-bottom:24px;color:#fff;box-shadow:0 10px 40px rgba(16,185,129,.3);">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
    <div style="flex:1;min-width:300px;">
      <h1 style="margin:0 0 8px 0;font-size:2em;font-weight:800;">🚀 Fexa AI Connector</h1>
      <span style="display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:999px;font-size:.8em;font-weight:700;margin-bottom:12px;">{$badge}</span>
      <p style="font-size:1.1em;opacity:.95;margin:8px 0 0 0;line-height:1.6;">{$intro}</p>
    </div>
    <a href="https://fexaai.com" target="_blank" rel="noopener noreferrer" style="display:inline-block;background:#fff;color:#059669;padding:16px 32px;border-radius:12px;text-decoration:none;font-weight:700;">🌐 {$access}</a>
  </div>
</div>
<div style="background:#fff;border-radius:16px;padding:28px;margin-bottom:24px;border:1px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.06);">
  <h3 style="color:#059669;margin:0 0 20px 0;">✨ {$featTitle}</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">🤖 {$f1t}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$f1d}</div>
    </div>
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">🌍 {$f2t}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$f2d}</div>
    </div>
    <div style="background:#ecfdf5;border-radius:12px;padding:18px;border:1px solid #6ee7b7;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">📄 {$f3t}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$f3d}</div>
    </div>
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">💬 {$f4t}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$f4d}</div>
    </div>
  </div>
</div>
<div style="background:#fff;border-radius:16px;padding:28px;margin-bottom:24px;border:2px solid #10b981;box-shadow:0 4px 20px rgba(0,0,0,.08);">
  <h3 style="color:#059669;margin:0 0 12px 0;">🔑 {$keyTitle}</h3>
  <p style="color:#4b5563;margin:0 0 16px 0;">{$keyHelp}</p>
  <input id="fexa-api-key" type="text" readonly value="{$safeKey}" onclick="this.select()" style="width:100%;background:#f3f4f6;padding:14px 18px;font-size:1.1em;border-radius:10px;border:1px solid #e5e7eb;font-family:monospace;color:#1f2937;box-sizing:border-box;"/>
  <button type="button" class="btn btn-primary" style="margin-top:16px;" onclick="var e=document.getElementById('fexa-api-key');e.select();document.execCommand('copy');this.innerHTML='✅';">📋 {$copy}</button>
</div>
HTML;
    }
}
