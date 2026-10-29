/* WP TeamSphere admin: media picker, colour swatches, skill chips, live preview. */
(function ($) {
	'use strict';

	/* ---------------------------------------------------------------
	 * Media picker
	 * ------------------------------------------------------------- */
	function clearField($field) {
		$field.find('.wpts-media-id').val('');
		$field.find('.wpts-media-preview').empty();
		$field.find('.wpts-media-remove').hide();
	}

	$(document).on('click', '.wpts-media-select', function (event) {
		event.preventDefault();

		var $field = $(this).closest('.wpts-media-field');
		var frame = wp.media({
			title: wptsAdmin.chooseTitle,
			button: { text: wptsAdmin.chooseButton },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;

			$field.find('.wpts-media-id').val(attachment.id);
			$field.find('.wpts-media-preview').empty().append($('<img>', { src: url, alt: '' }));
			$field.find('.wpts-media-remove').show();
		});

		frame.open();
	});

	$(document).on('click', '.wpts-media-remove', function (event) {
		event.preventDefault();
		clearField($(this).closest('.wpts-media-field'));
	});

	// After a term is added via AJAX on the "Add new" screen, reset our image picker.
	$(document).ajaxComplete(function (event, xhr, settings) {
		if (settings && typeof settings.data === 'string' && settings.data.indexOf('action=add-tag') !== -1) {
			$('#addtag .wpts-media-field').each(function () {
				clearField($(this));
			});
		}
	});

	/* ---------------------------------------------------------------
	 * Colour swatches
	 * ------------------------------------------------------------- */
	function setColor($field, color, fromInput) {
		if (!fromInput) {
			$field.find('.wpts-color-input').val(color);
		}
		$field.find('.wpts-swatch').each(function () {
			var active = String($(this).data('color')).toLowerCase() === String(color).toLowerCase();
			$(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
		});
	}

	$(document).on('click', '.wpts-swatch', function (event) {
		event.preventDefault();
		setColor($(this).closest('.wpts-color-field'), $(this).data('color'), false);
	});

	$(document).on('input change', '.wpts-color-input', function () {
		setColor($(this).closest('.wpts-color-field'), this.value, true);
	});

	/* ---------------------------------------------------------------
	 * "Add new" term form: tuck the URL name (slug) away
	 * ------------------------------------------------------------- */
	$(function () {
		var $slug = $('#addtag .term-slug-wrap').first();
		if (!$slug.length) {
			return;
		}
		var $details = $('<details class="wpts-advanced"></details>').append($('<summary></summary>').text(wptsAdmin.advanced));
		$slug.before($details);
		$details.append($slug);
	});

	/* ---------------------------------------------------------------
	 * Skill chips
	 * ------------------------------------------------------------- */
	function chipValues($field) {
		return $field.find('.wpts-skill-chip').map(function () {
			return $(this).attr('data-value');
		}).get();
	}

	function syncChips($field) {
		$field.find('.wpts-chips-value').val(JSON.stringify(chipValues($field)));
	}

	function addChip($field, raw) {
		var name = $.trim(String(raw || '')).replace(/\s+/g, ' ').substring(0, 60);
		if (!name) {
			return;
		}

		var exists = chipValues($field).some(function (value) {
			return value.toLowerCase() === name.toLowerCase();
		});
		if (exists) {
			return;
		}

		var $item = $('<li class="wpts-skill-chip"></li>').attr('data-value', name);
		$item.append($('<span></span>').text(name));
		$item.append(
			$('<button type="button" class="wpts-chip-remove"></button>')
				.attr('aria-label', wptsAdmin.removeSkill.replace('%s', name))
				.html('&times;')
		);

		$field.find('.wpts-chip-list').append($item);
		syncChips($field);
	}

	function commitInput($field) {
		var $input = $field.find('.wpts-chip-text');
		addChip($field, $input.val());
		$input.val('');
	}

	$(document).on('click', '.wpts-chip-add', function (event) {
		event.preventDefault();
		var $field = $(this).closest('.wpts-chips-field');
		commitInput($field);
		$field.find('.wpts-chip-text').trigger('focus');
	});

	$(document).on('keydown', '.wpts-chip-text', function (event) {
		if (event.key === 'Enter' || event.key === ',') {
			event.preventDefault();
			commitInput($(this).closest('.wpts-chips-field'));
		}
	});

	$(document).on('click', '.wpts-chip-remove', function (event) {
		event.preventDefault();
		var $field = $(this).closest('.wpts-chips-field');
		$(this).closest('.wpts-skill-chip').remove();
		syncChips($field);
		$field.find('.wpts-chip-text').trigger('focus');
	});

	// Don't lose a skill that was typed but not added yet.
	$(document).on('submit', 'form#post', function () {
		$('.wpts-chips-field').each(function () {
			commitInput($(this));
		});
	});

	/* ---------------------------------------------------------------
	 * Live card preview on the team member screen
	 * ------------------------------------------------------------- */
	function firstLetter(text) {
		text = $.trim(text);
		return text ? Array.from(text)[0].toUpperCase() : '?';
	}

	function updatePreview() {
		if (!$('#wpts-preview').length) {
			return;
		}

		var name = $.trim($('#title').val());
		if (!name) {
			name = $.trim($.trim($('#wpts_field_first_name').val()) + ' ' + $.trim($('#wpts_field_last_name').val()));
		}

		$('#wpts-preview-name').text(name || wptsAdmin.previewName);
		$('#wpts-preview-job').text($.trim($('#wpts_field_job_title').val()));
		$('#wpts-preview-initial').text(firstLetter(name));

		var $option = $('#wpts_department option:selected');
		var chip = document.getElementById('wpts-preview-dept');
		var color = $option.attr('data-color');

		if (chip) {
			if ($option.val() && $option.val() !== '0' && /^#[0-9a-f]{6}$/i.test(color || '')) {
				chip.textContent = $option.text();
				chip.style.setProperty('--wpts-chip', color);
				chip.style.setProperty('--wpts-chip-bg', color + '1f');
				chip.style.display = '';
			} else {
				chip.style.display = 'none';
			}
		}
	}

	$(document).on('input change', '#title, #wpts_field_first_name, #wpts_field_last_name, #wpts_field_job_title, #wpts_department', updatePreview);
	$(updatePreview);
})(jQuery);
