# Fexa AI Connector — PrestaShop 1.7 / PHP 7.4 build

Lightweight, dependency-free variant of the [Fexa AI](https://fexaai.com) connector
for older shops that **cannot run PHP 8.1** (required by the flagship build via
`php-mcp/server`).

It exposes the **same MCP endpoint, API key and tools** as the flagship, backed by
a hand-rolled JSON-RPC 2.0 handler instead of `php-mcp/server`. A shop installs
**either** this build **or** the PHP 8.1 build — never both (same module name).

## Compatibility

- **PrestaShop:** 1.7.x → 8.x
- **PHP:** 7.4+
- For **PrestaShop 9 / PHP 8.1+**, use the flagship build instead.

## Install

1. Download `fexa_ai_connector.zip` from the [latest release](../../releases/latest).
2. PrestaShop back-office → **Modules → Upload a module** → drop the zip.
3. Open the module, copy your **API key**, and paste it into your Fexa AI dashboard.

## What's inside

- `controllers/front/McpServer.php` — MCP endpoint (CORS + API-key auth).
- `src/Mcp/JsonRpcHandler.php` — hand-rolled JSON-RPC dispatcher (`initialize`,
  `ping`, `tools/list`, `tools/call`); unknown tools return `-32601`.
- `src/Mcp/Tools/*` — Product / Category / CMS / Shop tools (same logic as the
  flagship, PHP 7.4-compatible).
- `src/Helper/HtmlSanitizer.php` — defense-in-depth content sanitiser.

No Composer dependencies. No `vendor/`.

## License

Proprietary — © Fexa AI. All rights reserved.
