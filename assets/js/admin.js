jQuery(document).ready(function ($) {
    var $form = $('#wpd-discount-rules-form');
    var $rulesList = $('#wpd-rules-table-container');
    var rules = [];

    function loadAttributeValues() {
        var attribute = $('#wpd-attribute').val();
        if (!attribute) {
            $('#wpd-value').html('<option value="">Select an attribute first</option>');
            return;
        }
        $.ajax({
            url: wpd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpd_get_attribute_values',
                attribute: attribute,
                nonce: wpd_ajax.nonce
            },
            beforeSend: function () {
                $('#wpd-value').html('<option value="">Loading...</option>');
            },
            success: function (response) {
                if (response.success) {
                    $('#wpd-value').html(response.data);
                } else {
                    console.error('Error loading attribute values:', response.data);
                    $('#wpd-value').html('<option>Error loading values</option>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                $('#wpd-value').html('<option>Error loading values</option>');
            }
        });
    }

    $('#wpd-attribute').change(loadAttributeValues);

    function autoSelectFirstAttribute() {
        var $firstAttribute = $('#wpd-attribute option:first');
        if ($firstAttribute.length) {
            $firstAttribute.prop('selected', true);
            loadAttributeValues();
        }
    }

    function renderRulesList() {
        var html = '<table class="wp-list-table widefat fixed striped">' +
            '<thead><tr><th>Attribute</th><th>Value</th><th>Discount</th><th>Action</th></tr></thead>' +
            '<tbody>';
        if (rules.length === 0) {
            html += '<tr><td colspan="4">No rules added yet.</td></tr>';
        } else {
            rules.forEach(function (rule, index) {
                html += '<tr>' +
                    '<td>' + rule.attribute + '</td>' +
                    '<td>' + (rule.value.label || rule.value) + '</td>' +
                    '<td>$' + rule.discount + '</td>' +
                    '<td><button class="button button-small wpd-delete-rule" data-index="' + index + '">Delete</button></td>' +
                    '</tr>';
            });
        }
        html += '</tbody></table>';
        $rulesList.html(html);
    }

    $form.submit(function (e) {
        e.preventDefault();
        var newRule = {
            attribute: $('#wpd-attribute').val(),
            value: {
                slug: $('#wpd-value').val(),
                label: $('#wpd-value option:selected').text()
            },
            discount: parseFloat($('#wpd-discount').val())
        };
        rules.push(newRule);
        renderRulesList();
        $form[0].reset();
        autoSelectFirstAttribute();
        showMessage('Rule added successfully', 'success');
        saveAllRules();
    });

    $rulesList.on('click', '.wpd-delete-rule', function (e) {
        e.preventDefault();
        var index = $(this).data('index');
        rules.splice(index, 1);
        renderRulesList();
        showMessage('Rule deleted successfully', 'success');
        saveAllRules();
    });

    function saveAllRules() {
        $.ajax({
            url: wpd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpd_save_discount_rules',
                rules: JSON.stringify(rules),
                nonce: wpd_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    showMessage('Rules saved successfully!', 'success');
                } else {
                    showMessage('Error saving rules. Please try again.', 'error');
                }
            },
            error: function () {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });
    }

    function loadExistingRules() {
        $.ajax({
            url: wpd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wpd_get_discount_rules',
                nonce: wpd_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    rules = response.data;
                    renderRulesList();
                } else {
                    showMessage('Error loading rules. Please refresh the page.', 'error');
                }
            },
            error: function () {
                showMessage('An error occurred while loading rules. Please refresh the page.', 'error');
            }
        });
    }

    function showMessage(message, type) {
        var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wpd-admin-container').before($message);
        setTimeout(function () {
            $message.fadeOut(function () {
                $(this).remove();
            });
        }, 3000);
    }

    autoSelectFirstAttribute();
    loadExistingRules();
});