/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

$(document).ready(function() {

	// Prevent the events from being bound multiple times.
	if ($(this).data('multi-select-events')) {
		return;
	}
	$(this).data('multi-select-events', true);

	$(this).on('click', '.multi-select .remove-all', function(e) {
		e.preventDefault();
		var container = $(this).closest('.multi-select');
		container.find('.remove-one').trigger('click');
	});

	$(this).on('change', 'select.MultiSelectList', function() {
		MultiSelect_SelectItem($(this), $(this).children('option:selected'), null);
		return false;
	});

	$(this).on('click', 'a.MultiSelectRemove', 'click',function(e) {
		e.preventDefault();
		var anchor = $(this);
		var container = anchor.closest('.multi-select');
		var selections = container.find('.multi-select-selections');
		var noSelectionsMsg = container.find('.no-selections-msg');
		var removeAll = container.find('.remove-all');
		var input = anchor.closest('li').find('input[type="hidden"]');

		var attrs = {};
		$(input[0].attributes).each(function() {
			if (this.nodeName != 'type' && this.nodeName != 'name') {
				attrs[this.nodeName] = this.nodeValue;
			}
		});

		var text = anchor.data('text');
		var select = container.find('select');

		var attr_str = '';
		for (var key in attrs) {
			attr_str += ' ' + key + '="' + attrs[key] + '"';
		}

		select.append('<option' + attr_str + '>'+text+'</option>');
		sort_selectbox(select);

		anchor.closest('li').remove();
		input.remove();

		if (!selections.children().length) {
			selections.add(removeAll).addClass('hide');
			noSelectionsMsg.removeClass('hide');
		}

		if (container.data('show-none-placeholder') == 'yes' && selections.children().length == 1) {
			selections.children('li.MultiSelectNone').show();
		}

		if (select.hasClass('linked-fields')) {

			var fields = select.data('linked-fields').split(',');
			var values = select.data('linked-values').split(',');

			var index = $.inArray(text, values);

			if (index >= 0) {
				hide_linked_field(fields[index]);
			}
		}

		select.trigger('MultiSelectChanged');

		return false;
	});
});

function MultiSelect_SelectItem(select, selected, extra_values)
{
	if (selected.val().length >0) {
		var container = select.closest('.multi-select');
		var selections = container.find('.multi-select-selections');

		var fieldName = container.data('field-name');

		var noSelectionsMsg = container.find('.no-selections-msg');
		var removeAll = container.find('.remove-all');
		var options = container.data('options');
		if (typeof(select.data('extra-fields')) != 'undefined') {
			var extra_fields = select.data('extra-fields').split(',');
		} else {
			var extra_fields = [];
		}

		var input_class = select.data('input-class');

		if (selections.children('li').length >= parseInt(options['maxItems'])) {
			alert('You can only select a maximum of '+options['maxItems']+' item'+(options['maxItems'] == '1' ? '' : 's'));
			select.val('');
			return;
		}

		var attrs = {};
		$(selected[0].attributes).each(function() {
			attrs[this.nodeName] = this.nodeValue;
		});

		var inp_str = '<input type="hidden" name="'+fieldName+'[]"';

		if (input_class) {
			inp_str += ' class="'+input_class+'"';
		}

		for (var key in attrs) {
			inp_str += ' ' + key + '="' + attrs[key] + '"';
		}
		inp_str += ' />';

		var input = $(inp_str);

		var remote_data = {
			'href': '#',
			'class': 'MultiSelectRemove remove-one '+selected.val(),
			'text': 'Remove',
			'data-name': fieldName+'[]',
			'data-text': selected.text()
		};

		if ($(this).hasClass('linked-fields')) {
			remote_data['class'] += ' linked-fields';
			remote_data['data-linked-fields'] = $(this).data('linked-fields');
			remote_data['data-linked-values'] = $(this).data('linked-values');
		}

		var remove = $('<a />', remote_data);

		var item_text = '<li><span class="text">'+selected.text()+'</span>';

		if (extra_fields.length >0) {
			for (var i in extra_fields) {
				if (extra_values) {
					var extra_value = extra_values[i];
				} else {
					var extra_value = '';
				}

				item_text += '<input type="text" name="'+extra_fields[i]+'[]" value="' + extra_value + '"';

				if (input_class) {
					item_text += ' class="'+input_class+'"';
				}

				item_text += ' />';
			}
		}

		item_text += '</li>';

		var item = $(item_text);

		item.append(remove);
		item.append(input);

		selections
		.append(item)
		.removeClass('hide');

		if (extra_fields.length >0) {
			selections.children('li:last').find('input[type="text"]').focus();
		}

		if (container.data('show-none-placeholder') == 'yes') {
			selections.children('li.MultiSelectNone').hide();
		}

		noSelectionsMsg.addClass('hide');
		removeAll.removeClass('hide');

		selected.remove();
		select.val('');

		if (options.sorted) {
			selections.append(selections.find('li').sort(function(a,b) {
				return $.trim($(a).find('.text').text()) > $.trim($(b).find('.text').text());
			}));
		}
	}

	select.trigger('MultiSelectChanged');
}
