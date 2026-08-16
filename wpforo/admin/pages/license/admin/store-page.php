<?php
// Exit if accessed directly
use gVectors\License\LicenseModule;

if( ! defined( 'ABSPATH' ) ) exit;
if( ! current_user_can( 'administrator' ) ) exit;
?>

<div id="gvlicense-admin-wrap" class="wrap gvlicense-store" data-slug="<?php echo esc_attr( $slug ); ?>">
	<div id="gvlicense-store-logo">
        <div class="logo-container">
            <span class="logo-text"><span class="logo-g">g</span>Vectors</span>
            <span class="logo-store">Store</span>
        </div>
	</div>
	<h1 id="gvlicense-store-title">
		<?php esc_html_e( 'Addons', 'gvectors' ); ?>
		<button type="button" id="gvlicense-refresh" class="button" style="margin-left:15px;">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Refresh', 'gvectors' ); ?>
		</button>
        <?php if( ! empty( LicenseModule::getActionsService( $slug )->addonsService->licenseService->get_all() ) ): ?>
        <button type="button" class="button gvlicense-validate-all-btn">
            <span class="dashicons dashicons-shield"></span>
            <?php esc_html_e( 'Validate All Licenses', 'gvectors' ); ?>
        </button>
        <?php endif; ?>
        <span class="gvlicense-validated-label" id="gvlicense-validated-stamp" style="display:none;"></span>
	</h1>
	<br style="clear:both">

	<!-- Unified License Activation Bar -->
	<div class="gvlicense-license-bar">
		<h3><?php esc_html_e( 'Activate License', 'gvectors' ); ?></h3>
		<p class="description" style="margin:0 0 10px;color:#666;font-size:13px;">
			<?php esc_html_e( 'Enter your license key or transaction ID, or leave empty to automatically find and activate all licenses registered to this domain.', 'gvectors' ); ?>
		</p>
		<div class="gvlicense-license-form">
			<input type="text" id="gvlicense-unified-key" placeholder="<?php esc_attr_e( 'License key, transaction ID, or leave empty for auto-detect...', 'gvectors' ); ?>" class="regular-text">
			<select id="gvlicense-unified-product" class="gvlicense-product-select">
				<option value=""><?php esc_html_e( 'All addons', 'gvectors' ); ?></option>
			</select>
			<button type="button" id="gvlicense-unified-activate-btn" class="button button-primary">
				<?php esc_html_e( 'Activate', 'gvectors' ); ?>
			</button>
			<span id="gvlicense-unified-status" class="gvlicense-status"></span>
		</div>
	</div>

	<!-- Active Licenses Section -->
	<div id="gvlicense-licenses-section" class="gvlicense-licenses-section" style="display:none;">
		<h2 style="display:inline-block;margin-right:10px;"><?php esc_html_e( 'Active Licenses', 'gvectors' ); ?></h2>
		<table class="widefat striped" id="gvlicense-licenses-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Product', 'gvectors' ); ?></th>
				<th><?php esc_html_e( 'License Key', 'gvectors' ); ?></th>
				<th><?php esc_html_e( 'Status', 'gvectors' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'gvectors' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'gvectors' ); ?></th>
			</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

    <!-- Product Grid (loaded via AJAX) -->
    <div id="gvlicense-products-grid" class="gvlicense-products-grid">
        <div class="gvlicense-loading">
            <span class="spinner is-active" style="float:none;"></span>
            <?php esc_html_e( 'Loading products...', 'gvectors' ); ?>
        </div>
    </div>

	<div style="clear:both;"></div>
</div>

<!-- Product Card Template (used by JS) -->
<script type="text/html" id="tmpl-gvlicense-product-card">
	<#
		var _mainPriceIdx = 0;
		if (data.prices && data.prices.length > 1) {
			for (var _mi = 0; _mi < data.prices.length; _mi++) {
				if (data.prices[_mi].custom_data && data.prices[_mi].custom_data.Main) {
					_mainPriceIdx = _mi;
					break;
				}
			}
		}
		var _defaultPriceId = (data.prices && data.prices.length) ? data.prices[_mainPriceIdx].id : '';
	#>
	<div class="gvlicense-product-card<# if (data.is_bundle) { #> gvlicense-bundle-card<# } #>" data-product-id="{{data.id}}" data-plugin-slug="{{data.plugin_slug}}" data-is-bundle="{{data.is_bundle ? '1' : '0'}}">
		<div class="gvlicense-product-image">
			<# if (data.image_url) { #>
			<img src="{{data.image_url}}" alt="{{data.name}}">
			<# } else { #>
			<div class="gvlicense-product-placeholder">
				<# if (data.is_bundle) { #>
				<span class="dashicons dashicons-screenoptions"></span>
				<# } else { #>
				<span class="dashicons dashicons-admin-plugins"></span>
				<# } #>
			</div>
			<# } #>
			<# if (data.is_bundle) { #>
			<span class="gvlicense-badge gvlicense-badge-bundle"><?php esc_html_e( 'Bundle', 'gvectors' ); ?></span>
			<# } #>
			<# if (data.is_licensed) { #>
			<span class="gvlicense-badge gvlicense-badge-active"><?php esc_html_e( 'Licensed', 'gvectors' ); ?></span>
			<# } else if (data.is_trial) { #>
			<span class="gvlicense-badge gvlicense-badge-trial"><?php esc_html_e( 'Trial', 'gvectors' ); ?></span>
			<# } #>
		</div>
		<div class="gvlicense-product-info">
			<h3 class="gvlicense-product-name">{{data.name}}</h3>
			<p class="gvlicense-product-desc">{{data.description}}</p>
			<# if (data.is_bundle && data.bundle_slugs && data.bundle_slugs.length) { #>
			<div class="gvlicense-bundle-includes">
				<strong><?php esc_html_e( 'Includes:', 'gvectors' ); ?></strong>
				<ul>
					<# for (var b = 0; b < data.bundle_slugs.length; b++) { #>
					<li><span class="dashicons dashicons-yes"></span> {{data.bundle_addon_names ? data.bundle_addon_names[b] : data.bundle_slugs[b]}}</li>
					<# } #>
				</ul>
			</div>
			<# } #>
 		<div class="gvlicense-product-price">
 			<# if (data.is_licensed || data.is_trial) { #>
 				<# if (data.active_plan_name) { #>
 				<span class="gvlicense-active-plan">{{data.active_plan_name}}</span>
 				<# } #>
 				<# if (data.active_price_formatted) { #>
 				<span class="gvlicense-price-amount">{{data.active_price_formatted}}</span>
 				<span class="gvlicense-price-interval">{{data.active_price_interval}}</span>
 				<# } #>
 			<# } else if (data.prices && data.prices.length > 1) { #>
	 				<div class="gvlicense-price-select-wrap">
					<select class="gvlicense-price-select" data-product-id="{{data.id}}">
						<# for (var p = 0; p < data.prices.length; p++) {
							var optLabel = data.prices[p].formatted_price + ' ' + data.prices[p].interval_label;
							if (data.prices[p].name) optLabel += ' \u2014 ' + data.prices[p].name;
							if (data.prices[p].description) optLabel += ' (' + data.prices[p].description + ')';
						#>
						<option value="{{data.prices[p].id}}"
							data-price="{{data.prices[p].formatted_price}}"
							data-interval="{{data.prices[p].interval_label}}"
							data-name="{{data.prices[p].name}}"
							data-description="{{data.prices[p].description}}"
							<# if (p === _mainPriceIdx) { #>selected<# } #>
						>{{optLabel}}</option>
						<# } #>
					</select>
					<div class="gvlicense-price-detail">
						<span class="gvlicense-price-amount">{{data.prices[_mainPriceIdx].formatted_price}}</span>
						<span class="gvlicense-price-interval">{{data.prices[_mainPriceIdx].interval_label}}</span>
						<# if (data.prices[_mainPriceIdx].name) { #>
						<span class="gvlicense-price-name">{{data.prices[_mainPriceIdx].name}}</span>
						<# } #>
						<# if (data.prices[_mainPriceIdx].description) { #>
						<span class="gvlicense-price-desc">{{data.prices[_mainPriceIdx].description}}</span>
						<# } #>
					</div>
				</div>
 			<# } else if (data.prices && data.prices.length === 1) { #>
 				<span class="gvlicense-price-amount">{{data.prices[0].formatted_price}}</span>
 				<span class="gvlicense-price-interval">{{data.prices[0].interval_label}}</span>
 			<# } #>
 			</div>
 			<div class="gvlicense-product-actions">
 				<# if (data.is_bundle) { #>
 					<# if (data.bundle_all_licensed) { #>
 					<span class="gvlicense-addon-active"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'All Addons Licensed', 'gvectors' ); ?></span>
 					<# } else { #>
 						<# if (data.prices && data.prices.length) { #>
 						<button type="button" class="button button-primary gvlicense-buy-btn" data-price-id="{{_defaultPriceId}}" data-product-id="{{data.id}}"><?php esc_html_e( 'Buy Bundle', 'gvectors' ); ?></button>
 						<# } #>
 					<# } #>
 				<# } else if (data.is_licensed || data.is_trial) { #>
 					<# if (data.addon_status === 'active') { #>
 					<span class="gvlicense-addon-active"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active', 'gvectors' ); ?></span>
 					<# } else if (data.addon_status === 'installed') { #>
 					<button type="button" class="button button-primary gvlicense-activate-addon-btn" data-product-id="{{data.id}}" data-plugin-slug="{{data.plugin_slug}}"><?php esc_html_e( 'Activate', 'gvectors' ); ?></button>
 					<# } else { #>
 					<button type="button" class="button button-primary gvlicense-install-btn" data-product-id="{{data.id}}"><?php esc_html_e( 'Install & Activate', 'gvectors' ); ?></button>
 					<# } #>
 					<button type="button" class="button gvlicense-manage-btn" data-product-id="{{data.id}}"><?php esc_html_e( 'Manage', 'gvectors' ); ?></button>
 				<# } else { #>
 					<# if (data.prices && data.prices.length) { #>
 					<button type="button" class="button button-primary gvlicense-buy-btn" data-price-id="{{_defaultPriceId}}" data-product-id="{{data.id}}"><?php esc_html_e( 'Buy Now', 'gvectors' ); ?></button>
 					<# } #>
 					<# if (data.has_trial) { #>
 					<button type="button" class="button gvlicense-trial-btn" data-product-id="{{data.id}}"><?php esc_html_e( 'Start Free Trial', 'gvectors' ); ?></button>
 					<# } #>
 				<# } #>
			</div>
		</div>
	</div>
</script>

<!-- Manage Modal Template -->
<div id="gvlicense-manage-modal" class="gvlicense-modal" style="display:none;">
	<div class="gvlicense-modal-overlay"></div>
	<div class="gvlicense-modal-content">
		<button type="button" class="gvlicense-modal-close">&times;</button>
		<h2 id="gvlicense-modal-title"></h2>
		<div id="gvlicense-modal-body"></div>
	</div>
</div>
