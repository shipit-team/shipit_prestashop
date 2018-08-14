/**
 * Integral logistics for eCommerce with pickup or fulfillment through Shipit
 *
 * @author    Rolige <www.rolige.com>
 * @copyright 2011-2018 Rolige - All Rights Reserved
 * @license   Proprietary and confidential
 */

var _rg_shipit = {
    peru_id_country: (
        typeof rg_serpost !== 'undefined' ? rg_serpost.id_country : 0
    ),
    onCountryChange: function() {
        if (rg_shipit.ps_version < '1.7') {
            return _rg_shipit._onCountryChange();
        } else {
            $('input[name="city"]').waitUntilExists(function() {
                return _rg_shipit._onCountryChange();
            });
        }
    },
    _onCountryChange: function() {
        var id_country = $('select[name="id_country"]').val();

        if (id_country == rg_shipit.id_country) {
            var city = $('input[name="city"]').val(),
                exists = $.grep(rg_shipit.cities, function(v) {
                    return v.name === city;
                });

            if (exists.length < 1) {
                $('input[name="city"]').val('');
                
                if (rg_shipit.ps_version == '1.6') {
                    $('input[name="city"]').parent('div').removeClass('form-ok').addClass('form-error');
                }
            }

            $('input[name="city"]').flexdatalist({
                data: rg_shipit.cities,
                minLength: 1,
                valueProperty: (exists.length ? 'name' : null),
                searchIn: 'name',
                selectionRequired: true,
                searchByWord: true,
                noResultsText: rg_shipit.texts.no_results + ' "{keyword}"',
                debug: false
            });
        } else if ($('input[name="city"]').hasClass('flexdatalist-set') && id_country != _rg_shipit.peru_id_country) {
            $('input[name="city"]').flexdatalist('destroy');
        }
    },
    ready: function() {
        if (typeof rg_shipit.id_country != 'undefined') {
            $(window).on('load', function() {
                _rg_shipit.onCountryChange();
            });

            $('select[name="id_country"]').on('change', function() {
                _rg_shipit.onCountryChange();
            });

            if (rg_shipit.ps_version == '1.7') {
                $('select[name="id_country"]').change();
            }

            if (rg_shipit.ps_version == '1.6') {
                $(document).on('focusout', 'input[name="flexdatalist-city"]', function() {
                    var city = $('input[name="city"]').val(),
                        haserror = $('input[name="city"]').parent('div').hasClass('form-error');

                    if ((city.length > 2) && haserror) {
                        $('input[name="city"]').parent('div').removeClass('form-error').addClass('form-ok');
                    }
                });
            }
        }
    }
};

$(document).ready(_rg_shipit.ready);

// PrestaShop 1.5 & 1.6, standard account creation and address creation.
$(document).ajaxComplete(function(event, xhr, settings) {
    if (rg_shipit.ps_version < '1.7') {
        if (typeof rg_shipit.id_country != 'undefined') {
            var element = event.currentTarget.activeElement.id,
                error = typeof xhr.responseJSON != 'undefined' ? xhr.responseJSON.hasError : false;

            // If "SubmitCreate" button is submited to get "Create an account" form by ajax.
            if ((element == 'authentication') && (error == false) && (rg_shipit.page_name == 'authentication')) {
                $('select[name="id_country"]').waitUntilExists(function() {
                    _rg_shipit.ready();
                });
            }
        }
    }
});
