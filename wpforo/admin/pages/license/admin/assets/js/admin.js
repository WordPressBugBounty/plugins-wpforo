/**
 * gVectors License Module - Admin JavaScript
 * Handles product listing, centralized checkout (new tab), license management, addon install/activate
 */
(function ($) {
	'use strict';

	let gVectorsLicense = {
		products: [],
		_pollingActive: false,
		_checkoutOptions: null,

		init: function () {
			var $wrap = $('#gvlicense-admin-wrap');
			var slug = $wrap.data('slug') || 'gvectors';
			this.config = window[slug + 'License'] || {};
			this.ajaxPrefix = this.config.ajaxPrefix || 'gvectors_';

			this.bindEvents();
			this.loadProducts();
			this.loadLicenses();
			this.resumePendingTransactions();
		},

		// ==========================================
		// Visibility-based polling
		// ==========================================
		bindVisibilityPolling: function () {
			const self = this;
			document.addEventListener('visibilitychange', function(){
				self.startPolling( self );
			});
		},
		
		startPolling: function( s ){
			const self = s || this;
			if (document.visibilityState === 'visible') {
				if (self._currentTransactionId && !self._pollingActive) {
					self.pollTransactionStatus(self._currentTransactionId);
				}
			} else {
				self._pollingActive = false;
			}
		},

		/**
		 * Poll the proxy server to check if the webhook has arrived and a license was created.
		 */
		pollTransactionStatus: function (transactionId, attempt) {
			const self = this;
			attempt = attempt || 1;
			const maxAttempts = 15;
			const interval = 2000; // 2 seconds

			this._pollingActive = true;
			this.showToast(this.config.i18n.processing, 'info');

			this.ajax('verify_transaction', {
				transaction_id: transactionId,
			}, function (response) {
				if (response.success && response.data) {
					const status = response.data.status;

					if (status === 'completed') {
						self._pollingActive = false;
						self.removeCheckoutOverlay();
						self.clearPendingTransaction(transactionId);
						self._currentTransactionId = null;

						// All post-purchase actions (subscription cancellation, domain deactivation)
						// are handled server-side by the webhook via checkout_options
						self._checkoutOptions = null;

						// Bundle: multiple licenses
						if (response.data.is_bundle && response.data.licenses && response.data.licenses.length) {
							self.activateBundleLicenses(response.data.licenses);
							return;
						}
						// Single license
						if (response.data.license) {
							const license = response.data.license;
							self.activateLicenseAfterPurchase(license.license_key, license.product_id);
							return;
						}
					}

					if (status === 'duplicate') {
						self._pollingActive = false;
						self.removeCheckoutOverlay();
						self.clearPendingTransaction(transactionId);
						self._currentTransactionId = null;
						self.showToast(response.data.message || 'License already active.', 'info');
						self.loadProducts();
						self.loadLicenses();
						return;
					}

					// Still pending — retry if tab is visible
					if (attempt < maxAttempts && self._pollingActive) {
						setTimeout(function () {
							self.pollTransactionStatus(transactionId, attempt + 1);
						}, interval);
					} else {
						self._pollingActive = false;
						self.removeCheckoutOverlay();
						self.showToast(self.config.i18n.purchasePending || 'Purchase completed! License will be activated shortly.', 'info');
						self.loadProducts();
						self.loadLicenses();
					}
				}
			});
		},

		/**
		 * Save a pending transaction ID to the server so it persists across page reloads.
		 */
		savePendingTransaction: function (transactionId) {
			this.ajax('save_pending_transaction', {
				transaction_id: transactionId,
			}, function () {});
		},

		/**
		 * Clear a completed/resolved pending transaction from the server.
		 */
		clearPendingTransaction: function (transactionId) {
			this.ajax('clear_pending_transaction', {
				transaction_id: transactionId,
			}, function () {});
		},

		/**
		 * On a dashboard load, check for any pending transactions that were not
		 * completed (e.g., the page was closed/reloaded during checkout polling).
		 * Verify and activate them in the background.
		 */
		resumePendingTransactions: function () {
			const self = this;
			this.ajax('get_pending_transactions', {}, function (response) {
				if (response.success && response.data && response.data.length) {
					for (let i = 0; i < response.data.length; i++) {
						self.resumeTransaction(response.data[i]);
					}
				}
			});
		},

		/**
		 * Silently verify a single pending transaction and activate its license(s) if completed.
		 */
		resumeTransaction: function (transactionId, attempt) {
			const self = this;
			attempt = attempt || 1;
			const maxAttempts = 10;
			const interval = 3000;

			this.ajax('verify_transaction', {
				transaction_id: transactionId,
			}, function (response) {
				if (response.success && response.data) {
					const status = response.data.status;

					if (status === 'completed') {
						self.clearPendingTransaction(transactionId);
						// Bundle
						if (response.data.is_bundle && response.data.licenses && response.data.licenses.length) {
							self.activateBundleLicenses(response.data.licenses);
							return;
						}
						// Single
						if (response.data.license) {
							const license = response.data.license;
							self.activateLicenseAfterPurchase(license.license_key, license.product_id);
							return;
						}
						// Completed but no license data — just refresh
						self.loadProducts();
						self.loadLicenses();
						return;
					}

					if (status === 'duplicate') {
						self.clearPendingTransaction(transactionId);
						self.loadProducts();
						self.loadLicenses();
						return;
					}

					// Still pending — retry silently
					if (attempt < maxAttempts) {
						setTimeout(function () {
							self.resumeTransaction(transactionId, attempt + 1);
						}, interval);
					}
					// After max attempts, leave it pending for next page load
				}
			});
		},

		// ==========================================
		// Event Bindings
		// ==========================================
		bindEvents: function () {
			const self = this;

			// Refresh products
			$(document).on('click', '#gvlicense-refresh', function () {
				self.ajax('clear_cache', {}, function () {
					self.loadProducts();
					self.loadLicenses();
				});
			});

			// Unified activate (license key, transaction ID, or auto-detect by domain)
			$(document).on('click', '#gvlicense-unified-activate-btn', function () {
				self.unifiedActivate();
			});

			// Buy button - show pre-checkout dialog (overlap check + confirmation)
			$(document).on('click', '.gvlicense-buy-btn', function () {
				const $btn = $(this);
				const $card = $btn.closest('.gvlicense-product-card');
				const productId = $btn.data('product-id');

				// Read from select box if present, otherwise use button's data attribute
				const $select = $card.find('.gvlicense-price-select');
				const priceId = $select.length ? $select.val() : $btn.data('price-id');

				if (!priceId) {
					alert('Please select a price option for this product.');
					return;
				}

				self.openCheckout(priceId, productId, $btn);
			});

			// Price select change — update detail display and buy button
			$(document).on('change', '.gvlicense-price-select', function () {
				const $select = $(this);
				const $card = $select.closest('.gvlicense-product-card');
				const $opt = $select.find('option:selected');
				const $detail = $card.find('.gvlicense-price-detail');

				// Update price detail display
				$detail.find('.gvlicense-price-amount').text($opt.data('price') || '');
				$detail.find('.gvlicense-price-interval').text($opt.data('interval') || '');

				var name = $opt.data('name') || '';
				var $nameEl = $detail.find('.gvlicense-price-name');
				if (name) {
					if ($nameEl.length) { $nameEl.text(name); }
					else { $detail.append('<span class="gvlicense-price-name">' + $('<span>').text(name).html() + '</span>'); }
				} else {
					$nameEl.remove();
				}

				var desc = $opt.data('description') || '';
				var $descEl = $detail.find('.gvlicense-price-desc');
				if (desc) {
					if ($descEl.length) { $descEl.text(desc); }
					else { $detail.append('<span class="gvlicense-price-desc">' + $('<span>').text(desc).html() + '</span>'); }
				} else {
					$descEl.remove();
				}

				// Update buy button data attribute
				$card.find('.gvlicense-buy-btn').data('price-id', $select.val());
			});

			// Start trial
			$(document).on('click', '.gvlicense-trial-btn', function () {
				const productId = $(this).data('product-id');
				self.startTrial(productId, $(this));
			});

			// Install & Activate addon
			$(document).on('click', '.gvlicense-install-btn', function () {
				const productId = $(this).data('product-id');
				self.installAndActivateAddon(productId, $(this));
			});

			// Activate addon (already installed)
			$(document).on('click', '.gvlicense-activate-addon-btn', function () {
				const productId = $(this).data('product-id');
				self.installAndActivateAddon(productId, $(this));
			});

			// Manage button
			$(document).on('click', '.gvlicense-manage-btn', function () {
				const productId = $(this).data('product-id');
				self.openManageModal(productId);
			});

			// Close modal
			$(document).on('click', '.gvlicense-modal-close, .gvlicense-modal-overlay', function () {
				$('#gvlicense-manage-modal').hide();
			});

			// Deactivate license (from account page or modal)
			$(document).on('click', '.gvlicense-deactivate-btn', function () {
				const productId = $(this).data('product-id');
				self.deactivateLicense(productId, $(this));
			});

			// Validate all licenses (single batch button)
			$(document).on('click', '.gvlicense-validate-all-btn', function () {
				self.validateAllLicenses($(this));
			});

			// Subscription management
			$(document).on('click', '.gvlicense-cancel-sub-btn', function () {
				if (!confirm('Are you sure you want to cancel this subscription? It will remain active until the end of the current billing period.')) return;
				const subId = $(this).data('subscription-id');
				self.manageSubscription('cancel', subId, $(this));
			});
			$(document).on('click', '.gvlicense-resume-sub-btn', function () {
				const subId = $(this).data('subscription-id');
				self.manageSubscription('resume', subId, $(this));
			});
			// Resubscribe - close modal and trigger buy on the product card
			$(document).on('click', '.gvlicense-resubscribe-btn', function () {
				const productId = $(this).data('product-id');
				$('#gvlicense-manage-modal').hide();
				const $card = $('.gvlicense-product-card').filter(function () {
					return $(this).find('.gvlicense-buy-btn[data-product-id="' + productId + '"]').length > 0;
				});
				if ($card.length) {
					$card.find('.gvlicense-buy-btn').first().trigger('click');
				} else {
					self.showToast('Please use the Buy button on the product card to resubscribe.', 'info');
				}
			});
			$(document).on('click', '.gvlicense-manage-paddle-btn', function () {
				const $btn = $(this);
				const subId = $btn.data('subscription-id');
				self.setLoading($btn, true);
				self.ajax('get_portal_url', { subscription_id: subId }, function (response) {
					self.setLoading($btn, false);
					if (response.success && response.data && response.data.portal_url) {
						window.open(response.data.portal_url, '_blank');
					} else {
						const msg = (response.data && response.data.message) ? response.data.message : 'Failed to open Paddle portal.';
						self.showToast(msg, 'error');
					}
				});
			});
		},

		// ==========================================
		// Products
		// ==========================================
		loadProducts: function () {
			const self = this;
			const $grid = $('#gvlicense-products-grid');

			if (!$grid.length) return;

			$grid.html('<div class="gvlicense-loading"><span class="spinner is-active" style="float:none;"></span> ' + this.config.i18n.loading + '</div>');

			this.ajax('get_products', {}, function (response) {
				if (response.success && response.data) {
					const data = response.data;
					const products = data.products || data;
					const checkoutMode = data.checkout_mode || 'both';
					self.products = products;
					self.checkoutMode = checkoutMode;
					self.renderProducts(products);
				} else {
					$grid.html('<div class="gvlicense-no-products">' + self.config.i18n.noProducts + '</div>');
				}
			});
		},

		renderProducts: function (products) {
			const $grid = $('#gvlicense-products-grid');
			$grid.empty();

			if (!products.length) {
				$grid.html('<div class="gvlicense-no-products">' + this.config.i18n.noProducts + '</div>');
				return;
			}

			const template = wp.template('gvlicense-product-card');
			for (let i = 0; i < products.length; i++) {
				products[i].checkout_mode = this.checkoutMode || 'both';
				$grid.append(template(products[i]));
			}

			// Populate the unified product select box
			const $select = $('#gvlicense-unified-product');
			if ($select.length) {
				$select.find('option:not(:first)').remove();
				for (let j = 0; j < products.length; j++) {
					if (!products[j].is_bundle && products[j].id) {
						$select.append('<option value="' + products[j].id + '">' + $('<span>').text(products[j].name).html() + '</option>');
					}
				}
			}
		},

		// ==========================================
		// Checkout Overlay
		// ==========================================
		showCheckoutOverlay: function (message) {
			var self = this;
			this.removeCheckoutOverlay();
			const html = '<div id="gvlicense-checkout-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;">' +
			           '<div id="gvlicense-checkout-overlay-box" style="background:#fff;padding:40px 50px;border-radius:8px;text-align:center;max-width:420px;box-shadow:0 4px 24px rgba(0,0,0,0.3);position:relative;">' +
			           '<button type="button" id="gvlicense-checkout-overlay-close" style="position:absolute;top:8px;right:12px;background:none;border:none;font-size:22px;color:#999;cursor:pointer;line-height:1;padding:4px;">&times;</button>' +
			           '<span class="spinner is-active" style="float:none;margin:0 auto 15px;display:block;"></span>' +
			           '<p id="gvlicense-checkout-overlay-msg" style="font-size:15px;margin:0;color:#333;">' + message + '</p>' +
			           '</div></div>';
			$('body').append(html);

			// Close on X button click
			$('#gvlicense-checkout-overlay-close').on('click', function () {
				self.removeCheckoutOverlay();
				self._pollingActive = false;
			});

			// Close on click outside the dialog box
			$('#gvlicense-checkout-overlay').on('click', function (e) {
				if (e.target === this) {
					self.removeCheckoutOverlay();
					self._pollingActive = false;
				}
			});
		},

		updateCheckoutOverlay: function (message) {
			$('#gvlicense-checkout-overlay-msg').html(message);
		},

		removeCheckoutOverlay: function () {
			$('#gvlicense-checkout-overlay').remove();
		},

		// ==========================================
		// Checkout
		// ==========================================

		/**
		 * Pre-checkout flow: show dialog, check overlaps, let customer decide, then create transaction.
		 * No popup is opened until the customer clicks "Continue to Checkout" inside the dialog.
		 */
		openCheckout: function (priceId, productId, $btn) {
			const self = this;

			this.setLoading($btn, true);

			// Show dialog immediately with a loading state
			$('#gvlicense-modal-title').text('Preparing Checkout');
			$('#gvlicense-modal-body').html(
				'<div style="text-align:center;padding:20px 0;">' +
				'<span class="spinner is-active" style="float:none;margin:0 auto 10px;display:block;"></span>' +
				'<p style="color:#666;margin:0;">Checking your subscription status...</p>' +
				'</div>'
			);
			$('#gvlicense-manage-modal').show();

			// Check for overlaps (no transaction created yet)
			this.ajax('check_overlap', {
				price_id: priceId,
				product_id: productId,
			}, function (response) {
				self.setLoading($btn, false);

				if (!response.success) {
					$('#gvlicense-manage-modal').hide();
					var msg = (response.data && response.data.message) ? response.data.message : self.config.i18n.checkoutError;
					self.showToast(msg, 'error');
					return;
				}

				var data = response.data || {};
				var overlaps = data.overlapping_licenses || [];
				var overlapType = data.overlap_type || 'none';

				// Always show the modal — with or without overlaps
				self.showPreCheckoutPrompt(overlapType, data.message || '', overlaps, function (checkoutOptions) {
					self._checkoutOptions = checkoutOptions;
					self.startCheckout(priceId, productId, $btn);
				});
			});
		},

		/**
		 * Show the pre-checkout modal with overlap details or clean confirmation.
		 */
		showPreCheckoutPrompt: function (overlapType, message, overlaps, onProceed) {
			var subscriptionChoices = {};  // keyed by subscription_id → { action, product_id, product_name, license_keys, price_id }
			var html = '';

			if (overlapType === 'none' || !overlaps.length) {
				// No conflicts
				$('#gvlicense-modal-title').text('Ready to Checkout');
				html = '<div class="gvlicense-modal-section">' +
				       '<p style="margin:0 0 20px;color:#1e7e34;font-size:13px;line-height:1.5;">' +
				       'You are ready to proceed with your purchase.' +
				       '</p>' +
				       '<div style="display:flex;gap:10px;justify-content:flex-end;">' +
				       '<button type="button" class="button gvlicense-overlap-abort-btn">Cancel</button>' +
				       '<button type="button" class="button button-primary gvlicense-overlap-proceed-btn">Continue to Checkout</button>' +
				       '</div></div>';
				
				$('#gvlicense-modal-body').html(html);
				$('#gvlicense-manage-modal').off('click.overlap').on('click.overlap', '.gvlicense-overlap-proceed-btn', function () {
					$('#gvlicense-manage-modal').off('click.overlap').hide();
					if (typeof onProceed === 'function') onProceed(null);
				}).on('click.overlap', '.gvlicense-overlap-abort-btn, .gvlicense-modal-close', function () {
					$('#gvlicense-manage-modal').off('click.overlap').hide();
				});
				return;
			}

			// Has overlaps — show details
			var titles = {
				duplicate: 'Duplicate Subscription Detected',
				downgrade: 'Existing Higher-Tier Subscription',
				upgrade: 'Subscription Upgrade',
				lifetime: 'Lifetime Purchase',
				plan_change: 'Plan Change Detected'
			};
			$('#gvlicense-modal-title').text(titles[overlapType] || 'Subscription Conflict');

			html = '<div class="gvlicense-modal-section">' +
				'<p style="margin:0 0 15px;color:#555;font-size:13px;line-height:1.5;">' +
				$('<span>').text(message).html() +
				'</p>';

			// Group by subscription_id to avoid showing duplicates
			var seenSubs = {};
			for (var i = 0; i < overlaps.length; i++) {
				var lic = overlaps[i];
				var subId = lic.subscription_id || '';
				var groupKey = subId || ('lic_' + i);
				if (seenSubs[groupKey]) {
					// Add to existing group
					seenSubs[groupKey].licenses.push(lic);
					continue;
				}
				seenSubs[groupKey] = { subscription_id: subId, licenses: [lic] };
			}

			var siteDomain = this.config.siteDomain || '';

			for (var key in seenSubs) {
				var group = seenSubs[key];
				var firstLic = group.licenses[0];
				var subIdDisplay = group.subscription_id;
				var isBundle = group.licenses.length > 1;
				var groupName = isBundle ? (firstLic.product_name + ' (Bundle)') : firstLic.product_name;

				html += '<div class="gvlicense-overlap-row" data-subscription-id="' + subIdDisplay + '" ' +
					'style="background:#f9f9f9;border:1px solid #e5e5e5;border-radius:4px;padding:14px 16px;margin-bottom:12px;">';

				html += '<div style="margin-bottom:10px;">' +
					'<strong style="font-size:14px;">' + $('<span>').text(groupName).html() + '</strong>';
				if (firstLic.plan_name) {
					html += ' <span style="color:#888;font-size:12px;">(' + $('<span>').text(firstLic.plan_name).html() + ')</span>';
				}
				html += '</div>';

				// License details table
				html += '<table class="gvlicense-modal-table" style="margin-bottom:10px;">';

				// Show each license in the group
				for (var li = 0; li < group.licenses.length; li++) {
					var l = group.licenses[li];
					if (isBundle) {
						html += '<tr><th colspan="2" style="padding-top:8px;color:#23282d;font-weight:600;">' +
							$('<span>').text(l.plugin_slug).html() + '</th></tr>';
					}
					html += '<tr><th>License Key</th><td><code>' + $('<span>').text(l.license_key).html() + '</code></td></tr>';
					if (l.transaction_id) {
						html += '<tr><th>Transaction</th><td><code>' + $('<span>').text(l.transaction_id).html() + '</code></td></tr>';
					}
					html += '<tr><th>Sites</th><td>' + l.sites_used + ' / ' + l.max_sites + ' used</td></tr>';
					if (l.expires_at) {
						html += '<tr><th>Expires</th><td>' + $('<span>').text(l.expires_at).html() + '</td></tr>';
					}
				}

				if (subIdDisplay) {
					html += '<tr><th>Subscription</th><td><code style="font-size:11px;">' + $('<span>').text(subIdDisplay).html() + '</code></td></tr>';
				}
				html += '</table>';

				// Action buttons
				html += '<div class="gvlicense-overlap-actions" style="display:flex;gap:8px;" data-subscription-id="' + subIdDisplay + '">';
				html += '<button type="button" class="button gvlicense-overlap-cancel-btn" data-subscription-id="' + subIdDisplay + '">' +
					'Cancel old subscription</button>';
				html += '<button type="button" class="button gvlicense-overlap-keep-btn" data-subscription-id="' + subIdDisplay + '">' +
					'Keep both active</button>';
				html += '</div>';

				html += '</div>';
			}

			// Help text
			html += '<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:4px;padding:12px 14px;margin-top:8px;margin-bottom:12px;">' +
				'<p style="margin:0;font-size:12px;color:#6d4c00;line-height:1.5;">' +
				'<strong>Note:</strong> If you choose to keep both subscriptions active, you can use the old license key to activate on another domain ' +
				'(if your max sites limit allows). Save your license key and transaction ID for future reference.' +
				'<br>Cancelled subscriptions remain active until the end of the current billing period.' +
				'</p></div>';

			// Validation message (hidden by default)
			html += '<p class="gvlicense-overlap-validation" style="display:none;color:#c62828;font-size:12px;margin:0 0 10px;text-align:right;">' +
				'Please choose an action for each subscription above before proceeding.' +
				'</p>';

			// Footer buttons
			html += '<div style="display:flex;gap:10px;justify-content:flex-end;padding-top:12px;border-top:1px solid #eee;">' +
				'<button type="button" class="button gvlicense-overlap-abort-btn">Cancel Purchase</button>' +
				'<button type="button" class="button button-primary gvlicense-overlap-proceed-btn" disabled>Continue to Checkout</button>' +
				'</div></div>';

			$('#gvlicense-modal-body').html(html);

			// Helper: check if all subscriptions have a decision and enable/disable proceed button
			function updateProceedState() {
				var allDecided = true;
				for (var sk in seenSubs) {
					var subSid = seenSubs[sk].subscription_id;
					if (subSid && !subscriptionChoices[subSid]) {
						allDecided = false;
						break;
					}
				}
				var $btn = $('.gvlicense-overlap-proceed-btn');
				var $msg = $('.gvlicense-overlap-validation');
				if (allDecided) {
					$btn.prop('disabled', false);
					$msg.hide();
				} else {
					$btn.prop('disabled', true);
				}
			}

			// Helper: collect license/product info for a subscription from overlaps data
			function getSubInfo(sid) {
				var info = { license_keys: [], product_id: '', product_name: '', price_id: '' };
				for (var oi = 0; oi < overlaps.length; oi++) {
					if ((overlaps[oi].subscription_id || '') === sid) {
						if (overlaps[oi].license_key) info.license_keys.push(overlaps[oi].license_key);
						if (!info.product_id && overlaps[oi].product_id) info.product_id = overlaps[oi].product_id;
						if (!info.product_name && overlaps[oi].product_name) info.product_name = overlaps[oi].product_name;
						if (!info.price_id && overlaps[oi].price_id) info.price_id = overlaps[oi].price_id;
					}
				}
				return info;
			}

			// Per-subscription toggle handlers
			$('#gvlicense-manage-modal').off('click.overlap').on('click.overlap', '.gvlicense-overlap-cancel-btn', function () {
				var sid = $(this).data('subscription-id');
				var $actions = $(this).closest('.gvlicense-overlap-actions');
				$actions.html(
					'<span style="color:#c62828;font-weight:600;">Will be cancelled after purchase</span>' +
					' <button type="button" class="button button-link gvlicense-overlap-undo-btn" data-subscription-id="' + sid + '" style="margin-left:8px;">Undo</button>'
				);
				var info = getSubInfo(sid);
				subscriptionChoices[sid] = {
					action: 'cancel',
					product_id: info.product_id,
					product_name: info.product_name,
					license_keys: info.license_keys,
					price_id: info.price_id
				};
				updateProceedState();
			}).on('click.overlap', '.gvlicense-overlap-keep-btn', function () {
				var sid = $(this).data('subscription-id');
				var $actions = $(this).closest('.gvlicense-overlap-actions');
				var info = getSubInfo(sid);

				// Collect license keys where current domain is in activated_sites (for deactivation)
				var deactivateKeys = [];
				for (var oi = 0; oi < overlaps.length; oi++) {
					if ((overlaps[oi].subscription_id || '') === sid && overlaps[oi].license_key) {
						var sites = overlaps[oi].activated_sites || [];
						var domainNorm = siteDomain.replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '').toLowerCase();
						for (var si = 0; si < sites.length; si++) {
							var siteNorm = sites[si].replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '').toLowerCase();
							if (siteNorm === domainNorm) {
								deactivateKeys.push(overlaps[oi].license_key);
								break;
							}
						}
					}
				}

				$actions.html(
					'<span style="color:#1e7e34;font-weight:600;">Keeping active</span>' +
					' <button type="button" class="button button-link gvlicense-overlap-undo-btn" data-subscription-id="' + sid + '" style="margin-left:8px;">Undo</button>'
				);
				subscriptionChoices[sid] = {
					action: 'keep',
					product_id: info.product_id,
					product_name: info.product_name,
					license_keys: info.license_keys,
					price_id: info.price_id,
					deactivate_keys: deactivateKeys
				};
				updateProceedState();
			}).on('click.overlap', '.gvlicense-overlap-undo-btn', function () {
				var sid = $(this).data('subscription-id');
				var $actions = $(this).closest('.gvlicense-overlap-actions');
				$actions.html(
					'<button type="button" class="button gvlicense-overlap-cancel-btn" data-subscription-id="' + sid + '">Cancel old subscription</button>' +
					'<button type="button" class="button gvlicense-overlap-keep-btn" data-subscription-id="' + sid + '">Keep both active</button>'
				);
				delete subscriptionChoices[sid];
				updateProceedState();
			});

			// Proceed — require a choice for every overlapping subscription
			$('#gvlicense-manage-modal').on('click.overlap', '.gvlicense-overlap-proceed-btn', function () {
				// Safety check (button should already be disabled, but guard anyway)
				var allDecided = true;
				for (var sk in seenSubs) {
					var subSid = seenSubs[sk].subscription_id;
					if (subSid && !subscriptionChoices[subSid]) {
						allDecided = false;
						break;
					}
				}
				if (!allDecided) {
					$('.gvlicense-overlap-validation').show();
					return;
				}

				$('#gvlicense-manage-modal').off('click.overlap').hide();
				var hasChoices = Object.keys(subscriptionChoices).length > 0;
				var checkoutOpts = hasChoices ? {
					site_domain: siteDomain,
					decided_at: new Date().toISOString(),
					overlap_type: overlapType,
					subscriptions: subscriptionChoices
				} : null;
				if (typeof onProceed === 'function') onProceed(checkoutOpts);
			});

			// Abort
			$('#gvlicense-manage-modal').on('click.overlap', '.gvlicense-overlap-abort-btn', function () {
				$('#gvlicense-manage-modal').off('click.overlap').hide();
			});

			// Close button
			$('#gvlicense-manage-modal').on('click.overlap', '.gvlicense-modal-close', function () {
				$('#gvlicense-manage-modal').off('click.overlap').hide();
			});
		},

		/**
		 * Open a popup, create a transaction, and redirect.
		 * Called from a user click handler inside the modal, so the popup won't be blocked.
		 */
		startCheckout: function (priceId, productId, $btn) {
			const self = this;

			// Open the popup from this user-initiated click context
			// 1. Define desired popup dimensions
			const width = 850;
			const height = 700;
			
			// 2. Calculate the center position relative to the user's primary monitor screen
			const left = (window.screen.width / 2) - (width / 2);
			const top = (window.screen.height / 2) - (height / 2);
			var checkoutWindow = window.open(this.config.checkout_loading_url, 'gVectorsCheckout', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`);
			if (!checkoutWindow) {
				self.showToast('Popup blocked. Please allow popups and try again.', 'error');
				return;
			}

			self.setLoading($btn, true);
			self.showCheckoutOverlay('Creating checkout...');

			var ajaxData = {
				price_id: priceId,
				product_id: productId,
			};

			// Pass full customer overlap choices to be stored on the transaction
			if (self._checkoutOptions) {
				ajaxData.checkout_options = self._checkoutOptions;
			}

			this.ajax('create_checkout', ajaxData, function (response) {
				self.setLoading($btn, false);

				if (!response.success || !response.data || !response.data.transaction_id) {
					var msg = (response.data && response.data.message)
						? response.data.message
						: self.config.i18n.checkoutError;

					self.removeCheckoutOverlay();
					self.showToast(msg, 'error');
					if (checkoutWindow && !checkoutWindow.closed) checkoutWindow.close();
					return;
				}

				var transactionId = response.data.transaction_id;
				self._currentTransactionId = transactionId;

				self.savePendingTransaction(transactionId);
				self.updateCheckoutOverlay('Redirecting to secure checkout...');
				self.proceedToCheckout(transactionId, checkoutWindow);
			});
		},

		// ==========================================
		// License
		// ==========================================

		/**
		 * After a successful purchase, activate the license on the proxy server
		 * (registers this site domain on the license) then refresh UI.
		 */
		activateLicenseAfterPurchase: function (licenseKey, productId) {
			const self = this;

			this.ajax('activate_license', {
				license_key: licenseKey,
				product_id: productId || '',
			}, function (response) {
				if (response.success) {
					self.showToast(self.config.i18n.purchaseComplete || 'Purchase completed! License activated.', 'success');
				} else {
					const msg = (response.data && response.data.message) ? response.data.message : 'License saved but activation failed.';
					self.showToast(msg, 'error');
				}
				self.loadProducts();
				self.loadLicenses();
			});
		},

		/**
		 * Activate all licenses from a bundle purchase sequentially.
		 */
		activateBundleLicenses: function (licenses) {
			const self = this;
			const remaining = licenses.slice();
			let activated = 0;
			const total = licenses.length;

			self.showToast('Bundle purchased! Activating ' + total + ' licenses...', 'info');

			function activateNext () {
				if (!remaining.length) {
					self.showToast('Bundle complete! ' + activated + '/' + total + ' licenses activated.', 'success');
					self.loadProducts();
					self.loadLicenses();
					return;
				}
				const lic = remaining.shift();
				self.ajax('activate_license', {
					license_key: lic.license_key,
					product_id: lic.product_id || '',
				}, function (response) {
					if (response.success) {
						activated++;
					}
					activateNext();
				});
			}

			activateNext();
		},

		unifiedActivate: function () {
			const self = this;
			const key = $('#gvlicense-unified-key').val().trim();
			const productId = $('#gvlicense-unified-product').val();
			const $status = $('#gvlicense-unified-status');
			const $btn = $('#gvlicense-unified-activate-btn');

			this.setLoading($btn, true);
			$status.text(this.config.i18n.processing).removeClass('success error');

			// Empty key = activate-by-domain; proxy auto-detects all other key types
			this.ajax('unified_activate', {
				key: key,
				product_id: productId || '',
			}, function (response) {
				self.setLoading($btn, false);
				if (response.success) {
					self.showToast(response.data.message, 'success');
					$status.text(response.data.message).addClass('success').removeClass('error');
					$('#gvlicense-unified-key').val('');
					self.loadProducts();
					self.loadLicenses();
				} else {
					self.showToast(response.data.message, 'error');
					$status.text(response.data.message).addClass('error').removeClass('success');
				}
			});
		},

		deactivateLicense: function (productId, $btn) {
			const self = this;
			this.setLoading($btn, true);

			this.ajax('deactivate_license', {
				product_id: productId,
			}, function (response) {
				self.setLoading($btn, false);
				if (response.success) {
					self.showToast(response.data.message, 'success');
					self.loadProducts();
					self.loadLicenses();
					$('#gvlicense-manage-modal').hide();
				} else {
					self.showToast(response.data.message, 'error');
				}
			});
		},

		validateAllLicenses: function ($btn) {
			const self = this;
			this.setLoading($btn, true);

			this.ajax('validate_license', {}, function (response) {
				self.setLoading($btn, false);

				if (response.success) {
					const data = response.data;

					if (data.reason === 'server_unavailable') {
						self.showToast(data.message, 'warning');
						return;
					}

					const toastType = data.valid ? 'success' : 'warning';
					self.showToast(data.message, toastType);

					// Update the timestamp label next to the button
					const now = new Date();
					const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
					$('#gvlicense-validated-stamp').html('&#10003; Validated at ' + timeStr).show();

					// Refresh the license table and product grid with fresh data
					if (data.all_licenses) {
						self.renderLicenses(data.all_licenses);
					}
					self.loadProducts();
				} else {
					self.showToast(response.data.message || 'Validation failed', 'error');
				}
			});
		},

		loadLicenses: function () {
			const self = this;
			this.ajax('get_licenses', {}, function (response) {
				if (response.success && response.data) {
					self.renderLicenses(response.data);
				}
			});
		},

		renderLicenses: function (licenses) {
			setTimeout(() => {
				const $section = $('#gvlicense-licenses-section');
				const $tbody = $('#gvlicense-licenses-table tbody');

				if (!$section.length) return;

				const keys = Object.keys(licenses);
				if (!keys.length) {
					$section.hide();
					return;
				}

				$section.show();
				$tbody.empty();

				for (let i = 0; i < keys.length; i++) {
					const pid = keys[i];
					const lic = licenses[pid];
					const maskedKey = lic.license_key
						? lic.license_key.substring(0, 8) + '••••••••' + lic.license_key.slice(-4)
						: '—';
					let statusClass = 'gvlicense-status-unknown';
					if (lic.status === 'active') statusClass = 'gvlicense-status-active';
					else if (lic.status === 'trial') statusClass = 'gvlicense-status-trial';
					else if (lic.status === 'expired' || lic.status === 'cancelled') statusClass = 'gvlicense-status-expired';

					let actions = '';

					// Install & Activate / Activate button (same as product grid)
					const product = this.findProduct(pid);
					if (product && (lic.status === 'active' || lic.status === 'trial')) {
						if (product.addon_status === 'not_installed') {
							actions += '<button type="button" class="button button-small button-primary gvlicense-install-btn" data-product-id="' + pid + '">' + (this.config.i18n.install || 'Install & Activate') + '</button> ';
						} else if (product.addon_status === 'installed') {
							actions += '<button type="button" class="button button-small button-primary gvlicense-activate-addon-btn" data-product-id="' + pid + '" data-plugin-slug="' + (product.plugin_slug || '') + '">' + (this.config.i18n.activate || 'Activate') + '</button> ';
						}
					}

					// Manage button (same as product grid — opens full info popup with timeline)
					if (product) {
						actions += '<button type="button" class="button button-small gvlicense-manage-btn" data-product-id="' + pid + '">Manage</button> ';
					}

					actions += '<button type="button" class="button button-small gvlicense-deactivate-btn" data-product-id="' + pid + '">Deactivate</button>';
					if (lic.subscription_id) {
						actions += ' <button type="button" class="button button-small gvlicense-manage-paddle-btn" data-subscription-id="' + lic.subscription_id + '"><span class="dashicons dashicons-external" style="vertical-align:middle;font-size:14px;width:14px;height:14px;margin-right:2px;"></span>Manage on Paddle</button>';
					}

					$tbody.append(
						'<tr>' +
						'<td><strong>' + (lic.product_name || pid) + '</strong></td>' +
						'<td><code>' + maskedKey + '</code></td>' +
						'<td><span class="gvlicense-status-badge ' + statusClass + '">' + (lic.status || 'unknown') + '</span></td>' +
						'<td>' + (lic.expires_at || 'Lifetime') + '</td>' +
						'<td>' + actions + '</td>' +
						'</tr>'
					);
				}
			}, 500);
		},

		// ==========================================
		// Trial
		// ==========================================
		startTrial: function (productId, $btn) {
			const self = this;
			this.setLoading($btn, true);

			this.ajax('start_trial', {
				product_id: productId,
			}, function (response) {
				self.setLoading($btn, false);
				if (response.success) {
					self.showToast(response.data.message, 'success');
					self.loadProducts();
					self.loadLicenses();
				} else {
					self.showToast(response.data.message, 'error');
				}
			});
		},

		// ==========================================
		// Addons
		// ==========================================
		installAndActivateAddon: function (productId, $btn) {
			const self = this;
			this.setLoading($btn, true);
			$btn.text(this.config.i18n.processing);

			this.ajax('install_activate_addon', {
				product_id: productId,
			}, function (response) {
				self.setLoading($btn, false);
				if (response.success) {
					self.showToast(response.data.message, 'success');
					// Reload products first (updates addon_status), then licenses,
					// so renderLicenses sees the updated addon_status via findProduct()
					self.ajax('get_products', {}, function (prodResponse) {
						if (prodResponse.success && prodResponse.data) {
							const data = prodResponse.data;
							self.products = data.products || data;
							self.checkoutMode = data.checkout_mode || 'both';
							self.renderProducts(self.products);
						}
						self.loadLicenses();
					});
				} else {
					self.showToast(response.data.message, 'error');
					$btn.text(self.config.i18n.install);
				}
			});
		},

		// ==========================================
		// Manage Modal
		// ==========================================
		openManageModal: function (productId) {
			const self = this;
			const product = self.findProduct(productId);
			if (!product) return;

			const $modal = $('#gvlicense-manage-modal');
			const $title = $('#gvlicense-modal-title');
			const $body = $('#gvlicense-modal-body');

			$title.text(product.name);

			// Status badge class
			let statusClass = 'gvlicense-status-unknown';
			if (product.is_licensed) statusClass = 'gvlicense-status-active';
			else if (product.is_trial) statusClass = 'gvlicense-status-trial';
			else if (product.license_status === 'Cancelled' || product.license_status === 'cancelled' || product.license_status.toLowerCase() === 'expired') statusClass = 'gvlicense-status-expired';

			// Addon status label
			const addonLabels = {
				'active': 'Installed & Active',
				'installed': 'Installed (Inactive)',
				'not_installed': 'Not Installed',
			};
			const addonLabel = addonLabels[product.addon_status] || product.addon_status;
			const addonClass = product.addon_status === 'active' ? 'gvlicense-modal-addon-active' :
				product.addon_status === 'installed' ? 'gvlicense-modal-addon-installed' : 'gvlicense-modal-addon-none';

			// Build modal content
			let html = '<div class="gvlicense-manage-info">';

			// License section
			html += '<div class="gvlicense-modal-section">';
			html += '<h4>License Information</h4>';
			html += '<table class="gvlicense-modal-table">';
			html += '<tr><th>Status</th><td><span class="gvlicense-status-badge ' + statusClass + '">' + product.license_status + '</span></td></tr>';

			if (product.license_key) {
				const masked = product.license_key.substring(0, 8) + '••••••••' + product.license_key.slice(-4);
				html += '<tr><th>License Key</th><td><code>' + masked + '</code></td></tr>';
			}
			if (product.customer_id) {
				html += '<tr><th>Customer</th><td>' + product.customer_id + '</td></tr>';
			}
			if (product.plan_name) {
				html += '<tr><th>Plan</th><td>' + product.plan_name + '</td></tr>';
			}
			if (product.active_price_formatted) {
				html += '<tr><th>Price</th><td>' + product.active_price_formatted + ' <span class="gvlicense-modal-interval">' + (product.active_price_interval || '') + '</span></td></tr>';
			}
			html += '</table></div>';

			// Subscription / Billing section
			html += '<div class="gvlicense-modal-section">';
			html += '<h4>Billing &amp; Subscription</h4>';
			html += '<table class="gvlicense-modal-table">';
			if (product.subscription_id) {
				html += '<tr><th>Subscription ID</th><td><code>' + product.subscription_id + '</code></td></tr>';
			}
			if (product.expires_at) {
				const expiresDate = new Date(product.expires_at);
				const now = new Date();
				const daysLeft = Math.ceil((expiresDate - now) / (1000 * 60 * 60 * 24));
				const expiresFormatted = expiresDate.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
				const localStatus = (product.license_status || '').toLowerCase();
				const daysLabel = daysLeft > 0 ? ' (' + daysLeft + ' days remaining)' : daysLeft === 0 ? ' <span class="gvlicense-modal-expired">(Today Should expire)</span>' : ' <span class="gvlicense-modal-expired">(expired)</span>';
				if (localStatus === 'cancelled') {
					html += '<tr><th>License Expires</th><td>' + expiresFormatted + daysLabel + '<br><small style="color:#dc3232;">Subscription cancelled — license will not auto-renew</small></td></tr>';
				} else {
					// Show license expiration
					html += '<tr><th>License Expires</th><td>' + expiresFormatted + daysLabel + '</td></tr>';
					// Show next charge info based on billing interval
					const interval = (product.active_price_interval || '').toLowerCase().replace(/^[\s\/]+/, '').trim();
					if (interval && product.subscription_id) {
						let periodDays = 365;
						if (interval.indexOf('month') !== -1) periodDays = 30;
						else if (interval.indexOf('week') !== -1) periodDays = 7;
						const nextCharge = new Date(now.getTime() + periodDays * 24 * 60 * 60 * 1000);
						// If license has been extended beyond one billing cycle, note that
						if (daysLeft > periodDays + 30) {
							html += '<tr><th>Next Charge</th><td>~' + nextCharge.toLocaleDateString(undefined, {
								                                        year: 'numeric',
								                                        month: 'long',
							                                        }) + ' <span class="gvlicense-modal-interval">(' + product.active_price_interval + ')</span>'
							        + '<br><small style="color:#388e3c;">License has extra time from previous purchases</small></td></tr>';
						} else {
							html += '<tr><th>Next Charge</th><td>~' + nextCharge.toLocaleDateString(undefined, {
								year: 'numeric',
								month: 'long',
							}) + ' <span class="gvlicense-modal-interval">(' + product.active_price_interval + ')</span></td></tr>';
						}
					}
				}
			} else {
				html += '<tr><th>License</th><td>Lifetime (no expiration)</td></tr>';
			}
			if (product.activated_at) {
				const activatedDate = new Date(product.activated_at);
				html += '<tr><th>Activated On</th><td>' + activatedDate.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) + '</td></tr>';
			}
			html += '</table></div>';

			// Site Activation section
			html += '<div class="gvlicense-modal-section">';
			html += '<h4>Site Activation</h4>';
			html += '<table class="gvlicense-modal-table">';
			let sites = product.activated_sites || [];
			if (typeof sites === 'string') {
				try { sites = JSON.parse(sites); } catch (e) { sites = []; }
			}
			const maxSites = product.max_sites || 1;
			html += '<tr><th>Sites Used</th><td>' + (Array.isArray(sites) ? sites.length : 0) + ' / ' + maxSites + '</td></tr>';
			if (Array.isArray(sites) && sites.length) {
				let sitesList = '';
				for (let s = 0; s < sites.length; s++) {
					sitesList += '<span class="gvlicense-modal-site">' + sites[s] + '</span>';
				}
				html += '<tr><th>Activated Sites</th><td>' + sitesList + '</td></tr>';
			}
			html += '</table></div>';

			// Addon section
			html += '<div class="gvlicense-modal-section">';
			html += '<h4>Addon</h4>';
			html += '<table class="gvlicense-modal-table">';
			html += '<tr><th>Plugin</th><td>' + (product.plugin_slug || '—') + '</td></tr>';
			html += '<tr><th>Addon Status</th><td><span class="' + addonClass + '">' + addonLabel + '</span></td></tr>';
			html += '</table></div>';

			// Subscription Timeline section (loaded async)
			html += '<div class="gvlicense-modal-section">';
			html += '<h4>Timeline</h4>';
			html += '<div id="gvlicense-timeline-container">';
			html += '<div class="gvlicense-loading"><span class="spinner is-active" style="float:none;"></span> Loading timeline...</div>';
			html += '</div></div>';

			// Last validated
			if (product.last_validated) {
				const validatedDate = new Date(product.last_validated * 1000);
				html += '<div class="gvlicense-modal-footer-info">Last validated: ' + validatedDate.toLocaleString() + '</div>';
			}

			html += '</div>';

			// Actions
			html += '<div class="gvlicense-manage-actions">';
			if (product.addon_status === 'not_installed' && (product.is_licensed || product.is_trial)) {
				html += '<button type="button" class="button button-primary gvlicense-install-btn" data-product-id="' + productId + '">' + this.config.i18n.install + '</button>';
			}
			html += '<button type="button" class="button gvlicense-deactivate-btn" data-product-id="' + productId + '">' + this.config.i18n.deactivateLicense + '</button>';

			if (product.subscription_id) {
				const licStatus = (product.license_status || '').toLowerCase();
				if (licStatus !== 'cancelled' && licStatus !== 'expired') {
					html += '<button type="button" class="button gvlicense-cancel-sub-btn" data-subscription-id="' + product.subscription_id + '" data-product-id="' + productId + '">Cancel Subscription</button>';
				}
				html += '<button type="button" class="button gvlicense-manage-paddle-btn" data-subscription-id="' + product.subscription_id + '"><span class="dashicons dashicons-external" style="margin-right:3px;"></span>Manage on Paddle</button>';
			}
			html += '</div>';

			$body.html(html);
			$modal.show();

			// Load timeline asynchronously
			if (product.license_key) {
				this.loadTimeline(product.license_key);
			} else {
				$('#gvlicense-timeline-container').html('<p class="gvlicense-timeline-empty">No timeline data available.</p>');
			}
		},

		// ==========================================
		// Subscription Timeline
		// ==========================================
		loadTimeline: function (licenseKey) {
			this.ajax('get_timeline', {
				license_key: licenseKey,
			}, function (response) {
				const $container = $('#gvlicense-timeline-container');
				if (!$container.length) return;

				const timelineData = response.data || {};
				const events = timelineData.events || timelineData;
				const currentLicenseKey = timelineData.current_license_key || '';
				if (response.success && events && events.length) {

					// Check if subscription is canceled from timeline data and update UI accordingly
					// Only consider events from the CURRENT license key (not old/deleted ones)
					let hasCancelled = false;
					let hasCancelScheduled = false;
					for (let ci = 0; ci < events.length; ci++) {
						const evtKey = events[ci].license_key || '';
						// Skip events from old license keys — their cancel status doesn't apply to current
						if (currentLicenseKey && evtKey && evtKey !== currentLicenseKey) continue;
						if (events[ci].action_type === 'cancelled') hasCancelled = true;
						if (events[ci].action_type === 'cancel_scheduled') hasCancelScheduled = true;
					}

					const $actions = $('#gvlicense-manage-modal .gvlicense-manage-actions');
					const pid = $actions.find('[data-product-id]').first().data('product-id') || '';

					if (hasCancelled) {
						// Truly canceled (from Paddle directly) — update billing label
						$('#gvlicense-manage-modal .gvlicense-modal-table th').each(function () {
							if ($(this).text() === 'Next Renewal') {
								$(this).text('Active Until');
								const $td = $(this).next('td');
								$td.append('<br><small style="color:#dc3232;">Subscription cancelled — license will not auto-renew</small>');
							}
						});
						// Remove cancel/resume buttons, show Resubscribe
						$actions.find('.gvlicense-cancel-sub-btn, .gvlicense-resume-sub-btn').remove();
						if ($actions.length && !$actions.find('.gvlicense-resubscribe-btn').length && pid) {
							$actions.find('.gvlicense-deactivate-btn').after(' <button type="button" class="button button-primary gvlicense-resubscribe-btn" data-product-id="' + pid + '">Resubscribe</button>');
						}
					} else if (hasCancelScheduled) {
						// Scheduled cancellation — update billing label and swap Cancel to Resume
						$('#gvlicense-manage-modal .gvlicense-modal-table th').each(function () {
							if ($(this).text() === 'Next Renewal') {
								$(this).text('Active Until');
								const $td = $(this).next('td');
								$td.append('<br><small style="color:#d68000;">Cancellation scheduled — license will not auto-renew after this period</small>');
							}
						});
						// Swap Cancel button with Resume
						const $cancelBtn = $actions.find('.gvlicense-cancel-sub-btn');
						if ($cancelBtn.length) {
							const subId = $cancelBtn.data('subscription-id');
							$cancelBtn.replaceWith('<button type="button" class="button button-primary gvlicense-resume-sub-btn" data-subscription-id="' + subId + '" data-product-id="' + pid + '">Resume Subscription</button>');
						}
					}
					let html = '<div class="gvlicense-timeline">';

					const actionLabels = {
						'created': 'Subscription Created',
						'renewed': 'Subscription Renewed',
						'extended': 'License Extended',
						'cancelled': 'Subscription Cancelled',
						'cancel_scheduled': 'Cancellation Scheduled',
						'replaced': 'Subscription Replaced',
						'resumed': 'Subscription Resumed',
						'upgraded': 'Plan Upgraded',
						'downgraded': 'Plan Downgraded',
						'payment': 'Payment Processed',
					};

					const actionIcons = {
						'created': 'dashicons-plus-alt',
						'renewed': 'dashicons-update',
						'extended': 'dashicons-calendar',
						'cancelled': 'dashicons-dismiss',
						'cancel_scheduled': 'dashicons-clock',
						'replaced': 'dashicons-randomize',
						'resumed': 'dashicons-controls-play',
						'upgraded': 'dashicons-arrow-up-alt',
						'downgraded': 'dashicons-arrow-down-alt',
						'payment': 'dashicons-money-alt',
					};

					const actionColors = {
						'created': 'gvlicense-tl-green',
						'renewed': 'gvlicense-tl-blue',
						'extended': 'gvlicense-tl-blue',
						'cancelled': 'gvlicense-tl-red',
						'cancel_scheduled': 'gvlicense-tl-orange',
						'replaced': 'gvlicense-tl-orange',
						'resumed': 'gvlicense-tl-green',
						'upgraded': 'gvlicense-tl-green',
						'downgraded': 'gvlicense-tl-orange',
						'payment': 'gvlicense-tl-blue',
					};

					let lastLicenseKey = '';
					for (let i = 0; i < events.length; i++) {
						const evt = events[i];
						const evtLicenseKey = evt.license_key || '';
						const actionType = evt.action_type || 'created';

						// Draw a separator line when the license key changes
						if (currentLicenseKey && evtLicenseKey && evtLicenseKey !== lastLicenseKey && lastLicenseKey !== '') {
							const isCurrent = (evtLicenseKey === currentLicenseKey);
							html += '<div class="gvlicense-tl-separator">';
							html += '<span class="gvlicense-tl-separator-label">' + (isCurrent ? 'Current License' : 'Previous License') + '</span>';
							html += '</div>';
						}
						if (evtLicenseKey) lastLicenseKey = evtLicenseKey;

						const label = actionLabels[actionType] || actionType;
						const icon = actionIcons[actionType] || 'dashicons-marker';
						const colorClass = actionColors[actionType] || 'gvlicense-tl-gray';
						const eventDate = evt.date || '';
						let dateFormatted = '';
						if (eventDate) {
							const d = new Date(eventDate.replace(' ', 'T') + 'Z');
							dateFormatted = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
						}

						// Dim old license events
						const isOldLicense = currentLicenseKey && evtLicenseKey && evtLicenseKey !== currentLicenseKey;
						const itemClass = 'gvlicense-tl-item ' + colorClass + (isOldLicense ? ' gvlicense-tl-old' : '');

						html += '<div class="' + itemClass + '">';
						html += '<div class="gvlicense-tl-dot"><span class="dashicons ' + icon + '"></span></div>';
						html += '<div class="gvlicense-tl-content">';
						html += '<div class="gvlicense-tl-label">' + label + '</div>';
						if (dateFormatted) {
							html += '<div class="gvlicense-tl-date">' + dateFormatted + '</div>';
						}
						if (evt.expires_at) {
							const expD = new Date(evt.expires_at.replace(' ', 'T') + 'Z');
							html += '<div class="gvlicense-tl-detail">Valid until: ' + expD.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) + '</div>';
						}
						if (evt.site_domain) {
							html += '<div class="gvlicense-tl-detail">From: <code>' + evt.site_domain + '</code></div>';
						}
						if (evt.note) {
							html += '<div class="gvlicense-tl-detail"><em>' + evt.note + '</em></div>';
						}
						if (evt.source === 'paddle' && !evt.site_domain && (actionType === 'cancelled' || actionType === 'paused')) {
							html += '<div class="gvlicense-tl-detail"><em>Action performed outside your site (Paddle portal/email)</em></div>';
						}
						if (evt.old_subscription_id) {
							html += '<div class="gvlicense-tl-detail">Replaced: <code>' + evt.old_subscription_id + '</code></div>';
						}
						if (evt.transaction_id) {
							html += '<div class="gvlicense-tl-detail">Transaction: <code>' + evt.transaction_id + '</code></div>';
						}
						html += '</div></div>';
					}

					html += '</div>';
					$container.html(html);
				} else {
					$container.html('<p class="gvlicense-timeline-empty">No timeline of events was found.</p>');
				}
			});
		},

		// ==========================================
		// Subscription Management
		// ==========================================
		manageSubscription: function (action, subscriptionId, $btn) {
			const self = this;
			const productId = $btn.data('product-id') || '';
			const ajaxAction = action + '_subscription';
			this.setLoading($btn, true);

			this.ajax(ajaxAction, {
				subscription_id: subscriptionId,
			}, function (response) {
				self.setLoading($btn, false);
				if (response.success) {
					self.showToast(response.data.message, 'success');
					// Reload products and re-open the modal to show updated state
					self.ajax('get_products', {}, function (prodResponse) {
						if (prodResponse.success && prodResponse.data) {
							const data = prodResponse.data;
							const products = data.products || data;
							self.products = products;
							self.checkoutMode = data.checkout_mode || 'both';
							self.renderProducts(products);
							self.loadLicenses();
							// Re-open modal with refreshed data
							if (productId) {
								self.openManageModal(productId);
							}
						}
					});
				} else {
					self.showToast(response.data.message, 'error');
				}
			});
		},

		// ==========================================
		// Helpers
		// ==========================================
		ajax: function (action, data, callback) {
			data.action = this.ajaxPrefix + action;
			data.nonce = this.config.nonce;

			$.post(this.config.ajaxUrl, data, function (response) {
				if (typeof callback === 'function') callback(response);
			}).fail(function () {
				if (typeof callback === 'function') {
					callback({ success: false, data: { message: 'Request failed' } });
				}
			});
		},

		findProduct: function (productId) {
			for (let i = 0; i < this.products.length; i++) {
				if (this.products[i].id === productId) return this.products[i];
			}
			return null;
		},

		/**
		 * Redirect the pre-opened checkout window to Paddle.
		 */
		proceedToCheckout: function (transactionId, checkoutWindow) {
			const self = this;
			checkoutWindow.location.href = self.config.checkout_url + '?_ptxn=' + encodeURIComponent(transactionId);
			const messageHandler = function(event) {
				if (event.data && event.data.type === 'paddle_checkout_complete') {
					if( self.config.proxy_server_url === event.origin ){
						self.removeCheckoutOverlay();
						self.startPolling();
						self.bindVisibilityPolling();
					}
				}else if( event.data && event.data.type === 'paddle_checkout_closed' ){
					if( self.config.proxy_server_url === event.origin ) {
						self.removeCheckoutOverlay();
						window.removeEventListener('message', messageHandler);
					}
				}
			};
			window.addEventListener('message', messageHandler);
			setTimeout(function () {
				self.updateCheckoutOverlay('Checkout opened in a new tab. Please complete your payment there.');
			}, 1000);
		},

		// ==========================================
		// Subscription Overlap Prompt (pre-purchase)
		// ==========================================

		setLoading: function ($btn, loading) {
			if (!$btn || !$btn.length) return;
			if (loading) {
				$btn.addClass('gvlicense-btn-loading').prop('disabled', true);
			} else {
				$btn.removeClass('gvlicense-btn-loading').prop('disabled', false);
			}
		},

		showToast: function (message, type) {
			type = type || 'info';
			const $toast = $('<div class="gvlicense-toast gvlicense-toast-' + type + '">' + message + '</div>');
			$('body').append($toast);
			setTimeout(function () {
				$toast.fadeOut(300, function () { $(this).remove(); });
			}, 4000);
		},
	};

	$(document).ready(function () {
		// Only init on gVectors License pages
		if ($('.gvlicense-store').length || $('.gvlicense-account').length) {
			gVectorsLicense.init();
		}
	});

})(jQuery);
