<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

?>
<?php
$form = $this->beginWidget('BaseEventTypeCActiveForm', array(
	'id'=>'adminform',
	'enableAjaxValidation'=>false,
	'focus'=>'#username',
	'layoutColumns'=>array(
		'label' => 2,
		'field' => 4
	)
))?>
<div class="box admin">
	<h2>Drug Sets</h2>
	<?php echo $this->renderPartial('_form_errors',array('errors'=>$errors))?>

	<div class="field-row row">
		<div class="large-3 column">
			<label for="subspecialty_id">Sub Specialty:</label>
		</div>
		<div class="large-3 column end">
			<?= CHtml::dropDownList('subspecialty_id', $subspecialty_id, CHtml::listData(Subspecialty::model()->findAll(), 'id', 'name', 'specialty.name'), array('empty' => '-- Select --')); ?>
		</div>
	</div>

	<div <?php if (empty($subspecialty_id)) { ?> style="display: none;" <?php } ?>>

	<div class="row field-row">
		<div class="large-3 column">
			<label for="drug_set_id">Set Name:</label>
		</div>
		<div class="large-3 column end">
			<?php echo CHtml::dropDownList('drug_set_id', $drug_set_id, CHtml::listData($drug_sets, 'id', 'name'), array('empty' => '-- Select --')); ?>
		</div>
	</div>

	<div <?php if (empty($drug_set_id)) { ?> style="display: none;" <?php } ?>>

	<div class="row field-row">
		<div class="large-6 column">
			<fieldset class="row field-row">
				<legend class="large-4 column">
					Add Item
				</legend>
				<div class="large-6 column">
					<div class="field-row">
						<?php
						$this->widget('zii.widgets.jui.CJuiAutoComplete', array(
							'name' => 'drug_id',
							'id' => 'autocomplete_drug_id',
							'source' => "js:function(request, response) {
									$.getJSON('".$this->createUrl('DrugList')."', {
										term : request.term,
										type_id: $('#drug_type_id').val(),
										preservative_free: ($('#preservative_free').is(':checked') ? '1' : ''),
									}, response);
								}",
							'options' => array(
								'select' => "js:function(event, ui) {
										addItem(ui.item.value, ui.item.id);
										$(this).val('');
										return false;
									}",
							),
							'htmlOptions' => array(
								'placeholder' => 'or search formulary',
							)
						));?>
					</div>
				</div>

			</fieldset>
		</div>
		<div class="large-6 column">

		</div>
	</div>




	<input type="hidden" name="drug_set_items_valid" value="1" />
	<table class="drug_sets" id="drug_set_items">
		<thead>
		<tr>
			<th>Drug</th>
			<th>Dose</th>
			<th>Options</th>
			<th>Frequency</th>
			<th></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($drug_set as $key => $item) {
			$this->renderDrugSetItem($key, $item);
		} ?>
		</tbody>
	</table>
	</div>

		<button class="small primary event-action" name="save" type="submit" id="et_save">Save</button>

</div>


	<?php $this->endWidget()?>

	<script type="text/javascript">
	// Disable currently added drugs in dropdown
	$('#drug_set_items input[name$="[drug_id]"]').each(function(index) {
		var option = $('#common_drug_id option[value="' + $(this).val() + '"]');
		if (option) {
			option.data('used', true);
		}
	});
	applyFilter();

	$('body').delegate('#subspecialty_id', 'change', 	function () { this.form.submit();	});
	$('body').delegate('#drug_set_id', 'change', 	function () { this.form.submit();	});

	$('body').delegate('#common_drug_id', 'change', function() {
		var selected = $(this).children('option:selected');
		if (selected.val().length) {
			addItem(selected.text(), selected.val());
			$(this).val('');
		}
		return false;
	});

	$('body').delegate('#drug_set_id', 'change', function() {
		var selected = $(this).children('option:selected');
		if (selected.val().length) {
			addSet(selected.val());
			$(this).val('');
		}
		return false;
	});

	$('body').delegate('#repeat_drug_set', 'click', function() {
		addRepeat();
		return false;
	});

	$('body').delegate('#clear_drug_set', 'click', function() {
		$('#drug_set_items tbody tr').remove();
		$('#common_drug_id option').data('used', false);
		applyFilter();
		return false;
	});

	$('body').delegate('select.drugRoute', 'change', function() {
		var selected = $(this).children('option:selected');
		if (selected.val().length) {
			var options_td = $(this).parent().next();
			var key = $(this).closest('tr').attr('data-key');
			$.get(baseUrl+"/admin//RouteOptions", { key: key, route_id: selected.val() }, function(data) {
				options_td.html(data);
			});
		}
		return false;
	});

	$('#drug_set_items').delegate('a.removeItem', 'click', function() {
		var row =  $(this).closest('tr');
		var drug_id = row.find('input[name$="[drug_id]"]').first().val();
		var key = row.attr('data-key');
		$('#drug_set_items tr[data-key="'+key+'"]').remove();
		decorateRows();
		var option = $('#common_drug_id option[value="' + drug_id + '"]');
		if (option) {
			option.data('used', false);
			applyFilter();
		}
		return false;
	});

	$('#drug_set_items').delegate('a.taperItem:not(.processing)', 'click', function() {
		var row = $(this).closest('tr');
		var key = row.attr('data-key');
		var last_row = $('#drug_set_items tr[data-key="'+key+'"]').last();
		var taper_key = (last_row.attr('data-taper')) ? parseInt(last_row.attr('data-taper')) + 1 : 0;

		// Clone item fields to create taper row
		var dose_input = row.find('td.drugSetItemDose input').first().clone();
		dose_input.attr('name', dose_input.attr('name').replace(/\[dose\]/, "[taper]["+taper_key+"][dose]"));
		dose_input.attr('id', dose_input.attr('id').replace(/_dose$/, "_taper_"+taper_key+"_dose"));
		var frequency_input = row.find('td.drugSetItemFrequencyId select').first().clone();
		frequency_input.attr('name', frequency_input.attr('name').replace(/\[frequency_id\]/, "[taper]["+taper_key+"][frequency_id]"));
		frequency_input.attr('id', frequency_input.attr('id').replace(/_frequency_id$/, "_taper_"+taper_key+"_frequency_id"));
		frequency_input.val(row.find('td.drugSetItemFrequencyId select').val());
		var duration_input = row.find('td.drugSetItemDurationId select').first().clone();
		duration_input.attr('name', duration_input.attr('name').replace(/\[duration_id\]/, "[taper]["+taper_key+"][duration_id]"));
		duration_input.attr('id', duration_input.attr('id').replace(/_duration_id$/, "_taper_"+taper_key+"_duration_id"));
		duration_input.val(row.find('td.drugSetItemDurationId select').val());

		// Insert taper row
		var odd_even = (row.hasClass('odd')) ? 'odd' : 'even';
		var new_row = $('<tr data-key="'+key+'" data-taper="'+taper_key+'" class="drug_set-tapier '+odd_even+'"></tr>');
		new_row.append($('<td class="drug_set-label"><span>then</span></td>'), $('<td></td>').append(dose_input), $('<td></td>').append(frequency_input), $('<td></td>').append(duration_input), $('<td class="drugSetItemActions"><a class="removeTaper"	href="#">Remove</a></td>'));
		last_row.after(new_row);

		return false;
	});

	// Remove taper from item
	$('#drug_set_items').delegate('a.removeTaper', 'click', function() {
		var row =  $(this).closest('tr');
		row.remove();
		return false;
	});

	// Apply selected drug filter
	$('body').delegate('.drugFilter', 'change', function() {
		applyFilter();
		return false;
	});

	// Add repeat to drug_set
	function addRepeat()
	{
		$.get(baseUrl+"/admin//RepeatForm", { key: getNextKey(), patient_id: OE_patient_id }, function(data) {
			$('#drug_set_items').append(data);
			decorateRows();
			markUsed();
			applyFilter();
		});
	}

	// Add set to drug_set
	function addSet(set_id)
	{
		$.get(baseUrl+"/admin//SetForm", { key: getNextKey(), patient_id: OE_patient_id, set_id: set_id }, function(data) {
			$('#drug_set_items').append(data);
			decorateRows();
			markUsed();
			applyFilter();
		});
	}

	// Add item to drug_set
	function addItem(label, item_id)
	{
		$.get(baseUrl+"/admin//ItemForm", { key: getNextKey(), patient_id: OE_patient_id, drug_id: item_id }, function(data){
			$('#drug_set_items').append(data);
			decorateRows();
		});
		var option = $('#common_drug_id option[value="' + item_id + '"]');
		if (option) {
			option.data('used', true);
			applyFilter();
		}
	}

	// Mark used common drugs
	function markUsed()
	{
		$('#drug_set_items input[name$="\[drug_id\]"]').each(function(index) {
			var option = $('#common_drug_id option[value="' + $(this).val() + '"]');
			if (option) {
				option.data('used', true);
			}
		});
	}

	// Filter drug choices
	function applyFilter()
	{
		var filter_type_id = $('#drug_type_id').val();
		var filter_preservative_free = $('#preservative_free').is(':checked');
		$('#common_drug_id option').each(function() {
			var show = true;
			var drug_id = $(this).val();
			if (drug_id) {
				if (filter_type_id && common_drug_metadata[drug_id].type_id != filter_type_id) {
					show = false;
				}
				if (filter_preservative_free && common_drug_metadata[drug_id].preservative_free == '0') {
					show = false;
				}
				if (show) {
					$(this).removeAttr("disabled");
				} else {
					$(this).attr("disabled", "disabled");
				}
			}
		});
	}

	// Fix odd/even classes on all rows
	function decorateRows()
	{
		$('#drug_set_items .drugSetItem').each(function(i) {
			if (i % 2) {
				$(this).removeClass('even').addClass('odd');
			} else {
				$(this).removeClass('odd').addClass('even');
			}
			var key = $(this).attr('data-key');
			$('#drug_set_items .drug_setTaper[data-key="'+key+'"]').each(function() {
				if (i % 2) {
					$(this).removeClass('even').addClass('odd');
				} else {
					$(this).removeClass('odd').addClass('even');
				}
			});
		});
	}

	// Get next key for adding rows
	function getNextKey()
	{
		var last_item = $('#drug_set_items .drugSetItem').last();
		return (last_item.attr('data-key')) ? parseInt(last_item.attr('data-key')) + 1 : 0;
	}

	</script>



