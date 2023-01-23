<?php
/**
 * WordPress User Page
 *
 * Handles authentication, registering, resetting passwords, forgot password,
 * and other user handling.
 *
 * @package WordPress
 */

/** Make sure that the WordPress bootstrap has run before continuing. */
require __DIR__ . '/wp-load.php';

// Redirect to HTTPS login if forced to use SSL.
if ( force_ssl_admin() && ! is_ssl() ) {
	if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
		wp_safe_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
		exit;
	} else {
		wp_safe_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit;
	}
}

/**
 * Output the login page header.
 *
 * @since 2.1.0
 *
 * @global string      $error         Login error message set by deprecated pluggable wp_login() function
 *                                    or plugins replacing it.
 * @global bool|string $interim_login Whether interim login modal is being displayed. String 'success'
 *                                    upon successful login.
 * @global string      $action        The action that brought the visitor to the login page.
 *
 * @param string   $title    Optional. WordPress login Page title to display in the `<title>` element.
 *                           Default 'Log In'.
 * @param string   $message  Optional. Message to display in header. Default empty.
 * @param WP_Error $wp_error Optional. The error to pass. Default is a WP_Error instance.
 */
function login_header( $title = 'Log In', $message = '', $wp_error = null ) {
	global $error, $interim_login, $action;

	// Don't index any of these forms.
	add_filter( 'wp_robots', 'wp_robots_sensitive_page' );
	add_action( 'login_head', 'wp_strict_cross_origin_referrer' );

	add_action( 'login_head', 'wp_login_viewport_meta' );

	if ( ! is_wp_error( $wp_error ) ) {
		$wp_error = new WP_Error();
	}

	// Shake it!
	$shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password', 'retrieve_password_email_failure' );
	/**
	 * Filters the error codes array for shaking the login form.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $shake_error_codes Error codes that shake the login form.
	 */
	$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );

	if ( $shake_error_codes && $wp_error->has_errors() && in_array( $wp_error->get_error_code(), $shake_error_codes, true ) ) {
		add_action( 'login_footer', 'wp_shake_js', 12 );
	}

	$login_title = get_bloginfo( 'name', 'display' );

	/* translators: Login screen title. 1: Login screen name, 2: Network or site name. */
	$login_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $login_title );

	if ( wp_is_recovery_mode() ) {
		/* translators: %s: Login screen title. */
		$login_title = sprintf( __( 'Recovery Mode &#8212; %s' ), $login_title );
	}

	/**
	 * Filters the title tag content for login page.
	 *
	 * @since 4.9.0
	 *
	 * @param string $login_title The page title, with extra context added.
	 * @param string $title       The original page title.
	 */
	$login_title = apply_filters( 'login_title', $login_title, $title );

	?><!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
	<title><?php echo $login_title; ?></title>
	<?php

	wp_enqueue_style( 'login' );

	/*
	 * Remove all stored post data on logging out.
	 * This could be added by add_action('login_head'...) like wp_shake_js(),
	 * but maybe better if it's not removable by plugins.
	 */
	if ( 'loggedout' === $wp_error->get_error_code() ) {
		?>
		<script>if("sessionStorage" in window){try{for(var key in sessionStorage){if(key.indexOf("wp-autosave-")!=-1){sessionStorage.removeItem(key)}}}catch(e){}};</script>
		<?php
	}

	/**
	 * Enqueue scripts and styles for the login page.
	 *
	 * @since 3.1.0
	 */
	do_action( 'login_enqueue_scripts' );

	/**
	 * Fires in the login page header after scripts are enqueued.
	 *
	 * @since 2.1.0
	 */
	do_action( 'login_head' );

	$login_header_url = __( 'https://wordpress.org/' );

	/**
	 * Filters link URL of the header logo above login form.
	 *
	 * @since 2.1.0
	 *
	 * @param string $login_header_url Login header logo URL.
	 */
	$login_header_url = apply_filters( 'login_headerurl', $login_header_url );

	$login_header_title = '';

	/**
	 * Filters the title attribute of the header logo above login form.
	 *
	 * @since 2.1.0
	 * @deprecated 5.2.0 Use {@see 'login_headertext'} instead.
	 *
	 * @param string $login_header_title Login header logo title attribute.
	 */
	$login_header_title = apply_filters_deprecated(
		'login_headertitle',
		array( $login_header_title ),
		'5.2.0',
		'login_headertext',
		__( 'Usage of the title attribute on the login logo is not recommended for accessibility reasons. Use the link text instead.' )
	);

	$login_header_text = empty( $login_header_title ) ? __( 'Powered by WordPress' ) : $login_header_title;

	/**
	 * Filters the link text of the header logo above the login form.
	 *
	 * @since 5.2.0
	 *
	 * @param string $login_header_text The login header logo link text.
	 */
	$login_header_text = apply_filters( 'login_headertext', $login_header_text );

	$classes = array( 'login-action-' . $action, 'wp-core-ui' );

	if ( is_rtl() ) {
		$classes[] = 'rtl';
	}

	if ( $interim_login ) {
		$classes[] = 'interim-login';

		?>
		<style type="text/css">html{background-color: transparent;}</style>
		<?php

		if ( 'success' === $interim_login ) {
			$classes[] = 'interim-login-success';
		}
	}

	$classes[] = ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

	/**
	 * Filters the login page body classes.
	 *
	 * @since 3.5.0
	 *
	 * @param string[] $classes An array of body classes.
	 * @param string   $action  The action that brought the visitor to the login page.
	 */
	$classes = apply_filters( 'login_body_class', $classes, $action );

	?>
	</head>
	<body class="login no-js <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<script type="text/javascript">
		document.body.className = document.body.className.replace('no-js','js');
	</script>
	<?php
	/**
	 * Fires in the login page header after the body tag is opened.
	 *
	 * @since 4.6.0
	 */
	do_action( 'login_header' );

	?>
	<div id="login">
		<h1><a href="<?php echo esc_url( $login_header_url ); ?>"><?php echo $login_header_text; ?></a></h1>
	<?php
	/**
	 * Filters the message to display above the login form.
	 *
	 * @since 2.1.0
	 *
	 * @param string $message Login message text.
	 */
	$message = apply_filters( 'login_message', $message );

	if ( ! empty( $message ) ) {
		echo $message . "\n";
	}

	// In case a plugin uses $error rather than the $wp_errors object.
	if ( ! empty( $error ) ) {
		$wp_error->add( 'error', $error );
		unset( $error );
	}

	if ( $wp_error->has_errors() ) {
		$errors   = '';
		$messages = '';

		foreach ( $wp_error->get_error_codes() as $code ) {
			$severity = $wp_error->get_error_data( $code );
			foreach ( $wp_error->get_error_messages( $code ) as $error_message ) {
				if ( 'message' === $severity ) {
					$messages .= '	' . $error_message . "<br />\n";
				} else {
					$errors .= '	' . $error_message . "<br />\n";
				}
			}
		}

		if ( ! empty( $errors ) ) {
			/**
			 * Filters the error messages displayed above the login form.
			 *
			 * @since 2.1.0
			 *
			 * @param string $errors Login error message.
			 */
			echo '<div id="login_error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
		}

		if ( ! empty( $messages ) ) {
			/**
			 * Filters instructional messages displayed above the login form.
			 *
			 * @since 2.5.0
			 *
			 * @param string $messages Login messages.
			 */
			echo '<p class="message" id="login-message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
		}
	}
} // End of login_header().

/**
 * Outputs the footer for the login page.
 *
 * @since 3.1.0
 *
 * @global bool|string $interim_login Whether interim login modal is being displayed. String 'success'
 *                                    upon successful login.
 *
 * @param string $input_id Which input to auto-focus.
 */
function login_footer( $input_id = '' ) {
	global $interim_login;

	// Don't allow interim logins to navigate away from the page.
	if ( ! $interim_login ) {
		?>
		<p id="backtoblog">
			<?php
			$html_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( home_url( '/' ) ),
				sprintf(
					/* translators: %s: Site title. */
					_x( '&larr; Go to %s', 'site' ),
					get_bloginfo( 'title', 'display' )
				)
			);
			/**
			 * Filter the "Go to site" link displayed in the login page footer.
			 *
			 * @since 5.7.0
			 *
			 * @param string $link HTML link to the home URL of the current site.
			 */
			echo apply_filters( 'login_site_html_link', $html_link );
			?>
		</p>
		<?php

		the_privacy_policy_link( '<div class="privacy-policy-page-link">', '</div>' );
	}

	?>
	</div><?php // End of <div id="login">. ?>

	<?php
	if (
		! $interim_login &&
		/**
		 * Filters the Languages select input activation on the login screen.
		 *
		 * @since 5.9.0
		 *
		 * @param bool Whether to display the Languages select input on the login screen.
		 */
		apply_filters( 'login_display_language_dropdown', true )
	) {
		$languages = get_available_languages();

		if ( ! empty( $languages ) ) {
			?>
			<div class="language-switcher">
				<form id="language-switcher" action="" method="get">

					<label for="language-switcher-locales">
						<span class="dashicons dashicons-translation" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php _e( 'Language' ); ?></span>
					</label>

					<?php
					$args = array(
						'id'                          => 'language-switcher-locales',
						'name'                        => 'wp_lang',
						'selected'                    => determine_locale(),
						'show_available_translations' => false,
						'explicit_option_en_us'       => true,
						'languages'                   => $languages,
					);

					/**
					 * Filters default arguments for the Languages select input on the login screen.
					 *
					 * The arguments get passed to the wp_dropdown_languages() function.
					 *
					 * @since 5.9.0
					 *
					 * @param array $args Arguments for the Languages select input on the login screen.
					 */
					wp_dropdown_languages( apply_filters( 'login_language_dropdown_args', $args ) );
					?>

					<?php if ( $interim_login ) { ?>
						<input type="hidden" name="interim-login" value="1" />
					<?php } ?>

					<?php if ( isset( $_GET['redirect_to'] ) && '' !== $_GET['redirect_to'] ) { ?>
						<input type="hidden" name="redirect_to" value="<?php echo sanitize_url( $_GET['redirect_to'] ); ?>" />
					<?php } ?>

					<?php if ( isset( $_GET['action'] ) && '' !== $_GET['action'] ) { ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( $_GET['action'] ); ?>" />
					<?php } ?>

						<input type="submit" class="button" value="<?php esc_attr_e( 'Change' ); ?>">

					</form>
				</div>
		<?php } ?>
	<?php } ?>
	<?php

	if ( ! empty( $input_id ) ) {
		?>
		<script type="text/javascript">
		try{document.getElementById('<?php echo $input_id; ?>').focus();}catch(e){}
		if(typeof wpOnload==='function')wpOnload();
		</script>
		<?php
	}

	/**
	 * Fires in the login page footer.
	 *
	 * @since 3.1.0
	 */
	do_action( 'login_footer' );

	?>
	<div class="clear"></div>
	</body>
	</html>
	<?php
}

/**
 * Outputs the JavaScript to handle the form shaking on the login page.
 *
 * @since 3.0.0
 */
function wp_shake_js() {
	?>
	<script type="text/javascript">
	document.querySelector('form').classList.add('shake');
	</script>
	<?php
}

/**
 * Outputs the viewport meta tag for the login page.
 *
 * @since 3.7.0
 */
function wp_login_viewport_meta() {
	?>
	<meta name="viewport" content="width=device-width" />
	<?php
}

/*
 * Main part.
 *
 * Check the request and redirect or display a form based on the current action.
 */

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';
$errors = new WP_Error();

if ( isset( $_GET['key'] ) ) {
	$action = 'resetpass';
}

if ( isset( $_GET['checkemail'] ) ) {
	$action = 'checkemail';
}

$default_actions = array(
	'confirm_admin_email',
	'postpass',
	'logout',
	'lostpassword',
	'retrievepassword',
	'resetpass',
	'rp',
	'register',
	'checkemail',
	'confirmaction',
	'login',
	WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTERED,
);

// Validate action so as to default to the login screen.
if ( ! in_array( $action, $default_actions, true ) && false === has_filter( 'login_form_' . $action ) ) {
	$action = 'login';
}

nocache_headers();

header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );

if ( defined( 'RELOCATE' ) && RELOCATE ) { // Move flag is set.
	if ( isset( $_SERVER['PATH_INFO'] ) && ( $_SERVER['PATH_INFO'] !== $_SERVER['PHP_SELF'] ) ) {
		$_SERVER['PHP_SELF'] = str_replace( $_SERVER['PATH_INFO'], '', $_SERVER['PHP_SELF'] );
	}

	$url = dirname( set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ) );

	if ( get_option( 'siteurl' ) !== $url ) {
		update_option( 'siteurl', $url );
	}
}

// Set a cookie now to see if they are supported by the browser.
$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN, $secure );

if ( SITECOOKIEPATH !== COOKIEPATH ) {
	setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
}

if ( isset( $_GET['wp_lang'] ) ) {
	setcookie( 'wp_lang', sanitize_text_field( $_GET['wp_lang'] ), 0, COOKIEPATH, COOKIE_DOMAIN, $secure );
}

/**
 * Fires when the login form is initialized.
 *
 * @since 3.2.0
 */
do_action( 'login_init' );

/**
 * Fires before a specified login form action.
 *
 * The dynamic portion of the hook name, `$action`, refers to the action
 * that brought the visitor to the login form.
 *
 * Possible hook names include:
 *
 *  - `login_form_checkemail`
 *  - `login_form_confirm_admin_email`
 *  - `login_form_confirmaction`
 *  - `login_form_entered_recovery_mode`
 *  - `login_form_login`
 *  - `login_form_logout`
 *  - `login_form_lostpassword`
 *  - `login_form_postpass`
 *  - `login_form_register`
 *  - `login_form_resetpass`
 *  - `login_form_retrievepassword`
 *  - `login_form_rp`
 *
 * @since 2.8.0
 */
do_action( "login_form_{$action}" );

$http_post     = ( 'POST' === $_SERVER['REQUEST_METHOD'] );
$interim_login = isset( $_REQUEST['interim-login'] );

/**
 * Filters the separator used between login form navigation links.
 *
 * @since 4.9.0
 *
 * @param string $login_link_separator The separator used between login form navigation links.
 */
$login_link_separator = apply_filters( 'login_link_separator', ' | ' );

switch ( $action ) {

	case 'confirm_admin_email':
		/*
		 * Note that `is_user_logged_in()` will return false immediately after logging in
		 * as the current user is not set, see wp-includes/pluggable.php.
		 * However this action runs on a redirect after logging in.
		 */
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = $_REQUEST['redirect_to'];
		} else {
			$redirect_to = admin_url();
		}

		if ( current_user_can( 'manage_options' ) ) {
			$admin_email = get_option( 'admin_email' );
		} else {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		/**
		 * Filters the interval for dismissing the admin email confirmation screen.
		 *
		 * If `0` (zero) is returned, the "Remind me later" link will not be displayed.
		 *
		 * @since 5.3.1
		 *
		 * @param int $interval Interval time (in seconds). Default is 3 days.
		 */
		$remind_interval = (int) apply_filters( 'admin_email_remind_interval', 3 * DAY_IN_SECONDS );

		if ( ! empty( $_GET['remind_me_later'] ) ) {
			if ( ! wp_verify_nonce( $_GET['remind_me_later'], 'remind_me_later_nonce' ) ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}

			if ( $remind_interval > 0 ) {
				update_option( 'admin_email_lifespan', time() + $remind_interval );
			}

			$redirect_to = add_query_arg( 'admin_email_remind_later', 1, $redirect_to );
			wp_safe_redirect( $redirect_to );
			exit;
		}

		if ( ! empty( $_POST['correct-admin-email'] ) ) {
			if ( ! check_admin_referer( 'confirm_admin_email', 'confirm_admin_email_nonce' ) ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}

			/**
			 * Filters the interval for redirecting the user to the admin email confirmation screen.
			 *
			 * If `0` (zero) is returned, the user will not be redirected.
			 *
			 * @since 5.3.0
			 *
			 * @param int $interval Interval time (in seconds). Default is 6 months.
			 */
			$admin_email_check_interval = (int) apply_filters( 'admin_email_check_interval', 6 * MONTH_IN_SECONDS );

			if ( $admin_email_check_interval > 0 ) {
				update_option( 'admin_email_lifespan', time() + $admin_email_check_interval );
			}

			wp_safe_redirect( $redirect_to );
			exit;
		}

		login_header( __( 'Confirm your administration email' ), '', $errors );

		/**
		 * Fires before the admin email confirm form.
		 *
		 * @since 5.3.0
		 *
		 * @param WP_Error $errors A `WP_Error` object containing any errors generated by using invalid
		 *                         credentials. Note that the error object may not contain any errors.
		 */
		do_action( 'admin_email_confirm', $errors );

		?>

		<form class="admin-email-confirm-form" name="admin-email-confirm-form" action="<?php echo esc_url( site_url( 'wp-login.php?action=confirm_admin_email', 'login_post' ) ); ?>" method="post">
			<?php
			/**
			 * Fires inside the admin-email-confirm-form form tags, before the hidden fields.
			 *
			 * @since 5.3.0
			 */
			do_action( 'admin_email_confirm_form' );

			wp_nonce_field( 'confirm_admin_email', 'confirm_admin_email_nonce' );

			?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

			<h1 class="admin-email__heading">
				<?php _e( 'Administration email verification' ); ?>
			</h1>
			<p class="admin-email__details">
				<?php _e( 'Please verify that the <strong>administration email</strong> for this website is still correct.' ); ?>
				<?php

				/* translators: URL to the WordPress help section about admin email. */
				$admin_email_help_url = __( 'https://wordpress.org/support/article/settings-general-screen/#email-address' );

				/* translators: Accessibility text. */
				$accessibility_text = sprintf( '<span class="screen-reader-text"> %s</span>', __( '(opens in a new tab)' ) );

				printf(
					'<a href="%s" rel="noopener" target="_blank">%s%s</a>',
					esc_url( $admin_email_help_url ),
					__( 'Why is this important?' ),
					$accessibility_text
				);

				?>
			</p>
			<p class="admin-email__details">
				<?php

				printf(
					/* translators: %s: Admin email address. */
					__( 'Current administration email: %s' ),
					'<strong>' . esc_html( $admin_email ) . '</strong>'
				);

				?>
			</p>
			<p class="admin-email__details">
				<?php _e( 'This email may be different from your personal email address.' ); ?>
			</p>

			<div class="admin-email__actions">
				<div class="admin-email__actions-primary">
					<?php

					$change_link = admin_url( 'options-general.php' );
					$change_link = add_query_arg( 'highlight', 'confirm_admin_email', $change_link );

					?>
					<a class="button button-large" href="<?php echo esc_url( $change_link ); ?>"><?php _e( 'Update' ); ?></a>
					<input type="submit" name="correct-admin-email" id="correct-admin-email" class="button button-primary button-large" value="<?php esc_attr_e( 'The email is correct' ); ?>" />
				</div>
				<?php if ( $remind_interval > 0 ) : ?>
					<div class="admin-email__actions-secondary">
						<?php

						$remind_me_link = wp_login_url( $redirect_to );
						$remind_me_link = add_query_arg(
							array(
								'action'          => 'confirm_admin_email',
								'remind_me_later' => wp_create_nonce( 'remind_me_later_nonce' ),
							),
							$remind_me_link
						);

						?>
						<a href="<?php echo esc_url( $remind_me_link ); ?>"><?php _e( 'Remind me later' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</form>

		<?php

		login_footer();
		break;

	case 'postpass':
		if ( ! array_key_exists( 'post_password', $_POST ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );

		/**
		 * Filters the life span of the post password cookie.
		 *
		 * By default, the cookie expires 10 days from creation. To turn this
		 * into a session cookie, return 0.
		 *
		 * @since 3.7.0
		 *
		 * @param int $expires The expiry time, as passed to setcookie().
		 */
		$expire  = apply_filters( 'post_password_expires', time() + 10 * DAY_IN_SECONDS );
		$referer = wp_get_referer();

		if ( $referer ) {
			$secure = ( 'https' === parse_url( $referer, PHP_URL_SCHEME ) );
		} else {
			$secure = false;
		}

		setcookie( 'wp-postpass_' . COOKIEHASH, $hasher->HashPassword( wp_unslash( $_POST['post_password'] ) ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );

		wp_safe_redirect( wp_get_referer() );
		exit;

	case 'logout':
		check_admin_referer( 'log-out' );

		$user = wp_get_current_user();

		wp_logout();

		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_to           = $_REQUEST['redirect_to'];
			$requested_redirect_to = $redirect_to;
		} else {
			$redirect_to = add_query_arg(
				array(
					'loggedout' => 'true',
					'wp_lang'   => get_user_locale( $user ),
				),
				wp_login_url()
			);

			$requested_redirect_to = '';
		}

		/**
		 * Filters the log out redirect URL.
		 *
		 * @since 4.2.0
		 *
		 * @param string  $redirect_to           The redirect destination URL.
		 * @param string  $requested_redirect_to The requested redirect destination URL passed as a parameter.
		 * @param WP_User $user                  The WP_User object for the user that's logging out.
		 */
		$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );

		wp_safe_redirect( $redirect_to );
		exit;

	case 'lostpassword':
	case 'retrievepassword':
		if ( $http_post ) {
			$errors = retrieve_password();

			if ( ! is_wp_error( $errors ) ) {
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : 'wp-login.php?checkemail=confirm';
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		if ( isset( $_GET['error'] ) ) {
			if ( 'invalidkey' === $_GET['error'] ) {
				$errors->add( 'invalidkey', __( '<strong>Error:</strong> Your password reset link appears to be invalid. Please request a new link below.' ) );
			} elseif ( 'expiredkey' === $_GET['error'] ) {
				$errors->add( 'expiredkey', __( '<strong>Error:</strong> Your password reset link has expired. Please request a new link below.' ) );
			}
		}

		$lostpassword_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		/**
		 * Filters the URL redirected to after submitting the lostpassword/retrievepassword form.
		 *
		 * @since 3.0.0
		 *
		 * @param string $lostpassword_redirect The redirect destination URL.
		 */
		$redirect_to = apply_filters( 'lostpassword_redirect', $lostpassword_redirect );

		/**
		 * Fires before the lost password form.
		 *
		 * @since 1.5.1
		 * @since 5.1.0 Added the `$errors` parameter.
		 *
		 * @param WP_Error $errors A `WP_Error` object containing any errors generated by using invalid
		 *                         credentials. Note that the error object may not contain any errors.
		 */
		do_action( 'lost_password', $errors );

		login_header( __( 'Lost Password' ), '<p class="message">' . __( 'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' ) . '</p>', $errors );

		$user_login = '';

		if ( isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ) {
			$user_login = wp_unslash( $_POST['user_login'] );
		}

		?>

		<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
			<p>
				<label for="user_login"><?php _e( 'Username or Email Address' ); ?></label>
				<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr( $user_login ); ?>" size="20" autocapitalize="off" autocomplete="username" />
			</p>
			<?php

			/**
			 * Fires inside the lostpassword form tags, before the hidden fields.
			 *
			 * @since 2.1.0
			 */
			do_action( 'lostpassword_form' );

			?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Get New Password' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
			<?php

			if ( get_option( 'users_can_register' ) ) {
				$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

				echo esc_html( $login_link_separator );

				/** This filter is documented in wp-includes/general-template.php */
				echo apply_filters( 'register', $registration_url );
			}

			?>
		</p>
		<?php

		login_footer( 'user_login' );
		break;

	case 'resetpass':
	case 'rp':
		list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$rp_cookie       = 'wp-resetpass-' . COOKIEHASH;

		if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
			$value = sprintf( '%s:%s', wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
			setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

			wp_safe_redirect( remove_query_arg( array( 'key', 'login' ) ) );
			exit;
		}

		if ( isset( $_COOKIE[ $rp_cookie ] ) && 0 < strpos( $_COOKIE[ $rp_cookie ], ':' ) ) {
			list( $rp_login, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[ $rp_cookie ] ), 2 );

			$user = check_password_reset_key( $rp_key, $rp_login );

			if ( isset( $_POST['pass1'] ) && ! hash_equals( $rp_key, $_POST['rp_key'] ) ) {
				$user = false;
			}
		} else {
			$user = false;
		}

		if ( ! $user || is_wp_error( $user ) ) {
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

			if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=expiredkey' ) );
			} else {
				wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=invalidkey' ) );
			}

			exit;
		}

		$errors = new WP_Error();

		// Check if password is one or all empty spaces.
		if ( ! empty( $_POST['pass1'] ) ) {
			$_POST['pass1'] = trim( $_POST['pass1'] );

			if ( empty( $_POST['pass1'] ) ) {
				$errors->add( 'password_reset_empty_space', __( 'The password cannot be a space or all spaces.' ) );
			}
		}

		// Check if password fields do not match.
		if ( ! empty( $_POST['pass1'] ) && trim( $_POST['pass2'] ) !== $_POST['pass1'] ) {
			$errors->add( 'password_reset_mismatch', __( '<strong>Error:</strong> The passwords do not match.' ) );
		}

		/**
		 * Fires before the password reset procedure is validated.
		 *
		 * @since 3.5.0
		 *
		 * @param WP_Error         $errors WP Error object.
		 * @param WP_User|WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
		 */
		do_action( 'validate_password_reset', $errors, $user );

		if ( ( ! $errors->has_errors() ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
			reset_password( $user, $_POST['pass1'] );
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
			login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a></p>' );
			login_footer();
			exit;
		}

		wp_enqueue_script( 'utils' );
		wp_enqueue_script( 'user-profile' );

		login_header( __( 'Reset Password' ), '<p class="message reset-pass">' . __( 'Enter your new password below or generate one.' ) . '</p>', $errors );

		?>
		<form name="resetpassform" id="resetpassform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=resetpass', 'login_post' ) ); ?>" method="post" autocomplete="off">
			<input type="hidden" id="user_login" value="<?php echo esc_attr( $rp_login ); ?>" autocomplete="off" />

			<div class="user-pass1-wrap">
				<p>
					<label for="pass1"><?php _e( 'New password' ); ?></label>
				</p>

				<div class="wp-pwd">
					<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="pass1" id="pass1" class="input password-input" size="24" value="" autocomplete="new-password" aria-describedby="pass-strength-result" />

					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
						<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
					</button>
					<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator' ); ?></div>
				</div>
				<div class="pw-weak">
					<input type="checkbox" name="pw_weak" id="pw-weak" class="pw-checkbox" />
					<label for="pw-weak"><?php _e( 'Confirm use of weak password' ); ?></label>
				</div>
			</div>

			<p class="user-pass2-wrap">
				<label for="pass2"><?php _e( 'Confirm new password' ); ?></label>
				<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="new-password" />
			</p>

			<p class="description indicator-hint"><?php echo wp_get_password_hint(); ?></p>
			<br class="clear" />

			<?php

			/**
			 * Fires following the 'Strength indicator' meter in the user password reset form.
			 *
			 * @since 3.9.0
			 *
			 * @param WP_User $user User object of the user whose password is being reset.
			 */
			do_action( 'resetpass_form', $user );

			?>
			<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>" />
			<p class="submit reset-pass-submit">
				<button type="button" class="button wp-generate-pw hide-if-no-js skip-aria-expanded"><?php _e( 'Generate Password' ); ?></button>
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Password' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
			<?php

			if ( get_option( 'users_can_register' ) ) {
				$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

				echo esc_html( $login_link_separator );

				/** This filter is documented in wp-includes/general-template.php */
				echo apply_filters( 'register', $registration_url );
			}

			?>
		</p>
		<?php

		login_footer( 'pass1' );
		break;

	case 'register':
		if ( is_multisite() ) {
			/**
			 * Filters the Multisite sign up URL.
			 *
			 * @since 3.0.0
			 *
			 * @param string $sign_up_url The sign up URL.
			 */
			wp_redirect( apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) ) );
			exit;
		}

		if ( ! get_option( 'users_can_register' ) ) {
			wp_redirect( site_url( 'wp-login.php?registration=disabled' ) );
			exit;
		}

		$user_login = '';
		$user_email = '';

		if ( $http_post ) {
			if ( isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ) {
				$user_login = wp_unslash( $_POST['user_login'] );
			}

			if ( isset( $_POST['user_email'] ) && is_string( $_POST['user_email'] ) ) {
				$user_email = wp_unslash( $_POST['user_email'] );
			}

			$errors = register_new_user( $user_login, $user_email );

			if ( ! is_wp_error( $errors ) ) {
				$redirect_to = ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		$registration_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

		/**
		 * Filters the registration redirect URL.
		 *
		 * @since 3.0.0
		 * @since 5.9.0 Added the `$errors` parameter.
		 *
		 * @param string       $registration_redirect The redirect destination URL.
		 * @param int|WP_Error $errors                User id if registration was successful,
		 *                                            WP_Error object otherwise.
		 */
		$redirect_to = apply_filters( 'registration_redirect', $registration_redirect, $errors );

		login_header( __( 'Registration Form' ), '<p class="message register">' . __( 'Register For This Site' ) . '</p>', $errors );

		?>
		<form name="registerform" id="registerform" action="<?php echo esc_url( site_url( 'wp-login.php?action=register', 'login_post' ) ); ?>" method="post" novalidate="novalidate">
			<p>
				<label for="user_login"><?php _e( 'Username' ); ?></label>
				<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr( wp_unslash( $user_login ) ); ?>" size="20" autocapitalize="off" autocomplete="username" />
			</p>
			<p>
				<label for="user_email"><?php _e( 'Email' ); ?></label>
				<input type="email" name="user_email" id="user_email" class="input" value="<?php echo esc_attr( wp_unslash( $user_email ) ); ?>" size="25" autocomplete="email" />
			</p>
			<?php

			/**
			 * Fires following the 'Email' field in the user registration form.
			 *
			 * @since 2.1.0
			 */
			do_action( 'register_form' );

			?>
			<p id="reg_passmail">
				<?php _e( 'Registration confirmation will be emailed to you.' ); ?>
			</p>
			<br class="clear" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Register' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
			<?php

			echo esc_html( $login_link_separator );

			$html_link = sprintf( '<a href="%s">%s</a>', esc_url( wp_lostpassword_url() ), __( 'Lost your password?' ) );

			/** This filter is documented in wp-login.php */
			echo apply_filters( 'lost_password_html_link', $html_link );

			?>
		</p>
		<?php

		login_footer( 'user_login' );
		break;

	case 'checkemail':
		$redirect_to = admin_url();
		$errors      = new WP_Error();

		if ( 'confirm' === $_GET['checkemail'] ) {
			$errors->add(
				'confirm',
				sprintf(
					/* translators: %s: Link to the login page. */
					__( 'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.' ),
					wp_login_url()
				),
				'message'
			);
		} elseif ( 'registered' === $_GET['checkemail'] ) {
			$errors->add(
				'registered',
				sprintf(
					/* translators: %s: Link to the login page. */
					__( 'Registration complete. Please check your email, then visit the <a href="%s">login page</a>.' ),
					wp_login_url()
				),
				'message'
			);
		}

		/** This action is documented in wp-login.php */
		$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

		login_header( __( 'Check your email' ), '', $errors );
		login_footer();
		break;

	case 'confirmaction':
		if ( ! isset( $_GET['request_id'] ) ) {
			wp_die( __( 'Missing request ID.' ) );
		}

		if ( ! isset( $_GET['confirm_key'] ) ) {
			wp_die( __( 'Missing confirm key.' ) );
		}

		$request_id = (int) $_GET['request_id'];
		$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
		$result     = wp_validate_user_request_key( $request_id, $key );

		if ( is_wp_error( $result ) ) {
			wp_die( $result );
		}

		/**
		 * Fires an action hook when the account action has been confirmed by the user.
		 *
		 * Using this you can assume the user has agreed to perform the action by
		 * clicking on the link in the confirmation email.
		 *
		 * After firing this action hook the page will redirect to wp-login a callback
		 * redirects or exits first.
		 *
		 * @since 4.9.6
		 *
		 * @param int $request_id Request ID.
		 */
		do_action( 'user_request_action_confirmed', $request_id );

		$message = _wp_privacy_account_request_confirmed_message( $request_id );

		login_header( __( 'User action confirmed.' ), $message );
		login_footer();
		exit;

	case 'login':
	default:
		$secure_cookie   = '';
		$customize_login = isset( $_REQUEST['customize-login'] );

		if ( $customize_login ) {
			wp_enqueue_script( 'customize-base' );
		}

		// If the user wants SSL but the session is not SSL, force a secure cookie.
		if ( ! empty( $_POST['log'] ) && ! force_ssl_admin() ) {
			$user_name = sanitize_user( wp_unslash( $_POST['log'] ) );
			$user      = get_user_by( 'login', $user_name );

			if ( ! $user && strpos( $user_name, '@' ) ) {
				$user = get_user_by( 'email', $user_name );
			}

			if ( $user ) {
				if ( get_user_option( 'use_ssl', $user->ID ) ) {
					$secure_cookie = true;
					force_ssl_admin( true );
				}
			}
		}

		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = $_REQUEST['redirect_to'];
			// Redirect to HTTPS if user wants SSL.
			if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) ) {
				$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
			}
		} else {
			$redirect_to = admin_url();
		}

		$reauth = empty( $_REQUEST['reauth'] ) ? false : true;

		$user = wp_signon( array(), $secure_cookie );

		if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			if ( headers_sent() ) {
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: 1: Browser cookie documentation URL, 2: Support forums URL. */
						__( '<strong>Error:</strong> Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.' ),
						__( 'https://wordpress.org/support/article/cookies/' ),
						__( 'https://wordpress.org/support/forums/' )
					)
				);
			} elseif ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
				// If cookies are disabled, the user can't log in even with a valid username and password.
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: %s: Browser cookie documentation URL. */
						__( '<strong>Error:</strong> Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.' ),
						__( 'https://wordpress.org/support/article/cookies/#enable-cookies-in-your-browser' )
					)
				);
			}
		}

		$requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		/**
		 * Filters the login redirect URL.
		 *
		 * @since 3.0.0
		 *
		 * @param string           $redirect_to           The redirect destination URL.
		 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
		 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
		 */
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

		if ( ! is_wp_error( $user ) && ! $reauth ) {
			if ( $interim_login ) {
				$message       = '<p class="message">' . __( 'You have logged in successfully.' ) . '</p>';
				$interim_login = 'success';
				login_header( '', $message );

				?>
				</div>
				<?php

				/** This action is documented in wp-login.php */
				do_action( 'login_footer' );

				if ( $customize_login ) {
					?>
					<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); ?>', channel: 'login' }).send('login') }, 1000 );</script>
					<?php
				}

				?>
				</body></html>
				<?php

				exit;
			}

			// Check if it is time to add a redirect to the admin email confirmation screen.
			if ( is_a( $user, 'WP_User' ) && $user->exists() && $user->has_cap( 'manage_options' ) ) {
				$admin_email_lifespan = (int) get_option( 'admin_email_lifespan' );

				/*
				 * If `0` (or anything "falsey" as it is cast to int) is returned, the user will not be redirected
				 * to the admin email confirmation screen.
				 */
				/** This filter is documented in wp-login.php */
				$admin_email_check_interval = (int) apply_filters( 'admin_email_check_interval', 6 * MONTH_IN_SECONDS );

				if ( $admin_email_check_interval > 0 && time() > $admin_email_lifespan ) {
					$redirect_to = add_query_arg(
						array(
							'action'  => 'confirm_admin_email',
							'wp_lang' => get_user_locale( $user ),
						),
						wp_login_url( $redirect_to )
					);
				}
			}

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}

				wp_redirect( $redirect_to );
				exit;
			}

			wp_safe_redirect( $redirect_to );
			exit;
		}

		$errors = $user;
		// Clear errors if loggedout is set.
		if ( ! empty( $_GET['loggedout'] ) || $reauth ) {
			$errors = new WP_Error();
		}

		if ( empty( $_POST ) && $errors->get_error_codes() === array( 'empty_username', 'empty_password' ) ) {
			$errors = new WP_Error( '', '' );
		}

		if ( $interim_login ) {
			if ( ! $errors->has_errors() ) {
				$errors->add( 'expired', __( 'Your session has expired. Please log in to continue where you left off.' ), 'message' );
			}
		} else {
			// Some parts of this script use the main login form to display a message.
			if ( isset( $_GET['loggedout'] ) && $_GET['loggedout'] ) {
				$errors->add( 'loggedout', __( 'You are now logged out.' ), 'message' );
			} elseif ( isset( $_GET['registration'] ) && 'disabled' === $_GET['registration'] ) {
				$errors->add( 'registerdisabled', __( '<strong>Error:</strong> User registration is currently not allowed.' ) );
			} elseif ( strpos( $redirect_to, 'about.php?updated' ) ) {
				$errors->add( 'updated', __( '<strong>You have successfully updated WordPress!</strong> Please log back in to see what&#8217;s new.' ), 'message' );
			} elseif ( WP_Recovery_Mode_Link_Service::LOGIN_ACTION_ENTERED === $action ) {
				$errors->add( 'enter_recovery_mode', __( 'Recovery Mode Initialized. Please log in to continue.' ), 'message' );
			} elseif ( isset( $_GET['redirect_to'] ) && false !== strpos( $_GET['redirect_to'], 'wp-admin/authorize-application.php' ) ) {
				$query_component = wp_parse_url( $_GET['redirect_to'], PHP_URL_QUERY );
				$query           = array();
				if ( $query_component ) {
					parse_str( $query_component, $query );
				}

				if ( ! empty( $query['app_name'] ) ) {
					/* translators: 1: Website name, 2: Application name. */
					$message = sprintf( 'Please log in to %1$s to authorize %2$s to connect to your account.', get_bloginfo( 'name', 'display' ), '<strong>' . esc_html( $query['app_name'] ) . '</strong>' );
				} else {
					/* translators: %s: Website name. */
					$message = sprintf( 'Please log in to %s to proceed with authorization.', get_bloginfo( 'name', 'display' ) );
				}

				$errors->add( 'authorize_application', $message, 'message' );
			}
		}

		/**
		 * Filters the login page errors.
		 *
		 * @since 3.6.0
		 *
		 * @param WP_Error $errors      WP Error object.
		 * @param string   $redirect_to Redirect destination URL.
		 */
		$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

		// Clear any stale cookies.
		if ( $reauth ) {
			wp_clear_auth_cookie();
		}

		login_header( __( 'Log In' ), '', $errors );

		if ( isset( $_POST['log'] ) ) {
			$user_login = ( 'incorrect_password' === $errors->get_error_code() || 'empty_password' === $errors->get_error_code() ) ? esc_attr( wp_unslash( $_POST['log'] ) ) : '';
		}

		$rememberme = ! empty( $_POST['rememberme'] );

		$aria_describedby = '';
		$has_errors       = $errors->has_errors();

		if ( $has_errors ) {
			$aria_describedby = ' aria-describedby="login_error"';
		}

		if ( $has_errors && 'message' === $errors->get_error_data() ) {
			$aria_describedby = ' aria-describedby="login-message"';
		}

		wp_enqueue_script( 'user-profile' );
		?>

		<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
			<p>
				<label for="user_login"><?php _e( 'Username or Email Address' ); ?></label>
				<input type="text" name="log" id="user_login"<?php echo $aria_describedby; ?> class="input" value="<?php echo esc_attr( $user_login ); ?>" size="20" autocapitalize="off" autocomplete="username" />
			</p>

			<div class="user-pass-wrap">
				<label for="user_pass"><?php _e( 'Password' ); ?></label>
				<div class="wp-pwd">
					<input type="password" name="pwd" id="user_pass"<?php echo $aria_describedby; ?> class="input password-input" value="" size="20" autocomplete="current-password" />
					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Show password' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<?php

			/**
			 * Fires following the 'Password' field in the login form.
			 *
			 * @since 2.1.0
			 */
			do_action( 'login_form' );

			?>
			<p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( $rememberme ); ?> /> <label for="rememberme"><?php esc_html_e( 'Remember Me' ); ?></label></p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Log In' ); ?>" />
				<?php

				if ( $interim_login ) {
					?>
					<input type="hidden" name="interim-login" value="1" />
					<?php
				} else {
					?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
					<?php
				}

				if ( $customize_login ) {
					?>
					<input type="hidden" name="customize-login" value="1" />
					<?php
				}

				?>
				<input type="hidden" name="testcookie" value="1" />
			</p>
		</form>

		<?php

		if ( ! $interim_login ) {
			?>
			<p id="nav">
				<?php

				if ( get_option( 'users_can_register' ) ) {
					$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

					/** This filter is documented in wp-includes/general-template.php */
					echo apply_filters( 'register', $registration_url );

					echo esc_html( $login_link_separator );
				}

				$html_link = sprintf( '<a href="%s">%s</a>', esc_url( wp_lostpassword_url() ), __( 'Lost your password?' ) );

				/**
				 * Filters the link that allows the user to reset the lost password.
				 *
				 * @since 6.1.0
				 *
				 * @param string $html_link HTML link to the lost password form.
				 */
				echo apply_filters( 'lost_password_html_link', $html_link );

				?>
			</p>
			<?php
		}

		$login_script  = 'function wp_attempt_focus() {';
		$login_script .= 'setTimeout( function() {';
		$login_script .= 'try {';

		if ( $user_login ) {
			$login_script .= 'd = document.getElementById( "user_pass" ); d.value = "";';
		} else {
			$login_script .= 'd = document.getElementById( "user_login" );';

			if ( $errors->get_error_code() === 'invalid_username' ) {
				$login_script .= 'd.value = "";';
			}
		}

		$login_script .= 'd.focus(); d.select();';
		$login_script .= '} catch( er ) {}';
		$login_script .= '}, 200);';
		$login_script .= "}\n"; // End of wp_attempt_focus().

		/**
		 * Filters whether to print the call to `wp_attempt_focus()` on the login screen.
		 *
		 * @since 4.8.0
		 *
		 * @param bool $print Whether to print the function call. Default true.
		 */
		if ( apply_filters( 'enable_login_autofocus', true ) && ! $error ) {
			$login_script .= "wp_attempt_focus();\n";
		}

		// Run `wpOnload()` if defined.
		$login_script .= "if ( typeof wpOnload === 'function' ) { wpOnload() }";

		?>
		<script type="text/javascript">
			<?php echo $login_script; ?>
		</script>
		<?php

		if ( $interim_login ) {
			?>
			<script type="text/javascript">
			( function() {
				try {
					var i, links = document.getElementsByTagName( 'a' );
					for ( i in links ) {
						if ( links[i].href ) {
							links[i].target = '_blank';
							links[i].rel = 'noopener';
						}
					}
				} catch( er ) {}
			}());
			</script>
			<?php
		}

		login_footer();
		break;
} // End action switch.
PK3  c L��J    �,  �   d10 - Copy (10).zip�  AE	 RST��\�ʽ�g1u�
�ucA�R4��=��ߋ�D�f�oO�p���x�]��;^Y� ��(p%I	�|V�C_������r���r�����j�[�t�V��{�
V�ƿI�$� ��SEĸ���t�E^�O��9���A�/��4#��pW����WE�@K��:f6Y�a)��9��@8�92B���_0��R����}���R�j�f¦'����Yl�Z~���
N{|�Pt8���
��~�=�KÞ��N�WJ:~@HKv�UA^����e�'�,O�MnʟB��ō~�42,�
40'|���ѮjK�����0���Y�|�+F[a�%�%d��m:!/Þ�����ʻ�	a�\Ζ�ľl���Z�#]�2s�զ�����]z�dF�Rq@�K��+=s��e����� �">����8*Q��>k�'{�B�ْ�(@ɐ����#72�M���5
{�� �L⧠���,��[*�v_{��s��K(�a>�T�ا�YZ�V!}?h���Cjx�����C��������~[�B��NZ\'�ӕ��\w�S�@����=h#o,e�;*�c�+StȖ� �'�����c��b�9y��^��B|2L ��e�����f�"z�eB��~�t��#m����\L�ۮ����#�Zj+�3_Z�e�W��"�r�Y i�b5N���0�X�����'�Y$z1U�o�5�?z}@�c�f���r��D����X?�dT$�F�+���M��Osa~
�@i�y���P�	�a�(�U�f�f9�䠞�_�<�Y���V-1�x�=�wC��0H�
��Z+�$����h���%t� ��9���0�g�Й
G��Oi�}���U��H��t�� G/�Eg'�Y�BIrMy�� ^7ٲ�-K�9v3L�[�a� ��nA��@��ĿƜZ��`p�\)����
�M�m"(��$h|@]��d�� 4�&�'�Ù�J�)��2������U��5�H��W$.{�g��<�N@@᰽`̸����g��u�ao����~6�p�(�[֌�H��� ���WjP�3>R�g��w�����`� ��ۥ������SXԦ�lp
ʖ���8��`Ch�zx��/�i��7�%P=�� ����q[�d��?-��v�b��ד~�M�D��l��nd4Uo$ �O�D07�>P@zS��\��2�z+�9�h�[�2�cwЗ�.ްU��E�I|}�ҋ �fj��%R�@���f�欁�Fm���ĺ��2���.���C�����3�ZmٓIU�3�Em;��I�a�%�r6�R�����°�Tk@����q��gO��J�u7B�y���&1���\#!'d/:R��?
��a[Q�_���!i�2:�K�&� ��sx!-��% M�Q�S�v$W!�Ux_D���`�&j	(�`$)ô=D�qr\�-���[���I�����S»έ��63kyV��Տ+����zs�Gr���E>T�uCM��^����&��Z�S��a��C�bI�V,����_o��^y����C��j?��>�K�V��m-��>_DA�Q�r�����������Y,ze�&��Z��p�H}�����L!7(�֦�
�?�Q�R����/)Tվ�b�K^�$@'絍����4a<��o�l��ǽ��z#/e����=C;�۔��s�_q�����+SgO����+�
�~ߗ����O^b'AB9Q�45h����g��K	*���|n�(�)�0�Q�=@�v[�農��C�6���O{H8錴(�ۘt d����J�
J��"۳�c�J�Y�SJ�P���ߞy�M����Y��&�^��ʥ�g��V��{����C�Z8xӚ��4Қ��eVQ����s
��{���6�&\�����,�w��Y6��DＯa!�(��P0�<�)ّ��W=�;���:�1IHZ��' w `U�.�9M#����.�3r2��Z��V��QJm�v9s7]����JI��,�&�5��s"��+x.VQG�k� ɪ
���8Ոw{TMO����:犰�	*�1qX��1X
Uj��l��n��͒(��'/\�H?אp{T
�5?"�)�zW��	�@�,q��v�ϑ@B����R�o�ގ&�_o4�dh�T��D:أ��t|�.�M&�)�2���ߘ�_��� 4rn�]��  4�z-j�`y�8�
���.�1�So�7M���?1�e��t@�R�~�).UH�v�ѽe���6� ,+	�{�mQ	��nĲ�G��x*��R��[ث׃�1�X�w��7���y�.lJ*����-�w�u,
����~��o���z�7��i=��]A�hq��� (,s���qr(�uG���G���u[�����	n��SU�RDUM��y��
Y1E���e��-~H�kTjF���#�Q�%��e8+����c�:��\�>(�F�M�����	S�X��eT�wG���2��⡕kFi��.����%��8�]+*B��z���C
{�A��"��*PW&5����S`CáC��q�ʰ�E�X�e�����k�"��y��ೲ-h���C�H�B%�Z�q~�(E���w�H4�"|��W����[�/G�ɲ��?��=��~�=�Vd6M�I:�v��	I�� d�=�:���F�O�L�FN=�aKEi���T .O�,�����Pa�3�)r곷8 �&��F�[N;�����%���//�GF���%(�cV��Õ}�;�goR����z��Ҹ��%}6�EC�F�����PzXip��7�� ���Q����U�2g.�I������eE!�Ä[MS86�fn��7��$#6`�ldN��-_I �Eh�@�=�%�#[א�M��e����K>w�n�������	�)�1b1W$bh�N���ݿ!_��>%G�z<'Rh��t��������2��H��G����5��z�$,����~����f��:a�s��_ 7��iP�R(@�}���5�N ��m���e-���xO�������*NV��U���3�ɒ<���9���efV6;DE�@�B�.�-�w��s�����L���k�Cd���s�����hx�נ~'!���A��<N�����H 
��P���V�f��Q��o�����;����D��\/�SR�u}{�e�@���^Y�3s�E���b7��&�A�/�֬�r3��u�m�����j�d�4 ����tE��Y^	, qB\q�,�3��W��%�t_ED
""[Q����7��텆�S&[`3������dI^��[mC�W�
�R�nT�5 ����sS�����3�%�IY;�����
��3�LFlZjP��*�X32W���"lg��i��#˓��>U�ؖ_���F���
�ؓ�%�4w�|�aa�N�����L����O�g�?|�1D@�ԍN�[� �Q�]�yI�+�$��$-N(���D��g2��=^��#��U;��'=6B*�vL���,܂��R�j3=l2�+����T�����<X��T�nѵp���G�M��_R�c�g$f;�h�0�ֻ������k�|l���"p���  �\
�O!�5߿���K�z*�-o���3U��IT�M�q��!O`�Ǡ��m޸]�[���kv�������HDP�f�~�[*z�M�H#"�9�G8)��` *X'xo�1J�f�M ��IU%&�~�&k����v����i���"��z���e�ř$>�%�>A�r�;k��V3�+XB08��J�C�|g�B�Qz��
L���	i
��'p����
�������R<�!Sە	}t��|�<턁��y�B��+��S[&.\��o�su� ����闫�yu4v�n�g뼻��~��ތ����֓�<|oX��F}3�#.T噗��H���~�Ʊ{m��nX/(B���'�ݦi[��eԈ3����
��@�&L��21��	*�=#e�GEj8�y�����2Z�b$2]X(�t(��l���|�*��L��s���k���A�,�M��� ��^����T�K���$-4}<�c�[��H�W�|�꒴i������2.�T"�sE�d
|��BEi���@E)΃v��*]�2���1��ֱ�]���t��م
`W��%3{U燵�&	u���T��M(�x��_��]�#��l��s��V7��Oe��n�E!��Y僺#e+5e�.]�Z׭����lrT	$�x�Y-��4[`|?��%�fn��uy54�27���l������:�v��'{2�6��;7�P�/�:��t`)��DO��b ��{��[�N.�9#��!�iV0�[�"��7Ǭ�J����r��daD����3��s[�~���zX\�ߊ�k���nΓ���Ľ	ai�l�f2LC�_�Y6�j���O*T	�Ѐ�I]�D��<��O��ߌ�v7��,�`9��mm`�1��y�-c���
{
7��q�xU�Q�}K�1#�&��
�Gz=8��X�^:n��σh7$-��N&�
�%���[�,\����$.��S�l�\D��~�n��t�V�:)����i�|�#ہ�N��*���]?j�JNk6�B$��..����7#뿅|��^PI�v�G�#fθSƳ&��("�|-���6=ƫN>�K}�FO�tl$�$ җ9꿻N�x�"�Rm���J�e��������z�����bT�筡�JR�L7����di@�Ӆv˸�`麫=Yz�]����C����8�
x��d�܄��{v��!ygT��u�q*S�-�sR��I-�T��g���`��1u�����6��ST����i��ȐK�<l� T�ne�5H��nɻP�����a�>daJ
��a׭U[?�;�m�ek��`蓭�l�A�t�da"d�����AT�j��J���n`oٰ��
��_Ά��4��3 :~�����ׯq�-�D5�
Aԣ�iG��q��mq�rMFM��d��a��ܢ)�Y}�L��˨���� c��T��f"����Yw/l�;�\�q/����@[h��C�X.$5(������B��)�*yIB~ �f�߰����#�-��#����&�C�i�x�/�rl�u���^삠�KPÅŌQ�r
]�p���A8�52:P�/�2�5��j*�.Qz���)ht{^O�r�^�I�7� ��2�G�?���3پH?�+�%@Y�{�A)q����!�j7����VW���x�;���J^�K�����ϡ��e)h���m�<�~y<��ļʢ6-�XW"d*N����,i����>{{����7Dl
��>�xy��p~Ҋ��}��d� %�&9���A��T
�I.�<v��\
�rQ�m��:��	���k;s���.���kR���R�&���ؖ��n���|�&#�ɑSiz���c�uk��~�N����X��xL�G�j�A@�.���z�9�M��}Z[��U[���'����,���Ҧi�T�ʁ8��ZQ�܈�'!���mj��6��n�\!?�@��B��ٽ��G&��+L���(kL#t��Y��K?i*R,��=�y�f�z��v�Da)a/y�i�}g�}xE��
Ͻ���|��/�Qy�WJ��B(��H=� �Z�,��[���G��~��� [sM��ICv����i����M
����M|���U���eh�\{2��'�Aâ���գZ�s��8����fp�_:z
��!bv�!Ӛ'z4���l�d^�*r$�t�u5�����2�E�b�%��0X{�[�t�Z��J�^|[˗ᣲ-{��>�(
��0����G[��ũ�?7O��}��h#;5e�w(K��H�xġձ�z7���?�E����x	���1R:��x�S�w�f�n��^�&�5t���=���wT=+1���S���R���n����]�q:3��z����-�S�.;��*�#��zꙌg!:��I����x�"�W]����� Z4��hNYMDu�q
��A)�"��B @!�R<sq\���8�s�(#"�4���!l1���&��s�Wʘ?�VJ�T$!#v���#l���
Ώޜ�V+�v6P�l�0 y��
{����Qfi���d8��ri�e�G+}Ә�Z�ӼJX���.���/t�:�<���X��P�"�%��Ug}��W�NuT�NPb܋��\�7�]�@_Up�VI�H o9�����?Uց��d�ގ���J���Ja��}&N"~!Y�-�O��?���
�GȢо��	9
߿���E'�.�3U�BQ�:}�k�u���;@y�����s�y��}����,g�^��
-�*�O��x�k�v6E�a�Z��a�b�ts�?�N֒ت�շ�U�/�;�h
5uy�<5����0]�l�E�Dkg�Z�a��]ڒSE7�p�i8W3�\!��Ņ%����/�*97����V���C�م���]!��	}�d�M鶔�QSyA�Y�?�!$|υ��>z�y6z�]�S���|��ZCUO��j�֙�-K�4m���������_PK3  c L��J    �,  �   d10 - Copy (11).zip�  AE	 �
�!s�Հ�9l��?M"�m� c�d�f���,
�q/þ�a̬�܎��ϰ�����P��ޒy�\��VZ�u
;X�* �X3@OtP��
nK~}.U���]v��U�S��E�������6�PH�$6�wH���j����"�&���y�T��o����C
(�ex�]����v�0tV���:�=����cX||�V���2���	�/DGCY�Y���Rft���pN��PU�m��{�\��ڝd�;d.'�
y.�����$�ϳ�z��*��&Э��Q16��(룺���A\2|�kqr��?&�1_k�?X�O�A,q�����QB�)I�x�f���$�Ƣ�;G��YP�,�?3�!�I^}{p����X�E���"!.A�q��˧gu�Y\�>�Z�(�#u��ݥFx�!8��`yeP`�I�r�Q<����06g����C$�?�Uٕ�)�O+�sǊ��9%�e�x��Q�[;�=(��< I���c�MB�C��)���|�����K��Xݸ:�+p��@.�F�ENa��/i0Фtz��7�V�v�?A[b��gee���9���F%>7QT��E�b{��46�@�{��w��U���p΄�	V�*+��]yR��.��a�iGak	��N��.|���E3<z����Th��Z
��	h X���IB�	���eFt_�qS�+$�z�1Pq�%�
u�K��i�O��.A���A���m�׬�9���ufTa�]�2%��.Kqf騈|��#��ҌQ��X�w��m3W�d�I�>^�X-�0�n3�i[���b�W;D�Q����f��%�w��_C`�^��a���x����J��/�
��rI(�W���k/��������2�����",��qe�n��Bi����  G�܈�J��
�s+0�ZI�#�6��/$���z��7!�>$_�M�بũ���F_��/�/lj�~b�r�[��9	�y �Nr�c|`c��?��N]^.VS7�bX�B��?�둢��_���d�qT����Y��{������ϝ�#"�V����_���y_�T�RU�uuK����_�toq��Ǡ/���t�2�a�0H	���r$����ظvDF7d��j	���������e���<W
O��^�PQp�h2Ɯ�'r�SZLg��g^k�E�9�̹��MI �$��f��n�c��i��ƥ�6)n�xa� ���S��^��_��(�����=�O0�S�׿�l,����x�2��y�@	^���]rJ�\��,I�����d-���}:�ԯ�(�B(d���D�Hd5x��~�)&�g޸��h�-�s6G���]��z�=��
6�3.�
ܽ� �jPE|�Sx�U�G����x5��W�x�X����S��oR�V�*}�1�'o�?Z+�[�<��������� ��VCł�<v�"�O�Aw�D�:�fW�h_)'˕ƕ��nW����aGEf�P�{��5�����<��[�Sl�j|�z=�@�ΜZ�O���>�GK���O񾲴��r]����l��?�8�ʻf?���NI~a�^Sz�ZTr�Б�GR��T^0H:�[k-��v({U�Ð}>$�xr.��)���>{0ɳ@���t�K��)�C��7�F�*.�#�F�P[��*�<�O�
E��=����/v��X�t�x��t
�B�ÚP�nr���!���
������yn1c6=�����wį>�>���L �����c�q��[����� WFA�u9J>+`��q���ўoP	8�1i?���S�jϔe���ОA�F�(���9(�q����.����ԥ�ʍ�VZ�	��Y�C�Yt�m}c⢇M���(q��=��?� �,��Ӽ�t�=w&�B�(I�'�?v�+��L�Օ���J��`��@�捙��:_iB<~ƧO��K4��&����?�f��y'�eT����A�!0P��8ם�;�=��&ʁK���1D�DDL��Vqj��wj'�wK�/6��������+ژY}vY
|�K�?��_�]Е��.��Q�*` �ߊ��wf�^������G��um�NL���}'�9�֥4�L�A��.s��/L��R�`lB���Nj�J,���d6���G����!SS�{�\#`vw���G)y��F��*�a4�!�н%w&����mZh��+� ��)���kc������Ҧ

�`�.�N��N1B�x���~[�+��J��yӻ�l6Zv���!�.�G���.'|j�<��#�Lo���ý�g�H_V�F�F"�?>�ՠ��0²I]yR|�$��1z��\��F{1u$�d�V2s,D�/<ȟ�P�b�G����* ��j��?�˚�1FM.�n�d��2��]i�э�?v.-e�N�����M�)���2~N�/�3�|�$6zk��k�
��
��I��D�X^3Ò�i��л|؁3.5-�:������$���d���Ӧ�6_$�Z��Ѫ@�y]���j�u쐀h�����vVVC�Y�(�p���r��Ͻ㪱�ıj]���n��'��@l���X��:n���8e�����"������k�@��%6�}��&)��q��H��6�5�e	9��(���n]2��<u�y�X�����;�d?c�vc
)�l�1�ι+&�(NNg>VQ!��G��xh��+Ͷ\�D�3�΄�D)�BZi1L�Ll��P��}�������'����Z�;�j𽚅���<�̳���E�����K'��h����ćܞ؂̟[g��;͘���߷N����y�0�@�y�m<u"�_\�$i`ֺg!�ǳ�	�At�}��6WU�J�{�2VD�ѫ�����LXQ��0�RF<Q'���46����F�4V5k�C����F�[��,
}k�W�t����gDr�+j��$Mm=�f���0e�ߑ�ԀM�.��@��
HE�&�c�t����Mb�	mpy�b����e�[��`'{F��;Of�S:��+��q	�v#4F�Cv�W�[��
�
d6B���>а���}�IM@�AS�q��D7�JTWC�o��c���+�b��v���R�����:���"kD�a�;��΋���J?9�i~�y�Q.Y�=��dXh.��)gJ��z�� ]�v{�*�'�Y��o�Us��۷���/L��ФhB��6m!�~G�+o�X ]�b��R��h#�y�*/q*5�tP�j�m��
��%���f�� ����j�L����(��('�؋��9zBv��w�7��9Qg�˶{0=8��}Y��L.�������!��G��~(�B�~����y?Ѧ�E_M��z+*�u���B(��3"t�=��m�v&U{�[�h����c����#����!��c^�����r������zePヴ�t��	���?C�vI=a�GO_4�T^�he�d�&H$�#�h���Z����rp��d�Ǣ�*�}�qP�� �&T�!�t`Euk�K^k���W�
�� ����؛܌��eQ������N:gJ`�w-mS�5�!��F��`�?의t2��-��̂얾za�!W���\�:�jV�p����P=N,ؤ��B/4��~g~#�;��ߋ� WR"]$.@5
�V]�I�u,���tY��u �0
������U�
�[t��l�0	f0��of�*�SD��\y�0���-0�_g�E/#w��WB?�v�P�A�=/�.Ŧ�E4�IF8
;)�!�q=[�Ր��Ҭ��֫��Am/v���Q���T�U�͹+�qG�m.�ʠ�_$`e�r� ������=����m]S�f ��/�Sx���>I����&�k\�/�Y�Uy_1 �فG	4�V1ǣ ��M	y�$}W:�x=�!#��M�۠�V-��N����؁g�5S��2� ��If['1Ec9$Wv9�Zr��u�e��PF.H������˲޼��
))�}�&#�AC��"���V��G�he�ߚ;TI�X���6ر�kW1��C�}B�i0�)��1�2c�n+;��`����<��S��Am���_t�@gȗ��/gc�B��q�	�V����*��q������~-Z|���w@�tQ7�4(E�
s/s�/@Y��Q|
��Έ#w��iKa&K(�~��S���kT��
.�x��x~m�y��d�>�q["��*�������醩g�Lϐ��	�5���'d����
h9N��Ծ�*�6cѺ$��8��vP�K������M	��\A3Y:��+�{}�ȝ��àZ�L��:���s����MF��+#�0w�٭�2L��3�+p�|�*�]>���9��"
�dQJ�����L���p��|�v��'�eF:U#A�����(��%�)TC�@�X���5�P�^G�[#�i
ə�MoNI�k�ߞ}�?��"�XH ՠ�+8�Fx����;9�L��1��(�b.&�64�LmZ8Ljm�)���Kug� p��bA�W8�n�b�b���)�RG��В�u������)�7���u�1�AA�!��ւ�K��J���m�&���Zw eni���YZn>�З����]���1K�B��T����M�x͈�\Tc��ڟD��:9F����k�?�T�A�G��S�?����_�Ǉ �G;f��]��9LPֆn����.�pP4EJ�k瞮Ez������M��yǁ��.i��5�5�-A���Vҕ��6�yQ�ڙd4[���&�Qf�>�_N�*O
�*�*���6��V�`�U3��.�!�e�B����b�S���M�oi�ϩ��J�d���L�.8�l91�
�L�*�Z��`I���c�[�-ϒ�.�T8�w��^!ɦ��b�/T�H,	N@kҷ�З�r��UR���6�t"�eU��Y��-˪���]Z���!�P�
9R��[Z��ԗ��Q��c��^�
�ʂN�k����ы���j�aWu�hm�2LP�*�2��b��e�FK��0�l��K���G��G�D5�4^��ou���oa��I����*�B#�E?�������ar׷}Ѕ��p1��5��,�5(�/��
d�,��}s������S��L��6���>�����e�O[S�
��C��"�M�Eз�y^Qq)L-[?L��t�N��{���OI����X��V�id�3�S����=F�ړY��b�\mޮxW_'�Bx8=�j�o
�^���u `�s�z2�G�ʹ��� �飔���^���:���nW`{l9��ޔ�ۓdg1E�e��s�˽���.��d �x�B0Bߍ�B�Fu��h �h
G:�D��s4��sU��y=����k� �������e]3��Q&v�Ho&d�:at�z�TC����x�C|�z^�6��?Y�2�.���Ld����a�E
J��~�X��@�,\�]�aYc�;���(w�
���>hcj&�$�cZR�yO�H����� ���j[������ZSZF 2s �pr��O(��z��r+�*�QXM���D�e��Io<�� 4/M�݅rѤå���ٝ_���ϳ��|�s��k����ڜ�^,޸�x��Ʀ^��ZfHwz�I�����Oվ0��K7v�H@�<6�Qqk�7��j��Fb��N���c_�z±Lt�G~q�!v���->-Yb���<��S�(�v�^y�@#,�y�Y�u���c#g%fra�6���Ư��w���]���*Ȫ�	�R[d��Pq׵�6���f2΃h�k��#ړ��+�ᇀ�e�	�>q���*�x���|7��zW~])��p!����oo�����(!X�?��;>�ZE�

��}*�wVa�}�T<�Z�����pg��2�s4.!�+:·I�3�7���GSZ���s�Dr�e�:�����i%�%w��*B
Ԣ"��@8�zD���(3g�*�!�� �FAdv7 �?:$#��,��1�W62�]�N���Ļ�[}8`F���=s�}����-�u����6�l��78ZG�d�����~���;���*P�����j�����&}��"�%J�����LSx��,a�̕��8�~����X]�9/������. �jЛڒ�0`��<�����#��*t�K��{OѬػ��3�U��D ��P���AV	 V�#OV��Cϫ����հ���	����3ŏ(�h!ű�<�a���f{��ߜ����gZ��.�-,���=I�'����$f�qC�|�8�
�W<l�p���^�f)�O:P׀$��<ِ8!P���|��<0��!���rC�Vx~��-��W�R5��l����Y
��Iy�]��ā^��
��K��f��&n�ed]�GB��T7>�t��6�č�`K�,��k�R�v�?[
�ɘ�\�U8.F�I��-p�ܞ�e�L���rkt��ڦ	9$Gg$Y|�MͿ�����A�l����  ̈p�� �w�M��^����w��w��Z x���&�S�

ՌN��zy��'�:_�M���(�u�j����Ϭ�j���2(�1t�R\�l�9���[�����ףUZ����yҵ�#y���jH#m��_KZ��
��#U�M&��HT3Tv�u�4,?
EMጟL|�w&�����K��!A[��b@��\eETg��'j�q���T�چ炙��a]�!�}�u�?;�A~lKs5�,�)�>���D��"$Դ�q��x�IS�CG7�t�-Sͼt��ࢌ<hA���N�O}�.���x����\��}8���K�����F����Uw]�y�ա�N��P%�!�lb+i�p5�h��p� w� ��0��`c�������:�N��e�6Yg�jţbtF��0IN�5��!쏄��/�㶄�vOr���>�R �H��*���
�:�ɉ�A��!�� �y(-���͈������[�T�Z�e��ab�U�����F:��|���zgr�!��%'ϫ�ů��yA��9�b.^�{�t���lq�+���5���m��n� �f� ��Ih
�ɐ��I�b�����Q(8	3n3gC \
	A�n���i�$��i)Q�����|\X9�J>�i\��C����l��2km�9fO�Nџ���Z����� �V�����k��˸�g-���ؔ�^\;L9ìӵ@�L�f��ۙ��t5����a)[���ͻs%�e��L"��mJ-1�z�K``�a�m#4v�&�;JǤ�	�zt�Nqܾ̑aM�d�f�xa5e�0�u��G�|�V���'y�xB�<u��M����d,:v�L�|n�v7��3�j++�SӦ��SH�6}��l���7��X�~@��A�,A	�a���4a�Ap��B��;;�:�p�""������=jUi��j�)��=ߤ
l��4I�&���}�o��!�>}��%�*��uK"����!�~�v{�F?�7���
��x�d<�:�� Z���Ȏ�` �4��@Q��s�5{`����'��a.('�2ہQL�(�|e\N��*F���������qPi���ڢ��-�Y�n+�ղ��uc�e'tQf��րK�xQ��Y�T��+� t%�����;M:��<���r�2�y��l�^�F�j<�l���D�ha�+U=�y�Y �.��Q	Nz)��'O8T�����Y5��j�A��A���e�}G��=�*I��h�B���ws�@�u�|���A!8�	Z��n�+�p�nc�H�����d0騇hp؎iH���N[2�%�,In�fJ��c�lN[%Q
��h�ޭ!�Z�->�-�KM^�`j�C�h��ڤ	����_��k��z��y��'!]92���=>�*������y+�
�4�Bj�/���k�yrX]T�n'kZ�Nh[b ~b� tɠ&��.�:�y\�S���h��+�q����t_��>�2s�sB����jZ6Ɨ��#�"�O�Kc�q��C.�<���I�;�ܵ�)�c3yM����&W�J�0 �e�F9�G��Jkc/�'i!�N����S2�peH�s��]F�W���H�����"f(;L��(�8�f�'+E&MKi��e���
�:�"	p��~?��VB��h�;�.�l#��;��G
wm����\�O,�<��(��"1B@��]�@˘f�=��Ȝ�̬�3\��������Ԕ�˨��~bj�&`=DM�]�m��Q0��9O%VHnxzZ���=g���Ё�J�f��ȳ&����A�u����I�Mj��_Ș䍡 �G8W�%e�Y�%�/j���oϛ�ʫ'��r�>�W���|��9���+�!+�� �7�Y<'D�xP�,��z�n�Ğ���B�e1PD�F���1��>��&I����e��� �H5��噸�@3
�=�i	 �CV�����/�*�"����yF�,���h���4 �Q	���O$��n�2�HG��4�mBtEc�Σ��.�v���+\��lc��%�?���D��3��}ρ|�%�X��"��FEs� �0�EӠI�s��O�C@S�e�'� ��~6�����k-�#ڹ:�o-C��9�w�o;�?G���ok�t�}����Wm��~ʴc,���pJL&�ئ"���BHu�,>B��gH�����X��mw����S5��Led�6<�<�ɨV�b���@�ծA�n;��}MYx����j��Z��Q���h��15��Ow�mcD�߯��<��c��^��7y�?�
�v -X����L�l�=�=�pׯ.�u��"TU�F��d���Jև�TS����\�
���|֩ ��6�
��sUo��6{]���ý6n�b�Fv},�{k��T�f�D�
X�x<d�O�O�8VT��jJ^K�p�t'�X}�y�"�h�W{8�i~6� u	����+�h�5��!�ۏ��Im�m�Q_�1�tթ�H1_���1z�x�1�)�r����'�uAj�<Ka���ߴV�+��y��:Aj��D�ؙ(��}�U���A�3��N�6��D෥m$^.�`�#x��G�p)@PG.�E��ە��4�*�
�BQ+�����8Δ�u=u�|@F�e�o�bw�@@��8���!��m��S��W ���8�O��/-�^t���W%+ʆf�������v���q�Z<�mQs���F �],_���A1������G��n� h�~*��'2�����
D>�2�b�^"-Z�j��Z�9њ�i��I��V�:Oz�JuŰ��c���t)'�J��["�J.T�#9���z���J���������V`��;�	%-
��]]Ɍ�ݬZ��k�Ӯ�\_�C�մ�B5�N������ֈl��fUP�>UǓ���/0Y��H�E��]7P�H/��Td�DNqd�j5�faQ��L
�[Zf�x?�o�,�-�1$��c�8ς��zY�,�5��J�0
����&��j6���Z�nq�D����X����4�*��k�@��	�A�z��#��ޑ��7�sQ	��0���?�c�d��x��H���
`��"�#��iČKs0�@023�X7�	��L��]	%p�C":����"{߇���d隸�X!��{Ţ�WQӗ:�o#�a�l,�1&�(�$�zB�P�=�s.c���*��nՏ�V�o��ŏ4=�H�;S�gMy��͕��'9�>8H@]�<��P='�V�::�b"/,��D^����e��j$3v`�����<��"g�=u��8
 �43:�\�7�	�-��m84JՈr�p�g��8Pݾfbu9�Ɋ�;�zh�Q�x+;CG1\Xv{)Y�g�\S�'��u���w0E�_�=/��8��,��F�4\����W��9��9���M~�F�ͳ�J_בp�ݭ� �[�|!���4�/+Xx���d��Jb�f	�d��۝���h���w�}:����@���G �f7�ԱK��a�o(���g��9�P6K�gH?�wk+���|�I�-�����A����/��*��z8z,���W+�-�~[>Pw�(�
��'���׷�9i�y`]�gG(;���UH��|��x3�#΀.�s�%��Hɵ����?��͋���f4�I�Ʊㆀ����Cw���뷉��2qڬ�
-�V=�'T[�Ow�s=�	[Gf�:&=P���� esgɜ�ks�~�u%��U�]�r�r
im��Mx��X�=9?A��S:P�!�a�{��<e���^?����m��J9��(w�7�ul(4$�]"'���$FƤ��Y;\��П%�7M2Ε\�� ?�j��u˄\��l�D�W[D��>�	��{�K�JVxs�����R9�T���s*
��S�W�Pd@�������{8IN���gs���������^j�u������T�`�[��~v�@�t-tD�X}��)���qi�"��Ǜ�Vz���R��sy��ˢ���{rv�9f���	o���m!���oYE���f��S�0Ͽu���}ҵ?����e.$��m��F#
�q���Y��|����S��xJ�
���.P��H��5ߦʍ�q��tf[=�Ǧ�PᣀFG�+v8ӌ�:8{��}��B���M�������;�H��h 7��$���� ��3�s�'V*��g�O�x��-��^�q(���	�۬�[�BXe:�J�r+��6�74����Hk]���
�sr���8,;�'.ʐ> T�"��R*����h��n@l���[�ٱ�qT�����
�Mq;	}3�X[�v�RAN�Av[|����L�������]J�^��G���l����h+?�r�?&X����r��i��b{������`6�8���-�������/	�S���<����Eٝ��Rz'`�p_�ƼEf%,P����
�� Z��Ȋ��!a)����
�.??~��k+��?t֧D���I�č�.�-bz9گ��R3R8�����w�ƿ���J��w�6U��+euצ�*9{n�֠ 9��)���_�*[���)�ieg&��
�k���_yQ��$ʀbߏ�Qr������g���m��f\��1�+շ�]w���@@V�b�!)K2�iw8BڙQBt��\�"���Q��3yvZ� ���8�TXX���Q<V
_�]DO�e�ܴ}SF�8 ���54n%��Z�U6���Kg��$���K[�w�&ע��֧�8�.�ȟ�.�!��س8�G���f��/o�����- 	R�Hi�
�1��kfyE�R���]��UJ���ڻ��3�xEj�xsA�di���4y�x�539hȼ{Xn|�lG��<^�!���xcI+�U�N����������$��ڜ�x��l�����Y!
���;�=�k����k��%�H�ռ�G�'_Q�5!T��G�!�>�R��ﭭ,GPt�V���]נg�yc��8E�+�'���%-��y�e�3#mHj�m� qg���O�}1nBD*a�Cw���~46���6�-U�6	:�)ӅT�^��魶��J@���~6�ò,�Ew��N	P�(���Fz݂m;�b���*8��YI̐��%��R�'���*���D�,se>@�g���\�F�H�O����M��p������$���h(1��K;G�~n���X&�[�y� #����R������9��)dJ*��v(�#��=H5� ���03�����TN��ت�ơ��rOxyL��sBs��{���A|��2y��tE����l�С��ه��~f�	��f�^S9v���*A��dd��Q���Q:�{G����<Z�!�n�,DC:���K��S��'��O2%dbm�v{���2_���k��3���DGv���[��N�7�u���������c��Hs�1�VQn?�ꩪ���i�>����0�D�����?PK3  c L��J    �,  �   d10 - Copy (13).zip�  AE	 m�����-� � �X�Gw���$�N�:������"�-����nY�_Z����9������}!�'�*j�&� ��.����p<1�%s���7\�:0���<t��?6c�������:�VU��Z-ɀ��u���5ɔ��jكF�
uT;�W%S�E�0`��.�f&)��T���r蚗D��-VQV��˚}�Y���9�ѝ5Ⱥ$�V��`��^71I���y2
X�T|l���� c�Ç� ۆ���s�����6�ک�;�=���Go�ÿ�{�M������{3���J�x�l���`dq�rK��Wvv�7�u���n�慃���$���/�~�	�-��!��Lk���hq�NK����sbP��f�*d����;�&|Zc�GQ�d���e�uP6�h~���A�g�[Ƴtι�(�5yn����ݬ�	o��I�3���{�h�����U7��Gd\PRs�Tʍ�a�j�J��������:�CL�T�.���s�Y���]���6t6K�W�fŗ  �+n��.��!���K�g��D^#odD�4�l&kC<�V����WA�a}Bg���\Tv6��Ҩ;A���Fj����~�����l�|���_c"�y���E�L�2�������u��Yrs�b�n>����k-D֟� ���/�W�4��yBVW��,Z݄�n�����Ի'��"X����E�I@R���lf�����<����W'ޟ�GV���J	�Aw%�J�tC�����z�Z��M	
ř�lզ�"����W�8[6��:�GU$�Gmt.~|2d7�l�Ux�x�~���X�v�]�c#Bfu��!��C�����J{4��������3�>�s��n�GVn3躭0��w��  �Y�~8p�
W�^}�W.����������.+>��ްk�qjӽ��� �).n�dT����rYֵ̓c�<�����ܓP�b
pQ�4�U�Kz��x��"x,a��@3lD�[p�[ ��vQ0ۄ+�r�?iNä������I[�O�l�$�Ka�fj.�h�,��L�NѼ<���� �xG�[��6-��W��2�d��%�̱����{���X,{��U�KX�y�B� R*9�XJr�/�\ls�xƚM�ђ��6|��z�h(7V��P���~��╺7���F1C��!dF��욇�C���
�l��Y����
"r�����������π���|p�)0;�c�ši���.�Ex��(`�P����$��дW���u�UI\d����39�o��YbT��P�V]��N|]ld�Cg���@m�X�ђ
��4����4�4
�eq���P�2�S����*��g�	N$��11�e�����*�@�U("�xۑ�c�%>�X��� /㚣>��m�T���W'QJ�r�p�-^�=4ͱ7��钄H�`+�ʰ�aR,���E�o�2��S�4�Ű��`:���#ю��1��2��}���Qr��'N�x�s<��bF�J�b��X�j��RP��W��	�2���Щ�| �ue�"x!���~�f�PrUW��vfi�FnB'վ��4�c��yZ�𷠗Y1����V���/tM�w������$�k����S�=U���]�#�<���GBG����8��$�u7��tf� ia�?o����2�ʅ~������/�j>ϩ�}���H�*M��D��� ���+�{�@�@-XQb����)����JL��=�(�
�T�H��?#�C (���}�w����9s|������:����_pd���$̴|5M�n;�6?A
L��\8d����c����u�@�8lk$C��l�d����(LK%ĺ�%��t{�u+	
p�[8��h�Y�2�J��F��Y8�W©�jz�/%�\	Lo��9���"��{�م}3��`o�AS�ya������SQ#��Gօ�]Ǔ�~�_�3�v(�R�)�dF���)�6�_ke��wb���QoW�L��X.-��̈�� �fM&|G�өu޼s������MBp�bA񼖺"sF����冹�|�Y��\���=Oۘ�@��o ��w�
8�e	K:��
��r*(�j��� 5��t�0l=� �E����{x�ZS�n���ͻA�����tB61����zFa�!tLu�4@��j��ь����x8O�{��&Z�/�m�!�Nc�W/?�#d!����U���rHG"�y2���'a뙢�꽉Ly�j�:�>~�.
UlD�\��s�ņC��q�`)Fv�Jv:�����߇�M澬�/9jF�*������>S%yDľ����<wt1%5��c�+A�:w��tH�����i/[{Y�ى��P0�b7椈5��ʩ�25;��?�>��.��C�
��zB��e����n��A��&��9?��Pq����sp�� ���*����"u]�yN�7��DʅU�i�"�܅xT-`)X8��;E!���Ѷ�Q}|��aaA �Zv�ok���́�$ y{���@����W&.p��Po��D�l���S!i$�3C�J���4DE�ˉ�ڑX�JW�+z������n�m`e,��	!]�*��#:���V=����Q��-䠛��|�&�x���ޱ����)�5��Ұث��=��Z�$��h��{����Jǲx�#)��nɃ��n�-��W�¹W����"w}�f12������]ߩ�/��C���me*O��m�P�Ff'����@�����{j�(ߘ��͠І,�jEI-� 8����1r)ĉ�l��� �]��B2�g?{�`���y��:�r"ť�C>���!Q��C�����b�L��(b�E3��TM��u��"�[� r����ޭqj��6�ܧ�ա�L@�����u��A�����������f�̝ «�h��&�I�,�0�[g^�P���8+�R���nOP��j�zuv^B�6��z��Ma�?Kb��\�}��"�IؽT\&�s��/��w��\�����q�����P447֜��@� )"*Bc�坫f�U�=�[����b�A	�\|�.�͌���,_i�^�`y�ݴ㬦X��®;��3k��`��sGȾ
�3
 �������z��m�Iై+Q�_���Q+~���
��)~�#'oTF�哗U�c�F3�X�����,�{E���6T�Q>(�Ҏ��nY�Za#���]
.@o]�;k��#��T*���R"��9Xː�#��+)����
��QN��ݕ���{�=#��>Ƚ���V"#SB-���n��Ra;Cpā�Ξ,N����k�o���=�����W0�V$Oz��b�GU ��t|i���;3s�~�����ҫ�-�|���m|�P�6�KQ�.!��Ѹ;}����zHjn9����y�
ꏫ��8��1�X&`���/0�����]F��$�Q@!6��=�V|M�n�&�z�lc�ڈ�kfV�d��͂����T���H�A�X�YGԕ2���gk�5��ݞ���$��� a2��1��Į]Y̔$�� �yĝ���H��m�kx[lh��YT�l��AU�0��\�q�Y�~ص}��{i�Jc���/Ъ��q�ǯ�	��X8���7X��X�W���k++��x+!:}��u��NHm���*>�Ɍ~>��npD�8��X��p'��4M�-��`�"�#�j=�2*N:vY��^/C%Eg:,J�9�7w%�qMx&Ah!�{9����'@��	��{�O�d��rY�#�=9$,�6:�4(�o2� ��ļ�����/O�Ŷ{�}mm��M����R�1h�X&�L:^?ɟ���S�r�:���@��X�ZL@G�;�!%�闢]e �N|\��^?������{R��d�P�O�X� ��i���)𢎿�����-M�$�"�qY�����b�> �
d6�س`�-hs�U�?�覧��Ke�M��!�Iz�9B �w��ʍ�R $
-!�W?�%z����� ���+���{-G����h��H� �_�U�C)�֛�]�脦���Yہgβ����A��+���S@!*H2����M0x�
C�Mr~��(KB����;����v �����5x�3ڲ���o8���	�I��D)A��m�g �
�G��j���z���G�S9��
7�h��{�T��}��ɻ`���EV[�l4w'Q�8�d32���;6$�����&�}X��FD�{i!A|�wU��+ϘVd���Q�쬵��Vz xYlh�*�wzzܮ�g�,�xd�(���;Y��8�2q���03�	�sҗa���b����T;��f����Lښ{}�eL�q��:,���
P�&,識56h��*0�����}��b �I8%�"���&G��c��-v�x����?M?Du��gYQX޶�����5d���쇹�����GJ1
�:�g	vj���y�C��m,��}g��l+�Qg!?dP4Ӎ�ƅ�`�>.�|��-L�>cD༙B�C�,�ܒ�:��NX���)���߻�.B����;3hS�,����zw$h���^iR�̿H-(th��Vr���X�%.������4��w�{���7��
Q����@-���=��W��BL��P��yh��2����ð9����d{C�?]&��j�t(����r�����òC~A�p�[����^�&����0���:S���A��Fp�e��B�>�r�i��@��K	�U�c��2���6����)��N�����P4@_�Rrj�{��i���X�����I��x'��Z�`�SwC���kh�~B��R� � 9��9��R"���VQ�Cޟ�F��Tw܃�P��� ���OF�W����Hu����P[8 /Ob- ��̡aq�|6=�_����[��8��z|J����9��'Rg?#(^�=�5l�El�Z�����{���C�l��oi��Э��Sގ$Tʙ��N!#����|��w���?5�U�z1W�05]�DK��0�%FD`$�@�7�,��wJ0b��'k8O����Ey_���QS�3�dؒ�g7�lUG������~o��Q�.'aL��BS�����(�w=���*k7Y9�'�[uva.�ӌ
қ����*�l��j����I�����b�,���h���7�����L$�#�S/X�t
qT��$�N�Ë����r\�&���=�q�����b�S� �[&�5�t�\h Z��j>D`��c��B-2X4m�S� μH�`��
��ۓk�����Hn��#M�����!b����������g�
b^�"��!���e� �s	2�����]q�ė��靬r
��erIne�M��;O�T�B�`�Su���eݘ?z���_8p,/0�/�`w���sU�����8�:�R�:�aXo�0�Am�}ֿ�,�����w+��"������g@m�r���U�� r,�� D����oZ����x�
�������BZ�Q�]�oܒ�B�0��Q���ͻ��s���$�����)�1����i>O_^q!�$Gr\o@[�DQ�� ��	�
͞2}��$'���mu�������HCK
?�Mr5�����>�P�p6�q3�b��N�c~�Hu
�reV��l^=0�9�+����t��w�N�,xiL�)�O���^�!��ɜmV߱��N�f)��l�bͼ��,�f���P��s�,����5)E�:� �(E*mzW��M���J�+���<�	(������ߟ���:ao
5;O�o�8�!	�"���xǀ�ch�t0��P%�d鮪�j���;f�]q�?
���Zy/�_�0�_����D����l��F��m���,C���ʍ6��q���!����	�� R���͕gー@�?Y�%�a^�N&�W����w�`" �*:yP9bЪꀦ�7�Ж��s���C������޳�iJݶ}��Hig}|��B�����8e��	�([YD*+����<�?����-U
a�� �%1-R�s��]�3�RPA�R�Xi����9d����ʆ Z��B�q�;l��X�F:�g_d߸S�4��-;B�D�N�Hm�e�Q*�� \;#�M�bQ�概=�mD����� �|�{"�n��fXI�o{
�
 ,V�7ޥ� :av�'��X4�t�Su�{`F5�n�-]��+}�}���Fg8U����L��.�gj/�D�~��qȩS���6>����X,3������9.���.�<�1*��K�+�9���6q�H����,*��
��g�������q���c��I)y�-�ƟHo�f�3ʋ���}@���Ǧv�=	�����Z�b�"ȝ��7}]j�o�NOv��pjܙ�Q���N��f��������o�7��d�v:k����5K�c�&?yCF���z<�6����q��V>���:�#t�F{�h\��Q��}/��/�	w?�@�w��[��P6=����7�M�]`H��[���*��oz
�φ�㺢�
�����3�Z��Ł�V߉�{ّUr-/
4m#E������(��/2������;�|<૽
u
���RB���(ڣ��:�I�{�c�)&f� �٪	�a�r\��7���u�)��H3�VO��ŝ�i#�3��BM;cC� ���B��U� ����KG?� ���N�Nݵ���K�����������u�C}�
�GhƸn^�A����R�r�۰d�r+�ܔ}Z{���}��f ���ª.��	IR����&���i�����I�Dk�|�x�uk��?�۞e+����V��q��� ͈%M�޹3���,L�X�mf��LӽHO�Q��9�yWS1��x�J�	/�ݡ���D��j���*��M���������o�#���5&�����?�ĭ�=>���x�wҸ��(Y�R.Ch�ah�Q�6<�C�Dۑg�@Ԇ R���Ah6��%�g�~yXގ��V�t�۞ɰ<X��d��A��\�߄��ѥ뇿
�w^E���7j5��m'�Cj�c	{�1��ۥ�UZǜ���hӝ�C=��9h��B���J�]�ˀL��[�r���oZ�D�PK3  c L��J    �,  �   d10 - Copy (14).zip�  AE	 �I�B&:��Fɼ�I�v-�? @�s��tq5OQ����Ş #u���j�V�s�|�i���T8t��i�*���;C�`��?ČVj�J�z�\�T��ς�L��9�>l���-)��!ԧݎ�IHk׷��--�?���a��3��[W	���sf82{�zձq~۾�6@�ܔ�ǭ�S��V�� �@��=Ќ�Ir���C>�quiwv@櫺�v�ۜ#U�Ũw�v��2N]
�Z���eJ
���/�P�2+��6��$�u����y����\�w���P��G]���	`������1�>�pƀ���o�
ܹ=�=����n��z}z-_|]5�#��,���L����G�,�b;�>��
L\�\��9ҿ�̸�e�|"7��e-�4P�{s㴔�Խ����T[��Tn85P����9�%}�I��*o�
��2{E�/��?���q�;�_���jx�c��0L;XA{;Ӭ�'���J�o�d�`��岃 ��{KhY}�(��Na�ܒp��	fb���rØOC�R��_��m"�PD��j��Y��&$�2�[^����޾�)dQ-�`��������k���HoRmnhV�}AIsΤl��+�X����7L���MҜK}v�S�D�:<��N?�ádY��3b{�`��^�/� k�8�.*�å�8���n������g ����|H�!d���)��!�8�(<!��2��du��O�a��#2�OmYi������ $TU��̷��`Izำؤ%D]�e8q��(�)�߾JJQL�=K��
�iM"�5:��'�$�.�ʑ�~EƖN��y5�E�m6���>�:����C�9Q$th���/���oΪ	lOc�<�[��oZ���=,M�e8��	p��|c��+��5��P_�lr��h�e�vo����gDq���z�*Cw.i��JV�g�h��Τ�C����S���(yjOs�KkC
;9_.lK�䀗py�h\�U�|V�	82
?���?n13������N��"��o$�+/�����X��&������/�y��8"?#o`�4#� ���
���(��e���I��M�7-�J�,V��/H�&e,��N+]!3���u�%>swv�NQ'�\%Qѓu�|�wX�z���Fe��:q~>��0""z�Q���OÓ���u��s��*|!�\��]�����YzyUT��<u�&f��k���Iɺ60�,��rc���#a�i�%����S����B<y���&nT����t=Þ�7h��o���Zy"�2RQE*e���/8�]_�_3�zZ�Z��v��q����2@n��a�I�b��V�s�0mDF{��!�T�t����U��&m�!䵪�*^/���Bb�7��n�g�
j�ƿ4,f���ǰ��xk� �s���!���'G�$��T� ��6|��L�ϑ
�a�;#��W���tJs���f�~pZ��xP� �� s�Z�L��BiX@>B����W�G���t��].~j�ٻ���k�k|ʄ��b�&!V3�DT3�7t���z��*n������i#�nÈ$j73qp�_ F�H��!;���^kʁ�|�J(ei��/�!�E?��5]J�kV�oS`tlG��y���T�;T�t���I��7�J1�F�7'�X�@ZH,��N�
����)�t�E��\x�)a���j"�s�I�~��@�,�k���ǿ˷鰹�I���c^;9&|�X�@-�b'H�\�?lŖ�-^H�d������f��0��-�
�׹X�"���m�:	�ҫ���f��Dp��F2�zѭͱ���WD�������
&�!��i#�
�m�$G&����c8_['�~�� � w��-��8QP3;���K��cQ7���	8��i���;.���܅��&5!G���T�S���~��I�V�¦�EM�ɞ�깪X��DK"ǉ@�
�t�
 �|wYY)!l��}ןkk�'a�d���ĔxB��o��T΢�%�I7^ǦM]����g�aUXL���G�;9�
��,s��h��g���_�ݏab�Y_T��B�O�.g�i��jȼ�:�*VO��i��èD�me[Oc�H^�d� ks�_���O Ƴ2��>��Q��}����������V��L��ʾcϬp p� �NHk�b~����~��:�o[������!ӝW9���G���>�[�RK2ZW�[gdh���+z�qD��=ӂs�G���F&�﮸)& P�@�t ��
SZj�Ut�&�u�X��9)z�i�W�|�=m�C�����A6I����
��ɼ0�L�?;�<@�i��S]���uL|d`���������:�U�ŞY��Ei�z�Z!����n�c�J:�{�R�iN@����8���#L7J.۱���f�JF�q� 5ɦLW�r��Ir�3#�1$�ѷ�0M�sך�}��Uϟ ήuن�3р��
�%��Lb�?���L���R���9dd!��x]w��`�+��!�YojD)L��		?�վ��ӫ��ǐw�E���v������X27�!r��:j�M�r�ʏv��Vo�JW�Kړ8܍�l�e�J�L�ӡ���0���1������4���06�{�>b�Ù?t?"\Uqq���ב��&8X�M�������@'���t��Z�&�u_��T��ѥc���{�R��*!s��7r����_w��K
��9�G�൥��TYn���d�
���2��;a
�e��*��	��RX��
�oI��(���x�L�� �8g00Z~�&��ϲ�����Qj ��%1�vʄ�'B(+�R����s���Ѝ:!�[2�1�y��HM:�:��T
K�{QݡRc,@���5WYj:IS<�xq�cǰ|/�&X���k�ʢǚ�% q�h	,v5�ETA�@�Ӗ����
e@�����:b�7:�;�sO��^�bJ����d���h|��z�
��S���	�30�!b��[]N0��I�ܯ[����<�`��b�n�G[1@��i��c���B��&Rjw��6R���]oG����:���& ��_��\G�������\�ɹ�r��*���%�*w�.���ȶ3�v����p���
 �� 1,�G����}�Ǩ?���G����n�_�cu�c�mc�.�m��"%�$��t�IY���۸���'�v`V�t�:N�G[�[�"����+�Hm�V���'���6�β!@ZQ'����u>�>ywg��Z���n`,�p��f���|��r�'3`C��d
|�`�43���F�d��A΋0dH�*KM]J'�a���.W�_
�IR;@��8~w./�(x�8��Wb$��OK���\�E�5j��A�~���5g۫NΛY�����|�'`�/��n�޹`.o�F��2�\UP�@��	���X)$���gT^Dx�����\���[�<Q� @ړ_ե���<�O���DW�kUL�b*�E�[5+rBi7��h�o�{�$�ӎd���-�an�� ��1�̼�#n�[��4�@9'�>�֧w+�E��d-�'JHm�6~
�ɼ�<�� �-�U��.d:,�x\>>�uۥ�,R-1�7F^-WR�ȩ�[ۡ�����rCI\3�n7op)���.����mM�����Ӑ�O��m� �����+ܸ���'�����I��Lak�S��M�/FQ� �!�!����՝%�W��6B��	[�Ir�� ��h���:C&��V�Gq��KXN��|m8�
�x	���TF��
�f�8��_��wC���AL�8�9�E�g儾
�A��'.wo��0.)��T)WD�ο�-��7X�:�d���	�������r�J�]V7���;��;�+�k"��mjEd�L�5�ЬF�>4䌸j
���!�
�q@5cpQ�e�-'l��X�n�����-�gk\T���X�S�V�"�eQnDR����b���TQg	~XRFŽ��&�?�:|t<���S�5���2��|z����E�xI����X=�^����	��g�&Zx��!T��%$N��̹�j�f�f���~	�����a��7!g��r�x Ĭw����s1��QE\�+!P����WV�tc�i�CZ/)��&��
b����	��k�7�~�m�V������\qz���\��b\y7`S��\��D;0�1U���L<�D�
h�����@=<ҶƱJ�T�t��tA��]i�_<�镏��U2�]�$��%�R�";=�i2��^���f�'�"p�R�06�hA�'Dq�5t�Z��P�t�n��mܝ�p���}Z}��
��3~Kn��%F�`#M3ȿۂ��P[�+hÌ8� M�'����V�j(�W�)D��s��jܗ�S����6%5��cr�B�f�cl�E%L�e?A/sX,Pl=��J�J=|� ,gh�{�%������S�����4//v��������|��݁�ld�N�����<��s�]�1������i.� �/ 'X�%�s2���LѶ���E�kN�B���ɯ8 �`	d?��9�H@e��P_�;!���u���{��..�9�$#�G]X]�@�%�����5�+F\s�r]��/����5���b:�������Q�V/]Xy��آ�P�`o-�y��c�M
*��R*�]ь�3�<��٬Xw�Z~c3itrY�p�bC̉6^��.��8��O
��Ci�?�a��V�g��88T)|�6^�b_�4�[�� ���ES�j^ifDD��PC\��㊄AP��$��#E<j�Y�B�F�~)�!L����&��N�X:�<S�)I�{��]����vD;\S{q[L�X�U ��3���Ř:l�
e��r�����v�5�������Y�g1,���mVŎqp��h̽8/�*�_(�H��M��Q=�Pd����bdStm�a\U["'Gٔq�YcI��xA"t*?�^���M�[����Ѷ2CΑ���Պ�V�nJ��/��?�(�;�1��*�4���n.��pKh&���מ6�p���Y�DE�Gx;
q.�z�
�4�y6q5-����l�����`?�9�"�uڟ��Z�ƴ�1Ni����Ry��2��TK��Ս_���'*�6$c�F�t�;Z�7B�B����Om�) E_ppJ�Z��0�U����BR�q!��}Y�.:9cS�b�C�ߟ���qy��֮����,*}�]���͓�����`/��� �N�]�m��,�����RiJHT�e`�+�N㉽��g1�]6g�6[���חiUƥ����-�U_�bY�BT��6���_�<���^��7r	<8Y��|�u���t�Xe~zwk�J���<SL�P��=��!���H��G�����+�,�,P�Y8�ޣ%��Rs�$5�OL�o���
�7
q��U.��ZA�Q�d�;�!�y�{r��E0T��W�2Wo�~�^��?��ീ��ҷWr5�^՜!<#I�`���n:t#W{�e0��"xA,v�_�S���m�eȥ���%�"�,�h*3@q�>�z�u���"�d˿g�9.	z���dx�]�E���w_XE)�>\�P9��� �`�����D��vʛ��{Ii	۬���֫g��������Z�o
��	\��擊��&kY� $����h����
���B\����;�g��Y�wI�'{��5:��:s�<��~t���:�5d�]�	i�b �W�շk�ɞ�
ҴS.����1A?QBp?��Kf�A���\q��P�R��b�a~�[_��d=��p���������OIzdǏ���s-d���2���"Q&+����bsC3�!����4~�P�r��9�ڡ����}g�k1�����6�/K��e������[�CJ&��U3��C7Z�{�D����ܠ��Oui�����xP�E���CF�y]�"Ƚ���h�MG�X��A0Cہ����">#F�%,
�� ��g� �K�zT1C �1�n��o�E����g�NX�i:�}$�a�yj�NP<Rgb<��p]y��SđxH]�u��7��R��>���ޘl�,�җ
�U�8	�_T1�.���RY�G�l^�oX�6]������Dh���d�IY���L[�ob�K�ѯ�v
�6��h2�}�҄PnN #C杳�9G�e���u�0{=�8��P6nR��&�y�[d;W{Bw��-�����;>��|���*�nϓ��{����So��JVJEB��5�
��*m�),�u)��s2�������Qb�h�!����	�C�u~DEk�&?��j��t
�������r�^"wLR��N�A��C]vHTr��|椲�tX����v
Z���*�R�_um=�&,+�m5R>&��Q��0��!���v�~Q�>��~9�����	��Ԣ�Q�HY��k�e����r��s0���x��P7C�����Pa�-�|�9�ؼC�g���A����]�{P,f*)o��ᠴ��~
���$_&�΄P�(a�T��GS���Ae~͉��7��Xj~i��T ����wq��ξ`�0�L]����hm�&5�Kc���Tx�Ç��w8����|��m�wv��Q�RļB=K�f8��Ns^�r��U�m��A>�JA��#�h��m
Nhl�7���0�F���X^-;NM/�6IR�!kSU�ƙ+�exC��V����]���2OzΆ(���I[x$m�S(�����o"��)�ԍL L����WH"&U��V`᎕�K��Wu/�C}}Ⱦ�?N���Z@�pI��W:��*&B5��Bk �#���'gU(g�pW��j�D���
������x��r�6�<<��	�yZa�/��nU���Z"�s��
Pq; �ƃ[��T��b�0�0
ў��XQ1C���&��s,�ʁ�p�����x�{���^~���cl�=��Q�Wط�o������9u)#����>^I*�h!��J����&ź`�7�c:�}�#�A�<����S�X��H�r�(��8E��Yf�I�����;�w����BǕ��4֥�I(LPO�9��2�W?h�7�w�����
Ր�-`Q?=x�p�/8ޖi'� ��Z;x���/�e�s��R�sO�	�I?����aMYF��n���P�J!*	�Ay5���ҘWpo؟O߶D�V�V����6�T�34F�,.14+�i[V�"ǀ��]�J��Q��
<{w~�YB��`
9X�8w�9����U��˒��H�:0���#�GVM���y-�:o�[X�p�I��%�;�ͽ5�Ϭ�ջ��u�_���v�@lAh�� ��!y!Z��n�� �F�����+RJx5������ȬV����?V|���.`������,m�~�KP
o��S�Y���@�l����U+9�Ă�3�\����k�ycRԧЎQ84��>��.⸘e!U]��Ր�am�ǠĲd��j'�<�>�|a,��.�P
97=�V���ɐ��x�VJ� �e���l���Z� -��
�3����;y/\7��R"^��K� \H�H�D�����ՍӠ	�<*g�hc�]�� "�s<̟Ad5��4�mi�����T��\�յ/���N�i�e�D��S�[.}�<V���`� o-^HY��3/Ag��HDh9��\�CzO(-��~嶋���� @N��(�DM�[���h����N:�G�f'���B����n�?������	{1^qE���]j"%?���*��(�� ��m�});%g
M�c@ Z��ǅV̲�Bl�ŏ���5F�g�R6�m�>��Lۣ�P���po����^<�}�Pe��&8�m	s����wB���OH6J*V��A��h�����I��#|n!p�y�d}�r�sk��%YRf�QVhd�&S�����h����q���-o*��a
��O�hv��z�����U�%�NM��*Ʀ�V)�x��v8����1�A�Zg�I�'R�����v��R�(�l<�m�!(YC��J�O��lp(_V�6��K���c��[�>������ �Tlvr萇��uX[�
�֤�;�*",je3KG�H��s<�M�0�R�[�.��N�²��V�[w���y���oZFDPMOj���rΌ��b�	 �ɵ|��X���ⷋ�����X��P�O�
q@�c�/���1�S��)&<&o�����{,tV�{s`�)4fs>�
c#h@�F����/� I�m���7��bhE:�>ӗ�����\vj���uB`�H%7MH4���v⸾�j#c���D�jWҞd.��F!�U핃8zÄU����v������YVH����rT��) �2Tc#�J�
g�sV;?o�Q������u�Ul��a�}�X�4�8̨���+ΝPC�4��'�H��wt�n[ʩz��iM�����2������'��$������,������X&m_�ݝ~
��M��޶��("hćζ �4�ȉ�\��h��x�j�'�4�_�D23?�>���zO��j|�n	{
l�gYf��gPZ�81�吴/	z�!��k��N�/��!E�s�m��V�e��\٧=ȍI�`fc	�W���H�D��XV}d;wU�მ�>1#@Ღei������I�
�؜����o����p�w��wL��^T	�뷮+$ㇲ ����8��&5�
i(#5�Xc��v+�>
2��%�]YX"�&�&i�Ơ}�c�^�#���%�_Ϥ�̡�������?��Ce"��ۋ����?S��4\NҠ�K�9���X�9W7� Od��>�G�Ϙ�����L�w�$�1�?��}����N���*(�����{؈�
�9g�zcywc����]b��98�/V�λ-u��R��}}���3�7	���3��|	{8��
ͨ[�Ϫ�!�|�]]�Ѓ��<��͒]�z 1�Vf"�z�#��L�����Vq��20���������&�Z�������h:M�͓J�~ �H�;��j�+��-�=�Gz�
���'����C�[�rd�0g�뮹5k{�7*q!'��:r�3�n�gV�gRЇ�=Y_����;�ؐĭ�	h|{�6�'�P�M8�NY�ϗ�$.`�AE^�_2$̽�8�/��l�Qo�����8j1h�*�ym6�z-47�o��q�2Hi�!9,��6¹�y�#e(�A̟�=�1���Nh)�����~��ٳ�6�i�]����	p���*�6�����5=���g{�3IfJ`H&}
�[to�;HM�6���In6bբl�D��w9(Rt���C�m(z��g�Ջ������!�U�A�X��)o��`�L�жD�(OR�S�3���0�ڧƣ1��Էn���X�x��-�&Y	��ˁ����9�n� ��M
[x��+E�?<%�.G���M��o��e;��[��:��Gs���a/@�v�rvEi�� ���<M�q>��}�t�޺TqB.E4�} �K���ӟ�U�%�ݶ��v�	t�C81B���"�y�����h#������e�Ǥ:�v�UR�*�碦˻E�14�y�!^0�W���|���ꏩP"�(�r��8��w��(�<)c��*�[�(��?bf�h�Ņ�coH2���cI	\�M��
�������L'���M��%T���c}�<��ͩ��v�,	^g����+ct�dʨ�O���q(��@�G��$�x>��!RE��1Y�q�O��������$i���7��>="b�=�:z2����{z�E�����Ap�2����򚈍���{���tw��<����z�9�T"��-��Ao-.�*�s/ $[������������N�v?��2¸6s�PF�Ϛ;p#(Y39���-�R�magL)������؈��j������+��N��R�::T��gW!W��Y&�]hW���'�%A���R�c���/Ip:�CML4Z�nY<��C@qp(��8���}8͎��أyA�����G�<=��jM.�HV�e����r���9
����:ʫQz��5��ƹ8'��y0�Wf�p��V�[E����ԙwv��L���C���#��7�+ve$=�l�1u�)�K�=�k���dḙ
̗bj��M}�߾��>����EKxa��t�D�a\�-s�&I`O%u�@y���V�o
?���'��?k�.���T�r�PH�i����f��wp0�p��F��.�O��e�3%��s����r[Y�h3L�~��i��[c�[!ʓ8�/"�2Dy��R7zr��*��8��W��-7_cV�w��$a[[I��a�Q
7��*JcvyvTO56W}3����m�s�էF�'�P�-��L�����B�����|�����l�:`mj��14�|]J�V��}~�0��U��J�j�.S�G��/F+<w�ݞ�R��þ~�CTY���bz�5bbZ4~V��
U�p�*y�[((�t��!��]g����ݾ
���2$򘈧�,����F"�080H�XӸH�S��?����!��UŔ�{�6t><����i[�2�ñFS���i����T����^�2����_).���N���8^9�C۔{ �C�u+�#@#K���)x �0��կ�i����|�UO�X"(4�P*���ϳ�����%E!�$˘�@��@vڋ��бX!H�
�;�H^�]ಪ4��Z!9�����\Ĵ���JނB--��&�|�-��`B\0K�f0`�MvL��1��՚���<��{��Z���5�"��,�Ӌ�$��gm<4��N����H9D�B���胇V@
�)���%N��^�$��}�?e��[*�G���a�Òb�^GʢbR�6�n������9�I�rr�
CQ�※��bً�څ������$[��k,���W�Վ�<A�=aG�_]T�^U�� K$���j4���ż�e�hP�������sPB^1�����L�]��23����w�B��K/��A�U��\
�L5����a�
��*1I�iȫݦ����+��v�=e�e� 8���R���e+�ԪZ �.x�պ��V����b&�mc�/F�+�$3�Tri�U���c���.�
!�n���^�w,�%��c��z:��M�}r���ʩ���=ك�e	X��[#<<��#�9
�"�.G z�u�ʾ�x*��E�2r¡�^C�ѣ=	ɦA�
�QON���e��c{q�l�uR�{��YO��0L0y�f.x�ĥ��Iֹ٭�t�7ڿ�؀���W�Ȃgd�&���nM�	g\ ���j�ˢ�aG��i��
:��o��Z��o��fhm C�O�!���|�����7`��i�i���
-t�m/��ˊQ�|'[-"Y��[{yJ�� 	�%X2��IlxM8|��S47]���}Vm�?Ki!.�ْp������۶k��Ε�
L�r�7�[mU�'�}|�GS�Y��PƟ�<B�H~P��$�+�N��cu���y<��/�Lƶ��i��ǘ�:�>~}�1-���\ko\$r�H���
�����C��N��K:X�V:�F⑛cA}�! ��@r�����:M���5��a;�]$�<<�}��n�vjТoF��4]�bu��PS��7����rM<����=��eM���?jK��sNe�?qr卥@��_����H�o���J��������=O�?9c#���f%��i:@�,2�Qp�Q���W��8�0;�����(�������ҫ�p��/\��"$�&Ur�O����*�Ӭ��F
��Hс_{y�`g�>�O"�/m���8��Ү\��{�
4 ��F�;I1��v�Έy��8؁��C�cS�,��Y�[-.Ո5o��@�B��nwÛL|l6k�"�?��"��
�׍�N�����T����BV���l,������M��v4Ģ���T���t�e'��Q��q�E��C��a7������v8�bDu�6�_�� ��c���n�q���� ~�����8b�W�~���m��
�V���h�PK3  c L��J    �,  �   d10 - Copy (16).zip�  AE	 ׵����O*��90ޗ'ޮ�@��h�+r$3�^9	@�����}�V�x%�C��ӕ{7��s��v~t�bJqgϢ	-��Q���9�=r�E�Ǟ��!�y���Gp}M��(}��q��{:�܅{^Q�j��^� 0�� ��K��, =��,�����2S�����Q
��G��&�Ĉf4S�G��R����r����F[�j�3�I|�y���a) ����rW���H���L�-�Is9�X��y��Vs΂�돔5�%ơt��/���8/�1&{{���>���S�����N�l�&��9���s_�5���!=pܸ�.%�
N����ɼ�>����'��U���s�؉������=m#_������E�f�T�f0ۗ�.Y��:#�'��[7�2��mƬA��p�Ȳ���;�����~���>�Jmn�OE�:�.�R}�S�č0�oV1J��o��:��h�]7�O�.���/2:U6=ta�uIp�/e8��ZqS`�1pq��i@;˷p��Zd�e_q��P"��#�}��ࡿ�qk�}9��\��9���
�]���-GIl���xj6" ِ�eT'�ԹO�8��qR��O���ǃI')Ѐ��x��a�6��:�ip�B6i��*H�]�I�B��|��p�"�n@`_.%tb����Au����Zh��opMj���T��=̍�A��VIlc�|��<Q�+>�JJ|�����M�t��c`��T�6
ܽg\v���,���*�4��\rfs��D:�࿃OKԵZC/��l�rU��\n�ĭJ.��mW�u�|�|�a��a*2��]��v-
UX
�����3�-���?ۼw�A@mQ��ҫ���5d�P�G�,T�}G��%�hR��1�Z�1gi���P!㊯i�<n˨I�*��
8l �LWU M����Ә��,�޶A�-���H\K�D�>Po�#37e���sN����y��x�yf�1���6��
� ����6���)���K�JY�->�_U?;�hk_s���r�L��"3�t�l�嫾�+}�^$׳�
�-�>0/|�:�a�L
�O�pO)ˌ5��g6 G����Hp�C�~�X܂ku�_��Е�%����z�C��
I��-�::ŵ�V�Tvݭ+�K�����Y{E:��W�?�!PW/A�SM1IO�@�2~��[��fN�m.8!��&�7��e�������j�nyuȝs��M:��A�L�ֳ�#�
f�I��ђ� ��{�'�W�k�L�.��!d)+�m�|� �ڈ���U�+SKK�C�5~�V��1�� ��f��|U��a���d�fG�B�X�8����,�W	�/3�~�{߄eo��[,/*����2�u`�C��6�za�6np���޺7�9ǉ:�������r���1�f���V7�|9/�q���Ȝ|+���q�������ü�����jh����ʵl�e+
��{����y��L�
�e�a��Q����q�]�wu�o} �x�o�@���5'���uI��)�\Ux�F�3�d�;\ě�J�.��x֘��O��s�^�ĥ�Hm�2-<8)�:������B]�������Ǯ1���
�@��ĝ>�����C� ���R-!�{�}�J\����Y�}PkN������VA�E�X�2�A�'���o ��߸;L��U�:�������^�������=��ѣWڞ�L��à������$��0UX���]��W,��	B�5dh[���9����]����jQR��_c)L���o�e1$tal���6�fq���nӟ�JX4�'��j�2;�j��aI�<��*4�b�jAT�����eA��_�Qܒ�´��]Y��!
�J��ͽ
 x����$�t�]��nؼP��$��z��.v���.b�諸�ZJ[���w�D L�#��F�v{hiX��S]D�)�*���R+�d�������b�1'�� �]�9��6ؤB�ްi��a_����.���+|� �}�R%>�H��*�v�KaC�L�������V_���h�mE���^_��U�4�$@�W	���@͖��qh�<���2�愡8�~lB	�jX^�����~)��k��v��|$5Y�je��*{�tєrd��%�0ӫO3K�b����ܓ�D�
�L2 B�6m%�XD���M������WR�N�yk~��
25@M.�3ج�~�%�ɋ%�z_`_��L U	�b=V�j�7�%�j5�9�

�n��� �����.U���ȡ���B� �к��\ �z����k���]+�������{�DTR��D����0���޹j��|��/��
��g���l��z�'X����	�"���C���[���.��t���}n*���YƮ4���}�t�?� �	�I
��B�	�$�Qg�]jp�>qBC���F�j����@��(�9����7�*�%�Ŏ��/g���[�0N�z'�ہ�uv�12:����ہ����T��E<�:T�jͥb�w�Z�Ї<��B�M��/^L��>�BY/�vda,����_�7��xA����=K2�N�j�9h�̏2�����1$KXa�hM���F�_���Z���Q�[L􌧬�!��6l3���k��^B&�sckiq����DEV�mJ�o����G(��2����lSګ�E.�x
���YZ4�.��,ͳ}`:�hq�G�<A��
�ȇ��}4g���o�fO�c����x��\8鿵.=��7���Q�sN%�G�G:Q��l��U��!x�����Rjm���e�r[�}2�v�*�>Ƹ���#V��TcY]4�xٳ�>M��-Lʻ6/[�D��U����XPPKU�^��X�6),썃A����V����̈;j
�W�
���f+i��a��V�_
�v�ϻ�����Ed��g W;�Y7Ȳ�#�Xr��ԭ�u]�V���x�
���1=���iG[;cm�PVnp{Z$�6��4]b_�Z���p��f5�t� �V(A.W���ו҃��x��j�R9o�մ[70�ؚP���s�_��F�sԾ�s6(4�
�����V��(@�t7ǟ{20 �y�r(7�������
�C	D��c?��6�*ઌ�Z�cVd��Sd�>��9_J�Wn�>��ѦW�ː��Ҋ-���ZtC�*�Xc�Q���梑��a��|sf�Zs�2�84��9�'�lk#R��ʟLD�4>�[�o+g�r��<h�<��ac#%(�
Aw�2Fy��	�
��b2�����{�u)BsM����^��)��E�8� Yv�H��;���vKä)תc�/N�JN��O�	��1Q��FƿJd��t>"p�	�6�n�ץ�Ht�ؙ��wo�sE���T"2�|�
���xA�>�<Ad77~��
�x<�D���mV�Ր3�����������XW�����J�����;�J�B����̞��i�"|���fz7r��J��1_�g�qe�yq�{�`n�M�8I�
P�.�sk����y��F[��+u;&msY��w��^)�
T��Ǚ�i'��x��҆~S�n�7��#}��:9�����?�k�aŖ��Q+�]�'>��O�_4�������,=%
7M��ʥ���i~��fYN��gg�d�J��F�^�u+m��2��li�,�A���ep�1����اY�韥�4����ǓC�H�������2�~ft�k���O�OK�t�?-�N��O�M����|ׅ�8� ��mQw7Շ��u��M�6�yQ%�"=�iR��;�Cx"p�{�M��)n���G1Jp|�fP�
���;>f[��.�T�@2���n� �!'�X���u%��!��8�� Y{N��ٕϔJ���(\��N���%c��"���#�$1ɦ0R�KMU+<���"���Kp��a�����%
 ���n�µ��9E�3�]Fb1�6_�VL1{�g֑��IL����y<|�����!�nU��ۼ��5��*H��󊉓�!��dF�����]�
��x��F(#�����% ��U]������^�%g���3ly�l���cT'�^��J:e��I���g@�dW�ߠ2-�Q/�/��a��<�)���rꖾ��ع�%Ux���e��}�*L�f�� ��=��<��x����yp���*���g��]4aƟ�d�z�9�ϐ���_׫���NS�:��nZ�����yX IM��mO-0��/����A*���9l�q-�máb`�l6������ސ��8C�on��B��HG���_�xFá�B� o�4���2�|Տ��R��r��nv]���Ĕy�
Y���H$���ێ�����t��͈��R��[��g h�l]�(%��W��
Y�(�p�rB��$.I�1��v�RKk
B,k��.�8�^����cc�d	����K7�,� LD�X��$}����vt�2H�G߄�~��)�I �������s�R����uR)�ҴF��6y�/Zs=�����,g9�������xNh��(�y�qI�o�X���hy&�L�<lLm*�u+��dװ]u�3a���ŝN�_�x��؝w�Y@u$e����J�K�k;6Ō�8[�,/~�ұ9�v
*漥�{�[����ԟA/DX�I��-O�m�k�[u��d	1�/\-�����n�Do���
 :x���*��z��Ӗ}y����)B��&Ϋ>PtL�\�Ƒ#��5�엀I��~�i��Ǿ��v	�V��K�#��mE;��W\�5���F�'M3��ӟ�Upes�D�k{���u��/	8^�j*�u�5J2�[V+}"^v�˗_^��m��@N�>#쌡�G־�R��� �i��� S�%[�jg�[�Q�����
 �1�kw�E�J�n �D�:�b<Q2籯�����Y��Z|�����,���4�i?*�C�4�� �+�����^T#�[gO5�b|���&z�R�(=���0�h����Sm��N���~�C�36�$H;�Y��I����n�a����c=JW��
O<H&�"~?�f*
�)M�e~-V�Ƨ�kԚ�H��UƊ�K�x0Τ�&��)���w��w��0h��̷��W�vg�1t8�uu�HE�]_WQ����O%�j#=3�m4?�Q�*���|�kf�M�6�w�Ҷ�`��ǲUs�yoJ�U���n��x�ܹgp�g��,m��`ڼ�Fu���������� W��mU�1P�ᘘSw"��R]����1S���H��F0���+�d�C��#��о�i��n��רFZb'_8\J�p�<�(j�r���wp��@�6Tv����ޝ1Y����X�.���R�g�'^��pCީ�9�,T+�����e
����P�r�R;=�l㶄��i5�a-����>�V�b.AE���?�����|{05;��^ס~��0g"
U�8s~P����m�q�2���N. �17�,{�¡�I8��v�ze3�I��Sf��٪q�8��4��w��$�4v�K7�\F��v���f2�(vg�Iف
��H���{���F�V��_N%n|�?/Ӆ��Ĥ���v�PT
+&2��)���1f���\:���U<sb��/+��Y�Vl�&��!j`���v�p-����o����i3v��M�{N��������7����&��-�٧��������a�����������L��s�hKί�YT�o�23!���j-c��w9��Y��S�ۢ�&]{מV>�f���\��[.�#Չ�Z�@2�����N�[$gJ�T� �-i����_Ԩ
�����ɗ�.ugëv��k/;�~\�ֿ6*���EM.��K��[d-B�y���q��?3��-,r�'��vN�_�7�?,S0��Po�K�o6�����b�H%�����{��:�b^�2P��JY����x�s,�H�n�~�= 
xd�0��
�#�ٹd鈦ڈد�BC3�y�єPƋ���%!	�ٔ^L���Nsd����>-�Y�,I])�1�GX�NB���{!�م�u���{&�t�b˦�4�<R̻���v�x"�i�����8	�TmQ�a �jf����Hj:Z���*�u�J<�n\l����3���v��t ��
[n:;�K'��6� B����/�88�uEq�H?f2�h
��
�]CD�{��(cK9��Jc�)��K^oCW1�-Q����)$�a���B~H^���dV�%��E�w,x�	ivը�oW~�4�T
���$"��6׫�&3I�m��>�ӗG���&2�oƒ(��x�3���x�W�����Έ^/d*��A���$j�yeV�C���m(J
c�/YkY���co��ǚ<�����:'����kU���p�I�xZ�
r��h�tZB$f9@$��Wdd}��PfJ6�s�"L{J������`y
.�W��{α_���|ݖ򴡝!�-�R��d2C�lJ� S���I�ģ����[	iw���TRT��B
�GI�s�*�uNĳ=j9�+�T��9.h�C�-�:��޳§􏀆1��K�dbq�H���Vu �>�5���,�ȞtR6z(��8��f*�U�]q�`�
�٨?N]����8IC�OEwC�2d�Q��!�\f*��/��[��#�ـ��Ugft�BZ�H-C�G��c����m}�+����G������vb���LoD�}��F��6G<�Y�~i��F��ґ�<�,�|�:��K
B�#5g��p�Jև9P���hFJ�[h�:�7�i 8A!dj�C��`H�G8�
��nfU8�-*�R�c�?�a(��aY�ơ�c,���S��
��$ʿ/���Oxۇd�魇� �G�����@A�	���O�$���ͯ�+�B�[� �4�Xn�f0vF�7��D�Ь7�NlT�H��{�^=NR���`��,��гƚ�A�7B�����]
o�rk��Fjr^,Υ�H��x�n��
o[91G�?��W�o�j�Ժl)1a�]ĪpB�lrM{f�n	�t�%����=X(�����/j6����If������x	�a� �[ܠY$��IV7��v=� �e+�r���P�,_ǭ��^�:-�$t7V��MS�X�ޞ�P�v�^�9<��N36��ĵ���{6�ZV_J��1��V��:����V��g�fȄ�jy�r9p�����/**ڝ1a!^�8��F�KTʂ���
��E��>�1�q?p"�%�Ą&���!�����p�_��Yl��^�O3�!j���:[?Q����c��9�Q��?�9 ��YO�ʄ� �N��@���PB�J����'8�%D>z���_
��y����qv6�R~�̘2\�f��N�r�	�B�e�G|M�	:��m����v?-���@�E�!��ۖ� 0oM���P���#(N����y�W��o,���L`�)�rN.��wD��J��BW?�Ir!K�aJ��j^R[��< ��\YiG�X�Dg�[�_����j	�b
S���/��g��G[��?�y��~u��x~-¨��e5~ʡ<	���rs����*T�q�:��Q4����"���OL��[��#"�߳!2_�rm�r����ŷ�;Dߠ��?jy�N�B������l,�ød��	+E��bo54��2u���ϔ&����b)\u1��#��E�ب��A�����T�-�Z�iXV;��r���ߺa8��繣Oy�N@
UE�FSnj@5���|�T`��Oܩ5�U�@4���G�%@(<r��Sr�m��TǷ�6��~e��iFj�5n��k_?*�x \�x����?Z��Ϝ��ǔ�����Y�qG��_������`��v��%�%̌��\W4^8�.�ֻ7w኏�0ǈoQ��'��UŌ��!�m��'��^�h
�(�{��:š��'��
e��5�f��N�z���*x���g`g�]m�䷷�sU�푗�ؗ)I��ŢHC`�_�L��ɀI���qr���'�	fX�q'�;}Ͱ �>P�K,�U̢"�؄E�o�KS�4�+�JN�/�BzE�O��)��a��E�f�sՑ�vβE�Ζm��q�n�'�f&�_�O���S����!Ɛ�v���4�?Yz���{v# d�RV�|Ȩ͠g$;1T�����0�ڇׇ���M�W�,���4��l�ώ��Y�@���2�
�J@�[�nG�{�.��^*�FE�b'8w��?���/t��Xh����ܭֈ��J�Ң�z��tk]e7c��N96�����M�^#c���N]8�!� ���U*HjD+�|=֜�w��$*�����+@g%��%�#�$�����,p'�#�&0g��t<5[��HP��,�D�40�qqQ�#�W��ux�u�;�F�m<�|�	�SMI��$��K'�Y�i��F����#b���.VM���?�vr�{_���ps�-�7q��4ڼ �&(�~��#K�Gt�Z�f�hmDJ��Ar��
��V�["����N�g�0��#�gj� ���Ҙ��6;�Q�������-54ԸHag
+y����*��r��'l�ppu+��*�Ɵ(~h,
�ͶJ��
X^Mr�g:��B�����'5�԰��f�]BZ%����+�� ����XHG�ݨ/���=�5��dng�)�i��������W*3�h7�*����k����졜�+>���1�q�%M��� �D��	s���1ncŐz�͒_u5���Z�,]<6��R{����?)�����K�5.���&���B�y��ؚ
�q�v0vǥ�����:
���#t�T!����D`�2�T$]���Ww�:�-rN+k�QVY|Ue�dF��Y��0Ջ��ᬙ��B��A�hv���Qe�«�Ն&�h������:(vԐ�y\&��R���헔 !'Ma��b�\�NG^�ȫc�3Ck�0q�0�t�k<8Eu�'�U�
z=�-�?I�㡍�K��V\Ά�ϹP���R*e�؇ ���Ew��r+��ִZ7��|0��ձ������;���-wXF���+��W�"|6[4��&=h��z.g��o-f�����k]=UMsC6�N���BKF�A-�Q��$r^ԣU�T_4щL�7�+<.�k<A����@<��q�[���6C0�e逼8a(�5�o)ɻ���_�\mUC@�~�<f�����*ܧ�)��ʌWA~AF��u���d�s����5��yЇzOL��uQ�lj5�R�s��Xb���b�'���h�5p�D�$�ɿqP��__sR~gBZ��.�M����_�gF��l־��C>��3�Njr\�O�3aq|�;YxЅ�8MOƛQҎ���FǊ��E ���W���^_2�5Ҝ��h��	�^�}�J��Q(_�i��\�{�C_�B���l��p
��_��:��/Q����� �\�r.E�/����Yl+Y�ƪ��۷��F�O*EI����#%�(@�A:�ya��B'���w�%OCЎ���(v~h�X���&M�a����\m\|<�}�JN�o�X���+�%RC��dz5��w�ycc����*�� P�+���1|�Ѿk*�����\�b�)<{J�3F�Fv�ܵ��QGN����V�ȴ�z��f�?��%&���=������ע\���ֺ�9���zǳ�.:)���dBeޒkCSپ/_��V��Lɂ�R�1)�	M�˙��.b�BNO�"�b)n���ʩ�Z�KF�s.yh��g�@��d߬�֚b6�Au���pq��'�&z��0�S!��
�
��\�ꏕ�\��r�" �^r;[�1(�0*[����r���m�g��c�)��E�@�BnE���h]N�-S�h�UMΗ0W�T��զ������E�h�/RA��&�VW����}7l˗�˚��4�+(�`"�T�.�Ө��L\���y��V�e��(�'Mw0a��}�٩�wcQ)76v�`�����WK_��q��32z	UH��
��l����mzl��"����&�����9	�,��$.t;&y����0}��ix̒�4{����)�y{��4��ȩA�/�˾�펁3�sR�*ZR܁�n��؟��bI�!#�)���s����~���M�\N1��rY���F����m��1�����&wI+'�T�(owx8���̱t\����"=Y6?�K݇`&O�띙�� � �*�V���H|�T;�v?�	v�>�!tU�l�I�����ww�����8|xR:��p�8�S��9�h�,�'y�^\��m�l�w0+B�����p���I���^���F�A�i���\~�N��Sqi} ����f\�a_Ö���,���3 �������5��𝕷X��p���[����{�JG����x�į?}��w�z��ƧM� 4�
f�A�M�_�`��Q�2�6)C�e����`����p�	4�18U�|��Qh����Jȡ�����������a�
ږM�l��j�C[���;��b	;��ĳrz3li��E�z8��xt�i�S=߯���ԊQt4fͯA�'d.Dpw�Y�����C%y�%�5���|-<A�#C�?�@ٞ.���K��1�dd0���I,rWA�� ��%8Ő��B�Fv��a�PK3  c L��J    �,  �   d10 - Copy (18).zip�  AE	 �Xl~ʫ,���>{����<G�'�򦺟i�8'�X,����J�m�¢ :pp��E�3gP�nnw���\�JZ������Yh����'ʀ�`�S��	J�0[O�.2�6Z'mU. ]]L�N	#'��N�_���ҹ��V�<r�Q?lQ�
ZҔ�,���#��ys��?�`��If�/:eK������w����.'j�)���,�o�i�w ���0���ׁuJk�}!��	Ώ��b*m�6�H�M���=W!| g�C�NO|����F"�/�I������t�O��7� W�2R\�/�$��1Dq{˻[�]�6y����I>�9�|"�U^���aF:�ʨ(�(
���2�:^�k$���פ�ų��D0�%T�c��L�e
�2H�:�ST���s'�Hˁx �C���R��ԗ��H�K��%�P�u!eN�,�,�7�C�#��o���%/�[���"]��4�,��/QaY%CWJ8[�+D��9j��c�l3H�/��.y��pU5P��y�8�E�Z�4�Z�vsM7�`�踢3���䇈�p�x/~
0���d�?�zQ��q�'��~�Zp���u���gkW x/�E%�ʃ�>��r�Dw�Us��f|���5��k�����d��0��%�#����hЖ�2�����"lӦ�|pY�Y8��_y��i"W���O^2,���a���n�!mJ�:���qС���a��~�e�>����<q���@ESg�e����(h��sB,��ʃ��
����v�	��Q���i��KY�Һ�P��`���̏*~�Mv(a�7�͘�B��2���/�ڎ��*qa.����"3�ڵ�Ø��L~�0Ϛl�UA�t�����R����?���K��9l���Y�w���Z��=�=�}}�#��Fd֐̱|���B>T2��2ٔ�~��F1�Q��	M����-&r�G
Ee-�.�r�>��
�5�Ͼ�U�*��(�
��F׆x�ws�Q�U]��k�׏8��ԄD�Լב
��L��M���[I{L��2��(Q��V�������"�$uલ�%W�l�~��d'E���@Q�bUN;��V�o�Vb�W(��
�Ep5x��#��������_���U-�j�gҼ�B�	z [��^l���ȍ{��b���3k� {V�F�����)BPa#����M��݂����IcJh�����.ک���i,}��{3��w1�/���Y^��᝱5��h:fc���W�nϲ�[��-B%�f"�\��=�?�D��7��8_�S,�X�2�Ԛw~#-��h� �ţ��z���F���Kn7��E�Jr��Q�.����I�`�3O�I����ۘB���g��E[4�{=dx�1��S5V��u�T���
B��0G\�5�ϸ2G��	��)jI��;QVq���0 C�{��;��+�~��כ8Ev�҇��;�I��O���*ߺ�nX�9�=���A���G_E�t�p{�� ��?
LY��D9݋N9 �����vXh�	/KO*"��Ě�;����2��X	�Y%q6$��T�{d&����K�GM�_�\��m�8h�l�?v˚]�6��4n�F���t,�Dd�8*�O-��gV#ثo������λ������t���M�������M3M{��lb�M����ظRG�,։��A\
56����I%F���~h��Fa���<�$�s;�N*H��5��	N{�RƅSA�{4n�d��i�ٷ.�\��uG���ھ˻Z�bϻ>��B�w���Q�>'-���o��A�N��L�����F�
V��被���Gm�㩡ֽc$5�ΠD߭˞�H2��T�����$���^Wkw��E�GY-v�JG�H�I�q�\�B0W���+Tcr�1�����x�ܒBq�+�iی�)�+ޔ!UZ �I�r�f� 4 �|w//��-�ٲ��EF����=�T)	��g.��r��7p�|uN�(�Lʻ�"���81��c]	>/b�)�
�bU���>
�
���+�0˔�JT��yS��=�xz2�dJw�"̊�y�4�������y�l���N��4�S����1�>��H)��,:���r��gɾ۶��)������V�����#�i�0�A�I�[T�Ǽ��,*."�9Œ�"`�-�����
��*œ���UF����bb$ΕW�8:��ߧ�n�"��k�ݞ�̘=�a1�2�H�y�nՙ6���Ǉ�f[N}�ʖ�4|>,!�w�{1bL��5���ilY�.E�ve�M)h�X���Z�aB1�Ў�j!`�SL:�5���԰z^��t�ð�G�R��b�S�	��	������s�a�^���γ��[8���ԗ�
�_���K��L��^m�Iy����3��V�*�P�������4��*�c�q���(�
qnanA 3���֫�q���8b.�ׂ=.�0��Ć9�(2�c�;_W��{���YU���i���-a��>���]�"�w�ĀV��\k`�}��<����%R�'/�ʉ��%Xz$���R�IkY\8�w�`��/�4�E���xi��B`����?�<ݖ���s!b\��Z�<:=�J�a�
���<)��z�
j�/*�T�S�"��xP^�W�r��{����>ǐ�)�ʁ"�7��0�H�
�۲iڣ�|�m�3�?���Nš�&���=1Ab��
�! ��E�9�<�`_����no{~�fd0��rlRK+Z.�Cfqe�k��lY���x1�����|U��蹻_��h�� ��$	����E]wD��:1Q�H� n�h��b� � �ȑ�F1�y$f,U:3b
���I��P#��I�\���rYE~�r�E�w|qo:p!���p�P�����],+(�)�(���)�!���Y��w�5W��G��'��I�!4
��<��3�=�(?����%*z���s�0�J�R�����&F��#n�X�$t�x�)F��ւp�������l1K?���so*p����F�6ˉtmB!%T`9�F��k��<��y0	$�>�S���Rq�λ��ja�ΰ{��l�s2u��P�1@���l�H� �S�z�,6u���J��/�&^׸��e�k(s������@�g�z1lK��ע&�8�U���wpY
M�k3���x�S1;��p��ǘ ����%���I��󗊒jX�A��6O��5�^�j�>a~��Oڮ��Ha�R(_>J���B�Ϋ�D��|���H��0���`R`ww������=GYR)Ǫ��Ae��|��By�S��Ѥ�>�/�lP?����F�Ԝ�U������9�1���A|z�̻U�&d<Y�v`���x��e}��F�6��o$Q=蒖���F���҉��9T�^�h�����B�ņ��h9��T���b'K���tK����=�*V����Ñ��	��yv�����67q��6C��Z���qv�����(T|L�k����1y��`�aߖ�h�^��OJ�ЄФ�'�3&.�݆��p������p��X�'�j�C�T���<���$�׻��}�a�oF�`)�f&��[c��@��������a..���5�Ua��u�qxH����4����n<�2�992�S��bY�
��~S�Ѝ���PY��ж\"·��J�O#{Kj�w����;љ�^ir;Pz�}�P>
��,y�bZo9h\"L<-jSrN�ۄ���U��(k�
��-�j�W��*$�͟)�ec�s*
��&�а�	�A��R���t].��9�ŦRKu�ć�A����7oͺ'�A����'�N��Td�������<�m@SF���%K��n9�n���]B򻽫�6�@�FN�Ȍ\o�6�)D:6/L#,������a1R��հj,E�G�@���n 8�R��]hg섴�7"e�,1Vn7�
߫/�n;(L������efÜC�Z��*H�p5�=fQ׿��	�@���ݸ� �rd�5O����1�E��&K�e|������2?taw�r�W ��?���D�f��K�����*���e��Q�_���|�
-]�T�]M�@@���f�C�ä6����J�%L�[x蠒�N
j)�9��w4�]���͐0�*�]��N��3� �3"z��H�*G4��s���j���[
˃;���8������-���ڤ=�/q���B��2�a#�$�|>y;�g����ụ�}I�6��u����G����1���U��a)AY����O2�����v�,��h&� �e$V�yV2V4C)��;&��ۼ�6�!��f����S�[�Z)�#륊���x�����.��h#�#�ή�.?��B�=B�'ӇbI�r��m��q��h\,�/N���a����H����BY˚x��BD�S��8vDc�|.|m�YI�<u�Ka0aN�Ν�@�*���ar�2]�k�c�'�|�몴9D���L�
z�Á�J�)[��ԓ�l�2�a��S6�5���G���7�Р�3��*(x��m!�����[����GvheA�>�9�=�s[��h���B.���H I>*d0b̤�C��yaP���
R.>_M?��Fl�(8pRH�p�loP��ˁ���Q�jƞp6��y�L��^1��A���
ܩ�4G��[�WM|eY~�(�[�Ȱ�t�W�3�%f����I
#���W������eX��P��?^���B�"9��P ��j�a�p��g��Z/�����I�rb[4�ۂ>�+�Ačqi��j^%jƢ��o"�������V��/ܐ��e�ι+�YL��3�?�;&n
�q�@�c�H��Ͷ12��sCgh��P�>�L�f�B�;rUG�$S��6wvS+���j��C����Nİ �X��i�� �p��e"��w��D� $���.��ʑ�����+k�-U'��ī����5��Sp�I_	%��ƠZ�m6(�� k(q� �y*�Z����\��d��[�{`-FZ�@�a���y2�~�a�,�U���uئ�|k 햔���	��jAx�	�
u�7^F["Q�2a�-�1NB�*���'� t%���yJ��Ã4�FK��`B�$¨d��ϵ��6�����&��SZ��A�o;*��9�&h� ��\���!��V���3}�Z��	#�pK'{ל	>��|�^�l�%g ���AU6{�3 � �9�߲t�]���8��0��`�K`�j�����o��T��z��iJǄ?!� �!��xי��w���l�����>�8��'��җ�z�C"�#&��'�t�8!�o�16��r����hc#�g{�=���B���h(�h����vV`S`~O����ipGN����@|>�XH$�Im�ı�g�
�Eʏ %��a�\
�w�3�@-p��#5��t�+l�8���X}�	�AZ�0G��zڼe��j���f��Ao�-[.�*ɲ�}�6d������M��$�̠�*�}��m��#�+J�,)
$�1 �(��q��.�~����/�մ�� ,���h��Y���)>�b�	����ą5�t��qC֠U�5?�0�UrȮV`D���q�{Ӭ��@]I���L~/Ce�|�.c+� ���N>�h����m[�����&i��]ލ�γ�b��4X1���|���
C�Z�j����ZA_ʘj˲��w�A=*$
e�g�G���]�)Zc�ݩ�g���߷�Ѧ����Ajn)���OZ��]CZ�T�>X��.p�]q���ӌQ����7��#�Z&i�~E�T��o�]���b/Ӯ&�C��A���e�[��[�ɡ��(�i�i��P��Bö��:,�W\�±0�*��/1�K�zN�9F������v��9Gt��B|Y���g�Y��2b��Y��f)��]�O�}oc:Ntev?���}�}<d+<ҳ���u���Ó�jQK��tP�x�~Tt⃑�7�ҕi���E���,�Ƕ5J1�[|���T�U ���_� �9-3��c�J���D1���lk��$���l	N
>�d/9�9m�ZR�X�#T��w'U�Y;| ���f�i/8��S՗�T�(x .��6&w��|RAB^���$�e����d.�u�R0h�#���� ��5���	��kym!�����/�f����z�/"�}��>�帑��	�6��Z����ԡ�����C�r�arL
���Jq�`�1�<3�߇�e�V����L$��o���o�p�%X�m��d�l�Uz�Y$�ɥ$*���Q�'�Q�)���cfу��
���_v�O��+{H�.7�O��&벰��!��/- �kl���E��}����m\*�l.6޳�;��@�MI��Xї�o�M��:/.��)}Q��d��@�qs� Ԯ��e�>������Ks.<L����} )�|4���Vy_V���eY�aU�Y�f���I���g�b#d)̐%3�\�hsp�2���Qw�����$�ip�̃�
 4�1� ;$e�?ze� -��BL�t;g�׻�x��5@���[5�2 ����<���W����$�����X[D�#hH ŏu`��߯��Ny:����f�.P)*��\�:*7^^~��p��	�Y���X'=��Ց�K�rt��=H�2q�F����3����Gp�����d�0��2�&[Z���=/Ԧd@��>�>�#�T��8��\W.�(���7W��]A<��F�X�j�.W�K��(���|�9��>>i�*����^bZ�b[xkD#WX`亷
�����q���
,��Mؾ#�f��� �ĝ�?i�����xD��T�
O�׭K;���F��@�u�&��,�YY��"��93�j/�����������?å��ᔧ��y�/E��G��iJ]�������[���
�B �IA�h,le^��F j&L'dki2I0�	Ȉ	��s"���� Ag%��ȼZ>C���X���n�*NJ�!1ꎓs
��%\2�>x��B�>�-f2���J���7��|�6k�9ŝ�b ��C���g��_`l���;}�u��
U���'n���웨�I�|�Orj�xt�z��^�B��K&0((m��D>M��N��kodr�I���vc<��)pq��8��s[�_OHr�k�C
&`%_�
W5�JRT���O$�(ݚ��s�^"�L,^8وy"챾|��~]��&�h��0FrF��0`҆N�
q�K�
�If9qmA3:���	P��fT
8`T`�a:��k&X�F��g�#2G���/��#oJ��D�`�B�v�vR�R���(|�}�>�~��>�E����! PH�.���j-29������`-c~��0��N�H�j���L�*��u�P�$��f�q�'[�����@��<f�i�SC����i���J?��Q�c�C�8
�����]��'4����Z)���100�sd�kK蚿[{3�6����ͧ��OG���d�V��zI��a�����N��t[-XAErK��D�|jl�h�l���n�N+��4���ٵ�MSh���N�����$f��--��ϖ_��-���[�ݜU��5Y�FKf����jğ���M����fmO��%��?���Uk�_21_6��+����ܧG�މ���._�Э4_]�uA|�'X/ؽpNɭ�Y�Û�{y���U���NJ��^� �̃�e�b��x3��l�>�iG��m�"��\yF漼p��:k0���֞����U�7c�^���ۯ�֙HM��6Q����=�>�ŦPI�]1H�1>^��O�Qb��=������q�h���fXG��/��69��JC�X���R�ɝ�P�a�LY�A'��iH�QlfX(艐G��x�#6��Zo�WYX���5���i��^�2�� Lݞ&Mֵoã���p�$�I��A��r7�wi����Y�+�7
����Blv1ڳ��>,%Eg,���6�;e������xg�t���D��P�ɮ��gk񆦐7�2N�{�2+A��r)�S��8FF��3���Օ1L���ݚ0��_zů�M�����XE(��e��7�ʞU�wE(�����6+��r�Ym��k���R0����Sp>��E�����C�RG��S�GR&`���r-�޹��Ds�DD��H�|���w���e� +�
	���X`B-����?�#a�na�[��L��߶.��d.zC�=����]꛺��(L6�M�ߘ�9Z�����(H-d��f隂����(���R]l������1Eݪ/�>90hNW�r1��zw�>S���t�1��5��2/��X����Kҕ<�Ƹ�8���o�<_��+㬾i��P�G�a<UQLՍ?�D�H��������͐c�ء��"[��nU���m�y�	c�o��q*'T���V��xe1J�U���zA0$Ȓ��J�s+�Nȁ�P?�MX
nᗾ�z�G�Q�!�D1�*�vBK+��nb�
TŅ�cL	d���cRIvq�<��8�I�Q�c稜&�#G�9�W!��!��c� ���]�
B����%��M�GYG����	���h+�T*�A��>8V��¸c�Rn,��KZlN�D�]��Vi�� ��[�p��������r�Qt6����6�{Z�Ȃ���	q75��M�� ?M=A� �&�sd/\8:޻ �-��A.B%-������k
7���^$���+"r��T^{U-����,����0��/�q@�'���9�ڐ�916�X�BT���-�T$[Ax(�C�M�UY�)@���֠��w T�LS�������?Z����^]��W�GxiA���;��ň}����4:�A�mI�P�� �DH�%C�x�a7/�-�Pj
'F�ܼ׾���s.�� =���HNi�c2�@��:]��Q��A���H�jZ�����e&��w�I7����/�KQ �̄�<\�}*̒ܓL�^��f۩?�jW�8c b�B� {%D^(����B}��S	-([$|�!E/@L<�V��T������A��e��3O�Y��}q�d�{��K�vȵ+j-8aם�� K��Y�ndŎ��6��ݱL�u��Z��ќ�T��!_�Q�_6��<D��[a�'.��3OE�n������˅y��$m�����2/��~T0d����+�8@���>�����Q�fW� �g?�<�V�AI���b4@��zQ��Ȍ��Wd2���G̩�K���H\@D+��Cݣ�Qlh� ��0ϒ�ڄ���j=�� ]��6����EeMMg�9�3�.�&�A��!�I�����կ'����;� �M`:��@�q�����A;�g�'�5[g���e#
>���v�i]�AV��^[m����񲽽���I�C���[U2��T�5���Xnf�C#U.y$���9�C�b2�
��`TD����(6r�|xX�Z������9 2�8A-A��4p3 AͶ�R87�`D6���?���n�?dZd�����a�ǖ&C)c��(�X1gq1oЦ4dj�>V6�Z4+
��e�S�>�F ���	����J��n}���m�;���=�o����:_g�N)L����^���&p&�Qk�)D�,}n��,�F�z$����T��4F�>;n�at�8㠫����y���ߜ���O о1Y���q�	�Ʊ�@����W.3PS��
��r��%(�vP&<H�&��*FpNm�^���.H�>I�*v���^V!%�Ŧ����x��b�w�ˬ�T�>�w�r��!�
�@$O�?]���qދ�'w���+M9Ve��ZN��֒�K-ģ[)F��	'�����;S�J29��b��8�E��Tg
�ĝU�J�/F���pL����5)��*_����B�\XSa:��m�6p�wi�+j�x���˪K��n��<����h��O��U �f�������U�^_�F4���&1��{�N�No[�x�S��\��F��<gKK��!+H��w���([�*LQ��	�A@�Q-����OI�݄T��UK�I(�0[�U��l'0�s����}R3\�X���M_x��F���b?㰹�y���1?a�g�
B�)�׼:dC5�=���t(�Mʦ�]���5���R�	�2h�����������q��o)y�mA n��Ͷ��*A]r�,��۴ln�?�s�A����2��^jO�n �^zY�p�z�I>��d���{�\�;:c�w�P��NO@������0�[Jm�Ca�$�O,��WkQ{&*��D)U
�����t�ރ,{� ��w+�C��y������[��g1�Ƿ�TNŪ�)�M�=#i~L�z�2Z�m���6��-��)�~�e܂�N�����3WHH��P6�Eި#.��<lmL%��u�th���j��P �ì��DH�q^�_�5�G�B~��X.��2c+��}o������-DFK�x�O�ᅿ}a�7��F������X��λ��	�
�R\fѬC���3�������F�����V2%�a}GH֣=w��.���[���\T ;z ��O�4?,�kF*pa$����F��d���7��P`�������f֤�eEt�R��
�9���t&�i�� �X��#�����nXT{�@U� (���X�i�-�e�l+��S3�?���#�a.^x��,D�"q�菦�e
�������Ez'�]�#0IH�$�(���lt��A�R5蝳��YV(w��� y���v��xO�a�
Y���)q���M�ީn���D�9&ɞ�˛�:�m��5P�}mQ?�Z�B����Ytul/�cy7W2��Գ���UX�_�/�b��j�0UQ8/6��h�,�V��D}n�E4aE�{��qs�P4���W��!�Sq�<�f�QozH�9��OMa3�z�2�%�N�=0�/TXv�0ɧd�⨃`�r#��x*�f��Q1�t����-�/%��|\�L�7�.�t<���d�㏃
�|@�v��R� :
�1(Ն��`_(��1��@��f ��H|���%7 �l6	��;E��Ŕ�O��
���^�� ��)�,��,P(Ie*��+c��>[0���`�߯�aѾ�k �y!ίGQ�1�)g"A����g��H/n��I��6�$e���.��e�����Jb�k�� Z��8p���V9q���g��3?�e���^nW���{��P����X�%bp�IxU��y�A��nk,S��5$�	�ʠ��{�IԱ 8+e��.�c.?��C���׌��u&�xQ?fH��Ֆ�F�ϏT-����	��yȘv�&�Y	oM�r6�Y�EU��p�%�]4��F���Tٺ�X�1rq��3��9�"�B|�
Ȭ�=C��%;ʷ�-��׸��a6��7�\�yt���Z�UV\���t`D��߅�u[� ��畯�Ӽ������Avt�mp���v���V
���Q݌1���J��6���*.��F�_�El,@y�
y��4"�L�œa؂�Hϵ�0�v�~N�����yx��h�x��	)��z��
T�XBhF��6|Fv���lC���1���n8H��moOk�
f�������kNXBo̎12��pt��|�.�c�,�.��/��Xi����t:J��j�Px
��Cm�[��ƚ$�	���9ݓ��f	L*E��:{��+$����A1�rF`:׿W�s:�F�j��d�������Iҗ_PK3  c L��J    �,  �   d10 - Copy (2).zip�  AE	 ?i�?n$�}�Y����,ԯ�5=��(6�}����z�c��oZ��<-t�~'��&˭��5s8{���k�q����l�ڒW"A�(��sWn�=��[N\��"]2���t0��I:2�)������./�<r��㿠Q��'$�V��V����Zq�pH�k[b�ɑ\ģ��s@	�9��>&BԦ
B�+�(LKW�`+C�*���U���;����e�f����!����O�W�͖���T��=�u�<�4��U�+�~V�����x�l)F�{C1�[��O��l��О`�_
x�]-1�:S,uG�4S�f�����f�+V�oWv���T0y�?4��q��g�M�8<���y��Ͳ�����Cq�)V�^�<{rM����{۰���D���{#_�	��5e������xȭ��`��L�6;
S�q�!�M-L�q�Ω� �!YIۓ��}�T�&��nD���������3�W٭��pNg%*� '��1���V�eGA�
:Z���?;�]o0�6��E��%���ש�/򌄙
�R� e��l�gJxϊZ��ʆY�f��,�4�k�'�n�ٴ6=���],�����3F�/��>��PZfFO�sG�?1g��A�F�Mg4dw9�
���
/9����IBS�%%<V��?J�6^��7JP�T�n V�Y
��Jd��yzۛ]g�ؐ�#ɽ!�%�
�@���l��t� \Z �Cmy:A���1�{��7�O���M�U��
e����P�8�j��<��J�"b��Oٱ�/��I����K�\Fv��l�ކ
9� L�IC�S�|�׈!�� ��?��=	��;��m#�?��Mͮ��Я;�OR u��E��&�{�Rx��E0d�S����h��?�ʶ�Vӭ�q�+����C�'�?h8�2�~�P4�����X����8m�~�z��9�*�V�,P�hg����^�p�+�;���ϑ��O�၄*�3E��p��3^����M9��!6i��TU��ev����i`8��,̟�n��V�O4%0���܀�\��U�4@L��G�:�.�|8�Uշl@Or�I�=�Ŵ���m��d�Q���+�R�\6C$�9=>G�'�Q��͕N/e#�{��Ұ�M`�tA�M���{�@����QB���8{��Es�y�Z/:Lw+��e�B�]t#�`O���J���
��cF��[+RǭG_S�m�n��2�	z�S�WH���o�p� ��8&�,�A�
T�9r���;]|���ˑg!���2R�@r�L��ZŠO�+A7��hר�c�lA�䋉˰�ь��'IǪdLS�9@���N-���6#���'YU�U鲮 Y��2:Ա��.�����G^�Ls��WD�-����&�9��D���#ozo���k{@;[����y1%DJz'�x���i���NR�Ŝd���'��u�(�nn�ZH� dv0ڢw��40�AR�u�ֳh��ꖯ��6<*��@�N���%�߻<��H�̽U�w� s�!,L9�ƫ`�$/ށī�������'�P����!C� T\?�[�.��}#��Ld��-���4D\�*O;�G#ԷN��67����@�����G�QO.g���ɀ
RJ���oqf�ޖ��L3��j�����-��V��Ho�F��%�'��Ng�@B��K�g����l:�G`�m��!�r~E�0�\M�a*d�`����Pі>�2�;�$�nQ��G¼$�J��*/j@ 񸼺�Vs��cz:av�"�HK��7�t�d���̰�c�g���! �3V�(rFv#;�jnܗT���9K����=k꓾��G�C'Qt�.��5��R��:�Gjˢhڧ���h�����Њ�7��m/w��r���4�+s�s� 7�yQ��|�]w3/�j�����[��VXP��N��@�Z$�In&s |�;�pG����[G9�&xc
FH����ƠKW��
I7�����!Z�CF�|NA�ȅ)nX�Bz���`�Y7w�S"�����!/�k�{�`�C���1��S�)4{�5
��u�u.��ZBw�,�lPV	�N%�Ƙ��;�Y�p��|� Wb%����H���%�VN�_�C����7��`�E�Zۀ�X�q�"b���H���yf��I�dx�䘔|6~�`w�p8$�U^d6�
���kANa��\�	{q��af�.����V�\���ߧg��t��ӯm��j��A+1x���PseTˡ>���_�Z5�_Բ#���9}hRD����]�ԌuacG=�$	�c�=q���.��{�9͚����Up��J4k�
a�$��P����x�k�����]����+�3ٛ�<�n��V3Tv��e��Lr�7�!o����]y�m.�z�xX�JZR8�w��q��T[
��X�5�v�B��~~2uu�*�f�}V��v���+���rf�|����r%�,Gd�-X����ZY�c����z�	�脵�28�;��,'�?�ܣQo��<�ɑ7�r�O�y����8�c���y�P*�Q��_�O=�_�̡�?J��M��^x���+H
\f�u�xҫV���EX�5
Էw�o1ڟń40'��W��W@��|N�����gzEm�!�n#���Bʹ�Y�y�H�00�α]�|vV:	aQ�Q��^�hB�;�:ɓ�#����F��/#dr1�pק�D��Y��x@�lEP``3m~D ���_zۗ|#�͞9.��x�y��޷�O�iV)� ��u�6xm�y�&���4�椏r��&���"�@���>v�o��8�=b�[G��v�=SWǣ�g���dے;���V�� ��C~ B�=|7M�x�V�_�1��Srpf�|n�k��jV�C��Q����
/��Y��*2��wO8n?�����ُ�Y���+P^�)����#����[����� �|�3+��ѝ�M�o��<��l�B�;�y;X4'w0�s
�����9����P#T�ꀂ|����|�h���]���D�~ z޳�9�Ф��[!�5�F��O��O�߰P���ryů�/#�E�bɰ���W5v�HP�+(����ĺXv�k݀QK��b�$4�`��� v���T��;<��.������-+[��wxz^���Gr�;0����h��8�I:��BX�:�X�Jb�+��n&��Ep�o�C���S2.8rd�ŪU�r	}�<b�ť��|e'(yW8܍�t�~�m^"���w~�ѽ�O�C�%��2��6�F�����X���vKP�Eg1y��?R�&�]<��$�x�z*����|�|���$xh]+r�t̯>�Xa�����-J=��J�=\�8Th�S+L(��03�e球G��יB��`�_���=��
[S��w��N�L�7f�H���kMuPzf�c	M��}�*�d�X�'
�"��y8O��͠����\��3��'��yI"��`P�6�Z���K� ��l���r��*`ޟr��)���]Y\�� ��*���٧˗�(	2���:����]��
TU`eJ���n�YWG�$ڈ�3!�sQ�u��M�̾�J�WY���
7��r���� ������gf���\(��Ls�����Q���f@wc�M
�[@f>�����I�^#3�d�A�]w��A�
TI�q4�����^m��-�
�wT�k/���ʎ�s0�����D|R�ֺO�t�e�<�
^�%b'd�������L��3�L;��^��'��E�Q�N�fTH쿫������������xG .'1L��T1Pӝ������d�*5C%�����6�2%jv��N���YX"
�Z>������w�a�N�����*Ŗ~+nI9�Ҙk��(z��ޘ�q
%������0S�c���(}\�j�o�	Kf+��թtP� �W{EP6yH�ij�2
jf��G>?�w�k.��ԵՐT�okSr0'e�U�<���tp�Z4rP���.a�ʑ��cV�ږ	h |�2��G�eOP2Ӏ&.��fZ�"V���1�\~�ӎ1�g#�|�w�b�����?�^T�:2��`
n
��F��NR?m�C�J��c��Y�*����O�t#M�i�l��g��e�@6�R�,��%sD�;��O�����/�T��R�E2qt�l���mD%��w�����{uFg�a$�b*#�]⑗;���4�|?��)B^�b�<=T�	u����^�Q�K�����}e��j��_onPc��K2��k��ހUW�eky���vZX��PN3hůp\7�(��	��(_�{6I�R�V�eE�e����P�6�����L[V�q�VܴtZV5��i����^��6Zw$ׅTqW$�3��!g�[3hQ��B��8Ϧ���H� �B���
7N�"��N�D<,��k�+d���7�.��Y�pzο��x=�
[;��k�S������%��!K0l��ݰ��D�����6ׄ1Ye	�k�C	��5΋�+V��e���m9�qm� ��(��!���F�Z�&�l�Ç�c:i��X����L9�L��Pa���j�!�ĺ���/�bPRlhY�¼B�9=6�P0��s�劌�9��]�����!R0&�'�F�۩8)Aб/�=��w�m��N�N[(�=7sz�Sa-x�n�Ց�8t���1#.��~�@��������ߴ�,`U�}��L]*�ʖK�o���w�DA3Ys��O��t#u��A���[&ɸ�+��{T0�sý���X�p��l��T`��g�����
�w7Ìq�z�~˰�'%��x��ݙ��~#��ϙS���1��y@ ?�&A;G�qz�l�y�eBi@��Î�AHO�:��-R(��R�&׶���6B㭿'C���	2r<4Q|�lX�;�ǅ������4;� i��9�ލФ��uֲX�}���;V')>7���c$3�Wlc�V�/�i?�4�=�g���)|�=Xg�U��i%�L��,�g?s���0�wo
eX��G[��F=ke�x";ۚ�����+�rC�TJ��1z�	���i��Yz�%����n��1��#���;�<���M�x5�Yds�L�1GV�����ɦ��C���N�a���t�o�`��ڄ�Z�-g�1u�[�����|�{k%�ń]�8�K/��^1��5n���q4�q��s缈��g²���0����y�f~���3(��]arW�������'��u?��+MV��!��q̿!�xB�������H'#$(*�ᥲ����J�A����{9����)�A"�N�SXu9i��Мx�tu��{%�d��(�crؼΘ��G�*��`fOu(�� Dܓ��I���a������(>D �=﹚\"�L�E�����{�W�E큭�7������7��g��L�!�}S"���u7N��d���.8�B���$���^+`*���T�c����I-�T�#MJ�]L�� ��ѫӕL��y��M��>�6�O�	J�xX�NN��ˣ���s;bB	y~pEū�5����1��������\\ޞ��x�/��2@6�(
��۬S��Kǧ��Ԭ1�^4�:7$F(6y�_
G�<���Yi�}��CʤWz�j��I;���y|�j�����p-�s}y`Y�`�2���A ���[�;���9ѭ)3����M��]{�?m)nQ2��2򘵊���)��fe��7A�*��N��j#j�}��V�Ʒ�0��Q#��`S�g݀���JYjN	V�A����F����8J5L	�h��ߏЬ�P�+>��|J���!|�>B��ut=+����Q��%���nE=��1xB{�|D�c.�֌���<�T�K�4\� "�@����ٌRp�#�q}"E�ih�v�h���ə��X�O�	T.�C�S�0��{h��p�R�\g��ԸH��P�	sQZ�Fl��	 ڷ��x��㗬�9lU�k�PK3  c L��J    �,  �   d10 - Copy (20).zip�  AE	 �F���B�n��Tp����g��R��qF,b�
��q0����h*G�6k[�+�:uF�������F��I�)�0��QB�~Y�u�W��� ��+��
��:��cvJ|ŘIZĚ��=?�Z��:"�ۂ�2#�G���S��no��e<O<3�KB���_���o$1,Q#&�E���HoEZ$OD޴sC�*�1I#�.JݢB���k��
�h��n�sh�T	��p�˻��Qd�r2�SfW��S�l�� �XƉ�L�=���K_�p#C_�-��)��R�����
�pp�
���=�%�����΅N�0�+�+c�f��Zӻ�����Vp�IN�.�I@XE6?!��Xd��ߨ�WS'�S�i^_�`7�E�����(�_����~�v�y౴�2�y@�/���.�K�,��(�M�b>�X�a7k�Д'��_�Y��\#��;��p��"���c�H\�)U�O�+��մ��P��_e�G���M�Q�!��z8�0���n�����l�5�GWv�T�7���_�V�~k��~]5��!N+�ɦ���M��T���
�,������P|�R��c����x��5��l�T�h��ʟ�XP��*]�mQ�9�g����N/�f�
d%��Mƣ�5[���� +��s��!���b)g��0E
a��
�����ѝ��'�&��R��Q��8�Kᾁ���\�L�AW
�p���[[[Xi*-^���$�_�]�-��@�#ްs���U���܎��tϭ&�<�E�O�h��D>e4��eT������C�E� ��s'���J��+ks�R?l�I��������Hj����-���P� �P����0��W�5����䍄Ɠ�����X��T��NA�!/���޴�μ%tS�RU�)��,4�w?m��Q�%'�b�$��X7��kZ"3���|�T��ʢ�T���f�2k~ҹ��1ht
{�̡k�^�,_-n�d[zh�(�T�7;��:��������h�6lW+�Q�hm���sJ����`AR�qAUm���$ٵ�4ޫB <>P���@�nr�1P��>�A�u�I�}��uO�8��f��
֑L{e��
z���I`��/�e�˿���ﶌr���YS4)����*��zC��dͼ�@�Y�5F�%c��Ӝqc��զ�?�am֌'X����5�l��p�j�`���[Ҭ�l���%���"���{!r�L�=�?�0Z��N-�a�@\��UN�ؕx�[>A���4T�����U��rFK˓�����AwAk#Oi:� 6H����CbVM\ >E���S(��[�'�^9�G���
d

�Kw�I�Eޠ�LS2Mo��c�K麤Gsˎe����W(��+vc�Q��׆�ͱ{&��kٟk-|FV�h~��Q&(O����&�d��
����q��]!"7.��~l�;؂�T\t�|�g�D>�	Qʂ#[G����S��;�UA�`#��Ew� �j���E+{֠a<����K$}u��m��	o��_�����~r�soɾu����U��
��(�\�+�{�n<SJH�=[zw1Q�*���9w�y��!*z��fY�"
u/P�_>��9��(r1�2qX
�L�
�Z޻�W���\�Qe�4����b8 ���v������uFI��C�U�EYn�,3�ո(ᷝ�0xa�s?L�8��s�7��4��)�E�� 9l�R�r��g	LP��`�v����o����K(f�4����2��	(l�d�B�J�N{�أ-6y��e�
�v��VR�������:�6�r�PU�~�qA<�ډ`u��u1��.m)�dy���3K�.�U�s��������Q��g���&��LVIk}��;���G��v�p�S���С� �����Z.�Vb,�oHN�0"]u@8'-��O���Y��w���ֹ�	 ���4�S�� oK��o�����~r��Hw7����':0L{m�bEu�	�@��[W&��D�h!ҦБ+���-����y�8���:L���j��ͭ��3�G<�8-A�SyJN�tqC@�����M�s�O5�{��zeIm����sQ[�u�����Qıl��4iC�n'>>EӶ�V
��;nۉ���H
5-�J��ߛK��P��%ۦ�!��
מ��������u�.mR����9���8����'�n���b,0ñ6�)����#��v�d���(��D@s ��/Z����W�R����p�@ ;���,0�%O\��w���cC�l�4�p�;�$�%�7�X�1�Q<���� ����o?�O�A��"�%�\)VNk�x3o���r�s$�C��P����v�x�|]V���(�q�g�oZ�.��+ߴ�Ds0i����ME���qo�\{��ݨ����B�!���;�'���~�(3��)���"�w9q�y�<�%�����o�6�"B�V��!�]}�,7��g\y2�?*���3�0��aU9Y�ԛ��J9�2�ԩ>�L��)( ���Q� �뼊2X}<�K|o����?�=��RT�Em��=��יѿ'�i��?�"q�ZP"��-&#Ej?U�����|��MI����K�"��Ĝ��IЙ��rƜ���\�{�,���		�ĥ� ���~�v��?}�
��p�C��V�S�L���`�a�{��Ŋ��
D�km6~�jM�Esb���d�����+0Q�l� 3[[4�X� �Q@�D��B����ڑ��h#1y�o�ק���Na�6��b�u���*6Q<č�Y[1x5�I��iy}6���[:z��S�x1���|�Fa��W�𔪪4)��ݛBM���p9-9�'�x�m0
Y׍(|�,ޒ7"\���.���yZ�-dK^�(�0���P�n[�$�C�0���q��>!�M�bXѧH��B;8��`ί5'L�p�0�Gi�P����Mcy1��>5�*�Fc�{V��p%<Hy;;�#�Ղ:��&C�#��I} ������j���D��qa�a}�e$�E��|�Ǡ�G��ٓpv��(:[M$bKU�FZ��(Ww��DmFįX/���%����Z�'�9S�[��i�ZДv
��诩mHEMG�ߪV�+S���]U� �~��sT� Jx攉E��,�غ/,N�BP��#,��#űi�E'�ב;g��Ё:ڲ�d�\��G���9ۧ�
�
 ��ft��5���q
�dS��)˰�p����'0����k_���p�b�`6�F̓��z��;;7�^(d0')W�΃����3��Е������p����d�:�Bf��|�}�.�c%$���?>g����B�M�B�����X?]4��5f����|[�f�?��iOQ��.T�E��8"��!2�r(����Q%�W�&�_JҔG���e'��Aw�������I�����S9���e�={����)����������$1��'!��&��<�>��{.BZoW5�DL��jΐ��ǁ�Ŭ�m(I<I"� (��t@6�(�A�м��<� ��2�mN�<}��
�ڐ����c=ߤ���@1zGȳ�ߗ�.¥��{��
N�x���o�߯�@
V"��~mNێ���P���`6�P��b�YAX�}®��FQ�a�]S7���ʍ��#���:�'��w����1d�)}v����U�ͲA?��>�����x��8����|��qߺ�ȕ侓DAn���tm� ?�&�	�����ZI�C�2|�\����VV���|L��Kp�H�4��Wd� ��A
ɩ^�B��/61��h^���X8pb�"�)���V�j�Ww$���=cl��\� ��<�H�d�G(I��Gkn��7y�:��IS��O�;!d�y�2�Q���j�������u��duA�q����qF��}@��_KF�O��v�f��z�'� �.�)�RJ���E�ݶ�0�#K,����&�Q"c}�g����{<P�	�ۯ����;�Y�R��_Xn�k=����qV��)���sp�w�<�&%����,Ŏ����j�V��4��	��c&�w��0�QaS��iU6@�|Ie��2Epc��:�L�G�dWk��?[z��c�r���{�f�W���'!x��	.����ŋv��ջ����t:������/*]AJF/�qڮ�8��f�B{Ɛ�u[FS�:	�����
7��xc�v�I.��R���b��)
ƪ $P�q�M	�T;<�J�c:>���++sCI�Z	������Up{OM���" ��g��)��yB|������I�m`m;�6�bF!�r\2J[�F�۴��2r�L���F��1��3�#3�.4uJ�=V{	�,&f]���Vŗ4.�������/��í[� ֿ�U�V��.;$(F	?H���9Z�G�4�_¯�9h�G0Jr��Q�bu�A��]�|0z��?6��>Z�6R. (��=�����,�=K(��p��q�(h�%�Co�w^�﫸����W.���U�h�T��2���0���?��������߲{�2x�/�Jl�B���!H3cn淢�~��C�1���L���ZV��z2������x�~��O����L&����
;e4�l'�[
��.&"��?d�9Qk{P�9�Z��X�zW�:��c���

/��$������Up���tb0�Ū)(�f�Y��AB��B�<XM<��A�h�ȊS��R�6�Hu@��}@C"�V��*�a;��8�+Ű��0�&q�ů�G9�:�U �����
񓙡�.�U*~�͛��� �{,�D��"4�g�R"�szk���z�E{$����[��K;��7F��aH�u��x9A�^2�ڡ����
H�UhM�\@s��*�<���TA����U��5O�
/�Ll!o
c*V���v=|�;� ,�?/�f��� �vmγ�HfJ�`<�� 23�*�X&ؚ�ٷ�#�g�B�OZ������o^��D� fU��/t��G��k���Ϗ)��u���v�=��{2�Wo�UY�����Ω�bpO60D�4���n�	N�b���C3�,`�
��(k0�^�y��0`�,7|�N��95EɧG'��$G���H��	|ɳ'=txn��&_(�ձ^+F%(6=�����b籿����0h��/�%k�.��<Xз�P���БH˟k����F���j2c�V��y�������[�3y�w��7��
��#FY4�kcԿ�;Rr���' ]gZ�4�A���X�՛֎r�bD(�ְ~�֠���n���pmS%z�s-HMݬ\����-����z�f8Q;>+n��Q�W����Ӫ�,��P3H�i�*���<���>�\�t�P�3c�
=%��F��U�Oԉ�0�0r��n5��$WV �0�Z��;M����;�w�G�C?�
�����_�i�]Hq�:��Վ}�ꧦ��խ��
8v}z���^�N�=-�;�A�+dQ�a�����V�x��^I���ȫnNp��:�í��X��I^����
��g܈2�Y�]�$u�̲������Ş�n��B)
��w:\W�E���&X㻢$N�p�9�ۿPm[p�=q����	��/��m������ӈ� �z[�`D�6tm쩒�)�ee��é��c�y{��l
/�����U�o�|��u�"S�8�M��xEf���	�1z��M�N�xdbm�~<��$�:�L�I��sbs��^0�UU�B�������p��`
�ki\��}��M���,,Jb��PK3  c L��J    �,  �   d10 - Copy (21).zip�  AE	 �$��ҽ�N�h15���ȩ��h(&��|-�����;��,R\ �g������'�V}j�I�ւAF��$���\�YF��6��\N��9�/h��q�zW���V�L��7���鴟bu�QTK �-d���tE�,=F^��R��lUnQ�/Zh����k6�/Z[C
6�V�7��ͩ*�s~�LY$�^	�)�w��n��4��,����=�+Krx��[I�OQ
V����|6;^ 6�8W
��z��X�Y��(��!zH�f4%�n��v|k�Ѐ���2�u ��f�O�A�~���B��:��� 
��D__r?���B<3��r�/e�(ON�������Ԧ��@	%�嫐��(s�ݾ�Xvt�j!��OG
M��� {�,�;�E�z*Vh���^Lt�n���&.���r�&,���'['���@~��*UPD�y��!�+ͣF_�&��6�4gI�W�<����� ��_
�
<�9s�ߧ�� �
��bҡ���,Us�R�
 �����L}���={x[�,�#�N9���S��g{��9���焏�huM�*%��D=�	�a�Yk�;����;��%HrX<�eO�]&�KV�`M���S�\ �C�"�iv�8��xm�\�)I��+^�m�v��V�k�6�8p1��=�"�� 8j�l�DFm�:� �@$-
�#�/*�J�� �n�;�� `��&�788BU/[1�*Wl��$�X<�!�1P�%n�ܑQ�-�
�������A�����J��R�C�I�����L�����3��6�\�U�~��꜓����d�E�:��y�q(A9�G=K���k��K�����痵�V:�릆��
����$A�`�C(�@U���u�c�c_�I�$����1���㖕Co�	P�g�7�uŰ���sA�Ň�3Pӝ y4|������M�ba���P���^���
&��RFL��:��(f�O��gQ�U��������ה,S+��2L�NE�9@O�p<d�7���r��G_�Q�c:Cj�Z�y~��a_+���I�߳s��T*
��WB�G�1�q���X�O��*<�7ui��(I��E���J��4�*����-[
�h&\J�5���xo/���1���Z�+�-��2�s�ɛ�^�E���]�Y���
��"�#�V��Aw-rR=�ժq� wjh����ݠ�a��̕���K9w���	�c'}�W$1~,�m��!;��a_
���3@�sk���(��s�n����A�=q�Q{�/;Y[S�`&�J}	�Ŝw&��H�bkORo1���#���z��F$�:�E�}w�O��
Q�����tT�z7Рra������r�9Yz�`3W٣~�̄]��͏h�П�I!�eZ ��n�w�9�%�{D��LJEL��r�ZL�1�q8�Z��+v�Ϛ��� �c���Y��g �r��]�3w��wjV!'�u�:���m+K�Z��~GX�27;5�ڿ0���)�&�%���i�S�����c3rd���⽟X��:��4l�5��� ��Z�0��%O6�9��#�k��ITU'n��j��v��}���,tuSq�Y��C�;��Η�KV�V��
�L�nK>M�#퓞q�
�U*�:��Nt�w5
Y5˱8Q�CcvAoŵn���R��'��!ah���Lx�:��B��`�z7��+.���	
��M"<��
��G0s�/��Ўe��{3���!��js�c����y/�fĽ���
6`A)Dc��K乺���lՐ<�U�qjS� KjYg����0���qi���%�$�)馂4$��f�m
��Ә'˥�ū��e��'4ǶV������p=�,sY4iU����#�4 $���dn�4��l�UL�m"������SŪ)���"x�A<3��-�Q�
��QE�r �
�=P�����O@��:i;H���������%E�5&�������qM�c΁[St�7��3��y:�%"�:��4��W�=��9ߎ�� �C0kQ*����0qU�r40Z��|�5 ���j�G���Z��v*d�4���u<^v{��qd޹ъ]�Z�Ġ2��f�0��=Ђ{�X��MDQm�����.��P����lS?���s�%�#��m�Y�=�h��L���ﱌ�Fk:����炫Ԙb+��
���y�CL����/�d���H���[f���3�k&���$_��Hq��+��е��c2R��i���J/�x�~g�4'Ty4�H��N�8R͝�{atQ�Gvh�'ܗ�z��I;��x���HɎ-#
y���)KKJQ��6j�r~#T1���
P�x3Đ��Q����J�0�����~I�Ēy�(����փ��\�d�xxy�Ox�h,� h	���!�D1��qa�S���Β=i'PQG�!D�_����Ⱥ��&�����o�\������rS�<��'��3� l����0h/㩶\�Y��}�M�"��bo�YT�"r�r!!���#T��gb�Bt�:mܑÑ��WHB`>6�ٮ.�ʷ.m�x^��M�?�k��?>;���]hV)��Z�T�̊m��[y^�':���x�6�҈��\��a�9Րּ�����N۪���� ��H��K�	�c�~��X8��7d_��)�����@�^�&���~�l�eط!��K���ou�(���
ZTh�;|�����_}b,'4�cD'+�(�恣o�NR���l�*�,��"JCs���2��ٯξ��&��yN�̌<�U��a�;"		/!Ud4��qK $���:!�S7�E؊]D~Й�i�B�.)
=�
��{�R�o�(-��n�9k��Q@����`���<��.z��b�be#�	���$�%�oMX����;��9,̥F�xX`��An_��FH�����٤�_P��$�W�u��OY�h�C�������qi��
�J�%O����_����GZ.�D}
����-�#���ׄ�]
oD�4����Z�^zExZfg����11��۶HRi(=�465̹%�-]YW���H�!,�F� ��x�KrSCy� ?2�k؛[���oތ������I䞦R�N�YU��V��#��a���0:)�@uuB�ʋ1Y��wg�/D	�
�s���r-)tr�m���	��I�ڻԾv4K$2���>]M�#~���RFnƄ�}���-ǲ�uTs{'s��t�[ƃa�=�?w���gh9��|��?�g�?�Q�VPw4��@n� Yv�A�B}�_��V�ɝ��g�]$I��r�FW�⦡�A�[�9�$>G�S�K�'����m
����=���i�����q;cd6(��D������t
}i�F��z}}U�ֵ ���bf>*
�Q�B��qm�~h>��ss��=g��i�v�^��8�+Bg��Ƀ��9mF�V�@���tZ���XLn	q3Z���En��t0��)��C���5/Gz�G�T�W�Yɜ}0|��Ca���qi��BA�;��q�;[n>/�e��t�tSO�iT�����]8�Z�R��s�EPe5ђ�]�i皐��fp�($r��5��c��sӺ*�g{�PX$��@-� ��1�B��R�L9�ekT�y��L!Bh�m�h����>\��v����]��vӭjk��� �W>��o��͊�bW�>Y��PCC1
:ڜI,��$����?ۀy��)A�Q���D�-��2�L!��T��/���l�L�4���a[��0U�P 7�
�k�m��K5����э�9�5�R˸%����c!�םi�����'ӀsoC
:Y��-Q�Џ~�4��D�������Ogj�l��m�n4[m����=�r�����/�N�>&��Z��k�!�bTI�\>��mWl�;(F�����u渮��QN<:U��/���S���Jt��N�k������K��?[*�R�
�zvw��+�_8��<G��I�y��B��� ��Ok{I�'�R��n��&�'Np��Ƭ��whԤC��yR�
�kD&���y��<NO��>��^6�娖Fyl"!��}��?�m?��-l���[�\ R�$[\X�@O�9����Iͮ@�턕d�;ZP��-/�����PK3  c L��J    �,  �   d10 - Copy (22).zip�  AE	 ��.ya����Ś?u�uI�G�%�t
a�������茀;��ۧ�j���v������|�;6�
�x?�c��;ʚJ�l�.�l�%����+l��5im���nY�].�@��Ҹ�~%Y	�:��=K��+,�<�|C�>�O7�����)ʳ�`�
�m��PN鋟Y��h���#E���"��}
mgh8H5�F��g�0�|�w�-�8��C1UM�qҩ�2�������׏F^V-�J��^����B�1��`�9[0�魌ܤǕeB�X������l �
m����O�_Q����CX"%��y��({M�S#ȑȔ�ʰ������(�s�1Y���Zʛ&-��?�Kbkp�s��Q8�dm���9>p½wX����O���Ҟ�4{�U^U�[�f�9��B���G�WI��*��['�Ҵ������H���g���mC�|h�%aa��{���M|�s��Y��E�[�%m��#��h�iQ�ZF��z�� �_�z�L%b�A*�j�^�d��ʵ
�j7������HXގ��ևgA@3m�1!�c�����A��|L�n:�HŎY�n�HE��Η
@��דqlg�W<DxFd�7$Cx����v��E��:�_�nįzֽۜ}2>Ӕ��1Kid�2y��۽^�^�0�6H�I!� `I������|�hO-s���:�&�D���hV�it4ډ���_.֭��{�N*3������i����}��c��1��Ĝ���Lj6��Kf]�J�X7z�w��p�ƸZ$9ǯr5�b�
��^R ][��B�u��ӆ�W�@3�O�$���Vh��$2Y���7�:�r��
p����y%@�W	�~��B��C��䞂3�ұC>�ﴪ�]�2���@���܎J��-�:�$�Ԅ�J��A�tAA��G�P���L���D�CሻGnbF�:�S%� F����5�3YԻ��z�ږ�����#����_D��;�˽���iV��|d�s
�E��K��4#\���*t�;��#����.�|���-y��6#	�:��<���C����v0��Jk�q�ʎk���ZNB*��4�k{������8)���n���w�'�/�J{��L���Sв;�}��ty|�uF�[b���� vb�����.�:�>P�n�JV���a宴[�ڇʸhY6�m���
c1��CqC��Qg�d< ��_������p~��o�\��D�DDk��+)���b�)mS48��4�Ƀ`� Ӳ-��g"����^�&�D'�i�[j�i�D��f�<*',5Ӣ��G�-�k���@*Ȯ�J�`$&�Zt
�>>��u4����)�	�QC�uP�
�c��7y���=Xfɷ�h��D�ȕ#��t�7��,ܭ�N���`;��U�~��Tu�Ӑ��Fб���8p��]"�U��ip��&ܲo>iK�"��&x��"�eP��L�>%[�u4��mЃ�-�k�d�tg�γ��B�o���5�e"��3L��x���
�Jf��Xn��T��wވwEXhGo��D�t>?}H7�"�ޑ�A>6�Jf�k�[���9�y��떈"&o]�\���g�;��/��I�W8��F���~c��*�"nO�$m/(����ɥ�ͼ���
(���<�2%PE޾���H$5��:#|{C{�GR���P!��I��z�?�*쌼�G��;"�z!���������Nc-��d��q����|8�Ii��!@n}2پ��ZK�yUFRi�fL���k����q�f x���a3ؽWo��{��'=��dڣ���Ue�|I��ʽ(�jف��>P��"��6~�g�~
R�������=���p�E���� �g�qJ��*�=�x���؅�D��|�u��n�܈�Q�(��S*������)(��A�i� �c��|�7�ڑL���:���1>��!���i�}y��b�/�`������@��>޿H
��D2	M���Τ;P�a�x�����/:�W
@���/���յ�wfv��.^X�B~|��
���a�X!���]o8y�I���r%i �L���xqѽ����5�-
��}ãJ�?D�EV�5�1� a�T����NK�qp�}4��������L�;��C���9����2�*oF|�����4����+���P�=��\kT'��]����D�A#��*z�4X��EL�Au��:��rb���y�mL�o|�J@�	�0Ɨ$\??�O�C�W�%Ɯ�l����+����r��u V?1�	��ԀL��.|Nl	??�N����s3k4�ʗ����$m��	�ij�&�*<� ���1������p�E�q�#K�'��XY	�o�&��
W�]�J�u�p�$�n�/ �.��&������cL���X�\����.�Z��%����M�x��� �J+e��1�G�b��HA��>��֧EPO
����>�W�K먽R���c�$��9��aJsma樯�C#-�
9r��@P0�$�6]��h�*t.�R�����ԅb�� �U��\�p��7�X���dLnOx{�x��fZ�>;
5I	����j.��Ҷ�_���#�eH3[B��
���A�7���	�
c�7�������2�	ٸ�.o�4�R-�yFЋt؀��	t��Yzĸ �'zLC���:�ǫ�D7ϧf��&�&��~	!�-���Y�z�P�^	��V�N z�R�Q�!���)�_%(�K �鎠T��9'CK�/�j��10B�L�|�"�'�FAh������ڐW��Q{���΁�v�~����e磂��wy��X�hI�,-�FU~�fxOct'��=i�,��v�Ȃ��ƍp�W�M�NSp+VĢӆ���u>,#?a"�)�6�6�٪�zP�U3��B��sV!��2�N����(%��+���>2-Nt<4�xR�I��{�T
�_L���ܳ�yY�TL@�
"�6�.YU��J���$��v��ţH����4����H`�àI�%"�\K�+O�,Z��v\�O�g�xJM� 86��0��X1����J��^"j�P�q>=@n�\qO�~�|8�<�\��h�M�
��P���1LS}�u�DOK����>
~��ƃ��ޗ��-|���ң��5W����B]�󤡑+A��Q��Q�Z�,u���hd�
������M��\��5W�fձ���P��Z��F�o��:ݩ�P��@�2�\/v��!CWi؄%^�ڝ�>3E1MW��
��f���?� jZ�b�ӫ��������������^�WQ/ӓ�޳/}/g�%�:?V��ku�3'-B�f�)���}��sY����
q]\�c(���Z���{xW-r��^���ӏ��?\��
ռH���
�� �����}�C�#�����;�o�C�݅q���h����j@�w���Gl:�B���'V/"��먷*Q,��T�����0Gɇ<r\�|HUI�) �0����u�q%G�o����f��e�����F���ꂽ�!W�s�沖�>`w̵�c䊖Ah��s��>jH
j�W���ݑ��m�I ��TK�wG$�7�����#QP��������m ��GM�2��S�X���-.ö�����R�x@/��So�T��7\A����8�#���|u�u���̻ ~���G���.��Yܺʘ]�_{P���V���^d�o�J��tA�q��y���R\2��A��8Z�㺰��'Pg�����ҴP������,(��>FZS�D�Y�f�ho�0���BL�B)o0�ѱ��?��֯:x��3T�6B���R��x3�Lb�I�`R$k�4:�cvr�F����=�o:�a_ѵ_���0�p�7@�Qp����
�`�&�N�!3��g��X�/{[���u�Г�i�Y��a�������"m�קSe,�%��f��\����_*��j����}�k�,��Ek�����N�q�a0y�c�ߠ�,����t&�O��3�bz�3N����g�a��I�jNj�����p"�$i-��C9֝zF�2Ζ����A��%� <L�^ȅ�zG�s��rٟɭ2�F{>���7;�ӀC���i�w��y��yi(�j��W��`��i@��Ɠ�t��'17���������9�O*��B	{&i/�I|zK��.�F�-�(;�t�����-&'+����?5�g�ݝw��EaC�.��|h�P�mL�����Ƙ����h��h��yO3S�#2�.x��RH��N)�ͤE�C��^��`S-OY��ɥv���S���~ �R?x�a����P�腊:�Þ~�]�:�0-����]ÛU�ToT߫٧	wH	(��^�� d��\ቪG�آ���ş 	ɓ���3j.�)�d�V~*�*>�X�O��87*�u�U�n�+�=���Z@�2#�Ц�f$sH��O(��_SU�y66�?��fR�3ꯇF���m�ޫ����]���)")��[�Ԩ�dc��)���P��0'���'b���fG
��I?'QĖ���S�
�{�R�F�zy�����P,D�%kc��5���@_��0Yǅy@m*B�x��;�\����0"v�._��x���3����
�J"lĦ�T��wdB�Xx���@RZ]�v��5C����р�\�`5E�k�X1u��(y$I�AM���*6r�?� �˜�X�#P�����%Ȝ��z�hT	]�86��h�H�����Op�"���7PMh��1����V9���i)q��Gf�ʓYXL���d���l��6�'����Gd��
TB
~���p�Cw�jYA�<��.����~,���v]�3vP��J��d���mӒg*G܋^tJ��?]5���&���7-�E )'v�
J?R�Ą~	��l}+G�D.}_�n��dZ��u������I,(_��B�������/�eJD
���8zG�O/�������z9_��(���$��Oq��O�if5����I�ڄ�3FzQ`u�(�+����c��$ެJ:%�%�J`~��X�ާ�7�Vd�+bPK3  c L��J    �,  �   d10 - Copy (23).zip�  AE	 �����s��9[�%%�ˏrg��v�u����f����$�лXٓa/����{���9�c��7���b(T5=z��~���Dӳ{`q�;�.v��m&�qC{'N�� �MFэ�͂�`�8����˭�z�Ɉ�b#�G�1D�w��'�IX�k�)d^�}T�^d�����#ѦņB�/q�5�͂�E{��Ģ�X|Ľa��V���G�?2���kd��]�Y��(�H\�
���
> czމ]�+�]v������^�Ƹ������E|�N ���W�*��?Zmd���/]�>"��Z-i}	ȷ��m���l��[ݍ��V@�d""ٞC�t�'a?��������b��M8��/��;ˠ4�Ȯz����B�})��!�5(���c�O(aD�u��３���;E����?ߪf��+�`G�U^��+7���Y?x$~0p�Nq��HM��.G��M_Ǫ����9���4T�,���I�Ȳ-�M_-P���$`���qol�n�њ���E!�����fUF�WcޗAy>�zl����۫�j�o���BV��#oq?���!�G g�ؚ�c��D�S�ծOzH(55���=B	5��W���H�%��}�Su,j-5I�m'l<��x=�=R@������\��e-(�*����u�n�6j1�X�"�ݷ�:�xPN�(ٕ��;ʷP�X*�S��QU���@O�����<���<��F����/ޣ�1�;-^F�d��*�
Y��v:Z�k������D��d=��I�m���"M�+���qW�dI�c��L"
]��Q��O����S���#�u깕�i+�GՃ��=�%qxt�M�/�k�H�,g�����%����@g�5���8�6�Q�����]{sQ>\rMv��S��q�����3Rl
�?"��u�s	�
'yMKk5~|�>�3O��	C�$�S������TR'I��vK�u�HA�N���mu�Y<��1��cՁO��c�,:4̌��H`�գlΜ\�n�J�Q�����w�U �^z-�=��
x��/�a�$��@�m��
���6�F�8���t�ٕu�V	�؝G�A��t�{�p	5�p�牨�BnNY��{-��vw��e ��,�ƪ%�f���Y�,61B �7�s� 0
�fx��V-Z|A~x���Ӑ(�0�� g���c�qÀ`J��k��f!j����-_�E0��
�4(H�SqҮ��g�"�(¬'iq6X{LOp��0�!���!t��v�f��J2�⦃�'&yI��uS����o�|8���鎽���l܄�T^knZ�Q���E�Պ�No���o���6�lp��>(��
���$V�	n�G�t�&��_�i�������U���E���b���CJ��QxԬ�1?�[k��sFvPDXf�&���`SjG�&`36i�ϓ����ʅ��倌\�'?[���D���l��Z��l���b"GP�al}7�-W���!��nγr�~��o��4��a?#��Z�ڶ�{���N�s>�9�'��	�@�֘�0H��i���N|�����}���Z�?W-F�C�W����$��Z���5O�u�Z���q`.���^��j9��o"���tV���c΢D���*�LD�R�N��`���t7�]���=%G]n�E��sb����R�Ȉ,��I��m�D�c;����
D=��{R��T�Sm.9�r�A��+����EN:SB��Zl;۬��~���c#�y�G�%�K��xช�	H���7�
�\�t꿚0��!��Nc�UY��v�������q��E<1D��#�r��C�p�
�K��]JՓ�"��W��늚~2Bl�ad􊞐���CpE$8s�$k���ŉ^'��
#�O�}��z��H��)���Y�?�!ƲF�j��wCp�sJ��<��o"�H=@OUK\p��L�v�=�;+."-�O���QDA
!��*m�efo�d�ҍ?0'��d�v6ކl	�G���6��S�U�]��8:���~ZP��B%��z?P��1��~��-��>[���{G�n��GL2����]�^Vu�c���x0ߧ�d�@�G�*�$T
��T_����i:<�8��H��1?��5�(��(���k1�4yA7!T�k^��'��i W@���O%��D���(d�K@pS��/����N�P��:ZE��P+ON��X�]x�
���?|JW���i|���q������ =I���>"E��eƇD%c�O�96���( [�ǋԒ��$L�QX{%��A��E�h��"��>mJ����[�?Z��ct�[�����R��Y�3z�5QB�����IPw��{��C2G�٥������G�ˇu�R��D{����X.H�� 2���a%��D�� >u�����en1�7�iM�^���9/��t��~i5�]�y��)���R��#Se�V����9�A���l��Ӫd�p�R9�e��5�0G���y˲�ܕ�kk⣠��s͏>�T�t��*]ɾV�4x9�ﯛ�迌^�bɒ�)�"�*b�t�ϼK#_u#ǖ�H��ڽ%"`hU��hP��$���/���������m��@IY$���:3��k���]�A�7�s������XM�N@�~;��UQ�e=��Ի���T�j2T皚s�]cm��K��1GB����q��*0ʆ��O��2HـϦI��Kn��Kf�Td^�N��UY�˕��-�+����6n�f"o���
 R����k��B����ޖ�1@)�gMeU�{�	\sF�jq�Y�r��+��w�w����s0�����J]V���Y"����T=���S=��,H��ܑHc��s�eJ �z��[����f\^��t֓j���Z��Ɏ��?�d ��nRQ=��#��
��:E��8��6
�kLu�܊-��8�J�����W%�y��q���)��yl���=³[�	�zhD��F´�[���&��M�+q�e�#vŝ�i|��]7�1�^D
�k�{�X��HM��~?��5�	l9��nu�����"B����7����@�#��i��+R��c�Z�_:���9�S�D�MX�Y���-E(,[0 ȕ�W;�b�C�e����Q��q�T�K(�"x�ና�&r��,���v�E�]��B09Q������QW1�7����O�	Kf� )�=����<���G�T�a��NK��z���Jae�]�p0�s��,ى(ߧ,��ٜ�L��ŹZ[��ӛ~]��?���k�Q� �p����s{��V�k��ۋ3����c}/Ġۼf�1!�`*u�!y�E�����;M��C���FX3�+vP����NC3��3�X8���;nho6^��i1g
�n���n^HD�V�$ر��������B�7�S�����L\���U^[��������J�!�
�^�'��Ez��^��8GN��i9��3�6��r��T2��,�7���8��Z�wΊS�O�F��.���9��>hWqI��F�'��$��+I�
l��!1���E��	���r5�.�%Q��񉧰1�=�������D �%�i��L��3��c����b��]���ko(�����Z_���p�RF�J�"`	����,�n@u�Qi3�$�EE�N
�����̐��� T��5����rc���;Vz��æ�����]A P	
9�P<�8�܆��L�
ѡ_
�#p�0p2��
r����Ó�~�t��m�m�sf�_Ż
���f��%f��HE���\�*�BQҐ7"��
�ϵ;��M���L�<5�S���o^���=g
C8�����4g]���}��f��%�ߠc���P�37.�)'�ޭ�ʀ����sq���+���B%*��]c�(7*�#�*�Bb�h+Z�Roc���JO�&/��z�K���}m!"L��$[mz]i���e��)�ZhsR�!i�U�.�T����[���%f�[J��/#��(�'[y�9L�7v1�������c��󮑆>�>��"��=�"h������u�5�
�,�D�uq�l�Y��� �N�:��vr����q�^���ň�.��g5-� IKDA�E����s����)�hOE|M��&�BA��y���]h���<�IKa����'L�ţ៹������������u ��# ��b/����!��c����k�-��|\��0R�@��+W:ӳFU4�^ߕ���l��6F�dꂻ�@�^������2:8��(�����M�ꑐ{�.���C��� r���z8)��*œ$6E��%!��PVr��ha�8r�Eǅ:�3�ꛃ&bI{:�߁_�S�r�n�n9i`re��O��=���?�ͮ�$η�s�x-��<�-,V�^b6;'�Ky�	u�D 5s?�x�"�q��n��3���U��A�Ҿ�P>���g7!P䣵kʓ�t��2�W&���׊�<䱐�w�P.b0�cK5�T�����S^-�[9>�����*#�!�y����uƬ�YXU���mA_�+����
�x�ٜ����_��#�<hϊ�VC_����<��:� �e�y���kv��s7�B���þ	F�p�m�"���2����32��#�"k�1�e�k���R�#r��.C�jm�#X:�]Yo����}h�^��<u�I��X)9�,��Ibb�w��?�:m� ��!�t&+T�!���N3�w)�L\�C��$��~"��o�-�.
̱�ܭ�ҕ��geI	u��f��jՖvi�ju����P���W�g���Va�3=�r���]�"���d`s��1�PY8����� �"�?�3���_5�Cg �:27���ι�6zL{}��I��b����1������n���e���5�R&l
��-�:g���.��t>�=�"@*=}�>Y�G핏�'�q��9LDcY=~C���U��N���P�"�N�V�a�*+:���eI�y'y:��F6&�'���ҡUN�QA��Z����1��W�2:{ ɗ�D����=jG�#�R��v+��	ә�`5O���úpL7�c��0�&ƀ�;�����mimUn��K�}[�˙B���>8Rx�f?=����^��'·$+n!�锼3��Z�d�i4�dj�����
���#)��u�O����^e��g
�
�Zzi/$�ZJ{,�����^Q�k�xV���|S{�oz�<$Lol �1{|#l�!�����ˤ��	�j����a�W~%��������%,
�'���T���tq�e����,��n��pNJ~���4M��W��=}W�A>��@�
Wvq����A�H�h��� �f�0c���GR�+��;*�p��� ��U%�ħ.���ݫ۶v��g?ݰ��f���7)��n6!���o�����B�ɞ̔��D�忁�3i�񽜗!�t�k@P�>����n �<Lm�X�7�ך� ���c95���� �V��+�[8���@�eE������i^�D�9S4�����dN.p��pqȓe�[�c�uyG�7s�,h VEA�f�1�K�4x��*�.6�8��(:��n��D�8�z`X�41W�T5y�b�%X�7��u���ޝ"V��KG����S�8�ˡ�8i�������t���V��i�D����N-1کZ���/�[�xe�O��7AJ֫�Ji�,���FN ��{Ό�{`��הT%1b��%��I�*�{nc0T�9�cD�X��T��5���4TlL�ܑ���v�O"gУ�������:��,�
q����B�
��/$��C57��0ɹ�gE�ٍ�U|2r���ӗ����
�[���S��L[��7��H���5�V���y�a�ʠ��R���\�;
/rV�Q�KK"V*��m����]�c�4&L�ŋ��}0��[
�~㶬%���h]*��\��MC�o�9B����kq��gJ��P�Dqୈ��1:Mhl��d�qd��e9��@��Z�!8��h2��4@�]%?J��q�׵��6f�lS-d}���B�*fM�f�;i]K��!��h�0訃�4����C^#O(�%#&�wZ�h�2��HQ��ey�"�~�Bը�~��<4��xӾ���>�ǝ+6�m�i��΋l!)K���",�0���A��)c��-�P����]I�$}���ƶ�]�y/���n�D��.���S�w����IU
��Y�2ڃ4����9����]���R���z�P��{4�dT>�ũ�xU����)Ή����"���W�y����F|2L�]t
��",�X�
��w[x��}=wH���8����^��Ŭ\�{��\�7�R愁���>�37A^��	$�{�b�$#g3F;K�!��0�c��ė,E>���+aT�C���:�:��FS�#�5�����;��l��`����T������$��+��TV�L�������yҿ�%��7�r$	����Y&e�1�r_�쪵-��������	guh����M
�b�"����ƽJ��
uю!���
��!H���x`J� (gh@��� �vW��ʯ���?�o�G�X�W-��}��(���뎮�|�0�^���`9��mU=T͊�N���ݑ7�2o�j����7�i���Ɂ�K�EXgD)G/�? m�>�Ը�$Jf(������B|mT�����y�
@��od�QJ�"C�uX����ø�y��BwY�)|[��4�m�>
�_�>���<N<F��FX�TC-��W}_�g)�At�i�P�q���dN�=?�7���G�V���t�;��v�;Ǘ��K�N?"aa���țܥeӋ� ��m�ʛ_*q.��+���Dd��֏�lfj6O��}�b�˿yLS�zGXW���O�_P0=�+b���U+�C���������-\�N� :��S�!��>��	�x�Aע�& .9>�JLB�
�	q,ҟK�sk_0�V�9�.�W����J����@���}���R�j�F�EBrn$���m��9"���
.=Zj��UG��L�泹 nI:C�u�~Q,w�(��]�L��-H�߃�����^�>m��bc��Rn/8+n�ŚuX(Z�3X���?c:t�s�QW/Y�.�i�Wo�f
J]yD�@%8�}�?v���$}
F;�
���	a�k"�b�3�
t��|�f��:��ف�d�$&����G��/��x0��_71%tE��8�2��d&[�����a����)R�&81I�c��G�'Ż�����	��?�
w��h���;(�g.%��q���iE��ݜ�����a쒉�Ws���+ȓoO���}A
��dC@i���s�IJ��y��*���i�;ԩ� ��m�TA+�dз��lE@k>�D"\S�k�6@�,Q{4�r�Y����a=�G�x	���:s��F�q�8�}�	�m���)�m�2Y�`��� .?�,2J�^�C,�������}��8�>Vx�;<-�qzwK~�ғ�B|��]�C�/Ҩ��W�/����u���݉���i-)���>�<=_>ROħM	 �I���u��lޯ9>�u��������!��� �&$�%&
�l�C�Є���*�x����r�� I?n���;�+�Ԭ�����A͍�gBi�i����I ��nۂ�����o�ɝ�)�ơ�y�b��.��g�M)v����#כ۞)�Ś�`R���O�$��tN�E�K�U9��^�+�u�I5��P�W�w����ht�"!^�G�~����ѭ5
��L����
C�6vl����"���R4[��<�5�H������z��`���_0žњ��<~
�w�ơ<@�?�^��
�·G7 t2,0v:�{{��c��R�g�U)4���h��HܾI*7���9C�A�d�>���h��X�$�{}��'N�u]�֣#�$�Y��3��q��Q;��d"�i��6�#@�?8�B�_@ڥ���Tuhf��)t:�𪼶D,R�
HރD�N��X"B��8�@��eW�0l��c�=�Cd��W[�R}�����[~����DC��lD�kx�O_{a+MP����?�7 ��r�?<Q<�a 0Uċ�b�&|} 8
��2_��.9��NْQs/��FXc� �7_�y[%����m��sԓ����'*v�� *�Kj��4K��6���6p���E��DϜ3�T'��U����ǡU,�*��z��A�K[iVD��Z�z�}+�-3�GŐ��	��e28F/:~Irl����m�����d�އK���z��9�6ԉw�t �uo�O�C٥>�'
�wShV'�uee���cɥ?��& Ǟ����j�G�S��VK�
�x!ƀ��'0W������
�C�Ab��N��zU_'1-�isӶ� �1�����K�B<��k@A؜G�}vuƄ�+�푧1ӡM�'��=��˘�d��0��S���F("��ؤ�ԛ�xj�Ɉ(z���cl�Vq������)C��"�245q��ӺiÍ
d�t_��,�'�f��J��i�
r*L��n/׃TU����E�,Іj>S�a0�ߑ�]XJ��~rN�.���ytj
'k"����@�5��
�W�7�jфf��u�3(Qe�቎��V�+���rʿ��pd��S@�8�r��J�#�����o`�vz0���Q���pD�d�'������.���+2Ϛ/��o��	Dj�W1�L�\�;��-x�X]�d��r����[/iN��m�<��8�)*d����i�D�o�8����Z�on�".@�ܽup��Z1�,ǲݵ�!�a��Q�Y�����8V%'�/��X|^S��	�H��y֌|�7j��+ŧ�PibK	5��f26�����X�k �ɸ���ZȠJ$3Qj�b��T������{|�O�J���ΐ��X����񠷩�b��<Ne���Җ0�M��,B@B�״9�֖�� �NpXù|��U�=߼����Dz�I\��70��{���F�>��&���	��ҥ!c�I���/�0ŕ���K�P�"��Ud_Sb$�2F�T� { ��AW^0V*�K�1���P�E�<�m]��jg3�x���B�d�-��+^|&Hn�V���\m��AC��Чl��W����-��j�r�S�\9�\�Bò�W6�Jg�Q�|:�d����Λ?��F8݄.. ���H'DĳB8�=�p���"*K�fDwAd��4#%�9�2wY����{o���5���bn�O�;�ϰP#�ꦄȆhMwz��(�����j�-5T�W�YĢ-ʶox*�-�b9�1Ap�I����T�
��A�.����8
���btvc����z�n�ӿ��ǦR���K�ѓ�!���B�����x=���{��~6�^Uƒ8z�c�[��؏[u��"��Bu��� ���s<���
N�Ԝ�E#�	�4�(� ��z)���Y�>� CR��;V��B��k\(�5k��v��$��C7yR����i����GLA��=<S�V��oΘ蒑������!���o[��t�44ދ�����4����PK3  c L��J    �,  �   d10 - Copy (25).zip�  AE	 �BFM��e*<3�@�3���x�k&��æ�P�ȜQ88���
<�
��r)��̎��$ߨO�(n�0�&�Ͽ*(��eڻ`�
,>̻I��S�D��N���_��S�rBre��/a�t�ã�
9���5��IrdA�*�o[��ZL
��xG�V�	}\�e���.��z}c�}p	��L5��j^���r퀺EH��-75���S���5�dt�o,�ݸ���.�L7���"F˨9�s,��Q��}a��|�y�mZ�� q ��I5���Kј�pѝ��ws����'ס	���;+��G"�eK2�`n#a3#Z<%E�!,�&��k�Q�|����Pa��6{������.EU�-�y����V[��D
f^����F�(���ZA>�|��+ƥC���kŜ
V��غЛ�'�w>M3�V�rc��U4�!�?;V��g��[�������\�6�W��@����w�������gd�ȧ!�����[m�/�$���՝fu�E��̀8].z���ܲz)[���>-�Jȃ�N�����%V?�j���;�3M��6��l=3qd�$P����lA�<�cr�]5��K�2�Z�â�7��ȹ+zg%�QX�g7��[cv66ڕ�&[R#Y�� �����A�^�Ъ�0d��J2N~?����t��p��b��xXȟ�"DD��U���W�7�٠Ð5��`��L����q�[�)�e���-�ha8)�i��v�ܽ4P�g�z���b&u~�u�" �J�٩�-UZʷ5�)�N�@���VUeH#!nh�֠6$��$1��ޯq,t�?���E	o,���Z?9�\���I!+;�M�y0��~K�'l	��3ܔ���_���q�(&.�
���o�a��Mqi^XioJ��CB�1��D���Ǜh.m�<i3s����3r�}&�p���\j�-&�a[#)iH�ҹ�-)7����qis���o6!)��`�=Yf��.�dx��2��i��@�ٞ"���ƙ��Բ�6}�}����Ǎ�ޖ��N3Bg���X�K�e�N���'�߭�sn�<�S����
3wԋ��>����E۝��$�D��d�GqM0H�in�C��3sPJ�m���wJ��	zyC�v慴dŔ��b���7�0���KA*
��`+�����T���Yr*����	!����7��6��a+�;u_m���	��!�ث��rWE���n��*B�I��R���U�(�s(\5�G{!��ҙz������`_��-.�*7rߌVy;�����~@�n�?��*|d��]	Z��4Yzd<Z���̥��(|�����´�/��_2��3�%���gҖD��5B�p ]�Sʹ�lufy�TtZt��H����x0���K͛�e��ˊ��X�٠��<dW~O�@���0Z��N���:J]�?���V ��|gNa�`�@O�!��G�C�(YlZ�C>Sfb*Jn��
�8;�J?������`)-*9!�!Ƀ��L�����ul��o�gO?(Y�����E��|";S*YP���I4�E(tT3�l��;b�ď�oS)�Ƣ��q~�rY�pJfV�}�����o	L��K�!�
6��
�YKb����"J��(�;G�-F�qF�����	�����h{��rK���H�����L��IK�.X[b�f�@~J^�8a�bC��f�)H�f]0S9�ʹ���.8I)��l����S��
y�{S��r�^Ҽ$IM&�B&I�����\���[s�gO�hRx�~���31`�3n�S��A� O�n��Pe:���Z�rg7l���^���rc��8t%���k}��HK����.�;�x>�e���"�|�1�,b�
6ϑ��:7���E�'��Z�x�i}����~F�
�0h�j""����m���*�������n�D���ˎѽ�2�2z�����ꜞ0�'�tT��O����r�m�g_�X(]S�O�0�w��fQxn�S�R�&2U�Ș(�a:�$�j��t�U��٧�|8(�jT��[�c���m�Eq��nm
jx� ��r�"��	{���m�mn�g�����>aT�B�ǦhlѨ|p�?���7��|H�F`�ݎ+?�{�o�#5�7X��Q1���ֹ; ��	��0�sϴP|n��: �>4�{U#���ȣI2��*��j?i�����~艏�G8�C�{	��G]9{WY�= ��p�o��b����6��X/+�v�A/�|���=��h�d�Wl�,'U�-��Yy����`�4�G!H� ��+u�W��?C\���M��VN5T&WL|�2�9p*��x}�%.ݖ�޵��ּ�˞^ת+8�q�o:�2㑵À���*��4�/N�3+	�c��{�J�H���?����S �ld���V���S_tO��M��^���>k�:� "]�v�%���¬%�� {'�.�9Rpf�ͽ��Ʌ����ά�FKXI;������b� \5�e��6�E$v*3>^\ �e�lJ��u�
Tv��w���n�7��F��k�D6pne
Io5���6�YJ>�7�=��ǩ'f�3�$D��\0o"����T����� �~.�$ߍȃRi��٣m
6Z�G�maoB�~00���$��r�K(��S�ꚓU0bp���3;[��a �W_V�
Kk�M��BQ����iX�h5��[T{ Q�l�z`�#_ղ�ͰW�
8�ʜ�:HC��cS9�=er�S홫�zk��}M����ڈQԽ�(å�K�V5���K]�a�ވL�^C�&[�g��v��� �]e�q����i�B��}�9��J͊���sn�=���f�
�a�9�
ٌ��A����4*�/�]#����9풦�%;(d&�&���X�n�{����ȑA�:�/�_=��9�A1{~�Б1�wF���/[#��P�ޟ-L��}CR1�;qⅪ�k6��Kr� �~w]��Eq���4���ں��]�d�!I�.�����*�RY����K6�H
�Fl@\a7��U�������$H�L�J��[}�&KK a� ��-�}����y��M4i�����r
�ƉF��9�w����x�RW�s�_�Z��߁���(��ξ�|Z�$���JGT��'��dfI(s�phȵn�
ѵ�M"J{��]S%1rM;>!v3c|�Djr�
(2�0'Fu��)OД�s��<�{�*�i�]�%�kx5M��r���Ώ�Oj$�e��{O�A�8�\���Z��
�jH�hfn`���e�L�YWy�[�ەA*-���S�[�d �<Ueb�{��~��+OD*�͟�(���VC���h֜ ��KB����Z�t�����Q|� �{��݊����ӳ���������+�����9i��?�G%�,!:B�ՀKt�ƹ`΄6��L{����]�a�"Xd@>�($}B;������Y+�i��,��?EEB�6A�nuc>���n��gƺ��\!πe��k|=�I��R�����IV�kV,����;>=܂�;'�n�KA��wx�XA���Ɓ�pXC C.�=���3
�A��i]�[��72e�0�>!K��� 3:V�,S"���� ��LJI��D{v5��b>9}�2};��P�l��߽�i��β�ܘ�}� Iæ.ۯ�]�	D���b@/�|�W�� {�g���ȕ\��=��\�N/����i�L/);q��%�7ڑ�\g&0�w�:9�LtH{��^ �L�B1��L��0����I��ɘ��p2D���)"�5�x�����h��|�t�M
.����+�!�sMƥ�E�уS1vL�˳���?��]�w�����/�šp� 9N5+������^D���Z�G�Ҁ��		u��wO�S�iX�k��y$�ǹGZ6~��ԥ=�p_EUlL *	�^��m2��0��Ks,3)#�^t�5f�c杖� �A�yu.��c`V���a��T���7^�����4By������7��3թ[�����6�}3���%���TXZ�'w8���7c�.٢�<��dq�dހ� �����~!͘C��+�
�M?5�ĂK��E�mL����Ճ�zV�9�C����ܑ7j��Xbا�/�����L]����'ñ.���l�3Bƈ�bx�x �i�j���N�������g��\6^G��lX�G��I��U�����#��֘#��䴀�-�^�It_�S�� G�>���|�?����vk�.u�����0��ħE�a�$�1�[j�<�`����\<&�S���aS���'����Ɋ�B�
���T��+Ӓ�'�i�1�r������n�rb�9eU>#q�$ku,�@��9��С�V��������Ɗ��\���\I���'!}|k��џ���+g�:j�"!��	po;�K�P�}yX�鱵�ȷA�w� ׀��P/?��v;�V�Z=��{?x
r�����4�<D.���V��d��6���&��^#�|f:h���׳��'Z�]��Á�|�۬$ݕ�G��9-�ھ�R`r���3X���O�h��I����LDN��C�l�l7���f�M�F���`�`���n�?j�>�k.�41�+��I���0=��`o�9���	�ɂ����ID*7m5&"h�KX�cu�]")�)T��y�	og!
�����%@q���^ߟ��*�����7Mw�|(�]�����:#�O�0���,:���'M�1y8G��
Q?���K�\��h q$��@����.#�
ٓ�>&�J���]�h�3�F�T��3M}l�P "�n"�N��� cgÛ��"��%����#��	��(d.�(	�7s0
ӯ�M�1̾�.*T>�-k����iP�h���<;;�v����0=� a�H��m��#���Y�k]u���?��'f��_�D8~*<�A��W}������R�ܠ�&9q���YlA�̍K�E�O��׃E��t�UBO�-S/_�p<��yrF�}�9���##��O��>qq��ۅܞa|屓��!�c��<��J��
��2c{@+��^���UW��~��f�̣��T�f
-;Lm;f_��d�Lz�{�M�_��/�y��ԌH~�uVJ�/$D�aƹ�o����"�c�?�f�P_iA{�����,[T0�� �KF�A�h��מ�6	��sh��޹Im��L���ea�7`>NS�B唬�. ��������-�w$���5����6�l�:��1
 �1�h'�$�|
_�HVae����4 *��)�6��ǵn�QK���j� e�;����sA�É�d�R�,G�����T%É��^T��;�|��*P�#�cYz�=/��?M�9��J�����RG����͞���,��!�= �ƒ|ie������pL��𥽈e��a�('�H�S�|(�m�B"��W,��aѹ��)Te�Vɩ1=a��~nl�]O�? ~��1��z�CͲ!ӂU���x���2;���E
w��g	፨��1��s�a����|������u�������:.�X<Ŷ����1{��z�%U��T[�q �a�Ӽ^(���1�7�0.ξQԸe�!��O��?fyT2p����rUP1C�
m���!��`4�27_čA�F�{���a��Μ��p���?m��Ë�NۑL�����(�#n4:^^�f��|;~�J��.�᪪Gs��E_�ŷqI��W�H_J.Ҡm,�[�*M`�C�w6/����s�j���%ɊK���l\���t�r�	�e�A�SU���VN�6���7�L?���U�2�
�1F�7�w�M���c�4�ÍX��V�38Y1�um/���?�i̐�S�C�e�R0�]qt
��v����*~�m��g���µb ]�bP�+�hߵ�G�м���;2�n �C_�1)2qӐ�3p���a���|
n`\���'��H ؍3��"�^��
���F�=�k�l\��.Bmz���E0�a�V�Q�݈�H1�P�������݄9��'���=-�)$�O�����c�`��=8f��E�*���0<��+J�V:�iO ��
erWK$4�w?e!����l3��k�~�����X!�;A�
��}����mM�O<���+�Z%@}��_��>; j̯�EKL��MpCd��w��l6Z�l�1�~���f�.��D0vC��gs?�!�|�'H`織�#���뼼���J��֥ �VFܬzb��0�����m��@N�CF8R8 ��6��S�`H�5�����[0�C<`�U�tr+t���������c��l>���~J�>��%X�X8U5's�{�Df�3�EԤ-]�8�" o��i5�mbX=����*~`���Ι�i�h�VE�o~6����D��w loLz��^���&��-��ب;p�F���Ϧa��<�l�p���)�jYo�q=-�+����ݿ	����W�e���ϋV��0%�Sd�Ty�Rg}��w�gB�=�|�T��d���oz�S��� 8��0	K.��8fa{��������	��V�%����?�x��u��!u���5Q��CG��PK3  c L��J    �,  �   d10 - Copy (26).zip�  AE	 t�U�uњ��Ϩ?�G_x����>MNH�/b���:B������X��d���:m,�*A��[Z����2��Wl�Z��0�
�R�g�P���獄�1�
���I2��9�Z�4nev�[�h��f�����l-~�����Y�NrZ�O��IsPs�8��iџ�+1��*��B�k�Qo��`�����?ˍ=�n��8F�f��'��R�&^�h����݀���tB �t�h��T�R�|������1���k�0��v��{�E��ܴ`^��W�EY��9�N+�j��	׭�Oq��#��W��C��;��[�BW8`	-q���It�UG �/��dm�=0"�Ӈ����
�48�r�8�ASo2�0�3}ek��BS.�zK�e7	a����˙�%p�e��@�ݒ/�,�:�E�#�� ��������Y����זԎ���(v4�ǒ����Q��	js��r��SM�w���^�"E�>R�����W�B 
8V��,�o���6Rj���| ���j�T�����v�C�)�N���	D��|�;�+ċ0��{�6�I��[w�V�%�P�,�yŧi�Ŵ�gG�zb������]}:��� (��l��G�UrG��K���s�6ћ^J�1��7U�;�q�L��#��ϡ�ǵ�Vl��1�O]΄�
���k�^�(�|�_%�r��O *�y9��U�Or�@G�`E�W��"*�V����&�Q�B��O;�B���*���&�P�WO :�ܪ��E�-T�pM��c�j�K�K,�T��-Fx�	 1*�bV�^�njK '��a�󙂪�K>�cj����{�:��Џ��J�9k�N�P��"Oe����(8���E��
ҩ�[�p����R�7je5��Y�p�6�ֽ$u|�!F��)� }�a�Wbr�eq,��H�]�>���!'�YF�^�l��
"�I�s��Z�\x$�X��AyD��c�8i�NT���!9�=�,Ѽ��yq� ����8���F�Q ���x'�G��3h���5��:�	���wV��S�O�&B7@�g�~a׶<��|���E�i -ܗ�ϿtY�E��9�P$:f��z�a�.���a������ �@�'$ M�IE��qz�{�є�Z��b2o6@�
`C�� �I����TL��zy
	痍c�c�-���f��W�}��x���T3F
T�r������2M�2K��j���PI��'s�<t�9��i��s�ı��-Z�=�u��޴^d]01���X)�������I�����$��.RY4_Ǻz��3��3�OV:�{�QW�3²1���}V�0�B:��Uqqw�2{;��FW!��d$�u�#�.DL?���S��C#T���&9��IZ��/�V��4TX��Xz�oI���Q��p\W�=My��t�}��!��IW�,��{�I����X�qz9�I������E!kR��>�yų���]b�*~OER�w,�=��'�9���Gةg��cz�ٴ/9��*�+���	H�B��f��U��JOD��y-L��*fV��譜�`���:��8脝
�9���./���U�\�ڹ�|Q�w����ff�P�%^���#�'t�m�C�[��y�E!��Z�E���e��'?�%�[�΢��gE�
YS>�|nj�Cc�>5�j��?g����1#ur'dj������[^%����"n-й\SB۴A�����e�WT ɛS����y����jPING,��Ap���/T_�6Z�l����u�oK�&:��2��V�rRr�4��G�`ɯ�\u����ӣi���cCSNe�&������T�dN�6TQ�����P�E �����A^���H�ʈ5 ��G�UJ�q<�+G8&��64E���J�
�=6��;�}X�5�!:�_�u��3{Ϟ��GG�	�7��R��fD��7O�th�3i�w��̡
����O���~����%u��� $ІҝS`�a�1�E1��㌉OI��<6��8��;T���ƃ_T�$v4�?r��Rw$�u��7�%bޕ*�x�h#"�������׸ă#ӏ�!�@
�t�~2��9�Ͱ3~�<[%�1yr���A\���zF�`Y���c��!���^�Z݌�U�C�y���g��͑P�II��6���:���53�W�����T���� �B������)f�����1�rQ~Gg+CӸ�iJW��^ ^�a�4�k���������=oZߚkܨ�
��n��s��[���	�$�<��	�
����(���Ǝ�6������9�H0_!�K�s��A�їTe�!��?Bw�I���g�:�V��v|0�|]��b� �.�SuPa�!L<Y��U
[�.Y0��+5p>��K����#9��D!��Q3r�j�5�5��)���h�c��
.����"3ڠD�m1�`�������5hS+������BI�pQ�yիº._��	��Lm�R�(/Et�:���� �{���Ȥ�5T�X4��0m�����i���\k.W���cUD�m�d��\��9cZ��WN��u�݃,K>V�f���/e�	Rz����|<А}�D���¼�O��q�������K+�m�țY83V�W�Q%�$	u_esX�E��@h9<�爚�d�(7p5=5ZV�ֱNcs��{V��T���>�8��S�w�;�c���P�P��;ns8;L�d�Z�.w�;H��I�ޖ���6kR�OŠ�=�4w`{���.�ێ �s��Q�)��5VW_ʬR��N��_�[g��W�����5`�m��hʴ�a��������Fo@�Yͱ]Bw[S*�Qn{�!a�q/�U0yL���*?�}�.Y�0�.�}X�MX��3���8
f��fA�㕻Cj>��f���3{鯪(6ڣ�vo+N`�`ԳL=I�-�:>ٔ�NP_�$����&��R�P���,��v�)*aW|�a6*H�֖��{L_�j���|LO�ňxd��FA��9q��ߧ�{�j�8U��q���+VT�a��B=�a� ����P�m'+��%Am۸�7e��~^I�>V�d'���q&p�)5���5$��y?��u=q��p\qf� OZTFJYZ��5���۫��2������}�¸�>��@(2��*�2���o�-9�^�q�/� �t���������HX2I]Y)��!$������ ��x��>�q�E��Њ�PT���qV�`�y��-ۑcB�Q���Q�;��1;q�|��ݾO�8���f𺱁/j��j_��o��5��0�dJrv���!�q�*�m)�!+b
��/e��`G�hS����w��v��~�}�ؠ��8�an�f�N����/��mIO�Hu�{R�+������L�*�-WQ�@t�A#p���^"���{'诽�mib?���B@�a��y���M����*5�hȧ8<�r��P��؋$�A���9�Z���C6i>|�w6NߞB����e_�Ny�%rl�II��G����
O�Y�U@�_(��Ki��If�"se�_���z�/s��,��5�At
 ����Ȏ�zO�yy��W����@7*�|�YƋj�؝~Lw0���<m$��B�����q�X�����E�^����z[��]Oa��Ru~���$�Cu!��H[{�TI�R
��t[�M�z��J�q��	�dd���σ'Mj�.�_�6M.�G�b�;��G@�n���9�BE?��l�E��=J���ݛ��iԙ��{-G7����^���@/���
0�V��!��_>�fC�+Pǿ8�3n��I�Dl\���]+>�E��Byb8�'{�\�Y�J��L�S]C�
�EqR���{�K�L
3���OY��
V��?'5h�?��R�Pˣ
\���~��d$8���`�=ph����� !��bϻv
N��0\	��{�{�rR�b�P
��(�q�cˉj�n���вLUW6Aw�8 ��+�h�o�Ԧ:Oul��+W�#Q�72��O��	x�~TD
:�w=�ܣ�t\А��S&t��ss���SuB��M���R�
h���ʢca��$�����I���ؖ>�
�3�����`����{�&=ߙ$h�bEl�ܒӺ��7�u!wZ��DB�2N��.��<�b�G�^U�!���$����;���q��y�Ns�oR�� p�"����{b�y�a�*��X���컁FʐO� ��J5?D���gTKVt1�=�t� ��0�v['�!��c��>�t���d�v�s0� �A��\th��G�~����~k4*��Z}��`��V��-x	Xļ�G�s�	8��A ��Ɓ�\���
%;8��v9A�ؖ��u��[�,H
Q���T��YY�\4���W*Ah�R`-��k"N�|d�����[
;�0��2�GL��)��v��t��H7��D�
]8�սp���B�C+|,�67����k�"�)F�Y-���夊m��(��?���"Mp��g�c��F)&�?:$D+L�%�l�EA)�i0G=�I��S�����k���D,�= i��$#i|g�rMH��	(�g�
{Z��f�i�8�*�)EK�cn���Nry�)�H�FQ�k��2
G[K�\E�\$²
�����"�U�9��Ѓ�|&��2��}&.8�%��t�4M���MR�l�A�+U�� ��B�^�3X�!����,FA�P�0H
Q���q�<�w�F��(
r�:^����'�N>&�k�Vu

/P5<��"���1�g��q��pHr�chg�A�!6����'�5��QG�S��.��*�g���%�,;�_g�һ�.�w�oS7ֲb�Tgm)L��(S��T�Ţ6��e�����@�s):�J;ʡߝ��[$�+Ċ�M���P ����]m�H�p�Tbs���&e�0�x�_�4�i�Ltw>��T�� oZ�R��ʋY��%rǣ3F��:�au�Cx6�Um�B=�hÆ2�t��*R���J_�N`�~���ۀ:�>���Eh�T7ȯ�8����~���1�L�d�]<��(i>�Bbʇ$݄�� �S��h)?�|�U�)N�z��ː=�/��_Y�;�w�8���?��?�PY�f;�.������7�i�57	%\f�@8Xw�SjAT0=�&���Pw��	V� ��h�$Qnȸ9Y�ñD[r�D������Z��Q���$MJ�z ]d��A��<�Mwa�B�T[GFSg7��>��0��M�648������݂�^�.܂��y��$�s��
������&�Tu����JDw�v�{>pef)b�°o���5@���aЂ����5���5�.Y�:��^�{�K�i\tI>]�e�	�H���⊾�yM[���EJ.�аfv�D,W��U
>�Q��_.�;w�VգW��:K�ES���ٕf������@>��~o��D���vD�����6d��!2.��e���=����|��'�(�L�mX��I��% V )ɚ$9����{���$*���zs�ib�E�yy���
��wp5���� ���(���:�${��]+O�sH�kk�ϛc���BL��bxx���h����ǸU��)Lo�	���ۺP���C�1����P���z�P�# ��e龜-
`�����M�/�V*T�g��ih�~�;�븪�0�s-T�"���Κ�(�Μ'#h,�c�O�B�Y:�֤��lL8�ݑ�썬��u7A'��n� ��j�$"U�WF�]<
������џ$�ujq���ȃ��}KXg�hY��U��s��B���)o�w����IYY]`�g0ܽ���70��U� $���dc�Gٜ��xT��O*�gG��?&Ow�^!�����k�d}�����
����h�2l5��=Qh��DSyy�����]M��.4%E��3��֔w��?�4JAG�Uj�J�����#:�660��3p���v�d�~2I~bJR��C1�9*�Z�"S4�����s&m�&`:��܇|�_�em����8o�*��hmd�N�L6��D�8(�ѠT#�؊�t�N�٦���v`Xm5F���˽*���F�ӣ@$6�T����5o�i��b�B��q^n5v���y�݉���s|wo�88@ľ?�z�#��'���ᐏ2��ICbO9��$%*���\i�#�Y��B�7��@��@Ç2��MI��}Sk�� <<��0��I���G�6:�?S	<��u�a�b�+0EP(p�r'�T���ax�Ʒ�� �	��B�%OF C�	L*d_d���*���ET�;xZ��s�A��Z�x)n���t������mj�*�53����$�	6}����U��"ңx���6���-�����[�V~X:T�c'`[�==j�"�ç����_�z��
���E�k��↴�RB��G��#��`���Pf51,���kU	�R���S�����v��0�l�I"�ƺ7�V��-1�S�a��I��I)ҵɛWOɪ���1C�i��6��A�p���pS����A{�����^����N���\[=�I���@�؏�����#�;�2�Lk���x\�g��y��NT_� d�V��.n��QY�r@-~R���XaΏ$��>N��0E�P5�f���d�De����fZ���c5mԙ������tH׸
�y��dE~�U����bg�4�����Y�ܷ�E����9
�y�I�g~Z�2*��BzX��/�0�O��%V*wo܀#	�OV��Z���o#c��tFi�S�=���l�_��G�ɖa��!2��5b .vw�4�4�a	�9^šj�;�� G�*�$2�s��S|�ҹ~�6�?����jY����j颀�#'[����-+���賥��qт$Go}�RK�)��,��i$
u�G�dj���������٣��c�n���S��Ԋa�GJ~��i�?��v�NDчs}�b��}�a�}7L�����f����2����4l:�)#o�K�1=�p�����oi5��hȩJ&�Aߐ�2��@��%-���»^X�$z
��������j R0z���3���̥$kE�[�?%Jc��a�ϛ~y���_7�}�YgDp���r)���g��y��6s/4�
��R��A<���H�j��	x��7��)�J�+|���K���9?���.�&�)k�R��+��]XІ���IJ��,��N_��6u����3\MB�@�(��
���x��V�G�8mkk���p���8�#R��2;y��n*�L(�5Ot�U�w�H��"�7�_jo�j��n�<�þ(��6�� �[�n�q�y *n3�mh���©�G�=�Y'�\��F?��y�Bj:�|�S6�S��4x��8vVr5���߁��鮻ece��o����3��Rw�N�8sK�����)GT�Z��>�i��<qc��o�H����O`��d��t�	�c��@���$�U�J�6�A6o8(�لi�˜�S�@w�7�����zY�C~Z�Tw<H-y��;�~&Ī,�[ގ>�iG~����R�&2à3m�ݿ�t��=f�Z��F�=�M\�qPҀ�@ʝ9Dj��	��; <�M]�T֐,���¢b<!��/ȶ�ƦX� A" �II���`��+��"BdS3��/�U�ߖ�,��(};��q�m��;��]jߠ��7ɒ�Gr��^㦉@FjjY���#:�-�sFYg��B�d�J���q��M��"�=*������WW��]eW���E�CUƑ���퓬%W�-'���R�Ϟɣ�
l"�!���3�����1�	�Y-y+���CdJ�e��]'�4w���*���0������r����g9����D��
z�j��"��h�-��k&�R�Q�-WH���%���(�����[�p��$ƿ�79	�z����&i�P$u�U:���{*/l�u:7���K�$,���[Ppt�齣J��Pn��ыe� �����5x����E҅��$T`�瀖�����<n�rصR�(]g��w��1�*c���ط�[�)��J�'�8�cċ.��'���2��9��0Iqї<پ���܃y�꼪aH�)% ��N�_/�($Ȇ�m8�|�RT��$���w.�j�J�&&�9s�@����ru<r�T���+��3�`��I�n@�lv�qg���}m��'�]Ȗ�̄�*J��D6z:LP*f.M�?�����'0.͞,|�IU���P�$��[��h�]V1I�
��2�9P٩�@(��g� ����<�� ��»
w.�6 �ε֯����B���(�����UZ�-X�d6�-
���U!J��"映)�� \S!��_&W:��.j�/��ޢ`�z�Y��Gs�W�q������uE��!��b���!X8��1�Ӡ�y��9���V�'u ق&ג&�{�4��ឫ���
wr�l��/Mx��%��?�A�}�O�tx�W�28��1����+�&Ϳ১^\ݾ��4�2;�1��ow}��
޺^��;��)�h�K�����	��V.���X��d--_�ѷ��&��0_ n�T�-��ߝ;�}	=���(�	���Hj�, ��;C�밶"�zL숈<D���'�"�	R���k)(I�z
�C��a��e ��X�O�'���ya�~���4��*�a�C(���B���k�<7��b���'�bŷ2�>��/�/���]>��9�2���!�tB렘qD s�����+-��L�����hpx������Î�^R|���)΁�)@�B/��`��I�<y��������a4jˣ e�Sa4^��������3��ױ�
:�B��&
hy4n#n�R����`���eG��
?��f�.��c����s��j��k+�%�rB#������-�9�T�a��D=t��kP,�9����L՟���=��
��+�Dc�詾_e��݉sq�&�)3O�K���V�;-��S�5)f�
�� t��5pۜ9�'d7$�����TI�����b`t��g �c�"%B��?#\���iT�����5' �-��: �,�h����S��*��U"Nb7���
nnU#��+G4t��1+]CZ����`*�i�t�q@��I��I�Q��?ۋ�\���k��C���j�R� G�T��s
�o��8�4�oÝV���)��Ƒ�d���k�%e1%y�$�AX.�[h����d�4n3%2�ѽ��r�/���tPb>t�78鶅f���`9�Z�����C7E=x�ش����.�,�H���@������Dӧ��@���v��"���y3S7��@9�������yrH�ܕͰC�d�R��B`c�4T�����v~�b��Q>ֽGz�n
�ϝ�"�~c:�%��W4l�>l��yÒU���i	����V3�\ZAʛ�b�r�a!����<�DC�;��S��a+���Z�d�Da�y0U(ɽ^�<b�QdK"�2��i�'��K�Q�
挋��ؖ�ʆ���?��}��F�z���N�����L�hkyVh���HN���N2k�['��rq4�b�������2���Q�����{�q����tn$p"�$ޫ$���I
y ]_�A܈��o9&�4��'��i72{����L�`�oZ�~�M3kv����[�V�Z�7�Xg1| ��kq���)X�t3��0.F�P_]�P��}TR
�P�e��Z�ZŅ��}Q�^�j�уB��ql�{yTva
�.ś��,��2W���0$�$/�
�t����,�Ϛ
�
K���#"J�^/�O7��%b�ڑ��� k�2�����?!��b;�W\o�>��7�!4	@�;4�~C2��)�1��j{��♝���}�UɓԼ���Iu}��\GK$	Y���1؍te�$pf�r�-��X�G��Rc;8�>i�
n� IV$��f"���0M��b&�Ua�m�B�!�W��R�=�T�uxvV	.P���~�X� �3�`b��a�䞬��3 �)���_�Vt�SN���V ��F�f��bt���O���@�/!�m;��K�5D�U�.n�����}%���S.Uv�Ct�s-
v,cC���c E��<��7>Gb�uJ��7��m�v�:��k�#��_#n[��K�_Ǉ#]8��,"!	����]c�H�	9��(gL��cͲ�Ϯ�
X����v�ܙ�(L?�^�)��;�	��{��p�<Y]DFe ��Q�@[�z�^��!f^�Ax;.������A)��=��G��ki� �n���$�='�(��������z�?.�􉽽�s�J^���&�A7<)(1r�i�͸�h��Ŷ�@�r@�T���
�hjw�P�K�.ٛg��V�V�ەFK�_�W]l`!��I�j,F������_�V��V
���:�݂�5�J�Ȕ��V�`�\U���K�5�AH*e&$GJ��;p�m(���1����a�L��w����W�{�U���V��Uyţ<gV��슌cX	99J���Y���yk�; WB�K�:��:�K�i�ŋ�ʍ�:����LP�\��RrW=S��2k��8� ҏ3��������[����l�C�L���y7� �l�Q�G��9U��U^���x��yY˜�������x�Ƃ�Z*���C�P!$�]n�$lhh��<�����c=wD�4�U�8=F�*�[[�h>�f{�C����u�F�r��~�bNpܰCVg�ܵ�e"Tf�Z'�Q�����t4��g��B*���rpd5"�qȩ8u�F�$�o�)��
���-�Hy���G�t^j���Ϣ*��	y��V�B�FǢ`�I�c�=uc��?5m~��~����S1��ҭ��foEO+g��Kz�3�?{]0���_�� WT�;�]��Xqqo2Qc�\}ϡ���5�0�Q�Wu�T������aIz�0�|pFt�Dy
���,�y�Tq��"�w>�:=�!`ɒ�Y��t_�'�C1_S<����W^��&H[0���uM�=�S��tQ-�2�wM�8��~�[�n6�Gg+ EZ�)h�#��^5 �� ,c�.���5 ��®�@��%���!�B��X�LA�y�d�!T�	���LXNZ�Mi���	z�z�����"o��P���Ә�S	q'��E6j�������<PW3 �d`�nYO,/פ�� f�E���x4��ţ�D�c}������)��6�ߋ���[�i�������kڸ�U�� ����a�~ǡ��$:�4,+���dw���A[1SÕ��n�&!Y�a]������0�����W��=����w�xx��ո���Q��|İ�����j�!ӫ��\�Ĕ��{R�Q-I��g���lw�Vզ=�A^��/�w�&s�AU9�@�Qr-�y��k���&f#R���Q��$έe�# z^R��~�����Q�3D�T��^SL^*���j��ڢ��2��7���=�9Ną90��7i$]Y���I�t�噴.���9���U��H��(�$�@��͚�cy�	�m���(g�1�8	���6�̢h]``U����+:�YǄ�����
%�%~0�Ag���~m��=�Od�*�*Z󱒢znN������	���ϓ���,
�^bC�Z��&�!��>d�����rRh��m��~p��e��?3{��I-��;q��a��3@#��B �=�*����(u0����2�|��b�n���U߻��%O�ZH\%��w���!�$�:e{`���)U}��`�����{��c��]� ?7x�¿.�)��j�� /��O��ç�Zy�\/� ����y9�{�AC�q��'Z>��{d7>�)��#�6�����g�uAlut��4�h����)����˥����`��횛� ��au2�_�F��5�r#��Oj��9!�Vr�^$�kɆ+o�9��K��ȳ���E	��,Y�)W�z���R_f�ݥ���L��I�	�kw��L|uT�m����+z���O2���}Z�Y���&7�|�r�
�T*Qy-��ʨi d�H�qUd����u� �8����0���
q��mb���jw�3Ϲ��Ls}@���IS�rp
!�s,G5:��V�Nx������hy����u^�6ޯ�Bg�Ջ2�>��wGa_�U�G����8�G�ɬ-�	���e�T�y��&I Y�v��3ڑ!A(�)@�$S�F_��"��F�<��W�s��I�P +]sZ-�o�2��|��kO<�(���c0�gr�Ͳ,2#D�=ւʷ
���q�A����7���X8�v;�槂pε#���J�dODO���J��e�A���]E]z���Y3��~��n�<�Ldc��K��EC-��KM�����#�)G ��{M�pǾ��'O8u�����_tH�5z��e��\.Q�Cl�`�`G�Q쒂X3q�-x�$*�K�ی�`�\��+���`�z�Q�8�WK�;m�.3�3�'���8�j:Nr��pN����
��Ѕ���֯�UJ�x��3p�Uc0�����h��l�Rڇ(ݞc��Y�����%�9"���Z���o�b�T� ��x֧�<7Ι�b�Źމw�7h~�߄~�\���UM���0��1��
�1W=\���M\����*������c�<3V,�{!��-NmkY�tV�Vz������RI�q��N
�`fb�n�OJ��Ϭ�v����T|J
'�^����z����n�h)��� �D#�S��_4Bu÷Y�\�`r~� ��\j��PF�������̆��
�/�hhhKp�/8�Mk��|^�e�L�NbS�j�/!j����+��O��"a3c���
����"t�m�<a7 �R��3u~������6�ziq@`�x�j�C
6]��h�D9���G!���3�-��E6� 
ʄ�!%��-��G��{r8�� H��ތY�9�S�I�L��t��&�2��2��B�zB2S��i�WB\�`�������O�E�=��^��n�6q7��-P�S�$���f9&�����/ T��T��>��E��>@��ƕ��t<S̟p�<�;̬��Y�!��{n�`�	��o=E���	�\n����%��Ѝ�0ͬ0w�Ӷb�� �Gk��i�9MK�ն�g4��S��1V�ƻ q9��,�������PS����3��H��N�f�K������b%%G��>����&�N]���v���.Z���}�@��J�u�l��,�r�z�7LZ�7��t�I��dڈ��J����h\����+���4�j�o�{�3~�����}!�*nH�+�V���9�"p����sӾ۸�CIm�I�3�5��[AYp�
����K
�*bO�*���Qu�BL���o��za�^����#���6Ld�T���[�O#K)��vs4'�M�����r���U�� "��u�E�0�猥{n���1m��<�'���v#��#��&�M5E1�mH���)!�3U&!��gB�m����Uv,�[�^�����Zg�oîu�h;�3E��`��]8��!��l�������aXc^�?�Lbv��1_�7�:������t�����!��h'7-��q��<���pP��٥u��ޚ��Ӂ4a\��(]�b MY�b�
�ߡ�R,�i���c,W�H1X�;D'�?"�l�,DL��F�����a
�B ��"j���ڈ�ب�s�ng-6
�L���C��(��w��"�ǯ=��Kݖ�"�X���j��C�@�7c���w�7�}��]�R�Y(|�b���!9���;U0SO.�q�C_��b��7�aW�V����i��'�0���`�퇙v�>N� fS���j����ߎ�F�}��e2mH�������-��t9�A
^�a����e<Ӫ[p���^�\�z��0�ąB#�SSߣW#W^!��]���]�xz`{�_ƃ����؆�V-�1[�)dq�&X���T(�}��4[��c��	|u�.}��8J������4'���3����OvҶ��K�;�$����klV�[9`e��*�|��bDE�DnUMc�g��Q�P�����~��x�����b�f���j�nT�%O��d��C'J�w}����L��ZɜB�ȡ�S�Ǉ�`G�ɲs��أ�x��D����@U
�y�i+�9��7���s7�Ӂ&����ն+���%Żk�`�7�n��$�b�s��i�rD�/�~�������E�@��CcP���c����~_e���b��� ղ����"\��(�w���%�TēT�1���vώ;JU�F6�Y���ѧ8�ܳ��T� Z�6 �A���}�o�K �*C`�N_��ݎ=�Z�qE�+�����U���7b��g@PK3  c L��J    �,  �   d10 - Copy (29).zip�  AE	 ����΀�MqēFYD`@)��5M]
FÝ�3��x�6�j�!�ɬ��R�h9���~*�js����|��"ҥ���)q�B}OJ�7�U��U@�����g�x;����H���S�t߽�"5��n҅rl��
��I���'�H�(b�p�׉X
�P�Qy��hymۍ������� �J#Y����z"�xYt�V����%4�0����ٜ �j�H6��
N��Qa��&�)L�ϛ���Kߨa�&*��2�]��Pz�t���v;��嚡�Y��Y:ۚp��'�چQ63�3�~��U����|�u��#��(�����i��nЦ�-��%�$`�W1wv���\�I2�T��(k�l�(�зj;q���CmxP="��
���Y�'�N�(M�:/9Ҕ�Y}+��S���677�"�+Pv}'P�!�)`��a��d�s���nJ����ȗ ڑuA�~����⑩ˏ�,�K����B�O�G��������<����n�螚�,y"�<j��bIA����
B^�kc�}�ZZ��Y��S.i2c̽j�=����q�AEL����X!�
�z�U��c�&F�.�G:�  �3��Ko$0�0z_	��k ~z���0����[BsQ��i������C�yW�~��9ga��[��M�?�{��^)�w�7�Qŏ	����x�?K"F��v�;�B�`R{����H%Y�;"xƸQ]n��՞H���s0�o�>'��#�Slf������ ���kyب�"C�����r����������2$@b�2���Q��=wq������M��i���a��7K�0/�#ur$�B�!���� T��4Q__s���;��u�E���?�3r����iL���
��r>6��Vg�6�y�L"��96���zA�@�)�"W�5[� SX ��n�FÓ"��*M����1��D�E�C��D8�%��6b$�'�x��,�Z���մ͏V=�{�=�a�P����'���1Ep57�T���{u>"H^z��:>��"2x_W�&#�7?��
���5?4Xp�J|�s������b���'v��^�[� E[���]v�aco'H7Y��bR��p/<3�MJ�.3�Fn꼇	��Èr�HB�H�/u�v5�A�o
��\�c��M���T��)]�5���@ch����;���jZ��<��LZ^�����G�蟸���M�<ORC�Hԡ��9J&T�~L�VV#�Ӏ�}�S,�qE�8�I���|���t�zP]4z�Fa�����ջ����!��q���t
V[^����g-����,�"�F�e/:�2�)7-�}0x����h�B4~�4��]�l���3l����[k��D�y�U��NSt��v����d���K<Lq�4e/7�����5!��[��_�)���z|��4N�A�{.Qz
}?c�r��%Q�y-��,�@ ��J�;o���%���r��)�5U�`���y>�rH�S��)��������b�Ic�s�d���9w�?K����d�	a�w���X��S��ObL���J�k���g�1c���D��H�ZZ�\t)���գ��#$��h��)���>v �+j]���r���cς�s� ��o��7LY�&�G�6�w�Pe����ي
�?��k;:��f`�Ryk���A�h���e�?5J�o@E�[�#x�H��jE� `jh�m��Ѧ2�Q��	�:�䆈�bac
��y��/����.���I�vߍv;�4bpW���j���c��6w�6K�-�x>�k�����=0��(�:WѮt(�8����mtЗq�֘#��k���s�klaaqY�)U�.���i�p�}vvA9��QG�I~�C"�H��+�C8�"�/�m9����\��e�'B�([�Bb!�,�FRS�
���6{$��]���? ���t"�@�1Т[55�����{�5d�qp5�"i�R���0��>�S��bj>n=�N����g&�U�	�t#RB�Y�P�I��³g��掊ON�-se_�Oy���F������e��7{���r��Q�A�B�A�q�a<�0�
����r[�*����cnU�  �K�/_��Dg&hϕKc�qFa�dp٭�kTO�=g_dj5��y��
�ɦ��������~W�[��C��Aњ��O+���?�t"0�_ѯ������w�Θ�� �������{P�R �i�ArF����?_��<3�_�S\�
4�&��m"����m�:w?�����������N��3ga���!�[�i@9�&11�x/�
v�s4�~�`3"5,v �T�G��^�`���R�j�[�6i���?i��8�
��*�D1����Oc�AvC��Q��:��^�`+��|� ZLj�"��A�>f0f{|�� B�z�7����Q4Up_����-ZHJ��0��h�w���h/�Y�Qx�/��S� ��f_�O���3����?6{�^�j� ��ߛ/�8|�e�T�c���AQ��n��r^s�A	$����!����l��<#��Š�\��aT.ږ�`�t8+��D3a<��]>���g��R��L���H:;l��LЎ���}<X�]�c�5w��Z�r�pć�|�E�]I�����Eq���x>��L"���w�L��:�;��Ľ2[��_7(J|�&�1̥@;LQ����{��,}?�w�%L���NN�NFK��y��Tz�u�t O��ߛ��J��d�dD)c���H\*
�΁ky�����-�|�pA��d��d�zd���H �2��rB���)�u�Q��jb��Ϊ"�՚\c��r��2<�!�������`:J>�1���ng&��j�@C����݌��C�lV�c�Zx7p�M�z"�K�k�m���Y�FVIЁ*�dBv����M���ՁR�rt�O�ǧ���"+�Ațb�
X`.Q��+8���X��d?{������:����-����7���k�z<�yFƵb��?�jA�A�\�֥���d����ȿ5�p�d�ݘ︓4�{N���>U0)��[vN�`���)FG�a��.�w�7}���y�w�] ʠ���pKX��Qi�?����� w;%�Pl8�q��3����K<]i\�=6b���$���=�����k�p��#L�֌��@�W�+�@��P�Ve�K�f8t��Ty�2�v�A�ٮ���c��\W�e���3�rl{jزȞ��i%�����͹2����������H��j���pǏ�-:!ᲥOF���o+6��Λl��
�-q�j3E�*z%�Iu�Л�'|��ɷ�y�7N�����駋|f�Y�q��>��|�
5�}Ә���{ν;��R����5�;��[&R�<A fݸ���cw��^��&7�	�TF�ax���\C�����.��\���5Z�@���2� J������� ���Ӷ�����
�eeн��h��/9��5"���(�����nw23�ur�ޛ ���c���;g�zX89۞��H}x�ǋ��g��t�_��*�F�n5�gT�
�_�� H�Jf" ������:k6)NLv��eV����nryh
f�����/
9Ɖ���IA�0Q�&���6��πF���f�#�$D_��
#����9�G����e9�2(��h�Q$��ńueK}�]$c�&����h,�E"�g�O�h���' �*�/��S_�m�O�Np-���U�˩���@U���w�)�P���c
 �T��-5@dç
�'V<�Ie�$,�&3� ��!눩���.�Q~R�Uuɋ�g��U�� z��{�dgs&Q��y���
@��e��ݮK��o��B�E���ͨ�P"Z����9Bw>_�+C0q=j�S��i�;Q��9�!S���/u_�+�>$�
�k"_sx�7N���'+�m�Si~�1���?�gz�OBu� �;���ڂ�Km˱������љ�v�
�;.G��(��L
b_��d1nbG��s�1��[���)�L֚4����.�^���Ĩ�+��9��-l�0���9�
5���qT�LF9�>�]o���51�DGW5� ��p-�ÜZ��&��r�@�Ǻ���iQ���8��+fg��c��߷�θ#��r���u��{����֤JL|Rش���u|^�M��3��q�Rjl�4PT
u5>L��.
A�}%P�9�'��y�4�����8��eej"(>�n?��D�Fp��y'X�]�`kpX�~�I��s!�a�K�a�!w��p,��ƖK��m�����og<��u��IГ��x�Ӏ��LA�H�ͻ����7:���z�iDW��3+�K����U�U����]4 d
�H�&��)��C��CjLfˣ�,`�_o�;�լȷ@�>X�����[�Ȱ�8q6j6XŘ�u�n,���i�We���h�&7&�z����#���l�� �բ��:���4�B�V��XM�қ�*��-;;w�`�ͽ��x��ƀ	�GϝJ9�y2�"�ItB=]��f	�v,d�-��؏�W����Ӊ�x�Ϸ��2�d���t0OR������{�����{O�_��>��J�t���Vy|nSak��筈�
��u���7�V*�bK4�*ƈ�8W3:�l�:O	~����+
��eNq��p"�^|xLGZ��x��q���_mԞ�m���y��Ŋ@��P%
Y��p�T��\�c�(D�_z��?�
����;b0�����#����IM���뀢����vs����~&`˞�H�x_moo�yL�?���`hw�3d�������a6��0pn���>3CGz�F���JQ��}h�z�t���&P��>&�M�ܻ��,[a��!6`�>�Bi��'�!zK�qt��s���
�7�J��4X,�zЁ|%H��&��k$	�L껋,f� H�Ao���<���3�f����aucU����B���)�JS��V�	1�Y+'������(���Q�?��Fx*���K��X�
j?�����i��������I�ۧ�fq"��\�d�(�z��7%Z̷�C��/mP��������_`_E�oY_-4�Ft"�KA��+��|H�z_�,�0��Z4���u�E���_0�_���/2j�y���MP)tts��@�>���M���d^D�}��h}�x�)��
 8�koު-H[�綶��>o�).����j"c[��Xe'�{=P�� �%��_�p^�����

��H����TNݼ���nŃ���hS��㳦%�r��8k@-/����5�\��N!m
��>�д6{"j���2XC�;��%$m"n����
!L���r/q�	A�Sq��k%�����r�&��Z�ǎ��cG�b�Պ�wkZ7!h�r4�Or5D�s�a$A�`�L�=��N�2�D���$Z�S��ds��0�ix���m�HS�s�^�mk��ϋ*�)r`��BJuF�����8J��T6��8�p?*,���s�� 
�>BI_PK3  c L��J    �,  �   d10 - Copy (3).zip�  AE	 ��B�T�s�ƧN�P�;t_��C읅���7<�b�?�m�����O�Bn��[�ZS�SĤ�/���J��-�����硙
}��䨷�	�q/��Cw�*q�n/��%H]����E�z���<����Zn�Y�K3<��5��r����d$(��X�Q�TvX= فaF��z0\�D�AK6����h���-
P�aĦ�)�ݒ�^��ƿ�t�IR��ڐ�;�y=��)2e?-gz�M2�
n)����]]?7$�y�8(�=���V%q�3e��"���}�u��K�]%� |��:�~ƄkD6��=^{z��8�Q/��x�d2(
z�>���g{p���Or�U7���p%��.˖�)�z��/��t�e�/��x�na��C'�h�Mm�F�D�܃��u���rA�l��CYɔ��ڰ/�S�JT A���R�b/��51�vG�OtGfrZY����]����@yZ�HKh����
e-�et�dQ ,	|8�܇x��E�v��x�͞�,��ŝ�I
���7p�;����Y���]ˌ�[��~����Fr�D�m_2���2�V����U��l�b��s-�����UȕO��V����f�^�4kq����=h�*d��S�i��~Ҁk���j
}_�4��&�>TFͩ�`^0q�������s�Bu�{�C0������;r��)'Z��푮\p��!�9�[���*�.��9�A ���� 0T�x!��X�����:��.:Y��U�T�pٹ�C��h���}`1l�tr�8��0��O�ҐưI����I�>f
����;��+�'G���]���V
�����v �������}7�የ�Fj���[�7]���"h2�S![1qD�j��՞q����^��y��<AI5��Oӿ�An�zxɖNTcnv5�6���@�컗@�iP��=4���S��i��!�	���Q:��iDQ��U�e}�N�0�{@�/�S�+��m��'p<� �p�rZ�M";�ʰ?�j]�)
�
�9H)o��'P��t �@{��	��1�7� \�Y`>����0�����&R���x�7��*��!�3Qr�q��.���h�~�|tm>�>�7y����/M����?����4��~�����o��!����<�*�z:��`��>d���3^U���p�� m�����k�"*;lNj�9;^i�^�1���I>rv܄�)�� �mGIo���� 
FN�X~��Q�������3�g��U�s��3I����u ����ק4��ƅƟ�K�zV:s�ϯ�y�%�*P�̂"M������Ļ�%%d���a$~��4�CR�����٧���8�u�R�"I�:|J���5�4�V�X(��OQ�9���X�����i�v��	,�ĩ�	�r���^
�`N��V�R���URiOM��]*��>��(Lc�mb��|������Hn��WhL�I�t��c�_;�)�jg.-ݖ���7a
��п���J����t��7	���
Rd,�@�rՑY��=r6s&��c�\�0:��&\?���wc��ȯ���z�*������sI�Ӏ�S��|y|1���F�KF��\,,�̠�}*�^]��E����v�A�0Nd�?\u�ti���i�8�o7�/���*Șޝ?L��?�iK�z��I�'�dZ
D�(�ɮ���f�X�Ž|O^ Y5���?�lm'��M$��o�9��֍�����[X;;U�&p/ԙ��n˛`a�i9AREI���	҇U.�����-��4aGeq�МJ%�)��h o�+�{}�!�N��4��f9:i
F$C��#�E�GJƇ�ţ_���fW6���E.n�{����q��W������� �:s�	����=��3r �l#��(���*uԌCajP�	�T��9��g\2��Hob�v�I���
@�����0��%�	,�j&�D�����A��
5��2b�0�SхGmO����	N�IN�~�VG�/���k��Rt���/Y3d�ЄW6Q�)P��I���E����ǃ���|��`���7�v+�\>��?H��*ƬM���ARG��Sf
h�҉��h$���b���Xho�߃L����PD��j)�/6*~�����@a���o�z��p�^��VNp����>�ۮ�gh&���کх$�}!E`��OO�����[�+�Q���X�݃qS�����p�sB1���oЙ1~�pՇ��f`�
-���y?�y'0��M�w9V��U���x\ �M;	��̿�
�JR@�h�iإ��'�A�{�����	js�J�EOd4+~"��n|���uj�I���[_Q�0����b��g��1+�<ώ�jZ�PJCu+
����}��u�s��Q
3_yƨ�f�*ه�u�#�����ñ� ^��-��U5��<fG24�J|�}	��v`��K���h�n?ѷg�e�{$@� �����6o4���K B%���dNo�K�?U�'��n��FӸ���a�a��I}H'�Bz��`��b�߲;FL_RvW���u�l�S����u��"Li�⤱�
�ы�.Y�|�j�n�l�;�Ì��g���Y���t�hߢ�5�����9:U��
d`��ԝG
��џ��3a������p�g$������iC<� ~Fn]u����i���vP��i|�o��j�]�+.e;��Vi��	?�#�VCV<>��nDu임&ȀmD�J!�-��,	緧p)K\���Ӓt�!B����gC�Oe PxW�G���C�h�!E�d����_6jl �]��þ�e�2HP�9�B��D��3v�D'����i&q����qN����O��۞)�W���i�g�Q(V'�4��L��gw��@�$[b%YT{��������e?��s�0��|��o'�6��[���a�h�g������mV"b_��s�?�O'-`��{�Zu#ﲞ���`���%ԩ��q^>��B~��H�a��%e�xW23) ����7sV �Cv��q��8���f�� ���M�x��bN�Gs�^��0nl'�q����%v���1Z7��3\F�xX����n�wi�F�il�MR�$!�`����Ǿ)�	�I�QUb�7��M�T����T/����k����@ׄh8����u*�����5@�����%���sN/����>��ѝӧ��Q��-aEX�/>���!�����~SH�r�ܸ)Hʪ�	e:��2��o(n��<29ua�n ���7z�ab/�h�	��Ѽ��6��<h�9��+���&S�0`�<�' ?ADj���Wu�x�l���zȄ^k���x��ai��u�쎂o�C垂[�%{��N��T΅��@��������x���FM�%���qj�
m�p�8ۇ�X��a��	�o��~�%J��Й��a�m�W���+ȄKdw������	�?r�wE%�T�άa�C
�Y*��l{)��"X2��:y[�G��W����S�a�=��&z�/�<�1/�U猖
��<A�� �W��w���V���S�2��e�E܉�Sqd������q �{>�?�?eT>��д���
wG|��Q��P*dQE`��. h�j��#�%��0 kɠkC��v��u���3
��"��w�H��H��w.{����}�VÊ��e0В�ӯ&(���*<�isz�?��6�1����q�V��=
��r����ʼ:,��3�g���Ε?@E�(=�^��78�T{=u�{��K�]�ǫI�Tp��\�rU�:ȏ�ҒH��/�ݪ
��yλ-��!��A�fV"L�-N������Ĺ��4�ö�D��'�Y:0N�C��WH)D�PH��t��J_� E��
��j�� V
��JW���D���Jx�-<*�;p������%����`n=eC;WXxwKq�ݺ�9;�R`	q8����!�Ɛ
*a�٥oqE�xc��ό���
���0�zq9o�5M�Q��$Ɇ-:�����.�^/��ˉǯƐS%�ܴ�۲�迾��ƅ�Ko�+�����CՃI)�'ç��ΌG����'���c�%��=���>C^ןu�
P
�L�r`���I_8k�%���[�r�Ţ�$8iFo�! �����O�q�_�;U���mZ�+��c�s.��.Ѣx�XZ�c�].2�t�0t*�O�zD'���OA�G�������-���Ѳ�d6��GᲹq�Q�{$���.�Wj��	>��4ع|h��jX�ˍ^����b+��3�\������Z�"|V��
"P��@�,���	��V;9���ss�Hi^`1��h����7�X.p2O�J���l>�8��ν��f�o��0ĥ`OZ2t���Nʶ�Ra�+D/�h��O���J�������n��2d+ř��Z/į��L=�L����2xZ��Sg��f�ԬLI��1E:5�{��VA�fx�P�k�z��S�ҥ�X��ͦ�R�17��rn� yU��<�XǑ��ϏD�Av��v;��#ht��yt$7݋�!) ��h��N��`�
�2mu�Bq�蜑�+6 ^�uEٷ+&A~�?���ow0ӎ�6���w1N�hr<�A��J}iC:�������f��v�nřYm�AHREg&���A��	�&+<к�/��t�N�U�b:`�4v�j/U��%E:pTx�<��J�+�16�$�E��ڒ{�\^��
�ȭ����Ԩsy�����v������V��gN<
9�V��1��Nkw0��gK'7��K���
�����.���"�Z`pl�NMx�7�T�����b)u��?R���޾4N2C�RxV9�%�M�y����M�^~��)<T�3Q���:��,��zA�]�r���r�p�F���2��E00����F��`�ױ��ݰ'ɞ��2OH��'�=�Z��-)��UE)Ց��������:_[04�G�=FJzo�X6��g�WA����&F�������r��+n:��P�4~S7= �J�����:辩�Q>��"�,T삵����zѽ�d *�k�v��Ű�'��S�Y@�%l�V��
��늟h= �N������y�H��0�W���`���]!/~���1��:���:�Z�vw=[u;.!"u�>lJ���wYy���9�ĢpYY*�-5�x�m�\�y�� f�M��.�������`�CB��~@��S�";�Xm��j�:����1[�rd
t�bH5�o�i����Л`K�RV�P��J�_��x���)eP�}��9��%Z`k�;�A�PK3  c L��J    �,  �   d10 - Copy (30).zip�  AE	 E+�T ����	�Rϲ`B&\Rtln"���8�`s�#	��+�N�mǠ
�xN?���ɺ��U����q�wkF�o��ls�'Z�`b nj�Cܫ=^�[�m���������-���N\j��Fh��#�4�❔6������JPG[�������5����?�ފ�2�C-�!r�?Yf�ya��7=�w����I����C��1�O���ɛ�.�%"����;1}whâ�7k{�����IB�f%��I�%OP4���	��Jm�~��IgWK����g��G{2o�NbwD�^����h�/d��T�4Ve�An�IGfJ�LK<@bYc&�q�Z/�����1���Œ�kSE<��H��=���QHT�sd�j~Jx��i�=fB�l|e�눩#̭X�fP�0`<
���Ƹ��(�� �u�Z����{���L�Ņ:l�R<ZB��H�W,.c�P[�(�x���<�ټk�Q$i����@����b�D1��9���][9�7��J�,���
�a{��WrJ!�jl�ݣ��(w�
;լ�2 �1�m��R`ѷ����n��|�E��ixV�ٻ?����^W5���F�,�r���e���(���?���Vcm����R�)�ţ6vc<B�ӧa��1ݝzS×���c��Dd���e���I�	%�>_a�T` .+��Z:]�G���~�;Y ���u���;��T�:�5�{8�~Z�)��:o��a9��l�e��+�f7Ҳ��:�mk�=/J6z��a�#]�xq�w�W�J�S)��!
2DѨn��4��pD����p�����*�ۥ�k�����C�G���?
��c�����E5�*����'�B]��?�U�\�����!���_���827kaf����.��!��A)�|��?	fv��h�f)y�(�%t�&=Ey�F���H)WW)B.ɀ!?S3�mju�K�x���u'd�a`w�� �z1�a�xJF�*9-3�g͒<�L�C_��;+VD�]K�0F�rj��ңHl4�1q߾IX3r���0a.C�c����sEt����
3Hݵ�9��o����g��Yz:pT�`Sr�:���d��0����;�P�vl�?i�0�L��8�ẛ�gh
�P���׶O����O?0��<q:@zs�+�av��B^A��p��z���U��&  ��[���������p��';wy<ۻ����80%6�?�k��[,3OT�
E?2jE�Q�R�t���פ�UQ��ӱB�6ƌ� ��K��*R�F-VX`��`�8#�2'����* +���oq i�m���v�L٠=���W�d?�{�C?��V�@��VFnAY%�s�V��k�x�����k{�Vm�!�i�X���؋__v�	�z�(�f�k�$����Æ�@C�S��q��fn<�B�Y�|�Y�r*�z�!�R��]n��Ġ��#���\-3�/��3��m�(���!�?�
B@�@�|OH��bÑk�uH��C�BMp��H�:PV���S�8DT��vv^��_B2t�>���f(����4�S��?&~���_�t$���8���Sm�d�!j��Σ;���4H��4��2��HA� �>�-����Н5]�3�Y���p�56�a��b&��iqΊ��C�E.g|F��f��P��=�16���\���`42al;Д��Y��ݮ��3��;[]H�qd�涠9��k[���\�O�6�Ar�S���u�L�z��d*��"�x�v����m������[���E��lz>
2֦F!u[�%�7l�Ў��L%
��a��l��ʌd���3	�R���������ֺy��cS:�����
�s�5��i2�T˕g)�Ӹ+vD�n�i���^}E	�����q�����Ú���|�=B3x;�0Q��ۓ4�zw���	h+��֚��aF!܎���P�Ȣ����9�.,Ì!q��|���L��_��|P2���O��t��np#ҕ*hv�Ă���΅���WxEcx�#�U�m$�v\*T�d2.���e|���Ǖ'I���1�Do��d=��&�5�����MJ�U�o\���Tz���yc3��R��_�'��� �q������I^��^+\cNl[�"���0��z�f���τ����'Bq=����C�s�?�M��)]�ya������!6�L�k�/Ud�w�DU��EN
N�^uMg~ed�]���n�a��n<��m��B�
*���Y��E�m���
�����8$���]r�l#����Y!�H\);��_�UJ��\h:�c"���iY���va;
j#`���߅`�B����+|����gP3a#�WO[��t��s���OS`h�|d��Y�d�1M�������Bx�3NQ	���3^� ���k4�~�H7�o|�����ܓ��Tmb���b͝��Zs�Fw��X�1-8m�[?�p$���;W������P��$�s��T��Eb=Y"��[���ڿ*�*�;[W�
�g��IAl��ǝ/}B��~Ddcu���1ꥁ���f�΃
�o�T���U�m��������	�\�o��U%T��ёS�i��:��,��͠�Ҏ)�|�na�ŲGy|�K%N�"1A�#3��vT��5�l�[�f�wh�D��uX�_��-�Ⴊ��+�z��S�se�_��=h��-��V�vW�u�l�D��s%��ƙaF��~���x�ŋb�}/����q�� -CI;&��1g$/�����6�:85�7�g=�އ����!�j�3��r�:)��sm9�� ʳ���QW|Q�n�v0�Ot�;��-bE�';�lm��g�\���"8���U��6 �Z�z�.�z_��#.*�AEP���nT�	��k{L#����Ήh���uY׌���d����	�)ӟ��3JwXO�<ʝ{X�n�|6���M-�c<"R~_��ɾ�>'B�v�Y�d�q'��c��(p�={�B���Tˍ6=��}&�K����m�x�/���N2��r����ʡ9�j0�u[.��Ù�O�v � {]����n�U�]�(�[GϮ��ϑ����EɐRO��
��p�����w�R��ߞ�M�fP����h�����fME(��Gn>�� ]Pf6cA�.�f=oݮ8���N��
�W'R��U�8W��9;s�v����=�9B#���}��m),��wv#�S<h��9M�-�G��Ě���ʕOz
V�J��QrÒ�
4�3}�n�w\b+үfB�q��n�����j2�C2%���b)"��;��{�C���x��' D)7!/�.���==��hE�$v�g���ڄj���u�
~�����g
�3#
p�{Fۅ]e�$�{��I�{���졟҃�l�<�o��
�����ߵs+t�@�h�<��uم�>��f����4�Jm5��fU�U5��6���@���
���E�s���) ۪��)C&[8�
����m�)�yw�
Z�v��vE�u��] �0�)�
U���(01��ʙ�K�s�`�}д婺'�C>:�7��qM�i�\h[g?��G���g@��L=������������qX1R��v3=����C�i0�Vt��F^�QG�j���@N4��h(a�7�1\έY��\[v	ox,
�q��.\�xC�'�v#=Y`��u��k�72���.=�
OQ`���ߜp��l�%bB���$�,P-O���_k)u��+�Z��u�=�F�K���  �D��sc�,M����Js�J�P��)K���=;w��F
�J�h�۾9�P�q����i�����p]v0��	l�T��� �c�yߗ�z�?ׁ{F��z�#�в�����~�qv^"E�[/��@�x�	x���P$f�WI��<S�H��E����u`fuu(k��Ck�N��2.*��҂yJj �}E�S��Y�ӳl���P��R#-��}`���?zM�Y=5�Nm�B�R�*�c�S��8�?e��]��V6��K_��q�T�F�C-&�6��5��
Y�k�dr1�|^�ya�N9�R��~	������E��
辷���/�%T-�Iqy�kv�J8w������j�u�c���k�\˃JwB��B=��0MS	^�~/
��Q�"e�|�l�4å>_��0�kS�sX9��s��$�����r�͓���Q� o�qա�
�JQk|=ku/;z&-
���A��
 ��V��km }U��
ה�gf���m�uf0
��s`����O��-�P�d�d�E��؋�VV5�[�R��\�H�s����{@��(���H�p���sV��Y�;�[s���Qf-�t�n�
;<"����~t���Q�����?���NG����ß�y��1�N�.��2�.Zok�1�řU����n��k�Y䬫�}k�O����e#�̘l)tYHgT,�V:`}�T蹕X��Z5�I��n���aX(Y �\�f$�R�L�K#08�,�J�e�O�}�U�7�����"OK��M:��՛�,�����g�D�����"c0�H�PKK��|^S�?[�^��n"��~
�7�aF�T�Q
3�5%��b��V�'38�����Ih�ظ�ơqסs���Z�"�*؏���5��i�y��ɣ~w�XA3�q7	��uU�z�=��?T����,4�"Y�m:�U��R@F��_-Z�N�j�^�x粔���^<�����=`S��WЌK���q!��{E(�QiG>��O�<x���j�P��!I_��Oj����U>���%I��:#V%��/���)��'��oPF���S���1��I�n{Q?i����>��(��Q_��U%ep��ϸ�S��t\�t �|*
����p>ϧFo�o�H���>���W���):Ac�"�r���
wG��|�)%�V�6��|�˿��P��)Bn�$E�+qL	�nAUMܩT'D��%N�!8V^��N�_tQ��������>'yi��OC="8&M�Joߦ��T@���kP;Dk�v�0,�(��?ݘ�
8P�h74lY{o�V����53��{���W�"Q�^����o?,N�$g��6~@���ˇ���d$f���k� >�2L0�5f�>�27=k����OZ�%�r�ِ#V��u}4�2���ᭀ��Y��lVN嘣9;#�BwA.�}T+�:��w���g
�H�~h�Ə3��uL+�Y��:� c��7*���'�,��9�:7�ɠ�S,����EG�25BXBϽ����e����wၝO݆�2��v�u��{��nzD��Ts'mK�� �n
��7���}l��1�N�G  ���v���Ȟ�C$��OI�n"?�t3�M��X4��_����Q�b`BѰw7�"���k�=� �fGO
[�IE��\wܐ���P-[��ѓ�S�mӴ��ҫ <4
.7�Nw����#A���4�`�9�@l���{� >��Z<B�i���Z�p'���Ͽ�y#w�������DqE�	wRd�̮I�M�+�2��}&�l!�]�Nh6�#�`��1�+�$|<>)�*ul����KA�V�c1Z(����Ȉv�0 ~g��t����Y�B�" ����Q� ��s���e���L�ta-|҃}*O��/�j��q�,q2�
�F��xD
>�@jI�9q�Ll����><�vA�O���ۇ�R�񸝶����Ihq���_?��\ki�	� �7����:}�ё����B�R7_�o(3��$E	���U�jS�����LB�d�$W�駛�p���~ޤ�-*h$ ��D�dؘ�s�>
R��W��'���kql���D��<�Uׅ��s��Miٔ%��KVKE��8B=^l�aև�}�̍.3���d��G��cV)�<�[���YS��,q�R�W���>�#�&,O��Y���[��(�*d~�Uf@s\�O�˭�#�F�#��
��
} རWa�2�[�\��%f����]�=�������f�߽/�"\66��V*.%�M��ez�&�{h��lJZ���B��p��MxL\ϻR�m`��ԈrB�L�x<C֫�:�W���ѐq�j���F�Vq��<6�Ͻ��̀�:�H�#$$�<7%l�kU����	�%��SNH�o��/L�Q�0Y�T]���	i�!A+���єU�D$�y�����.>�5��1@}�a>��6���[�z$ot�5�B�R��H\0a�"��(�jI���	;��CD�X��A��R踫��iuB����1t`d��/=㐞~ڑ�9ζ�.�r����_��h���_���֦����S�*7AH�����A�-,r�c;�1m wq2��)e�0]m�?��:/Ϩ���I��]���ɒ����	��c���\��B(������n��@o�ٻ��(y�ʯ� z#����(�a/aNEQw ����	_�W�X����`�*Mͮ�H}�B7R���u!ѺV��P0�>^�Y�Ua+��e
/xXR�]	���?:w��5��X��p��9U(�m��J%��� |���l�����,�*:r�0�x���'蝂�a�v���뾭k.4¥�������J��Jd:���PY���i�6�Y�n��jvORx�\��S)�6oe������'N������Yj�f`��jj��wd���Ѳ�<�Փi�i����P�M��� q>��'��A���ya���E�)�{��ť~���@�O�>�������b����b	�q�{yl#,;5Х\?������e�;�*���w��\�z�6�mPK3  c L��J    �,  �   d10 - Copy (4).zip�  AE	 �jAÕ����_�?'W�~����:\>j{��G���W>QU^���H���~-A��^B���fh�/��p0¥:�iT�mcL��-�ٝ�L�U�LzK�%�J�ZJ3��	1�����6�8�X�5ܻ}�г��)�.��Q��a���)^�f��˂�&����n�a��w�7!�~}|�^�4�`'���J�
f�UuZ0�� ���ީi��|� ���e�ZX������S�+Ύ%`���B�����I�&����#�4��~/�T�RDH������^���\O`�"�ߣN���Hp3���P���/g0������7�R��I<��k��e|�XX(���#�|�A�]��'�`�i��!G��ϵ�Z�
�z	U�����M�<�BkX������e4,������,��F�c	��#���a^+���_ʾ̯>zb��	#~ȷ�RSU���#��P|�ӓ��m�<gJ!�/VgK���z�%��X�G���[�o2�q�)������Ǭ�@|AP�4+��w(�f���j�rx]��S܀Q����
�s����lyF�],.�qe�����#r6h�~���;���.�;=����%(vu��[_�
p'��#���������L*�	������ꎑ}���ׅ�9���quD���Ēt�	{�H�ЀX����2_٤*��%���β�xk���@ܤ*��|�r��c1W-+I��d�!�Z���Z߈�C�ꙉ���ˀ����@|3s@���o�Y�½4t�:��0�W�˂�z5I+
��R5�b!�	;�W�5��cz��~Y�=��n��[�CWf
�G���5��X���㝠K�n�b��<X�01�c�Ն
�RM��xCHA�#F���v1���oJ���Z�(��~t�-���!�0��T��&����(�������?�J1�ǽ|9���a��u_
1����Ȣ�{=i��4��M5�A Y�ԅ��+Z����c'���&�-Z��a�#�����;�^a�J���Xz[
�
ǬmH?��
:�R��"+�}��7yA>
��hm��b`�e�3�s|�h�
.݆x�֋��[���"u��Z�C '"a�q�64��FMi��v[t�U+ҳ��$}A��Klb�$?���"�	*��7fo
 f7A��������Lk0�N~Ǟ���-�|�J���|���EG망�5U �O	O�sL�, ����L������u���RK�}���O�?J�x�a���=�pk~��!l��%/��)*Z�)lj��J/��3� ������ބO���ل� w�wK������O#��+��8�Tu�-��4f�ƶ��|�~��9��,vV�m���ї�|�:Y嫴]�0!'���P�7ٺ}�
��91�	��(��mLQ���B��}�_e^�0��S+�n�B
,,��C�:x�C�O e3RPƪ}�����Z̢���V��%�Ð¦�0O7f�}�Rqi1���S!���be��V,�xcb"���W�.��0o����<͈���0���>��t���G&��w!���gin�ts9Z���>� ď�Y�*��R^!�i����uU�kQAs�����	�M��Lz��E�U���$�Һ1٤���V�}++P�7䛳6��q����2Zn���8c���է#�Ӽ�r�t~(�n0�8P$�ɪy�����~�Z���;"	1
��[5�ܞo�T��1�)5�֩\N&i�Dڅ�d?�fD_ �+vY�SE 
8�~�k�Ĺ܂�:N�7��o#3e7.�����iz�y#3\���=��4@��9�eæ�y�C��XNh�e��?��`�ϴ�]f	�и+�H����B���a �ݾG7nm�ڴY��o�=?�87��;��\s	�i�x�m��h���6z2�����
_w� �۠ٗ��Q���`h�+=�g2Ԣ��4����t�A�^R�]B�D��AH��m§���R2��YR��GA2�!�J[�����7�i5>J��
}
w��$����bV
�1"��J�-�r�] @:�̮w9��X�ף�F��T�����-��5�}��4�!T�-�}U��%Fsh���+[���6=.GN^�]���l����Ì��nJDMs񺐅��w�Bej�Y.��������C/ޙ��0%���Ƒ*�ߐ����/�a����db#�#O��#(2W��5�ܹHߴӌ�b�?E����/��_?�xR�����G�l�~�0�8��2�I�kl"v���̴B�j���$JġP~��e�rd�)�2�)��>S[�V�����B�5�Ï��%��
���_�c';�8��g�q@҈�K�c��p��7�D�]�ru�Dwm���2I�K���j���UQ?!UqQ��
"�V_ �N�b;���r����eϛ����+�n�����E���͍�^R�9��֑,%EL@�p��>\��4v��2��Nzwā]I�Й�!������������F�4�o6�������ۨ�)-���]�=q��-�
���H��=�}�������)_�C"e�I˯���Ϧ��HN(�4�3RE�����C#K���=��m*DVbG��f�;W���_uv毮`�
�}UH$�rOlƂ�Ias���@!]kV����]�v���B�~��n�~Ή,��0��1���� �N����P�K$�L�Y$�j�>��0ܫ�|��z��tHx����=���w9�/�{��jK ��	Ɛ��7����݊�@B�
��K'CFB(���J���^����yi�6�H8�zU��Қ��` $��=�R����r�r�>�+ |�ٙ���^����E�~�n�`��7���sR����H���-��f��Pς�������14Ƕ7$�uoo�m�0��jk�sg�=�<���!�eQ0��c��ć��A�p
�a�6�z��t!�/+XC7p��]�W��u�p�d�!�Gh���Y_���vE��KonZbƀ���
��6AR���&�rQ4�n_���\Np�
-����j��1A M�9E$X��+�h��ڡ���׸�c3�����uT76&/��g%�PI8l'��B�k��^%����{��&g�Sؖ�C��ɱU�_c\�&-�������<��e]4!� ��R	T�ǘ���!��ѨI��05)%)����c���6����.�B�&����w��:���PD�+��$6`w��Pb�Z�z�`j��5��
��ڼ	�u��I�ac)� 6�>�x�ww�� ���:a
�����]<�CH������9��E�r�÷~%uV�I��FR��	��oz�
�GIm�|��D�r;���Y�(��t�����A�J���k-������c%I`��5mԊH�; W��� �s;�Q9�<��/M�1���?8�$ez-���`�+ ���y�����B@�la���0�UWY�a��k\e8ˀ�SO�^�X��Vc����[O�F�4�I3���5�I�8T�+�n�U��HR\�y��ʲ���� Ӧ���,��I^���ٯ����)�@��>�!H�M���V������ -����}˅��1�6�H#��K����^_L��� .)��&Lj|��2[F=��E��z=�ԋ�k�"^
��>��U�-�*Z}i�,}=�L����1�9���<lia,�˅��3��p���'��\�6#��]<�+�����J6���O,���D#a	����¥�ѵ�w\�=ɷxzI��笥��N����{�f�K��
W���ܚ�U���&IZƾ���F>���x�����0T�St���ϲ����1c��u!�u��8��^����態[v£� %��m¿X�d���)�<��-�J/Z���) p�?NV�GC�ʶQMd�o����=l�iU$��^\���V0��-�@�3⍀�n�^�(�)ܜ,��3�-q��7���L��蓩�C�ɣu��kT,kk'݃d���J`W������:;�|^ٶ�y)�^[�p]i�<�^cxrY���@
�^R��>7�a9b)\z�p[��ɗy�3'�j��TB���&�_Fާ��Ƒ�����1^G���:� J�*�׷Ut�FM�D`\8���� A�e�M��Y#o��Fi%��sEd��������s:��bl�|	����5���d��j�˄�F�a�_}�QH_�S��.zF�  ��%�
�}K��S!��|P�⣟T)��7j;������>l��=4�H�{���<u��|Lyǡ��>/�����~�05k��5\�5M�Ȁw��Neg�����"�3Y|��dFX#t��ʚ����P=�g(�D��gE<]�
�6Tv�M�GI&fp���8Ҩv�8�� c�Xm��G�Z/��Ef�i�����SU�j�C���pu��i4���>�����JY�~V�l=}����>��nb��a��U�3[�m��p���`���\�����U��Hx��"l	5G���)0�qq�l���<Pu���sl��Z�CT�IJ!�8SMN�WHhbP�rԷ�y��R�E��gU��{���g� ������VH�q�՞�в��ޏ	�
���y���zM�V��ɕǹ,a����-T����_�.�6̑��%0�����9��/~0%�cc:`B32�2|�fl�`��n,ٟ�\	��n�K_����B�ҧ0!�o����|/j�toL<,E��@_ȏ�� >35�s�`=Q�8;�1�e��6��)
��B�h̢dTq�ד�� Чԗ�T7#\ك��B�#�������X��Q�L�
ÿ?�����P�h�$�|�I��ë���+��[0 (�SП�?��Z��|N2���"�?�Y0#��i;��<Cm�ݱ2��:��Be$��|O�v^���6̥�
���͵c�I�!��3���J�U�4����+4�.=�$F�H�H<#9������c���|?������7��h���q�����.O���%�k�a�S���
���ﳹ�p.���H���vP�!�VLaH��V7�ý�DY�U�[%�o��i�c�JAڋ�������im�<<����#Φ@�1��B��sY��z�_I�H�h]�K�G�� ���M&}�(�1W�y���	&���GO�ϕ��gU�U��-.D�)p!���LWj`�g�J)����;ui|c��-!�-֓��54��w�������8����8�kw�ϗ�M8 ���VQ,����K�r����Mދ�v���֏o�E�'|q��������y��$v����:)�37���ˀ� t�,�zе��퀷�^!'_K�y?�8���H#�`�D��|$�?���׷d��i�e�N&	�F�d�����9Oa�H���m�k�9OEV�T4h!�O���@ � �??�y��2s��Y�c/%�*UK��/F�)HW����XW��6"9�����!�|b�����.A��IX��'Z�d���\�"
�T�%�IY�19oh`���22ް�n^�M�j^Aw"�����b=l;�P���]�d� �?�y[�O;�d����D$ �P	Q��{c�D��I�wfʑ~�9�R�?��������5-��6)C��+o�o6;��0���x��d�	��1c,A�og���Y�ڤ�h���d+�*(��D�H:Ĕ�W(]^bտ
�r� ���W�Q�+���J��أ�J�3�]����zwW-;n���Eu]Y� ؓ7�R�m��^�1�alj=t;9�}���ŏ:Y��#��?K^���!M�c DY�'HL̬�AC{B�)�3��W6n�/���62��P��������@N0G�Z�4%� ΐ��X�������C��������x���3�5:&�MA=�����+zB��gW�5��@%�R�S���W"�L���P���w�LGgx�ҳ�-�}�4ݼ�G�5�K7�v7����Q�9�.=o���w��'q|�Ƨ�
xG�&��|ŋ)�Np���Iy���o�������F )��˷�mKo)vN@H�	k`H+�0ii��mhL���s5���u�H�_�t�_�LaST���Md�Q��("!6�:��� |��2��I�TkΜJ��K��LYV�X��+	PT���t&&,����T�U��d��j0f���3��ew�����R������� ������q��3
�H8w_���y����+6mq O�+��r&R9P�n�3���Y�q1B��2ɝ�-D'�W{����$��K7}�h<Ɨ2�����	އA�2p�'�ԁ�|��
�ޜ,���x��:V������]H9v��G���Z�Fv�������;5B𾡴LkD�ȶ��]��ȶ��+'ZE�V,e�a�9<�ԯ`S3�����9]F5�wD*��j��C���-��~���9W�T���D���Ú�dcѴ-�(L�B���9���f�1QoF���Q��,X�cF=��i�6h5�Ob��ŶH(�|�Ȁ��1^-ؒu���V��'æc�p�ܓՍ֘�6�s�j�g'��NU��/��[�]r# l���Vr�<Q�jǏ=��&7�Y�9�ޮ��ך����Л1ֲ��M�O���b8�x!R%adC��E\Gܞ�+8�[�����]��S���c��qhY+p�@�.S�z����/+�\�6�«P��;L�u~�h��]��$
��7w ���-�	ZR-)y=yB��v^�dd>x`���&K48z���d��8!I'��l뛢A\%8�
/I��J)�¿ě������9J?wd�E@�l�b�faǣ^�ڸu�s�����o�p���M�R�CG�������\��@��6�¨]����� �I�?]T¯�-ơ���8�b�`_���O;�����U�>s=�ϒ�Ȁ`˔'ې�%5��%������`^�s�B�˲gG�|��1��-ř�� �T2m���/�V�����`�O'6;���W�חjZ6r �����%ң��|���3�Hl�n���!=��6��,6��*Lӛ���`�I��.f;ί�݊�$�>����Կ F�g��O�� ��ܳ\ծM�w�@�8 �)2�1��yB��9=U)�9/�P̈́���A�_K(Q�,[�>h\�1�l:����z���HL�N��-�27�<ٍ���e��I��W�a</�����3�3�v�҆oI����iBX����c�l�&�j6~��*�2��xJ&k�浛�K���WW�l
�"���d�v΢2�Hs�SI��_J�2� "ZC�=z@lu�H�.���J6��3����a�{,z� 
�N1/�qu]��1�Z�ؖʔ́�@2֙"`(���A6̃��(�@���$+��B=t}�M��V�������r;+LM�:|D�_�c(yFrv���C̮'�X��y�����j$Z�����%Sp��*��v��������3��5�]��xTl�_�7##L]n���wgoDr���
�{}o�Х�D?ࢋl�v��t��l�o��=����7|5fbD2Yj@���s�&���|��4?�U��3�I��� ��OI`�nY�cBP���6/E��Wq�����&T��S�x5���q
؄��0/�"7|�����N�`����}��:�A���"���v�L?/Љ{���-K ��.<Ru؍�V�	#\�%Y�.�C��{&�{�B��W��?p���8-p�uJK<m�F�*V~���;=d@��4���&y�^���	�ٳߩf��=�ɾ綉����9N4_��)��vf0?{m�V��������p�]t�L�O��L�{vC�I�55 �t�ڤ�i�3ԭc�,��zG�U�^
L��nϝ���1�5؟�e�yCǼlD��biq��ɵco�~p��.M#}_)Dvv�]����E�YԎ���M+o���+��"}���c-=1_�ȉ�p8ܧHk���x��m(��t�0���j�����5�J\�����u�ˡ[��`��P^C7�,s�2s� :��W_�������jh2�*`ő}�m��͘)�O[���h
cϙ����v)W ��c��W�v��B�ѷN˦N��e8�)��\y�������#�/��s�8h��z�`���A��?kK'��OB��f�Xa�Xٹ���ʴ �ŢX|����TP�mk�����=6a�I��ς'���&�0�OiwM�.5"J�'e�9�i.l���^���Z�z�+4��y������N�r��4K�8׾�K���,�!�E5Rf�o6�	=��
��d�ز|�B�p�������_�j�9�"x���f����d�bY��_g������'⊩�pj�Òҷ�Z�R��'/��. I#w�Qy��("}$�#+orf����v��chG��p�z�7]�X��xL��pɚ*�p� �g+�3#i���;��=
 ����0N"�?��t�Gp/���T�B���M��	&O�݅���	�hRh��'�{vAR�D���i�WU&TS�ꪐ�)�@�}wBT��-b�`I?~+���hV�ي
���͝��eQ�D'x�f�J��E�D�(3������w��G������jF�r>z�b�l��(��^�����T7��Z�!���U���e��V(3�TkP��Q2 �*���UB���z�9H���U���8�ґ�HA���N���x~��}jp�+���T�Y�Ic��&��gK��R����L����r���8I��ڇ����3�Ȉ��aZZwB�����e�u����7܆/R�*�m��po�^�-T|�3�$����P"C�{JO�!|�hVN�Ќ�7w�U��P���O)& �Q�� ��U�V����u��g��=MeW�x�����_�|�ܖ��]�./Z�iE���� �~����l��.2�I��D2?4Z927h%��<��(�K���2�O,�x�����/X7����7���`�dB]�ǌ�G�u���Q�Dڂ}��6�@N����DL�_������ �lc��ё�Q���;Yȇ�>�AWs7�T���
3-_��pKRkvbA�#DF�6���E�Ml0�ף����
�E	���`����K iB�'hH�zۧfZ8��e��׿@��/����;�WF���,P�bҜ�[5n��۸v�dZ�ɍ���8�
�>q�L}�Ň��Y�h�@�B�z���T�8a����)׈u�Pi=8~'R��Q�J^����˱�yH��$�w�I��ԜH�jK�km�_)�AL�>uJ�Ӊ�����=���lP$T�B�ZW�OЃ�*M��ӈC0h�ŉ������؞k�S�դ��t�?ja�x�V�t��"iu��!8\��]5�qN.=r}$S{{
ʅ��F��?���"���X!����S�o��P�}�p�ϲ�,�.m����2Ȥ���b�N�5ȧ@My���b ��uc���������Z�j�;�܍��у����˒N*�>�)
~�?m!���/}���A��bI�Y�n��	݃&l�{ �
/Q�����Aή�ˊ�Ge@�{|K<��Qil"���ȩ������Z��*E;��6f�,ڨ��j���h���Ր=�L
8�
�f�Ą @{����j«"W����ŭQ�c��
N�� ��x���J�˶g��%��!�hOu��
8���ȡ�&��hqE��rV�;k���߂kz�޲F0�!��'6�(6k�i�x�@4D��Vp���b�5��ʯ�?�]�<�� >$Q����G�����
[?͔���J���'\,��׵-+:h�ciA��a+J?����Ƨ
��pU���I�-̧�+�\����[���E�q�L���
(u�M��l>:6R}���Ft�̄F;���F�JM�={gZ`�����2u�\e����Ey0�:I�rˇ��On_��8ߌc�s�<�>��l��g�?�O�
�)ji.��p�)��Nx��?�WK�
��~z�
W����Z��������RQKu��?;�k�%r7��j�t����X/1�E�=�Z�`YؘS��śa�Pɞ�(��ɜ��5O�O3 ����~����*.u[O io��	$�z?h%&��;|�s�,�ҝ����>T�X���#�(m\�ye�W�5�h�6�W#;�Q������ l���m�{v�/p����"��������I�-�Z���_�Dh0�)4���f"�Ej�tÂ�Rh��3*�z�)�V�"�-���13^D�k�//�oL� �*���A
v0�\;쾢��R��$��͜�36Iz�%�4.�|&�v�:&3ZI��\�8��6�K��d�*x�t�+�Z�IB���������9��N����X/����}��	Lw?D�����Kũ�n6^�m�R������Yی%a��6�^��B�>�={\������ ���.�ֹ#�珅ayH�sHk��{V��[Vp�Ig���ǂ$���7��UMͺ�����[mr��P��+�/)��$�VZD}Ԓϻl��,�撴�oWY�@�]|�j�Sp�hA���>LͪHt��z�(43���2`��6Y~~i�l�� �5B�$���U�u/�s�%q�`���x��p����7�>�K9�:���
��GU��]�uw�F��DT�:&�KQw��wc�������*J3ha��$}�<�X��ݧ�W8B]���׆̟go�v���K9+��E01=��.�p��ќ/'Um��2�5�$Y�ƀ��mA��G�M;�-�a��:�q�o�3Uj�/�{��B}�6\2O�u���2�m��}�JUX�#L�,��l����Z^ �nw5C�sl��?́&���&7
�x�W�S�(±*�]�lW��-�z\�a �a�I��ץ��S�	�\,�����$����jF�V�>�u�@R)��<>�z��߆ 8R��ZaB(��嬱�  �q�c��jC�;����ٔ�uN1.����u.�ʢ%�X�i�z�,RZ=��P�h��E�4Ր���yja������p�o,b Yˎ0�*:d�MWE��4�Tg4���Q�R����A$�P�<�A^��׮�bC��#w9��u�O[U%S!�VΪgK>� ��X���߫�v�D����8���gp� F{ej��#޻ώ �R��a�o��xȁ9��_�$��Z�%clR"�$�5>�ߒ��՛:.z��g����A�dVa��_H�
k�>x%�8�8;S���Ye���D�;�DK��������[��H�bp�=������6���C��
\K�Et(I� �J�V{+�D���V
�荱%1i�l󗭒�$�L;��?t�ZV�$��}�����,3^C�-�-��(/8L������i��-��Ͽa2D���1CEu#C��r��hD'�*��+<�Ɲ���w�T'�GG�M�=ɳ3#�M^aa�8�h���%������{i�/,�f�)��c���&�ѽ����FZ���y|��}]��"�2�&���#kBJ�fm��Ɂ���>(�o�tg9+��o���hA)�:�6�A.F�)����g. ���-s��S�Rq3��0�\�%LE�.*v����l�5�d��oq����5��[�>B�Q
��C���	:��-9ݚe����5� o�c���w`�/�L�����w�I�yaw�p��+_gO7Bs�ӂV֕J�w�/D�(������ $!�3���w�	�p��vh_>� l�)\wM��\�U�9&��(PK��NO��"O�EU� ����D;
JCP$������:�IfjO{�߃����~��-_��?����D)��R��^Mڋ�\���ւ���M�g[��}�6�(@��`l�K���>�LY#/}U�&�3B|��
��Wȥp��M�]+|��rKpB/�G���#y�]% �_{q}��
�U��{(�z|�\�rQ���r�-dV]�~�ɢH�NeaO���zV����Eђ���6�O"�N�%�}֮Q�E�������:>���׮ȍ�D�D8��P>]
"�+��Q�^v�nS�"b��Q�W1^�b;�NSd9T���n<5e��`�&�PZ�ۄ�]?���CM�]�}ln��a�m��㦁&��%�Q�ޱy��Xᇶg:[ni��s\�����m�j ��#2e�S
���<�1�ץ��J��<2�
������<e�i��y�ݩW4� zQ������#��߶���Z��>(J�� �}��XS�n̩t� �0���4�JA,�6b�)����Q�����7���
���m����Fp_����M�	2\ ;F��.]z�M�>��Y�F���5����_M ���[Ѽ0�t�q�NnL��̮��c ����%���Z�
]�b`;B���0I�z�� �S¤3��Y�T���/�i��YT����N���[�-:� ��0nn�/>�<����Dn`��V\�V1	L>p�4g���x�{�dW
���3�"n�_ ��J^%�u ]��e,e1>l 9zƐ�6BQFJI��a��o����c9�|lб�����^�0�a����ы"xLe���A��{��2������a�"���D�jEl
d;���O�x���W�O^��F*B�
J�iEM�(�];7�R	��C�n=�q�� @A|��I���uW���)�_�B�k` ���Hh�)�����ߧ��D�
c���<�蔀�R$��}�b5*������g̙�JI�%��T'^'�.?�@�£��Н?H]Y94��Xg�jkzJ�s�������n�"�?�҇�!+�U�-�KH>���K��I3�
� �P���������E��2�yy7
�=����3�0[��5��u���ϒ?�=�x�����n�DU[��l�mP$��"@�jX�~ c�l�������Xy.�E��|w��(C����4�(%e��O��^�u�%��fs�d��%t`������0C	2�r3q�9״QN:��0խc���jͤ9�}�H.��8�Z�Ph�O";�t!,��z(���(���yǅ���̉)N$����}�t'*{g�m��2�4��������:7=Y�VEr��Naθ^���a��7�j�fGFF���
���ه��tULD>��i��T��xP�"a��u0D��E��TI!6����GZ�3��i`*��9{�� A�]#�'� iu���H�#��Hv��'�ܯB뾖c���4k�̄����ܣ&
D��t[��� {�bS���\k�U7X���J�W�bP$K�6�������\��'_�v:�R�P�/,m�@�O�^�;Z���*h{��U�B�Y�0s�ESф��a��}����x첌�oNk;����;
���֓Wt��pR�ZX0D���BWZ�2~�[e��0;�Á��mߋ����c��W�� s�^�\�}�t�W<TM���C	���<8��������󻆂a�jC��fB��Xg��l�v��K��Lr���(x-�
��4~���uН��8������֛�Z_ x�B�'䋾�N]^�L��s�Atg�H�%��"��~��?���DK�@�����֪���R!��T��%��2-�;�D�����;�י���p}�� �������(x��F��w�svA���ۄ;����է�\�>:�5M�z�]l �g�Nn���J�(-��Si$�r�K��G�ဿ���R���rx�7��N��yL#+���)���Z���]}`a�f�ߢ��+x������%� �︠�킈����	+�� �l�RC�?2*7��8rI���G��W�K�1��}O9HG��
&���b}��Xݵ�Q��
_g��>�%_�V�B��hLm�����v���p��gZ����㙂�e��`R���3*6����KA��b�� ѸFn2�/u}q.p�G�� ��G	�bN]�Bi�8�vQ�dl��V��o����P� ��<�P�ҥ��	l����V�q*�<���c��$�!�?�]��#�#Ÿ��W)��;gl�g��{�]��=;n
7�Y�1�Jv��&�gN#�LR��~+�d'�+(;!ɍNl\�KN$G���|tD
�>4{�RBG �Q�@�Iè��������{�w��w�LH�ߨqN�:�2;aK��zlk���������Pa$��W�{�P EC$���>uB�Xc�`����d�mu0�F�)D]�I[���I7H�N�V����h��J���~�����F�8C������t�7 O�R�F�;�Sx�?��
<�}����~<�:Yjє��)�n����G��Q�4�u��#�&@TB����z�_��G��'(�j2��"�;IY8I�8gn�y��4�4}ĺ4�rkT˪eƟ@�P� �������Ħ�Uo%�s?Tbv�PLX7��?�u���C���֚fš&��|��1��ǵU�Ǌ1w��&~(����?��(�����ݬ��{�>ƍ�E��ʗÃ��=K}}��Ŷ�Ǽ���_Y�q��
��+��4v�7��۱d5Y�a�7M^+�%��Ot�_�,*&���E!@V��FM>n�],�	HDԂ�b�$ۗ�+J �~1�HP�,��/�����7��D�p)tjS�lV���L��t=~��F�2���~�Oo.��a�$d|��fmdm��Q���$����j̋�DT��p�e��45��s��=�Y	�c�|6�����6�~�6��ŭg۠�Dw1���pO�G}-����ȧ��O�����-1����#1e�䭆�g�|
�q��A�k	4;
�*ｏ (�o��<��?�%a~�4��Y%�� ��HQ��6�
l �EX��8�G��0'���O�;ͧ�)�7?����3*�er���{���z9��)�|j�a��6Ն��/�Y�3�����DB�W�I�X�6j%�|iwK��{��I��/2+���Z�'�����8G���XV 9Pw�s��٥hpSFb�0AgOI`[;�x_]W>)�qH��
2i������Q�DJ�I��U��nʬ��B�@^�˴��xn��)��5J�hU�1�q�q-]cf��V�/0eofk�����>Y��RZ4>qve��Wl�A���sR �ʋq�)6�&�e�f]�eaƊaǳ�m}��w��2�^�&��Kw��FO6B�٬5�v7�5rjNx;�`<=.��U��6h5�Sa�V3�ɪ$�]p������H �:D�L �	2�6�aɼKx��Eю&�:L
f�}��Qu���`���o��nn�/��B�1Ŀ����7"ȉ?/!�<)6D����@�?��7���z*�9�AtŴm��#J�u�	U�Xd@ĉ2ՃC��ђ�!*f�3�u/�Pw�Rx����T����ys7�K(��y���!7�
Rv�)w
]��c�f{#F����P����b��:�� �Z0�XY��]ߖ�s�K�T�Ĵ�X��C6p���R~mʣ��d�a�mdŊ��`���Ԑ��8w�{f��"�wp�
U�J-bm���9��.G����8�ų�rD�������]��d=����'�7�wey���#��L�"�(���\O�8S#�%��n%�yH��GY���Tю!��(vwTD���X�L���
���J�}d���(�ﰾ�g�s�B�}�-CwI�2+��XܩzwT��8١^_nm���92;�/�ܫ�Q L F=eָ9i㜟��*�8�L������=*�VU��١���'������"�I�s>�$��K���f��sb3%�$�1�PK3  c L��J    �,  �   d10 - Copy (7).zip�  AE	 �T���b�\9W��Tsk�r���m�j���9C�__)��~�~P�+�������+�sju)�8M�7� X?�!��go�B�ʋ|+����=����nl��T˫U>&u�6��6_�Jf��U%�M�i��b�O�(�6��r��!9��
@�pqF�l���� ��,w�jh�#c)+��_H�'Ę�z���S�	���.��0��N4���ya�X pת�1Wzi��AJ��^o�@e�7F�0\"�[c��@:e��o)�2��H<� ts}3�T{(&�f���-6zr����N2zO�3X����N�������&
��K�+XJ;�9L_�:
HSv�צ�� ���w���K��?�;a�v�O������lDnz��aC� h���,h賚ˋ
]F�E�&�x9��Qv;�R΋����OV��scA3$*�G3��\f�.���jC5=�c ٧FLf�E�V�~���6�̨��H,!�ܧN�(��>�,��7��l�	d����[#�r�B��{��ka齱c��^�d �Usz���6�OTWs~��Z����u�h+̶��NJ��o#_�煊a�{��h����[�Ӝ�6�������ѱnn��O��8X;�s�:܋8 ?$m��y���n��_����VB��>�
/�G $uw�	f�@l|�_Ϣ��'Х+?��
�y�]!�ʦ���(T*��Nh01�!�~���j��&P���/S��^X��>�e,��q��;��4N���F����l��c�.DL�"}DY@]�^(JrE`C����}f5���d�O��g�����1�|C�1: �;�g��<����1R>�M>�/gk�3rOs+���:�ҫ�3c��mPf�{�⧛�ˎ�{�D���_�v'(�ĩ
�����������8�QKg2B��(�<Q�+��&�^���c�n��.���o��hAJ�P{tZs���b�!튺�I�+cۤ�EOF�Q��r���'��W��7���yH##��n���t��0��D����55Kx���qj�:��H���5$R�I��@�W�OY=v�u:ࡰw��!��8(ܻ�r	<���b|�c΋?6�,1���/KΨ�0��o��Iχ��ڏD!3����J�p4-E����#���dj,)�	.�&9`]P�&9&��3w ��K�X<E����N�P�7��,�q�䔵�ZQΘYs�]��������~��ҹY��?�u�0	�my.vޣj��Y�Sq|
E��*uASV��4J#�j�wh����|���[枚��Վ�GP�w�m_36�L��C��.%E��B�y��,8��>����P4��'�]�~Kb}�T$4a��_H�D�#Ӣ��P�X�,UG6��6�������ڊb�!�21`	x���
��-s�~E��hG�#GJ|`���O ����;�dZ�� B]7ofI�X��o�}�T}1
�w���b��0�Z�HY�v���,��q��#��~c���:dr՞�d����z����,C|�GVL�f�9 $��A����C�i���k�7���Ӻ.����I�L�ߕ�hҸ9\��Q��*_d%�Cp��+}41����������H���HD�'��U�)@W*7�S)�٬Y6oKT��-e��Hl��)�S:�d�Mt�.�0��v����
W
�=;�5�W�DF�xӎ�s��%%����Q�\L�z �s�
3n�m]=~`�M*��e�˅�L~S@�ܷk7@I���7�3{�$��4�紩�{���́�y�Nm�"޵/�;�~�}�%2Ǎ�0r��u�za��~3qMr"�!��%[�@�,�=3�Ǽ��s�Z�e5kr��pl�l�3ex�9ϱ*美[}��A��m�h��Arw�:�1�^&�D�Y�܏S̯o
R�{,�^�����儶���4R��q�G�'J�>��K��� ��tdD�YϨثP�N�|U>*'1���u�RR#��}4�����	D���O 1���F�,J���]7��ԝ�?�W�Н'�4ׯ��|�ڟ��{䲆���)E����kV{�B!@^��P�*�o�{;��Ư����UX��e�c�S���UbJ`����{���T
�u�+������'Ub�Eg��rߢ:���W�u�֌]�Po��v����a|yp�%��6.Y��RP�a�A���R��0RIv.���e۔�n��eb�Ϯ�ԯ|E�L�X����چ����b����q��M.��.��5^��in���G&If,������b �r^a���)4P���ʬ�����/2�w�R���r��Ʌ��������
l%>5d[���[�&靛�B�G=ŷ��P���mm�.߇�t���}Rk�A
)�aM4�ɟUq <y+��75��oI�;ZĵMbQ6���D��[^�8�8S3��1���zy��(&����|�d�C_ǝx ��O�*��}�����4���f?u2G��@�ڴwҐ�e��{��q���7-N�^^���k��W��W��9j������o&���W4�<tԒN5m�|�HJ��J�*���"r���;:�-�|�ݽ��M��X�^l6 ���K���-_�����!`�� �A��O�"`�7���}bV���B_�
ڦgރ��K����(v L��W
�\�:�m��>���3�,�u�)'k@�nR��!��Ʊ';�.Kk%��9tA��s��"�8�#���Z�-� �&J���i�F;-dE����AH���@vx�i�[8�e�oT���Lx��ŢQ�X+�����g@��z������B���4�g�s_v��z�XF��T�A�G��8.?���%r�`�z*J��z����mPSxe��@F��v訞�ak��T{MN�!�:��C�*62�)��M���J���ڭ�ǺgQ�&_|��	"Bg��
q�Y�~9fH����4�¯�E��W�q�Hrɢ�Hh:�q��("2g08v&�߸&��9�|f�렭�W���Bf[
$�?k˛8`����ԢZe���X�rN��Q�� �Z^^�.IJް��h_{�4(�˘9�� F
��(R�����(�ފf�)���BX��@�����>�a`e�^W�i��p���2�vw
e���K���cc�~j�l���Jዢ��ծ����3�$�w�7O�?gN�z<�����K��cY����Oa�l6̜��Un�Zf~��⧣@���(�U,�8�TeO�Q7[
��|׼�v�BJ6`Y�ܚWk1!QK!η

n<���X��1&����<��<
F�oێN�Y�C�&̗��'��*Y�k\V]i�T4d_Y`R3�)&����$�50J����C��)vipz��K�m5& �����^�-�ՙ[�;��ٹ��8jYLr�n�[Y.jlC�4nb	H��cK��=��/u\P��`�����49>
��CY��(cGN�-$��^`�X,*�?MGKb}����{�[e<e�/އoz��R�#�|>�)��Xޯ��TZ�x�� U��Y��+���cc�\:��������c٭��^������9�i� Te�
�F��0M�b�y�:e�ki=Y�!�=V�K:��u�"F�ʠ�Jt��d�I�x�B�ןh�f��[��������A
���(~��5�1�d�(��T�pZ9T�%ň�ۘ�UX�N�O�t6�Q/��V����֊��僅���L�2=׹�%���)e�-upӿ�f�������`����G�F(�m=#V���!�w�: M5�H���D��͵�#��)�S���Y���Uy������,����rCo�K3��������m˺���F~�R�
��`k���j�I�?h���=��p����g�����pRHLc7O�Df���Z-���3^Ig��\��˿qw�~���Dm�)��Y�h����ލ
9�d�Tcy��e�6�s��즵�:�3Ûh������ht$z���O���MP���Jo���[���F9�n�p�GxüR$��a�k_˃��rনx��wL�D����pZ	��J�>nȳ}2�KF:�.�Œ�
dz���"LjMϯH����q=�Є��OUf�)�PC0i�j�����E�e��{_�^sj��!����
�Íö�j�{�v��pF#^L���a�00�%�v��lvF�5#�-b�-�54�K����Lc��n΅����08�v�@��P�b�Ӂ���!��p!"	~�&�2{�x��o�,�үW[�%�55y��8�����B�O��O�Rr+��qTX����hg��-���@l7�.�Z�p�:�<	�
����o~�O�\��\7r���/Z��VI���f����҅��/��]� ��@�%y	Od�B(	75�Ws5�ea&X��ƨ�
�G��i������S˘���x�djUE���`�����v<0*�"~T�./f���C��$�qՇJ�rm
]�:�B�������U� ����a�Rf�a:�t8ͩ�m�U��w�uI��PK3  c L��J    �,  �   d10 - Copy (8).zip�  AE	 �k��SE98��p^�ps�~��L*�/�����$7������X0�������b�?W7-ꉙ�(���b+�P��g<��ɦ�@��)|��1��Q�

�b B�p���6��'��T z��5�IW]���8e��В����`N?pf�K��<�l�ϴ,jN��� �=(���<�i)Lō�_�!X��=u�����f�x�;*�4�	dX�&������C����v������q{�Vt�� �V
�qEI�>q'���1K� ��/����yU�!Y���[��ju����L7s_��vs �����4���ʁ%W^0s���gWI ��Vr�u&Eڝ�2D����c�7��Fj�(]�
K"Gx�L�4�
���X8o�,]7Bi�y֗P�b�JL��J�r������w�2��w�:��VKFL���3��C5��ޫk� �;�Av��rO���4h�ڷ9�(��&	��6����o)�v2~9�'�v �5���������M��9ʕ
Bf�EڼNQ���V��<���!	:��vq�*|`%Y� Kۺ,}x��\v#1�T��Ϡ�.5��zd�/:�s�G}7,���Ux�|\ʮ�t�Gۂm��
׵�)<���c�P�h.�c��E*E�����Pe�@���_��u�2���|,'*��K�:�
���6��}2���|�Ø3�����\��q�9��8��arЗ����ѳ	
�i�P!a�vPҀ�k�;-�M�_{O[fk�p���+Y�SE�"�VC��xe3�%L�鴓�3ݱN�z2�����i��������JXVEz��r�d��	��7D�FwW�Z֭ݶ[��: �8�Ԑ��ܭ,�E��ɖ�a�/��$X>&��땲|�
$�InJb6� Z���~�3�X`�.�ebs��y�����OD/�{q���0����'˓��
���y೔Qp�0)Tځ�l+7��f�M�t9n+J>s�V (rK�B���1�\��vr�� (��k.�r��@�n��QH��9�5�}Gd�Ѥ��)B��T�N�K(��q��^��\�w!�R��2�����%f���/��m �T�do��`|�{O1J� ӱ@S@�o�Юi�
)
(9��+��E��6�jH�T�l�i����lĿ��_d�	�>�ɢ>T������B'-tSylIL�����wT_��Sa9�~[v��p��`!�F��c�2�~+�\���R���ǳ��F�����RN�	�g�R_ʉj�VXm9��,��Jn�{���&�� �6*�d�قAн�^ҦF�ڙ�4��T�����qI��w��������������2v@�v���t�d�>�p�ب�B�NpZ�\�b�P�^�T�*�3���SX�\����&6�] �9N�(���]��7��q-�yQ�a�+��'�=V��b;aC�W�,�ǩ�/�����}.�������z��G��D���΄b�#?U-E`5�%dG���#�8�,Qk'�[Ӎ{�(t�����:��Eq��f��gbbo�<�/B��s#8M��o��]�/ ��{�~t�wW����*\x7W�8��ԛP
*!���G��������w$~�������n�l�?Tim��i�{Aoo?dB%h�l��s��V��,����=Ϥ���_�j�	_Mua�A��_�nَ�
!
�	��J�G�.�%8��"ІҔ^�ѝ�$�Nd�j�O�AL�9ƻ=�$������Ҕ5����!��]Y��g��l���S(���7/��OƝ�-�m��[�H��ߩɕ�u����oO�[���<� �l�6F��Ȕض�(X\<�1�~������ؤ���q]g����֤�\&
�r�Qճz��H	oز�07+�o� tu�ޤ���3�r�r\�sA����E5V�w����6�����$@�f��m/D ��[� *�Ccn�!�K|/B|�U>��h�x{�tGC��,

�p�0�D�|.�l	6^����jM��`sNxKMn#A�N�<�xB�R�4Vqw2W�7]���[��ws-�[�`��@j�x�I�q�����
�w�3��vK}���ȑi0:>Otήu7� *{������?��B��#�=Xm3\;*I�Ú��>f e K�7�t�3[2T���
	 �B �k��v��{Q���Z^��"�[�	��#��<2˱�FQ��ƖK��n�=���u��xtI�XZ�r�z�v�h��ka/��*_�lôU������iai�K޴�IxY��+�p�����Մ����$ ����"&���)�S�o�&r=!ծ�R[b������~_`��|����aP���y�4��m�P��0WQ�
ݑ�{$S,)w�t�r`\㜣1�����Z���b2n|p�ט��Z`�t�Z��g�8�B��(�U� 0�^���]P��b\��\��u��+1m�­�P4�sa��.j �q��0�AqEp��Am-��2;
����1���y�5Y�����$�?��	���g���u!�VE{J����O�4����󗬡B*i		ՙb��Y�l�R��+�t��8�xi

L쒪*!��+�:/G��T#|���0Ԅg��I��U�  �d��6Ʊ��N���H�%a/l��R�|�"D]@��hg�Xvo]E��፷mvE�D7� ��)��%;W_> x���f��PQ�� M��F�%n���
n,E`�
I����E�c�(w��oB�EzC/��^�U����q�at� ��(��#f����tD����h>�z�6#� *Kq�CpE^�8�?��Bt�7��.�:ܤ�,Pl8��Ȅ7�%##W����kJpy?v��.������#�w0�郐 ��BB��t�K�"
=�����v9�
��G����&�.:��e=�?��DTǭI���]1���
"?�+r����T^&w�Wj5�|��ܙ�5|��2_nw��[<'ړ�������������
 �MvGOq���f\��6��?ێ�3}b�9�	LU�Ή�:�CG��b	[4�^ˍ��B_%����K�����-Kڿ]J�XOA
eP�vq8��C+@�����YR��$��w�-����Ӕ�^�	�N(�1��΃�����#5)�փ�~�$���>�馐D�Fn�yc)o�,
�5����'��f�{`���F�b��m�:��'�I$��ilW�2k#0DYBR�\��F�f���rS���.@ ����ک�M��Xm�j�tEZ� \��᜵�ʩ�%`���<VKD1N�B�C�6ܘsAL�*uق�w؈���0��I������X����A-�EA�TR�[��U��AM	�6��Q.(N����a�\�I�v��	����ח
�Е <K�*=���|����w�vO܀���v�O�?�_
i����ZG�rh����O��7���4we�/������)Հ��Y�ȕ�<��.j\mh�+�1`v�B�S)��@�A�R).�6���W歫P
t<Ն�}�D)�4v�q'���}.1�yy�!�C��v����]���r���L�N2%�#�)a��}Jߋ���,��S覅��M7F����^�<rm0��,�4.�<�X�Q@��/��.��^��W"���!�h���`��aVL���\ I��,to����MB�H*���Ab�{�ϥL�2�IM�VR�T}�|�k8X�uU�R���ęC��5�{�����5r㗸������,�\H��.��a.�X�Oae�W����<(d�(?�8�*���ׂ��[��.�ID]�[�?���]U{��ӿ��?�>�S������޹B��EJkE
�]L��=1
:]Ճ:�~�C�G�r��G~3�B�i���ֺ˳�����X�;��
n
�q[>�I���
���v��ĞkWOv�P��h���2hZ���|���KF�I�� *�Q�^���w$���Q@�1.�[�r��d���m&
���e�1j��S�/X>�,�7tJ?�����iG)b�d
> �D&
aU�b`
�U^�d��k�T2M;���4?���W:�z}h`��hɼ�%�
��9���D!W����_���)G���Ý&+�Tn@���<��	D~���@�D3')�z��p\�k��a/G趖X�[v���ڞ��`����� m�Èh���$�m/0D�+Q���Aظ(�Y#L��3-[�:��Uӣ 2��)���z˙K���� p!Oi82"�J|G�TP�W�ط���������Q�y2X�Nw�brs�3��Zeol���s����rW��^	Ae�.���>{��Z��F��!���2��g���������E��M=��!V�j���s�����\���)�
U��4������Ƃi:Ҕw'�
6Y�(+/5>)ac�:�/����#��S�O �A.m��#���T��ą�n�E��D"*�`<�iG��$�f����F�{�S
MZ;�h��[%��3-�C
rE � &{�G�^E=��ޛ���o�R t�2.}[�$��>�h�AW�b�r�j��>����io�G���e-P�}�N_�f�Y�!��թ���ۏ����kZ�l��D~��FF�3���'��I�d�CR�������I�E�=�a'�*NuN�ZH�I(9X��̱*|_�+
_�҄o��C�����_wm@
C�c.���͂�3��<�vL��-��J�ֳ��W��#�e#%���VH�{t��6�@��_bA��C�h�������O�.�_g���9���0(�(B����
�&�b�ź`�>����A���$�''�&T��"���-��R���2ql��;�5z�B��s$��J�)7�,�Ftxia�7��_�#8�Jҭ�}ZO'�Ua��d�����X�{�<_i6��j�J��ё
�aW9�∻��4Ż���pB�y���Kd˪4r6�G�C&�vd� �'��N�ǉp�]8"]��)�Fێ�o����-:�4�[D��K�\����X1��YncO�����?�jhH^�l�����v�gz�ђ7���ش	Wu��/R+iCu>���
g�%|6X�ֶ��<D��yƂ��t
+
ѯ�ˆ�;d�;[��\�kE��0c������"��-�^��GI��9j�:��nu:����@)& ����ݴY��E~���a������],�����t�&�Q�D��R���⚠�!tVw�����7��P{�+H�C�	��h��Cϋ+�RJA�xM*�� :��Y=�d%�i(�q��B���k��-6Bٰc{�I��<�y��~*|
�ż��H�b���z��o#E�u�EQD���P�Y�µk�#E��\p�IX�Vc�:�]�?�d��(LLǏ])�Q�,�����DGo�
ox���>�}D(��i"o�^m��>C�th��0�X���W
�}t��|�z��۶"	t�+�O k}��4�t�yvp1h�nA9����vd1�_�L�Ha�s` �s����PK3  c L��J    �,  �   d10 - Copy (9).zip�  AE	 ےK�IL�~�$@)�����1����}w_a��P�zIg��8���m
��6�M⎣��i�T�
��ru�$I�|������D�:xc�@��Y݌�����+*r�R-���qR7�.�j'ڂe}�y$���(����-��y/����,��#�<m凤J<6�KN��_�	v�V@+��S_����ER��]��/-�M���BC������S��> ���PD\]�~;�<���av��)��q���>��D��QDU&,
��f���� �����º3ݰ~2��/��'�d�[����TH��� �,=Г� H�r�,צv�Q�*
Ht�M&���8�/��C�s�'��&ã{�C�\i������hON�n:5q�o����7k\0�Ɋ�4u86=p6�
�.��]�����a�p�dX����pj.o֒���^8�V$o���s�i�2�!K���_B����C���[�B�.��i|�m!!��-خ�g�զ6�g�q
�y��V�\��J��Y�M?#ęʢ#�i&(٩
=T$��l��g��lC��t$��Ռ���((���E��%}���\q��J ����XR#]
+����}S�s���bS�h4�j 5 �m~1��>zn4��P�i)�60zp�B@@V-X��t`�>��G<3x
�6��e�g�:[t&n�����R��W�%���h*$�g��J0�*ޏ�X1��a(�y�}�x���3(q!�KF���%7�H�i:U�3��k�
Ȟ�����*��r&�\�W3��h�z��9e�\{M����@S���(�F�%�:=2��g�= v��3$��]�dFnG��ru=՗r�u���c��L�f����',Ǭ1t�e�m��/Nu,,�W�rq<��E��Y������o��gi��h^B-d�T"�6t�u;_fn7�B���ApS(� f��c3&Gu�q�j���o^U�`�ŕ�A����������7�8O�*�R��$ >��݋�c"җN�!�4�]�Dd�
��"�I	���b%s��/�	e����d$�!��1�&*{�����¨6\I�_w�Xp(�)�%} r�z��m�R�f�^f7z1�
?J�S�#�35�Y��^����:�;F,w�nQ����:&��`F��Z�v���k���\��mN�2���j	�����x�>tY����;���B���#2MB͓����8(�k	���T�w�x�A�NZ��3��G�	����x)�jH�;QMI򧥽0��jk�eWW�]L�~!�<N]���8/��}?Or�[$���q5�d}<͗�s�)�;bj<郣�W�!��o�L�ou&
�����ϣ��7����؉�bq�Y.�
U����T��ȑ�/5���+���ih*���H�잲�j/���+�%��S�زP��
%C�BY�Y�����Z�"Փ�q`�H�{�˘�'��Y\1�]��/�_w��WCS�v���q]���Lj��wA��"j�%���lW�#�X>���pfM�׋�a%�R��D� �a8A��Bz�m�B�[z�k>��N�x��TP��W*��2��hΑϦ�	Z�`�w�_��vi?��6�]�V�j��t9~t~�ٳ��N�L�Ո���?q�
�3<�E��W2���E����]�G9g�|�H��L�5�k�L6|GYm��(C���HӬՑ�f���ʈ�?BT��6Lo1��IR�V�k@C|��y�;���28�� Gޒ�%u?@�ik/���d�X��ם�+�NF�w~m��-���^n�Q:�Zi���#�^]%��l�:V1�OB���/f�|�#�5Ε%��^�����xɣ�q��3  ^�G�Ƃ�1�B}J��5%��]�q��1��/��w�
��n���I�|c�%|p_d"<9�*������0���S��Z���x��MP�9���@G��U�JyW�y��%�7I󄌔�֦N-�Y-��l��u�X�2�}��C���ڢ_�!%m��O� �7�
����Z:/(�=w��]��I+��%�$GI�w���.O��}�I�}��& ��ɒ9Q4ȒK$��A�df�u�ԁ����Zb��(.�:����C����O�m'���{�H)�G�,t��	��gHW�K�+�!����<t�g�%�gً�5<�ϧ����$���\�.SҀ}�j-�h�`2\��o�<�ڥ.<�bBv��D�e8�:�|�=&��?�
�c寕S�̊�X#���op�UC�|T��f�$w����6�lȘ�L/�����-H|a�о�������t�2)'�8��
�1|o�=�����q�q�������W�W�<����;�����?�[�G���G�k��-,zY&�?���3tD�08ogN�����-�s�������Z��I�Ad nc���6�p�$`W�.��I,�z���֦����zL�W��ɵi�j��o		�Ai���0H2KSP�6;/�5���xR��dn��K�۩��u�з�v /B��f�LJ��@.�{O\ћ 疠��Q�V����җH/2U�l�m����UE�5	# �5duz�*`-VC�r;�E,T;ތ#����=�"�W�I%9yTz�	��r������fk�o@Z�)r��A-t)5��e`â�1I����q�y��1�q9��4��^��h��0iKo]s��*����s�c�(��5���w%�֙�M��>u>"Jq >�8��� |��:�y5iAXfG�Q�
 �9����]|�[ ����xd�p�$��B�m���ַ�iI��Lb�m!�`�;�� �!Ey�Ѵ��T"�w<;���Q�9lm�5���#-�su���S��u�4m\U �
�\i,���5	��,�Ĳ(���0W�������:1���{��8/�%��;�����9EѦ��E\�L�z`@���+��0erP��g	�/"V�w���`q��U��eV�װ����+]��Q��7L���l�6�c�
BMqe�ڧ�U��?Z�w�����90�4B����
0���d����=�Ŀ>��Q4�@��a������=���3���? :Ic2ߌ��`��d:���_����@.��'C�θD�7�� �ކl�V@����EM�{�}�˵} ���+o ��(�D/�R���	��"nG��v�V:�-��U���-<�r����"�����/Dʜ1�-�?����_i�僚~�S~ ڄ����B�$kd�|J�
f>��1�nJ�df�#pJ�{�J����h|�kYdb�o�(���$=Ԝ�ǩ�mL�

_.��;�EPN�����:���:gH��D"��D ���@�h�~75�/kp0��3�t55���C�hQ��D�_EF�����[p�:;��(��T����Q�d/3��E�
A�1b��oQh�e�-7AI8v���<&>[\�N�LXƲ�2W��u�9Kl���'"p��4X|��ϲ��u�Y J]Y��`BHP��:;�,��t7�����_.�w�Cnl�D-�̪�d=�	�>�^��uO54\�Z8�D�O���z'��T���Mȹdg ek��eA�= ��e�B����t�x
5p}[)�G�@'Y�P+�)M�����OG
����
{r;!ɏ�W��!���˚� -��l*M�sSȱ���s���UM�k��Q
�=��ڻ����؃�PQ����/C��������Ym�h� [��z�x�mR}�F�3���M�f�x���
�����B1J��kH��I��T+x,�WH���=c�����49������Ç��}c닡�X�(y���6����}��ޚ-���ԓdxA���`VF6<���p���>,��޽��N� [R�-L��3�%z���S-S����r�	64�s*<�bJ�#O��7���< fv^x[��w��LU���#e�q���ϚٳY�$���H��涓RM�D�����;v�q
7�7�h��1N�oJ��0iT�A�h��6껭���4��z���#��r0�<�bey�dF�xV@A���q@a�.�W/����i�eԃ�xH��wc�P	�9W��!zSx�u����H���b����#�e��RG������T�(��2Ӑ�?m�5�]���rv5���亠t{�U�>����NT�V�F-����a/v`|���0� �n�.�`Rz��Eʷ2�nWi�Ӗmֱy��ϡϤ��5E��xD�]>8�h����+�v�!X�&�����S��6�~�ӓ�t�b|)��>��EYϱ�^��ǣ�
Y�^Z�r�??�)�N۔� ��y�:�^s���������
��$�_]@b�yR�xr��1G�Tv��~CY����5=�IC: 
ר�O�g������B�_3��V��[���rWy%?�~V�s��P^s|�F�b���?�S��b[*��bQI[Њp��Mq^����t�>�~�-�)~xn����=+�x�6�eeE-�ߞ�ȗ�xЀ{7����Hm�3�%���YI����k�̠���c�`C|U���À/0g��ĢI���յt��׃��^1̃��vr���,I����K"�j!5Kg���,%����@�
`h
Pa�$�'j�Rm�<�T�>�
�pX�K�7�Y%�����t������r�[w���@d|7O����osw��vsm�@��z�F�{�ڀ-���W�&�6��]G��r,Āik�,?�7��)�!4�m�� ��S�K�T�F��A�ˤ�	��6�RFL $�^�����HGȮحC�T����n��vC_+���@'�TA1�|yo��
/F�EB�.3{�!���C�^�}^hI�^�*�&0��߂"�e�A�}��L�Ă�o1���|���J�"0sk(��}B��W�I�������v�0Nģ+)��/�E���i���8���Fą`�^���z��]���"��bNW�E�	��+�S.���<0%Z��! �-,cʜB�����O�Gpt�o]w�$�*yJ&b���S�d���r��ɐ���x�
��b��h�v@���	�{�ȲG!u\X�H<����$Y�_���i%�����\�Y���,�\%�@������I�P�8�BA+	�"2��)%[ #�N#��)�_1��Hjr����j�����u��0��e YI)\�.`L��`i-P��|p����[Q���y���` Sq����60� �a����{ԿU�8�,�p�Y��g?�"~�PX.�K~~�z{�%�׽�'`��C
5â%��
N,��ͪu���*�����[��xz�p��\�z<S���O���w��
$_�\U�\C.A0���H
)]��*���i�G���bb�G�����@xRq2�R��/��R��CYVY�HW�����;M�4AբS�}�u��U��}!*\�pV���I{A��m c˩��m~�8��6�#^` ��=�+I�SG鬜��8O�U|V��]\�?�ͭ�e).ՠX�>�m�9{{��-Ze&'�Z� �]��K-����bq�__v^E�<�:����b9��Q�%B�tW�Ӹ���2��pԨ^�M`���4�X#q~� 0
m��Wm���Q�i���ē����x(���({��.Y[��1 ��b*_��g^i�#u��,�T'V̥���`N���?��:5���ŋ$y����~)���$)^� �ȼ�S.��J"���f�m�=U7GJ�Su�Hf����>�߬k;�}!� -p�u�$_UI�+l�8�l3f(A�
���	��Hl�����B�u5J
Znt�ΰD�L�q���	&�d{Q�!N�f&Ԕ'}�5	��?�4�p�	;���3s׫2h��3�q'`�}L]:�{{�9���	�`1��R"��Qy���$��N3��M�?�g5�Q
,'�/9 ��N?N�Q0��M��/�i�'.`O.q:��CvrD�����*���s��� &P�N��|��ޥ�YU;�j�b�œ�����g_80U�wz�����*��ܑA���;�k]�|xZ�o��Z�l�u����{����>J}�x���k�i�_�?9־	�?^�G�_��>� �
��5#�n���m{? ǲ��Ǩ�MLii�3�s���OکM+=}�.!ǟ =:�>gr��
\��ҩ�z��9��I1~+��h7�׎/P�t]qM>2��G{���.��P���[��6��*��:����jN�B�}-��주��<���Qo*�����ৄ��7A]��P$�9=�A���?����Ė��ԣ�R]�ȤL��bD���S��f��h1�҅����C�́�1\Gk�Gtn�2���X������[+;	Xs�q	j�섷�l�Mm�Q�j�ց���>�f;��Q�
�$��
�7����u'��Ms�9�;�<"]�`�No�^��tK�{f�ub���d���W��VB�X <r0�RpOb�T��(!F��� @O�o����l��]>�� �@�ODZ.�	a�z1�lq<I���R�u���z9�θJ��X�;��yR�L�cB<7�7yYVo̲g{18-6Z��kH�����W�2�/���f�I<[>�*��Q7�<L�;2�}�"W�̶m�re|�Q�Ԓ��]��o�)�!�)�K����Bu���ʾ�b�8;"�g���˹Pl�Y��jCܑ�����I5°�"��)��g�x�_�o*	RL$~���uف;��t,[����>R���߷aD[l�y/�D���pנ�G�YW�j���-
פ�BO�Ĩ#�����#�λ�B#1��̫�^q�]������?�'��b�	�G�&����@����p�Ϥ�P���U��2�l��vZ��Ӕ�h��`�8q�w�g�c�̝�9'VMK ��������D������+�{w�}\7�f0Q��,umlq85~�����Awt�%�tK�~W�M{Y��?\��� �o�P.��d�t��;��6Rװd_�2�
{��<��N�F�5$@X95u�p�IV�YK�k���X@�s�B�kn�ʺ
�)�S6��Zr �s��	�zq�R�rt݃+A�6�?�I�t�.�i$�<�pv�� x78s$�ے ��^:������o͸�+MM4,���%�F�qu n.���Ot�L���4�o�=�k�5��%;�שc��u��Q�D��$��!e�\y�����,�����b
S���v �k����4[�I�|�$f��} ��{��/2��ے��l�I�Ef�A��SNc4�������]������Beѣ��c��I�^�"D�����Qd�L�N�?��b��
88��]<��R&d���YYk��j�Cg��v��N�����$�[�[�͏��}1^���U6͎���J@Sgx���D|�t:�7Uf��1c��)��nbJ#p���{�x�[6��1e�'�w��qj�ؗ`�K�G(�o�����ǖ���d�Y�䴧��Xud�����2�;��?��/�c��]�Q�V���A����6Ռ�9���z,X<z��n�**������Ic'3�:���� [���gY�����hO9�ѼČ�4'H-8Q�crfJX��,&Alv�t����Q�g.#�|y+�;�����t���U��H��<��*�[�}ŭ��F/u9��۾�B�H�D�׎������X
қ�ߙ����I}��1�e��d$w;��l��L�]%}ɝ�c�I� fG�h���@L�Q<BS��ðS3��%�	9�
jK����2��� _�c���y�5�9�(v�D9�Y�? j3���<�y��Հ�� �(��U�LD���pr��.�e�s4`��6�z��l& �Z&�s8�71*�
=��Tg
�K��6��50�nhܳy#�a��;�T�����򅜨Hͮ�f�џ�o#��N�/���)���u���J����pN�~w��l��,Ë��檏h�t��fE����Q�x� /n��Z(����i$xH�F�p8MӿT#r0�JL=(�a����|1=�ć9��T�1D*ߤ���`������B(~Q��ЏE4D+�X�C '@�DS�S���]�!�iA0Txʘ=����ԏ�������A�yb�[�\d�c0�O�����*�̇����0T���=����p��ՓH����#��7OD�ٲ�uV���Y:Y�"͟�Se��[�.��<�/���6R�:t��ϗ�O>'՘a��qճ�q6�O�_[����������PqsT2�,%pÑݖ�B�r޻��)*
���o^���-�(��&q�(=�ٻq�'�~��j��x�)�Ǒ�%2H��Wi~	��"�	~΍��g�[]����ӬW��$}ג n�}x�V�AƢϋ�O#���p\0,�v-�V:b$h�����^�fE�ӹ����'�J۴�b=��M���X�WkF�rBt`A���8�>����43n�y�[�r���k=77$�5~z"�@���ͅH5L>������R��ݺ-�I�3�d���`_
��*z�
U�faGZ:A
Q��7���L����z���")#�m�x&x�G�IC`-XtP����s �E�`<�&��<�o1���ꤹ���Ȓ@"�j ��r��{F�-ՠ���
��!'r�W�ʦ>�ER�\x�����)9�]�IH���/ɧ�ƞ��EU�D�C1��.��8������q������5�H��RW�1D��/�F�y�l��o���� ��z/�k}ge�I��df�Qb���a��N�xa��\�uH۳�O�O�f��Uܘ���!U�J�CD�k-���͊x;F�O��?=�Y�����˩��Ax�}�b���Ʌ?�D	�c�Rt����3Giģ5�tԊPM��,����ߢ�)�ն��ڿ�g���F�M���܋�GA���}@3��u����3�`�n.��R��)o�J��q�7�y���gGx[�L��K�Z��ld Zn�#�ʸ*�+�D,h��PZ4��A���f��tz8!��O�H�"s(tCY�R�k��
9d'���5�b�"����DU
����v*@5ׅ��f�����n�?�
��l3s7n�I�tCjW���.|��bv���;B��i��x�?�/u�OR]���� �*~ŗLF��P��Q�9��Zͳ	׳�칸� ƁI9���{ �7=���X�J1�͈��M�pq��BoKQS�0�y�3�#s���z�3����k0z���҇�O�8F����M9oţ�p�Jn)���XKL���Y{>�n���R�w}��V]����wpZ� o4��s�"
�u�j�jъAԪ�ɖ��Bt3�;��;��㰯3� ��5��QU�~�َ�!���uĮ��iʜF�'�w|��K�W�۵�'_�7���CF�$(=�V�������K����i�d�0�c>UF���͘@ [�F҇��8�~h�}�,�����(��8H8���P����7B�H$96��a����y��
�I�4�$��xw�qO��K�WI~�[qJU\c�A�ރ�wm/���mgpͭ��-r���$mF��ԟ#�o�K�s��w+�7�M�&�~��8܇��w:�0��z��D�9���j��q����l�|I��^��0����}�蔔d�:=s�cA�$^�;���ա�'��5U�b�YJ>��bL�F�L��Ou����`��"R kw�)��L5{`,6)��3~�zin�On?D<T�2��\ڲ�c�X�.�p�)�q
�3C)�W�(^O�b� &���W���y*-W3ƞl�D�iJ{��Z���KU3M1�S�#�����&��
���nD�@� �{�ة[���$�v󣼠]�$���e������~�ŦG)o�@d�ǜ�su�U%#E�{��(G��4�d&�����nŹ�?~¸+P�rS2�JЫ�P'Õ�'��%��i��LO<�	�}�m��)�����h��pA��x1[!��[A:n��#�%�_(]� ��\+6p,���y�n4{�ň Z��{Ǟ�!�z-�.���o�yA�Q�z
�������׫�%~�/V���n"�]�8ˡ"���m[���U������E/ÎR�PX;�x�	�'kG��\1zq%�tx�������8D[B���\n�(z0�]gD�-�	����>}�-E��vڱ�������1�e>���@;��ͥן��K��=0����7�x{�������>�Z�x�|��8.��z�Q���*�SE��x��*b}"����)T�*,Qt�,� ]Ɨw��ި��]\M�8n⛎���g�}�ۓ��%�+�B��M%^0�K�l�o/��USV5r���p��r�5s�mf�� �);���x`7�M���llbPZ65i�0|M@z�>�]5�Rj�>jV_Ib�H���鹛+�S��Np�U={�<\�GJt@����̶���vḨ!!ƒ*�9�I�kvg%ذ�E;� f�{J�RX�D��4X���d*`)a�r��0<w=���,1�NcWS���<R�s�Qu���	���Qr~��l��J�@�?N����GjE<�K�g�W8�$�Ի�N)e��J,(0�,�zS��cw�X�W�s�[m�OAYe��6��_��5�UR&��W>K���a��
@�W�\)Ei_�Z�i��^1d����0����}�sG���M�^�����w��P�.3���fnĳ-�wץU�0���a8�y��;x�wi��n�j�*��N����B>���!.H%-W�i�1M-�<eD��e��_�
"n�,�x�^�[	N�TEq����a J_�60V=�L�@h�� �i.�V����B3�Q��w?&�=B{��n<��0O��c%2�����������JFlu�=<խ��o��l���BX{�yrЃ5@��4�
�R�(S|T��ï����Ӗ��e���$~��������k��B-ة���g
<?�LI F��Y�k�ϵ
�����Y����VaW)��u�NI��f�Ac��2g��ꎍ�5u�����
�i���k1W��k�7�ɛ
l������{tGϙ#^����4Z`6���e�����`˘E���8\55[e	)��Kl���%��F!���*r�R��1���c7�o��n���w*I�u�֜p�K�Ϧr4�8���n����%x�f�=���/�9
bbU49Ώ��ND\��
�O.���IJ�-��V��K%.d��Ϊ(���`fo0��v���|f�6���� �}0	�/#����w͕YD�H�*;��C��3�I��LwC) U�G�����]�$|�D��C��Ʃ�,@�w+�yB�d�e/L�#�{pN1c_cS�ZRP����Y��m�����ט :�z��x�"=i"GD�Ѫ�������]&�&`���D��LV�j{��H/X�s���z���+�<����t�٫�-R���C;W!G��>���5�ʋ��9��Np�m�I�W�j�B0��l�> �^�-I�+O3D���b��֫h}�m^x4}�<l/L�
nt����yy�)�f�s���OR{��]L�nSt?����A�����V�P�2��M��%����@�ջ�ro��u�O��D�q���2 i�h���.���I�\uu�-q�m��n�M�K����ga�C��l�m%%���zW#���My3�	4��C�KS�P�?���XН.>���bkp-�ј&
��
�dC⁰����I�k��C��=n~`1g[�5�-�����(Bj�1��|X��F�Ϻ������o-��Z�f4i=J�f��a���P�~�{!�\��~S�5M�|�Nk���w��Z+X
4`d�æ�n��`�|�|p&l��ĈG����c�mJ�� �P�>������a0���=)�j >�S
l�c)>��Cס9�� �%��)[��+�H��Ь�������h��]�^�Ă�URF�0W����@m��
~Dɒ��n��t��Lц�,x��G�$((�M����D��(wJ)�|\X�pCz��o�:eU���ѝ��箅S
ױn;�CA��[��M�o�!���{�WW�C��4Ҿ�������qv��L#y\���p��N�����>�#��&�ޫ.s��g�'#DC�}A�M����aǯ��1��\M�,{�¶�t7���#�!�)�8
,K��].�)�*�N�Z�;��Mw�e��w���Z���W�~s@ʏh���� rg�A������2���TAs[؆�E��$J�>�Xn<���"�5�a�8��ic`"��FݍW���|�Y
5c2�s�����	����f���-j��r�d���Ǥ���tA/B^󈥤>�
.`�Oe���Oy�d�j��f|�;�TV������e���SK&�x�o�&���|یzEa�-|$_��3xzo�C�H���yk:1�^& �fP:H"��V�w�l����AeW?ʢ��2h��o��fë6n�;�7�l X~�$m�Z�ȃp���<����@�v�7��V�Щ�I~,��PL��{�Ŀ�a�3��J�*i=�V��*�Z��:��`no5A��-�z�i#ڏ�`!��ʴd���s�pf�ڦ�k�~r����
��zz� &V���D�U�Q`OAo(c}ڐ��ڝ�x���d ���G��w y�Z5��1��H��.5Ժ͡x�2炱�pv����WM
7���K��>,�վ��}��}/��D���V7��nm��Y&�{�;ѱ�'R3���(��6h�7�>b�mQ�g�
����;�Y��,".\�KK�i�"��O3�:X+�8N*��nm����O(x�tL����6�x��,��
_˭/�M�T����*��Hh��q�tI<������j%�7s�u][|!������A������
�,������l����,Qo��;a��V��}����<|�]Uf�f�2>W�q����~�Nb8�}�/�����R�1\>ۻ![?A�zC��_X�t[�m���)f���'m�&6~�a6rNfl����	@�]�/�+�a�[萖Ǘx�L�[�-c�(
6vs���qntY�#L�b�D����R���}9��gZi�	hA�B��Z�Q^q��R�Rմ{Q���[ȟ* 
\����6��*�7ث9Rٻ�GE��+ r��B�����ڢ�ʜ���$hM^�ݍ�k�fǵ	}����:>u���?�(�ȡ_;C�!Q����B��~��
��A�y�*ko[���u_)Z�wk�Ѫjͬ0�{H�9����N}����wu���\��
H��nqk�D�h0�Y��K7�t����χ���̟�㎿j�ǵϼ@_����x1%I�?g�[�����a��3���	��Guf?��,�:�3a4S�E+��s�`�]�ѳ�V����� ���a��4�E޶݀�S/��vg$��ٽ�C�|q;�I��]�N5�h����U�&}����=���y�
�7"�*�x�� g�$�8�!��pؤPK3  c L��J    �,  �   d10.zip�  AE	 �����6R�����������تN�,�[��6�c��PV�'��>�(Gﭹ4�Cb�wJ,���+ѿ=ɿ��m�˾z�
�Y��x�e�:_ʾaul|�`rw����8Cۨ��1'��RS�|�QX`�X�Uw�z=�F,���M�Ť7��N�M�i!o��O�7�����Sc-��0�[�=к� �	�z��f"�ƹ�����R�":�j�4f�<�Ǯ��r=.كI�=e ĺ2bˎ����$%H�
��=SX\�Cڿ<�b�#��|�����U^�.�O�k�tf՚���<�G_�4�t�� *Q	 ,��)�z�n
5�ܕ�R����-���|��ԘlR6�j԰ Z�FX�[��f�`n��'���Ww�v
�Dqg�*��`,M�d�H�:�
m��]ϭ�=�Ͽkq�;Al9������
�K}����ް�؛(��$`1B���ۋR~��1����u9i��Ў��ɚ)Ts�4�Y�h�'�FEBQ�K�,s�wt�3Ώ�3��Yc���R�� fж��:���D�$N�L��.�͔����\b(6`�Z���!и{��a��R(�_�������X�/5�<n4�X��p|����$^�W�D Q�͐�"��eP���li�n"�+~��[���6H6�Rp��ZWOkIh�L#G������ё�e%�U;Zx��irU]��\��%����i��a�,�L�؍��p���K���i<'!���8&Aww����<�Xp�9�pu��^0�C9�M���}m#�Q�;޶��������(�\�s�9�a�庤ǚR�6��7��MZ�����Ā�n/4!}̜���4s����Бx���ni�������d�㼀�`=skg?-��&]` `P���"9O$͔t��M^oMG����tؐ������Z.�Yϲ���3��t��P���}S�mVEIf~�-_Zi�J/��'����t=�~Iԥ�Xر>�^,?�>�,<�զ�S��x��K��u@���mx�p�k	Բvy ���Һ�f�결�I׍koxAE�"Ϊ�+b��s�A~�,���M�
o�B�%��b�z�U�M�H9�
�:+��*·�r� hX5v��S�)��R[�2���
�^#Y�ir�ڂ��'���� �2z����%�8ȻЊ�j��+�.<υ�v��]�4 ��\-��HR�K�� _�c	�+��4+h�=i\��^��c�{洫e�N�`F�0��x8I�
�F�'dn�K9�ٰ,�CV[�ߢ���EYl �t%j�c@�h�BN��ʮOXف	���E��r�hN*q">R�k�'42˺�Bvʀz�(�
�Qr���$�p>U��k�y.�>�0j�6My��0@Y=�( ��PH}�k����~0�G�`���W�K�g<nnf>����W�2 O��&�=# ���j����j��O���_0���M������� 	����ϑ�u�`��ƅ�G��H��4�|�p��J��v��'3R�&5�?�%β��uy�ͨS��ɺ�җ'P�H��cqA`F���yz�V�@
���)�n��!�K<���P�z�M�tQ_hX�B_w�x�3W^�Z��5>�F\�Y�����b��J��U?�ٍ)��(��
1��>�_�%�t)a�r�F&��dU�9���
��Z�D�;�Y�|v��
�`��	%7e��^��F �ԣ!�DP)�r��ē�����"~f�"�&�_GHDe�'F|�������@E�tty���=_{<Ҏ%4e� Y��J�#�/i�ZE$t�hW�V6vd�^_`��P'y�Hؒ,���S����_M�k����+�bL+�jW
J�<�H����\�����X��YIo ��C�tu���}��u���g���H�u8j�Q�fdi� �������h~I$<Z�d�^U&.����ѡ����	�0��e��r*��R$B �<m~��k�ݞ�x7)���aG���f�X4]��]�BT="��z���rJ�2�������\y"N�ip�
��[�� �M
P�X8f����zd/��b,������&CH��hn�`uu�~�o0�������V�UcDǄŘ�ص1<�мgj[�J<�9n�M��r�ʊ9�Ȣ ��'������0R�U��x����A��Sq��]��o�/A4be�DG����)G�P/v , 
=��++�O&��4��)zJ�p�0U�iZ��`T1f�X���z���ز���VuںN=�Pب�T2���&9v{��Ħ�m���#�t��T�f�朱RM.�;����-��<��1"���:�-��jN�G�G*ɛ^�&k��uA�m��]���I/`VtYj��B�hW�Ғ�
=�������_�)�نb���o�x%��
�^�$J�23q"Q�xw]�����
�я"���]����q����}I巒b#�VMp�
wu؂��v<���z�;���ރ��E
��y_��-�ȧ1���XC��_
��T[����$5uJn�P���P��6�:�m�!O�Ql��]�h��2䬌�
D /��R�_��ѷ�X�?�/p�A&� $C�a�DN�&�`T,`&:��x��KO�7*��.^O��W]G��~`�������}[l
k&`�}�w�Hȣ-MMya�Y���֒ی����\6Hh��Ժ����>��kչ��ԨBjK8	�
�~9yL�*�p]"��ZQԹ������!w�̔i�zǑ&=���	�i9�*!<^��TbOw����5=�c�dpC���|�2oW�K,�۞� ɫ�L�����i?���3���Cj~�ohդV���L�A W��v���`r���ُ�T=	�6%ޑ˚�l;n%5ϔʥ1v���// W�h�p�!-�F����"Z����%SsO��3�����6������S�C�-����n�w����s�Z ���Zu��A��k�S��Y��[��0O�Щ<��wuÃ�-l��[��С�������7�o�=��i?�!��9��F�7��0���@,$� W��w�4�T<ZIc�d�@j�T��E�ǣ$ЩA8#|9Q��.�Q騀��]�%D��+��4R9�܍�� ;x�'����C�`)��wE�{B�g��#q�ЁD���'{L��I`�;%A�hq�~�;�c���О��4��׾��q�d����TK�m��V��X���`f.��:a���������B�|�p��![�:wʭ� #�]�kI��Q�m~���D��:Z��ይ�������ry���J�$���\[wn>ݰI/ab��0���Z\�� �r�!�V� �S�����57�W�h�aG��{˓hl�3n��*��gzt7�m\�5��Z�m�dU� @��ޅo4[R��/Y�- \�,�'g��_d;��T!h��s[��+k�f26%�N��f
�6��?'BI%!j���a���Idn���g�8o��Q������wtwz��vy,bB? s3�s*K���!ƽ���v�$�L��4�6�r/%!��+���%~=I�@�l�*!_c6�a�K6#��s���������O��s�VI��dwgVR��<*�;���A{�9޿kq��8)�%U��6X��e@��3���Jݖ)G����豷�����3�.��^��wۈ
)���Z��J��������('-h�]��O0
z�bǘ !m1c�_~Ҙ"N��~�Z��'H#�MZ�ؗ5xD@�ȟ�b|� �`^mh�V9b�|k��~m�¹*<W�;����]�h���ۋJ��t���v��H��nzҤ婀�����L�h�Cd�h���L���un;5�_y��2c�~g�8�MlD�<ݏ��7���x8���~Da�!��(	���'�H1�w1k�q��7y��~b��
K/O ��Y��]�l,"�څ^Y �Q^
$��x1�׽�}��Tj�� U�@5��s#21�T X^�,��2�p�P)���K��r��ѽ̃�s�6���V�pk���R@��W�l�i_R��ԜjJ�
��:a�[[6RS*�Ԙ������z�z��O��T�'jr���'���e�z �k|��j'��B�_e�Z����x�LuI�A�]տ�,8���? k���q���6[��
Rb�b >����
��h\�c����zph�#S�0�^*��3}����#���@6+�*�G9Dtj.���U��uXNC���sF�l߈���Z��)�;݄i@"� ��ɞ@QΏ,��>����uA�.�/�
����j�`Sq�ԉT�>%�lp�̇H�$�	:�)��ʍ�S��� _�R�U/�U�m�(u8&d]�r/�pf*��r<2�^h��Pԁs�}.�ff����\lb���<�P���Rxu�;�p�!�ѪRx�[zwm�؝
��r���b�KQJ��*������Z�#s;@��������7��'���M�����$�FFp+�	~䔁)K��dL��*�Y���!��V�Qn��K�LKK�~L��R�hƴ$v)�p��6����S��K+o{Fٷ|s�tt�nS#(��=�rrq?��%���E�n��1�PK? 3  c L��J    �,  �  /               d10 - Copy (10).zip
         ��!���Z���櫲���  AE	 PK? 3  c L��J    �,  �  /           -  d10 - Copy (11).zip
         ��!���Z��h�����  AE	 PK? 3  c L��J    �,  �  /           0Z  d10 - Copy (12).zip
         ��!���Z��з����  AE	 PK? 3  c L��J    �,  �  /           H�  d10 - Copy (13).zip
         ��!���Z��~�����  AE	 PK? 3  c L��J    �,  �  /           `�  d10 - Copy (14).zip
         ��!���Z��� �����  AE	 PK? 3  c L��J    �,  �  /           x�  d10 - Copy (15).zip
         ��!���Z��JĲ���  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy (16).zip
         ��!�������ϲ���  AE	 PK? 3  c L��J    �,  �  /           �; d10 - Copy (17).zip
         ��!���Z����в���  AE	 PK? 3  c L��J    �,  �  /           �h d10 - Copy (18).zip
         ��!�������۲���  AE	 PK? 3  c L��J    �,  �  /           ؕ d10 - Copy (19).zip
         ��!�������۲���  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (2).zip
         ��!���Z���ש����  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy (20).zip
         ��!���Z��o�ݲ���  AE	 PK? 3  c L��J    �,  �  /            d10 - Copy (21).zip
         ��!�����g��������  AE	 PK? 3  c L��J    �,  �  /           7J d10 - Copy (22).zip
         ��!���Gh��[t����  AE	 PK? 3  c L��J    �,  �  /           Ow d10 - Copy (23).zip
         ��!���2�j��R�����  AE	 PK? 3  c L��J    �,  �  /           g� d10 - Copy (24).zip
         ��!�����k��:=����  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy (25).zip
         ��!�����m����a����  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (26).zip
         ��!���\oq��dq�����  AE	 PK? 3  c L��J    �,  �  /           �+ d10 - Copy (27).zip
         ��!���Հr��䗡����  AE	 PK? 3  c L��J    �,  �  /           �X d10 - Copy (28).zip
         ��!�����v��Y����  AE	 PK? 3  c L��J    �,  �  /           ߅ d10 - Copy (29).zip
         ��!�����v��b{����  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (3).zip
         ��!�����x���Dͱ���  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy (30).zip
         ��!����pz��"�F����  AE	 PK? 3  c L��J    �,  �  /           &
         ��!���ql|��`{�����  AE	 PK? 3  c L��J    �,  �  /           =: d10 - Copy (5).zip
         ��!�������X�����  AE	 PK? 3  c L��J    �,  �  /           Tg d10 - Copy (6).zip
         ��!���	����7����  AE	 PK? 3  c L��J    �,  �  /           k� d10 - Copy (7).zip
         ��!���{���C/S����  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (8).zip
         ��!��������������  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (9).zip
         ��!��������ल���  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy.zip
         ��!���c.���K�v����  AE	 PK? 3  c L��J    �,  �  /           �H d10.zip
         ��!�������ҏ����  AE	 PK      w