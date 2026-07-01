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
        $this->version = '3.6.6';

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
        $this->context->smarty->assign([
            'fexa_badge' => $this->l('Édition PrestaShop 1.7 / PHP 7.4'),
            'fexa_intro' => $this->l('Optimisez automatiquement votre boutique pour le SEO et les moteurs de réponse IA (ChatGPT, Perplexity, Google SGE) : descriptions, méta, balises ALT, traductions et fichier /llms.txt, générés en un clic.'),
            'fexa_access' => $this->l('Accéder à Fexa AI'),
            'fexa_feat_title' => $this->l('Ce que Fexa AI optimise pour vous'),
            'fexa_f1t' => $this->l('SEO réécrit par l\'IA'),
            'fexa_f1d' => $this->l('Titres, méta-descriptions, descriptions et balises ALT optimisés automatiquement.'),
            'fexa_f2t' => $this->l('Traductions multilingues'),
            'fexa_f2d' => $this->l('Tout votre catalogue traduit et optimisé pour chaque langue, en un clic.'),
            'fexa_f3t' => $this->l('Fichier /llms.txt (nouveau)'),
            'fexa_f3d' => $this->l('Une carte de votre boutique lisible par les IA, servie automatiquement à la racine (/llms.txt).'),
            'fexa_f4t' => $this->l('Moteurs de réponse IA'),
            'fexa_f4d' => $this->l('Contenu prêt pour ChatGPT, Perplexity et Google SGE — votre boutique citée par les IA.'),
            'fexa_key_title' => $this->l('Votre clé API'),
            'fexa_key_help' => $this->l('Copiez cette clé et collez-la dans votre tableau de bord Fexa AI pour connecter votre boutique.'),
            'fexa_copy' => $this->l('Copier la clé'),
            'fexa_api_key' => (string) Configuration::get('FEXA_AI_API_KEY'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
}
