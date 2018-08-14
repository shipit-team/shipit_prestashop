{**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 *}

<script type="text/javascript">
    var rg_shipit = {
            secure_key: "{$rg_shipit.secure_key|escape:'htmlall':'UTF-8'}",
            page_name: "{$rg_shipit.page_name|escape:'htmlall':'UTF-8'}",
            ps_version: "{$rg_shipit.ps_version|escape:'htmlall':'UTF-8'}",
            cities: JSON.parse('{$rg_shipit.cities}'),
            id_country: "{$rg_shipit.id_country|escape:'htmlall':'UTF-8'}",
            currency_sign: "{$rg_shipit.currency_sign|escape:'htmlall':'UTF-8'}",
            texts: JSON.parse('{$rg_shipit.texts}')
        };
</script>
