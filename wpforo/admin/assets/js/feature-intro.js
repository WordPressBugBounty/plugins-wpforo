/**
 * wpForo Feature Introduction Modal
 */
(function($) {
	'use strict';

	var currentSlide = 0;
	var totalSlides = wpforoFeatureIntro.totalSlides || 1;
	var $overlay, $slides, $dots, $prevBtn, $nextBtn;

	$(document).ready(function() {
		$overlay = $('#wpforo-feature-intro-overlay');
		if (!$overlay.length) return;

		$slides = $overlay.find('.wpf-fi-slide');
		$dots = $overlay.find('.wpf-fi-dot');
		$prevBtn = $overlay.find('.wpf-fi-prev');
		$nextBtn = $overlay.find('.wpf-fi-next');

		// Show modal with animation
		setTimeout(function() {
			$overlay.addClass('wpf-fi-visible');
		}, 300);

		updateArrows();

		// Arrow navigation
		$prevBtn.on('click', function() {
			goToSlide(currentSlide - 1);
		});

		$nextBtn.on('click', function() {
			goToSlide(currentSlide + 1);
		});

		// Dot navigation
		$dots.on('click', function() {
			goToSlide($(this).data('slide'));
		});

		// Close button
		$overlay.on('click', '.wpf-fi-close', function(e) {
			e.preventDefault();
			dismissIntro();
		});

		// Maybe Later button
		$overlay.on('click', '.wpf-fi-dismiss', function(e) {
			e.preventDefault();
			dismissIntro();
		});

		// Connect Now button - dismiss then navigate
		$overlay.on('click', '.wpf-fi-connect', function(e) {
			e.preventDefault();
			var url = $(this).attr('href');
			dismissIntro(function() {
				window.location.href = url;
			});
		});

		// Click overlay background to close
		$overlay.on('click', function(e) {
			if (e.target === this) {
				dismissIntro();
			}
		});

		// Keyboard navigation
		$(document).on('keydown.wpforo-feature-intro', function(e) {
			if (e.key === 'Escape') {
				dismissIntro();
			}
			if (e.key === 'ArrowRight') {
				goToSlide(currentSlide + 1);
			}
			if (e.key === 'ArrowLeft') {
				goToSlide(currentSlide - 1);
			}
		});
	});

	function goToSlide(index) {
		if (index < 0 || index >= totalSlides || index === currentSlide) return;

		currentSlide = index;

		// Update slides
		$slides.removeClass('wpf-fi-active');
		$slides.filter('[data-slide="' + index + '"]').addClass('wpf-fi-active');

		// Update dots
		$dots.removeClass('wpf-fi-active');
		$dots.filter('[data-slide="' + index + '"]').addClass('wpf-fi-active');

		// Update arrow visibility
		updateArrows();
	}

	function updateArrows() {
		$prevBtn.toggleClass('wpf-fi-hidden', currentSlide === 0);
		$nextBtn.toggleClass('wpf-fi-hidden', currentSlide === totalSlides - 1);
	}

	function dismissIntro(callback) {
		var optOut = $('#wpf-fi-opt-out').is(':checked') ? '1' : '0';

		// Fade out
		$overlay.removeClass('wpf-fi-visible');

		// Send AJAX
		$.post(wpforoFeatureIntro.ajaxUrl, {
			action: 'wpforo_dismiss_feature_intro',
			_wpnonce: wpforoFeatureIntro.nonce,
			version: wpforoFeatureIntro.version,
			opt_out: optOut
		}).always(function() {
			// Remove from DOM after animation
			setTimeout(function() {
				$overlay.remove();
				$(document).off('keydown.wpforo-feature-intro');
				if (typeof callback === 'function') callback();
			}, 350);
		});
	}

})(jQuery);
