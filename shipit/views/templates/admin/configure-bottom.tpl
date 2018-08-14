{**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 *}

<div class="panel">
    <h3><i class="icon icon-link"></i> {l s='Webhook and Logs' mod='rg_shipit'}</h3>
    <ul>
        <li>
            <b>{l s='Configure this URL in your Shipit Admin Panel - API Configuration as Webhook' mod='rg_shipit'}</b>:
            <br><code>{$rg_shipit._url|escape:'html':'UTF-8'}webhook.php?secure_key={$rg_shipit.secure_key|escape:'htmlall':'UTF-8'}</code>
        </li>
        <li>
            <b>{l s='You can check error logs at' mod='rg_shipit'}</b>:
            <br><a href="{$rg_shipit._url|escape:'html':'UTF-8'}logs.php?token={$rg_shipit.secure_key|escape:'htmlall':'UTF-8'}" target="_blank">{$rg_shipit._url|escape:'html':'UTF-8'}logs.php?token={$rg_shipit.secure_key|escape:'htmlall':'UTF-8'}</a>
        </li>
    </ul>
</div>

<div class="panel">
    <h3><i class="icon icon-tags"></i> {l s='Documentation' mod='rg_shipit'}</h3>
    <p>
        &raquo; {l s='You can get PDF documentation to configure this module' mod='rg_shipit'}:
        <ul>
            {* <li><a href="{$rg_shipit._path|escape:'htmlall':'UTF-8'}docs/readme_en.pdf" target="_blank">{l s='English' mod='rg_shipit'}</a></li> *}
            <li><a href="{$rg_shipit._path|escape:'htmlall':'UTF-8'}docs/readme_es.pdf" target="_blank">{l s='Spanish' mod='rg_shipit'}</a></li>
        </ul>
    </p>
</div>
