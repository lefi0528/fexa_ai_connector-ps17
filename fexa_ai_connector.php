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
        $this->version = '3.8.1';

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
            // A reinstall generates a new API key: the old "connected" status must not survive it.
            && Configuration::deleteByName('FEXA_AI_LAST_SEEN')
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
        // claim link (carrying the security code) the merchant follows to finish binding the shop.
        $fexaConnect = null;
        if (Tools::isSubmit('submitFexaConnect')) {
            $fexaConnect = $this->pairWithFexa();
        }

        // Closed loop: McpServer stamps FEXA_AI_LAST_SEEN whenever Fexa AI authenticates.
        $fexaConnectedLabel = $this->getConnectedLabel();

        $this->context->smarty->assign([
            'fexa_badge' => $this->l('Édition PrestaShop 1.7 / PHP 7.4'),
            'fexa_intro' => $this->l('Optimisez automatiquement votre boutique pour le SEO et les moteurs de réponse IA (ChatGPT, Perplexity, Google SGE) : descriptions, méta, balises ALT, traductions et fichier /llms.txt, générés en un clic.'),
            'fexa_access' => $this->l('Accéder à Fexa AI'),
            'fexa_dashboard_url' => self::FEXA_APP_BASE_URL . '/dashboard',
            'fexa_connected' => ('' !== $fexaConnectedLabel),
            'fexa_connected_label' => $fexaConnectedLabel,
            'fexa_dashboard_btn' => $this->l('Ouvrir mon tableau de bord'),
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
            'fexa_connect_intro' => $this->l('Connectez votre boutique en 1 clic : cliquez sur le bouton, puis confirmez sur Fexa AI. Aucune clé à copier.'),
            'fexa_connect_btn' => ('' !== $fexaConnectedLabel) ? $this->l('Connecter à un compte Fexa AI') : $this->l('Connecter à Fexa AI'),
            'fexa_connect_code_help' => $this->l('Cliquez sur « Ouvrir Fexa AI pour finaliser » : le code de sécurité est transmis automatiquement, vous n\'avez rien à recopier. Lien valable 15 minutes.'),
            'fexa_connect_finish' => $this->l('Ouvrir Fexa AI pour finaliser'),
            'fexa_connect_code_label' => $this->l('Code de sécurité (pour vérification) :'),
            'fexa_connect_code_warn' => $this->l('Ne collez ce code nulle part : il est déjà dans le lien.'),
            'fexa_connect_err' => (isset($fexaConnect['message']) ? (string) $fexaConnect['message'] : ''),
            'fexa_connect_retry' => $this->l('Réessayer'),
            'fexa_connect_code' => (isset($fexaConnect['code']) ? (string) $fexaConnect['code'] : ''),
            'fexa_connect_url' => (isset($fexaConnect['claimUrl']) ? (string) $fexaConnect['claimUrl'] : ''),
            'fexa_connect_error' => (isset($fexaConnect['error']) && $fexaConnect['error']),
            'fexa_key_advanced_label' => $this->l('Connexion manuelle (avancé) — clé API à 64 caractères'),
            'fexa_key_advanced_note' => $this->l('À utiliser seulement si la connexion en 1 clic ne fonctionne pas. La clé ci-dessous fait 64 caractères — ce n\'est PAS le code à 6 caractères.'),
            'fexa_key_help' => $this->l('Copiez cette clé (64 caractères) et collez-la dans le champ « Clé API » de votre tableau de bord Fexa AI.'),
            'fexa_copy' => $this->l('Copier la clé'),
            'fexa_api_key' => (string) Configuration::get('FEXA_AI_API_KEY'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Start a one-click pairing with Fexa AI. Sends this shop's base URL + API key over a verified TLS
     * connection to the public pairing endpoint and returns ['code' => ..., 'claimUrl' => ...] on
     * success, or ['error' => <cause>, 'message' => <what to do>] on failure (the merchant can retry,
     * or fall back to the manual API-key block). The API key never appears in a URL or in a log — only
     * in the request body over HTTPS.
     */
    private function pairWithFexa()
    {
        $shopUrl = $this->getShopBaseUrl();
        if (!function_exists('curl_init')) {
            return $this->pairingFailure('curl_missing', 0, 0, $shopUrl);
        }
        $this->ensureApiKey();
        $apiKey = (string) Configuration::get('FEXA_AI_API_KEY');
        if ($apiKey === '' || $shopUrl === '') {
            return $this->pairingFailure('config', 0, 0, $shopUrl);
        }

        $fields = [
            'shopUrl' => $shopUrl,
            'apiKey' => $apiKey,
            'psVersion' => _PS_VERSION_,
            'moduleVersion' => $this->version,
            // The server caps it at 32 characters; some distribution builds report longer strings.
            'phpVersion' => substr(PHP_VERSION, 0, 32),
        ];
        $uiLang = $this->getEmployeeIsoCode();
        if ($uiLang !== '') {
            $fields['uiLang'] = $uiLang;
        }

        $ch = curl_init(self::FEXA_APP_BASE_URL . '/api/connect/pair/init');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $errno = (int) curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $errno !== 0) {
            return $this->pairingFailure($this->curlFailureCause($errno), 0, $errno, $shopUrl);
        }
        $data = json_decode((string) $resp, true);
        if ($status !== 200) {
            $apiError = (is_array($data) && isset($data['error']) && is_string($data['error'])) ? $data['error'] : '';

            return $this->pairingFailure($this->httpFailureCause($status, $apiError), $status, 0, $shopUrl);
        }
        if (!is_array($data) || empty($data['ok']) || empty($data['code']) || empty($data['claimUrl'])) {
            return $this->pairingFailure('bad_response', $status, 0, $shopUrl);
        }

        return ['code' => (string) $data['code'], 'claimUrl' => (string) $data['claimUrl']];
    }

    /**
     * Public base URL of this shop WITH its sub-folder (physical + virtual URI) and no trailing slash,
     * e.g. https://example.com/boutique — the address Fexa AI will call. Arguments are explicit: the
     * default of $auto_secure_mode differs between cores (false on 1.7.x, true on 8.x / 9.x).
     */
    private function getShopBaseUrl(): string
    {
        $shop = $this->context->shop;
        $url = $shop->getBaseURL(true, true);
        if (!$url) {
            // No domain set on the shop object: previous rule (PS_SSL_ENABLED) plus the sub-folder.
            $url = Tools::getShopDomainSsl(true) . $shop->getBaseURI();
        }

        return rtrim((string) $url, '/');
    }

    /** ISO 639-1 code of the logged-in employee's language ('' when unknown — the field is optional). */
    private function getEmployeeIsoCode(): string
    {
        if (!isset($this->context->employee->id_lang)) {
            return '';
        }
        $iso = strtolower((string) Language::getIsoById((int) $this->context->employee->id_lang));

        return preg_match('/^[a-z]{2}$/', $iso) ? $iso : '';
    }

    /** Failure cause of a cURL error (numbers rather than CURLE_* constants, not all defined on every PHP). */
    private function curlFailureCause(int $errno): string
    {
        if ($errno === 6) { // CURLE_COULDNT_RESOLVE_HOST
            return 'dns';
        }
        if ($errno === 7) { // CURLE_COULDNT_CONNECT
            return 'refused';
        }
        if ($errno === 28) { // CURLE_OPERATION_TIMEDOUT
            return 'timeout';
        }
        // SSL/TLS handshake, certificate and CA bundle errors.
        if (in_array($errno, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91], true)) {
            return 'ssl';
        }

        return 'network';
    }

    /** Failure cause of a non-200 answer of /api/connect/pair/init. */
    private function httpFailureCause(int $status, string $apiError): string
    {
        if ($status === 429) {
            return 'rate_limited';
        }
        if ($status === 400) {
            return in_array($apiError, ['invalid_url', 'blocked_url'], true) ? 'unreachable_url' : 'invalid_request';
        }
        if ($status === 404) {
            return 'disabled';
        }

        return $status >= 500 ? 'server' : 'unexpected';
    }

    /**
     * Precise, actionable message for a failed pairing attempt, plus a trace in the PrestaShop logs
     * (Advanced Parameters > Logs). The log carries the cause, HTTP status, cURL error number, shop URL
     * and versions only — never the API key, the security code or the claim link.
     */
    private function pairingFailure(string $cause, int $httpStatus, int $curlErrno, string $shopUrl): array
    {
        switch ($cause) {
            case 'curl_missing':
                $message = $this->l('L\'extension PHP cURL n\'est pas activée sur votre serveur : le module ne peut pas contacter Fexa AI. Demandez à votre hébergeur d\'activer l\'extension « curl » de PHP, ou utilisez la connexion manuelle ci-dessous.');
                break;
            case 'config':
                $message = $this->l('Le module n\'a pas pu déterminer l\'adresse de votre boutique ou préparer sa clé API. Vérifiez l\'URL de votre boutique (Paramètres de la boutique › Trafic et SEO), puis réessayez.');
                break;
            case 'dns':
                $message = $this->l('Votre serveur n\'arrive pas à trouver l\'adresse de Fexa AI (échec de la résolution DNS de fexaai.com). Demandez à votre hébergeur de vérifier la configuration DNS du serveur, puis réessayez. En attendant, la connexion manuelle ci-dessous fonctionne.');
                break;
            case 'refused':
                $message = $this->l('Votre serveur n\'a pas pu se connecter à Fexa AI (connexion refusée ou bloquée vers fexaai.com). Un pare-feu bloque probablement les connexions sortantes : demandez à votre hébergeur d\'autoriser les connexions HTTPS sortantes (port 443) vers fexaai.com, puis réessayez. En attendant, la connexion manuelle ci-dessous fonctionne.');
                break;
            case 'timeout':
                $message = $this->l('Fexa AI n\'a pas répondu à temps (délai de 15 secondes dépassé). Réessayez dans quelques instants. Si cela se reproduit, un pare-feu ou un proxy filtre peut-être les connexions sortantes de votre serveur vers fexaai.com : signalez-le à votre hébergeur, ou utilisez la connexion manuelle ci-dessous.');
                break;
            case 'ssl':
                $message = $this->l('La connexion sécurisée (SSL) vers fexaai.com a échoué : votre serveur n\'a pas pu vérifier le certificat (certificats racines absents ou périmés, ou horloge du serveur déréglée). Demandez à votre hébergeur de mettre à jour les certificats racines du serveur (paquet ca-certificates ou réglage curl.cainfo de PHP), puis réessayez. En attendant, la connexion manuelle ci-dessous fonctionne.');
                break;
            case 'network':
                $message = sprintf($this->l('La communication entre votre serveur et Fexa AI a échoué (erreur réseau cURL n° %d). Réessayez dans quelques instants ; si cela se reproduit, contactez votre hébergeur ou utilisez la connexion manuelle ci-dessous.'), $curlErrno);
                break;
            case 'rate_limited':
                $message = $this->l('Trop de tentatives de connexion en peu de temps. Patientez une dizaine de minutes, puis cliquez sur « Réessayer » (ou utilisez la connexion manuelle ci-dessous).');
                break;
            case 'unreachable_url':
                $message = sprintf($this->l('L\'adresse de votre boutique n\'est pas accessible depuis Internet (adresse envoyée : %s). Fexa AI ne peut connecter qu\'une boutique en ligne : une boutique installée en local (localhost, adresse IP privée ou réseau interne) ne peut pas être connectée. Vérifiez l\'URL de votre boutique (Paramètres de la boutique › Trafic et SEO), puis réessayez.'), $shopUrl);
                break;
            case 'invalid_request':
                $message = $this->l('Fexa AI a refusé la demande de connexion (données invalides). Téléchargez la dernière version du module sur https://fexaai.com/connect, ou utilisez la connexion manuelle ci-dessous.');
                break;
            case 'disabled':
                $message = $this->l('La connexion automatique est momentanément désactivée chez Fexa AI. Utilisez la connexion manuelle ci-dessous (clé API à 64 caractères), ou réessayez plus tard.');
                break;
            case 'server':
                $message = sprintf($this->l('Fexa AI rencontre un problème temporaire (erreur serveur %d). Réessayez dans quelques minutes ; si cela persiste, utilisez la connexion manuelle ci-dessous.'), $httpStatus);
                break;
            case 'unexpected':
                $message = sprintf($this->l('Réponse inattendue de Fexa AI (code HTTP %d). Réessayez dans quelques instants ; si cela persiste, utilisez la connexion manuelle ci-dessous.'), $httpStatus);
                break;
            default: // bad_response
                $message = $this->l('Réponse inattendue de Fexa AI. Réessayez dans quelques instants ; si cela persiste, utilisez la connexion manuelle ci-dessous.');
        }

        try {
            PrestaShopLogger::addLog(
                'Fexa AI Connector : échec de la connexion 1-clic (cause=' . $cause
                . ', http=' . $httpStatus . ', curl_errno=' . $curlErrno . ', boutique=' . $shopUrl
                . ', module=' . $this->version . ', ps=' . _PS_VERSION_ . ', php=' . PHP_VERSION . ')',
                2,
                $httpStatus > 0 ? $httpStatus : $curlErrno,
                'Module',
                (int) $this->id,
                true
            );
        } catch (Throwable $e) {
            // Logging must never turn a connection failure into a fatal error.
        }

        return ['error' => $cause, 'message' => $message];
    }

    /**
     * "Boutique connectée à Fexa AI — dernier échange il y a X min/h/j" when Fexa AI authenticated on
     * this shop's MCP endpoint within the last 30 days (FEXA_AI_LAST_SEEN, stamped by the McpServer
     * controller at most once an hour), '' otherwise.
     */
    private function getConnectedLabel(): string
    {
        $lastSeen = (int) Configuration::getGlobalValue('FEXA_AI_LAST_SEEN');
        if ($lastSeen <= 0) {
            return '';
        }
        $elapsed = max(0, time() - $lastSeen);
        if ($elapsed >= 30 * 86400) {
            return '';
        }
        if ($elapsed < 3600) {
            return sprintf($this->l('Boutique connectée à Fexa AI — dernier échange il y a %d min'), max(1, (int) floor($elapsed / 60)));
        }
        if ($elapsed < 86400) {
            return sprintf($this->l('Boutique connectée à Fexa AI — dernier échange il y a %d h'), (int) floor($elapsed / 3600));
        }

        return sprintf($this->l('Boutique connectée à Fexa AI — dernier échange il y a %d j'), (int) floor($elapsed / 86400));
    }
}
