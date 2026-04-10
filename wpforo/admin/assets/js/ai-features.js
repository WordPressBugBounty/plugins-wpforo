/**
 * wpForo AI Features - Admin JavaScript
 *
 * Handles interactive elements on the AI Features admin page including:
 * - API key reveal/hide functionality
 * - Disconnect service confirmation dialog
 * - Form submission with loading states
 *
 * @since 3.0.0
 */

(function($) {
	'use strict';

	/**
	 * Main AI Features Admin object
	 */
	const WpForoAI = {

		// Flag to prevent multiple initializations
		initialized: false,

		/**
		 * Initialize all functionality
		 */
		init: function() {
			// Prevent multiple initializations
			if (this.initialized) {
				console.log('WpForoAI already initialized, skipping...');
				return;
			}

			this.initialized = true;
			this.bindEvents();
			this.initTooltips();
			this.initRAGFeatures();
			this.initTagSuggest();
			this.initCharCounters();
			this.initBotUserSearch();
			this.checkPostPurchaseRefresh();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
		// Unbind all events first to prevent duplicates
		$(document).off('click', '.wpforo-ai-reveal-key');
		$(document).off('click', '.wpforo-ai-disconnect-btn');
		$(document).off('click', '.wpforo-ai-disconnect-purge-btn');
		$(document).off('submit', '.wpforo-ai-wrap form');
		$(document).off('click', '.wpforo-ai-copy-btn');
		$(document).off('click', '.wpforo-ai-upgrade-btn');
		$(document).off('click', '.wpforo-ai-buy-credits-btn');
		$(document).off('click', '.wpforo-ai-features-accordion .accordion-header');
		$(document).off('click', '.wpforo-ai-activate-license-btn');
		$(document).off('click', '.wpforo-ai-activate-paddle-txn-btn');
		$(document).off('click', '.wpforo-ai-bonus-credits-btn.eligible');
		$(document).off('click', '.wpforo-ai-legal-link');
		$(document).off('click', '.wpforo-ai-modal-close, .wpforo-ai-modal-close-btn, .wpforo-ai-modal-overlay');

			// Reveal/hide API key
			$(document).on('click', '.wpforo-ai-reveal-key', this.toggleApiKeyVisibility.bind(this));

			// Disconnect service button
			$(document).on('click', '.wpforo-ai-disconnect-btn', this.showDisconnectDialog.bind(this));

			// Disconnect and remove all data button
			$(document).on('click', '.wpforo-ai-disconnect-purge-btn', this.showDisconnectPurgeDialog.bind(this));

			// Add loading state to form submissions (use event delegation to prevent multiple handlers)
			$(document).on('submit', '.wpforo-ai-wrap form', this.handleFormSubmit.bind(this));

			// Copy to clipboard functionality (if needed in future)
			$(document).on('click', '.wpforo-ai-copy-btn', this.copyToClipboard.bind(this));

			// Checkout - Upgrade buttons (routes to Paddle or Freemius based on selected provider)
			$(document).on('click', '.wpforo-ai-upgrade-btn', this.handleUpgradeClick.bind(this));

			// Checkout - Credit pack purchase buttons (routes to Paddle or Freemius)
			$(document).on('click', '.wpforo-ai-buy-credits-btn', this.handleCreditPackClick.bind(this));

			// Payment provider toggle
			$(document).on('change', 'input[name="wpforo_ai_payment_provider"]', this.handleProviderChange.bind(this));

			// Features accordion toggle
			$(document).on('click', '.wpforo-ai-features-accordion .accordion-header', this.toggleAccordion.bind(this));

			// License activation button
			$(document).on('click', '.wpforo-ai-activate-license-btn', this.activateLicense.bind(this));

			// Paddle transaction activation button
			$(document).on('click', '.wpforo-ai-activate-paddle-txn-btn', this.activatePaddleTransaction.bind(this));

			// Bonus credits request button
			$(document).on('click', '.wpforo-ai-bonus-credits-btn.eligible', this.requestBonusCredits.bind(this));

			// Legal document links
			$(document).on('click', '.wpforo-ai-legal-link', this.openLegalModal.bind(this));

			// Close legal modal
			$(document).on('click', '.wpforo-ai-modal-close, .wpforo-ai-modal-close-btn, .wpforo-ai-modal-overlay', this.closeLegalModal.bind(this));

			// Close modal with Escape key
			$(document).on('keydown', this.handleModalKeydown.bind(this));

			// Terms checkbox validation
			$(document).on('submit', '#wpforo-ai-connect-form', this.validateTermsAgreement.bind(this));
		},

		/**
		 * Toggle accordion panel
		 */
		toggleAccordion: function(e) {
			e.preventDefault();

			const $header = $(e.currentTarget);
			const $content = $header.next('.accordion-content');
			const isExpanded = $header.attr('aria-expanded') === 'true';

			if (isExpanded) {
				// Collapse
				$header.attr('aria-expanded', 'false');
				$content.slideUp(300);
			} else {
				// Expand
				$header.attr('aria-expanded', 'true');
				$content.slideDown(300);
			}
		},

		/**
		 * Open legal document modal
		 */
		openLegalModal: function(e) {
			e.preventDefault();

			const $link = $(e.currentTarget);
			const documentType = $link.data('document');
			const $modal = $('#wpforo-ai-legal-modal');
			const $title = $('#wpforo-ai-modal-title');
			const $content = $('#wpforo-ai-modal-content');

			// Set title based on document type
			if (documentType === 'terms') {
				$title.text('Terms of Service');
			} else if (documentType === 'privacy') {
				$title.text('Privacy Policy');
			}

			// Show loading state
			$content.html('<div class="wpforo-ai-modal-loading">Loading document...</div>');
			$modal.show();
			$('body').addClass('wpforo-ai-modal-open');

			// Load document content via AJAX
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_legal_document',
					document: documentType,
					nonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.content) {
						$content.html(response.data.content);
					} else {
						$content.html('<p>Error loading document. Please try again.</p>');
					}
				},
				error: function() {
					$content.html('<p>Error loading document. Please try again.</p>');
				}
			});
		},

		/**
		 * Close legal document modal
		 */
		closeLegalModal: function(e) {
			if (e) {
				e.preventDefault();
			}

			const $modal = $('#wpforo-ai-legal-modal');
			$modal.hide();
			$('body').removeClass('wpforo-ai-modal-open');
		},

		/**
		 * Handle keyboard events for modal
		 */
		handleModalKeydown: function(e) {
			if (e.key === 'Escape' && $('#wpforo-ai-legal-modal').is(':visible')) {
				this.closeLegalModal();
			}
		},

		/**
		 * Validate terms agreement before form submission
		 */
		validateTermsAgreement: function(e) {
			const $checkbox = $('#wpforo-ai-agree-terms');

			if (!$checkbox.is(':checked')) {
				e.preventDefault();
				alert('Please read and agree to the Terms of Service and Privacy Policy before connecting.');
				$checkbox.focus();
				return false;
			}

			return true;
		},

		/**
		 * Toggle API key visibility
		 */
		toggleApiKeyVisibility: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $keyElement = $('.wpforo-ai-key-masked');
			const isRevealed = $button.data('revealed') === true;

			if (!isRevealed) {
				// Show confirmation before revealing
				if (!confirm('Are you sure you want to reveal your API key? Make sure no one is looking over your shoulder.')) {
					return;
				}

				// Get full key from WordPress options via AJAX
				this.fetchFullApiKey(function(fullKey) {
					if (fullKey) {
						$keyElement.text(fullKey);
						$button.data('revealed', true);
						$button.html('<span class="dashicons dashicons-hidden"></span> Hide');
					}
				});
			} else {
				// Hide the key again
				this.fetchMaskedApiKey(function(maskedKey) {
					$keyElement.text(maskedKey);
					$button.data('revealed', false);
					$button.html('<span class="dashicons dashicons-visibility"></span> Reveal');
				});
			}
		},

		/**
		 * Fetch full API key via AJAX
		 */
		fetchFullApiKey: function(callback) {
			// For now, we'll use a placeholder since AJAX endpoint isn't implemented
			// TODO: Implement AJAX endpoint for secure key retrieval
			const $keyElement = $('.wpforo-ai-key-masked');
			const maskedKey = $keyElement.text();

			// This is a temporary solution - in production, fetch from server
			const mockFullKey = maskedKey.replace('***', 'XXXXXXXXXXXXXXXX');

			callback(mockFullKey);
		},

		/**
		 * Fetch masked API key
		 */
		fetchMaskedApiKey: function(callback) {
			const $keyElement = $('.wpforo-ai-key-masked');
			const currentText = $keyElement.text();
			const prefix = currentText.substring(0, 6);
			const maskedKey = prefix + '***';

			callback(maskedKey);
		},

		/**
		 * Show disconnect service confirmation dialog
		 */
		showDisconnectDialog: function(e) {
			e.preventDefault();

			const $form = $('#wpforo-ai-disconnect-form');

			if (!$form.length) {
				console.error('Disconnect form not found');
				return;
			}

			// Use WordPress-style dialog if available
			if (typeof wp !== 'undefined' && wp.media) {
				// TODO: Implement custom modal with wp.media
				this.showNativeDisconnectDialog($form);
			} else {
				this.showNativeDisconnectDialog($form);
			}
		},

		/**
		 * Show native browser confirm dialog for disconnect
		 */
		showNativeDisconnectDialog: function($form) {
			const confirmMessage =
				'⚠️ WARNING: This will disconnect your forum from wpForo AI service.\n\n' +
				'⚠️ If you have an active subscription plan, please cancel it before disconnecting. Disconnecting does NOT cancel your subscription.\n\n' +
				'• Your credits will be preserved\n' +
				'• Your indexed content will be deleted after 30 days\n' +
				'• You can reconnect anytime with the same site URL\n\n' +
				'Are you absolutely sure you want to disconnect?';

			if (!confirm(confirmMessage)) {
				return;
			}

			// Ask for optional reason (user can click Cancel and still proceed)
			const reason = prompt('Optional: Tell us why you\'re disconnecting (helps us improve):');

			// Set values in form
			$form.find('input[name="confirm"]').prop('checked', true);

			if (reason && reason.trim()) {
				$form.find('textarea[name="reason"]').val(reason.trim());
			}

			// Submit form using native DOM method (works better in Firefox after preventDefault)
			$form[0].submit();
		},

		/**
		 * Show disconnect and remove all data confirmation dialog
		 */
		showDisconnectPurgeDialog: function(e) {
			e.preventDefault();

			const $form = $('#wpforo-ai-disconnect-purge-form');

			if (!$form.length) {
				console.error('Disconnect purge form not found');
				return;
			}

			const confirmMessage =
				'⚠️ WARNING: This will PERMANENTLY DELETE ALL your data from gVectors AI servers.\n\n' +
				'⚠️ If you have an active subscription plan, please cancel it before disconnecting. Disconnecting does NOT cancel your subscription.\n\n' +
				'• All indexed content and embeddings will be deleted immediately\n' +
				'• Your credits will NOT be preserved\n' +
				'• Your tenant account will be removed\n' +
				'• This action CANNOT be undone\n\n' +
				'Are you absolutely sure you want to delete all data?';

			if (!confirm(confirmMessage)) {
				return;
			}

			// Double confirmation for destructive action
			if (!confirm('This is your final confirmation. All data will be permanently deleted. Continue?')) {
				return;
			}

			const reason = prompt('Optional: Tell us why you\'re removing your data (helps us improve):');

			$form.find('input[name="confirm"]').prop('checked', true);

			if (reason && reason.trim()) {
				$form.find('textarea[name="reason"]').val(reason.trim());
			}

			$form[0].submit();
		},

		/**
		 * Handle form submission with loading state
		 */
		handleFormSubmit: function(e) {
			const $form = $(e.currentTarget);
			const $submitButton = $form.find('button[type="submit"]');

			// Add loading state to button
			$submitButton.addClass('loading').prop('disabled', true);

			// Note: Form will submit normally, this just adds visual feedback
			// The page will reload after submission completes
		},

		/**
		 * Copy text to clipboard
		 */
		copyToClipboard: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const textToCopy = $button.data('copy');

			if (!textToCopy) {
				return;
			}

			// Modern clipboard API
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(textToCopy).then(function() {
					WpForoAI.showCopySuccess($button);
				}).catch(function(err) {
					console.error('Failed to copy:', err);
					WpForoAI.fallbackCopyToClipboard(textToCopy, $button);
				});
			} else {
				// Fallback for older browsers
				this.fallbackCopyToClipboard(textToCopy, $button);
			}
		},

		/**
		 * Fallback copy method for older browsers
		 */
		fallbackCopyToClipboard: function(text, $button) {
			const $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();

			try {
				document.execCommand('copy');
				this.showCopySuccess($button);
			} catch (err) {
				console.error('Fallback copy failed:', err);
				alert('Failed to copy to clipboard. Please copy manually.');
			}

			$temp.remove();
		},

		/**
		 * Show success feedback for copy action
		 */
		showCopySuccess: function($button) {
			const originalText = $button.html();

			$button.html('<span class="dashicons dashicons-yes"></span> Copied!');
			$button.addClass('copied');

			setTimeout(function() {
				$button.html(originalText);
				$button.removeClass('copied');
			}, 2000);
		},

		/**
		 * Open a centered popup with a loading spinner
		 */
		openCenteredPopup: function(name, width, height) {
			var left = (screen.width - width) / 2;
			var top = (screen.height - height) / 2;
			var popup = window.open('about:blank', name, 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
			if (popup) {
				popup.document.write(
					'<!DOCTYPE html><html><head><title>gVectors Store - Checkout</title>' +
					'<style>body{margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;' +
					'background:#f8f9fa;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}' +
					'.loader{text-align:center;color:#555;}.spinner{width:40px;height:40px;margin:0 auto 16px;' +
					'border:3px solid #e0e0e0;border-top:3px solid #4d9113;border-radius:50%;' +
					'animation:spin .8s linear infinite;}@keyframes spin{to{transform:rotate(360deg)}}</style></head>' +
					'<body><div class="loader"><div class="spinner"></div>Loading checkout...</div></body></html>'
				);
				popup.document.close();
			}
			return popup;
		},

		/**
		 * Get the currently selected payment provider
		 */
		getSelectedProvider: function() {
			const $checked = $('input[name="wpforo_ai_payment_provider"]:checked');
			if ($checked.length) {
				return $checked.val();
			}
			// Fallback to global default
			return window.wpforoPaymentProvider || 'paddle';
		},

		/**
		 * Handle payment provider toggle change
		 */
		handleProviderChange: function() {
			window.wpforoPaymentProvider = this.getSelectedProvider();
		},

		/**
		 * Route upgrade button click to the correct provider
		 */
		handleUpgradeClick: function(e) {
			const provider = this.getSelectedProvider();
			if (provider === 'paddle') {
				this.openPaddleCheckout(e);
			} else {
				this.openFreemiusCheckout(e);
			}
		},

		/**
		 * Route credit pack button click to the correct provider
		 */
		handleCreditPackClick: function(e) {
			const provider = this.getSelectedProvider();
			if (provider === 'paddle') {
				this.openPaddleCreditPackCheckout(e);
			} else {
				this.openCreditPackCheckout(e);
			}
		},

		/**
		 * Open Paddle checkout in a popup window for plan upgrade
		 *
		 * Flow: AJAX to WP → backend creates checkout transaction
		 * → returns checkout URL → opens checkout page in popup
		 * → detects popup close → post-purchase refresh
		 *
		 * The checkout page is hosted on YOUR approved domain (e.g., gvectors.com),
		 * not on the customer's WordPress site. No domain verification needed per customer.
		 */
		openPaddleCheckout: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const plan = $button.data('plan');
			const tenantId = $button.data('tenant-id');

			// Enterprise: redirect to contact page
			if (plan === 'enterprise') {
				window.open('https://v3.wpforo.com/gvectors-ai/#gvai-contact', '_blank');
				return;
			}

			// Get Paddle config
			if (!window.wpforoPaddleCheckout || !window.wpforoPaddleCheckout.plans || !window.wpforoPaddleCheckout.plans[plan]) {
				console.error('Paddle checkout configuration not found for plan:', plan);
				alert('Checkout configuration error. Please try again or contact support.');
				return;
			}

			const config = window.wpforoPaddleCheckout.plans[plan];
			const originalText = $button.html();

			// Show loading state
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update wpforo-status-spin"></span> Loading...');

			// Open popup IMMEDIATELY on user click (before AJAX) to avoid popup blockers.
			// Browsers only allow window.open() in direct click handlers — async callbacks get blocked.
			const checkoutWindow = this.openCenteredPopup('paddle_checkout', 850, 650);

			// Create checkout via AJAX → backend
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_paddle_checkout',
					nonce: wpforoAIAdmin.nonce,
					price_id: config.price_id,
					plan: plan
				},
				success: function(response) {
					if (response.success && response.data.checkout_url) {
						if (checkoutWindow && !checkoutWindow.closed) {
							// Redirect the already-open popup to checkout URL
							checkoutWindow.location.href = response.data.checkout_url;
						} else {
							// Popup was blocked or closed — fall back to redirect
							window.location.href = response.data.checkout_url;
							return;
						}

						// Listen for postMessage from checkout page (success signal)
						var purchaseCompleted = false;
						var messageHandler = function(event) {
							if (event.data && event.data.type === 'paddle_checkout_complete') {
								purchaseCompleted = true;
							}
						};
						window.addEventListener('message', messageHandler);

						// Poll for popup close — only redirect if purchase was confirmed
						const pollTimer = setInterval(function() {
							if (checkoutWindow.closed) {
								clearInterval(pollTimer);
								window.removeEventListener('message', messageHandler);
								if (purchaseCompleted) {
									// Redirect to post-purchase page (spinner + auto-refresh)
									window.location.href = window.location.href.split('?')[0] +
										'?page=wpforo-ai&upgraded=1&plan=' + encodeURIComponent(plan);
								}
								// If not completed, do nothing — user just closed the window
							}
						}, 500);

						// Restore button
						$button.prop('disabled', false).html(originalText);
					} else {
						// Close the blank popup on error
						if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close();
						const msg = (response.data && response.data.message) || 'Failed to create checkout.';
						alert(msg + ' Please try again or contact support.');
						$button.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close();
					alert('Failed to create checkout. Please check your connection and try again.');
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		/**
		 * Open Paddle checkout in a popup window for credit pack purchase
		 */
		openPaddleCreditPackCheckout: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const pack = $button.data('pack');
			const tenantId = $button.data('tenant-id');

			// Get Paddle config
			if (!window.wpforoPaddleCheckout || !window.wpforoPaddleCheckout.creditPacks || !window.wpforoPaddleCheckout.creditPacks[pack]) {
				console.error('Paddle checkout configuration not found for credit pack:', pack);
				alert('Checkout configuration error. Please try again or contact support.');
				return;
			}

			const config = window.wpforoPaddleCheckout.creditPacks[pack];
			const originalText = $button.html();

			// Show loading state
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update wpforo-status-spin"></span> Loading...');

			// Open popup IMMEDIATELY on user click to avoid popup blockers
			const checkoutWindow = this.openCenteredPopup('paddle_checkout', 850, 650);

			// Create checkout via AJAX → backend
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_paddle_checkout',
					nonce: wpforoAIAdmin.nonce,
					price_id: config.price_id,
					plan: 'credit_pack_' + pack
				},
				success: function(response) {
					if (response.success && response.data.checkout_url) {
						if (checkoutWindow && !checkoutWindow.closed) {
							checkoutWindow.location.href = response.data.checkout_url;
						} else {
							window.location.href = response.data.checkout_url;
							return;
						}

						// Listen for postMessage from checkout page (success signal)
						var purchaseCompleted = false;
						var messageHandler = function(event) {
							if (event.data && event.data.type === 'paddle_checkout_complete') {
								purchaseCompleted = true;
							}
						};
						window.addEventListener('message', messageHandler);

						// Poll for popup close — only redirect if purchase was confirmed
						const pollTimer = setInterval(function() {
							if (checkoutWindow.closed) {
								clearInterval(pollTimer);
								window.removeEventListener('message', messageHandler);
								if (purchaseCompleted) {
									window.location.href = window.location.href.split('?')[0] +
										'?page=wpforo-ai&credits_purchased=1&pack=' + encodeURIComponent(pack);
								}
							}
						}, 500);

						$button.prop('disabled', false).html(originalText);
					} else {
						if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close();
						const msg = (response.data && response.data.message) || 'Failed to create checkout.';
						alert(msg + ' Please try again or contact support.');
						$button.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close();
					alert('Failed to create checkout. Please check your connection and try again.');
					$button.prop('disabled', false).html(originalText);
				}
			});
		},

		/**
		 * Open Freemius checkout overlay for plan upgrade
		 */
		openFreemiusCheckout: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const plan = $button.data('plan');
			const tenantId = $button.data('tenant-id');

			// Get checkout config from global var
			if (!window.wpforoFreemiusCheckout || !window.wpforoFreemiusCheckout.plans || !window.wpforoFreemiusCheckout.plans[plan]) {
				console.error('Freemius checkout configuration not found for plan:', plan);
				if (confirm('Checkout configuration error. Please open a support ticket to quickly resolve this issue.')) {
					window.open('https://v3.wpforo.com/login-register/?tab=login', '_blank');
				}
				return;
			}

			const checkoutConfig = window.wpforoFreemiusCheckout.plans[plan];

			// Load Freemius Checkout JS library if not already loaded
			if (typeof FS === 'undefined' || typeof FS.Checkout === 'undefined') {
				this.loadFreemiusCheckoutSDK(function() {
					WpForoAI.initFreemiusCheckout(checkoutConfig, plan, tenantId);
				});
			} else {
				this.initFreemiusCheckout(checkoutConfig, plan, tenantId);
			}
		},

		/**
		 * Load Freemius Checkout SDK dynamically
		 */
		loadFreemiusCheckoutSDK: function(callback) {
			// Check if already loaded
			if (window.FS && window.FS.Checkout) {
				callback();
				return;
			}

			// Load the Freemius Checkout SDK
			const script = document.createElement('script');
			script.src = 'https://checkout.freemius.com/checkout.min.js';
			script.async = true;
			script.onload = callback;
			script.onerror = function() {
				console.error('Failed to load Freemius Checkout SDK');
				if (confirm('Failed to load checkout. Please open a support ticket to quickly resolve this issue.')) {
					window.open('https://v3.wpforo.com/login-register/?tab=login', '_blank');
				}
			};
			document.head.appendChild(script);
		},

		/**
		 * Initialize Freemius Checkout with configuration
		 */
		initFreemiusCheckout: function(config, plan, tenantId) {
			console.log('Initializing Freemius checkout with config:', config);

			// Validate required fields
			if (!config.plugin_id || !config.public_key) {
				console.error('Missing required Freemius config:', config);
				if (confirm('Checkout configuration error. Please open a support ticket to quickly resolve this issue.')) {
					window.open('https://v3.wpforo.com/login-register/?tab=login', '_blank');
				}
				return;
			}

			// Create checkout instance
			const handler = FS.Checkout.configure({
				plugin_id:  config.plugin_id,
				plan_id:    config.plan_id,
				pricing_id: config.pricing_id,
				public_key: config.public_key, // Use actual public key from config
				image:      'https://ps.w.org/wpforo/assets/icon-256x256.png'
			});

			// Build success URL with query parameters for post-purchase detection
			const adminUrl = window.location.href.split('?')[0]; // Get base URL without query params
			const successUrl = adminUrl + '?page=wpforo-ai&upgraded=1&plan=' + encodeURIComponent(plan);

			console.log('Checkout success URL:', successUrl);

			// Open the checkout overlay
			handler.open({
				name: 'wpForo AI Features',
				licenses: 1,
				billing_cycle: config.billing_cycle || 'monthly',
				currency: config.currency || 'usd',
				user_email: config.user ? config.user.email : '',
				user_firstname: config.user ? config.user.first : '',
				user_lastname: config.user ? config.user.last : '',
				metadata: config.metadata || { tenant_id: tenantId }, // CRITICAL: Pass tenant_id in metadata
				success_url: successUrl, // CRITICAL: Redirect URL after successful purchase
				success: function(response) {
					console.log('Checkout success:', response);
					WpForoAI.handlePurchaseComplete(response, plan, tenantId);
				},
				cancel: function() {
					console.log('Checkout cancelled');
				},
				purchaseCompleted: function(response) {
					console.log('Purchase completed:', response);
					WpForoAI.handlePurchaseComplete(response, plan, tenantId);
				},
				exitIntent: function() {
					console.log('User exited checkout');
				}
			});
		},

		/**
		 * Open Freemius checkout overlay for credit pack purchase
		 */
		openCreditPackCheckout: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const pack = $button.data('pack');
			const tenantId = $button.data('tenant-id');

			// Get checkout config from global var
			if (!window.wpforoFreemiusCheckout || !window.wpforoFreemiusCheckout.creditPacks || !window.wpforoFreemiusCheckout.creditPacks[pack]) {
				console.error('Freemius checkout configuration not found for credit pack:', pack);
				if (confirm('Checkout configuration error. Please open a support ticket to quickly resolve this issue.')) {
					window.open('https://v3.wpforo.com/login-register/?tab=login', '_blank');
				}
				return;
			}

			const checkoutConfig = window.wpforoFreemiusCheckout.creditPacks[pack];

			// Load Freemius Checkout JS library if not already loaded
			if (typeof FS === 'undefined' || typeof FS.Checkout === 'undefined') {
				this.loadFreemiusCheckoutSDK(function() {
					WpForoAI.initCreditPackCheckout(checkoutConfig, pack, tenantId);
				});
			} else {
				this.initCreditPackCheckout(checkoutConfig, pack, tenantId);
			}
		},

		/**
		 * Initialize Freemius Checkout for credit pack purchase
		 */
		initCreditPackCheckout: function(config, pack, tenantId) {
			console.log('Initializing Freemius checkout for credit pack:', pack, config);

			// Validate required fields
			if (!config.plugin_id || !config.public_key) {
				console.error('Missing required Freemius config:', config);
				if (confirm('Checkout configuration error. Please open a support ticket to quickly resolve this issue.')) {
					window.open('https://v3.wpforo.com/login-register/?tab=login', '_blank');
				}
				return;
			}

			// Create checkout instance
			const handler = FS.Checkout.configure({
				plugin_id:  config.plugin_id,
				plan_id:    config.plan_id,
				pricing_id: config.pricing_id,
				public_key: config.public_key,
				image:      'https://ps.w.org/wpforo/assets/icon-256x256.png'
			});

			// Build success URL with query parameters for post-purchase detection
			const adminUrl = window.location.href.split('?')[0]; // Get base URL without query params
			const successUrl = adminUrl + '?page=wpforo-ai&credits_purchased=1&pack=' + encodeURIComponent(pack);

			console.log('Credit pack checkout success URL:', successUrl);

			// Open the checkout overlay
			handler.open({
				name: 'wpForo AI Credits - ' + pack + ' Pack',
				licenses: 1,
				billing_cycle: 'one-time',
				currency: config.currency || 'usd',
				user_email: config.user ? config.user.email : '',
				user_firstname: config.user ? config.user.first : '',
				user_lastname: config.user ? config.user.last : '',
				metadata: config.metadata || { tenant_id: tenantId }, // CRITICAL: Pass tenant_id in metadata
				success_url: successUrl, // CRITICAL: Redirect URL after successful purchase
				success: function(response) {
					console.log('Credit pack purchase success:', response);
					WpForoAI.handleCreditPackPurchaseComplete(response, pack, tenantId);
				},
				cancel: function() {
					console.log('Credit pack checkout cancelled');
				},
				purchaseCompleted: function(response) {
					console.log('Credit pack purchase completed:', response);
					WpForoAI.handleCreditPackPurchaseComplete(response, pack, tenantId);
				},
				exitIntent: function() {
					console.log('User exited credit pack checkout');
				}
			});
		},

		/**
		 * Handle successful purchase completion
		 */
		handlePurchaseComplete: function(response, plan, tenantId) {
			console.log('Purchase completed:', response);

			// Show success message
			const $notice = $('<div class="notice notice-success is-dismissible"><p><strong>Purchase Successful!</strong> Your plan has been upgraded. Refreshing page...</p></div>');
			$('.wpforo-ai-wrap').prepend($notice);

			// CRITICAL: Link subscription_id to tenant for webhook matching
			// Freemius webhooks need this to identify the tenant
			if (response.purchase && response.purchase.subscription_id) {
				$.ajax({
					url: wpforoAIAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpforo_ai_link_subscription',
						nonce: wpforoAIAdmin.nonce,
						subscription_id: response.purchase.subscription_id,
						user_id: response.user ? response.user.id : '',
						plan: plan
					},
					success: function(linkResponse) {
						console.log('Subscription linked:', linkResponse);
					},
					error: function(xhr, status, error) {
						console.error('Failed to link subscription:', error);
					}
				});
			}

			// Wait a moment then reload the page to show updated plan
			setTimeout(function() {
				window.location.href = response.success || window.location.href.split('?')[0] + '?page=wpforo-ai&upgraded=1&plan=' + plan;
			}, 2000);
		},

		/**
		 * Handle successful credit pack purchase completion
		 */
		handleCreditPackPurchaseComplete: function(response, pack, tenantId) {
			console.log('Credit pack purchase completed:', response);

			// Show success message
			const $notice = $('<div class="notice notice-success is-dismissible"><p><strong>Purchase Successful!</strong> ' + pack + ' credits have been added to your account. Refreshing page...</p></div>');
			$('.wpforo-ai-wrap').prepend($notice);

			// Wait a moment then reload the page to show updated credits
			setTimeout(function() {
				window.location.href = response.success || window.location.href.split('?')[0] + '?page=wpforo-ai&credits_purchased=1&pack=' + pack;
			}, 2000);
		},

		/**
		 * Activate license manually
		 *
		 * Called when user enters a License ID and clicks Activate.
		 * Sends request to backend to verify with Freemius API.
		 */
		activateLicense: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const $wrapper = $btn.closest('.license-input-wrapper');
			const $input = $wrapper.find('#wpforo-ai-license-id');
			const $spinner = $wrapper.find('.spinner');
			const $result = $btn.closest('.wpforo-ai-license-activation').find('.wpforo-ai-license-result');
			const licenseId = $input.val().trim();

			// Validate input
			if (!licenseId) {
				$result.html('<div class="notice notice-error inline"><p>Please enter your License ID.</p></div>').show();
				$input.focus();
				return;
			}

			// Show loading state
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide();

			// Send AJAX request
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_activate_license',
					nonce: wpforoAIAdmin.nonce,
					license_id: licenseId
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						const data = response.data;
						$result.html(
							'<div class="notice notice-success inline">' +
							'<p><strong>License Activated!</strong> ' + data.message + '</p>' +
							(data.plan ? '<p>Plan: <strong>' + data.plan.charAt(0).toUpperCase() + data.plan.slice(1) + '</strong></p>' : '') +
							(data.credits_added ? '<p>Credits added: <strong>' + data.credits_added.toLocaleString() + '</strong></p>' : '') +
							'</div>'
						).show();

						// Clear input
						$input.val('');

						// Reload page after 2 seconds to show updated status
						setTimeout(function() {
							window.location.reload();
						}, 2500);
					} else {
						$result.html(
							'<div class="notice notice-error inline">' +
							'<p>' + (response.data && response.data.message ? response.data.message : 'License activation failed. Please check your License ID.') + '</p>' +
							'</div>'
						).show();
					}
				},
				error: function(xhr, status, error) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					let errorMsg = 'An error occurred. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}

					$result.html(
						'<div class="notice notice-error inline">' +
						'<p>' + errorMsg + '</p>' +
						'</div>'
					).show();
				}
			});
		},

		/**
		 * Activate Paddle transaction manually (mirrors activateLicense)
		 */
		activatePaddleTransaction: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const $wrapper = $btn.closest('.license-input-wrapper');
			const $input = $wrapper.find('#wpforo-ai-paddle-txn-id');
			const $spinner = $wrapper.find('.spinner');
			const $result = $btn.closest('.wpforo-ai-paddle-activation').find('.wpforo-ai-paddle-result');
			const txnId = $input.val().trim();

			// Validate input
			if (!txnId) {
				$result.html('<div class="notice notice-error inline"><p>Please enter your Transaction ID.</p></div>').show();
				$input.focus();
				return;
			}

			if (txnId.indexOf('txn_') !== 0) {
				$result.html('<div class="notice notice-error inline"><p>Invalid Transaction ID format. Must start with "txn_".</p></div>').show();
				$input.focus();
				return;
			}

			// Show loading state
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide();

			// Send AJAX request
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_activate_paddle_transaction',
					nonce: wpforoAIAdmin.nonce,
					transaction_id: txnId
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						const data = response.data;
						$result.html(
							'<div class="notice notice-success inline">' +
							'<p><strong>Transaction Activated!</strong> ' + data.message + '</p>' +
							(data.plan ? '<p>Plan: <strong>' + data.plan.charAt(0).toUpperCase() + data.plan.slice(1) + '</strong></p>' : '') +
							(data.credits_added ? '<p>Credits added: <strong>' + data.credits_added.toLocaleString() + '</strong></p>' : '') +
							'</div>'
						).show();

						// Clear input
						$input.val('');

						// Reload page after 2 seconds to show updated status
						setTimeout(function() {
							window.location.reload();
						}, 2500);
					} else {
						$result.html(
							'<div class="notice notice-error inline">' +
							'<p>' + (response.data && response.data.message ? response.data.message : 'Transaction activation failed. Please check your Transaction ID.') + '</p>' +
							'</div>'
						).show();
					}
				},
				error: function(xhr, status, error) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');

					let errorMsg = 'An error occurred. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}

					$result.html(
						'<div class="notice notice-error inline">' +
						'<p>' + errorMsg + '</p>' +
						'</div>'
					).show();
				}
			});
		},

		/**
		 * Request bonus credits for large forums
		 */
		requestBonusCredits: function(e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();

			const $btn = $(e.currentTarget);

			// Prevent double-click
			if ($btn.hasClass('loading') || $btn.prop('disabled')) {
				return;
			}
			const $spinner = $btn.siblings('.wpforo-ai-bonus-spinner');

			// Confirm dialog
			const confirmMessage =
				'🎁 Request Free Indexing Credits\n\n' +
				'This is a one-time bonus for large forums.\n' +
				'Credits will be added based on your topic count.\n\n' +
				'Do you want to proceed?';

			if (!confirm(confirmMessage)) {
				return;
			}

			// Show loading state - spin the icon
			$btn.addClass('loading').prop('disabled', true);
			$btn.find('.dashicons').addClass('dashicons-update dashicons-spin').removeClass('dashicons-star-filled');
			$spinner.addClass('is-active');

			// Timer to show progress - 60 second timeout
			let seconds = 0;
			const originalText = $btn.html();
			const timerInterval = setInterval(function() {
				seconds++;
				// Update button text to show countdown to refresh
				$btn.contents().filter(function() {
					return this.nodeType === 3; // Text nodes only
				}).remove();
				$btn.append(' Processing... (' + seconds + 's)');
			}, 1000);

			// Send AJAX request with extended timeout
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				timeout: 60000, // 60 second timeout
				data: {
					action: 'wpforo_ai_request_bonus_credits',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					clearInterval(timerInterval);
					$spinner.removeClass('is-active');

					if (response.success) {
						const data = response.data;
						const creditsAdded = data.credits_added || 0;

						// Show success message
						alert('✅ Success!\n\n' + data.message + '\n\nCredits added: ' + creditsAdded.toLocaleString());

						// Update button to show claimed state
						$btn.removeClass('eligible loading')
							.addClass('claimed')
							.prop('disabled', true)
							.html('<span class="dashicons dashicons-awards"></span> Extra Free Credits ' + creditsAdded.toLocaleString());

						// Reload page after 2 seconds to update credit display
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						// Re-enable button on error
						$btn.removeClass('loading').prop('disabled', false).html(originalText);

						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Failed to request bonus credits.';
						alert('❌ Error\n\n' + errorMsg);
					}
				},
				error: function(xhr, status, error) {
					clearInterval(timerInterval);
					$btn.removeClass('loading').prop('disabled', false).html(originalText);
					$spinner.removeClass('is-active');

					let errorMsg = 'An error occurred. Please try again.';
					if (status === 'timeout') {
						errorMsg = 'Request timed out. Please try again.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}

					alert('❌ Error\n\n' + errorMsg);
				}
			});
		},

		/**
		 * Initialize tooltips (if needed)
		 */
		initTooltips: function() {
			// Add WordPress-style tooltips to elements with title attributes
			$('[data-tooltip]').each(function() {
				const $el = $(this);
				const tooltipText = $el.data('tooltip');

				if (tooltipText) {
					$el.attr('title', tooltipText);
				}
			});
		},

		/**
		 * Show notification message
		 */
		showNotice: function(message, type) {
			type = type || 'info'; // info, success, warning, error

			const $notice = $('<div>')
				.addClass('notice notice-' + type + ' is-dismissible')
				.append($('<p>').text(message));

			// Insert notice after page title
			$('.wpforo-ai-title').after($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);

			// Make dismissible
			$(document).trigger('wp-updates-notice-added');
		},

		/**
		 * Format numbers with thousand separators
		 */
		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		},

		/**
		 * Validate form before submission
		 */
		validateForm: function($form) {
			let isValid = true;
			const requiredFields = $form.find('[required]');

			requiredFields.each(function() {
				const $field = $(this);
				const value = $field.val().trim();

				if (!value) {
					isValid = false;
					$field.addClass('error');
					$field.on('input change', function() {
						$(this).removeClass('error');
					});
				}
			});

			if (!isValid) {
				alert('Please fill in all required fields.');
			}

			return isValid;
		},

		/**
		 * Initialize RAG-specific features
		 */
		initRAGFeatures: function() {
			// Check if indexing was being stopped before page reload
			if (localStorage.getItem('wpforo_indexing_stopping') === 'true') {
				this.indexingStopping = true;
				// Update status to show "Stopping..." if still processing
				const $statusElement = $('#rag-indexing-status');
				const statusText = $statusElement.text().trim();
				// Only show "Stopping..." if status indicates processing (not idle)
				if ($statusElement.length && statusText !== 'Idle') {
					$statusElement.text('Stopping...');
				} else if (statusText === 'Idle') {
					// Process already stopped, clear the flag
					this.indexingStopping = false;
					localStorage.removeItem('wpforo_indexing_stopping');
				}
			}

			// Unbind first to prevent duplicate handlers
			$(document).off('click', '.wpforo-ai-reindex-all');
			$(document).off('click', '.wpforo-ai-reindex-images');
			$(document).off('click', '.wpforo-ai-clear-database');
			$(document).off('click', '.wpforo-ai-clear-and-reindex');
			$(document).off('click', '.wpforo-ai-stop-indexing');
			$(document).off('click', '.wpforo-ai-cleanup-session');
			$(document).off('submit', '#wpforo-ai-search-test-form');

			// Bind bulk action buttons
			$(document).on('click', '.wpforo-ai-reindex-all', this.handleReindexAll.bind(this));
			$(document).on('click', '.wpforo-ai-reindex-images', this.handleReindexImages.bind(this));
			$(document).on('click', '.wpforo-ai-clear-database', this.handleClearDatabase.bind(this));
			$(document).on('click', '.wpforo-ai-clear-and-reindex', this.handleClearAndReindex.bind(this));
			$(document).on('click', '.wpforo-ai-stop-indexing', this.handleStopIndexing.bind(this));
			$(document).on('click', '.wpforo-ai-cleanup-session', this.handleCleanupSession.bind(this));

			// Bind search test form
			$(document).on('submit', '#wpforo-ai-search-test-form', this.handleSearchTest.bind(this));

			// Bind storage mode toggle
			$(document).off('change', 'input[name="wpforo_ai_storage_mode"]');
			$(document).on('change', 'input[name="wpforo_ai_storage_mode"]', this.handleStorageModeChange.bind(this));

			// Bind auto-indexing toggle
			$(document).off('change', '#wpforo-ai-auto-indexing');
			$(document).on('change', '#wpforo-ai-auto-indexing', this.handleAutoIndexingToggle.bind(this));

			// Bind image indexing toggle
			$(document).off('change', '#wpforo-ai-image-indexing');
			$(document).on('change', '#wpforo-ai-image-indexing', this.handleImageIndexingToggle.bind(this));

			// Bind document indexing toggle
			$(document).off('change', '#wpforo-ai-document-indexing');
			$(document).on('change', '#wpforo-ai-document-indexing', this.handleDocumentIndexingToggle.bind(this));

			// Bind refresh status button
			$(document).off('click', '.wpforo-ai-refresh-rag-status');
			$(document).on('click', '.wpforo-ai-refresh-rag-status', this.handleRefreshStatus.bind(this));

			// WordPress Content Indexing handlers
			this.initWordPressIndexingFeatures();

			// Check for in-progress local indexing and auto-resume
			this.checkLocalIndexingProgress();

			// Note: Polling is started from PHP inline script based on server-side $is_indexing status
			// No need to start it here to avoid duplicate polling
		},

		/**
		 * Handle storage mode toggle change
		 */
		handleStorageModeChange: function(e) {
			const $input = $(e.currentTarget);
			const newMode = $input.val();
			const $container = $input.closest('.wpforo-ai-storage-toggle');

			// Update active state on labels
			$container.find('.wpforo-ai-storage-option').removeClass('active');
			$input.next('label').addClass('active');

			// Get the current board ID from URL
			const urlParams = new URLSearchParams(window.location.search);
			const boardId = urlParams.get('boardid') || 0;

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_storage_mode',
					nonce: wpforoAIAdmin.nonce,
					storage_mode: newMode,
					board_id: boardId
				},
				beforeSend: function() {
					$container.css('opacity', '0.6');
				},
				success: function(response) {
					$container.css('opacity', '1');
					if (response.success) {
						// Reload page to update storage info section
						window.location.reload();
					} else {
						alert(response.data?.message || 'Failed to save storage mode.');
						// Revert the change
						window.location.reload();
					}
				},
				error: function() {
					$container.css('opacity', '1');
					alert('Error saving storage mode. Please try again.');
					window.location.reload();
				}
			});
		},

		/**
		 * Handle auto-indexing toggle change
		 */
		handleAutoIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const boardId = $input.data('board-id') || 0;
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_auto_indexing',
					nonce: wpforoAIAdmin.nonce,
					enabled: isEnabled,
					board_id: boardId
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (!response.success) {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert(response.data?.message || 'Failed to save auto-indexing setting.');
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					// Revert the change on error
					$input.prop('checked', !isEnabled);
					alert('Error saving auto-indexing setting. Please try again.');
				}
			});
		},

		/**
		 * Handle image indexing toggle change
		 *
		 * When enabled, posts with images will consume +1 additional credit
		 * for multimodal processing (image → text → embedding).
		 * Requires Business or Enterprise plan.
		 */
		handleImageIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const boardId = $input.data('board-id') || 0;
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Show confirmation when enabling (due to credit impact)
			if (isEnabled) {
				const confirmed = confirm(
					'Enable Image Indexing?\n\n' +
					'When enabled, posts with images will consume +1 additional credit during indexing.\n\n' +
					'• Maximum 10 images per post are processed\n' +
					'• Images are converted to text descriptions for search\n' +
					'• Small images (< 50x50px) like smileys are skipped\n\n' +
					'Continue?'
				);
				if (!confirmed) {
					$input.prop('checked', false);
					return;
				}
			}

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_image_indexing',
					nonce: wpforoAIAdmin.nonce,
					enabled: isEnabled,
					board_id: boardId
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (response.success) {
						// Show success message
						if (response.data?.message) {
							// Brief notification instead of alert
							console.log('Image indexing: ' + response.data.message);
						}
					} else {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert(response.data?.message || 'Failed to save image indexing setting.');
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					// Revert the change on error
					$input.prop('checked', !isEnabled);
					alert('Error saving image indexing setting. Please try again.');
				}
			});
		},

		/**
		 * Handle document indexing toggle change
		 */
		handleDocumentIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const boardId = $input.data('board-id') || 0;
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Show confirmation when enabling (due to credit impact)
			if (isEnabled) {
				const confirmed = confirm(
					'Enable Document Indexing?\n\n' +
					'When enabled, document attachments (PDF, DOCX, PPTX, etc.) will be processed during indexing.\n\n' +
					'• Maximum 5 documents per post\n' +
					'• Text is extracted from documents for search\n' +
					'• Credit cost: 1 per page\n\n' +
					'Continue?'
				);
				if (!confirmed) {
					$input.prop('checked', false);
					return;
				}
			}

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_document_indexing',
					nonce: wpforoAIAdmin.nonce,
					enabled: isEnabled,
					board_id: boardId
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (response.success) {
						if (response.data?.message) {
							console.log('Document indexing: ' + response.data.message);
						}
					} else {
						$input.prop('checked', !isEnabled);
						alert(response.data?.message || 'Failed to save document indexing setting.');
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					$input.prop('checked', !isEnabled);
					alert('Error saving document indexing setting. Please try again.');
				}
			});
		},

		/**
		 * Handle refresh status button click
		 */
		handleRefreshStatus: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const $icon = $button.find('.dashicons-update');

			// Add spinning animation
			$icon.addClass('wpforo-spin');
			$button.prop('disabled', true);

			// Store reference for callback
			const self = this;

			// Refresh status via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_rag_status',
					nonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateRAGStatusDisplay(response.data);
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to refresh RAG status:', error);
				},
				complete: function() {
					// Stop spinning animation
					$icon.removeClass('wpforo-spin');
					$button.prop('disabled', false);
				}
			});
		},

		// =====================================================
		// WordPress Content Indexing Methods
		// =====================================================

		/**
		 * Initialize WordPress content indexing features
		 */
		initWordPressIndexingFeatures: function() {
			const self = this;

			// Unbind first to prevent duplicate handlers
			$(document).off('click', '.wpforo-ai-refresh-wp-status');
			$(document).off('change', '#wp-taxonomy-select');
			$(document).off('submit', '.wpforo-ai-wp-taxonomy-form');
			$(document).off('submit', '.wpforo-ai-wp-custom-form');
			$(document).off('submit', '.wpforo-ai-wp-ids-form');
			$(document).off('click', '.wpforo-ai-wp-clear-index');
			$(document).off('click', '.wpforo-ai-select-all-terms');
			$(document).off('click', '.wpforo-ai-deselect-all-terms');
			$(document).off('change', '#wpforo-ai-wp-auto-indexing');
			$(document).off('change', '#wpforo-ai-wp-image-indexing');

			// Bind event handlers
			$(document).on('click', '.wpforo-ai-refresh-wp-status', this.handleRefreshWPStatus.bind(this));
			$(document).on('change', '#wp-taxonomy-select', this.handleTaxonomyChange.bind(this));
			$(document).on('submit', '.wpforo-ai-wp-taxonomy-form', this.handleWPTaxonomyIndex.bind(this));
			$(document).on('submit', '.wpforo-ai-wp-custom-form', this.handleWPCustomIndex.bind(this));
			$(document).on('submit', '.wpforo-ai-wp-ids-form', this.handleWPIndexByIds.bind(this));
			$(document).on('click', '.wpforo-ai-wp-clear-index', this.handleWPClearIndex.bind(this));

			// WordPress-specific auto-indexing and image indexing toggles
			$(document).on('change', '#wpforo-ai-wp-auto-indexing', this.handleWPAutoIndexingToggle.bind(this));
			$(document).on('change', '#wpforo-ai-wp-image-indexing', this.handleWPImageIndexingToggle.bind(this));

			// Select All / Deselect All for terms
			$(document).on('click', '.wpforo-ai-select-all-terms', function() {
				$('#wp-terms-container input[type="checkbox"]').prop('checked', true);
				self.updateTermIndexButton();
			});
			$(document).on('click', '.wpforo-ai-deselect-all-terms', function() {
				$('#wp-terms-container input[type="checkbox"]').prop('checked', false);
				self.updateTermIndexButton();
			});

			// Load initial WordPress indexing status
			if ($('.wpforo-ai-wordpress-indexing-box').length) {
				this.loadWPIndexingStatus();
			}
		},

		/**
		 * Refresh WordPress indexing status
		 */
		handleRefreshWPStatus: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const $icon = $button.find('.dashicons-update');

			$icon.addClass('wpforo-spin');
			$button.prop('disabled', true);

			this.loadWPIndexingStatus(function() {
				$icon.removeClass('wpforo-spin');
				$button.prop('disabled', false);
			});
		},

		// Polling interval for WordPress content indexing
		wpIndexingPollInterval: null,

		/**
		 * Load WordPress indexing status from API
		 */
		loadWPIndexingStatus: function(callback) {
			const self = this;

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_get_indexing_status',
					security: wpforoAIAdmin.adminNonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateWPIndexingDisplay(response.data);

						// Start polling if indexing is in progress
						if (response.data.queue && response.data.queue.status === 'processing') {
							self.startWPIndexingPolling();
						} else {
							self.stopWPIndexingPolling();
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to load WordPress indexing status:', error);
				},
				complete: function() {
					if (typeof callback === 'function') {
						callback();
					}
				}
			});
		},

		/**
		 * Start polling for WordPress indexing status
		 */
		startWPIndexingPolling: function() {
			const self = this;

			// Don't start if already polling
			if (this.wpIndexingPollInterval) {
				return;
			}

			// Poll every 5 seconds
			this.wpIndexingPollInterval = setInterval(function() {
				self.loadWPIndexingStatus();
			}, 5000);
		},

		/**
		 * Stop polling for WordPress indexing status
		 */
		stopWPIndexingPolling: function() {
			if (this.wpIndexingPollInterval) {
				clearInterval(this.wpIndexingPollInterval);
				this.wpIndexingPollInterval = null;
			}
		},

		/**
		 * Update WordPress indexing display with status data
		 */
		updateWPIndexingDisplay: function(data) {
			// Update total indexed
			if (data.total_indexed !== undefined) {
				$('#wp-total-indexed').text(data.total_indexed.toLocaleString());
			}

			// Update by_type counts
			if (data.by_type) {
				for (const [type, info] of Object.entries(data.by_type)) {
					const postType = type.replace('wp_', '');
					const $indexed = $('#wp-indexed-' + postType + ' .indexed-count');
					if ($indexed.length) {
						$indexed.text(info.indexed || 0);
					}
				}
			}

			// Get status elements
			const $statusElement = $('#wp-indexing-status');
			const $statusIcon = $statusElement.closest('.rag-stat-item').find('.stat-icon .dashicons');

			// Update status with spinner animation
			if (data.queue && data.queue.status === 'processing') {
				$statusElement.text(wpforoAIAdmin.strings?.indexing || 'Indexing...');
				// Add spinning animation to icon
				$statusIcon
					.removeClass('dashicons-saved')
					.addClass('dashicons-update wpforo-wp-indexing-spin');
				this.showWPProgress(data.queue);
			} else {
				$statusElement.text(wpforoAIAdmin.strings?.idle || 'Idle');
				// Stop spinning, show checkmark
				$statusIcon
					.removeClass('dashicons-update wpforo-wp-indexing-spin')
					.addClass('dashicons-saved');
				$('.wpforo-ai-wp-progress').hide();
			}
		},

		/**
		 * Show WordPress indexing progress bar
		 */
		showWPProgress: function(queue) {
			const $progress = $('.wpforo-ai-wp-progress');
			const percent = queue.total > 0 ? Math.round((queue.current / queue.total) * 100) : 0;

			$progress.show();
			$progress.find('.progress-fill').css('width', percent + '%');
			$progress.find('.progress-percent').text(percent + '%');
			$progress.find('.progress-status').text(
				(queue.indexed || 0) + ' indexed, ' + (queue.failed || 0) + ' failed'
			);
		},

		/**
		 * Handle taxonomy dropdown change - load terms as checkboxes
		 */
		handleTaxonomyChange: function(e) {
			const self = this;
			const taxonomy = $(e.currentTarget).val();
			const $termsContainer = $('#wp-terms-container');
			const $termsActions = $('#wp-terms-actions');
			const $indexBtn = $('.wpforo-ai-wp-index-taxonomy');

			if (!taxonomy) {
				$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Select a taxonomy first to load terms...</div>');
				$termsActions.hide();
				$indexBtn.prop('disabled', true);
				return;
			}

			$termsContainer.html('<div class="wpforo-ai-terms-loading"><span class="spinner is-active"></span> Loading terms...</div>');
			$termsActions.hide();

			// Note: post_types are not passed here - the backend will auto-detect
			// the post types that use this taxonomy and count only published posts
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_get_taxonomy_terms',
					security: wpforoAIAdmin.adminNonce,
					taxonomy: taxonomy
				},
				success: function(response) {
					if (response.success && response.data && response.data.terms) {
						const terms = response.data.terms;
						if (terms.length === 0) {
							$termsContainer.html('<div class="wpforo-ai-terms-placeholder">No terms found in this taxonomy.</div>');
							$termsActions.hide();
							$indexBtn.prop('disabled', true);
							return;
						}

						let html = '<div class="wpforo-ai-terms-checklist">';
						terms.forEach(function(term) {
							const indexed = term.indexed || 0;
							const total = term.count || 0;
							html += self.renderTermCheckbox(term, indexed, total, false);

							// Add children if any
							if (term.children && term.children.length) {
								term.children.forEach(function(child) {
									const childIndexed = child.indexed || 0;
									const childTotal = child.count || 0;
									html += self.renderTermCheckbox(child, childIndexed, childTotal, true);
								});
							}
						});
						html += '</div>';

						$termsContainer.html(html);
						$termsActions.show();

						// Bind checkbox change events
						$termsContainer.find('input[type="checkbox"]').on('change', function() {
							self.updateTermIndexButton();
						});

						self.updateTermIndexButton();
					} else {
						$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Error loading terms.</div>');
						$termsActions.hide();
					}
				},
				error: function() {
					$termsContainer.html('<div class="wpforo-ai-terms-placeholder">Error loading terms.</div>');
					$termsActions.hide();
				}
			});
		},

		/**
		 * Render a single term checkbox item
		 */
		renderTermCheckbox: function(term, indexed, total, isChild) {
			const itemClass = isChild ? 'wpforo-ai-term-checkbox-item wpforo-ai-term-child' : 'wpforo-ai-term-checkbox-item';
			return '<label class="' + itemClass + '">' +
				'<input type="checkbox" name="term_ids[]" value="' + term.term_id + '" data-count="' + total + '">' +
				'<span class="term-name">' + this.escapeHtml(term.name) + '</span>' +
				'<span class="wpforo-ai-term-info">(' + indexed + '/' + total + ')</span>' +
				'</label>';
		},

		/**
		 * Update the index button state based on selected terms
		 */
		updateTermIndexButton: function() {
			const $indexBtn = $('.wpforo-ai-wp-index-taxonomy');
			const checkedCount = $('#wp-terms-container input[type="checkbox"]:checked').length;
			$indexBtn.prop('disabled', checkedCount === 0);
		},

		/**
		 * Escape HTML special characters
		 */
		escapeHtml: function(text) {
			const div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		},

		/**
		 * Handle taxonomy-based indexing form submission
		 */
		handleWPTaxonomyIndex: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-taxonomy');
			const taxonomy = $form.find('#wp-taxonomy-select').val();

			// Collect all selected term IDs from checkboxes
			const termIds = [];
			$('#wp-terms-container input[type="checkbox"]:checked').each(function() {
				termIds.push($(this).val());
			});

			if (!taxonomy || termIds.length === 0) {
				alert('Please select a taxonomy and at least one term.');
				return;
			}

			// Get selected post types
			const postTypes = [];
			$('.wpforo-ai-wp-type-checkbox:checked').each(function() {
				postTypes.push($(this).val());
			});

			if (postTypes.length === 0) {
				alert('Please select at least one content type.');
				return;
			}

			$button.prop('disabled', true).text('Indexing...');

			// Build request data including optional date range
			const requestData = {
				action: 'wpforo_ai_wp_index_by_taxonomy',
				security: wpforoAIAdmin.adminNonce,
				taxonomy: taxonomy,
				term_ids: termIds,
				post_types: postTypes
			};

			// Add date range if specified
			const dateFrom = $form.find('#wp-tax-date-from').val();
			const dateTo = $form.find('#wp-tax-date-to').val();
			if (dateFrom) requestData.date_from = dateFrom;
			if (dateTo) requestData.date_to = dateTo;

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: requestData,
				success: function(response) {
					if (response.success) {
						alert('Indexing queued: ' + response.data.total_posts + ' posts in ' + response.data.batches + ' batches.');
						// Start polling for progress
						WpForoAI.loadWPIndexingStatus();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Terms');
				}
			});
		},

		/**
		 * Handle custom indexing form submission
		 */
		handleWPCustomIndex: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-custom');

			// Get selected post types from within this form
			const postTypes = [];
			$form.find('.wpforo-ai-wp-type-checkbox:checked').each(function() {
				postTypes.push($(this).val());
			});

			if (postTypes.length === 0) {
				alert('Please select at least one content type.');
				return;
			}

			const data = {
				action: 'wpforo_ai_wp_index_custom',
				security: wpforoAIAdmin.adminNonce,
				post_types: postTypes,
				date_from: $form.find('#wp-date-from').val(),
				date_to: $form.find('#wp-date-to').val()
			};

			$button.prop('disabled', true).text('Indexing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						alert('Indexing queued: ' + response.data.total_posts + ' posts in ' + response.data.batches + ' batches.');
						WpForoAI.loadWPIndexingStatus();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index Selected Content');
				}
			});
		},

		/**
		 * Handle index by specific IDs form submission
		 */
		handleWPIndexByIds: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);
			const $button = $form.find('.wpforo-ai-wp-index-ids');
			const postIds = $form.find('#wp-post-ids').val().trim();

			if (!postIds) {
				alert('Please enter at least one post ID.');
				return;
			}

			const data = {
				action: 'wpforo_ai_wp_index_custom',
				security: wpforoAIAdmin.adminNonce,
				post_ids: postIds
			};

			$button.prop('disabled', true).text('Indexing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						alert('Indexing queued: ' + response.data.total_posts + ' posts in ' + response.data.batches + ' batches.');
						WpForoAI.loadWPIndexingStatus();
						$form.find('#wp-post-ids').val(''); // Clear the field
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
					}
				},
				error: function() {
					alert('Error starting indexing. Please try again.');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Index by IDs');
				}
			});
		},

		/**
		 * Handle Clear WordPress index button
		 */
		handleWPClearIndex: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const confirmMessage = $button.data('confirm');

			if (!confirm(confirmMessage)) {
				return;
			}

			$button.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_wp_delete_content',
					security: wpforoAIAdmin.adminNonce,
					delete_all: 'true'
				},
				success: function(response) {
					if (response.success) {
						alert('WordPress index cleared successfully.');
						WpForoAI.loadWPIndexingStatus();
					} else {
						alert('Error: ' + (response.data?.message || 'Unknown error'));
					}
				},
				error: function() {
					alert('Error clearing index. Please try again.');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear WordPress Index');
				}
			});
		},

		/**
		 * Handle WordPress auto-indexing toggle change
		 */
		handleWPAutoIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const optionName = $input.data('option-name') || 'ai_wp_auto_indexing_enabled';
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_wp_indexing_option',
					nonce: wpforoAIAdmin.nonce,
					option_name: optionName,
					enabled: isEnabled
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (!response.success) {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert('Error: ' + (response.data?.message || 'Failed to save setting'));
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					$input.prop('checked', !isEnabled);
					alert('Error saving setting. Please try again.');
				}
			});
		},

		/**
		 * Handle WordPress image indexing toggle change
		 */
		handleWPImageIndexingToggle: function(e) {
			const $input = $(e.currentTarget);
			const isEnabled = $input.is(':checked') ? 1 : 0;
			const optionName = $input.data('option-name') || 'ai_wp_image_indexing_enabled';
			const $toggle = $input.closest('.wpforo-ai-auto-index-toggle');

			// Show confirmation when enabling (due to credit impact)
			if (isEnabled) {
				const confirmed = confirm(
					'Enable Image Indexing for WordPress Content?\n\n' +
					'When enabled, posts with images will consume +1 additional credit during indexing.\n\n' +
					'• Maximum 10 images per post are processed\n' +
					'• Images are converted to text descriptions for search\n' +
					'• Small images (< 50x50px) like smileys are skipped\n\n' +
					'Continue?'
				);
				if (!confirmed) {
					$input.prop('checked', false);
					return;
				}
			}

			// Disable the toggle during AJAX request
			$input.prop('disabled', true);
			$toggle.css('opacity', '0.6');

			// Save via AJAX
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_save_wp_indexing_option',
					nonce: wpforoAIAdmin.nonce,
					option_name: optionName,
					enabled: isEnabled
				},
				success: function(response) {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					if (!response.success) {
						// Revert the change on failure
						$input.prop('checked', !isEnabled);
						alert('Error: ' + (response.data?.message || 'Failed to save setting'));
					}
				},
				error: function() {
					$input.prop('disabled', false);
					$toggle.css('opacity', '1');
					$input.prop('checked', !isEnabled);
					alert('Error saving setting. Please try again.');
				}
			});
		},

		/**
		 * Initialize tag autocomplete using WordPress suggest script
		 */
		initTagSuggest: function() {
			var $tagInput = $('.wpforo-ai-tags-input');
			if ($tagInput.length && typeof $.fn.suggest === 'function' && typeof wpforoAIAdmin !== 'undefined') {
				var ajaxUrl = wpforoAIAdmin.ajaxUrl;
				$tagInput.suggest(
					ajaxUrl + (ajaxUrl.indexOf('?') !== -1 ? '&' : '?') + 'action=wpforo_tag_search',
					{
						multiple: true,
						multipleSep: ',',
						delay: 500,
						minchars: 2,
						resultsClass: 'wpforo-ai-tag-results',
						selectClass: 'wpforo-ai-tag-over',
						matchClass: 'wpforo-ai-tag-match'
					}
				);
			}
		},

		/**
		 * Initialize Bot User Search autocomplete for AI Bot Reply settings
		 */
		initBotUserSearch: function() {
			const self = this;
			const $searchInput = $('#wpforo-ai-bot-user-search');

			// Only init if the search input exists (settings page with Bot Reply section)
			if (!$searchInput.length) {
				return;
			}

			const $wrapper = $searchInput.closest('.wpforo-ai-user-search-wrapper');
			const $hiddenInput = $wrapper.find('.wpforo-ai-user-id-input');
			const $resultsContainer = $wrapper.find('.wpforo-ai-user-search-results');
			const nonce = $('#wpforo_ai_bot_user_nonce').val() || '';
			let searchTimeout = null;

			// Handle input for search
			$searchInput.on('input', function() {
				const searchTerm = $(this).val().trim();

				// Clear previous timeout
				if (searchTimeout) {
					clearTimeout(searchTimeout);
				}

				// Clear results if search term is too short
				if (searchTerm.length < 2) {
					$resultsContainer.empty().hide();
					return;
				}

				// Debounce the search
				searchTimeout = setTimeout(function() {
					self.searchBotUsers(searchTerm, $resultsContainer, $hiddenInput, $searchInput, nonce);
				}, 300);
			});

			// Handle click outside to close results
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.wpforo-ai-user-search-wrapper').length) {
					$resultsContainer.empty().hide();
				}
			});

			// Handle focus to show results if there's a search term
			$searchInput.on('focus', function() {
				if ($(this).val().trim().length >= 2 && $resultsContainer.children().length > 0) {
					$resultsContainer.show();
				}
			});
		},

		/**
		 * Perform AJAX search for bot users
		 */
		searchBotUsers: function(searchTerm, $resultsContainer, $hiddenInput, $searchInput, nonce) {
			$resultsContainer.html('<div class="wpforo-ai-user-search-loading">Searching...</div>').show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_search_bot_users',
					search: searchTerm,
					_wpnonce: nonce
				},
				success: function(response) {
					$resultsContainer.empty();

					if (response.success && response.data.users && response.data.users.length > 0) {
						const $list = $('<ul class="wpforo-ai-user-search-list"></ul>');

						response.data.users.forEach(function(user) {
							const $item = $('<li class="wpforo-ai-user-search-item" data-user-id="' + user.id + '"></li>');
							$item.text(user.label);
							$item.on('click', function() {
								$hiddenInput.val(user.id);
								$searchInput.val(user.label);
								$resultsContainer.empty().hide();
								// Clear usergroup when specific user is selected
								$hiddenInput.closest('.wpforo-ai-form-section').find('.wpforo-ai-author-groupid-select').val('');
							});
							$list.append($item);
						});

						$resultsContainer.append($list).show();
					} else {
						$resultsContainer.html('<div class="wpforo-ai-user-search-empty">No users found</div>').show();
					}
				},
				error: function() {
					$resultsContainer.html('<div class="wpforo-ai-user-search-error">Search error</div>').show();
				}
			});
		},

		/**
		 * Initialize character counters for textareas with limits
		 * Uses proper character counting that works with multibyte characters
		 */
		initCharCounters: function() {
			const self = this;

			// Find all textareas with data-char-limit attribute
			$(document).on('input', 'textarea[data-char-limit]', function() {
				self.updateCharCounter($(this));
			});

			// Also handle when form fields are populated (e.g., when editing a task)
			$(document).on('wpforo-ai-task-loaded', function() {
				$('textarea[data-char-limit]').each(function() {
					self.updateCharCounter($(this));
				});
			});

			// Initialize counters on page load
			$('textarea[data-char-limit]').each(function() {
				self.updateCharCounter($(this));
			});
		},

		/**
		 * Update character counter for a textarea
		 * Uses string spread operator for proper Unicode character counting
		 */
		updateCharCounter: function($textarea) {
			const limit = parseInt($textarea.data('char-limit'), 10) || 120;
			const $counter = $textarea.siblings('.wpforo-ai-char-counter').find('.current');
			const $counterWrapper = $textarea.siblings('.wpforo-ai-char-counter');

			if (!$counter.length) {
				return;
			}

			// Use spread operator to properly count Unicode characters (multibyte safe)
			const text = $textarea.val() || '';
			const charCount = [...text].length;

			$counter.text(charCount);

			// Update counter styling based on proximity to limit
			$counterWrapper.removeClass('warning limit');
			if (charCount >= limit) {
				$counterWrapper.addClass('limit');
			} else if (charCount >= limit * 0.8) {
				$counterWrapper.addClass('warning');
			}

			// Enforce limit (multibyte safe truncation)
			if (charCount > limit) {
				const truncated = [...text].slice(0, limit).join('');
				$textarea.val(truncated);
				$counter.text(limit);
				$counterWrapper.addClass('limit');
			}
		},

		/**
		 * Scroll to the Indexing Status section
		 */
		scrollToIndexingStatus: function() {
			const $statusBox = $('.wpforo-ai-rag-status-box');
			if ($statusBox.length) {
				$('html, body').animate({
					scrollTop: $statusBox.offset().top - 50
				}, 500);
			}
		},

		/**
		 * Handle Re-Index All button click
		 */
		handleReindexAll: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const confirmMessage = $button.data('confirm');

			if (!confirm(confirmMessage)) {
				return;
			}

			// Scroll to status section
			this.scrollToIndexingStatus();

			// Check if we're in local storage mode
			if (this.isLocalStorageMode()) {
				// Use AJAX-driven batch processing for local mode
				this.startLocalIndexing($button);
			} else {
				// Use form submission for cloud mode
				this.submitRAGAction('reindex_all', $button);
			}
		},

		/**
		 * Handle Re-Index Topic Images button click
		 * Only re-indexes topics that contain images
		 */
		handleReindexImages: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const confirmMessage = $button.data('confirm');

			if (!confirm(confirmMessage)) {
				return;
			}

			// Scroll to status section
			this.scrollToIndexingStatus();

			// Check if we're in local storage mode
			if (this.isLocalStorageMode()) {
				// Use AJAX-driven batch processing for local mode with images_only flag
				this.startLocalIndexing($button, { images_only: true });
			} else {
				// Use form submission for cloud mode with images_only flag
				this.submitRAGAction('reindex_images', $button);
			}
		},

		/**
		 * Handle Clear Database button click
		 */
		handleClearDatabase: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const confirmMessage = 'WARNING: This will permanently delete all indexed data.\n\nType "DELETE" to confirm:';

			const userInput = prompt(confirmMessage);

			if (userInput !== 'DELETE') {
				if (userInput !== null) {
					alert('Confirmation failed. Database was not cleared.');
				}
				return;
			}

			// Create and submit form with confirmation value
			this.submitRAGAction('clear_database', $button, { confirm: userInput });
		},

		/**
		 * Handle Clear & Re-Index button click
		 */
		handleClearAndReindex: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const confirmMessage = 'This will:\n1. Clear all indexed data\n2. Re-index all topics\n\nType "CONFIRM" to proceed:';

			const userInput = prompt(confirmMessage);

			if (userInput !== 'CONFIRM') {
				if (userInput !== null) {
					alert('Confirmation failed. Operation cancelled.');
				}
				return;
			}

			// Check if we're in local storage mode
			if (this.isLocalStorageMode()) {
				// Use AJAX-driven process for local mode
				this.clearAndReindexLocal($button);
			} else {
				// Use form submission for cloud mode
				this.submitRAGAction('clear_and_reindex', $button);
			}
		},

		/**
		 * Clear and re-index for local storage mode via AJAX
		 */
		clearAndReindexLocal: function($button) {
			const self = this;

			// Show loading state
			$button.addClass('loading').prop('disabled', true);
			$button.html('<span class="dashicons dashicons-update wpforo-spin"></span> Clearing...');

			// First clear local embeddings
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_action',
					wpforo_ai_action: 'clear_local_embeddings',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						console.log('Local embeddings cleared:', response.data);
						// Now start the indexing
						self.startLocalIndexing($button);
					} else {
						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Failed to clear embeddings';
						alert('Error: ' + errorMsg);
						$button.removeClass('loading').prop('disabled', false);
						$button.html('<span class="dashicons dashicons-trash"></span> Clear & Re-Index');
					}
				},
				error: function(xhr, status, error) {
					console.error('Clear local embeddings error:', error);
					alert('Error clearing embeddings: ' + error);
					$button.removeClass('loading').prop('disabled', false);
					$button.html('<span class="dashicons dashicons-trash"></span> Clear & Re-Index');
				}
			});
		},

		/**
		 * Handle Stop Indexing button click
		 */
		handleStopIndexing: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const confirmMessage = $button.data('confirm');

			if (!confirm(confirmMessage)) {
				return;
			}

			// Set stopping flag so status shows "Stopping..." while process winds down
			// Use localStorage to persist across page reloads
			this.indexingStopping = true;
			localStorage.setItem('wpforo_indexing_stopping', 'true');

			// Immediately update status to show "Stopping..."
			const $statusElement = $('#rag-indexing-status');
			if ($statusElement.length) {
				$statusElement.text('Stopping...');
			}

			// Check if we're in local storage mode with AJAX indexing
			if (this.isLocalStorageMode() && this.localIndexingState) {
				// Stop the AJAX-driven indexing loop (this updates UI)
				this.stopLocalIndexing();
				// Clear the queue on the server via AJAX (no page reload)
				this.clearLocalIndexingQueue();
			} else {
				// Cloud mode: tell the backend to stop the image_worker
				// draining queued media jobs. Polling will pick up the
				// drained state via the regular /rag/status poll.
				this.cancelCloudIndexing();
			}
		},

		/**
		 * Tell the backend to stop in-flight cloud indexing (image worker).
		 * No page reload — polling will pick up the drained state.
		 */
		cancelCloudIndexing: function() {
			const self = this;
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_cancel_cloud_indexing',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					console.log('Cloud indexing cancel requested:', response);
				},
				error: function(xhr, status, error) {
					console.error('Failed to cancel cloud indexing:', error);
					// Clear the stopping flag so the user can retry
					self.indexingStopping = false;
					localStorage.removeItem('wpforo_indexing_stopping');
				}
			});
		},

		/**
		 * Handle "Cleanup Indexing Session" button clicks.
		 *
		 * Resets stuck indexing state (queues, WP-Cron jobs, transient locks,
		 * status caches) without touching any already-indexed data. Works for
		 * both local and cloud storage modes — the backend cleans up both
		 * queue keys in one call and also tells the cloud image_worker to
		 * drop any in-flight messages.
		 *
		 * Also clears the browser-side localStorage stopping flag so the UI
		 * doesn't get stuck on "Stopping..." after the cleanup.
		 *
		 * data-scope on the button is 'forum' or 'wp'.
		 */
		handleCleanupSession: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const scope = $button.data('scope') || 'forum';
			const confirmMsg = $button.data('confirm') || 'Reset stuck indexing session?';

			if (!window.confirm(confirmMsg)) {
				return;
			}

			const originalHtml = $button.html();
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Cleaning up...');

			// Clear any browser-side stuck state first — regardless of AJAX
			// outcome. This is the only client-side flag the plugin sets for
			// indexing (see handleStopIndexing / checkLocalIndexingProgress).
			try {
				localStorage.removeItem('wpforo_indexing_stopping');
			} catch (err) { /* localStorage may be blocked in some contexts */ }
			this.indexingStopping = false;

			const self = this;
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_cleanup_indexing_session',
					scope: scope,
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).html(originalHtml);
					if (response && response.success) {
						// Reload to refresh all server-rendered counts and
						// flip the UI out of "Indexing..." state cleanly.
						window.location.reload();
					} else {
						const msg = (response && response.data && response.data.message) || 'Cleanup failed.';
						window.alert(msg);
					}
				},
				error: function(xhr, status, error) {
					$button.prop('disabled', false).html(originalHtml);
					console.error('Cleanup indexing session failed:', error);
					window.alert('Cleanup failed. Check the browser console for details.');
				}
			});
		},

		/**
		 * Clear local indexing queue via AJAX (no page reload)
		 */
		clearLocalIndexingQueue: function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_action',
					wpforo_ai_action: 'stop_local_indexing',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					console.log('Local indexing queue cleared:', response);
				},
				error: function(xhr, status, error) {
					console.error('Failed to clear queue:', error);
				}
			});
		},

		/**
		 * Submit RAG action form
		 */
		submitRAGAction: function(action, $button, additionalData) {
			// Create hidden form
			const $form = $('<form>', {
				method: 'post',
				action: ''
			});

			// Add nonce - get from button's data-nonce attribute
			const nonceName = 'wpforo_ai_' + action;
			const nonceValue = $button.data('nonce'); // Get from button data attribute

			$form.append($('<input>', {
				type: 'hidden',
				name: '_wpnonce',
				value: nonceValue
			}));

			// Add action
			$form.append($('<input>', {
				type: 'hidden',
				name: 'wpforo_ai_action',
				value: action
			}));

			// Add chunking configuration parameters for reindex actions
			if (action === 'reindex_all' || action === 'clear_and_reindex') {
				const chunkSize = $('#wpforo-ai-chunk-size').val() || 1000;
				const overlapPercent = $('#wpforo-ai-overlap-percent').val() || 20;

				$form.append($('<input>', {
					type: 'hidden',
					name: 'chunk_size',
					value: chunkSize
				}));

				$form.append($('<input>', {
					type: 'hidden',
					name: 'overlap_percent',
					value: overlapPercent
				}));
			}

			// Add additional data if provided
			if (additionalData) {
				$.each(additionalData, function(key, value) {
					$form.append($('<input>', {
						type: 'hidden',
						name: key,
						value: value
					}));
				});
			}

			// Add loading state to button
			$button.addClass('loading').prop('disabled', true);

			// Append form to body and submit
			$('body').append($form);
			$form.submit();
		},

		/**
		 * Refresh RAG status via AJAX
		 */
		refreshRAGStatus: function() {
			const self = this;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_rag_status',
					_wpnonce: self.ajaxNonce || $('#_wpnonce').val()
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateRAGStatusDisplay(response.data);
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to refresh RAG status:', error);
				}
			});
		},

		/**
		 * Update RAG status display
		 */
		updateRAGStatusDisplay: function(data) {
			// Update total topics indexed (threads) count - sync all displays
			if (typeof data.total_topics !== 'undefined') {
				const formattedTopics = this.formatNumber(data.total_topics);
				$('#rag-total-topics').text(formattedTopics);
				$('#local-total-topics').text(formattedTopics);
				$('#index-total-indexed').text(formattedTopics);

				// Update remaining to index
				const $totalTopicsCount = $('#index-total-topics-count');
				if ($totalTopicsCount.length) {
					const totalCount = parseInt($totalTopicsCount.text().replace(/,/g, ''), 10) || 0;
					const indexed = data.total_topics;
					const remaining = Math.max(0, totalCount - indexed);
					const $remainingEl = $('#index-remaining');
					$remainingEl.text(this.formatNumber(remaining));
					if (remaining === 0) {
						$remainingEl.addClass('stat-success');
					} else {
						$remainingEl.removeClass('stat-success');
					}
				}
			}

			// Update local storage stats if available
			if (typeof data.total_indexed !== 'undefined') {
				$('#local-total-embeddings').text(this.formatNumber(data.total_indexed));
			}
			if (typeof data.storage_size_mb !== 'undefined') {
				$('#local-storage-size').text(data.storage_size_mb + ' MB');
			}

			// Update credits if available in response
			if (typeof data.credits_remaining !== 'undefined') {
				$('#index-credits-available').text(this.formatNumber(data.credits_remaining));
			}

			// Update indexing status — only show spinner when backend is actively indexing
			// or a cron batch is actively running. Queued topics with a future schedule
			// (e.g. 24h auto-indexing delay) should NOT trigger the spinner.
			if (typeof data.is_indexing !== 'undefined') {
				const $statusElement = $('#rag-indexing-status');
				const $statusIcon = $statusElement.closest('.rag-stat-item').find('.dashicons');
				const cronActive = data.pending_cron_jobs && data.pending_cron_jobs.is_actively_processing;
				const isActivelyProcessing = data.is_indexing || cronActive;

				// Track previous state to detect completion
				const wasProcessing = this.previousProcessingState === true;
				this.previousProcessingState = isActivelyProcessing;

				if (isActivelyProcessing) {
					// Show indexing state
					let statusText;
					if (this.indexingStopping) {
						statusText = 'Stopping...';
					} else {
						statusText = 'Indexing...';
					}
					$statusElement
						.text(statusText)
						.removeClass('status-idle')
						.addClass('status-active');
					$statusIcon
						.removeClass('dashicons-saved')
						.addClass('dashicons-update-alt wpforo-rag-status-spin');
				} else {
					// Clear stopping flag when process is fully stopped
					this.indexingStopping = false;
					localStorage.removeItem('wpforo_indexing_stopping');

					$statusElement
						.text('Idle')
						.removeClass('status-active')
						.addClass('status-idle');
					$statusIcon
						.removeClass('dashicons-update-alt wpforo-rag-status-spin')
						.addClass('dashicons-saved');

					// Stop polling when backend is no longer indexing
					this.stopRAGStatusPolling();

					// Reload page when processing completes to refresh all counts
					if (wasProcessing) {
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					}
				}
			}

			// Update queued topics count in the "Total Threads Indexed" stat
			if (data.pending_cron_jobs) {
				const pendingTopics = data.pending_cron_jobs.pending_topics || 0;
				const $queuedCount = $('#rag-total-topics .rag-queued-count');
				if (pendingTopics > 0) {
					if ($queuedCount.length) {
						$queuedCount.text('| ' + this.formatNumber(pendingTopics) + ' queued...');
					} else {
						$('#rag-total-topics').append(' <small class="rag-queued-count">| ' + this.formatNumber(pendingTopics) + ' queued...</small>');
					}
				} else {
					$queuedCount.remove();
				}
			}

			// Update queue info
			if (typeof data.queue_info !== 'undefined') {
				$('#rag-queue-pending').text(data.queue_info.pending || 0);
				$('#rag-queue-processing').text(data.queue_info.processing || 0);
				$('#rag-queue-failed').text(data.queue_info.failed || 0);

				// Show/hide queue info box
				if (data.is_indexing) {
					$('.wpforo-ai-queue-info').show();
				} else {
					$('.wpforo-ai-queue-info').hide();
				}
			}

			// Async media (image/document) sub-progress.
			// Present when the backend image_worker has queued work. The
			// element is created on demand and lives inside the queue-info
			// box so it inherits existing styling.
			this.renderMediaProgress(data.media_progress);

			// Update last indexed timestamp
			if (typeof data.last_indexed_at !== 'undefined' && data.last_indexed_at) {
				$('#rag-last-indexed').text(data.last_indexed_at);
			}
		},

		/**
		 * Render the async media (image/document) sub-progress line.
		 *
		 * The backend image_worker processes images and documents out-of-band
		 * from text ingestion. This function creates (on first call) and
		 * updates a small status line showing "Media: done/total processed"
		 * inside the existing queue-info box. When no media work is in
		 * flight the element is hidden.
		 *
		 * @param {Object|null} mediaProgress {total, done, failed, skipped_cancelled, in_flight, progress_percent}
		 */
		renderMediaProgress: function(mediaProgress) {
			const $container = $('#wpforo-ai-queue-info');
			const $existing = $('#rag-media-progress');

			if (!mediaProgress || !mediaProgress.total) {
				$existing.hide();
				return;
			}

			let $el = $existing;
			if (!$el.length) {
				if (!$container.length) {
					return;
				}
				$el = $('<div id="rag-media-progress" class="wpforo-ai-media-progress"></div>');
				$container.append($el);
			}

			const done = parseInt(mediaProgress.done, 10) || 0;
			const total = parseInt(mediaProgress.total, 10) || 0;
			const failed = parseInt(mediaProgress.failed, 10) || 0;
			const skipped = parseInt(mediaProgress.skipped_cancelled, 10) || 0;
			const percent = parseInt(mediaProgress.progress_percent, 10) || 0;

			// Hardcoded English to match surrounding status strings
			// ('Indexing...', 'Stopping...', 'Idle'). No JS i18n layer here.
			const label = mediaProgress.in_flight ? 'Processing media' : 'Media processed';

			let line = label + ': ' + done + ' / ' + total + ' (' + percent + '%)';
			if (failed > 0) {
				line += ' — ' + failed + ' failed';
			}
			if (skipped > 0) {
				line += ' — ' + skipped + ' skipped';
			}

			$el.text(line).show();
		},

		/**
		 * Start polling for RAG status updates
		 */
		startRAGStatusPolling: function() {
			const self = this;

			// Initialize state tracking - assume processing is active when polling starts
			this.previousProcessingState = true;

			// Poll every 10 seconds while processing is active
			this.ragStatusInterval = setInterval(function() {
				self.refreshRAGStatus();
			}, 10000);

			// Safety timeout after 2 hours (in case of stuck state)
			// Normal completion will stop polling via stopRAGStatusPolling() when processing completes
			this.ragSafetyTimeout = setTimeout(function() {
				console.log('RAG polling safety timeout reached (2 hours). Stopping polling.');
				self.stopRAGStatusPolling();
				// Reload page to get fresh state
				window.location.reload();
			}, 7200000); // 2 hours
		},

		/**
		 * Stop polling for RAG status updates
		 */
		stopRAGStatusPolling: function() {
			if (this.ragStatusInterval) {
				clearInterval(this.ragStatusInterval);
				this.ragStatusInterval = null;
			}
			// Also clear safety timeout if it exists
			if (this.ragSafetyTimeout) {
				clearTimeout(this.ragSafetyTimeout);
				this.ragSafetyTimeout = null;
			}
		},

		/**
		 * Handle search test form submission
		 */
		handleSearchTest: function(e) {
			e.preventDefault();

			const self = this;
			const $form = $(e.currentTarget);
			const $button = $form.find('#search-test-btn');
			const $spinner = $form.find('.spinner');
			const $results = $('#search-test-results');
			const $resultsContent = $('#search-results-content');

			const query = $form.find('#search-query').val().trim();
			const limit = parseInt($form.find('#search-limit').val()) || 5;

			if (!query) {
				alert('Please enter a search query.');
				return;
			}

			// Show loading state with "Searching..." text
			$button.prop('disabled', true).addClass('loading');
			$button.html('<span class="dashicons dashicons-search"></span> Searching...');
			$spinner.addClass('is-active');
			$results.hide();
			$resultsContent.html('');

			// Perform AJAX search
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_semantic_search',
					_wpnonce: self.ajaxNonce || $('#wpforo-ai-search-test-form #_wpnonce').val(),
					query: query,
					limit: limit
				},
				success: function(response) {
					if (response.success && response.data) {
						self.displaySearchResults(response.data, query);
					} else {
						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Search failed. Please try again.';
						$resultsContent.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
						$results.show();
					}
				},
				error: function(xhr, status, error) {
					console.error('Search error:', error, xhr);

					// Try to extract the actual error message from the response
					let errorMsg = 'Search request failed: ' + error;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}

					$resultsContent.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
					$results.show();
				},
				complete: function() {
					// Always reset button state, even on error
					$button.prop('disabled', false);
					$button.removeClass('loading');
					$button.html('<span class="dashicons dashicons-search"></span> Test Search');
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Display search results
		 */
		displaySearchResults: function(data, query) {
			const $resultsContent = $('#search-results-content');
			const $results = $('#search-test-results');

			// Clear previous results
			$resultsContent.html('');

			// Show query info
			const queryInfo = $('<div class="search-query-info">')
				.append($('<p>').html(
					'<strong>Query:</strong> "' + this.escapeHtml(query) + '" | ' +
					'<strong>Results:</strong> ' + data.total + ' found | ' +
					'<strong>Time:</strong> ' + data.query_time_ms + 'ms'
				));
			$resultsContent.append(queryInfo);

			// Show credit status if available
			if (data.credit_status && data.credit_status.credits) {
				const creditInfo = $('<div class="search-credit-info notice notice-info inline">')
					.append($('<p>').html(
						'<strong>Credits Remaining:</strong> ' + data.credit_status.credits.remaining + ' / ' + data.credit_status.credits.total +
						' (' + data.credit_status.credits.usage_percent.toFixed(1) + '% used)'
					));
				$resultsContent.append(creditInfo);
			}

			// Display results
			if (data.results && data.results.length > 0) {
				const $resultsList = $('<div class="search-results-list">');

				data.results.forEach(function(result, index) {
					const $resultItem = $('<div class="search-result-item">');

					// Result header with rank and score
					$resultItem.append(
						$('<div class="result-header">').html(
							'<strong>#' + (index + 1) + '</strong> - Score: ' + (result.score * 100).toFixed(1) + '%'
						)
					);

					// Title and excerpt
					$resultItem.append($('<h4 class="result-title">').text(result.title));
					$resultItem.append($('<p class="result-excerpt">').text(result.excerpt));

					// Generate post-specific URL if chunk_post_id is available
					let postUrl = result.url; // Default to topic URL
					if (result.metadata && result.metadata.chunk_post_id) {
						// Build post-specific URL: /community/postid/{id}/
						// Extract base forum URL from topic_url
						const topicUrl = result.metadata.topic_url || result.url;
						if (topicUrl) {
							// Extract everything up to and including /community/
							const match = topicUrl.match(/^(.*\/community\/)/);
							if (match) {
								const baseUrl = match[1];
								postUrl = baseUrl + 'postid/' + result.metadata.chunk_post_id + '/';
							}
						}
					}

					// URL (show post-specific URL if available, otherwise topic URL)
					if (postUrl) {
						const urlLabel = result.metadata && result.metadata.chunk_post_id ? 'Post URL' : 'Topic URL';
						$resultItem.append(
							$('<p class="result-url">').html(
								'<strong>' + urlLabel + ':</strong> ' +
								'<a href="' + this.escapeHtml(postUrl) + '" target="_blank" class="button button-small">' +
								'View Post →</a> ' +
								'<code style="margin-left: 10px;">' + this.escapeHtml(postUrl) + '</code>'
							)
						);
					}

					// Metadata as formatted JSON
					if (result.metadata) {
						const $metadataBox = $('<div class="result-metadata">');
						$metadataBox.append($('<strong>').text('Metadata:'));
						$metadataBox.append($('<pre>').text(JSON.stringify(result.metadata, null, 2)));
						$resultItem.append($metadataBox);
					}

					// Full result JSON (collapsible) - only show in debug mode
					if (typeof wpforoAIAdmin !== 'undefined' && wpforoAIAdmin.debugMode) {
						const $fullJsonToggle = $('<button class="button button-small toggle-json-btn" type="button">')
							.text('Show Full JSON')
							.on('click', function() {
								const $this = $(this);
								const $jsonBox = $this.next('.result-full-json');
								if ($jsonBox.is(':visible')) {
									$jsonBox.hide();
									$this.text('Show Full JSON');
								} else {
									$jsonBox.show();
									$this.text('Hide Full JSON');
								}
							});

						const $fullJson = $('<div class="result-full-json" style="display:none;">');
						$fullJson.append($('<pre>').text(JSON.stringify(result, null, 2)));

						$resultItem.append($fullJsonToggle);
						$resultItem.append($fullJson);
					}

					$resultsList.append($resultItem);
				}.bind(this));

				$resultsContent.append($resultsList);
			} else {
				$resultsContent.append(
					$('<div class="notice notice-warning"><p>No results found for your query.</p></div>')
				);
			}

			// Show results container
			$results.show();
		},

		// =====================================================
		// Local Storage AJAX-Driven Indexing
		// =====================================================

		/**
		 * Check if we're in local storage mode
		 */
		isLocalStorageMode: function() {
			const $localRadio = $('input[name="wpforo_ai_storage_mode"][value="local"]');
			// If radio buttons don't exist (cloud storage feature not available),
			// the storage mode is always local (default) — return true
			if (!$localRadio.length) {
				return true;
			}
			return $localRadio.is(':checked');
		},

		/**
		 * Start local indexing process via AJAX
		 * @param {jQuery} $button - The button that triggered the indexing
		 * @param {Object} options - Optional parameters (images_only: bool)
		 */
		startLocalIndexing: function($button, options) {
			options = options || {};

			// Get settings from the form (pagination_size is used as batch size)
			const chunkSize = $('#wpforo-ai-chunk-size').val() || 512;
			const overlapPercent = $('#wpforo-ai-overlap-percent').val() || 20;
			const batchSize = $('#wpforo-ai-pagination-size').val() || 10;

			// Store original button HTML for restoration later
			if (!$button.data('original-html')) {
				$button.data('original-html', $button.html());
			}

			// Show loading state
			$button.addClass('loading').prop('disabled', true);
			$button.html('<span class="dashicons dashicons-update wpforo-spin"></span> Starting...');

			// Build AJAX data
			const ajaxData = {
				action: 'wpforo_ai_action',
				wpforo_ai_action: 'start_local_indexing',
				_wpnonce: wpforoAIAdmin.nonce,
				chunk_size: chunkSize,
				overlap_percent: overlapPercent,
				batch_size: batchSize
			};

			// Add images_only flag if set
			if (options.images_only) {
				ajaxData.images_only = 1;
			}

			// Call the start_local_indexing AJAX action
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: ajaxData,
				success: function(response) {
					if (response.success) {
						console.log('Local indexing started:', response.data);

						// Reload page — checkLocalIndexingProgress() will detect
						// the queue on load and start the AJAX batch loop
						window.location.reload();
					} else {
						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Failed to start indexing';
						alert('Error: ' + errorMsg);
						$button.removeClass('loading').prop('disabled', false).show();
						if ($button.data('original-html')) {
							$button.html($button.data('original-html'));
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('Start local indexing error:', error);
					alert('Error starting indexing: ' + error);
					$button.removeClass('loading').prop('disabled', false).show();
					if ($button.data('original-html')) {
						$button.html($button.data('original-html'));
					}
				}
			});
		},

		/**
		 * Process local indexing batches in a loop
		 */
		processLocalBatches: function($button) {
			const self = this;

			// Guard against concurrent calls (e.g., page reload while previous request in-flight)
			if (this._batchProcessing) {
				return;
			}

			// Check if indexing was stopped
			if (this.localIndexingStopped) {
				this.localIndexingStopped = false;
				// Buttons already reset by stopLocalIndexing()
				return;
			}

			this._batchProcessing = true;

			// Call the process_local_batch AJAX action
			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_action',
					wpforo_ai_action: 'process_local_batch',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					self._batchProcessing = false;

					if (response.success) {
						const data = response.data;
						console.log('Batch processed:', data);

						// Handle 'wait' action — another process is indexing, retry
						if (data.action === 'wait') {
							setTimeout(function() {
								self.processLocalBatches($button);
							}, 2000);
							return;
						}

						// Update state
						self.localIndexingState.processed = data.processed;
						self.localIndexingState.remaining = data.remaining;

						if (data.errors && data.errors.length > 0) {
							self.localIndexingState.errors = self.localIndexingState.errors.concat(data.errors);
						}

						// Update UI
						self.updateLocalIndexingUI(data);

						// Check if done
						if (data.done) {
							self.finishLocalIndexing($button, data);
						} else {
							// Continue processing next batch after a short delay
							setTimeout(function() {
								self.processLocalBatches($button);
							}, 500); // 500ms delay between batches
						}
					} else {
						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Batch processing failed';
						console.error('Batch error:', errorMsg);

						// Check for credits exhausted - stop immediately
						if (response.data && (response.data.action === 'credits_exhausted' || (errorMsg && errorMsg.indexOf('nsufficient credits') !== -1))) {
							self.localIndexingState.processed = response.data.processed || 0;
							self.localIndexingState.remaining = 0;
							self.updateLocalIndexingUI(response.data);
							self.finishLocalIndexing($button, {
								errors: [errorMsg],
								credits_exhausted: true
							});
							return;
						}

						// Try to continue if there are remaining items
						if (self.localIndexingState.remaining > 0) {
							self.localIndexingState.errors.push(errorMsg);
							self.updateLocalIndexingUI(self.localIndexingState);
							setTimeout(function() {
								self.processLocalBatches($button);
							}, 1000);
						} else {
							self.finishLocalIndexing($button, { errors: [errorMsg] });
						}
					}
				},
				error: function(xhr, status, error) {
					self._batchProcessing = false;
					console.error('Process batch error:', error);

					// Check response body for credits_exhausted
					try {
						var responseData = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
						if (responseData.data && responseData.data.action === 'credits_exhausted') {
							self.finishLocalIndexing($button, {
								errors: [responseData.data.message || 'Insufficient credits'],
								credits_exhausted: true
							});
							return;
						}
					} catch(e) {}

					// Retry after a delay if there are remaining items
					if (self.localIndexingState.remaining > 0) {
						self.localIndexingState.errors.push('Network error: ' + error);
						setTimeout(function() {
							self.processLocalBatches($button);
						}, 2000);
					} else {
						self.finishLocalIndexing($button, { errors: ['Network error: ' + error] });
					}
				}
			});
		},

		/**
		 * Show local indexing progress UI
		 */
		showLocalIndexingProgress: function() {
			const state = this.localIndexingState;

			// Create or update progress container
			let $progress = $('#wpforo-local-indexing-progress');
			if (!$progress.length) {
				$progress = $('<div id="wpforo-local-indexing-progress" class="notice notice-info">' +
					'<p><strong>Local Indexing in Progress</strong></p>' +
					'<div class="progress-bar-container" style="width: 100%; height: 20px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">' +
					'<div class="progress-bar" style="width: 0%; height: 100%; background: #0073aa; transition: width 0.3s;"></div>' +
					'</div>' +
					'<p class="progress-text">Processed: <span class="processed">0</span> / <span class="total">' + state.total + '</span> topics</p>' +
					'<p class="error-text" style="color: #d63638; display: none;">Errors: <span class="error-count">0</span></p>' +
					'</div>');

				// Insert before the action buttons
				$('.wpforo-ai-bulk-actions').before($progress);
			}

			$progress.find('.total').text(state.total);
			$progress.show();

			// Update status indicator (same as cloud indexing)
			const $statusElement = $('#rag-indexing-status');
			const $statusIcon = $statusElement.closest('.rag-stat-item').find('.dashicons');
			$statusElement
				.text('Indexing...')
				.removeClass('status-idle')
				.addClass('status-active');
			$statusIcon
				.removeClass('dashicons-saved')
				.addClass('dashicons-update-alt wpforo-rag-status-spin');

			// Show existing stop button, hide reindex button
			$('.wpforo-ai-reindex-all').hide();
			$('.wpforo-ai-stop-indexing').show();
		},

		/**
		 * Update local indexing progress UI
		 */
		updateLocalIndexingUI: function(data) {
			const state = this.localIndexingState;
			const $progress = $('#wpforo-local-indexing-progress');

			if (!$progress.length) return;

			const processed = data.processed || state.processed;
			const total = state.total;
			const percent = total > 0 ? Math.round((processed / total) * 100) : 0;

			$progress.find('.progress-bar').css('width', percent + '%');
			$progress.find('.processed').text(this.formatNumber(processed));
			$progress.find('.total').text(this.formatNumber(total));

			// Show errors if any
			const errorCount = state.errors.length;
			if (errorCount > 0) {
				$progress.find('.error-text').show().find('.error-count').text(errorCount);
			}

			// Update stats on the page
			const remaining = total - processed;
			$('#index-remaining').text(this.formatNumber(remaining));
			$('#index-total-indexed').text(this.formatNumber(processed));
			$('#rag-total-topics').text(this.formatNumber(processed));

			// Update credits if available
			if (typeof data.credits_remaining !== 'undefined') {
				$('#index-credits-available').text(this.formatNumber(data.credits_remaining));
			}
		},

		/**
		 * Finish local indexing
		 */
		finishLocalIndexing: function($button, data) {
			const self = this;
			const state = this.localIndexingState;
			const $progress = $('#wpforo-local-indexing-progress');

			// Calculate elapsed time
			const elapsed = Date.now() - state.startTime;
			const elapsedSeconds = Math.round(elapsed / 1000);
			const minutes = Math.floor(elapsedSeconds / 60);
			const seconds = elapsedSeconds % 60;
			const timeStr = minutes > 0 ? minutes + 'm ' + seconds + 's' : seconds + 's';

			// Show completion message
			if (data && data.credits_exhausted) {
				$progress.removeClass('notice-info').addClass('notice-error');
				$progress.find('p:first strong').text('Indexing Stopped - Insufficient Credits');
				$progress.find('.progress-text').html(
					'Indexed ' + this.formatNumber(state.processed) + ' of ' + this.formatNumber(state.total) +
					' topics. <strong style="color: #d63638;">Please wait for your monthly credit reset or purchase additional credits to continue.</strong>'
				);
			} else if (state.errors.length > 0) {
				$progress.removeClass('notice-info').addClass('notice-warning');
				$progress.find('p:first strong').text('Indexing Complete with Errors');
				$progress.find('.progress-text').html(
					'Processed: ' + this.formatNumber(state.processed) + ' / ' + this.formatNumber(state.total) +
					' topics in ' + timeStr + '. ' +
					'<strong style="color: #d63638;">' + state.errors.length + ' errors occurred.</strong>'
				);
			} else {
				$progress.removeClass('notice-info').addClass('notice-success');
				$progress.find('p:first strong').text('Indexing Complete!');
				$progress.find('.progress-text').html(
					'Successfully indexed ' + this.formatNumber(state.processed) + ' topics in ' + timeStr + '.'
				);
				$progress.find('.progress-bar').css('background', '#00a32a');
			}

			// Show reindex button, hide stop button, restore original button text
			$('.wpforo-ai-stop-indexing').hide();
			const $reindexBtn = $('.wpforo-ai-reindex-all');
			$reindexBtn.show().removeClass('loading').prop('disabled', false);
			if ($reindexBtn.data('original-html')) {
				$reindexBtn.html($reindexBtn.data('original-html'));
			}

			// Update status indicator (same as cloud indexing)
			const $statusElement = $('#rag-indexing-status');
			const $statusIcon = $statusElement.closest('.rag-stat-item').find('.dashicons');
			$statusElement
				.text('Idle')
				.removeClass('status-active')
				.addClass('status-idle');
			$statusIcon
				.removeClass('dashicons-update-alt wpforo-rag-status-spin')
				.addClass('dashicons-saved');

			// Refresh stats after a short delay
			setTimeout(function() {
				self.refreshRAGStatus();
			}, 1000);

			// Auto-hide progress after 10 seconds
			setTimeout(function() {
				$progress.fadeOut(500, function() {
					$(this).remove();
				});
			}, 10000);
		},

		/**
		 * Stop local indexing
		 */
		stopLocalIndexing: function() {
			this.localIndexingStopped = true;

			// Show reindex button, hide stop button, restore original button text
			$('.wpforo-ai-stop-indexing').hide();
			const $reindexBtn = $('.wpforo-ai-reindex-all');
			$reindexBtn.show().removeClass('loading').prop('disabled', false);
			if ($reindexBtn.data('original-html')) {
				$reindexBtn.html($reindexBtn.data('original-html'));
			}

			// Show "Stopping..." status while background jobs complete
			// The status will change to "Idle" when updateRAGStatusDisplay detects no more pending jobs
			const $statusElement = $('#rag-indexing-status');
			$statusElement.text('Stopping...');

			// Update progress UI
			const $progress = $('#wpforo-local-indexing-progress');
			if ($progress.length) {
				$progress.removeClass('notice-info').addClass('notice-warning');
				$progress.find('p:first strong').text('Indexing Stopped');
				$progress.find('.progress-text').html('Indexing was stopped by user.');

				setTimeout(function() {
					$progress.fadeOut(500, function() {
						$(this).remove();
					});
				}, 5000);
			}
		},

		/**
		 * Check for in-progress local indexing on page load (auto-resume)
		 */
		checkLocalIndexingProgress: function() {
			const self = this;

			// Only check if we're on the AI features page and in local mode
			if (!this.isLocalStorageMode()) {
				return;
			}

			$.ajax({
				url: wpforoAIAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_action',
					wpforo_ai_action: 'get_indexing_progress',
					_wpnonce: wpforoAIAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.indexing_active) {
						console.log('Found in-progress indexing, resuming...', response.data);

						// Initialize state from server
						self.localIndexingState = {
							total: response.data.total,
							processed: response.data.processed,
							remaining: response.data.remaining,
							batchSize: response.data.batch_size,
							errors: [],
							startTime: Date.now() - ((Date.now() / 1000 - response.data.started_at) * 1000) // Approximate start time
						};

						// Show progress UI (this also shows stop button and updates status icon)
						self.showLocalIndexingProgress();
						self.updateLocalIndexingUI(response.data);

						// Resume processing
						self.processLocalBatches($('.wpforo-ai-reindex-all'));
					}
				},
				error: function() {
					// Silent fail - no in-progress indexing
					console.log('No in-progress local indexing found');
				}
			});
		},

		/**
		 * Check if returning from purchase and auto-refresh after 60 seconds with countdown
		 */
		checkPostPurchaseRefresh: function() {
			// Check if URL has upgraded=1 or credits_purchased=1 parameter
			const urlParams = new URLSearchParams(window.location.search);
			const isUpgraded = urlParams.get('upgraded') === '1';
			const isCreditsPurchased = urlParams.get('credits_purchased') === '1';
			const isPostPurchase = isUpgraded || isCreditsPurchased;

			if (isPostPurchase) {
				console.log('Post-purchase detected, will refresh in 60 seconds...');

				// Find existing status badge and update it with countdown
				const $statusBadge = $('.wpforo-ai-status-badge').first();
				const purchaseType = isUpgraded ? 'Subscription Plan' : 'AI Credits';

				if ($statusBadge.length) {
					// Update existing badge with countdown message
					$statusBadge
						.removeClass('status-active status-inactive status-error')
						.addClass('status-success')
						.html('<span class="dashicons dashicons-update wpforo-status-spin"></span>Updating ' + purchaseType + ' ... (<span class="wpforo-countdown">60</span>s)');

					// Start countdown from 60 seconds
					let secondsLeft = 60;
					const countdownInterval = setInterval(function() {
						secondsLeft--;
						$statusBadge.find('.wpforo-countdown').text(secondsLeft);

						if (secondsLeft <= 0) {
							clearInterval(countdownInterval);
							// Remove purchase parameters and refresh
							window.location.href = window.location.href.split('?')[0] + '?page=wpforo-ai';
						}
					}, 1000);
				} else {
					// Fallback: just refresh after 60 seconds if no badge found
					setTimeout(function() {
						window.location.href = window.location.href.split('?')[0] + '?page=wpforo-ai';
					}, 60000);
				}
			}
		}
	};

	/**
	 * Initialize when document is ready
	 */
	$(document).ready(function() {
		// Initialize on AI Features page or Settings page with bot user search field
		if ($('.wpforo-ai-wrap').length || $('#wpforo-ai-bot-user-search').length) {
			WpForoAI.init();
		}
	});

	/**
	 * Make WpForoAI available globally for debugging
	 */
	window.WpForoAI = WpForoAI;

})(jQuery);

/**
 * Forum checkbox select all/deselect all with parent-child relationship
 */
jQuery(document).ready(function($) {
	// Select all forums
	$('.wpforo-ai-select-all-forums').on('click', function(e) {
		e.preventDefault();
		$('.wpforo-ai-forum-checklist input[type="checkbox"]').prop('checked', true);
	});

	// Deselect all forums
	$('.wpforo-ai-deselect-all-forums').on('click', function(e) {
		e.preventDefault();
		$('.wpforo-ai-forum-checklist input[type="checkbox"]').prop('checked', false);
	});

	// Parent-child checkbox logic
	$('.wpforo-ai-forum-checklist input[type="checkbox"]').on('change', function() {
		const $checkbox = $(this);
		const forumId = $checkbox.data('forum-id');
		const parentId = $checkbox.data('parent-id');
		const isCategory = $checkbox.data('is-category') == 1;
		const isChecked = $checkbox.prop('checked');

		// If this is a parent/category being checked
		if (isCategory && parentId == 0) {
			// Find all children of this parent
			const $children = $('.wpforo-ai-forum-checklist input[data-parent-id="' + forumId + '"]');

			// Set all children to match parent state
			$children.prop('checked', isChecked);
		}

		// If this is a child being unchecked
		if (!isChecked && parentId > 0) {
			// Find the parent checkbox
			const $parent = $('.wpforo-ai-forum-checklist input[data-forum-id="' + parentId + '"]');

			// Uncheck the parent if child is unchecked
			$parent.prop('checked', false);
		}

		// If this is a child being checked
		if (isChecked && parentId > 0) {
			// Check if all siblings are now checked
			const $siblings = $('.wpforo-ai-forum-checklist input[data-parent-id="' + parentId + '"]');
			const allSiblingsChecked = $siblings.length === $siblings.filter(':checked').length;

			// If all children are checked, check the parent
			if (allSiblingsChecked) {
				const $parent = $('.wpforo-ai-forum-checklist input[data-forum-id="' + parentId + '"]');
				$parent.prop('checked', true);
			}
		}
	});
});

/**
 * AI Tasks Module
 * Handles AI task creation, management, and AJAX interactions
 */
jQuery(document).ready(function($) {
	'use strict';

	const WpForoAITasks = {
		initialized: false,
		editingTaskId: null,
		searchTimeout: null,

		/**
		 * Initialize AI Tasks functionality
		 */
		init: function() {
			if (this.initialized) {
				return;
			}
			this.initialized = true;
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Unbind all task events first to prevent duplicates
			$(document).off('click', '.wpforo-ai-create-task-btn');
			$(document).off('click', '.wpforo-ai-cancel-task-btn');
			$(document).off('change', '#wpforo-ai-task-type');
			$(document).off('click', '#wpforo-ai-save-task-btn');
			$(document).off('submit', '#wpforo-ai-task-form');
			$(document).off('click', '.wpforo-ai-task-actions-toggle');
			$(document).off('click', '.wpforo-ai-task-run');
			$(document).off('click', '.wpforo-ai-task-pause');
			$(document).off('click', '.wpforo-ai-task-activate');
			$(document).off('click', '.wpforo-ai-task-edit');
			$(document).off('click', '.wpforo-ai-task-delete');
			$(document).off('click', '.wpforo-ai-task-duplicate');
			$(document).off('click', '.wpforo-ai-task-stats');
			$(document).off('click', '.wpforo-ai-task-logs');
			$(document).off('click', '.wpforo-ai-bulk-apply');
			$(document).off('change', '.wpforo-ai-select-all-tasks');
			$(document).off('change', '.wpforo-ai-filter-status, .wpforo-ai-filter-type');
			$(document).off('keyup', '.wpforo-ai-search-tasks');

			// Actions dropdown toggle
			$(document).on('click', '.wpforo-ai-task-actions-toggle', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const $dropdown = $(this).closest('.wpforo-ai-task-actions-dropdown');
				const isOpen = $dropdown.hasClass('open');

				// Close all other dropdowns first
				$('.wpforo-ai-task-actions-dropdown').removeClass('open');

				// Toggle current dropdown
				if (!isOpen) {
					$dropdown.addClass('open');
				}
			});

			// Close dropdown when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.wpforo-ai-task-actions-dropdown').length) {
					$('.wpforo-ai-task-actions-dropdown').removeClass('open');
				}
			});

			// Close dropdown when clicking a menu item
			$(document).on('click', '.wpforo-ai-task-actions-menu a', function() {
				$(this).closest('.wpforo-ai-task-actions-dropdown').removeClass('open');
			});

			// Create Task button - toggle form visibility (with debounce)
			let isToggling = false;
			$(document).on('click', '.wpforo-ai-create-task-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				if (isToggling) {
					console.log('Debounced - toggle already in progress');
					return;
				}
				isToggling = true;
				self.toggleTaskForm();
				setTimeout(function() { isToggling = false; }, 500);
			});

			// Cancel button - hide form
			$(document).on('click', '.wpforo-ai-cancel-task-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				self.hideTaskForm();
			});

			// Task type selection - show dynamic config
			$(document).on('change', '#wpforo-ai-task-type', function() {
				self.handleTaskTypeChange($(this).val());
			});

			// Day checkbox toggle styling
			$(document).on('change', '.wpforo-ai-day-checkboxes input', function() {
				const $label = $(this).closest('label');
				if ($(this).is(':checked')) {
					$label.addClass('selected');
				} else {
					$label.removeClass('selected');
				}
			});

			// Quality tier selection
			$(document).on('click', '.wpforo-ai-quality-tier', function() {
				const $tier = $(this);
				const $input = $tier.find('input[type="radio"]');

				// Remove selection from all tiers in this group
				$tier.siblings('.wpforo-ai-quality-tier').removeClass('selected');
				$tier.addClass('selected');
				$input.prop('checked', true);
			});

			// Duplicate prevention checkbox toggle
			$(document).on('change', '[name="config[duplicate_prevention]"]', function() {
				const $checkbox = $(this);
				const $section = $checkbox.closest('.wpforo-ai-column');
				const $duplicateSettings = $section.find('.wpforo-ai-duplicate-settings');

				if ($checkbox.is(':checked')) {
					$duplicateSettings.slideDown(200);
				} else {
					$duplicateSettings.slideUp(200);
				}
			});

			// Run on approval toggle - hide/disable scheduled options
			$(document).on('change', '.wpforo-ai-run-on-approval-checkbox', function() {
				const $checkbox = $(this);
				// Look for scheduled options in either column or form-section (Tag Generator uses form-section)
				let $section = $checkbox.closest('.wpforo-ai-column');
				if (!$section.length) {
					$section = $checkbox.closest('.wpforo-ai-form-section');
				}
				const $scheduledOptions = $section.find('.wpforo-ai-scheduled-options');

				if ($checkbox.is(':checked')) {
					$scheduledOptions.slideUp(200);
					// Disable inputs to prevent form validation errors
					$scheduledOptions.find('input, select').prop('disabled', true);
				} else {
					$scheduledOptions.slideDown(200);
					$scheduledOptions.find('input, select').prop('disabled', false);
				}

				// Update estimated credits (will be different for on-approval mode)
				self.updateEstimatedCredits();
			});

			// Author mutual exclusion: usergroup selected → clear user field
			$(document).on('change', '.wpforo-ai-author-groupid-select', function() {
				if ($(this).val()) {
					const $section = $(this).closest('.wpforo-ai-form-section');
					$section.find('.wpforo-ai-user-id-input').val('');
					$section.find('.wpforo-ai-user-search').val('');
				}
			});

			// Credit estimation - update on field changes
			$(document).on('change', '[name="config[frequency]"], [name="config[topics_per_run]"], [name="config[replies_per_run]"], [name="config[quality_tier]"], [name="config[active_days][]"]', function() {
				self.updateEstimatedCredits();
			});

			// Save Task button (by ID) and form submit
			$(document).on('click', '#wpforo-ai-save-task-btn', function(e) {
				e.preventDefault();
				self.saveTask();
			});

			// Also handle form submit to prevent default
			$(document).on('submit', '#wpforo-ai-task-form', function(e) {
				e.preventDefault();
				self.saveTask();
			});

			// Task actions - Run
			$(document).on('click', '.wpforo-ai-task-run', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.runTask(taskId);
			});

			// Task actions - Pause
			$(document).on('click', '.wpforo-ai-task-pause', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.toggleTaskStatus(taskId, 'paused');
			});

			// Task actions - Activate
			$(document).on('click', '.wpforo-ai-task-activate', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.toggleTaskStatus(taskId, 'active');
			});

			// Task actions - Duplicate
			$(document).on('click', '.wpforo-ai-task-duplicate', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.duplicateTask(taskId);
			});

			// Task actions - View Stats
			$(document).on('click', '.wpforo-ai-task-stats', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.viewTaskStats(taskId);
			});

			// Task actions - Edit
			$(document).on('click', '.wpforo-ai-task-edit', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.editTask(taskId);
			});

			// Task actions - Delete
			$(document).on('click', '.wpforo-ai-task-delete', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.deleteTask(taskId);
			});

			// Task actions - View Logs
			$(document).on('click', '.wpforo-ai-task-logs', function(e) {
				e.preventDefault();
				const taskId = $(this).closest('tr').data('task-id');
				self.viewTaskLogs(taskId);
			});

			// Bulk actions
			$(document).on('click', '.wpforo-ai-bulk-apply', function(e) {
				e.preventDefault();
				self.applyBulkAction();
			});

			// Select all checkbox
			$(document).on('change', '.wpforo-ai-select-all-tasks', function() {
				$('.wpforo-ai-task-checkbox').prop('checked', $(this).is(':checked'));
			});

			// Filter change
			$(document).on('change', '.wpforo-ai-filter-status, .wpforo-ai-filter-type', function() {
				self.filterTasks();
			});

			// Search
			$(document).on('keyup', '.wpforo-ai-search-tasks', function() {
				clearTimeout(self.searchTimeout);
				self.searchTimeout = setTimeout(function() {
					self.filterTasks();
				}, 300);
			});
		},

		/**
		 * Toggle task form visibility
		 */
		toggleTaskForm: function() {
			const $container = $('.wpforo-ai-task-form-container');
			const $btn = $('.wpforo-ai-create-task-btn');

			if ($container.hasClass('visible')) {
				this.hideTaskForm();
			} else {
				// Show the form with inline styles to ensure visibility
				$container.addClass('visible').css({
					'display': 'block',
					'visibility': 'visible',
					'opacity': '1'
				});
				$btn.html('<span class="dashicons dashicons-no-alt"></span> Cancel');

				// Reset form if not editing
				if (!this.editingTaskId) {
					this.resetForm();
				}

				// Scroll to form using native scrollIntoView for better compatibility
				$container[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		},

		/**
		 * Hide task form
		 */
		hideTaskForm: function() {
			const $container = $('.wpforo-ai-task-form-container');
			const $btn = $('.wpforo-ai-create-task-btn');

			$container.removeClass('visible').css({
				'display': 'none',
				'visibility': '',
				'opacity': ''
			});
			$btn.html('<span class="dashicons dashicons-plus-alt2"></span> Create AI Task');

			// Reset editing state
			this.editingTaskId = null;
			this.resetForm();
		},

		/**
		 * Reset form to defaults
		 */
		resetForm: function() {
			const $form = $('#wpforo-ai-task-form');
			if ($form.length) {
				$form[0].reset();
			}

			// Clear config section completely to prevent cached checkbox values
			$('#wpforo-ai-task-config-section').empty().hide();

			// Reset task type select
			$('#wpforo-ai-task-type').val('');

			// Hide dynamic config sections
			$('.wpforo-ai-dynamic-config').removeClass('visible');

			// Reset day checkboxes styling
			$('.wpforo-ai-day-checkboxes label').removeClass('selected');

			// Reset quality tier selection
			$('.wpforo-ai-quality-tier').removeClass('selected');

			// Explicitly uncheck all forum checkboxes (in case of browser caching)
			$('.forum-checkbox').prop('checked', false);

			// Update form header
			$('.wpforo-ai-task-form-box .wpforo-ai-box-header h2').html(
				'<span class="dashicons dashicons-plus-alt2"></span> Create New AI Task'
			);
		},

		/**
		 * Calculate and update estimated monthly credits
		 * Based on: frequency × items_per_run × credits_per_tier × active_days_factor
		 */
		updateEstimatedCredits: function() {
			const taskType = $('#wpforo-ai-task-type').val();
			if (!taskType) return;

			const $configSection = $('#wpforo-ai-task-config-section');
			const $estimatedValue = $configSection.find('.wpforo-ai-estimated-credits-value');
			if (!$estimatedValue.length) return;

			// Get frequency
			const frequency = $configSection.find('[name="config[frequency]"]').val() || 'daily';

			// Get items per run based on task type
			let itemsPerRun = 1;
			if (taskType === 'topic_generator') {
				itemsPerRun = parseInt($configSection.find('[name="config[topics_per_run]"]').val()) || 1;
			} else if (taskType === 'reply_generator') {
				itemsPerRun = parseInt($configSection.find('[name="config[replies_per_run]"]').val()) || 1;
			}

			// Get quality tier credits
			const qualityTier = $configSection.find('[name="config[quality_tier]"]').val() || 'balanced';
			const creditsPerItem = {
				'fast': 1,
				'balanced': 2,
				'advanced': 3,
				'premium': 4
			}[qualityTier] || 2;

			// Get active days count (default all 7)
			const activeDays = $configSection.find('[name="config[active_days][]"]:checked').length || 7;
			const activeDaysFactor = activeDays / 7;

			// Calculate runs per month based on frequency
			const runsPerMonth = {
				'hourly': 24 * 30,      // 720
				'2hours': 12 * 30,      // 360
				'3hours': 8 * 30,       // 240
				'4hours': 6 * 30,       // 180
				'6hours': 4 * 30,       // 120
				'12hours': 2 * 30,      // 60
				'daily': 30,            // 30
				'3days': 10,            // 10 (30/3)
				'weekly': 4,            // 4
				'monthly': 1            // 1
			}[frequency] || 30;

			// Calculate estimated monthly credits
			const estimatedCredits = Math.round(runsPerMonth * itemsPerRun * creditsPerItem * activeDaysFactor);

			// Format with comma for thousands
			const formattedCredits = estimatedCredits.toLocaleString();

			// Update display
			$estimatedValue.text('~' + formattedCredits + ' credits');

			// Calculate and update manual run cost (itemsPerRun × creditsPerItem)
			const manualRunCost = itemsPerRun * creditsPerItem;
			const $manualRunCostValue = $configSection.find('.wpforo-ai-manual-run-cost-value');
			if ($manualRunCostValue.length) {
				$manualRunCostValue.text(manualRunCost + ' credit' + (manualRunCost !== 1 ? 's' : ''));
			}
		},

		/**
		 * Handle task type selection change
		 */
		handleTaskTypeChange: function(taskType) {
			const $configSection = $('#wpforo-ai-task-config-section');

			// Hide language dropdown for tag maintenance (tags match topic content language)
			const $languageField = $('#wpforo-ai-task-language').closest('.wpforo-ai-form-field');
			if (taskType === 'tag_maintenance') {
				$languageField.hide();
			} else {
				$languageField.show();
			}

			// Clear and hide if no type selected
			if (!taskType) {
				$configSection.empty().hide();
				return;
			}

			// Get template content from script tag
			const $template = $('#wpforo-ai-task-config-' + taskType);
			if ($template.length) {
				// Load template content into config section
				$configSection.html($template.html()).show();

				// Initialize dynamic form elements after loading template
				this.initDynamicFormElements($configSection);

				// Update estimated credits for the new task type
				this.updateEstimatedCredits();
			} else {
				console.error('Template not found for task type:', taskType);
				$configSection.empty().hide();
			}
		},

		/**
		 * Initialize dynamic form elements (collapsible sections, range sliders)
		 */
		initDynamicFormElements: function($container) {
			// Initialize collapsible sections
			$container.find('.wpforo-ai-collapsible-toggle').each(function() {
				const $toggle = $(this);
				const $content = $toggle.next('.wpforo-ai-collapsible-content');

				// Set initial state
				const isExpanded = $toggle.attr('aria-expanded') === 'true';
				if (!isExpanded) {
					$content.hide();
				}

				// Remove any existing click handlers and add new one
				$toggle.off('click').on('click', function(e) {
					e.preventDefault();
					const currentlyExpanded = $toggle.attr('aria-expanded') === 'true';

					if (currentlyExpanded) {
						$toggle.attr('aria-expanded', 'false');
						$content.slideUp(300);
					} else {
						$toggle.attr('aria-expanded', 'true');
						$content.slideDown(300);
					}
				});
			});

			// Initialize range sliders
			$container.find('.wpforo-ai-range-slider').each(function() {
				const $slider = $(this);
				const $valueDisplay = $slider.next('.wpforo-ai-range-value');

				// Set initial value display
				if ($valueDisplay.length) {
					$valueDisplay.text($slider.val() + '%');
				}

				// Update value on input
				$slider.off('input').on('input', function() {
					if ($valueDisplay.length) {
						$valueDisplay.text($(this).val() + '%');
					}
				});
			});

			// Initialize forum select all/deselect all buttons within container
			$container.find('.wpforo-ai-select-all-forums').off('click').on('click', function(e) {
				e.preventDefault();
				$(this).closest('.wpforo-ai-form-field').find('.wpforo-ai-forum-checkbox-item input[type="checkbox"]').prop('checked', true);
			});

			$container.find('.wpforo-ai-deselect-all-forums').off('click').on('click', function(e) {
				e.preventDefault();
				$(this).closest('.wpforo-ai-form-field').find('.wpforo-ai-forum-checkbox-item input[type="checkbox"]').prop('checked', false);
			});

			// Initialize parent/category checkbox toggle behavior
			$container.find('.forum-parent-toggle').off('change').on('change', function() {
				const $parent = $(this);
				const parentId = $parent.data('forum-id');
				const isChecked = $parent.prop('checked');
				const $checklist = $parent.closest('.wpforo-ai-forum-checklist');

				// Find all child forums (forums with this parent ID)
				$checklist.find('.forum-checkbox').each(function() {
					const $child = $(this);
					if ($child.data('parent-id') == parentId) {
						$child.prop('checked', isChecked);
					}
				});
			});

			// Update parent checkbox state when child checkboxes change
			$container.find('.forum-checkbox:not(.forum-parent-toggle)').off('change').on('change', function() {
				const $child = $(this);
				const parentId = $child.data('parent-id');
				if (!parentId) return;

				const $checklist = $child.closest('.wpforo-ai-forum-checklist');
				const $parent = $checklist.find('.forum-parent-toggle[data-forum-id="' + parentId + '"]');
				if (!$parent.length) return;

				// Check if all children of this parent are checked
				const $siblings = $checklist.find('.forum-checkbox[data-parent-id="' + parentId + '"]:not(.forum-parent-toggle)');
				const allChecked = $siblings.length > 0 && $siblings.filter(':checked').length === $siblings.length;
				const someChecked = $siblings.filter(':checked').length > 0;

				$parent.prop('checked', allChecked);
				$parent.prop('indeterminate', someChecked && !allChecked);
			});

			// Initialize duplicate prevention toggle state
			$container.find('[name="config[duplicate_prevention]"]').each(function() {
				const $checkbox = $(this);
				const $section = $checkbox.closest('.wpforo-ai-column');
				const $duplicateSettings = $section.find('.wpforo-ai-duplicate-settings');

				// Set initial visibility based on checkbox state
				if ($checkbox.is(':checked')) {
					$duplicateSettings.show();
				} else {
					$duplicateSettings.hide();
				}
			});

			// Initialize user search fields
			this.initUserSearch($container);
		},

		/**
		 * Initialize AJAX user search for author selection
		 */
		initUserSearch: function($container) {
			const self = this;
			let searchTimeout = null;

			$container.find('.wpforo-ai-user-search').each(function() {
				const $searchInput = $(this);
				const $wrapper = $searchInput.closest('.wpforo-ai-user-search-wrapper');
				const $hiddenInput = $wrapper.find('.wpforo-ai-user-id-input');
				const $resultsContainer = $wrapper.find('.wpforo-ai-user-search-results');

				// Handle input for search
				$searchInput.off('input').on('input', function() {
					const searchTerm = $(this).val().trim();

					// Clear previous timeout
					if (searchTimeout) {
						clearTimeout(searchTimeout);
					}

					// Clear results if search term is too short
					if (searchTerm.length < 2) {
						$resultsContainer.empty().hide();
						return;
					}

					// Debounce the search
					searchTimeout = setTimeout(function() {
						self.searchUsers(searchTerm, $resultsContainer, $hiddenInput, $searchInput);
					}, 300);
				});

				// Handle click outside to close results
				$(document).on('click', function(e) {
					if (!$(e.target).closest('.wpforo-ai-user-search-wrapper').length) {
						$resultsContainer.empty().hide();
					}
				});

				// Handle focus to show results if there's a search term
				$searchInput.off('focus').on('focus', function() {
					if ($(this).val().trim().length >= 2 && $resultsContainer.children().length > 0) {
						$resultsContainer.show();
					}
				});
			});
		},

		/**
		 * Perform AJAX user search
		 */
		searchUsers: function(searchTerm, $resultsContainer, $hiddenInput, $searchInput) {
			$resultsContainer.html('<div class="wpforo-ai-user-search-loading">Searching...</div>').show();

			// Get AJAX URL and nonce from localized script and hidden input
			const ajaxUrl = (typeof wpforoAIAdmin !== 'undefined' && wpforoAIAdmin.ajaxUrl) ? wpforoAIAdmin.ajaxUrl : ajaxurl;
			const nonce = $('#wpforo-ai-task-nonce').val() || '';

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_search_users',
					search: searchTerm,
					_wpnonce: nonce
				},
				success: function(response) {
					$resultsContainer.empty();

					if (response.success && response.data.users && response.data.users.length > 0) {
						const $list = $('<ul class="wpforo-ai-user-search-list"></ul>');

						response.data.users.forEach(function(user) {
							const $item = $('<li class="wpforo-ai-user-search-item" data-user-id="' + user.id + '"></li>');
							$item.text(user.label);
							$item.on('click', function() {
								$hiddenInput.val(user.id);
								$searchInput.val(user.label);
								$resultsContainer.empty().hide();
								// Clear usergroup when specific user is selected
								$hiddenInput.closest('.wpforo-ai-form-section').find('.wpforo-ai-author-groupid-select').val('');
							});
							$list.append($item);
						});

						$resultsContainer.append($list).show();
					} else {
						$resultsContainer.html('<div class="wpforo-ai-user-search-empty">No users found</div>').show();
					}
				},
				error: function() {
					$resultsContainer.html('<div class="wpforo-ai-user-search-error">Search error</div>').show();
				}
			});
		},

		/**
		 * Collect form data
		 */
		collectFormData: function() {
			const taskType = $('#wpforo-ai-task-type').val();

			// Basic task data
			const data = {
				task_name: $('#wpforo-ai-task-name').val(),
				task_type: taskType,
				board_id: $('input[name="board_id"]').val() || 0,
				status: $('input[name="status"]:checked').val() || 'paused'
			};

			// Collect type-specific config
			const config = {};

			// Language setting (shared across all task types)
			config.response_language = $('#wpforo-ai-task-language').val() || '';

			// Config section where dynamic form is rendered
			const $configSection = $('#wpforo-ai-task-config-section');

			if (taskType === 'topic_generator') {
				// Get checked forum IDs (use correct name attribute from form)
				config.target_forums = this.getCheckedValues($configSection.find('[name="config[target_forum_ids][]"]'));

				// Content settings
				config.topic_theme = $configSection.find('[name="config[topic_theme]"]').val() || '';
				config.topic_style = $configSection.find('[name="config[topic_style]"]').val() || 'neutral';
				config.topic_tone = $configSection.find('[name="config[topic_tone]"]').val() || 'neutral';
				config.content_length = $configSection.find('[name="config[content_length]"]').val() || 'medium';

				// Content options (what to include)
				config.include_code = $configSection.find('[name="config[include_code]"]').is(':checked');
				config.include_links = $configSection.find('[name="config[include_links]"]').is(':checked');
				config.include_steps = $configSection.find('[name="config[include_steps]"]').is(':checked');
				config.include_youtube = $configSection.find('[name="config[include_youtube]"]').is(':checked');

				// Author settings
				config.author_userid = parseInt($configSection.find('[name="config[author_userid]"]').val()) || 0;
				config.author_groupid = parseInt($configSection.find('[name="config[author_groupid]"]').val()) || 0;
				config.show_ai_badge = $configSection.find('[name="config[show_ai_badge]"]').is(':checked');

				// Scheduling
				config.frequency = $configSection.find('[name="config[frequency]"]').val() || 'daily';
				config.topics_per_run = parseInt($configSection.find('[name="config[topics_per_run]"]').val()) || 1;
				config.active_days = this.getCheckedValues($configSection.find('[name="config[active_days][]"]:checked'));

				// AI Quality & Credits
				config.quality_tier = $configSection.find('[name="config[quality_tier]"]').val() || 'balanced';
				config.credit_stop_threshold = parseInt($configSection.find('[name="config[credit_stop_threshold]"]').val()) || 0;
				config.auto_pause_on_limit = $configSection.find('[name="config[auto_pause_on_limit]"]').is(':checked');

				// Content Safety
				config.duplicate_prevention = $configSection.find('[name="config[duplicate_prevention]"]').is(':checked');
				config.similarity_threshold = parseInt($configSection.find('[name="config[similarity_threshold]"]').val()) || 75;
				config.duplicate_check_days = parseInt($configSection.find('[name="config[duplicate_check_days]"]').val()) || 90;
				config.topic_status = parseInt($configSection.find('[name="config[topic_status]"]:checked').val()) || 0;

				// Advanced Options
				config.topic_prefix = $configSection.find('[name="config[topic_prefix]"]').val() || '';
				config.topic_prefix_id = $configSection.find('[name="config[topic_prefix_id]"]').val() || '';
				config.auto_tags = $configSection.find('[name="config[auto_tags]"]').val() || '';
				config.search_keywords = $configSection.find('[name="config[search_keywords]"]').val() || '';
			} else if (taskType === 'reply_generator') {
				// Target settings - forums, topic IDs, and date range
				config.reply_target_forums = this.getCheckedValues($configSection.find('[name="config[reply_target_forum_ids][]"]'));
				config.target_topic_ids = $configSection.find('[name="config[target_topic_ids]"]').val() || '';
				config.only_not_replied = $configSection.find('[name="config[only_not_replied]"]').is(':checked');
				config.date_range_from = $configSection.find('[name="config[date_range_from]"]').val() || '';
				config.date_range_to = $configSection.find('[name="config[date_range_to]"]').val() || '';
				config.reply_style = $configSection.find('[name="config[reply_style]"]').val() || 'neutral';
				config.reply_tone = $configSection.find('[name="config[reply_tone]"]').val() || 'neutral';
				config.response_guidelines = $configSection.find('[name="config[response_guidelines]"]').val() || '';
				config.reply_length = $configSection.find('[name="config[reply_length]"]').val() || 'medium';
				config.knowledge_source = $configSection.find('[name="config[knowledge_source]"]').val() || 'forum_only';
				config.no_content_action = $configSection.find('[name="config[no_content_action]"]').val() || 'use_ai_fallback';
				config.author_userid = $configSection.find('[name="config[author_userid]"]').val() || 0;
				config.author_groupid = parseInt($configSection.find('[name="config[author_groupid]"]').val()) || 0;
				config.show_ai_badge = $configSection.find('[name="config[show_ai_badge]"]').is(':checked');
				// Scheduling - Run on approval OR scheduled
				config.run_on_approval = $configSection.find('[name="config[run_on_approval]"]').is(':checked');
				config.frequency = $configSection.find('[name="config[frequency]"]').val() || '3hours';
				config.replies_per_run = parseInt($configSection.find('[name="config[replies_per_run]"]').val()) || 3;
				config.active_days = this.getCheckedValues($configSection.find('[name="config[active_days][]"]:checked'));
				config.quality_tier = $configSection.find('[name="config[quality_tier]"]').val() || 'balanced';
				config.credit_stop_threshold = parseInt($configSection.find('[name="config[credit_stop_threshold]"]').val()) || 0;
				config.auto_pause_on_limit = $configSection.find('[name="config[auto_pause_on_limit]"]').is(':checked');
				config.duplicate_prevention = $configSection.find('[name="config[duplicate_prevention]"]').is(':checked');
				config.similarity_threshold = parseInt($configSection.find('[name="config[similarity_threshold]"]').val()) || 75;
				config.duplicate_check_days = parseInt($configSection.find('[name="config[duplicate_check_days]"]').val()) || 90;
				config.reply_status = parseInt($configSection.find('[name="config[reply_status]"]:checked').val()) || 0;
				config.reply_strategy = $configSection.find('[name="config[reply_strategy]"]').val() || 'first_post';
				// Reply content options
				config.reply_include_code = $configSection.find('[name="config[reply_include_code]"]').is(':checked');
				config.reply_include_links = $configSection.find('[name="config[reply_include_links]"]').is(':checked');
				config.reply_include_steps = $configSection.find('[name="config[reply_include_steps]"]').is(':checked');
				config.reply_include_followup = $configSection.find('[name="config[reply_include_followup]"]').is(':checked');
				config.reply_include_youtube = $configSection.find('[name="config[reply_include_youtube]"]').is(':checked');
				config.reply_include_greeting = $configSection.find('[name="config[reply_include_greeting]"]').is(':checked');
				config.max_replies_per_topic = parseInt($configSection.find('[name="config[max_replies_per_topic]"]').val()) || 1;
			} else if (taskType === 'tag_maintenance') {
				// Target settings
				config.tag_target_forum_ids = this.getCheckedValues($configSection.find('[name="config[tag_target_forum_ids][]"]'));
				config.target_topic_ids = $configSection.find('[name="config[target_topic_ids]"]').val() || '';
				config.date_range_from = $configSection.find('[name="config[date_range_from]"]').val() || '';
				config.date_range_to = $configSection.find('[name="config[date_range_to]"]').val() || '';
				config.only_not_tagged = $configSection.find('[name="config[only_not_tagged]"]').is(':checked');

				// Tag options
				config.max_tags = parseInt($configSection.find('[name="config[max_tags]"]').val()) || 5;
				config.preserve_existing = $configSection.find('[name="config[preserve_existing]"]').is(':checked');
				config.maintain_vocabulary = $configSection.find('[name="config[maintain_vocabulary]"]').is(':checked');
				config.remove_duplicates = $configSection.find('[name="config[remove_duplicates]"]').is(':checked');
				config.remove_irrelevant = $configSection.find('[name="config[remove_irrelevant]"]').is(':checked');
				config.lowercase = $configSection.find('[name="config[lowercase]"]').is(':checked');

				// Scheduling - Run on approval OR scheduled
				config.run_on_approval = $configSection.find('[name="config[run_on_approval]"]').is(':checked');
				config.frequency = $configSection.find('[name="config[frequency]"]').val() || 'daily';
				config.topics_per_run = parseInt($configSection.find('[name="config[topics_per_run]"]').val()) || 20;
				config.active_days = this.getCheckedValues($configSection.find('[name="config[active_days][]"]:checked'));

				// AI Quality & Credits
				config.quality_tier = $configSection.find('[name="config[quality_tier]"]').val() || 'premium';
				config.credit_stop_threshold = parseInt($configSection.find('[name="config[credit_stop_threshold]"]').val()) || 0;
				config.auto_pause_on_limit = $configSection.find('[name="config[auto_pause_on_limit]"]').is(':checked');
			}

			data.config = JSON.stringify(config);

			return data;
		},

		/**
		 * Get checked checkbox values
		 * @param {string|jQuery} selectorOrElements - CSS selector string or jQuery object
		 */
		getCheckedValues: function(selectorOrElements) {
			const values = [];
			// Handle both string selectors and jQuery objects
			let $elements;
			if (typeof selectorOrElements === 'string') {
				$elements = $(selectorOrElements + ':checked');
			} else {
				// Already jQuery object, filter for checked if not already
				$elements = selectorOrElements.filter(':checked').length ?
					selectorOrElements.filter(':checked') : selectorOrElements;
			}
			$elements.each(function() {
				const val = $(this).val();
				if (val) {
					values.push(val);
				}
			});
			return values;
		},

		/**
		 * Validate form
		 */
		validateForm: function() {
			const taskName = $('#wpforo-ai-task-name').val().trim();
			const taskType = $('#wpforo-ai-task-type').val();
			const $configSection = $('#wpforo-ai-task-config-section');

			if (!taskName) {
				alert('Please enter a task name.');
				$('#wpforo-ai-task-name').focus();
				return false;
			}

			if (!taskType) {
				alert('Please select a task type.');
				$('#wpforo-ai-task-type').focus();
				return false;
			}

			// Author validation for topic and reply generators
			if (taskType === 'topic_generator' || taskType === 'reply_generator') {
				const authorUserId = parseInt($configSection.find('[name="config[author_userid]"]').val()) || 0;
				const authorGroupId = parseInt($configSection.find('[name="config[author_groupid]"]').val()) || 0;
				if (!authorUserId && !authorGroupId) {
					alert('Please select either an author user or an author usergroup.');
					return false;
				}
			}

			// Type-specific validation
			if (taskType === 'topic_generator') {
				// Validate at least one forum is selected
				const selectedForums = $configSection.find('[name="config[target_forum_ids][]"]:checked').length;
				if (selectedForums === 0) {
					alert('Please select at least one target forum.');
					return false;
				}
			} else if (taskType === 'reply_generator') {
				// Validate at least one target is specified (forums OR date range OR topic IDs)
				const selectedForums = $configSection.find('[name="config[reply_target_forum_ids][]"]:checked').length;
				const topicIds = $configSection.find('[name="config[target_topic_ids]"]').val().trim();
				const dateFrom = $configSection.find('[name="config[date_range_from]"]').val();
				const dateTo = $configSection.find('[name="config[date_range_to]"]').val();
				const hasDateRange = dateFrom || dateTo;

				if (selectedForums === 0 && !topicIds && !hasDateRange) {
					alert('Please select at least one target: forums, date range, or specific topic IDs.');
					return false;
				}
			}

			return true;
		},

		/**
		 * Save task via AJAX
		 */
		saveTask: function() {
			if (!this.validateForm()) {
				return;
			}

			const $saveBtn = $('#wpforo-ai-save-task-btn');
			const originalText = $saveBtn.html();

			// Disable button and show loading
			$saveBtn.prop('disabled', true).html('<span class="dashicons dashicons-update wpforo-save-spin"></span> Saving...');

			const formData = this.collectFormData();
			formData.action = 'wpforo_ai_save_task';
			formData._wpnonce = $('#wpforo-ai-task-nonce').val();

			if (this.editingTaskId) {
				formData.task_id = this.editingTaskId;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						// Show success message
						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice(response.data.message || 'Task saved successfully.', 'success');
						}

						// Reload the page to show updated task list
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						alert(response.data.message || 'Failed to save task.');
						$saveBtn.prop('disabled', false).html(originalText);
					}
				},
				error: function(xhr, status, error) {
					console.error('Save task error:', error);
					alert('Failed to save task. Please try again.');
					$saveBtn.prop('disabled', false).html(originalText);
				}
			});
		},

		/**
		 * Edit existing task
		 */
		editTask: function(taskId) {
			const self = this;

			// Show loading
			$('.wpforo-ai-task-edit').addClass('loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_task',
					task_id: taskId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					$('.wpforo-ai-task-edit').removeClass('loading');

					if (response.success && response.data.task) {
						self.populateForm(response.data.task);
						self.editingTaskId = taskId;

						// Update form header
						$('.wpforo-ai-task-form-box .wpforo-ai-box-header h2').html(
							'<span class="dashicons dashicons-edit"></span> Edit Task: ' + response.data.task.task_name
						);

						// Show form
						$('.wpforo-ai-task-form-container').addClass('visible');
						$('.wpforo-ai-create-task-btn').html('<span class="dashicons dashicons-no-alt"></span> Cancel');

						// Scroll to form
						$('html, body').animate({
							scrollTop: $('.wpforo-ai-task-form-container').offset().top - 50
						}, 300);
					} else {
						alert(response.data.message || 'Failed to load task.');
					}
				},
				error: function() {
					$('.wpforo-ai-task-edit').removeClass('loading');
					alert('Failed to load task. Please try again.');
				}
			});
		},

		/**
		 * Populate form with task data
		 */
		populateForm: function(task) {
			const self = this;

			$('#wpforo-ai-task-name').val(task.task_name);
			$('#wpforo-ai-task-type').val(task.task_type).trigger('change');

			// Set status radio button (not a select)
			$('input[name="status"][value="' + task.status + '"]').prop('checked', true);

			// Parse config
			let config = {};
			try {
				config = typeof task.config === 'string' ? JSON.parse(task.config) : task.config;
			} catch (e) {
				console.error('Failed to parse task config:', e);
			}

			// Set language dropdown (in basic section, always available in DOM)
			$('#wpforo-ai-task-language').val(config.response_language || '');

			// Use setTimeout to ensure the template is fully rendered before populating
			// The trigger('change') loads the template HTML, but DOM needs time to update
			// Use 200ms to ensure reliable DOM rendering across all browsers
			setTimeout(function() {
				// Populate type-specific fields
				if (task.task_type === 'topic_generator') {
					self.populateTopicGeneratorConfig(config);
				} else if (task.task_type === 'reply_generator') {
					self.populateReplyGeneratorConfig(config);
				} else if (task.task_type === 'tag_maintenance') {
					self.populateTagMaintenanceConfig(config);
				}
			}, 200);
		},

		/**
		 * Populate Topic Generator config
		 */
		populateTopicGeneratorConfig: function(config) {
			const $section = $('#wpforo-ai-task-config-section');
			const self = this;

			// Check forum checkboxes (uncheck all first, then check saved ones)
			$section.find('[name="config[target_forum_ids][]"]').prop('checked', false);
			if (config.target_forums && config.target_forums.length > 0) {
				config.target_forums.forEach(function(forumId) {
					$section.find('[name="config[target_forum_ids][]"][value="' + forumId + '"]').prop('checked', true);
				});
			}

			// Content settings
			$section.find('[name="config[topic_theme]"]').val(config.topic_theme || '');
			$section.find('[name="config[topic_style]"]').val(config.topic_style || 'neutral');
			$section.find('[name="config[topic_tone]"]').val(config.topic_tone || 'neutral');
			$section.find('[name="config[content_length]"]').val(config.content_length || 'medium');

			// Content options (what to include)
			$section.find('[name="config[include_code]"]').prop('checked', config.include_code === true);
			$section.find('[name="config[include_links]"]').prop('checked', config.include_links === true);
			$section.find('[name="config[include_steps]"]').prop('checked', config.include_steps === true);
			$section.find('[name="config[include_youtube]"]').prop('checked', config.include_youtube === true);

			// Author settings - handle both author_userid and legacy bot_user_id
			const authorUserId = config.author_userid || config.bot_user_id || '';
			$section.find('[name="config[author_userid]"]').val(authorUserId);
			$section.find('[name="config[author_groupid]"]').val(config.author_groupid || '');
			$section.find('[name="config[show_ai_badge]"]').prop('checked', config.show_ai_badge !== false);

			// Load user display name if author_userid is set
			if (authorUserId) {
				self.loadUserDisplayName(authorUserId, $section);
			}

			// Scheduling
			$section.find('[name="config[frequency]"]').val(config.frequency || 'daily');
			$section.find('[name="config[topics_per_run]"]').val(config.topics_per_run || 1);

			// Active days - uncheck all first, then check saved ones
			if (config.active_days && config.active_days.length) {
				$section.find('[name="config[active_days][]"]').prop('checked', false);
				config.active_days.forEach(function(day) {
					$section.find('[name="config[active_days][]"][value="' + day + '"]').prop('checked', true);
				});
			}

			// AI Quality & Credits
			$section.find('[name="config[quality_tier]"]').val(config.quality_tier || 'balanced');
			$section.find('[name="config[credit_stop_threshold]"]').val(config.credit_stop_threshold || 100);
			$section.find('[name="config[auto_pause_on_limit]"]').prop('checked', config.auto_pause_on_limit !== false);

			// Content Safety
			$section.find('[name="config[duplicate_prevention]"]').prop('checked', config.duplicate_prevention !== false);
			$section.find('[name="config[similarity_threshold]"]').val(config.similarity_threshold || 75);
			$section.find('[name="config[duplicate_check_days]"]').val(config.duplicate_check_days || 90);
			$section.find('[name="config[topic_status]"][value="' + (config.topic_status || 0) + '"]').prop('checked', true);

			// Advanced Options
			$section.find('[name="config[topic_prefix]"]').val(config.topic_prefix || '');
			$section.find('[name="config[topic_prefix_id]"]').val(config.topic_prefix_id || '');
			$section.find('[name="config[auto_tags]"]').val(config.auto_tags || '');
			$section.find('[name="config[search_keywords]"]').val(config.search_keywords || '');

			// Update range slider display
			$section.find('.wpforo-ai-range-slider').each(function() {
				const $slider = $(this);
				const $valueDisplay = $slider.next('.wpforo-ai-range-value');
				if ($valueDisplay.length) {
					$valueDisplay.text($slider.val() + '%');
				}
			});

			// Update duplicate prevention visibility
			const $duplicateCheckbox = $section.find('[name="config[duplicate_prevention]"]');
			const $duplicateSettings = $section.find('.wpforo-ai-duplicate-settings');
			if ($duplicateCheckbox.is(':checked')) {
				$duplicateSettings.show();
			} else {
				$duplicateSettings.hide();
			}

			// Trigger event to update character counters after populating form
			$(document).trigger('wpforo-ai-task-loaded');

			// Update estimated credits after populating form
			this.updateEstimatedCredits();
		},

		/**
		 * Load user display name for the user search field
		 */
		loadUserDisplayName: function(userId, $section) {
			if (!userId) return;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_search_users',
					search: '',
					user_id: userId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success && response.data.users && response.data.users.length > 0) {
						const user = response.data.users[0];
						$section.find('.wpforo-ai-user-search').val(user.label);
					}
				}
			});
		},

		/**
		 * Populate Reply Generator config
		 */
		populateReplyGeneratorConfig: function(config) {
			const $section = $('#wpforo-ai-task-config-section');
			const self = this;

			// Target settings - forums (uncheck all first, then check saved ones)
			$section.find('[name="config[reply_target_forum_ids][]"]').prop('checked', false);
			if (config.reply_target_forums && config.reply_target_forums.length > 0) {
				config.reply_target_forums.forEach(function(forumId) {
					$section.find('[name="config[reply_target_forum_ids][]"][value="' + forumId + '"]').prop('checked', true);
				});
			}

			// Target settings - topic IDs and date range
			$section.find('[name="config[target_topic_ids]"]').val(config.target_topic_ids || '');
			$section.find('[name="config[only_not_replied]"]').prop('checked', config.only_not_replied === true);
			$section.find('[name="config[date_range_from]"]').val(config.date_range_from || '');
			$section.find('[name="config[date_range_to]"]').val(config.date_range_to || '');

			// Reply content settings
			$section.find('[name="config[reply_style]"]').val(config.reply_style || 'neutral');
			$section.find('[name="config[reply_tone]"]').val(config.reply_tone || 'neutral');
			$section.find('[name="config[response_guidelines]"]').val(config.response_guidelines || '');
			$section.find('[name="config[reply_length]"]').val(config.reply_length || 'medium');
			$section.find('[name="config[knowledge_source]"]').val(config.knowledge_source || 'forum_only');
			$section.find('[name="config[no_content_action]"]').val(config.no_content_action || 'use_ai_fallback');

			// Author settings - handle both author_userid and legacy bot_user_id
			const authorUserId = config.author_userid || config.bot_user_id || '';
			$section.find('[name="config[author_userid]"]').val(authorUserId);
			$section.find('[name="config[author_groupid]"]').val(config.author_groupid || '');
			$section.find('[name="config[show_ai_badge]"]').prop('checked', config.show_ai_badge !== false);

			// Load user display name if author_userid is set
			if (authorUserId) {
				self.loadUserDisplayName(authorUserId, $section);
			}

			$section.find('[name="config[frequency]"]').val(config.frequency || '3hours');
			$section.find('[name="config[replies_per_run]"]').val(config.replies_per_run || 3);
			$section.find('[name="config[quality_tier]"]').val(config.quality_tier || 'balanced');
			$section.find('[name="config[credit_stop_threshold]"]').val(config.credit_stop_threshold || 100);
			$section.find('[name="config[auto_pause_on_limit]"]').prop('checked', config.auto_pause_on_limit !== false);
			$section.find('[name="config[duplicate_prevention]"]').prop('checked', config.duplicate_prevention !== false);
			$section.find('[name="config[similarity_threshold]"]').val(config.similarity_threshold || 75);
			$section.find('[name="config[duplicate_check_days]"]').val(config.duplicate_check_days || 90);
			$section.find('[name="config[reply_status]"][value="' + (config.reply_status || 0) + '"]').prop('checked', true);
			$section.find('[name="config[reply_strategy]"]').val(config.reply_strategy || 'first_post');

			// Reply content options
			$section.find('[name="config[reply_include_code]"]').prop('checked', config.reply_include_code === true);
			$section.find('[name="config[reply_include_links]"]').prop('checked', config.reply_include_links === true);
			$section.find('[name="config[reply_include_steps]"]').prop('checked', config.reply_include_steps === true);
			$section.find('[name="config[reply_include_followup]"]').prop('checked', config.reply_include_followup !== false);
			$section.find('[name="config[reply_include_youtube]"]').prop('checked', config.reply_include_youtube === true);
			$section.find('[name="config[reply_include_greeting]"]').prop('checked', config.reply_include_greeting !== false);
			$section.find('[name="config[max_replies_per_topic]"]').val(config.max_replies_per_topic || 1);

			// Check day checkboxes
			if (config.active_days && config.active_days.length) {
				// Uncheck all first
				$section.find('[name="config[active_days][]"]').prop('checked', false);
				config.active_days.forEach(function(day) {
					$section.find('[name="config[active_days][]"][value="' + day + '"]').prop('checked', true);
				});
			}

			// Update range slider display
			$section.find('.wpforo-ai-range-slider').each(function() {
				const $slider = $(this);
				const $valueDisplay = $slider.next('.wpforo-ai-range-value');
				if ($valueDisplay.length) {
					$valueDisplay.text($slider.val() + '%');
				}
			});

			// Update duplicate prevention visibility
			const $duplicateCheckbox = $section.find('[name="config[duplicate_prevention]"]');
			const $duplicateSettings = $section.find('.wpforo-ai-duplicate-settings');
			if ($duplicateCheckbox.is(':checked')) {
				$duplicateSettings.show();
			} else {
				$duplicateSettings.hide();
			}

			// Run on approval toggle
			const $runOnApproval = $section.find('[name="config[run_on_approval]"]');
			const $scheduledOptions = $section.find('.wpforo-ai-scheduled-options');
			if (config.run_on_approval) {
				$runOnApproval.prop('checked', true);
				$scheduledOptions.hide();
				$scheduledOptions.find('input, select').prop('disabled', true);
			} else {
				$runOnApproval.prop('checked', false);
				$scheduledOptions.show();
				$scheduledOptions.find('input, select').prop('disabled', false);
			}

			// Trigger event to update character counters after populating form
			$(document).trigger('wpforo-ai-task-loaded');

			// Update estimated credits after populating form
			this.updateEstimatedCredits();
		},

		/**
		 * Populate Tag Maintenance config
		 */
		populateTagMaintenanceConfig: function(config) {
			const $section = $('#wpforo-ai-task-config-section');

			// Target settings - forums (uncheck all first, then check saved ones)
			$section.find('[name="config[tag_target_forum_ids][]"]').prop('checked', false);
			if (config.tag_target_forum_ids && config.tag_target_forum_ids.length > 0) {
				config.tag_target_forum_ids.forEach(function(forumId) {
					$section.find('[name="config[tag_target_forum_ids][]"][value="' + forumId + '"]').prop('checked', true);
				});
			}

			// Target settings - topic IDs and date range
			$section.find('[name="config[target_topic_ids]"]').val(config.target_topic_ids || '');
			$section.find('[name="config[date_range_from]"]').val(config.date_range_from || '');
			$section.find('[name="config[date_range_to]"]').val(config.date_range_to || '');
			$section.find('[name="config[only_not_tagged]"]').prop('checked', config.only_not_tagged === true);

			// Tag options
			$section.find('[name="config[max_tags]"]').val(config.max_tags || 5);
			$section.find('[name="config[preserve_existing]"]').prop('checked', config.preserve_existing !== false);
			$section.find('[name="config[maintain_vocabulary]"]').prop('checked', config.maintain_vocabulary !== false);
			$section.find('[name="config[remove_duplicates]"]').prop('checked', config.remove_duplicates !== false);
			$section.find('[name="config[remove_irrelevant]"]').prop('checked', config.remove_irrelevant !== false);
			$section.find('[name="config[lowercase]"]').prop('checked', config.lowercase === true);

			// Scheduling
			$section.find('[name="config[frequency]"]').val(config.frequency || 'daily');
			$section.find('[name="config[topics_per_run]"]').val(config.topics_per_run || 20);

			// Active days - uncheck all first, then check saved ones
			if (config.active_days && config.active_days.length) {
				$section.find('[name="config[active_days][]"]').prop('checked', false);
				config.active_days.forEach(function(day) {
					$section.find('[name="config[active_days][]"][value="' + day + '"]').prop('checked', true);
				});
			}

			// AI Quality & Credits
			$section.find('[name="config[quality_tier]"]').val(config.quality_tier || 'premium');
			$section.find('[name="config[credit_stop_threshold]"]').val(config.credit_stop_threshold || 500);
			$section.find('[name="config[auto_pause_on_limit]"]').prop('checked', config.auto_pause_on_limit !== false);

			// Run on approval toggle
			const $runOnApproval = $section.find('[name="config[run_on_approval]"]');
			const $scheduledOptions = $section.find('.wpforo-ai-scheduled-options');
			if (config.run_on_approval) {
				$runOnApproval.prop('checked', true);
				$scheduledOptions.hide();
				$scheduledOptions.find('input, select').prop('disabled', true);
			} else {
				$runOnApproval.prop('checked', false);
				$scheduledOptions.show();
				$scheduledOptions.find('input, select').prop('disabled', false);
			}

			// Trigger event to update character counters after populating form
			$(document).trigger('wpforo-ai-task-loaded');

			// Update estimated credits after populating form
			this.updateEstimatedCredits();
		},

		/**
		 * Delete task
		 */
		deleteTask: function(taskId) {
			if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
				return;
			}

			const $row = $('tr[data-task-id="' + taskId + '"]');
			$row.css('opacity', '0.5');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_delete_task',
					task_id: taskId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();

							// Show empty state if no tasks left
							if ($('.wpforo-ai-tasks-table tbody tr').length === 0) {
								$('.wpforo-ai-tasks-table').replaceWith(
									'<div class="wpforo-ai-tasks-empty">' +
									'<span class="dashicons dashicons-schedule"></span>' +
									'<h3>No AI Tasks Yet</h3>' +
									'<p>Create your first AI task to automate forum content generation.</p>' +
									'</div>'
								);
							}
						});

						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice('Task deleted successfully.', 'success');
						}
					} else {
						$row.css('opacity', '1');
						alert(response.data.message || 'Failed to delete task.');
					}
				},
				error: function() {
					$row.css('opacity', '1');
					alert('Failed to delete task. Please try again.');
				}
			});
		},

		/**
		 * Run task immediately
		 */
		runTask: function(taskId) {
			if (!confirm('Run this task now? This will use credits from your account.')) {
				return;
			}

			const $row = $('tr[data-task-id="' + taskId + '"]');
			const $statusCell = $row.find('td.column-status');
			const originalStatusHtml = $statusCell.html();

			// Show running indicator on the row - replace status content
			$row.addClass('wpforo-ai-task-running');
			$statusCell.html('<span class="wpforo-ai-task-status status-running"><span class="dashicons dashicons-update wpforo-ai-spin"></span> Running...</span>');

			// Disable all action buttons for this task
			$row.find('.wpforo-ai-task-actions button').prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_run_task',
					task_id: taskId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					$row.removeClass('wpforo-ai-task-running');
					$row.find('.wpforo-ai-task-actions button').prop('disabled', false);

					if (response.success) {
						// Show success status briefly
						$statusCell.html('<span class="wpforo-ai-task-status status-success"><span class="dashicons dashicons-yes-alt"></span> Completed</span>');

						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice(response.data.message || 'Task completed successfully.', 'success');
						}

						// Reload to show updated stats
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						// Restore original status on error
						$statusCell.html(originalStatusHtml);
						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice(response.data.message || 'Failed to run task.', 'error');
						} else {
							alert(response.data.message || 'Failed to run task.');
						}
					}
				},
				error: function() {
					$row.removeClass('wpforo-ai-task-running');
					$row.find('.wpforo-ai-task-actions button').prop('disabled', false);
					$statusCell.html(originalStatusHtml);
					if (typeof WpForoAI !== 'undefined') {
						WpForoAI.showNotice('Failed to run task. Please try again.', 'error');
					} else {
						alert('Failed to run task. Please try again.');
					}
				}
			});
		},

		/**
		 * Toggle task status (pause/resume/activate)
		 */
		toggleTaskStatus: function(taskId, newStatus) {
			const self = this;
			const $row = $('tr[data-task-id="' + taskId + '"]');
			const $statusBadge = $row.find('.wpforo-ai-task-status');

			// If no status provided, toggle between active and paused
			if (!newStatus) {
				const currentStatus = $statusBadge.hasClass('status-active') ? 'active' : 'paused';
				newStatus = currentStatus === 'active' ? 'paused' : 'active';
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_update_task_status',
					task_id: taskId,
					status: newStatus,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						// Update status badge
						$statusBadge.removeClass('status-active status-paused status-draft');
						$statusBadge.addClass('status-' + newStatus);

						if (newStatus === 'active') {
							$statusBadge.html('<span class="dashicons dashicons-yes-alt"></span> Active');
						} else {
							$statusBadge.html('<span class="dashicons dashicons-clock"></span> Paused');
						}

						// Update dropdown buttons - swap activate/pause
						const $dropdown = $row.find('.wpforo-ai-task-actions-menu');
						const $activateBtn = $dropdown.find('.wpforo-ai-task-activate');
						const $pauseBtn = $dropdown.find('.wpforo-ai-task-pause');

						if (newStatus === 'active') {
							// Replace activate with pause
							if ($activateBtn.length) {
								$activateBtn
									.removeClass('wpforo-ai-task-activate')
									.addClass('wpforo-ai-task-pause')
									.html('<span class="dashicons dashicons-controls-pause"></span> Pause');
							}
						} else {
							// Replace pause with activate
							if ($pauseBtn.length) {
								$pauseBtn
									.removeClass('wpforo-ai-task-pause')
									.addClass('wpforo-ai-task-activate')
									.html('<span class="dashicons dashicons-controls-play"></span> Activate');
							}
						}

						// Update row data attribute
						$row.data('status', newStatus);

						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice('Task ' + (newStatus === 'active' ? 'activated' : 'paused') + ' successfully.', 'success');
						}
					} else {
						alert(response.data.message || 'Failed to update task status.');
					}
				},
				error: function() {
					alert('Failed to update task status. Please try again.');
				}
			});
		},

		/**
		 * Duplicate a task
		 */
		duplicateTask: function(taskId) {
			const self = this;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_duplicate_task',
					task_id: taskId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice('Task duplicated successfully.', 'success');
						}
						// Reload page to show new task
						window.location.reload();
					} else {
						alert(response.data.message || 'Failed to duplicate task.');
					}
				},
				error: function() {
					alert('Failed to duplicate task. Please try again.');
				}
			});
		},

		/**
		 * View task statistics
		 */
		viewTaskStats: function(taskId) {
			const self = this;
			const $row = $('tr[data-task-id="' + taskId + '"]');
			const taskName = $row.find('.wpforo-ai-task-name').text().trim();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_task_stats',
					task_id: taskId,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showStatsModal(taskName, response.data.stats);
					} else {
						alert(response.data.message || 'Failed to load task statistics.');
					}
				},
				error: function() {
					alert('Failed to load task statistics. Please try again.');
				}
			});
		},

		/**
		 * Show statistics modal
		 */
		showStatsModal: function(taskName, stats) {
			// Remove existing modal
			$('.wpforo-ai-stats-modal-overlay').remove();

			const modalHtml = `
				<div class="wpforo-ai-stats-modal-overlay">
					<div class="wpforo-ai-stats-modal">
						<div class="wpforo-ai-stats-modal-header">
							<h3><span class="dashicons dashicons-chart-bar"></span> Task Statistics: ${taskName}</h3>
							<button type="button" class="wpforo-ai-stats-modal-close">&times;</button>
						</div>
						<div class="wpforo-ai-stats-modal-body">
							<div class="wpforo-ai-stats-grid">
								<div class="wpforo-ai-stat-card">
									<div class="wpforo-ai-stat-value">${stats.total_runs || 0}</div>
									<div class="wpforo-ai-stat-label">Total Runs</div>
								</div>
								<div class="wpforo-ai-stat-card">
									<div class="wpforo-ai-stat-value">${stats.items_created || 0}</div>
									<div class="wpforo-ai-stat-label">Number of Items</div>
								</div>
								<div class="wpforo-ai-stat-card">
									<div class="wpforo-ai-stat-value">${stats.credits_used || 0}</div>
									<div class="wpforo-ai-stat-label">Credits Used</div>
								</div>
								<div class="wpforo-ai-stat-card">
									<div class="wpforo-ai-stat-value">${stats.success_rate || '0%'}</div>
									<div class="wpforo-ai-stat-label">Success Rate</div>
								</div>
							</div>
							<div class="wpforo-ai-stats-details">
								<p><strong>Last Run:</strong> ${stats.last_run || 'Never'}</p>
								<p><strong>Next Scheduled:</strong> ${stats.next_run || 'Not scheduled'}</p>
								<p><strong>Avg Items/Run:</strong> ${stats.avg_items_per_run || '0'}</p>
							</div>
						</div>
					</div>
				</div>
			`;

			$('body').append(modalHtml);

			// Close modal events
			$('.wpforo-ai-stats-modal-close, .wpforo-ai-stats-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$('.wpforo-ai-stats-modal-overlay').remove();
				}
			});
		},

		/**
		 * View task logs
		 */
		viewTaskLogs: function(taskId) {
			const self = this;
			const $row = $('tr[data-task-id="' + taskId + '"]');
			const taskName = $row.find('.wpforo-ai-task-name').text().trim();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_task_logs',
					task_id: taskId,
					limit: 50,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						self.showLogsModal(taskName, response.data.logs);
					} else {
						alert(response.data.message || 'Failed to load task logs.');
					}
				},
				error: function() {
					alert('Failed to load task logs. Please try again.');
				}
			});
		},

		/**
		 * Show logs modal
		 */
		showLogsModal: function(taskName, logs) {
			// Remove existing modal
			$('.wpforo-ai-logs-modal-overlay').remove();

			let logsHtml = '';
			if (logs && logs.length > 0) {
				logsHtml = '<table class="wpforo-ai-logs-table"><thead><tr>' +
					'<th>Date</th><th>Status</th><th>Items</th><th>Credits</th><th>Duration</th><th>Message</th>' +
					'</tr></thead><tbody>';

				logs.forEach(function(log) {
					const statusClass = log.status === 'completed' ? 'status-success' :
						(log.status === 'error' ? 'status-error' : 'status-warning');
					const duration = log.execution_duration ? parseFloat(log.execution_duration).toFixed(1) + 's' : '-';
					const message = log.error_message || (log.status === 'completed' ? 'Success' : '-');
					logsHtml += '<tr>' +
						'<td>' + (log.execution_time || '-') + '</td>' +
						'<td><span class="wpforo-ai-log-status ' + statusClass + '">' + (log.status || '-') + '</span></td>' +
						'<td>' + (log.items_created || 0) + '</td>' +
						'<td>' + (log.credits_used || 0) + '</td>' +
						'<td>' + duration + '</td>' +
						'<td>' + message + '</td>' +
						'</tr>';
				});

				logsHtml += '</tbody></table>';
			} else {
				logsHtml = '<div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-info-outline"></span><p>No logs found for this task yet.</p></div>';
			}

			const modalHtml = `
				<div class="wpforo-ai-logs-modal-overlay">
					<div class="wpforo-ai-logs-modal">
						<div class="wpforo-ai-logs-modal-header">
							<h3><span class="dashicons dashicons-list-view"></span> Task Logs: ${taskName}</h3>
							<button type="button" class="wpforo-ai-logs-modal-close">&times;</button>
						</div>
						<div class="wpforo-ai-logs-modal-body">
							${logsHtml}
						</div>
					</div>
				</div>
			`;

			$('body').append(modalHtml);

			// Close modal events
			$('.wpforo-ai-logs-modal-close, .wpforo-ai-logs-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					$('.wpforo-ai-logs-modal-overlay').remove();
				}
			});
		},

		/**
		 * Apply bulk action
		 */
		applyBulkAction: function() {
			const action = $('.wpforo-ai-bulk-action-select').val();
			const selectedIds = [];

			$('.wpforo-ai-task-checkbox:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (!action) {
				alert('Please select a bulk action.');
				return;
			}

			if (selectedIds.length === 0) {
				alert('Please select at least one task.');
				return;
			}

			let confirmMessage = 'Are you sure you want to ' + action + ' ' + selectedIds.length + ' task(s)?';
			if (action === 'delete') {
				confirmMessage = 'Are you sure you want to delete ' + selectedIds.length + ' task(s)? This action cannot be undone.';
			}

			if (!confirm(confirmMessage)) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_bulk_task_action',
					bulk_action: action,
					task_ids: selectedIds,
					_wpnonce: $('#wpforo-ai-task-nonce').val()
				},
				success: function(response) {
					if (response.success) {
						if (typeof WpForoAI !== 'undefined') {
							WpForoAI.showNotice(response.data.message || 'Bulk action completed.', 'success');
						}
						window.location.reload();
					} else {
						alert(response.data.message || 'Bulk action failed.');
					}
				},
				error: function() {
					alert('Bulk action failed. Please try again.');
				}
			});
		},

		/**
		 * Filter tasks
		 */
		filterTasks: function() {
			const status = $('.wpforo-ai-filter-status').val();
			const type = $('.wpforo-ai-filter-type').val();
			const search = $('.wpforo-ai-search-tasks').val().toLowerCase();

			$('.wpforo-ai-tasks-table tbody tr').each(function() {
				const $row = $(this);
				const rowStatus = $row.data('status');
				const rowType = $row.data('task-type');
				const rowName = $row.find('.wpforo-ai-task-name').text().toLowerCase();

				let visible = true;

				if (status && rowStatus !== status) {
					visible = false;
				}

				if (type && rowType !== type) {
					visible = false;
				}

				if (search && rowName.indexOf(search) === -1) {
					visible = false;
				}

				$row.toggle(visible);
			});
		}
	};

	// Initialize AI Tasks if on that tab
	if ($('.wpforo-ai-tasks-tab').length) {
		WpForoAITasks.init();
	}

	// Make available globally
	window.WpForoAITasks = WpForoAITasks;

	// ==========================================================================
	// Analytics Tab
	// ==========================================================================

	const WpForoAIAnalytics = {
		charts: {},
		data: null,
		initialized: false,

		init: function() {
			if (this.initialized) {
				return;
			}
			this.initialized = true;
			this.bindEvents();
			this.loadAnalyticsData();
		},

		destroyAllCharts: function() {
			// Destroy charts stored in our object
			Object.keys(this.charts).forEach(key => {
				if (this.charts[key]) {
					this.charts[key].destroy();
					this.charts[key] = null;
				}
			});

			// Also destroy any Chart.js charts on our canvases
			['credits-usage-chart', 'credits-by-feature-chart', 'moderation-stats-chart'].forEach(id => {
				const canvas = document.getElementById(id);
				if (canvas) {
					const existingChart = Chart.getChart(canvas);
					if (existingChart) {
						existingChart.destroy();
					}
				}
			});
		},

		bindEvents: function() {
			// Custom range toggle - use .off() to prevent duplicate bindings
			$('.wpforo-ai-custom-range-toggle').off('click.customRange').on('click.customRange', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$('.wpforo-ai-custom-range-picker').slideToggle(200);
			});
		},

		loadAnalyticsData: function() {
			if (typeof wpforoAIAnalytics === 'undefined') {
				return;
			}

			const self = this;

			$.ajax({
				url: wpforoAIAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_analytics',
					nonce: wpforoAIAnalytics.nonce,
					board_id: wpforoAIAnalytics.boardId,
					start_time: wpforoAIAnalytics.startTime,
					end_time: wpforoAIAnalytics.endTime
				},
				success: function(response) {
					if (response.success && response.data) {
						self.data = response.data;
						self.updateSummaryCards();
						self.renderCharts();
						self.renderUsageTable();
					} else {
						self.showError(response.data ? response.data.message : wpforoAIAnalytics.i18n.error);
					}
				},
				error: function() {
					self.showError(wpforoAIAnalytics.i18n.error);
				}
			});
		},

		updateSummaryCards: function() {
			const summary = this.data.summary || {};

			$('#total-credits-used').text(this.formatNumber(summary.total_credits || 0));
			$('#avg-credits-day').text(this.formatNumber(summary.avg_credits_per_day || 0, 1));
			$('#total-api-calls').text(this.formatNumber(summary.total_requests || 0));
			$('#success-rate').text((summary.success_rate || 0).toFixed(1) + '%');
		},

		renderCharts: function() {
			this.hideLoading();
			this.destroyAllCharts();
			this.renderCreditsOverTimeChart();
			this.renderCreditsByFeatureChart();
			this.renderModerationChart();
		},

		renderCreditsOverTimeChart: function() {
			const ctx = document.getElementById('credits-usage-chart');
			if (!ctx) return;

			const timeSeries = this.data.time_series || [];

			if (timeSeries.length === 0) {
				this.showNoData(ctx.parentElement);
				return;
			}

			const showYear = timeSeries.length > 1 && new Date(timeSeries[0].date).getFullYear() !== new Date(timeSeries[timeSeries.length - 1].date).getFullYear();
			const labels = timeSeries.map(function(item) {
				const date = new Date(item.date);
				const opts = showYear ? { month: 'short', day: 'numeric', year: '2-digit' } : { month: 'short', day: 'numeric' };
				return date.toLocaleDateString(undefined, opts);
			});
			const credits = timeSeries.map(item => item.credits);
			const requests = timeSeries.map(item => item.requests);

			if (this.charts.creditsOverTime) {
				this.charts.creditsOverTime.destroy();
			}

			this.charts.creditsOverTime = new Chart(ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: wpforoAIAnalytics.i18n.credits,
							data: credits,
							borderColor: '#2271b1',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							fill: true,
							tension: 0.4,
							yAxisID: 'y'
						},
						{
							label: wpforoAIAnalytics.i18n.requests,
							data: requests,
							borderColor: '#46b450',
							backgroundColor: 'transparent',
							borderDash: [5, 5],
							tension: 0.4,
							yAxisID: 'y1'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: 'index',
						intersect: false
					},
					plugins: {
						legend: {
							position: 'top'
						}
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: wpforoAIAnalytics.i18n.credits
							}
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							title: {
								display: true,
								text: wpforoAIAnalytics.i18n.requests
							},
							grid: {
								drawOnChartArea: false
							}
						}
					}
				}
			});
		},

		renderCreditsByFeatureChart: function() {
			const ctx = document.getElementById('credits-by-feature-chart');
			if (!ctx) return;

			const byFeature = this.data.by_feature || {};
			const features = Object.keys(byFeature);

			if (features.length === 0) {
				this.showNoData(ctx.parentElement);
				return;
			}

			const labels = features.map(key => wpforoAIAnalytics.featureNames[key] || key);
			const data = features.map(key => byFeature[key].credits || 0);
			const colors = features.map(key => wpforoAIAnalytics.featureColors[key] || '#999');

			if (this.charts.creditsByFeature) {
				this.charts.creditsByFeature.destroy();
			}

			this.charts.creditsByFeature = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: labels,
					datasets: [{
						data: data,
						backgroundColor: colors,
						borderWidth: 2,
						borderColor: '#fff'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'right',
							labels: {
								boxWidth: 12,
								padding: 15
							}
						}
					}
				}
			});
		},

		renderModerationChart: function() {
			const ctx = document.getElementById('moderation-stats-chart');
			if (!ctx) return;

			const moderation = this.data.moderation || {};

			const labels = [
				wpforoAIAnalytics.i18n.spamBlocked,
				wpforoAIAnalytics.i18n.toxicDetected,
				wpforoAIAnalytics.i18n.policyViolations,
				wpforoAIAnalytics.i18n.cleanPassed
			];

			const data = [
				moderation.spam_blocked || 0,
				moderation.toxic_detected || 0,
				moderation.policy_violations || 0,
				moderation.clean_passed || 0
			];

			const colors = ['#F44336', '#E91E63', '#673AB7', '#46b450'];

			if (this.charts.moderation) {
				this.charts.moderation.destroy();
			}

			this.charts.moderation = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						data: data,
						backgroundColor: colors,
						borderRadius: 4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								stepSize: 1
							}
						}
					}
				}
			});
		},

		renderUsageTable: function() {
			const tbody = $('#feature-usage-tbody');
			if (!tbody.length) return;

			const byFeature = this.data.by_feature || {};
			const features = Object.keys(byFeature);

			if (features.length === 0) {
				tbody.html('<tr class="wpforo-ai-no-data"><td colspan="5">' + wpforoAIAnalytics.i18n.noData + '</td></tr>');
				return;
			}

			let html = '';
			features.forEach(key => {
				const feature = byFeature[key];
				const name = wpforoAIAnalytics.featureNames[key] || key;
				const color = wpforoAIAnalytics.featureColors[key] || '#999';
				const successRate = feature.success_rate || 100;
				const rateClass = successRate >= 95 ? '' : (successRate >= 80 ? 'warning' : 'error');

				html += '<tr>';
				html += '<td><div class="wpforo-ai-feature-name"><span class="wpforo-ai-feature-color" style="background-color: ' + color + '"></span>' + name + '</div></td>';
				html += '<td>' + this.formatNumber(feature.requests || 0) + '</td>';
				html += '<td>' + this.formatNumber(feature.credits || 0) + '</td>';
				html += '<td>' + (feature.avg_response_ms ? feature.avg_response_ms + ' ms' : '-') + '</td>';
				html += '<td><div class="wpforo-ai-success-rate"><span>' + successRate.toFixed(1) + '%</span><div class="wpforo-ai-success-rate-bar"><div class="wpforo-ai-success-rate-fill ' + rateClass + '" style="width: ' + successRate + '%"></div></div></div></td>';
				html += '</tr>';
			});

			tbody.html(html);
		},

		hideLoading: function() {
			$('.wpforo-ai-chart-loading').hide();
			$('#wpforo-ai-analytics-main-loading').addClass('hidden');
			$('#wpforo-ai-analytics-content').addClass('loaded');
		},

		showNoData: function(container) {
			$(container).html('<div class="wpforo-ai-analytics-empty"><span class="dashicons dashicons-chart-bar"></span><h3>' + wpforoAIAnalytics.i18n.noData + '</h3></div>');
		},

		showError: function(message) {
			$('#wpforo-ai-analytics-main-loading').html('<span class="dashicons dashicons-warning" style="color:#d63638;font-size:24px;"></span><span style="color:#d63638;">' + message + '</span>');
			$('.wpforo-ai-chart-loading').html('<div class="wpforo-ai-analytics-error"><span class="dashicons dashicons-warning"></span><p>' + message + '</p></div>');
			$('#feature-usage-tbody').html('<tr class="wpforo-ai-no-data"><td colspan="5">' + message + '</td></tr>');
			$('.wpforo-ai-analytics-card-value').text('-');
		},

		formatNumber: function(num, decimals) {
			decimals = decimals || 0;
			return parseFloat(num).toLocaleString(undefined, {
				minimumFractionDigits: decimals,
				maximumFractionDigits: decimals
			});
		}
	};

	// Initialize Analytics if on that tab
	if ($('.wpforo-ai-analytics-tab').length) {
		WpForoAIAnalytics.init();
	}

	// Make available globally
	window.WpForoAIAnalytics = WpForoAIAnalytics;

	// ===== Forum Activity Analytics =====
	const WpForoForumActivity = {
		charts: {},

		init: function() {
			if (typeof wpforoForumActivity === 'undefined') {
				return;
			}

			this.initPostsOverTimeChart();
			this.initTopForumsChart();
		},

		destroyCharts: function() {
			Object.keys(this.charts).forEach(function(key) {
				if (this.charts[key]) {
					this.charts[key].destroy();
					this.charts[key] = null;
				}
			}.bind(this));
		},

		initPostsOverTimeChart: function() {
			const canvas = document.getElementById('forum-posts-chart');
			if (!canvas) return;

			// Destroy existing chart
			const existingChart = Chart.getChart(canvas);
			if (existingChart) {
				existingChart.destroy();
			}

			const data = wpforoForumActivity.postsOverTime || [];
			const showYear = data.length > 1 && new Date(data[0].date).getFullYear() !== new Date(data[data.length - 1].date).getFullYear();
			const labels = data.map(function(d) {
				const date = new Date(d.date);
				const opts = showYear ? { month: 'short', day: 'numeric', year: '2-digit' } : { month: 'short', day: 'numeric' };
				return date.toLocaleDateString(undefined, opts);
			});
			const topics = data.map(function(d) { return d.topics || 0; });
			const replies = data.map(function(d) { return d.replies || 0; });

			this.charts.postsOverTime = new Chart(canvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: wpforoForumActivity.i18n.topics || 'Topics',
							data: topics,
							borderColor: '#4CAF50',
							backgroundColor: 'rgba(76, 175, 80, 0.1)',
							fill: true,
							tension: 0.3,
							borderWidth: 2,
							pointRadius: 3,
							pointHoverRadius: 5
						},
						{
							label: wpforoForumActivity.i18n.replies || 'Replies',
							data: replies,
							borderColor: '#2196F3',
							backgroundColor: 'rgba(33, 150, 243, 0.1)',
							fill: true,
							tension: 0.3,
							borderWidth: 2,
							pointRadius: 3,
							pointHoverRadius: 5
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						intersect: false,
						mode: 'index'
					},
					plugins: {
						legend: {
							position: 'top',
							labels: {
								usePointStyle: true,
								padding: 15
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								precision: 0
							}
						}
					}
				}
			});
		},

		initTopForumsChart: function() {
			const canvas = document.getElementById('top-forums-chart');
			if (!canvas) return;

			// Destroy existing chart
			const existingChart = Chart.getChart(canvas);
			if (existingChart) {
				existingChart.destroy();
			}

			const forums = wpforoForumActivity.topForums || [];
			if (forums.length === 0) {
				$(canvas).parent().html('<div class="wpforo-ai-analytics-empty"><span class="dashicons dashicons-chart-bar"></span><p>No forum activity in this period</p></div>');
				return;
			}

			const labels = forums.map(function(f) {
				// Truncate long forum names
				const title = f.title || 'Unknown';
				return title.length > 20 ? title.substring(0, 18) + '...' : title;
			});
			const topicsData = forums.map(function(f) { return parseInt(f.topics) || 0; });
			const repliesData = forums.map(function(f) { return parseInt(f.replies) || 0; });

			this.charts.topForums = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [
						{
							label: wpforoForumActivity.i18n.topics || 'Topics',
							data: topicsData,
							backgroundColor: 'rgba(76, 175, 80, 0.8)',
							borderColor: '#4CAF50',
							borderWidth: 1
						},
						{
							label: wpforoForumActivity.i18n.replies || 'Replies',
							data: repliesData,
							backgroundColor: 'rgba(33, 150, 243, 0.8)',
							borderColor: '#2196F3',
							borderWidth: 1
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					indexAxis: 'y',
					plugins: {
						legend: {
							position: 'top',
							labels: {
								usePointStyle: true,
								padding: 10
							}
						},
						tooltip: {
							callbacks: {
								title: function(context) {
									// Show full forum name in tooltip
									const idx = context[0].dataIndex;
									return forums[idx].title || 'Unknown';
								}
							}
						}
					},
					scales: {
						x: {
							beginAtZero: true,
							stacked: true,
							ticks: {
								precision: 0
							}
						},
						y: {
							stacked: true
						}
					}
				}
			});
		}
	};

	// Initialize Forum Activity if on that sub-tab
	if (typeof wpforoForumActivity !== 'undefined') {
		WpForoForumActivity.init();
	}

	window.WpForoForumActivity = WpForoForumActivity;

	// ===== User Engagement Analytics =====
	const WpForoUserEngagement = {
		charts: {},

		init: function() {
			if (typeof wpforoUserEngagement === 'undefined') {
				return;
			}

			this.initRegistrationsChart();
			this.initDistributionChart();
		},

		destroyCharts: function() {
			Object.keys(this.charts).forEach(function(key) {
				if (this.charts[key]) {
					this.charts[key].destroy();
					this.charts[key] = null;
				}
			}.bind(this));
		},

		initRegistrationsChart: function() {
			const canvas = document.getElementById('registrations-chart');
			if (!canvas) return;

			// Destroy existing chart
			const existingChart = Chart.getChart(canvas);
			if (existingChart) {
				existingChart.destroy();
			}

			const data = wpforoUserEngagement.registrations || [];
			const showYear = data.length > 1 && new Date(data[0].date).getFullYear() !== new Date(data[data.length - 1].date).getFullYear();
			const labels = data.map(function(d) {
				const date = new Date(d.date);
				const opts = showYear ? { month: 'short', day: 'numeric', year: '2-digit' } : { month: 'short', day: 'numeric' };
				return date.toLocaleDateString(undefined, opts);
			});
			const counts = data.map(function(d) { return d.count || 0; });

			this.charts.registrations = new Chart(canvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: wpforoUserEngagement.i18n.newRegistrations || 'New Registrations',
						data: counts,
						borderColor: '#9C27B0',
						backgroundColor: 'rgba(156, 39, 176, 0.1)',
						fill: true,
						tension: 0.3,
						borderWidth: 2,
						pointRadius: 3,
						pointHoverRadius: 5
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						intersect: false,
						mode: 'index'
					},
					plugins: {
						legend: {
							position: 'top',
							labels: {
								usePointStyle: true,
								padding: 15
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								precision: 0
							}
						}
					}
				}
			});
		},

		initDistributionChart: function() {
			const canvas = document.getElementById('user-distribution-chart');
			if (!canvas) return;

			// Destroy existing chart
			const existingChart = Chart.getChart(canvas);
			if (existingChart) {
				existingChart.destroy();
			}

			const dist = wpforoUserEngagement.distribution || {};
			const i18n = wpforoUserEngagement.i18n || {};

			const labels = [
				i18n.powerUsers || 'Power Users (50+)',
				i18n.activeUsers || 'Active (10-49)',
				i18n.occasional || 'Occasional (2-9)',
				i18n.oneTime || 'One-time (1)',
				i18n.lurkers || 'Lurkers (0)'
			];

			const data = [
				dist.power_users || 0,
				dist.active_users || 0,
				dist.occasional || 0,
				dist.one_time || 0,
				dist.lurkers || 0
			];

			const colors = [
				'#4CAF50',  // Green - Power users
				'#2196F3',  // Blue - Active
				'#FF9800',  // Orange - Occasional
				'#9C27B0',  // Purple - One-time
				'#9E9E9E'   // Gray - Lurkers
			];

			this.charts.distribution = new Chart(canvas, {
				type: 'doughnut',
				data: {
					labels: labels,
					datasets: [{
						data: data,
						backgroundColor: colors,
						borderWidth: 0,
						hoverOffset: 4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: '60%',
					plugins: {
						legend: {
							position: 'right',
							labels: {
								usePointStyle: true,
								padding: 12,
								generateLabels: function(chart) {
									const datasets = chart.data.datasets;
									return chart.data.labels.map(function(label, i) {
										const value = datasets[0].data[i];
										return {
											text: label + ': ' + value.toLocaleString(),
											fillStyle: colors[i],
											strokeStyle: colors[i],
											lineWidth: 0,
											pointStyle: 'circle',
											hidden: false,
											index: i
										};
									});
								}
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									const total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
									const value = context.raw;
									const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
									return context.label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
								}
							}
						}
					}
				}
			});
		}
	};

	// Initialize User Engagement if on that sub-tab
	if (typeof wpforoUserEngagement !== 'undefined') {
		WpForoUserEngagement.init();
	}

	window.WpForoUserEngagement = WpForoUserEngagement;

	/* ==========================================================================
	   Content Performance Analytics Module
	   ========================================================================== */

	var WpForoContentPerformance = {
		charts: {},

		init: function() {
			this.initForumDistributionChart();
		},

		initForumDistributionChart: function() {
			const canvas = document.getElementById('content-distribution-chart');
			if (!canvas) return;

			// Destroy existing chart
			const existingChart = Chart.getChart(canvas);
			if (existingChart) {
				existingChart.destroy();
			}

			const distribution = wpforoContentPerformance.forumDistribution || [];
			const i18n = wpforoContentPerformance.i18n || {};

			if (distribution.length === 0) {
				return;
			}

			const labels = distribution.map(function(item) {
				return item.title;
			});

			const data = distribution.map(function(item) {
				return parseInt(item.topics, 10);
			});

			// Generate colors for each forum
			const colors = this.generateColors(distribution.length);

			this.charts.forumDistribution = new Chart(canvas, {
				type: 'pie',
				data: {
					labels: labels,
					datasets: [{
						data: data,
						backgroundColor: colors,
						borderWidth: 2,
						borderColor: '#fff',
						hoverOffset: 8
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'right',
							labels: {
								usePointStyle: true,
								padding: 12,
								font: {
									size: 12
								},
								generateLabels: function(chart) {
									const datasets = chart.data.datasets;
									const total = datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
									return chart.data.labels.map(function(label, i) {
										const value = datasets[0].data[i];
										const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
										return {
											text: label + ': ' + value.toLocaleString() + ' (' + percentage + '%)',
											fillStyle: colors[i],
											strokeStyle: '#fff',
											lineWidth: 1,
											pointStyle: 'circle',
											hidden: false,
											index: i
										};
									});
								}
							}
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									const total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
									const value = context.raw;
									const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
									const topicsLabel = i18n.topics || 'Topics';
									return context.label + ': ' + value.toLocaleString() + ' ' + topicsLabel + ' (' + percentage + '%)';
								}
							}
						}
					}
				}
			});
		},

		generateColors: function(count) {
			// Predefined colors for forums
			const baseColors = [
				'#2196F3',  // Blue
				'#4CAF50',  // Green
				'#FF9800',  // Orange
				'#9C27B0',  // Purple
				'#F44336',  // Red
				'#00BCD4',  // Cyan
				'#795548',  // Brown
				'#607D8B',  // Blue Grey
				'#E91E63',  // Pink
				'#3F51B5',  // Indigo
				'#009688',  // Teal
				'#CDDC39',  // Lime
				'#FFC107',  // Amber
				'#673AB7',  // Deep Purple
				'#8BC34A'   // Light Green
			];

			const colors = [];
			for (var i = 0; i < count; i++) {
				colors.push(baseColors[i % baseColors.length]);
			}
			return colors;
		}
	};

	// Initialize Content Performance if on that sub-tab
	if (typeof wpforoContentPerformance !== 'undefined') {
		WpForoContentPerformance.init();
	}

	window.WpForoContentPerformance = WpForoContentPerformance;

	/* ==========================================================================
	   AI Insights Module
	   ========================================================================== */

	var WpForoAIInsights = {
		config: null,
		activeModal: null,

		init: function() {
			if (typeof wpforoAIInsights === 'undefined') {
				return;
			}
			this.config = wpforoAIInsights;
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Run Insight buttons
			$(document).off('click.aiInsights', '.wpforo-ai-run-insight-btn').on('click.aiInsights', '.wpforo-ai-run-insight-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var insightType = $btn.data('insight-type');
				var credits = parseInt($btn.data('credits'), 10);

				if ($btn.prop('disabled')) {
					return;
				}

				self.showConfirmModal(insightType, credits);
			});
		},

		showConfirmModal: function(insightType, credits) {
			var self = this;
			var i18n = this.config.i18n;

			// Create modal HTML
			var modalHtml = '<div class="wpforo-ai-insights-modal-overlay">' +
				'<div class="wpforo-ai-insights-modal">' +
					'<div class="wpforo-ai-insights-modal-header">' +
						'<h3>' + i18n.confirmTitle + '</h3>' +
					'</div>' +
					'<div class="wpforo-ai-insights-modal-body">' +
						'<p>' + i18n.confirmMessage.replace('%d', credits) + '</p>' +
					'</div>' +
					'<div class="wpforo-ai-insights-modal-footer">' +
						'<button type="button" class="button wpforo-ai-insights-cancel-btn">' + i18n.cancelButton + '</button>' +
						'<button type="button" class="button button-primary wpforo-ai-insights-confirm-btn">' + i18n.confirmButton + '</button>' +
					'</div>' +
				'</div>' +
			'</div>';

			// Remove any existing modal
			this.closeModal();

			// Add modal to body
			$('body').append(modalHtml);
			this.activeModal = $('.wpforo-ai-insights-modal-overlay');

			// Bind modal events
			this.activeModal.find('.wpforo-ai-insights-cancel-btn').on('click', function() {
				self.closeModal();
			});

			this.activeModal.find('.wpforo-ai-insights-confirm-btn').on('click', function() {
				self.closeModal();
				self.runInsight(insightType);
			});

			// Close on overlay click
			this.activeModal.on('click', function(e) {
				if ($(e.target).hasClass('wpforo-ai-insights-modal-overlay')) {
					self.closeModal();
				}
			});

			// Close on escape key
			$(document).on('keydown.aiInsightsModal', function(e) {
				if (e.key === 'Escape') {
					self.closeModal();
				}
			});
		},

		closeModal: function() {
			if (this.activeModal) {
				this.activeModal.remove();
				this.activeModal = null;
			}
			$(document).off('keydown.aiInsightsModal');
		},

		runInsight: function(insightType) {
			var self = this;
			var $widget = $('.wpforo-ai-insights-widget[data-insight-type="' + insightType + '"]');
			var $btn = $widget.find('.wpforo-ai-run-insight-btn');
			var $loading = $widget.find('.wpforo-ai-insights-loading');
			var $results = $widget.find('.wpforo-ai-insights-results');
			var $error = $widget.find('.wpforo-ai-insights-error');

			// Show loading state
			$btn.prop('disabled', true);
			$loading.show();
			$results.hide();
			$error.hide();

			// Make AJAX request
			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_run_insight',
					nonce: this.config.nonce,
					insight_type: insightType,
					board_id: this.config.boardId
				},
				success: function(response) {
					$loading.hide();
					$btn.prop('disabled', false);

					if (response.success) {
						// Update results and add "Just now" cached notice
						var cachedNotice = '<div class="wpforo-ai-insights-cached-notice"><span class="dashicons dashicons-clock"></span> ' + (self.config.i18n.cachedJustNow || 'Just now') + '</div>';
						$results.html(response.data.html + cachedNotice).show();

						// Remove outdated notice since we just refreshed the data
						$widget.find('.wpforo-ai-insights-outdated-notice').remove();

						// Hide outdated notice since we just refreshed the data
						$widget.find('.wpforo-ai-insights-outdated-notice').hide();

						// Update button to "Refresh" state
						if (!$btn.hasClass('has-results')) {
							$btn.html('<span class="dashicons dashicons-update"></span> Refresh Analysis');
							$btn.addClass('has-results');
						}

						// Update credits remaining
						if (response.data.credits_remaining !== undefined) {
							self.config.creditsRemaining = response.data.credits_remaining;
							$('.wpforo-ai-insights-credits-number').text(self.formatNumber(response.data.credits_remaining));
							self.updateButtonStates();
						}
					} else {
						$error.text(response.data.message || self.config.i18n.error).show();
					}
				},
				error: function() {
					$loading.hide();
					$btn.prop('disabled', false);
					$error.text(self.config.i18n.error).show();
				}
			});
		},

		updateButtonStates: function() {
			var self = this;
			$('.wpforo-ai-run-insight-btn').each(function() {
				var $btn = $(this);
				var credits = parseInt($btn.data('credits'), 10);
				var $insufficient = $btn.siblings('.wpforo-ai-insights-insufficient');

				if (self.config.creditsRemaining < credits) {
					$btn.prop('disabled', true);
					if ($insufficient.length === 0) {
						$btn.after('<span class="wpforo-ai-insights-insufficient">' + self.config.i18n.insufficientCredits + '</span>');
					}
				} else {
					$btn.prop('disabled', false);
					$insufficient.remove();
				}
			});
		},

		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
	};

	// Initialize AI Insights if on that sub-tab
	if (typeof wpforoAIInsights !== 'undefined') {
		WpForoAIInsights.init();
	}

	window.WpForoAIInsights = WpForoAIInsights;

	/**
	 * AI Logs Tab Manager
	 * Handles filtering, pagination, detail view, and bulk operations for AI logs
	 */
	const WpForoAILogs = {
		config: {
			perPage: 50,
			currentPage: 1,
			totalLogs: 0,
			viewMode: 'logs', // 'logs' or 'chat_messages'
			filters: {
				action_type: '',
				date_range: 'all',
				status: '',
				user_type: '',
				search: ''
			}
		},

		init: function() {
			if (!$('#wpforo-ai-logs-tab').length) {
				return;
			}

			this.cacheElements();
			this.bindEvents();
			this.updateShowingText();
		},

		cacheElements: function() {
			this.$container = $('#wpforo-ai-logs-tab');
			this.$nonce = this.$container.data('nonce');
			this.$boardid = this.$container.data('boardid') || 0;
			this.$table = $('#wpforo-ai-logs-table');
			this.$tbody = $('#wpforo-ai-logs-tbody');
			this.$loading = $('#wpforo-ai-logs-loading');
			this.$pagination = $('#wpforo-ai-logs-pagination');
			this.$totalCount = $('#wpforo-ai-logs-total-count');
			this.$showing = $('#wpforo-ai-logs-showing');
			this.$detailOverlay = $('#wpforo-ai-log-detail-overlay');
			this.$detailBody = $('#wpforo-ai-log-detail-body');
			this.$emptyConfirmOverlay = $('#wpforo-ai-empty-confirm-overlay');

			// Read per page from data attribute
			var perPageData = this.$pagination.data('per-page');
			if (perPageData) {
				this.config.perPage = parseInt(perPageData, 10) || 50;
			}
		},

		bindEvents: function() {
			var self = this;

			// Unbind all log events first to prevent duplicates
			$(document).off('change', '.wpforo-ai-logs-filter');
			$(document).off('keypress', '#wpforo-ai-logs-search');
			$(document).off('click', '#wpforo-ai-logs-apply-filter');
			$(document).off('click', '#wpforo-ai-logs-reset-filter');
			$(document).off('click', '#wpforo-ai-logs-chat-messages-btn');
			$(document).off('click', '.wpforo-ai-logs-pagination .button');
			$(document).off('change', '#wpforo-ai-logs-select-all');
			$(document).off('click', '#wpforo-ai-logs-bulk-apply');
			$(document).off('click', '#wpforo-ai-empty-logs-btn');
			$(document).off('click', '#wpforo-ai-confirm-empty-logs');
			$(document).off('click', '#wpforo-ai-cancel-empty-logs');
			$(document).off('click', '.wpforo-ai-confirm-overlay');
			$(document).off('blur change', '#wpforo-ai-logs-cleanup-days');
			$(document).off('change', '#wpforo-ai-logs-per-page');
			$(document).off('click', '.wpforo-ai-log-view');
			$(document).off('click', '.wpforo-ai-log-delete');
			$(document).off('click', '.wpforo-ai-chat-message-view');
			$(document).off('click', '.wpforo-ai-log-detail-close');
			$(document).off('click', '.wpforo-ai-log-detail-overlay');

			// Filter controls
			$(document).on('change', '.wpforo-ai-logs-filter', function() {
				// Auto-apply on change for selects
				if ($(this).is('select')) {
					self.applyFilters();
				}
			});

			$(document).on('keypress', '#wpforo-ai-logs-search', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.applyFilters();
				}
			});

			$(document).on('click', '#wpforo-ai-logs-apply-filter', function(e) {
				e.preventDefault();
				self.applyFilters();
			});

			$(document).on('click', '#wpforo-ai-logs-reset-filter', function(e) {
				e.preventDefault();
				self.resetFilters();
			});

			// AI ChatBot Messages button
			$(document).on('click', '#wpforo-ai-logs-chat-messages-btn', function(e) {
				e.preventDefault();
				self.showChatMessages();
			});

			// View chat message detail
			$(document).on('click', '.wpforo-ai-chat-message-view', function(e) {
				e.preventDefault();
				var messageId = $(this).data('message-id');
				self.showChatMessageDetail(messageId);
			});

			// Pagination
			$(document).on('click', '.wpforo-ai-logs-pagination .button', function(e) {
				e.preventDefault();
				if (!$(this).prop('disabled')) {
					var page = $(this).data('page');
					self.goToPage(page);
				}
			});

			// Select all checkbox
			$(document).on('change', '#wpforo-ai-logs-select-all', function() {
				$('.wpforo-ai-log-checkbox').prop('checked', $(this).prop('checked'));
			});

			// Bulk action
			$(document).on('click', '#wpforo-ai-logs-bulk-apply', function(e) {
				e.preventDefault();
				self.applyBulkAction();
			});

			// View log detail
			$(document).on('click', '.wpforo-ai-log-view', function(e) {
				e.preventDefault();
				var logId = $(this).data('log-id');
				self.showLogDetail(logId);
			});

			// Delete single log
			$(document).on('click', '.wpforo-ai-log-delete', function(e) {
				e.preventDefault();
				var logId = $(this).data('log-id');
				if (confirm(wpforoAI.i18n.confirmDelete || 'Are you sure you want to delete this log?')) {
					self.deleteLogs([logId]);
				}
			});

			// Close detail modal
			$(document).on('click', '#wpforo-ai-log-detail-close', function(e) {
				e.preventDefault();
				self.$detailOverlay.hide();
			});

			$(document).on('click', '.wpforo-ai-log-detail-overlay', function(e) {
				if ($(e.target).hasClass('wpforo-ai-log-detail-overlay')) {
					self.$detailOverlay.hide();
				}
			});

			// Empty all logs
			$(document).on('click', '#wpforo-ai-empty-logs-btn', function(e) {
				e.preventDefault();
				self.$emptyConfirmOverlay.show();
			});

			$(document).on('click', '#wpforo-ai-empty-cancel', function(e) {
				e.preventDefault();
				self.$emptyConfirmOverlay.hide();
			});

			$(document).on('click', '#wpforo-ai-empty-confirm', function(e) {
				e.preventDefault();
				self.emptyAllLogs();
			});

			$(document).on('click', '.wpforo-ai-confirm-overlay', function(e) {
				if ($(e.target).hasClass('wpforo-ai-confirm-overlay')) {
					self.$emptyConfirmOverlay.hide();
				}
			});

			// Save cleanup days setting on blur or change (arrows trigger change)
			var cleanupDaysOriginal = $('#wpforo-ai-logs-cleanup-days').val();
			$(document).on('blur change', '#wpforo-ai-logs-cleanup-days', function() {
				var $input = $(this);
				var $spinner = $('#wpforo-ai-logs-cleanup-spinner');
				var $saved = $('#wpforo-ai-logs-cleanup-saved');
				var days = parseInt($input.val(), 10) || 0;

				// Only save if value changed
				if (days.toString() === cleanupDaysOriginal) {
					return;
				}

				$input.prop('disabled', true);
				$spinner.addClass('is-active');
				$saved.removeClass('is-visible');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpforo_ai_save_cleanup_days',
						nonce: self.$nonce,
						boardid: self.$boardid,
						days: days
					},
					success: function(response) {
						$input.prop('disabled', false);
						$spinner.removeClass('is-active');
						if (response.success) {
							cleanupDaysOriginal = days.toString();
							$saved.addClass('is-visible');
							setTimeout(function() { $saved.removeClass('is-visible'); }, 2000);
						}
					},
					error: function() {
						$input.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// Save per page setting on change
			$(document).on('change', '#wpforo-ai-logs-per-page', function() {
				var $select = $(this);
				var $spinner = $('#wpforo-ai-logs-per-page-spinner');
				var $saved = $('#wpforo-ai-logs-per-page-saved');
				var perPage = parseInt($select.val(), 10) || 50;

				$select.prop('disabled', true);
				$spinner.addClass('is-active');
				$saved.removeClass('is-visible');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpforo_ai_save_per_page',
						nonce: self.$nonce,
						boardid: self.$boardid,
						per_page: perPage
					},
					success: function(response) {
						$select.prop('disabled', false);
						$spinner.removeClass('is-active');
						if (response.success) {
							self.config.perPage = perPage;
							self.config.currentPage = 1;
							$saved.addClass('is-visible');
							setTimeout(function() { $saved.removeClass('is-visible'); }, 2000);
							self.loadLogs();
						}
					},
					error: function() {
						$select.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// ESC key to close modals
			$(document).on('keyup', function(e) {
				if (e.key === 'Escape') {
					self.$detailOverlay.hide();
					self.$emptyConfirmOverlay.hide();
				}
			});
		},

		applyFilters: function() {
			this.config.filters.action_type = $('#wpforo-ai-logs-filter-action').val();
			this.config.filters.date_range = $('#wpforo-ai-logs-filter-date').val();
			this.config.filters.status = $('#wpforo-ai-logs-filter-status').val();
			this.config.filters.user_type = $('#wpforo-ai-logs-filter-user-type').val();
			this.config.filters.search = $('#wpforo-ai-logs-search').val();
			this.config.currentPage = 1;

			if (this.config.viewMode === 'chat_messages') {
				this.loadChatMessages();
			} else {
				this.loadLogs();
			}
		},

		resetFilters: function() {
			$('#wpforo-ai-logs-filter-action').val('');
			$('#wpforo-ai-logs-filter-date').val('all');
			$('#wpforo-ai-logs-filter-status').val('');
			$('#wpforo-ai-logs-filter-user-type').val('');
			$('#wpforo-ai-logs-search').val('');
			this.config.filters = {
				action_type: '',
				date_range: 'all',
				status: '',
				user_type: '',
				search: ''
			};
			this.config.currentPage = 1;

			// Always switch back to logs mode on reset
			if (this.config.viewMode === 'chat_messages') {
				this.config.viewMode = 'logs';
				$('#wpforo-ai-logs-chat-messages-btn').removeClass('active');
				$('#wpforo-ai-logs-filter-action').prop('disabled', false);
			}

			this.loadLogs();
		},

		showChatMessages: function() {
			// Update filters from current values (except action type)
			this.config.filters.date_range = $('#wpforo-ai-logs-filter-date').val();
			this.config.filters.status = $('#wpforo-ai-logs-filter-status').val();
			this.config.filters.user_type = $('#wpforo-ai-logs-filter-user-type').val();
			this.config.filters.search = $('#wpforo-ai-logs-search').val();
			this.config.currentPage = 1;
			this.config.viewMode = 'chat_messages';

			// Disable action type filter and highlight button
			$('#wpforo-ai-logs-filter-action').prop('disabled', true);
			$('#wpforo-ai-logs-chat-messages-btn').addClass('active');

			this.loadChatMessages();
		},

		loadChatMessages: function() {
			var self = this;

			self.$loading.show();
			self.$tbody.css('opacity', '0.5');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_chat_messages',
					nonce: self.$nonce,
					boardid: self.$boardid,
					page: self.config.currentPage,
					per_page: self.config.perPage,
					date_range: self.config.filters.date_range,
					status: self.config.filters.status,
					user_type: self.config.filters.user_type,
					search: self.config.filters.search
				},
				success: function(response) {
					self.$loading.hide();
					self.$tbody.css('opacity', '1');

					if (response.success) {
						self.$tbody.html(response.data.html);
						self.config.totalLogs = response.data.total;
						self.updatePagination();
						self.updateShowingText();
						self.$totalCount.text('(' + self.formatNumber(response.data.total) + ')');
						$('#wpforo-ai-logs-select-all').prop('checked', false);
					} else {
						self.showNotice(response.data.message || 'Error loading chat messages', 'error');
					}
				},
				error: function() {
					self.$loading.hide();
					self.$tbody.css('opacity', '1');
					self.showNotice('Failed to load chat messages', 'error');
				}
			});
		},

		showChatMessageDetail: function(messageId) {
			var self = this;

			self.$detailBody.html('<div class="wpforo-ai-logs-loading"><span class="spinner is-active"></span> Loading...</div>');
			self.$detailOverlay.show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_chat_message_detail',
					nonce: self.$nonce,
					boardid: self.$boardid,
					message_id: messageId
				},
				success: function(response) {
					if (response.success) {
						self.$detailBody.html(response.data.html);
					} else {
						self.$detailBody.html('<div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-warning"></span><p>' + (response.data.message || 'Error loading message details') + '</p></div>');
					}
				},
				error: function() {
					self.$detailBody.html('<div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-warning"></span><p>Failed to load message details</p></div>');
				}
			});
		},

		goToPage: function(page) {
			this.config.currentPage = parseInt(page, 10);
			if (this.config.viewMode === 'chat_messages') {
				this.loadChatMessages();
			} else {
				this.loadLogs();
			}
		},

		loadLogs: function() {
			var self = this;

			self.$loading.show();
			self.$tbody.css('opacity', '0.5');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_logs',
					nonce: self.$nonce,
					boardid: self.$boardid,
					page: self.config.currentPage,
					per_page: self.config.perPage,
					action_type: self.config.filters.action_type,
					date_range: self.config.filters.date_range,
					status: self.config.filters.status,
					user_type: self.config.filters.user_type,
					search: self.config.filters.search
				},
				success: function(response) {
					self.$loading.hide();
					self.$tbody.css('opacity', '1');

					if (response.success) {
						self.$tbody.html(response.data.html);
						self.config.totalLogs = response.data.total;
						self.updatePagination();
						self.updateShowingText();
						self.$totalCount.text('(' + self.formatNumber(response.data.total) + ')');
						$('#wpforo-ai-logs-select-all').prop('checked', false);
					} else {
						self.showNotice(response.data.message || 'Error loading logs', 'error');
					}
				},
				error: function() {
					self.$loading.hide();
					self.$tbody.css('opacity', '1');
					self.showNotice('Failed to load logs', 'error');
				}
			});
		},

		updatePagination: function() {
			var totalPages = Math.ceil(this.config.totalLogs / this.config.perPage);
			var currentPage = this.config.currentPage;

			if (totalPages <= 1) {
				this.$pagination.html('');
				return;
			}

			var html = '<div class="tablenav-pages">';
			html += '<span class="displaying-num">' + this.formatNumber(this.config.totalLogs) + ' items</span>';
			html += '<span class="pagination-links">';

			// First page
			html += '<button type="button" class="button first-page" data-page="1" ' + (currentPage === 1 ? 'disabled' : '') + '>';
			html += '<span aria-hidden="true">&laquo;</span></button>';

			// Previous page
			html += '<button type="button" class="button prev-page" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + '>';
			html += '<span aria-hidden="true">&lsaquo;</span></button>';

			// Page indicator
			html += '<span class="paging-input">';
			html += '<span class="current-page">' + currentPage + '</span> of ';
			html += '<span class="total-pages">' + totalPages + '</span>';
			html += '</span>';

			// Next page
			html += '<button type="button" class="button next-page" data-page="' + (currentPage + 1) + '" ' + (currentPage >= totalPages ? 'disabled' : '') + '>';
			html += '<span aria-hidden="true">&rsaquo;</span></button>';

			// Last page
			html += '<button type="button" class="button last-page" data-page="' + totalPages + '" ' + (currentPage >= totalPages ? 'disabled' : '') + '>';
			html += '<span aria-hidden="true">&raquo;</span></button>';

			html += '</span></div>';

			this.$pagination.html(html);
		},

		updateShowingText: function() {
			var start = ((this.config.currentPage - 1) * this.config.perPage) + 1;
			var end = Math.min(this.config.currentPage * this.config.perPage, this.config.totalLogs);

			if (this.config.totalLogs === 0) {
				this.$showing.text('');
			} else {
				this.$showing.text('(Showing ' + start + '-' + end + ' of ' + this.formatNumber(this.config.totalLogs) + ')');
			}
		},

		applyBulkAction: function() {
			var action = $('#wpforo-ai-logs-bulk-action').val();
			if (!action) {
				return;
			}

			var selectedIds = [];
			$('.wpforo-ai-log-checkbox:checked').each(function() {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				this.showNotice('Please select at least one log', 'warning');
				return;
			}

			if (action === 'delete') {
				if (confirm(wpforoAI.i18n.confirmDeleteSelected || 'Are you sure you want to delete the selected logs?')) {
					this.deleteLogs(selectedIds);
				}
			}
		},

		deleteLogs: function(ids) {
			var self = this;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_delete_logs',
					nonce: self.$nonce,
					boardid: self.$boardid,
					log_ids: ids
				},
				success: function(response) {
					if (response.success) {
						self.showNotice(response.data.message || 'Logs deleted successfully', 'success');
						self.loadLogs();
					} else {
						self.showNotice(response.data.message || 'Error deleting logs', 'error');
					}
				},
				error: function() {
					self.showNotice('Failed to delete logs', 'error');
				}
			});
		},

		emptyAllLogs: function() {
			var self = this;

			$('#wpforo-ai-empty-confirm').prop('disabled', true).text('Deleting...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_empty_all_logs',
					nonce: self.$nonce,
					boardid: self.$boardid
				},
				success: function(response) {
					$('#wpforo-ai-empty-confirm').prop('disabled', false).text(wpforoAI.i18n.deleteAllLogs || 'Delete All Logs');
					self.$emptyConfirmOverlay.hide();

					if (response.success) {
						self.showNotice(response.data.message || 'All logs deleted successfully', 'success');
						self.config.totalLogs = 0;
						self.config.currentPage = 1;
						self.$tbody.html('<tr class="wpforo-ai-logs-empty-row"><td colspan="8"><div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-info-outline"></span>' + (wpforoAI.i18n.noLogs || 'No logs found.') + '</div></td></tr>');
						self.$totalCount.text('(0)');
						self.updatePagination();
						self.updateShowingText();
					} else {
						self.showNotice(response.data.message || 'Error deleting logs', 'error');
					}
				},
				error: function() {
					$('#wpforo-ai-empty-confirm').prop('disabled', false).text(wpforoAI.i18n.deleteAllLogs || 'Delete All Logs');
					self.$emptyConfirmOverlay.hide();
					self.showNotice('Failed to delete all logs', 'error');
				}
			});
		},

		showLogDetail: function(logId) {
			var self = this;

			self.$detailBody.html('<div class="wpforo-ai-logs-loading"><span class="spinner is-active"></span> Loading...</div>');
			self.$detailOverlay.show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpforo_ai_get_log_detail',
					nonce: self.$nonce,
					boardid: self.$boardid,
					log_id: logId
				},
				success: function(response) {
					if (response.success) {
						self.$detailBody.html(response.data.html);
					} else {
						self.$detailBody.html('<div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-warning"></span><p>' + (response.data.message || 'Error loading log details') + '</p></div>');
					}
				},
				error: function() {
					self.$detailBody.html('<div class="wpforo-ai-logs-empty"><span class="dashicons dashicons-warning"></span><p>Failed to load log details</p></div>');
				}
			});
		},

		showNotice: function(message, type) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wpforo-ai-logs-tab .wpforo-ai-box:first').before($notice);

			// Auto dismiss after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);

			// Make dismissible
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			});
		},

		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
	};

	// Initialize AI Logs if on that tab
	$(document).ready(function() {
		WpForoAILogs.init();

		// Re-init when tab is shown (in case of dynamic tab switching)
		$(document).on('click', '.wpforo-admin-tabs a', function() {
			setTimeout(function() {
				WpForoAILogs.init();
			}, 100);
		});
	});

	window.WpForoAILogs = WpForoAILogs;
});
