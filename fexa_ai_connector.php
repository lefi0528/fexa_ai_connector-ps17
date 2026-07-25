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
        $this->version = '3.7.0';

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

    const FEXA_APP_BASE_URL = 'https://fexaai.com';

    public function getContent()
    {
        // One-click assisted connection: send this shop's URL + API key to Fexa AI, which returns a
        // short verification code and a claim link the merchant follows to finish binding the shop.
        $fexaConnect = null;
        if (Tools::isSubmit('submitFexaConnect')) {
            $fexaConnect = $this->pairWithFexa();
        }

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
            'fexa_connect_title' => $this->l('Connectez votre boutique en 1 clic'),
            'fexa_connect_intro' => $this->l('Cliquez ci-dessous : Fexa AI vous renverra un code de vérification à saisir sur son site pour finaliser. Aucune clé à copier-coller.'),
            'fexa_connect_btn' => $this->l('Connecter à Fexa AI'),
            'fexa_connect_code_help' => $this->l('Ouvrez Fexa AI avec le bouton ci-dessous, puis saisissez ce code de vérification pour finaliser la connexion :'),
            'fexa_connect_finish' => $this->l('Ouvrir Fexa AI pour finaliser'),
            'fexa_connect_err' => $this->l('La connexion automatique n\'est pas disponible pour le moment. Utilisez votre clé API ci-dessous pour connecter votre boutique manuellement.'),
            'fexa_connect_manual_hint' => $this->l('Connexion manuelle (avancé)'),
            'fexa_connect_code' => (isset($fexaConnect['code']) ? (string) $fexaConnect['code'] : ''),
            'fexa_connect_url' => (isset($fexaConnect['claimUrl']) ? (string) $fexaConnect['claimUrl'] : ''),
            'fexa_connect_error' => (isset($fexaConnect['error']) && $fexaConnect['error']),
            'fexa_key_title' => $this->l('Votre clé API'),
            'fexa_key_help' => $this->l('Copiez cette clé et collez-la dans votre tableau de bord Fexa AI pour connecter votre boutique.'),
            'fexa_copy' => $this->l('Copier la clé'),
            'fexa_api_key' => (string) Configuration::get('FEXA_AI_API_KEY'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Start a one-click pairing with Fexa AI. Sends this shop's URL + API key over a verified TLS
     * connection to the public pairing endpoint and returns ['code' => ..., 'claimUrl' => ...] on
     * success, or ['error' => true] on any failure (the merchant then falls back to the manual
     * API-key card). The API key never appears in a URL — only in the request body over HTTPS.
     */
    private function pairWithFexa()
    {
        $this->ensureApiKey();
        $apiKey = (string) Configuration::get('FEXA_AI_API_KEY');
        $shopUrl = (string) Tools::getShopDomainSsl(true);
        if ($apiKey === '' || $shopUrl === '') {
            return ['error' => true];
        }

        $payload = json_encode([
            'shopUrl' => $shopUrl,
            'apiKey' => $apiKey,
            'psVersion' => _PS_VERSION_,
            'moduleVersion' => $this->version,
        ]);

        $ch = curl_init(self::FEXA_APP_BASE_URL . '/api/connect/pair/init');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $status !== 200) {
            return ['error' => true];
        }
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['code']) || empty($data['claimUrl'])) {
            return ['error' => true];
        }
        return ['code' => (string) $data['code'], 'claimUrl' => (string) $data['claimUrl']];
    }
}
