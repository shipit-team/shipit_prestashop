/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

$(document).ready(function() {
    $('#alert_asm').click(function (e) {
        e.preventDefault();
        $.ajax({
            url: module_dir + 'rg_shipit/ajax-bo.php',
            type: 'POST',
            data: {secure_key: rg_shipit.secure_key},
        }).done(function(data) {
            if (data == 1) {
                $('#alert_asm').parent('div').hide();
            } else {
                alert('There was a problem trying to turn off the message');
            }
        }).fail(function() {
            alert('There was a problem trying to turn off the message');
        });
    });

    if ((typeof rg_shipit != 'undefined') && (typeof rg_shipit.id_country != 'undefined')) {
        $('select#id_country').on('change', function() {
            var id_country = $(this).val();

            if (id_country == rg_shipit.id_country) {
                var city = $('input#city').val();
                var exists = $.grep(rg_shipit.cities, function(v) {
                    return v.name === city;
                });

                if (exists.length < 1) {
                    $('input#city').val('');
                }

                $('input#city').flexdatalist({
                    data: rg_shipit.cities,
                    minLength: 1,
                    valueProperty: (exists.length ? 'name' : null),
                    searchIn: 'name',
                    selectionRequired: true,
                    searchByWord: true,
                    noResultsText: rg_shipit.texts.no_results + ' "{keyword}"',
                    debug: false
                });
            } else if ($('input#city').hasClass('flexdatalist-set')) {
                $('input#city').flexdatalist('destroy');
            }
        }).change();
    }

    /*
     * Module configuration form
     */
    var _ps_version = _PS_VERSION_.substr(0, 3);

    $('input[name="SHIPIT_ACTIVE_QUOTATION"]').on('change', function() {
        var val = parseInt($('input[name="SHIPIT_ACTIVE_QUOTATION"]:checked').val());

        $('div#fieldset_2_2').toggle(Boolean(val));
        $('div#fieldset_3_3').toggle(Boolean(val));
    }).change();

    $('select#SHIPIT_SET_DIMENSIONS').on('change', function() {
        var val = parseInt($(this).val());

        if (_ps_version >= 1.6) {
            $('div.SHIPIT_SET_VALUE_WIDTH').toggle(Boolean(val));
            $('div.SHIPIT_SET_VALUE_HEIGHT').toggle(Boolean(val));
            $('div.SHIPIT_SET_VALUE_DEPTH').toggle(Boolean(val));
        } else {
            $('#SHIPIT_SET_VALUE_WIDTH').parent('.margin-form').toggle(Boolean(val));
            $('#SHIPIT_SET_VALUE_HEIGHT').parent('.margin-form').toggle(Boolean(val));
            $('#SHIPIT_SET_VALUE_DEPTH').parent('.margin-form').toggle(Boolean(val));
        }
    }).change();

    $('select#SHIPIT_SET_WEIGHT').on('change', function() {
        var val = parseInt($(this).val());

        if (_ps_version >= 1.6) {
            $('div.SHIPIT_SET_VALUE_WEIGHT').toggle(Boolean(val));
        } else {
            $('#SHIPIT_SET_VALUE_WEIGHT').parent('.margin-form').toggle(Boolean(val));
        }
    }).change();

    $('select#SHIPIT_IMPACT_PRICE').on('change', function() {
        var val = parseInt($(this).val());

        if (_ps_version >= 1.6) {
            if (val == 1 || val == 3) {
                $('div.SHIPIT_IMPACT_PRICE_AMOUNT span.input-group-addon').text('%');
            } else {
                $('div.SHIPIT_IMPACT_PRICE_AMOUNT span.input-group-addon').text(rg_shipit.currency_sign);
            }

            $('div.SHIPIT_IMPACT_PRICE_AMOUNT').toggle(Boolean(val));
        } else {
            if (val == 1 || val == 3) {
                $('#SHIPIT_IMPACT_PRICE_AMOUNT + span').text('%');
            } else {
                $('#SHIPIT_IMPACT_PRICE_AMOUNT + span').text(rg_shipit.currency_sign);
            }

            $('#SHIPIT_IMPACT_PRICE_AMOUNT').parent('.margin-form').toggle(Boolean(val));
        }
    }).change();
});
