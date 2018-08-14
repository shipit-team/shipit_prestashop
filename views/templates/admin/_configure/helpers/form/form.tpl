{**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 *}

{extends file="helpers/form/form.tpl"}

{block name="field"}
    {if $smarty.const._PS_VERSION_ < 1.6}
        <div class="margin-form{if isset($input.form_group_class)} {$input.form_group_class|escape:'htmlall':'UTF-8'}{/if}">
        {block name="input"}
        {if $input.type == 'text' || $input.type == 'tags'}
            {if isset($input.lang) AND $input.lang}
                <div class="translatable">
                    {foreach $languages as $language}
                        <div class="lang_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if}; float: left;">
                            {if $input.type == 'tags'}
                                {literal}
                                <script type="text/javascript">
                                    $().ready(function () {
                                        var input_id = '{/literal}{if isset($input.id)}{$input.id}_{$language.id_lang}{else}{$input.name}_{$language.id_lang}{/if}{literal}';
                                        $('#'+input_id).tagify({delimiters: [13,44], addTagPrompt: '{/literal}{l s='Add tag' js=1}{literal}'});
                                        $({/literal}'#{$table}{literal}_form').submit( function() {
                                            $(this).find('#'+input_id).val($('#'+input_id).tagify('serialize'));
                                        });
                                    });
                                </script>
                                {/literal}
                            {/if}
                            {assign var='value_text' value=$fields_value[$input.name][$language.id_lang]}
                            <input type="text"
                                    name="{$input.name}_{$language.id_lang}"
                                    id="{if isset($input.id)}{$input.id}_{$language.id_lang}{else}{$input.name}_{$language.id_lang}{/if}"
                                    value="{if isset($input.string_format) && $input.string_format}{$value_text|string_format:$input.string_format|escape:'htmlall':'UTF-8'}{else}{$value_text|escape:'htmlall':'UTF-8'}{/if}"
                                    class="{if $input.type == 'tags'}tagify {/if}{if isset($input.class)}{$input.class}{/if}"
                                    {if isset($input.size)}size="{$input.size}"{/if}
                                    {if isset($input.maxlength)}maxlength="{$input.maxlength}"{/if}
                                    {if isset($input.readonly) && $input.readonly}readonly="readonly"{/if}
                                    {if isset($input.disabled) && $input.disabled}disabled="disabled"{/if}
                                    {if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
                            {if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
                        </div>
                    {/foreach}
                </div>
            {else}
                {if isset($input.prefix)}<span>{$input.prefix|escape:'htmlall':'UTF-8'}</span>{/if}
                {if $input.type == 'tags'}
                    {literal}
                    <script type="text/javascript">
                        $().ready(function () {
                            var input_id = '{/literal}{if isset($input.id)}{$input.id}{else}{$input.name}{/if}{literal}';
                            $('#'+input_id).tagify({delimiters: [13,44], addTagPrompt: '{/literal}{l s='Add tag'}{literal}'});
                            $({/literal}'#{$table}{literal}_form').submit( function() {
                                $(this).find('#'+input_id).val($('#'+input_id).tagify('serialize'));
                            });
                        });
                    </script>
                    {/literal}
                {/if}
                {assign var='value_text' value=$fields_value[$input.name]}
                <input type="text"
                        name="{$input.name}"
                        id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
                        value="{if isset($input.string_format) && $input.string_format}{$value_text|string_format:$input.string_format|escape:'htmlall':'UTF-8'}{else}{$value_text|escape:'htmlall':'UTF-8'}{/if}"
                        class="{if $input.type == 'tags'}tagify {/if}{if isset($input.class)}{$input.class}{/if}"
                        {if isset($input.size)}size="{$input.size}"{/if}
                        {if isset($input.maxlength)}maxlength="{$input.maxlength}"{/if}
                        {if isset($input.class)}class="{$input.class}"{/if}
                        {if isset($input.readonly) && $input.readonly}readonly="readonly"{/if}
                        {if isset($input.disabled) && $input.disabled}disabled="disabled"{/if}
                        {if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
                {if isset($input.suffix)}<span>{$input.suffix|escape:'htmlall':'UTF-8'}</span>{/if}
                {if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
            {/if}
        {elseif $input.type == 'select'}
            {if isset($input.options.query) && !$input.options.query && isset($input.empty_message)}
                {$input.empty_message}
                {$input.required = false}
                {$input.desc = null}
            {else}
                <select name="{$input.name}" class="{if isset($input.class)}{$input.class}{/if}"
                        id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
                        {if isset($input.multiple)}multiple="multiple" {/if}
                        {if isset($input.size)}size="{$input.size}"{/if}
                        {if isset($input.onchange)}onchange="{$input.onchange}"{/if}>
                    {if isset($input.options.default)}
                        <option value="{$input.options.default.value}">{$input.options.default.label}</option>
                    {/if}
                    {if isset($input.options.optiongroup)}
                        {foreach $input.options.optiongroup.query AS $optiongroup}
                            <optgroup label="{$optiongroup[$input.options.optiongroup.label]}">
                                {foreach $optiongroup[$input.options.options.query] as $option}
                                    <option value="{$option[$input.options.options.id]}"
                                        {if isset($input.multiple)}
                                            {foreach $fields_value[$input.name] as $field_value}
                                                {if $field_value == $option[$input.options.options.id]}selected="selected"{/if}
                                            {/foreach}
                                        {else}
                                            {if $fields_value[$input.name] == $option[$input.options.options.id]}selected="selected"{/if}
                                        {/if}
                                    >{$option[$input.options.options.name]}</option>
                                {/foreach}
                            </optgroup>
                        {/foreach}
                    {else}
                        {foreach $input.options.query AS $option}
                            {if is_object($option)}
                                <option value="{$option->$input.options.id}"
                                    {if isset($input.multiple)}
                                        {foreach $fields_value[$input.name] as $field_value}
                                            {if $field_value == $option->$input.options.id}
                                                selected="selected"
                                            {/if}
                                        {/foreach}
                                    {else}
                                        {if $fields_value[$input.name] == $option->$input.options.id}
                                            selected="selected"
                                        {/if}
                                    {/if}
                                >{$option->$input.options.name}</option>
                            {elseif $option == "-"}
                                <option value="">-</option>
                            {else}
                                <option value="{$option[$input.options.id]}"
                                    {if isset($input.multiple)}
                                        {foreach $fields_value[$input.name] as $field_value}
                                            {if $field_value == $option[$input.options.id]}
                                                selected="selected"
                                            {/if}
                                        {/foreach}
                                    {else}
                                        {if $fields_value[$input.name] == $option[$input.options.id]}
                                            selected="selected"
                                        {/if}
                                    {/if}
                                >{$option[$input.options.name]}</option>

                            {/if}
                        {/foreach}
                    {/if}
                </select>
                {if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
            {/if}
        {elseif $input.type == 'radio'}
            {foreach $input.values as $value}
                <input type="radio" name="{$input.name}" id="{$value.id}" value="{$value.value|escape:'htmlall':'UTF-8'}"
                        {if $fields_value[$input.name] == $value.value}checked="checked"{/if}
                        {if isset($input.disabled) && $input.disabled}disabled="disabled"{/if} />
                <label {if isset($input.class)}class="{$input.class}"{/if} for="{$value.id}">
                 {if isset($input.is_bool) && $input.is_bool == true}
                    {if $value.value == 1}
                        <img src="../img/admin/enabled.gif" alt="{$value.label}" title="{$value.label}" />
                    {else}
                        <img src="../img/admin/disabled.gif" alt="{$value.label}" title="{$value.label}" />
                    {/if}
                 {else}
                    {$value.label}
                 {/if}
                </label>
                {if isset($input.br) && $input.br}<br />{/if}
                {if isset($value.p) && $value.p}<p>{$value.p}</p>{/if}
            {/foreach}
        {elseif $input.type == 'textarea'}
            {if isset($input.lang) AND $input.lang}
                <div class="translatable">
                    {foreach $languages as $language}
                        <div class="lang_{$language.id_lang}" id="{$input.name}_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if}; float: left;">
                            <textarea cols="{$input.cols}" rows="{$input.rows}" name="{$input.name}_{$language.id_lang}" {if isset($input.autoload_rte) && $input.autoload_rte}class="rte autoload_rte {if isset($input.class)}{$input.class}{/if}"{/if} >{$fields_value[$input.name][$language.id_lang]|escape:'htmlall':'UTF-8'}</textarea>
                        </div>
                    {/foreach}
                </div>
            {else}
                <textarea name="{$input.name}" id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}" cols="{$input.cols}" rows="{$input.rows}" {if isset($input.autoload_rte) && $input.autoload_rte}class="rte autoload_rte {if isset($input.class)}{$input.class}{/if}"{/if}>{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}</textarea>
            {/if}
        {elseif $input.type == 'checkbox'}
            {foreach $input.values.query as $value}
                {assign var=id_checkbox value=$input.name|cat:'_'|cat:$value[$input.values.id]}
                <input type="checkbox"
                    name="{$id_checkbox}"
                    id="{$id_checkbox}"
                    class="{if isset($input.class)}{$input.class}{/if}"
                    {if isset($value.val)}value="{$value.val|escape:'htmlall':'UTF-8'}"{/if}
                    {if isset($fields_value[$id_checkbox]) && $fields_value[$id_checkbox]}checked="checked"{/if} />
                <label for="{$id_checkbox}" class="t"><strong>{$value[$input.values.name]}</strong></label><br />
            {/foreach}
        {elseif $input.type == 'file'}
            {if isset($input.display_image) && $input.display_image}
                {if isset($fields_value[$input.name].image) && $fields_value[$input.name].image}
                    <div id="image">
                        {$fields_value[$input.name].image}
                        <p align="center">{l s='File size'} {$fields_value[$input.name].size}{l s='kb'}</p>
                        <a href="{$current}&{$identifier}={$form_id}&token={$token}&deleteImage=1">
                            <img src="../img/admin/delete.gif" alt="{l s='Delete'}" /> {l s='Delete'}
                        </a>
                    </div><br />
                {/if}
            {/if}
            
            {if isset($input.lang) AND $input.lang}
                <div class="translatable">
                    {foreach $languages as $language}
                        <div class="lang_{$language.id_lang}" id="{$input.name}_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if}; float: left;">
                            <input type="file" name="{$input.name}_{$language.id_lang}" {if isset($input.id)}id="{$input.id}_{$language.id_lang}"{/if} />
            
                        </div>
                    {/foreach}
                </div>
            {else}
                <input type="file" name="{$input.name}" {if isset($input.id)}id="{$input.id}"{/if} />
            {/if}
            {if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
        {elseif $input.type == 'password'}
            <input type="password"
                    name="{$input.name}"
                    size="{$input.size}"
                    class="{if isset($input.class)}{$input.class}{/if}"
                    value=""
                    {if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
        {elseif $input.type == 'birthday'}
            {foreach $input.options as $key => $select}
                <select name="{$key}" class="{if isset($input.class)}{$input.class}{/if}">
                    <option value="">-</option>
                    {if $key == 'months'}
                        {foreach $select as $k => $v}
                            <option value="{$k}" {if $k == $fields_value[$key]}selected="selected"{/if}>{l s=$v}</option>
                        {/foreach}
                    {else}
                        {foreach $select as $v}
                            <option value="{$v}" {if $v == $fields_value[$key]}selected="selected"{/if}>{$v}</option>
                        {/foreach}
                    {/if}

                </select>
            {/foreach}
        {elseif $input.type == 'group'}
            {assign var=groups value=$input.values}
            {include file='helpers/form/form_group.tpl'}
        {elseif $input.type == 'shop'}
            {$input.html}
        {elseif $input.type == 'categories'}
            {include file='helpers/form/form_category.tpl' categories=$input.values}
        {elseif $input.type == 'categories_select'}
            {$input.category_tree}
        {elseif $input.type == 'asso_shop' && isset($asso_shop) && $asso_shop}
                {$asso_shop}
        {elseif $input.type == 'color'}
            <input type="color"
                size="{$input.size}"
                data-hex="true"
                {if isset($input.class)}class="{$input.class}"
                {else}class="color mColorPickerInput"{/if}
                name="{$input.name}"
                value="{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}" />
        {elseif $input.type == 'date'}
            <input type="text"
                size="{$input.size}"
                data-hex="true"
                {if isset($input.class)}class="{$input.class}"
                {else}class="datepicker"{/if}
                name="{$input.name}"
                value="{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}" />
        {elseif $input.type == 'free'}
            {$fields_value[$input.name]}
        {/if}
        {if isset($input.required) && $input.required && $input.type != 'radio'} <sup>*</sup>{/if}
        {/block}{* end block input *}
        {block name="description"}
            {if isset($input.desc) && !empty($input.desc)}
                <p class="preference_description">
                    {if is_array($input.desc)}
                        {foreach $input.desc as $p}
                            {if is_array($p)}
                                <span id="{$p.id}">{$p.text}</span><br />
                            {else}
                                {$p}<br />
                            {/if}
                        {/foreach}
                    {else}
                        {$input.desc}
                    {/if}
                </p>
            {/if}
        {/block}
        {if isset($input.lang) && isset($languages)}<div class="clear"></div>{/if}
        </div>
        <div class="clear"></div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}{* end block field *}
