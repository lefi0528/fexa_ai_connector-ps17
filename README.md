# Fexa AI Connector — PrestaShop 1.7 / PHP 7.4 build

Lightweight, dependency-free variant of the [Fexa AI](https://fexaai.com) connector
for older shops that **cannot run PHP 8.1** (required by the flagship build via
`php-mcp/server`).

It exposes the **same MCP endpoint, API key and tools** as the flagship, backed by
a hand-rolled JSON-RPC 2.0 handler instead of `php-mcp/server`. A shop installs
**either** this build **or** the PHP 8.1 build — never both (same module name).

## Compatibility

- **PrestaShop:** 1.7.x → 8.x
- **PHP:** 7.4+ recommended. Since v3.8.1 the code also parses on PHP 7.1 → 7.3 (older 1.7.x hosts).
- For **PrestaShop 8.1+ / 9 on PHP 8.1+**, use the flagship build instead (both on [fexaai.com/connect](https://fexaai.com/connect)).

## Install and connect (about 3 minutes)

The module's back-office screens are in French; button labels are quoted as they appear.

1. Download `fexa_ai_connector.zip` (PrestaShop 1.7 edition) from [fexaai.com/connect](https://fexaai.com/connect) or the [latest release](../../releases/latest).
2. PrestaShop back-office → **Modules → Upload a module** → drop the zip, then open the module's **Configure** page.
3. Click **« Connecter à Fexa AI »**, then **« Ouvrir Fexa AI pour finaliser »**: Fexa AI opens with the security code already in the link — nothing to retype. Sign in (or create your free account) and confirm. The link is valid for 15 minutes.
4. Back on the module page, a green banner **« Boutique connectée à Fexa AI — dernier échange il y a … »** appears once Fexa AI has exchanged with the shop.

If the connection cannot start, the module explains the cause (DNS, firewall, SSL certificate, shop
address not reachable from the Internet, too many attempts…) and what to do, with a **« Réessayer »**
button; the failure is also written to *Advanced Parameters › Logs*, without any secret.

**Fallback only:** the folded *« Connexion manuelle (avancé) »* block shows the 64-character API key,
to paste in Fexa AI when the one-click connection is not possible on the server.

## What's inside

- `controllers/front/McpServer.php` — MCP endpoint (CORS + API-key auth, key accepted in
  `Authorization: Bearer` / `X-MCP-API-KEY` headers only). Requests carrying the valid key still get
  through when the shop is in maintenance mode or behind a country restriction; everyone else sees the
  standard PrestaShop page.
- `src/Mcp/JsonRpcHandler.php` — hand-rolled JSON-RPC dispatcher (`initialize`,
  `ping`, `tools/list`, `tools/call`); unknown tools return `-32601`.
- `src/Mcp/Tools/*` — Product / Category / CMS / Shop / **Llms** tools (same logic
  as the flagship, PHP 7.4-compatible).
- `controllers/front/llms.php` — serves the AI-readable **`/llms.txt`** at the
  domain root (new in **v3.6.0**), stored via the `set_llms_txt` tool in
  Configuration (no extra table). Answers the Lighthouse "Agentic Browsing" audit.
- `src/Helper/HtmlSanitizer.php` — defense-in-depth content sanitiser.

No Composer dependencies. No `vendor/`.

## License

Proprietary — © Fexa AI. All rights reserved.
