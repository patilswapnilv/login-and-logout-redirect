(function( $ ) {
	'use strict';

	/**
	 * Login and Logout Redirect - Enhanced Admin JavaScript
	 * Provides URL validation, real-time feedback, and accessibility enhancements
	 */

	// Initialize when DOM is ready
	$(function() {
		initRedirectSettings();
	});

	/**
	 * Initialize the redirect settings functionality
	 */
	function initRedirectSettings() {
		// Add real-time URL validation
		$('#login_redirect_url, #logout_redirect_url').each(function() {
			var $input = $(this);
			var $wrapper = $input.closest('.field-input-wrapper');

			// Add validation on input
			$input.on('input', function() {
				validateUrl($input, $wrapper);
			});

			// Add validation on blur (more thorough)
			$input.on('blur', function() {
				validateUrl($input, $wrapper, true);
			});

			// Initial validation
			validateUrl($input, $wrapper);
		});

		// Add URL preview functionality
		addUrlPreview();

		// Add accessibility enhancements
		addAccessibilityFeatures();
	}

	/**
	 * Validate URL input and provide visual feedback
	 * @param {jQuery} $input - The input element to validate
	 * @param {jQuery} $wrapper - The wrapper element for styling
	 * @param {boolean} thorough - Whether to perform thorough validation
	 */
	function validateUrl($input, $wrapper, thorough = false) {
		var value = $input.val().trim();
		var isValid = true;
		var $description = $input.siblings('.field-description');

		// Remove existing validation classes
		$input.removeClass('valid invalid');
		$wrapper.removeClass('has-success has-error');

		// Empty value is valid (uses default behavior)
		if (value === '') {
			$input.addClass('valid');
			$wrapper.addClass('has-success');
			updateDescription($description, 'Empty value uses WordPress default behavior.', 'success');
			return;
		}

		// Basic URL validation
		try {
			var url = new URL(value);

			// Check for valid protocols
			if (!['http:', 'https:'].includes(url.protocol)) {
				isValid = false;
			}

		} catch (e) {
			isValid = false;
		}

		// Apply validation styles
		if (isValid) {
			$input.addClass('valid');
			$wrapper.addClass('has-success');
			updateDescription($description, 'Valid URL format.', 'success');
		} else {
			$input.addClass('invalid');
			$wrapper.addClass('has-error');
			updateDescription($description, 'Please enter a valid URL (http:// or https://).', 'error');
		}

		// Update preview if URL is valid
		if (isValid) {
			updateUrlPreview($input.attr('id'), value);
		} else {
			hideUrlPreview($input.attr('id'));
		}
	}

	/**
	 * Update field description with validation message
	 * @param {jQuery} $description - The description element
	 * @param {string} message - The message to display
	 * @param {string} type - The type of message (success, error, info)
	 */
	function updateDescription($description, message, type = 'info') {
		if (!$description.length) return;

		$description.removeClass('validation-success validation-error validation-info');
		$description.addClass('validation-' + type);
		$description.text(message);
	}

	/**
	 * Add URL preview functionality
	 */
	function addUrlPreview() {
		$('#login_redirect_url, #logout_redirect_url').each(function() {
			var $input = $(this);
			var fieldId = $input.attr('id');

			// Create preview element if it doesn't exist
			if ($('#' + fieldId + '_preview').length === 0) {
				$input.closest('.form-field').append(
					'<div id="' + fieldId + '_preview" class="url-preview" style="display: none;"></div>'
				);
			}
		});
	}

	/**
	 * Update URL preview display
	 * @param {string} fieldId - The field ID
	 * @param {string} url - The URL to preview
	 */
	function updateUrlPreview(fieldId, url) {
		var $preview = $('#' + fieldId + '_preview');
		if (!$preview.length) return;

		var isValidUrl = true;
		try {
			new URL(url);
		} catch (e) {
			isValidUrl = false;
		}

		if (isValidUrl) {
			$preview.html(
				'<strong>Preview:</strong> ' +
				'<a href="' + url + '" target="_blank" rel="noopener noreferrer">' +
				url + ' <span class="dashicons dashicons-external" aria-hidden="true"></span>' +
				'</a>'
			).show();
		}
	}

	/**
	 * Hide URL preview display
	 * @param {string} fieldId - The field ID
	 */
	function hideUrlPreview(fieldId) {
		var $preview = $('#' + fieldId + '_preview');
		if ($preview.length) {
			$preview.hide();
		}
	}

	/**
	 * Add accessibility enhancements
	 */
	function addAccessibilityFeatures() {
		// Add ARIA live region for dynamic updates
		if ($('#redirect-accessibility-updates').length === 0) {
			$('body').append('<div id="redirect-accessibility-updates" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
		}

		// Enhance form submission feedback
		$(document).on('submit', 'form[action="options.php"]', function() {
			var $form = $(this);
			var $submitButton = $form.find('input[type="submit"]');

			$submitButton.prop('disabled', true).val('Saving...');

			// Announce to screen readers
			$('#redirect-accessibility-updates').text('Saving redirect settings...');

			// Re-enable button after a delay (in case of success)
			setTimeout(function() {
				$submitButton.prop('disabled', false).val('Save Changes');
			}, 2000);
		});

		// Add keyboard navigation improvements
		$('#login_redirect_url, #logout_redirect_url').on('keydown', function(e) {
			// Add Ctrl+Enter to test URL
			if (e.ctrlKey && e.keyCode === 13) {
				e.preventDefault();
				var $input = $(this);
				var url = $input.val().trim();

				if (url) {
					window.open(url, '_blank', 'noopener,noreferrer');
					$('#redirect-accessibility-updates').text('URL opened in new tab for testing.');
				}
			}
		});
	}

	/**
	 * Announce message to screen readers
	 * @param {string} message - The message to announce
	 */
	function announceToScreenReader(message) {
		$('#redirect-accessibility-updates').text(message);
	}

	// Utility function to check if we're on the settings page
	function isSettingsPage() {
		return window.location.href.indexOf('options-general.php') !== -1;
	}

})( jQuery );
