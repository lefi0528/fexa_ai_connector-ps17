{*
 * Copyright (c) 2025 Fexa AI
 *
 * All Rights Reserved.
 *
 * This module is proprietary software owned by Fexa AI.
 *
 * @author    Fexa AI <support@fexaai.com>
 * @copyright 2025 Fexa AI
 * @license   Proprietary
 *}
<div style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:16px;padding:32px;margin-bottom:24px;color:#fff;box-shadow:0 10px 40px rgba(16,185,129,.3);">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
    <div style="flex:1;min-width:300px;">
      <h1 style="margin:0 0 8px 0;font-size:2em;font-weight:800;">🚀 Fexa AI Connector</h1>
      <span style="display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:999px;font-size:.8em;font-weight:700;margin-bottom:12px;">{$fexa_badge|escape:'html':'UTF-8'}</span>
      <p style="font-size:1.1em;opacity:.95;margin:8px 0 0 0;line-height:1.6;">{$fexa_intro|escape:'html':'UTF-8'}</p>
    </div>
    <a href="https://fexaai.com" target="_blank" rel="noopener noreferrer" style="display:inline-block;background:#fff;color:#059669;padding:16px 32px;border-radius:12px;text-decoration:none;font-weight:700;">🌐 {$fexa_access|escape:'html':'UTF-8'}</a>
  </div>
</div>
<div style="background:#fff;border-radius:16px;padding:28px;margin-bottom:24px;border:3px solid #10b981;box-shadow:0 8px 30px rgba(16,185,129,.18);">
  <h3 style="color:#059669;margin:0 0 12px 0;font-size:1.4em;">🔗 {$fexa_connect_title|escape:'html':'UTF-8'}</h3>
  {if $fexa_connect_code}
    <p style="color:#4b5563;margin:0 0 16px 0;line-height:1.6;">{$fexa_connect_code_help|escape:'html':'UTF-8'}</p>
    {* PRIMARY action: the big button opens Fexa and finalizes. The code below is only typed on that
       page — it must NOT be pasted into Fexa's API-key field (the #1 connect mistake). *}
    <a href="{$fexa_connect_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="display:block;text-align:center;font-size:1.35em;font-weight:800;padding:22px;border-radius:12px;">🚀 {$fexa_connect_finish|escape:'html':'UTF-8'}</a>
    <div style="margin-top:22px;padding-top:18px;border-top:1px dashed #e5e7eb;">
      <div style="font-size:.85em;color:#6b7280;margin-bottom:8px;">{$fexa_connect_code_label|escape:'html':'UTF-8'}</div>
      <div style="font-family:monospace;font-size:1.5em;font-weight:700;letter-spacing:.3em;color:#374151;text-align:center;">{$fexa_connect_code|escape:'html':'UTF-8'}</div>
      <div style="margin-top:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:.88em;color:#92400e;">⚠️ {$fexa_connect_code_warn|escape:'html':'UTF-8'}</div>
    </div>
  {elseif $fexa_connect_error}
    <div style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;">⚠️ {$fexa_connect_err|escape:'html':'UTF-8'}</div>
  {else}
    <p style="color:#4b5563;margin:0 0 16px 0;line-height:1.6;">{$fexa_connect_intro|escape:'html':'UTF-8'}</p>
    <form method="post" action="">
      <button type="submit" name="submitFexaConnect" value="1" class="btn btn-primary" style="font-size:1.25em;font-weight:800;padding:20px 36px;">🔗 {$fexa_connect_btn|escape:'html':'UTF-8'}</button>
    </form>
  {/if}
</div>
<div style="background:#fff;border-radius:16px;padding:28px;margin-bottom:24px;border:1px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.06);">
  <h3 style="color:#059669;margin:0 0 20px 0;">✨ {$fexa_feat_title|escape:'html':'UTF-8'}</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">🤖 {$fexa_f1t|escape:'html':'UTF-8'}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$fexa_f1d|escape:'html':'UTF-8'}</div>
    </div>
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">🌍 {$fexa_f2t|escape:'html':'UTF-8'}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$fexa_f2d|escape:'html':'UTF-8'}</div>
    </div>
    <div style="background:#ecfdf5;border-radius:12px;padding:18px;border:1px solid #6ee7b7;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">📄 {$fexa_f3t|escape:'html':'UTF-8'}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$fexa_f3d|escape:'html':'UTF-8'}</div>
    </div>
    <div style="background:#f0fdf4;border-radius:12px;padding:18px;border:1px solid #bbf7d0;">
      <div style="font-weight:700;color:#065f46;margin-bottom:6px;">💬 {$fexa_f4t|escape:'html':'UTF-8'}</div>
      <div style="color:#4b5563;font-size:.95em;line-height:1.5;">{$fexa_f4d|escape:'html':'UTF-8'}</div>
    </div>
  </div>
</div>
<details{if $fexa_connect_error} open{/if} style="background:#fff;border-radius:16px;margin-bottom:24px;border:1px solid #e5e7eb;box-shadow:0 2px 10px rgba(0,0,0,.04);">
  <summary style="cursor:pointer;padding:22px 28px;font-weight:700;color:#6b7280;">🔧 {$fexa_key_advanced_label|escape:'html':'UTF-8'}</summary>
  <div style="padding:0 28px 28px 28px;">
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;font-size:.9em;color:#92400e;margin-bottom:16px;">⚠️ {$fexa_key_advanced_note|escape:'html':'UTF-8'}</div>
    <p style="color:#4b5563;margin:0 0 12px 0;">{$fexa_key_help|escape:'html':'UTF-8'}</p>
    <input id="fexa-api-key" type="text" readonly value="{$fexa_api_key|escape:'html':'UTF-8'}" onclick="this.select()" style="width:100%;background:#f3f4f6;padding:14px 18px;font-size:1.1em;border-radius:10px;border:1px solid #e5e7eb;font-family:monospace;color:#1f2937;box-sizing:border-box;"/>
    <button type="button" class="btn btn-primary" style="margin-top:16px;" onclick="var e=document.getElementById('fexa-api-key');e.select();document.execCommand('copy');this.innerHTML='✅';">📋 {$fexa_copy|escape:'html':'UTF-8'}</button>
  </div>
</details>
