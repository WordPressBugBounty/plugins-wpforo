<?php

namespace wpforo\classes;

use WP_Error;

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

class API {
	public $locale     = 'en_US';
	public $locale_iso = 'en';
	public $fb_local   = [
		'af_ZA',
		'ar_AR',
		'az_AZ',
		'be_BY',
		'bg_BG',
		'bn_IN',
		'bs_BA',
		'ca_ES',
		'cs_CZ',
		'cy_GB',
		'da_DK',
		'de_DE',
		'el_GR',
		'en_US',
		'en_GB',
		'eo_EO',
		'es_ES',
		'es_LA',
		'et_EE',
		'eu_ES',
		'fa_IR',
		'fb_LT',
		'fi_FI',
		'fo_FO',
		'fr_FR',
		'fr_CA',
		'fy_NL',
		'ga_IE',
		'gl_ES',
		'he_IL',
		'hi_IN',
		'hr_HR',
		'hu_HU',
		'hy_AM',
		'id_ID',
		'is_IS',
		'it_IT',
		'ja_JP',
		'ka_GE',
		'km_KH',
		'ko_KR',
		'ku_TR',
		'la_VA',
		'lt_LT',
		'lv_LV',
		'mk_MK',
		'ml_IN',
		'ms_MY',
		'nb_NO',
		'ne_NP',
		'nl_NL',
		'nn_NO',
		'pa_IN',
		'pl_PL',
		'ps_AF',
		'pt_PT',
		'pt_BR',
		'ro_RO',
		'ru_RU',
		'sk_SK',
		'sl_SI',
		'sq_AL',
		'sr_RS',
		'sv_SE',
		'sw_KE',
		'ta_IN',
		'te_IN',
		'th_TH',
		'tl_PH',
		'tr_TR',
		'uk_UA',
		'vi_VN',
		'zh_CN',
		'zh_HK',
		'zh_TW',
	];
	public $tw_local   = [
		'en',
		'ar',
		'bn',
		'cs',
		'da',
		'de',
		'el',
		'es',
		'fa',
		'fi',
		'fil',
		'fr',
		'he',
		'hi',
		'hu',
		'id',
		'it',
		'ja',
		'ko',
		'msa',
		'nl',
		'no',
		'pl',
		'pt',
		'ro',
		'ru',
		'sv',
		'th',
		'tr',
		'uk',
		'ur',
		'vi',
		'zh-cn',
		'zh-tw',
	];
	public $ok_local   = [ "ru", "en", "uk", "hy", "mo", "ro", "kk", "uz", "az", "tr" ];
	
	public function __construct() {
		add_action( 'wpforo_after_init', function() {
			if( ! wpforo_is_admin() ) {
				$this->init_wp_recaptcha();
				$this->hooks();
			}
		} );
	}
	
	private function hooks() {
		$template = WPF()->current_object['template'];
		
		###############################################################################
		############### X.com & Social Share API ####################################
		###############################################################################

		if( is_wpforo_page() ) {
			if( apply_filters( 'wpforo_api_tw_load_wjs', true ) && wpforo_setting( 'social', 'sb', 'tw' ) ) {
				add_action( 'wpforo_top_hook', [ $this, 'tw_wjs' ], 11 );
			}
			if( apply_filters( 'wpforo_api_vk_load_js', true ) && wpforo_setting( 'social', 'sb', 'vk' ) ) {
				add_action( 'wpforo_top_hook', [ $this, 'vk_js' ], 13 );
			}
		}
		
		###############################################################################
		############### reCAPTCHA API #################################################
		###############################################################################
		
		$site_key   = wpforo_setting( 'recaptcha', 'site_key' );
		$secret_key = wpforo_setting( 'recaptcha', 'secret_key' );
		
		if( ! is_user_logged_in() && $site_key && $secret_key ) {
			
			$wpf_reg_form      = wpforo_setting( 'recaptcha', 'wpf_reg_form' );
			$wpf_login_form    = wpforo_setting( 'recaptcha', 'wpf_login_form' );
			$wpf_lostpass_form = wpforo_setting( 'recaptcha', 'wpf_lostpass_form' );
			
			add_filter( 'script_loader_tag', [ &$this, 'rc_enqueue_async' ], 10, 3 );
			
			//Verification Hooks: Login / Register / Reset Pass
			if( $wpf_login_form ) add_filter( 'wp_authenticate_user', [ $this, 'rc_verify_wp_login' ], 15, 2 );
			if( $wpf_reg_form ) add_filter( 'registration_errors', [ $this, 'rc_verify_wp_register' ], 10, 3 );
			if( $wpf_lostpass_form ) add_action( 'lostpassword_post', [ $this, 'rc_verify_wp_lostpassword' ], 10 );
			
			//Load reCAPTCHA API on wpForo pages: Login / Register / Reset Pass
			if( in_array( $template, [ 'login', 'register', 'lostpassword' ], true ) ) {
				if( $wpf_reg_form || $wpf_login_form || $wpf_lostpass_form ) {
					add_action( 'wp_enqueue_scripts', [ $this, 'rc_enqueue' ] );
				}
			}
			
			//Load reCAPTCHA Widget wpForo forms: Login / Register / Reset Pass
			if( $wpf_login_form && $template === 'login' ) add_action( 'login_form', [ $this, 'rc_widget' ] );
			if( $wpf_reg_form && $template === 'register' ) add_action( 'register_form', [ $this, 'rc_widget' ] );
			if( $wpf_lostpass_form && $template === 'lostpassword' ) {
				add_action( 'lostpassword_form', [ $this, 'rc_widget' ] );
			}
			
			//Load reCAPTCHA API and Widget for Topic and Post Editor
			if( in_array( $template, [ 'forum', 'topic', 'post', 'add-topic' ], true ) ) {
				add_action( 'wp_enqueue_scripts', [ $this, 'rc_enqueue' ] );
				add_action( 'wpforo_verify_form_end', [ $this, 'rc_verify' ] );
				if( wpforo_setting( 'recaptcha', 'topic_editor' ) ) {
					add_action( 'wpforo_topic_form_extra_fields_after', [ $this, 'rc_widget' ] );
				}
				if( wpforo_setting( 'recaptcha', 'post_editor' ) ) {
					add_action( 'wpforo_reply_form_extra_fields_after', [ $this, 'rc_widget' ] );
					add_action( 'wpforo_portable_form_extra_fields_after', [ $this, 'rc_widget' ] );
				}
			}
		}
		
		###############################################################################
	}
	
	private function init_wp_recaptcha() {
		$template   = WPF()->current_object['template'];
		$site_key   = wpforo_setting( 'recaptcha', 'site_key' );
		$secret_key = wpforo_setting( 'recaptcha', 'secret_key' );
		
		if( ! is_user_logged_in() && $site_key && $secret_key ) {
			$reg_form      = wpforo_setting( 'recaptcha', 'reg_form' );
			$login_form    = wpforo_setting( 'recaptcha', 'login_form' );
			$lostpass_form = wpforo_setting( 'recaptcha', 'lostpass_form' );
			
			//Verification Hooks: Login / Register / Reset Pass
			if( $login_form ) add_filter( 'wp_authenticate_user', [ $this, 'rc_verify_wp_login' ], 15, 2 );
			if( $reg_form ) add_filter( 'registration_errors', [ $this, 'rc_verify_wp_register' ], 10, 3 );
			if( $lostpass_form ) add_action( 'lostpassword_post', [ $this, 'rc_verify_wp_lostpassword' ], 10 );
			
			//Load reCAPTCHA API and Widget on wp-login.php
			if( $reg_form || $login_form || $lostpass_form ) {
				add_action( 'login_enqueue_scripts', [ $this, 'rc_enqueue' ] );
				add_action( 'login_enqueue_scripts', [ $this, 'rc_enqueue_css' ] );
				if( $login_form && $template !== 'login' ) add_action( 'login_form', [ $this, 'rc_widget' ] );
				if( $reg_form && $template !== 'register' ) add_action( 'register_form', [ $this, 'rc_widget' ] );
				if( $lostpass_form && $template !== 'lostpassword' ) {
					add_action( 'lostpassword_form', [ $this, 'rc_widget' ] );
				}
			}
		}
	}
	
	public function local( $api ) {
		$wplocal     = get_locale();
		$wplocal_iso = substr( $wplocal, 0, 2 );
		
		if( $api === 'fb' ) {
			if( in_array( $wplocal, $this->fb_local ) ) {
				return $wplocal;
			} else {
				return $this->locale;
			}
		} elseif( $api === 'tw' ) {
			if( in_array( $wplocal_iso, $this->tw_local ) ) {
				return $wplocal_iso;
			} else {
				return $this->locale_iso;
			}
		} elseif( $api === 'vk' ) {
			return $wplocal_iso;
		} elseif( $api === 'ok' ) {
			if( in_array( $wplocal_iso, $this->ok_local ) ) {
				return $wplocal_iso;
			} else {
				return $this->locale_iso;
			}
		}
		
		return $wplocal;
	}
	
	
	public function fb_share_button( $url = '', $type = 'custom', $text = '' ) {
		if( ! wpforo_setting( 'social', 'sb', 'fb' ) ) return;
		$url = $url ?: WPF()->current_url;
		// Facebook deprecated custom parameters (quote, title, etc.)
		// Content is now pulled from Open Graph meta tags on the shared URL
		$share_url = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode( (string) $url );
		if( $type === 'custom' ) { ?>
            <span class="wpforo-share-button wpf-fb" data-wpfurl="<?php echo esc_attr( $url ) ?>"
                  title="<?php wpforo_phrase( 'Share to Facebook' ); ?>">
                <?php if( wpforo_setting( 'social', 'sb_icon' ) === 'figure' ): ?>
                    <i class="fab fa-facebook-f" aria-hidden="true"></i>
                <?php elseif( wpforo_setting( 'social', 'sb_icon' ) === 'square' ): ?>
                    <i class="fab fa-facebook-square" aria-hidden="true"></i>
                <?php else: ?>
                    <i class="fab fa-facebook" aria-hidden="true"></i>
                <?php endif; ?>
            </span>
			<?php
		} else {
			?>
            <div class="wpf-sbw wpf-sbw-fb">
				<?php if( wpforo_setting( 'social', 'sb_type' ) === 'button_count' ): ?>
                    <a target="_blank" href="<?php echo esc_url( $share_url ) ?>"
                       rel="nofollow"><?php wpforo_phrase( 'Share' ); ?></a>
				<?php elseif( wpforo_setting( 'social', 'sb_type' ) === 'button' ): ?>
                    <a class="wpf-sb-button wpf-fb" href="<?php echo esc_url( $share_url ) ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i> <span><?php wpforo_phrase( 'Share' ) ?></span>
                    </a>
				<?php else: ?>
                    <a class="wpf-sb-button wpf-sb-icon wpf-fb" href="<?php echo esc_url( $share_url ) ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}
	}
	
	public function tw_wjs() {
		?>
        <script type="text/javascript">window.twttr = (function (d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0], t = window.twttr || {};
				if (d.getElementById(id)) return t;
				js = d.createElement(s);
				js.id = id;
				js.src = 'https://platform.twitter.com/widgets.js';
				fjs.parentNode.insertBefore(js, fjs);
				t._e = [];
				t.ready = function (f) { t._e.push(f); };
				return t;
			}(document, 'script', 'twitter-wjs'));</script>
		<?php
	}
	
	public function tw_share_button( $url = '', $type = 'custom', $text = '' ) {
		if( ! wpforo_setting( 'social', 'sb', 'tw' ) ) return;
		$url    = $url ?: WPF()->current_url;
		$n_url  = strlen( (string) $url );
		$n_text = 280 - $n_url;
		$text   = $text ?: wpfval( WPF()->current_object, 'og_text' );
		$text   = urlencode( wpforo_text( strip_shortcodes( strip_tags( (string) $text ) ), $n_text, false ) );
		if( $type == 'custom' ) { ?>
            <a class="wpforo-share-button wpf-tw"
               href="https://twitter.com/intent/tweet?text=<?php echo $text ?>&url=<?php echo urlencode( (string) $url ) ?>"
               title="<?php wpforo_phrase( 'Tweet this post' ); ?>" rel="nofollow">
				<?php if( wpforo_setting( 'social', 'sb_icon' ) === 'figure' ): ?>
                    <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
				<?php elseif( wpforo_setting( 'social', 'sb_icon' ) === 'square' ): ?>
                    <i class="fa-brands fa-x-twitter-square" aria-hidden="true"></i>
				<?php else: ?>
                    <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
				<?php endif; ?>
            </a>
			<?php
		} else { ?>
            <div class="wpf-sbw wpf-sbw-tw">
				<?php if( wpforo_setting( 'social', 'sb_type' ) === 'button_count' ): ?>
                    <a href="https://twitter.com/share?ref_src=twsrc%5Etfw" class="twitter-share-button"
                       data-lang="<?php $this->local( 'tw' ) ?>" data-show-count="true" rel="nofollow"><?php wpforo_phrase(
							'Tweet'
						); ?></a>
				<?php elseif( wpforo_setting( 'social', 'sb_type' ) === 'button' ): ?>
                    <a class="wpf-sb-button wpf-tw"
                       href="https://twitter.com/intent/tweet?text=<?php echo $text ?>&url=<?php echo urlencode(
						   (string) $url
					   ) ?>" rel="nofollow">
                        <i class="fa-brands fa-x-twitter" aria-hidden="true"></i> <span><?php wpforo_phrase(
								'Tweet'
							) ?></span>
                    </a>
				<?php else: ?>
                    <a class="wpf-sb-button wpf-sb-icon wpf-tw"
                       href="https://twitter.com/intent/tweet?text=<?php echo $text ?>&url=<?php echo urlencode(
						   (string) $url
					   ) ?>" rel="nofollow">
                        <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}
	}
	
	public function vk_js() {
		?>
        <script type="text/javascript" src="https://vk.com/js/api/share.js?95" charset="windows-1251"></script>
		<?php
	}
	
	public function wapp_share_button( $url = '', $type = 'custom', $text = '' ) {
		if( ! wpforo_setting( 'social', 'sb', 'wapp' ) ) return;
		$url    = $url ?: WPF()->current_url;
		$domain = wp_is_mobile() ? 'https://api.whatsapp.com' : 'https://web.whatsapp.com';
		$text   = $text ?: ( wpfval( WPF()->current_object, 'og_text' ) ? WPF()->current_object['og_text'] : WPF()->board->get_current( 'settings' )['title'] );
		$text   = urlencode( wpforo_text( strip_shortcodes( strip_tags( (string) $text ) ), 100, false ) ) . ' URL: ' . urlencode( (string) $url );
		if( $type === 'custom' ) { ?>
            <a class="wpforo-share-button wpf-wapp" href="<?php echo $domain ?>/send?text=<?php echo $text ?>"
               title="<?php wpforo_phrase( 'Share to WhatsApp' ); ?>" target="_blank"
               data-action="share/whatsapp/share" rel="nofollow">
                <i class="fab fa-whatsapp" aria-hidden="true"></i>
            </a>
			<?php
		} else { ?>
            <div class="wpf-sbw wpf-sbw-wapp">
				<?php if( wpforo_setting( 'social', 'sb_type' ) === 'button_count' ): ?>
                    <!-- WhatsApp is not available -->
				<?php elseif( wpforo_setting( 'social', 'sb_type' ) === 'button' ): ?>
                    <a class="wpf-sb-button wpf-wapp" href="<?php echo $domain ?>/send?text=<?php echo $text ?>"
                       target="_blank" data-action="share/whatsapp/share" rel="nofollow">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i> <span><?php wpforo_phrase( 'Share' ) ?></span>
                    </a>
				<?php else: ?>
                    <a class="wpf-sb-button wpf-sb-icon wpf-wapp"
                       href="<?php echo $domain ?>/send?text=<?php echo $text ?>" target="_blank"
                       data-action="share/whatsapp/share" rel="nofollow">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}
	}
	
	public function lin_share_button( $url = '', $type = 'custom', $text = '' ) {
		if( ! wpforo_setting( 'social', 'sb', 'lin' ) ) return;
		$url   = $url ?: WPF()->current_url;
		$title = (string) wpfval( WPF()->current_object, 'topic', 'title' );
		$text  = $text ?: ( wpfval( WPF()->current_object, 'og_text' ) ? WPF()->current_object['og_text'] : WPF()->board->get_current(
			'settings'
		)['title'] );
		$text  = urlencode( wpforo_text( strip_shortcodes( strip_tags( (string) $text ) ), 500, false ) );
		if( $type == 'custom' ) { ?>
            <a class="wpforo-share-button wpf-lin"
               href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(
				   (string) $url
			   ) ?>&title=<?php echo urlencode( $title ) ?>&summary=<?php echo $text ?>"
               title="<?php wpforo_phrase( 'Share to LinkedIn' ); ?>" target="_blank" rel="nofollow">
                <i class="fab fa-linkedin-in" aria-hidden="true"></i>
            </a>
			<?php
		} else { ?>
            <div class="wpf-sbw wpf-sbw-lin">
				<?php if( wpforo_setting( 'social', 'sb_type' ) === 'button_count' ): ?>
                    <!-- LinkedIn is not available -->
				<?php elseif( wpforo_setting( 'social', 'sb_type' ) === 'button' ): ?>
                    <a class="wpf-sb-button wpf-lin"
                       href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(
						   (string) $url
					   ) ?>&title=<?php echo urlencode( $title ) ?>&summary=<?php echo $text ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-linkedin-in" aria-hidden="true"></i> <span><?php wpforo_phrase(
								'Share'
							) ?></span>
                    </a>
				<?php else: ?>
                    <a class="wpf-sb-button wpf-sb-icon wpf-lin"
                       href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(
						   (string) $url
					   ) ?>&title=<?php echo urlencode( $title ) ?>&summary=<?php echo $text ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-linkedin-in" aria-hidden="true"></i>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}
	}
	
	public function vk_share_button( $url = '', $type = 'custom', $text = '' ) {
		if( ! wpforo_setting( 'social', 'sb', 'vk' ) ) return;
		$url  = $url ?: WPF()->current_url;
		$text = $text ?: wpfval( WPF()->current_object, 'og_text' );
		$text = urlencode( wpforo_text( strip_shortcodes( strip_tags( (string) $text ) ), 500, false ) );
		if( $type === 'custom' ) { ?>
            <a class="wpforo-share-button wpf-vk" onclick="return VK.Share.click(0, this);"
               href="https://vk.com/share.php?url=<?php echo urlencode( (string) $url ) ?>&description=<?php echo $text ?>"
               title="<?php wpforo_phrase( 'Share to VK' ); ?>" target="_blank" rel="nofollow">
                <i class="fab fa-vk" aria-hidden="true"></i>
            </a>
			<?php
		} else { ?>
            <div class="wpf-sbw wpf-sbw-vk">
				<?php if( wpforo_setting( 'social', 'sb_type' ) === 'button_count' ): ?>
                    <script type="text/javascript">document.write(VK.Share.button(false, {
							type: 'round',
							text: "<?php wpforo_phrase( 'Share' ); ?>",
						}));</script>
				<?php elseif( wpforo_setting( 'social', 'sb_type' ) === 'button' ): ?>
                    <a class="wpf-sb-button wpf-vk" onclick="return VK.Share.click(0, this);"
                       href="https://vk.com/share.php?url=<?php echo urlencode(
						   (string) $url
					   ) ?>&description=<?php echo $text ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-vk" aria-hidden="true"></i> <span><?php wpforo_phrase( 'Share' ) ?></span>
                    </a>
				<?php else: ?>
                    <a class="wpf-sb-button wpf-sb-icon wpf-vk" onclick="return VK.Share.click(0, this);"
                       href="https://vk.com/share.php?url=<?php echo urlencode(
						   (string) $url
					   ) ?>&description=<?php echo $text ?>" target="_blank" rel="nofollow">
                        <i class="fab fa-vk" aria-hidden="true"></i>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}
	}
	
	
	public function share_toggle( $url = '', $text = '', $type = 'custom' ) {
		WPF()->api->fb_share_button( $url, $type );
		WPF()->api->tw_share_button( $url, $type, $text );
		WPF()->api->wapp_share_button( $url, $type, $text );
		WPF()->api->lin_share_button( $url, $type, $text );
		WPF()->api->vk_share_button( $url, $type, $text );
	}
	
	public function share_buttons( $url = '', $type = 'default', $text = '' ) {
		$template = wpfval( WPF()->current_object, 'template' );
		$exclude  = [ 'lostpassword', 'resetpassword' ];
		if( $template && ! in_array( $template, $exclude ) ) {
			WPF()->api->fb_share_button( $url, $type );
			WPF()->api->tw_share_button( $url, $type, $text );
			WPF()->api->wapp_share_button( $url, $type, $text );
			WPF()->api->lin_share_button( $url, $type, $text );
			WPF()->api->vk_share_button( $url, $type, $text );
		}
	}
	
	public function rc_enqueue() {
		$version  = wpforo_setting( 'recaptcha', 'version' );
		$theme    = wpforo_setting( 'recaptcha', 'theme' );
		$site_key = wpforo_setting( 'recaptcha', 'site_key' );

		// reCAPTCHA v3 uses a different script URL and approach
		if( $version === 'v3' ) {
			wp_register_script(
				'wpforo_recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . $site_key
			);
			wp_add_inline_script(
				'wpforo_recaptcha',
				"var wpForoReCaptchaV3Execute = function(action){
					return new Promise(function(resolve, reject){
						if( typeof grecaptcha !== 'undefined' && typeof grecaptcha.execute === 'function' ){
							grecaptcha.ready(function(){
								grecaptcha.execute('" . esc_js( $site_key ) . "', {action: action}).then(function(token){
									resolve(token);
								}).catch(function(error){
									reject(error);
								});
							});
						} else {
							reject('reCAPTCHA not loaded');
						}
					});
				};
				var wpForoReCaptchaV3Init = function(){
					var forms = document.querySelectorAll('form[data-wpforo-recaptcha-v3]');
					forms.forEach(function(form){
						form.addEventListener('submit', function(e){
							var tokenInput = form.querySelector('input[name=\"g-recaptcha-response\"]');
							if( tokenInput && !tokenInput.value ){
								e.preventDefault();
								var action = form.getAttribute('data-wpforo-recaptcha-action') || 'wpforo_form';
								wpForoReCaptchaV3Execute(action).then(function(token){
									tokenInput.value = token;
									form.submit();
								}).catch(function(error){
									console.error('reCAPTCHA error:', error);
									form.submit();
								});
							}
						});
					});
				};
				if( document.readyState === 'loading' ){
					document.addEventListener('DOMContentLoaded', wpForoReCaptchaV3Init);
				} else {
					wpForoReCaptchaV3Init();
				}"
			);
		} elseif( $version === 'v2_invisible' ) {
			// reCAPTCHA v2 Invisible
			wp_register_script(
				'wpforo_recaptcha',
				'https://www.google.com/recaptcha/api.js?onload=wpForoReCallback&render=explicit'
			);
			wp_add_inline_script(
				'wpforo_recaptcha',
				"var wpForoReWidgetIds = {};
				var wpForoReCallback = function(){
					setTimeout(function () {
						if( typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function' ){
							var rc_widgets = document.getElementsByClassName('wpforo_recaptcha_widget');
							if( rc_widgets.length ){
								var i;
								for (i = 0; i < rc_widgets.length; i++) {
									if( rc_widgets[i].firstElementChild === null ){
										rc_widgets[i].innerHTML = '';
										var form = rc_widgets[i].closest('form');
										var widgetId = grecaptcha.render(
											rc_widgets[i], {
												'sitekey': '" . esc_js( $site_key ) . "',
												'theme': '" . esc_js( $theme ) . "',
												'size': 'invisible',
												'callback': function(token){
													if( form ){
														var tokenInput = form.querySelector('input[name=\"g-recaptcha-response\"]');
														if( !tokenInput ){
															tokenInput = document.createElement('input');
															tokenInput.type = 'hidden';
															tokenInput.name = 'g-recaptcha-response';
															form.appendChild(tokenInput);
														}
														tokenInput.value = token;
														form.submit();
													}
												}
											}
										);
										if( form ){
											wpForoReWidgetIds[form.id || i] = widgetId;
											form.addEventListener('submit', function(e){
												var formId = this.id || Array.prototype.indexOf.call(document.forms, this);
												if( wpForoReWidgetIds[formId] !== undefined ){
													var tokenInput = this.querySelector('input[name=\"g-recaptcha-response\"]');
													if( !tokenInput || !tokenInput.value ){
														e.preventDefault();
														grecaptcha.execute(wpForoReWidgetIds[formId]);
													}
												}
											});
										}
									}
								}
							}
						}
					}, 800);
				}"
			);
		} else {
			// reCAPTCHA v2 Checkbox (default)
			wp_register_script(
				'wpforo_recaptcha',
				'https://www.google.com/recaptcha/api.js?onload=wpForoReCallback&render=explicit'
			);
			wp_add_inline_script(
				'wpforo_recaptcha',
				"var wpForoReCallback = function(){
					setTimeout(function () {
						if( typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function' ){
							var rc_widgets = document.getElementsByClassName('wpforo_recaptcha_widget');
							if( rc_widgets.length ){
								var i;
								for (i = 0; i < rc_widgets.length; i++) {
									if( rc_widgets[i].firstElementChild === null ){
										rc_widgets[i].innerHTML = '';
										grecaptcha.render(
											rc_widgets[i], { 'sitekey': '" . esc_js( $site_key ) . "', 'theme': '" . esc_js( $theme ) . "' }
										);
									}
								}
							}
						}
					}, 800);
				}"
			);
		}
		wp_enqueue_script( 'wpforo_recaptcha' );
	}
	
	public function rc_enqueue_async( $tag, $handle ) {
		if( $handle === 'wpforo_recaptcha' ) return str_replace( '<script', '<script async defer', $tag );
		
		return $tag;
	}
	
	public function rc_enqueue_css() {
		$version = wpforo_setting( 'recaptcha', 'version' );

		// Only add CSS for v2 checkbox (visible widget)
		// v2 invisible and v3 don't need widget styling
		if( $version !== 'v2_checkbox' ) {
			return;
		}

		wp_register_style( 'wpforo-rc-style', false );
		wp_enqueue_style( 'wpforo-rc-style' );
		$custom_css = ".wpforo_recaptcha_widget{ -webkit-transform:scale(0.9); transform:scale(0.9); -webkit-transform-origin:left 0; transform-origin:left 0; }";
		wp_add_inline_style( 'wpforo-rc-style', $custom_css );
	}
	
	public function rc_widget( $action = 'wpforo_form' ) {
		$site_key = wpforo_setting( 'recaptcha', 'site_key' );
		$version  = wpforo_setting( 'recaptcha', 'version' );

		if( $site_key ) {
			if( $version === 'v3' ) {
				// reCAPTCHA v3 - hidden token input, form gets data attributes
				echo '<input type="hidden" name="g-recaptcha-response" value="" />';
				echo '<input type="hidden" name="g-recaptcha-version" value="v3" />';
				// Add JS to mark the parent form for v3 processing
				echo "\r\n<script>(function(){
					var input = document.currentScript.previousElementSibling.previousElementSibling;
					var form = input.closest('form');
					if(form){
						form.setAttribute('data-wpforo-recaptcha-v3', '1');
						form.setAttribute('data-wpforo-recaptcha-action', '" . esc_js( $action ) . "');
					}
				})();</script>";
			} else {
				// reCAPTCHA v2 (checkbox or invisible) - standard widget div
				echo '<div class="wpforo_recaptcha_widget"></div><div class="wpf-cl"></div>';
				echo "\r\n<script>if(typeof wpForoReCallback === 'function') wpForoReCallback();</script>";
			}
		}
	}
	
	public function _rc_check() {
		if( isset( $_POST['g-recaptcha-response'] ) && ! empty( $_POST['g-recaptcha-response'] ) ) {
			$secret_key = wpforo_setting( 'recaptcha', 'secret_key' );
			$version    = wpforo_setting( 'recaptcha', 'version' );

			// Use POST method for verification (recommended by Google)
			$response = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				[
					'body' => [
						'secret'   => $secret_key,
						'response' => sanitize_text_field( $_POST['g-recaptcha-response'] ),
						'remoteip' => WPF()->current_user_ip,
					],
				]
			);

			if( is_wp_error( $response ) || empty( $response['body'] ) ) {
				$error = wpforo_phrase( "ERROR: Can't connect to Google reCAPTCHA API", false );
				if( wpforo_setting( 'general', 'debug_mode' ) && is_wp_error( $response ) ) {
					$error .= ' ( ' . $response->get_error_message() . ' )';
				}

				return $error;
			}

			$response_data = json_decode( $response['body'], true );

			if( ! empty( $response_data['success'] ) ) {
				// For reCAPTCHA v3, also check the score
				if( $version === 'v3' || ( isset( $_POST['g-recaptcha-version'] ) && $_POST['g-recaptcha-version'] === 'v3' ) ) {
					$score     = isset( $response_data['score'] ) ? (float) $response_data['score'] : 0;
					$threshold = (float) wpforo_setting( 'recaptcha', 'v3_score_threshold' );

					// Default threshold to 0.5 if not set
					if( $threshold <= 0 || $threshold > 1 ) {
						$threshold = 0.5;
					}

					if( $score < $threshold ) {
						// Score too low, likely a bot
						$error = wpforo_phrase( 'reCAPTCHA verification failed: suspicious activity detected', false );
						if( wpforo_setting( 'general', 'debug_mode' ) ) {
							$error .= sprintf( ' (score: %.2f, threshold: %.2f)', $score, $threshold );
						}

						return $error;
					}
				}

				return 'success';
			} else {
				$error = wpforo_phrase( 'Google reCAPTCHA verification failed', false );
				if( wpforo_setting( 'general', 'debug_mode' ) && ! empty( $response_data['error-codes'] ) ) {
					$error .= ' (' . implode( ', ', $response_data['error-codes'] ) . ')';
				}

				return $error;
			}
		} else {
			return wpforo_phrase( 'Google reCAPTCHA data are not submitted', false );
		}
	}
	
	public function rc_check() {
		return wpforo_ram_get( [ &$this, '_rc_check' ] );
	}
	
	public function rc_verify() {
		if( ! wpforo_setting( 'recaptcha', 'post_editor' ) || ! wpforo_setting( 'recaptcha', 'topic_editor' ) ) {
			if( ! wpforo_setting( 'recaptcha', 'post_editor' ) && in_array(
					wpfval( $_POST, 'wpfaction' ),
					[ 'post_add', 'post_edit' ],
					true
				) ) {
				return true;
			} elseif( ! wpforo_setting( 'recaptcha', 'topic_editor' ) && in_array(
					wpfval( $_POST, 'wpfaction' ),
					[ 'topic_add', 'topic_edit' ],
					true
				) ) {
				return true;
			}
		}
		$result = $this->rc_check();
		if( $result === 'success' ) {
			return true;
		} else {
			WPF()->notice->add( $result, 'error' );
			wp_safe_redirect( wpforo_get_request_uri() );
			exit();
		}
	}
	
	public function rc_verify_wp_login( $user ) {
		if( ! isset( $_POST['log'] ) && ! isset( $_POST['pwd'] ) ) return $user;
		if( ! wpforo_setting( 'recaptcha', 'login_form' ) || ! wpforo_setting( 'recaptcha', 'wpf_login_form' ) ) {
			if( ! wpfval( $_POST, 'wpforologin' ) && ! wpforo_setting( 'recaptcha', 'login_form' ) ) {
				return $user;
			} elseif( wpfval( $_POST, 'wpforologin' ) && ! wpforo_setting( 'recaptcha', 'wpf_login_form' ) ) {
				return $user;
			}
		}
		$errors = is_wp_error( $user ) ? $user : new WP_Error();
		$result = $this->rc_check();
		if( $result !== 'success' ) {
			$errors->add( 'wpforo-recaptcha-error', $result );
			$user = is_wp_error( $user ) ? $user : $errors;
			remove_filter( 'authenticate', 'wp_authenticate_username_password' );
			remove_filter( 'authenticate', 'wp_authenticate_cookie' );
		}
		
		return $user;
	}
	
	public function rc_verify_wp_register( $errors = '' ) {
		if( ! is_wp_error( $errors ) ) $errors = new WP_Error();
		if( ! wpforo_setting( 'recaptcha', 'reg_form' ) || ! wpforo_setting( 'recaptcha', 'wpf_reg_form' ) ) {
			if( ! wpfval( $_POST, 'wpfreg' ) && ! wpforo_setting( 'recaptcha', 'reg_form' ) ) {
				return $errors;
			} elseif( wpfval( $_POST, 'wpfreg' ) && ! wpforo_setting( 'recaptcha', 'wpf_reg_form' ) ) {
				return $errors;
			}
		}
		$result = $this->rc_check();
		if( $result !== 'success' ) {
			$errors->add( 'wpforo-recaptcha-error', $result );
		}
		
		return $errors;
	}
	
	public function rc_verify_wp_lostpassword( $errors = '' ) {
		if( ! is_wp_error( $errors ) ) $errors = new WP_Error();
		if( ! wpforo_setting( 'recaptcha', 'lostpass_form' ) || ! wpforo_setting( 'recaptcha', 'wpf_lostpass_form' ) ) {
			if( ! wpfval( $_POST, 'wpfororp' ) && ! wpforo_setting( 'recaptcha', 'lostpass_form' ) ) {
				return;
			} elseif( wpfval( $_POST, 'wpfororp' ) && ! wpforo_setting( 'recaptcha', 'wpf_lostpass_form' ) ) {
				return;
			}
		}
		$result = $this->rc_check();
		if( $result !== 'success' ) {
			if( isset( $_POST['wc_reset_password'] ) && isset( $_POST['_wp_http_referer'] ) ) {
				//$errors->add('wpforo-recaptcha-error', $result);
				//return $errors;
			} else {
				wp_die( $result, 'reCAPTCHA ERROR', [ 'back_link' => true ] );
			}
		}
	}
}
