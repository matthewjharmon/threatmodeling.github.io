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
�ucA�R4��=��ߋ�D�f�oO�p���x�]��;^Y� ��(p%I	�|V�C_������r���r�����j�[�t�V��{�����q�5��҄�7F�{�JǤ��4>�]{$�̫{�*��� -�@E.�{�0ߑ�<��~�Lh*�wLtq:A�l��L��o�l`#p9��t�u!s�$��>:�\�1F�[��D���O�d�����Z$���"���u���1��T�\dug�P�MX���5��������()f�
V�ƿI�$� ��SEĸ���t�E^�O��9���A�/��4#��pW����WE�@K��:f6Y�a)��9��@8�92B���_0��R����}���R�j�f¦'����Yl�Z~���
N{|�Pt8��� 5����AHZ?7�k*=�����P��K
��~�=�KÞ��N�WJ:~@HKv�UA^����e�'�,O�MnʟB��ō~�42,�
40'|���ѮjK�����0���Y�|�+F[a�%�%d��m:!/Þ�����ʻ�	a�\Ζ�ľl���Z�#]�2s�զ�����]z�dF�Rq@�K��+=s��e����� �">����8*Q��>k�'{�B�ْ�(@ɐ����#72�M���5�ݤ$�px��j#EwK$��c�<^�f�9�
{�� �L⧠���,��[*�v_{��s��K(�a>�T�ا�YZ�V!}?h���Cjx�����C��������~[�B��NZ\'�ӕ��\w�S�@����=h#o,e�;*�c�+StȖ� �'����c��b�9y��^��B|2L ��e�����f�"z�eB��~�t��#m����\L�ۮ����#�Zj+�3_Z�e�W��"�r�Y i�b5N���0�X�����'�Y$z1U�o�5�?z}@�c�f���r��D����X?�dT$�F�+���M��Osa~��we�����,�6��m#S�U�?���q�>D�}����9q����{�ǰ���v�b�չ�Ln����P����jm��h�0e}�����8U���p<��+v�:����7�+:������qK�߭�M�\V�W�
�@i�y���P�	�a�(�U�f�f9�䠞�_�<�Y���V-1�x�=�wC��0H�
��Z+�$����h���%t� ��9���0�g�Йy�컠**��?N;�*�Yo���B:�P����V�U��z��~�������ft��|��	}�n}=�3��cq
G��Oi�}���U��H��t�� G/�Eg'�Y�BIrMy�� ^7ٲ�-K�9v3L�[�a� ��nA��@��ĿƜZ��`p�\)����
�M�m"(��$h|@]��d�� 4�&�'�Ù�J�)��2������U��5�H��W$.{�g��<�N@@᰽`̸����g��u�ao����~6�p�(�[֌�H��� ���WjP�3>R�g��w�����`� ��ۥ������SXԦ�lp���>���?�Q�6���I*͝{YP]�V�oz0����H��q"3��85OC�9����PwR�M��Kٯx��y�E-���Eܯ�5�y�B���01�w��{*�/	ga��Ï63H������yô;�LV1t{�G/�֎D�m���Y��%�&�I������;L�q�ʗlh�(ى İ�`E�,�qZ�C�9�>^�H���h�3#x�D��QNh�J(#����0&����߮V�j%'F0��5���`�t�W�������ܚ���[�n�#�r�w8	��"�	A�P��}���뾒�9t��P��)�ߒ4~�?��r��~jo���7��[/ob��Z�y��B=l5
ʖ���8��`Ch�zx��/�i��7�%P=�� ����q[�d��?-��v�b��ד~�M�D��l��nd4Uo$ �O�D07�>P@zS��\��2�z+�9�h�[�2�cwЗ�.ްU��E�I|}�ҋ �fj��%R�@���f�欁�Fm���ĺ��2���.���C�����3�ZmٓIU�3�Em;��I�a�%�r6�R�����°�Tk@����q��gO��J�u7B�y���&1���\#!'d/:R��?
��a[Q�_���!i�2:�K�&� ��sx!-��% M�Q�S�v$W!�Ux_D���`�&j	(�`$)ô=D�qr\�-���[���I�����S»έ��63kyV��Տ+����zs�Gr���E>T�uCM��^����&��Z�S��a��C�bI�V,����_o��^y����C��j?��>�K�V��m-��>_DA�Q�r�����������Y,ze�&��Z��p�H}�����L!7(�֦�
�?�Q�R����/)Tվ�b�K^�$@'絍����4a<��o�l��ǽ��z#/e����=C;�۔��s�_q�����+SgO����+��N��Y��e��5��&gૺ���c{ym��<OX
�~ߗ����O^b'AB9Q�45h����g��K	*���|n�(�)�0�Q�=@�v[�農��C�6���O{H8錴(�ۘt d����J�
J��"۳�c�J�Y�SJ�P���ߞy�M����Y��&�^��ʥ�g��V��{����C�Z8xӚ��4Қ��eVQ����s���1�ҵɩ˖��ָ��Ϸ҇�ۄQ��ٕ������F>�?%JRr���ǟ��^��_A}BH��GQ�L L��q �8=Bp��e{I��a[�!�i	���J��oL�_\5ψEx�l�d�n���vr"/ÃFABę���"��X	�,�I�	��Co�݁ׯ���7=��wG�Bf��5�V�`ʇ�[��)5���;��=�!����&b��*��YWz5��?lXo f��rUWc S�m��W�`�\�R�5���w�T�0�,O���F;�4a,���H�F���a�>��01����7��*�.g*V��yU�G)r�2��ֶ��i�Y%$����o�չ뭇����==ܲm���_�:_�3�?=��4�Մ<���O�s�z�f�9�ot��?�3��PJ�9V#w<��k���D�ư�x��,FVp�$qn��7���U<�n���ܺE��@��.�VA["���F���qN�k�d�U�No��=Ƌ@��,V�=V[���cpr���qX(�#�f��0JXTv�n&#1��/��̎4�$%2�����ʇL��=q�l�Oo��k��*(t����R�}����L���1�{�@�D��q����2�)�a�a�Hh���n]p!;����	.��rg�f�K�����w8
��{���6�&\�����,�w��Y6��DＯa!�(��P0�<�)ّ��W=�;���:�1IHZ��' w `U�.�9M#����.�3r2��Z��V��QJm�v9s7]����JI��,�&�5��s"��+x.VQG�k� ɪ&� ��71��R�Z�2�J$������d�uU���42�ּM� �@D^�ƠݩGJE�T� �jg]~P;��b-�VLY���B%.S]�=FMg���U�p��	��(�v��|���u,�A	�*�/�=��(�	��pG��֋U�]��eO����x5�Ř T8��;�<B�p�>v��^T*��괅�OGNW_&��B?��&Q��48��ê�U%����Ľ��w.7�0��� �+x��V3SkJ�fI��������l�e+�b�	I�+=��{&Wu;o'���:���{5����)���G���Z'��W�֭z�w[�|Z�6�A5�S�P��IT���i��Փ�_�2d=MƤ���ҧ~v�O���W'��%�������ݣO�P}��ǆ�¸5��kam�q�C+ӝ �m��Lo�Eߟ�K�M�.s��,m�̢���X&��y�^1�9�c��3������;��E��<�a���m���ev(�'��%��0�rdʄ�^��_�7稐�Q��n���A:g�'�ֳ��o9�i�?�*�"��l��u���\�}��QPf���<�|��Wو�H��}���7M�~�)�tf��闂�%�n�d3UJW��Ș]b6����470���@���Ը�����0���W~�� `L�Kgt�)�{'�6�I�\ڱ��K2��\b�B��7�W�l�w��5�'}"��0N|%��vx�54�vO�X���@�c���-BB�����JpM��di�~��Jh:��	?{�e�~|��. �5�vi��-2U��\�g�3�"R����!���)�O�:c�J�?�
���8Ոw{TMO����:犰�	*�1qX��1X�)�Y;�t��oYXe	i������>�����o	��7�����z;��H�N�q|\��َ�JTGu��(N=*ӌ��o�^0L�I��9oXPO}p[Q����}��ib�RPc�o��)~g���� [���퍷i/�>�!0�+B�܉0������vi��wd�\J�Pd ~	������<e��eC�%�c1�*�4�Y�h}m���5EB�|q ��^"~0	���6���f�b�cҢ<�/��%���:o��]1�'�qi�:BK��TxgI#
Uj��l��n��͒(��'/\�H?אp{T
�5?"�)�zW��	�@�,q��v�ϑ@B����R�o�ގ&�_o4�dh�T��D:أ��t|�.�M&�)�2���ߘ�_��� 4rn�]��  4�z-j�`y�8���y��,\�M�Pw@r��`��M��"��O��X�	���.���N٭����e\�wAgΙ<���5t�G�{8�d��s>eC��F\%ɳ���o@ �\G�a$��K��w�QX{4˗�4y
���.�1�So�7M���?1�e��t@�R�~�).UH�v�ѽe���6� ,+	�{�mQ	��nĲ�G��x*��R��[ث׃�1�X�w��7���y�.lJ*����-�w�u,o�6��K�;���K���u��<�<��,r(g.�q��VexTxv{!^�E,:����Dwy�U�����
����~��o���z�7��i=��]A�hq��� (,s���qr(�uG���G���u[�����	n��SU�RDUM��y��
Y1E���e��-~H�kTjF���#�Q�%��e8+����c�:��\�>(�F�M�����	S�X��eT�wG���2��⡕kFi��.����%��8�]+*B��z���CWm�{�f�Gs���|J1O1P�x�����E�(J�lV��1��p��a
{�A��"��*PW&5����S`CáC��q�ʰ�E�X�e�����k�"��y��ೲ-h���C�H�B%�Z�q~�(E���w�H4�"|��W����[�/G�ɲ��?��=��~�=�Vd6M�I:�v��	I�� d�=�:���F�O�L�FN=�aKEi���T .O�,�����Pa�3�)r곷8 �&��F�[N;�����%���//�GF���%(�cV��Õ}�;�goR����z��Ҹ��%}6�EC�F�����PzXip��7�� ���Q����U�2g.�I������eE!�Ä[MS86�fn��7��$#6`�ldN��-_I �Eh�@�=�%�#[א�M��e����K>w�n�������	�)�1b1W$bh�N���ݿ!_��>%G�z<'Rh��t��������2��H��G����5��z�$,����~����f��:a�s��_ 7��iP�R(@�}���5�N ��m���e-���xO�������*NV��U���3�ɒ<���9���efV6;DE�@�B�.�-�w��s�����L���k�Cd���s�����hx�נ~'!���A��<N�����H 	�|Be��I����T�CS�.u(������w3N�O�r��4}9�����-5��o���iD��>�L
��P���V�f��Q��o�����;����D��\/�SR�u}{�e�@���^Y�3s�E���b7��&�A�/�֬�r3��u�m�����j�d�4 ����tE��Y^	, qB\q�,�3��W��%�t_ED��	]
""[Q����7��텆�S&[`3������dI^��[mC�W�P���b�llHx�N�2��5��/����c�n�R��X:b.^���[IoX˳�T��r�"POX�P��(���_g���F���p��ݤ��[a�? M�h5�Qй�'[ǒ	�,\t�&�M�(8�X�ϯ��`׎�������O�H6U:Yi�%B�cO�񑨳�^2TN�t^�{�3W�-&�O�W1>�o��PV}�HA��m� 4�SD���V��Ó�I�瘕8K�)ܱ=XܚI��t�d�ߓ�}��ND��&�W�ٓ`�
�R�nT�5 ����sS�����3�%�IY;�����
��3�LFlZjP��*�X32W���"lg��i��#˓��>U�ؖ_���F���
�ؓ�%�4w�|�aa�N�����L����O�g�?|�1D@�ԍN�[� �Q�]�yI�+�$��$-N(���D��g2��=^��#��U;��'=6B*�vL���,܂��R�j3=l2�+����T�����<X��T�nѵp���G�M��_R�c�g$f;�h�0�ֻ������k�|l���"p���  �\
�O!�5߿���K�z*�-o���3U��IT�M�q��!O`�Ǡ��m޸]�[���kv�������HDP�f�~�[*z�M�H#"�9�G8)��` *X'xo�1J�f�M ��IU%&�~�&k����v����i���"��z���e�ř$>�%�>A�r�;k��V3�+XB08��J�C�|g�B�Qz��
L���	i�zh|%r�4M͹�� a�S�a_� ��AJ��K4D��VpR�S�Sg10��2#F-\�?��ע�7����7�tA6�|�:�ɂ��1�\���o���_�;9�[�N^�I�`H���@��@��PM<*�[�kv\]�W5w䊞A��"AI��[�m)^0O�50��Z�?mck֋��
��'p�����gR|��|]#�6�<[�/��5D��G)Q��YY�ė��!>}$2V�*�S5��^�JW�2��"��͓��x ]�3S�3H�fQ�U������1��-4��}���z��U��!^O��q4S��ז�:Z�ѥ�k�<�j�lp�rW�/������,[+8;e�,����:޶�d��G���C�$�ޜ���%03 ��6��
�������R<�!Sە	}t��|�<턁��y�B��+��S[&.\��o�su� ����闫�yu4v�n�g뼻��~��ތ����֓�<|oX��F}3�#.T噗��H���~�Ʊ{m��nX/(B���'�ݦi[��eԈ3����
��@�&L��21��	*�=#e�GEj8�y�����2Z�b$2]X(�t(��l���|�*��L��s���k��A�,�M��� ��^����T�K���$-4}<�c�[��H�W�|�꒴i������2.�T"�sE�d��dC��N�5$r�h��2���?˒Ӎ'Q4yx<J�U�����q.�U��*wegpl)̬���4u����@u!	)����^�# n��~�kk�����f��kt�6E�Qv�6"���kYp���Q�b�/�bF���VD���f�]���{���%�
|��BEi���@E)΃v��*]�2���1��ֱ�]���t��م���V�;uΨ�m�i�=�}
`W��%3{U燵�&	u���T��M(�x��_��]�#��l��s��V7��Oe��n�E!��Y僺#e+5e�.]�Z׭����lrT	$�x�Y-��4[`|?��%�fn��uy54�27���l������:�v��'{2�6��;7�P�/�:��t`)��DO��b ��{��[�N.�9#��!�iV0�[�"��7Ǭ�J����r��daD����3��s[�~���zX\�ߊ�k���nΓ���Ľ	ai�l�f2LC�_�Y6�j���O*T	�Ѐ�I]�D��<��O��ߌ�v7��,�`9��mm`�1��y�-c���
{]\H��K����o������|yY��+�Z2u�B�xQq��Q�Ͻ5���0��p:��|��2�$�p�!sNi�4t��@�Y�7ł�[)�kG�v�
7��q�xU�Q�}K�1#�&��
�Gz=8��X�^:n��σh7$-��N&�<8wRi�3kA�V	SץX$��PUJT����b� �"O݅H>K:����	�Q0�
�%���[�,\����$.��S�l�\D��~�n��t�V�:)���i�|�#ہ�N��*���]?j�JNk6�B$��..����7#뿅|��^PI�v�G�#fθSƳ&��("�|-���6=ƫN>�K}�FO�tl$�$ җ9꿻N�x�"�Rm���J�e��������z�����bT�筡�JR�L7����di@�Ӆv˸�`麫=Yz�]����C����8�wt�y�x�u����o;�gD�k���YϷ��2��w�JS�U��ON'i%:���.�O��vlWj?m��ڒ����|M!WW�UDi(t�����:jK4N<e�ӎ��z:Shi,�EنG��������˃V�VA�}��
x��d�܄��{v��!ygT��u�q*S�-�sR��I-�T��g���`��1u�����6��ST����i��ȐK�<l� T�ne�5H��nɻP�����a�>daJ�&���g$�x8���y�pg��Q��Xך����p�M��A�� ����R��U �K�ߤ�7T9���*�<�j��X����#���*qM����b�f�[�\���i:����>���Dd�m̴&a1���L�=|����~�odK�|4��v팖١�QƲ\�%L�{����gTR��e
��a׭U[?�;�m�ek��`蓭�l�A�t�da"d�����AT�j��J���n`oٰ��
��_Ά��4��3 :~�����ׯq�-�D5�
Aԣ�iG��q��mq�rMFM��d��a��ܢ)�Y}�L��˨���� c��T��f"����Yw/l�;�\�q/����@[h��C�X.$5(������B��)�*yIB~ �f�߰����#�-��#����&�C�i�x�/�rl�u���^삠�KPÅŌQ�rP���+�P�͢�-t|T]h�ߥp<�����J�[�)��<���NPq��}�DJ�:�q�D�~���I�_�=�d�1X1��d�G�q{{��r
]�p���A8�52:P�/�2�5��j*�.Qz���)ht{^O�r�^�I�7� ��2�G�?���3پH?�+�%@Y�{�A)q����!�j7����VW���x�;���J^�K�����ϡ��e)h���m�<�~y<��ļʢ6-�XW"d*N����,i����>{{����7Dl
��>�xy��p~Ҋ��}��d� %�&9���A��T
�I.�<v��\
�rQ�m��:��	���k;s���.���kR���R�&���ؖ��n���|�&#�ɑSiz���c�uk��~�N����X��xL�G�j�A@�.���z�9�M��}Z[��U[���'����,���Ҧi�T�ʁ8��ZQ�܈�'!���mj��6��n�\!?�@��B��ٽ��G&��+L���(kL#t��Y��K?i*R,��=�y�f�z��v�Da)a/y�i�}g�}xE��*4D�)�i�b��I�K�2��X�w�p�0��%�����)�<	��8s �3慽���Rl�<b�MU�pݤ���)�:J	�3qY&�}���^�g���G��~�(ʻ	qe�K
Ͻ���|��/�Qy�WJ��B(��H=� �Z�,��[���G��~��� [sM��ICv����i����Mu�+�܃�l]G
����M|��U���eh�\{2��'�Aâ���գZ�s��8����fp�_:zbE�G����0��Ѓ[�����
��!bv�!Ӛ'z4���l�d^�*r$�t�u5�����2�E�b�%��0X{�[�t�Z��J�^|[˗ᣲ-{��>�(
��0����G[��ũ�?7O��}��h#;5e�w(K��H�xġձ�z7���?�E����x	���1R:��x�S�w�f�n��^�&�5t���=���wT=+1���S���R���n����]�q:3��z����-�S�.;��*�#��zꙌg!:��I����x�"�W]����� Z4��hNYMDu�q
��A)�"��B @!�R<sq\���8�s�(#"�4���!l1���&��s�Wʘ?�VJ�T$!#v���#l���
Ώޜ�V+�v6P�l�0 y��h�r���01K,�X�ˣ��xR�rZ�FW�iگMaD�Q�Ӝ�,W%���oV��`G[N<���@YB����Ly��)��"&����d0C~ˏO����<ܥ5D
{����Qfi���d8��ri�e�G+}Ә�Z�ӼJX���.���/t�:�<���X��P�"�%��Ug}��W�NuT�NPb܋��\�7�]�@_Up�VI�H o9�����?Uց��d�ގ���J���Ja��}&N"~!Y�-�O��?���kB����b	sc����
�GȢо��	9'&�n�;/+�ß��$8��/�q�mJ3kx�������A��,(��O: �m�ՖX��?��4��))�46���J;I��ѣ�3��@jct�[�(�`��u{����5�����V�?�7�[
߿���E'�.�3U�BQ�:}�k�u���;@y�����s�y��}����,g�^��
-�*�O��x�k�v6E�a�Z��a�b�ts�?�N֒ت�շ�U�/�;�h
5uy�<5����0]�l�E�Dkg�Z�a��]ڒSE7�p�i8W3�\!��Ņ%����/�*97����V���C�م���]!��	}�d�M鶔�QSyA�Y�?�!$|υ��>z�y6z�]�S���|��ZCUO��j�֙�-K�4m���������_PK3  c L��J    �,  �   d10 - Copy (11).zip�  AE	 �a�dD��X� 昆�2鑎�z��4e���)c�6� �e��G��a��tDݡ.�췋@K��dmeo���,�Е&��A,WB�<&��d4�����m}gE���y][L��U:�H?�UQº�f^�������`qPH�jKR�X
�!s�Հ�9l��?M"�m� c�d�f���,
�q/þ�a̬�܎��ϰ�����P��ޒy�\��VZ�u��4D�DD^~g3`�� ����-	�.�Dx7��3� 9,�MEɯ-F
;X�* �X3@OtP��
nK~}.U���]v��U�S��E�������6�PH�$6�wH���j����"�&���y�T��o����C
(�ex�]����v�0tV���:�=����cX||�V���2���	�/DGCY�Y���Rft���pN��PU�m��{�\��ڝd�;d.'�
y.�����$�ϳ�z��*��&Э��Q16��(룺���A\2|�kqr��?&�1_k�?X�O�A,q�����QB�)I�x�f���$�Ƣ�;G��YP�,�?3�!�I^}{p����X�E���"!.A�q��˧gu�Y\�>�Z�(�#u��ݥFx�!8��`yeP`�I�r�Q<����06g����C$�?�Uٕ�)�O+�sǊ��9%�e�x��Q�[;�=(��< I���c�MB�C��)���|�����K��Xݸ:�+p��@.�F�ENa��/i0Фtz��7�V�v�?A[b��gee���9���F%>7QT��E�b{��46�@�{��w��U���p΄�	V�*+��]yR��.��a�iGak	��N��.|���E3<z����Th��Z��ʿ��Mx����R��y��LwZY�=|KE|�n�s"9�rOaB�f��*�>]W�:�w �Mϔ���+��Ġ�1-+N���a��(FRn����f9#�����v��|X2�]�	�֮��hS1_�G>2��Biߗ��1Պ����l�Z",��`��2'�G2>F��Ы��hh˖8�����r;m=�9jB�(��P����׍�y���ѹ��(��1��sW��=z�5ԑ_Z���p��'�i�'������̀��'�"��	��ʏcl��^"�-Av��'}q�Rh����l�(9��W�g^���Nc:��Y䀜�a�$�dQzy�c�˾Q�U�,�u���ؽ�> FK/������ƿ��RZ��V�_���~PE1L�М��sä{W;z;Y�3�#��X���Y��}��;�f�s���`�=A��s�*4ި�Li�:ZЋIw�E�C����u�.q�sb�K���Q�w�n�B�/��@@�!���1�A~0&���y�p�/���,u|��h�G񩓁��f������ȗ�ķ ;�v�r;����q�(���j�5_ȄF�!��i�P�Ggc4�Į������Qȩ~kqǓ��i�'1ܴ������>?��͆j�:X�c�{[!�Ah�|'�:�D�Ͷ�K^J����kwz\Ӽ)�6S���7AZ� ��V@��K��80Ȑ�0�s%[g?���f8>���E��WO�C�K�Y���Z|�	�W��)��F*��ș��m�.uy��h�w���p��y�H8ڝ�0o�%@�Œ3i�P���S��]hˈ�hQ�����R���b
��	h X���IB�	���eFt_�qS�+$�z�1Pq�%�u�l�E�r��!�0���q/�e���x��T����T'��ƘfE2�{u6K��Y�5i�d/"�$t9;� ����r�
u�K��i�O��.A���A���m�׬�9���ufTa�]�2%��.Kqf騈|��#��ҌQ��X�w��m3W�d�I�>^�X-�0�n3�i[���b�W;D�Q����f��%�w��_C`�^��a���x����J��/�
��rI(�W���k/��������2�����",��qe�n��Bi����  G�܈�J���l�c|5��i׾ �������S��ƪZ�ɯ�M �r�E���Jb�tQ��?,7B���Sf���]��w+�;��?^�ǟDx�V�[yɍ=
�s+0�ZI�#�6��/$���z��7!�>$_�M�بũ���F_��/�/lj�~b�r�[��9	�y �Nr�c|`c��?��N]^.VS7�bX�B��?�둢��_���d�qT����Y��{������ϝ�#"�V����_���y_�T�RU�uuK����_�toq��Ǡ/���t�2�a�0H	���r$����ظvDF7d��j	���������e���<W [��Y<�4�W",��D;���@%�l��	�>߯M�c��6d��&�ϐ����_�_���x��XBR;9=�P�}7�Θ2e P��;��|SZ�"5�TX�Ys2^!��a���Qy�\uU�q,�ny��4��6%7�[����J\��幱(��=������[���SdѝÝl&�NI�D/0\���r�n�?j��Hز1=߀�U��d����OW��ʴ��z�-�42�Ϋb�����4C���y�Ʉ>��-�Ok	+�\��[�/���U�J�Z���)/�{/��Ǫ}\�cF�����#�j5��L��������G�4�G�a���8i���ex���W��W��m?[����Bq���`I�$;���>���o�S�9�ǉ�F!Z0*+����r��Y�wD3.Y�Ӡ0�h�������f>�<��)wJ��+�{-���vKhB���������sBqIҨ��k+�/'Lf{��~{N��)� rMԈ!!e�ca�v�ˁ�fd_*=V������oEMm���7�M��o�;�Co�l��<���F̺��}���0w�����Xq��0�T��k�j�L	Hsq�����9U��AzX&+/�sNC����YofZ_B����GY��=^`�����+�\�Y�/l�
O��^�PQp�h2Ɯ�'r�SZLg��g^k�E�9�̹��MI �$��f��n�c��i��ƥ�6)n�xa� ���S��^��_��(�����=�O0�S�׿�l,����x�2��y�@	^���]rJ�\��,I�����d-���}:�ԯ�(�B(d���D�Hd5x��~�)&�g޸��h�-�s6G���]��z�=����髶��7����XU���qkaZѼ%�a���x�&û���+e����OL������NsV[�L��n�bG�Ӣ�Ʉ�+x���{J���V,fTK|�����;wfm�x�J���?���7����@��RU�i��EU-�x���C��7�Ѩ�K�U�q
6�3.�^�a�+��w��m�$��q\L�C�����t�\o�Z�0��/��>��-�ϙ���ob9g���>;��vC)p�B��\�����6b��}'���&�U
ܽ� �jPE|�Sx�U�G����x5��W�x�X����S��oR�V�*}�1�'o�?Z+�[�<��������� ��VCł�<v�"�O�Aw�D�:�fW�h_)'˕ƕ��nW����aGEf�P�{��5�����<��[�Sl�j|�z=�@�ΜZ�O���>�GK���O񾲴��r]����l��?�8�ʻf?���NI~a�^Sz�ZTr�Б�GR��T^0H:�[k-��v({U�Ð}>$�xr.��)���>{0ɳ@���t�K��)�C��7�F�*.�#�F�P[��*�<�O�
E��=����/v��X�t�x��t�:>!�̠�i�����0�!��P�Ά�}�X�z�c���p���$]�N��)fm��!Z��R�\��Q�!0rs��k����c����?���Ln��j��Ie�� 5����fn���BZ��*���=0���>Z��Q�b��^줸���hB=��8}I�G	E"�*"���;�d���/��+{�a�p��訟� ��^�fA��yQ�@���<a��8o���ן���1k���2�Nנe�z�� T��{���ݖ�rx�a3@�dܓO�Z�!�?�őS����Ɏ�	Ck�m�Mڭ����S�`Vάû�:�86��P^�+ިH����+M^]��2�� ص/[�mS�������u�)l_+?�&��@�if��Ouq�W�-�]�N��ʱ]��.����_�L��K=�\�2��!ZU���h
�B�ÚP�nr���!������KϏ����yݧ����}ʵ�z$
������yn1c6=�����wį>�>���L �����c�q��[����� WFA�u9J>+`��q���ўoP	8�1i?���S�jϔe���ОA�F�(���9(�q����.����ԥ�ʍ�VZ�	��Y�C�Yt�m}c⢇M���(q��=��?� �,��Ӽ�t�=w&�B�(I�'�?v�+��L�Օ���J��`��@�捙��:_iB<~ƧO��K4��&����?�f��y'�eT����A�!0P��8ם�;�=��&ʁK���1D�DDL��Vqj��wj'�wK�/6��������+ژY}vY
|�K�?��_�]Е��.��Q�*` �ߊ��wf�^������G��um�NL���}'�9�֥4�L�A��.s��/L��R�`lB���Nj�J,���d6���G����!SS�{�\#`vw���G)y��F��*�a4�!�н%w&����mZh��+� ��)���kc������Ҧ��È�bnnf)o/r��,gf,���" ��gu�f�G���cÒ��j��A�Юz-1칄`HaO)U�uܔ`�g�$� �����x�F�ߜ#� >�$!N�V�k�{kHX-���}�|���9��BG�섨|MZa_�i��Հ��8�|wq����J��A�K�x=́Q�`]/���n�cD<a����!섕j!SDwPT1g��}x�ak�T9v2���L��x�h���@�כE"��w߅��/�G0��D�k�

�`�.�N��N1B�x���~[�+��J��yӻ�l6Zv���!�.�G���.'|j�<��#�Lo���ý�g�H_V�F�F"�?>�ՠ��0²I]yR|�$��1z��\��F{1u$�d�V2s,D�/<ȟ�P�b�G����* ��j��?�˚�1FM.�n�d��2��]i�э�?v.-e�N�����M�)���2~N�/�3�|�$6zk��k��2h�$�̥����I��z�7Qg~M���\כ�w/��z������q��f݀�"�kR��o?� ��C	�i�0F;Wl�6��2; z�`�u���B��>�!�g��Р��ȓ�,B�	��#��brU�3S�|K�V�tw�|܈�w'��u���xs�v���	��h��$�����g5��1Y�q�Kd�(�b�f���º��1�������)T^�LH�y�KDD=7Se�`X���)Ubop7�^y݀���R|���_��&��	tm��,OV��v֚�������G�zL<�4��C��Jo����� #Y��i�����JW�Y���g��ؚ2�ŴN�e���F����h�9�G�ib:e�M��� ����]Pb����-S��r8�*��F�Y22�-/�.W�+1���j0
��
��I��D�X^3Ò�i��л|؁3.5-�:������$���d���Ӧ�6_$�Z��Ѫ@�y]���j�u쐀h�����vVVC�Y�(�p���r��Ͻ㪱�ıj]���n��'��@l���X��:n���8e�����"������k�@��%6�}��&)��q��H��6�5�e	9��(���n]2��<u�y�X�����;�d?c�vcG��S�&?��~U:�-3M.��'@[W*|��n��u=Ms�a�����Y귇&E1�����PHV΅��H����.��z(��C����_M
)�l�1�ι+&�(NNg>VQ!��G��xh��+Ͷ\�D�3�΄�D)�BZi1L�Ll��P��}�������'����Z�;�j𽚅���<�̳���E�����K'��h����ćܞ؂̟[g��;͘���߷N����y�0�@�y�m<u"�_\�$i`ֺg!�ǳ�	�At�}��6WU�J�{�2VD�ѫ�����LXQ��0�RF<Q'���46����F�4V5k�C����F�[��,
}k�W�t����gDr�+j��$Mm=�f���0e�ߑ�ԀM�.��@��
HE�&�c�t����Mb�	mpy�b����e�[��`'{F��;Of�S:��+��q	�v#4F�Cv�W�[��
��Bі肀K�=f���`����&�F��G�H�`�X>.Hkbr~�n$�~Z�iJ'��?ek�1�M|��Xbe�n�)C6�S^����C͚x*q)D�, �M�"�|���"PP���+�]��q�NK��t��8	��<����d�ծm�3<�.҈��b�9��՚��	��N{��-������v��P)$@�g�CN�x$�/a�m��r��N��aB���0!mF���7�V@LAk⊦���yA��˻�T�ȔG�Ǿ�s��%�'���e-�|0��������$8��JC�䐾j����Y%$�j
d6B���>а���}�IM@�AS�q��D7�JTWC�o��c���+�b��v���R�����:���"kD�a�;��΋���J?9�i~�y�Q.Y�=��dXh.��)gJ��z�� ]�v{�*�'�Y��o�Us��۷���/L��ФhB��6m!�~G�+o�X ]�b��R��h#�y�*/q*5�tP�j�m��
��%���f�� ����j�L����(��('�؋��9zBv��w�7��9Qg�˶{0=8��}Y��L.�������!��G��~(�B�~����y?Ѧ�E_M��z+*�u���B(��3"t�=��m�v&U{�[�h����c����#����!��c^�����r������zePヴ�t��	���?C�vI=a�GO_4�T^�he�d�&H$�#�h���Z����rp��d�Ǣ�*�}�qP�� �&T�!�t`Euk�K^k���W�~S��!T�H�(�� 0����8�o`*'<2�\z�f%F"��p���^�_!�0�.A.��BS����\%��U�i:�5�Q얘��n#����aPc�Fd��Rj����ci�$i-�Y?4㷻�,0���mU�P�~a�X�O����҉�b�p����'Bկ�tD���Y)�p�H���*�h�4-k�+�Թ+�`Q�Ae��7kUv�`�'��*�ӂ?I �~;FF�(�:���`�Y#��bܳ$�Q���D+���n ]D9�P�RF����8���.�41�E�@K������*By)E��z�P�`a��5Z	���%��!�8�p�Xo�4�Fl�	zʰ\��qnzrq8��p+tղ�-9���B6e_��ı�Y�.��a�K2��m�`6��^�J}dx��>++vo
�� ����؛܌��eQ������N:gJ`�w-mS�5�!��F��`�?의t2��-��̂얾za�!W���\�:�jV�p����P=N,ؤ��B/4��~g~#�;��ߋ� WR"]$.@5
�V]�I�u,���tY��u �0�o!?�/�����4�W�6�����=�H��Z��?x�n���\���/t>C�c^r8W��o߰NM��AA�6k�c�q4G&��S���L������.Q^��P����Nm�������	*�L[�y�=i+��M(���e�uV��,�8�Z�{Y�{p*1���f�3�&O�R���	��)�d�N�yo`���Ǟ�A#N�'��� h��
������U�#yU�QA,�G��w�)����!�.L?jMɖ��{�^Y����p��&����V���w��M���w���⻞@���.Ea��.1?��f���Hq�����	�]��mC/��������+s��Y�sy-�b�����f��,�K��A#ц��k�S7F{R⛽$�6ý�b{�W%�t䤑P����E�L�J��*��W#��'c��L�RM5N���o�\�¡��9���7�dAy��MgG��X�鶣2�BD�S�0�t�V�e"kq���4"N�A,��^���I*��B��o_%����+U��.�`�9s|#o��.8@�x�w�Q8����h���iN�Ёi(@��Jr��f�SB�۰y�̸c0��[�1]3�|MK���h`���)y�����K��ha~�����t��׿󒢶��U�(���,�����6e:�V�8�ȥ�P�t5�D+S��D��f�ӋWY3��`9F���a�,̡�أ�X CR�4Õ4R$Ѷ�������t���@��5��ZA�6/�,��ė���<�F^��.r��+(��A�G�)\{B[�}�3&Q�����P8+��0e�4�4����^�� �;�Z�(����*��6��<��I���{��ֺ�i_ly"�����z���v��3�oܥ)�9�g�9l��@g2�.��tlH���V��/�=���H����12]���U/�?�ċ��(w��Jit����M�#���Ţ���z1@�
�[t��l�0	f0��of�*�SD��\y�0���-0�_g�E/#w��WB?�v�P�A�=/�.Ŧ�E4�IF8
;)�!�q=[�Ր��Ҭ��֫��Am/v���Q���T�U�͹+�qG�m.�ʠ�_$`e�r� ������=����m]S�f ��/�Sx���>I����&�k\�/�Y�Uy_1 �فG	4�V1ǣ ��M	y�$}W:�x=�!#��M�۠�V-��N����؁g�5S��2� ��If['1Ec9$Wv9�Zr��u�e��PF.H������˲޼��
))�}�&#�AC��"���V��G�he�ߚ;TI�X���6ر�kW1��C�}B�i0�)��1�2c�n+;��`����<��S��Am���_t�@gȗ��/gc�B��q�	�V����*��q������~-Z|���w@�tQ7�4(E�꒫���؏Q���
s/s�/@Y��Q|
��Έ#w��iKa&K(�~��S���kT��
.�x��x~m�y��d�>�q["��*�������醩g�Lϐ��	�5���'d������r�V"�p��)N⣷�j9����J��򯽷���Oaݍ/��YЛ����B?�ÙSDU P{=/MG?}Q�τ��$��1�_-ug�Ջ�ޛ����M��ӳ��'r�]�T��Z|�>�Ȇ��*�����u\����Br����=���Cx˝:ܖ^u�x�9�����v�))x]\Fo���h���@ \I��4!"Mƽ6�s��-���6G�$�6�v��~��{�<�
h9N��Ծ�*�6cѺ$��8��vP�K������M	��\A3Y:��+�{}�ȝ��àZ�L��:���s����MF��+#�0w�٭�2L��3�+p�|�*�]>���9��"
�dQJ�����L���p��|�v��'�eF:U#A�����(��%�)TC�@�X���5�P�^G�[#�i
ə�MoNI�k�ߞ}�?��"�XH ՠ�+8�Fx����;9�L��1��(�b.&�64�LmZ8Ljm�)���Kug� p��bA�W8�n�b�b���)�RG��В�u������)�7���u�1�AA�!��ւ�K��J���m�&���Zw eni���YZn>�З����]���1K�B��T����M�x͈�\Tc��ڟD��:9F����k�?�T�A�G��S�?����_�Ǉ �G;f��]��9LPֆn����.�pP4EJ�k瞮Ez������M��yǁ��.i��5�5�-A���Vҕ��6�yQ�ڙd4[���&�Qf�>�_N�*O
�*�*���6��V�`�U3��.�!�e�B����b�S���M�oi�ϩ��J�d���L�.8�l91�
�L�*�Z��`I���c�[�-ϒ�.�T8�w��^!ɦ��b�/T�H,	N@kҷ�З�r��UR���6�t"�eU��Y��-˪���]Z���!�P�
9R��[Z��ԗ��Q��c��^�
�ʂN�k����ы���j�aWu�hm�2LP�*�2��b��e�FK��0�l��K���G��G�D5�4^��ou���oa��I����*�B#�E?�������ar׷}Ѕ��p1��5��,�5(�/��Q��j���j�#i9?��Ks��j<Q!�j�_�`�"�`J�(��-Z�ݕ3l� V�;��o�%������1ш��S��N�Z�\/�]��'9�s�ʬf	�h�������]����r02Af�5}�����D@��2Y��ڿA�Jѡ�t��Q;�o��h�q1��Et�>��F��)k�\�g�aF��b]^,6�7\���$O� {�],��B|�p�swu���ea
d�,��}s������S��L��6���>�����e�O[S�
��C��"�M�Eз�y^Qq)L-[?L��t�N��{���OI����X��V�id�3�S����=F�ړY��b�\mޮxW_'�Bx8=�j�o��T�����ƥmo�Aއ��a{���Co���_%3�R�1�iyv|sΉ���k�E^��p.m��2���%s?Rxvhѐ��!��iq�Ԅ��G�����a¹���˿�����r��4�D�XҮ�ݝ�	f�A!V�"?۹�c.�=�І�/R�mXq��[��m	s/ t��Zճr*Bw�}����^
�^���u `�s�z2�G�ʹ��� �飔���^���:���nW`{l9��ޔ�ۓdg1E�e��s�˽���.��d �x�B0Bߍ�B�Fu��h �h�h����녝��O��bGn�
G:�D��s4��sU��y=����k� ������e]3��Q&v�Ho&d�:at�z�TC����x�C|�z^�6��?Y�2�.���Ld����a�EC5�� �!`����A��|Q '�+x�����pr�����>��iPM*��B��MZx�&Cr�8w�����r���$0u���A�6��s"*�s����:�������IT��S���/��'N�$x��i}'������y��W�N��PK3  c L��J    �,  �   d10 - Copy (12).zip�  AE	 ���҃W�<�-��[2��\)_M 9�K��dz^jb�3|�D��\F��7V4759��E4ţD�As������K��!� �Q�s���>{A�E���\<��r
J��~�X��@�,\�]�aYc�;���(w�
���>hcj&�$�cZR�yO�H����� ���j[������ZSZF 2s �pr��O(��z��r+�*�QXM���D�e��Io<�� 4/M�݅rѤå���ٝ_���ϳ��|�s��k����ڜ�^,޸�x��Ʀ^��ZfHwz�I�����Oվ0��K7v�H@�<6�Qqk�7��j��Fb��N���c_�z±Lt�G~q�!v���->-Yb���<��S�(�v�^y�@#,�y�Y�u���c#g%fra�6���Ư��w���]���*Ȫ�	�R[d��Pq׵�6���f2΃h�k��#ړ��+�ᇀ�e�	�>q���*�x���|7��zW~])��p!����oo�����(!X�?��;>�ZE�
,Q����#�/5ERBl��֖2�=ۼ��i����/dŢ'r&��Y۩�`.��e2|,������t-+3���{k!�QL ���R�<�dQu��&�|l^��͊Hob��*;��E�5!]��Eb;�
��}*�wVa�}�T<�Z�����pg��2�s4.!�+:·I�3�7���GSZ���s�Dr�e�:�����i%�%w��*B��Ċ>��b3�#BO�!�U�f�eR��!�f��Xe�v�l!�a�q$�a~2��K�r�t��+����X���qh�n�����870�"��
Ԣ"��@8�zD���(3g�*�!�� �FAdv7 �?:$#��,��1�W62�]�N���Ļ�[}8`F���=s�}����-�u����6�l��78ZG�d�����~���;���*P�����j�����&}��"�%J�����LSx��,a�̕��8�~����X]�9/������. �jЛڒ�0`��<�����#��*t�K��{OѬػ��3�U��D ��P���AV	 V�#OV��Cϫ����հ���	����3ŏ(�h!ű�<�a���f{��ߜ����gZ��.�-,���=I�'����$f�qC�|�8�
�W<l�p���^�f)�O:P׀$��<ِ8!P���|��<0��!���rC�Vx~��-��W�R5��l����Y
��Iy�]��ā^��9-CC@/Hd��D�+j:doE��f�)�{_~���DOKtx��w&4��Z^8S�s�� j]�A⽸�����m�k�s{��2�Mj�9o��+�;�\ �)���'�>e��U;���d���rPG���1���T�vQ���[P�n����=�y\���\��e�[C�[b��?n?E'��������`�oj�^��9�Vɬ� Zk.��M�ݓ�X���r���`�������3��v��،�A���%�|����聿�������pX�;��(c��ģSuO�{hB2Қ�5�Ib2`�u�C`��f�$��jK��m�v��h62.�.N���ш�w�o}2[��{��=do����.��uxD<-͆ث�١?�ѱ~������?��?Z@��b� r��X'�
��K��f��&n�ed]�GB��T7>�t��6�č�`K�,��k�R�v�?[
�ɘ�\�U8.F�I��-p�ܞ�e�L���rkt��ڦ	9$Gg$Y|�MͿ�����A�l����  ̈p�� �w�M��^����w��w��Z x���&�S�
֑M���O<�]AQhD���=t��
ՌN��zy��'�:_�M���(�u�j����Ϭ�j���2(�1t�R\�l�9���[�����ףUZ����yҵ�#y���jH#m��_KZ���q��đ^]��|>H�+�)����ѯ����ԽH7m������z�5͚��8gDW1^&{7ڀ�uÞa��8]Ê��9��d��=�+z��p� �Q�q�8,C✚@g&��7����2{J�A�B��/G	#b�n�
��#U�M&��HT3Tv�u�4,?
EMጟL|�w&�����K��!A[��b@��\eETg��'j�q���T�چ炙��a]�!�}�u�?;�A~lKs5�,�)�>���D��"$Դ�q��x�IS�CG7�t�-Sͼt��ࢌ<hA���N�O}�.���x����\��}8���K�����F����Uw]�y�ա�N��P%�!�lb+i�p5�h��p� w� ��0��`c�������:�N��e�6Yg�jţbtF��0IN�5��!쏄��/�㶄�vOr���>�R �H��*���1vפ?��`�v���uz����2~��������q�\��WJzc��?��E{�(�(��$�A�0����n�bX�-�`U-eA� �q^n:���[ft5���nE�c>z'󣪳������Q� R|kT�Љ�5��ܩ�=T�t�FN�?�z6���*AsX���$���G�Q,� P@ ��x���z^]u68�ϣ]�mZ�׊�o¯YNzgߘ�F���í�e�pm�q)�9��뫋]`P�C���^��|s�F&���ϐ?�qP
�:�ɉ�A��!�� �y(-���͈������[�T�Z�e��ab�U�����F:��|���zgr�!��%'ϫ�ů��yA��9�b.^�{�t���lq�+���5���m��n� �f� ��Ih�h���1���/b>z��z��ܸ�K_|��촃�gl�fʣ�_DI��` 6֍��6�<���DޡP���3�qA?�Ӷ��b!tBS�����;�{Bi-�75�+PrG���W�&�='r��rD��.��&h��N�j����� �~1/K?��$E��Kw
�ɐ��I�b�����Q(8	3n3gC \
	A�n���i�$��i)Q�����|\X9�J>�i\��C����l��2km�9fO�Nџ���Z����� �V�����k��˸�g-���ؔ�^\;L9ìӵ@�L�f��ۙ��t5����a)[���ͻs%�e��L"��mJ-1�z�K``�a�m#4v�&�;JǤ�	�zt�Nqܾ̑aM�d�f�xa5e�0�u��G�|�V���'y�xB�<u��M����d,:v�L�|n�v7��3�j++�SӦ��SH�6}��l���7��X�~@��A�,A	�a���4a�Ap��B��;;�:�p�""������=jUi��j�)��=ߤ
l��4I�&���}�o��!�>}��%�*��uK"����!�~�v{�F?�7���a�Li}��>h�י\n��w�gi�3���i�u^�N�\��-6��G��ߙ~Xw�)@i��sȚo1c48`�z�ģ��W����M.8�- ��
��x�d<�:�� Z���Ȏ�` �4��@Q��s�5{`����'��a.('�2ہQL�(�|e\N��*F���������qPi���ڢ��-�Y�n+�ղ��uc�e'tQf��րK�xQ��Y�T��+� t%�����;M:��<���r�2�y��l�^�F�j<�l���D�ha�+U=�y�Y �.��Q	Nz)��'O8T�����Y5��j�A��A���e�}G��=�*I��h�B���ws�@�u�|���A!8�	Z��n�+�p�nc�H�����d0騇hp؎iH���N[2�%�,In�fJ��c�lN[%Q
��h�ޭ!�Z�->�-�KM^�`j�C�h��ڤ	����_��k��z��y��'!]92���=>�*������y+�
�4�Bj�/���k�yrX]T�n'kZ�Nh[b ~b� tɠ&��.�:�y\�S���h��+�q����t_��>�2s�sB����jZ6Ɨ��#�"�O�Kc�q��C.�<���I�;�ܵ�)�c3yM����&W�J�0 �e�F9�G��Jkc/�'i!�N����S2�peH�s��]F�W���H�����"f(;L��(�8�f�'+E&MKi��e�����NiT^�|�#��mL�-�8p7�S��4GQ�h#�ᰦ	�"Òw��9��2���8����Kܐ��\�����38M�v���$�Q��%,^�R�~V�؀@w���M�����,�QM~#�g�&K[t�d�W��7���:�A4�a4&'�-�+jH˽Pj��,jO�<P���H���qoQ�c��p���
�:�"	p��~?��VB��h�;�.�l#��;��GF�(/&�_��m��P6�˷�8e���A�dq�۩����r����>�K9d��8Ѓx
wm����\�O,�<��(��"1B@��]�@˘f�=��Ȝ�̬�3\��������Ԕ�˨��~bj�&`=DM�]�m��Q0��9O%VHnxzZ���=g���Ё�J�f��ȳ&����A�u����I�Mj��_Ș䍡 �G8W�%e�Y�%�/j���oϛ�ʫ'��r�>�W���|��9���+�!+�� �7�Y<'D�xP�,��z�n�Ğ���B�e1PD�F��1��>��&I����e��� �H5��噸�@3
�=�i	 �CV�����/�*�"����yF�,���h���4 �Q	���O$��n�2�HG��4�mBtEc�Σ��.�v���+\��lc��%�?���D��3��}ρ|�%�X��"��FEs� �0�EӠI�s��O�C@S�e�'� ��~6�����k-�#ڹ:�o-C��9�w�o;�?G���ok�t�}����Wm��~ʴc,���pJL&�ئ"���BHu�,>B��gH�����X��mw����S5��Led�6<�<�ɨV�b���@�ծA�n;��}MYx����j��Z��Q���h��15��Ow�mcD�߯��<��c��^��7y�?�4w^%t/�=YX!��_�+�z�����_�'O�n��J�$%z�R�_���6��RRp�<T�=�A��5�E��Zc�=z�P��V���.�0AC��9&Kg1M��:=P�}bA�H�����Q�Ht7���^�Ɍ6���!z��äëp!��0�p�?u�؛-'��'��f�;E�8ו,_��}WANo:��$�|�4F�:9���dP�S�C��b�����X��쉆��2`	��Ԟ~�D����I_<< �5�u��j��P�?&�kqo�^�����送�&	��ք6���)}t�
�v -X����L�l�=�=�pׯ.�u��"TU�F��d���Jև�TS����\�
���|֩ ��6��ˣS�����z�{�+�W�y
��sUo��6{]���ý6n�b�Fv},�{k��T�f�D�5�'�5A6�	ͩ���nY��	C�P�kdw�����z\D5)�x"=�L����zG�
X�x<d�O�O�8VT��jJ^K�p�t'�X}�y�"�h�W{8�i~6� u	����+�h�5��!�ۏ��Im�m�Q_�1�tթ�H1_���1z�x�1�)�r����'�uAj�<Ka���ߴV�+��y��:Aj��D�ؙ(��}�U���A�3��N�6��D෥m$^.�`�#x��G�p)@PG.�E��ە��4�*�
�BQ+�����8Δ�u=u�|@F�e�o�bw�@@��8���!��m��S��W ���8�O��/-�^t���W%+ʆf�������v���q�Z<�mQs���F �],_���A1������G��n� h�~*��'2�����
D>�2�b�^"-Z�j��Z�9њ�i��I��V�:Oz�JuŰ��c���t)'�J��["�J.T�#9���z���J���������V`��;�	%-�O�����:���?��G�D��V�e�^M1j��$�i�l�r��I��DO`�)2�Y�yЊ
��]]Ɍ�ݬZ��k�Ӯ�\_�C�մ�B5�N������ֈl��fUP�>UǓ���/0Y��H�E��]7P�H/��Td�DNqd�j5�faQ��L
�[Zf�x?�o�,�-�1$��c�8ς��zY�,�5��J�0
����&��j6���Z�nq�D����X����4�*��k�@��	�A�z��#��ޑ��7�sQ	��0���?�c�d��x��H����"3�b�(k>5�~�*���'����2)�Ï<��b�����~<����"V��S�5�%�`^�q�]����d�dήy����^?�9�)H��C���,$5f�EP��^04��]*>��FdYȗ8�x6�uT)	��MJ%�O ��_P�k)��laV�BH�����k�B��>:��R�A�I��.ho/cbng^_�g�4�S ی����&S�[��ʲ���>Y�z�A����%�&;���n�c��\tXkӺd,~N,|^j�:R�ٹ��+U-����WK��d�����u���nV��G���[�9�R6W�u[$��r<	b�!�$kj��@01Lr��*�e�_ ��H���~ƿ�+�[o����@tǺN�����������h:��ʤ��e��Ʉ�N�0�� ��.�3O�G�%>������9��
`��"�#��iČKs0�@023�X7�	��L��]	%p�C":����"{߇���d隸�X!��{Ţ�WQӗ:�o#�a�l,�1&�(�$�zB�P�=�s.c���*��nՏ�V�o��ŏ4=�H�;S�gMy��͕��'9�>8H@]�<��P='�V�::�b"/,��D^����e��j$3v`�����<��"g�=u��8A+& �fn���g���w������L2��O���ԠFEK[SF����2nX	nŏ���ߧXe�*wc�zPq�0��%_ħ曵�y�,G}��>�~~�0��~����M.denn�-OW�t��^hPB���g� 1i�I<�I�w杼[+��	��T����%�f,�^�Z�Y�_j(�bL��K���� ��M��� �|��/o.Q/${*�G#��2�cV8�U���k���~�PVq (9�2�NNuG"���v�z�DW�\����zd���
 �43:�\�7�	�-��m84JՈr�p�g��8Pݾfbu9�Ɋ�;�zh�Q�x+;CG1\Xv{)Y�g�\S�'��u���w0E�_�=/��8��,��F�4\����W��9��9���M~�F�ͳ�J_בp�ݭ� �[�|!���4�/+Xx���d��Jb�f	�d��۝���h���w�}:����@���G �f7�ԱK��a�o(���g��9�P6K�gH?�wk+���|�I�-�����A����/��*��z8z,���W+�-�~[>Pw�(�
��'���׷�9i�y`]�gG(;���UH��|��x3�#΀.�s�%��Hɵ����?��͋���f4�I�Ʊㆀ����Cw���뷉��2qڬ�
-�V=�'T[�Ow�s=�	[Gf�:&=P���� esgɜ�ks�~�u%��U�]�r�r
im��Mx��X�=9?A��S:P�!�a�{��<e���^?����m��J9��(w�7�ul(4$�]"'���$FƤ��Y;\��П%�7M2Ε\�� ?�j��u˄\��l�D�W[D��>�	��{�K�JVxs�����R9�T���s*<����RQ=D9�'�~�$���ľ<	�W�d�K��m#����J,25�����_ظ�?�XN���h$���<��3[�	���-��x��L�����j�N�N�t�T���.����J7�	"M��7^�f!fa��ӆ�}�>�u<�?hz���e�D��� ������w�IWp��в�'ճ �4�� W�.�h��H�1��қ�D?����!~���җ�Y�\v�\g(���ܖ橦 � $�=U-��}�Vx����Q�d����k2�u��E�z��՘-��X4i�]p�fX���\4��#A�/+ا�#����ű�w�A��2�Tp������h����@x�&8��m:�`�\�kH��7��+Xә�D��ݳ���%�:b)�ͺ�T�>��U��n	o5	;���|�>��λS����Ws��|V����*B�C4x��A*�������
��S�W�Pd@�������{8IN���gs���������^j�u������T�`�[��~v�@�t-tD�X}��)���qi�"��Ǜ�Vz���R��sy��ˢ���{rv�9f���	o���m!���oYE���f��S�0Ͽu���}ҵ?����e.$��m��F#
�q���Y��|����S��xJ�
���.P��H��5ߦʍ�q��tf[=�Ǧ�PᣀFG�+v8ӌ�:8{��}��B���M�������;�H��h 7��$���� ��3�s�'V*��g�O�x��-��^�q(���	�۬�[�BXe:�J�r+��6�74����Hk]����2�o�]������ҏ���$�菝�73��9��0��a�٣���M�&*�O��|@Dd����߁ȕ5Z�I���v�)taY�e��جc��������j�2`O��� {���besENP/��S�S1?a�땹;�w3S����_�ǁw_�b�!O�q5�+�j����z@�\7�>/|,�TF�;~���&f�K���� �xri�E��߃	��ƺH���ơ(��+,��)��x���W{2
�sr���8,;�'.ʐ> T�"��R*����h��n@l���[�ٱ�qT�����
�Mq;	}3�X[�v�RAN�Av[|����L�������]J�^��G���l����h+?�r�?&X����r��i��b{������`6�8���-�������/	�S���<����Eٝ��Rz'`�p_�ƼEf%,P����
�� Z��Ȋ��!a)����1=�����C���Y�I�Č�6�}&�i^f��!�ꅱC�k|��kkO����j,yR��B��紤��=�UsjO�WbS������F6DF��'�օ�<�+Z~&ѻQ :W��e�7��������#����>纙ݚ��2k����*r���5��(^���-�G����?��CQ+����Ŕ5!�/�#�n@l�� ��,��c���Va�O͠����s��Gb��J����.�8�V�e�i����!?N�l�
�.??~��k+��?t֧D���I�č�.�-bz9گ��R3R8�����w�ƿ�J��w�6U��+euצ�*9{n�֠ 9��)���_�*[���)�ieg&�����m�f�=	R����t�3��n�Z�\1�.J}SH�W��>"�0j�����eN�E@�(�֬P e_v�-��I@��0'8�NQ�j�]x!��z�#���z��؂U�Xj�1%ׄ/���C>���M'��ԐkQ��vY���ly�L��w���;�m�A�xR�(b�)-�Sk
�k���_yQ��$ʀbߏ�Qr������g���m��f\��1�+շ�]w���@@V�b�!)K2�iw8BڙQBt��\�"���Q��3yvZ� ���8�TXX���Q<V���/�0�>]��l���W�_i�p��T����L�B�d�������o�\���Ɏӷ�V
_�]DO�e�ܴ}SF�8 ���54n%��Z�U6���Kg��$���K[�w�&ע��֧�8�.�ȟ�.�!��س8�G���f��/o�����- 	R�Hi�� �8W�E�(�&�G���K��l��J�d�ŋݳ\mr�{���:��T�Q�.V��P��ڍ�(]i@y�tMY����r�UM,?cK�>�:���r	K���k��9�{�<L،�?��7���ﺷ3ڿ�A���~(��w#�_r-�خ��v�b�����?��g��j������'����q�-��(��U�hR}���t:!�c�>�K�D�d*C�X��W�:�i4f����eZ%Uh�_LJc����V�KL$�&�������d3� ���ґ9�w*��H$�t��`��C�+g� ��(���S��L��J��G!hz�BO�d���aO�������<1�*X w�e�U�p]��-�L�'��-�����d�:p�k�18F��̮�|�u����2�l��c`�Д���8�����+�,f���}�r5_��[�>��Iw:��]f��D��[�g���1�1��y�]��O���2.Q(z0��0
�1��kfyE�R���]��UJ���ڻ��3�xEj�xsA�di���4y�x�539hȼ{Xn|�lG��<^�!���xcI+�U�N����������$��ڜ�x��l�����Y!���c#^l͕ȩ�Z����#��Gv�=E,��ʋ����c!~۩	R�u���뜫����4���u��K�m2�ga��:p���SQ�-�FNi��� �4@�{�I�5�*�$�)��Lՠ���8����ۜ��itv�-���k��ヿ<p�NiW���/7�l����@p)�6��횽}!b�.'+�94u�}%GU�y=�	�	� O�M�=�"Of��%QL�_+�fӘ��Ķ��8�,D�I钢�F�O,/K
���;�=�k����k��%�H�ռ�G�'_Q�5!T��G�!�>�R��ﭭ,GPt�V���]נg�yc��8E�+�'���%-��y�e�3#mHj�m� qg���O�}1nBD*a�Cw���~46���6�-U�6	:�)ӅT�^��魶��J@���~6�ò,�Ew��N	P�(���Fz݂m;�b���*8��YI̐��%��R�'���*���D�,se>@�g���\�F�H�O����M��p������$���h(1��K;G�~n���X&�[�y� #����R������9��)dJ*��v(�#��=H5� ���03�����TN��ت�ơ��rOxyL��sBs��{���A|��2y��tE����l�С��ه��~f�	��f�^S9v���*A��dd��Q���Q:�{G����<Z�!�n�,DC:���K��S��'��O2%dbm�v{���2_���k��3���DGv���[��N�7�u���������c��Hs�1�VQn?�ꩪ���i�>����0�D�����?PK3  c L��J    �,  �   d10 - Copy (13).zip�  AE	 m�����-� � �X�Gw���$�N�:������"�-����nY�_Z����9������}!�'�*j�&� ��.����p<1�%s���7\�:0���<t��?6c�������:�VU��Z-ɀ��u���5ɔ��jكF�
uT;�W%S�E�0`��.�f&)��T���r蚗D��-VQV��˚}�Y���9�ѝ5Ⱥ$�V��`��^71I���y2
X�T|l���� c�Ç� ۆ���s�����6�ک�;�=���Go�ÿ�{�M������{3���J�x�l���`dq�rK��Wvv�7�u���n�慃���$���/�~�	�-��!��Lk���hq�NK����sbP��f�*d����;�&|Zc�GQ�d���e�uP6�h~���A�g�[Ƴtι�(�5yn����ݬ�	o��I�3���{�h�����U7��Gd\PRs�Tʍ�a�j�J��������:�CL�T�.���s�Y���]���6t6K�W�fŗ  �+n��.��!���K�g��D^#odD�4�l&kC<�V����WA�a}Bg���\Tv6��Ҩ;A���Fj����~�����l�|���_c"�y���E�L�2�������u��Yrs�b�n>����k-D֟� ���/�W�4��yBVW��,Z݄�n�����Ի'��"X����E�I@R���lf�����<����W'ޟ�GV���J	�Aw%�J�tC�����z�Z��M	 ���a�R�-��,�!�O5�了�n��I���WK�z�fp2(G��]�	�(��Ϋ~]Rъ�a���ǆ&��w�� O,T`�N�+Iu�Z�wd�4��S{Aaa�B�/�����Z�l>�����RC��/'�8��)W*,�Z|��ه)��9�9���J��=�-VAS��뙩K���5�q���b�����3�S��we9��Ư[ox��2աP_O�6�Y3�3z��,�vZe�ʷ���A�侥
ř�lզ�"����W�8[6��:�GU$�Gmt.~|2d7�l�Ux�x�~���X�v�]�c#Bfu��!��C�����J{4��������3�>�s��n�GVn3躭0��w��  �Y�~8p�
W�^}�W.����������.+>��ްk�qjӽ��� �).n�dT����rYֵ̓c�<�����ܓP�b
pQ�4�U�Kz��x��"x,a��@3lD�[p�[ ��vQ0ۄ+�r�?iNä������I[�O�l�$�Ka�fj.�h�,��L�NѼ<���� �xG�[��6-��W��2�d��%�̱����{���X,{��U�KX�y�B� R*9�XJr�/�\ls�xƚM�ђ��6|��z�h(7V��P���~��╺7���F1C��!dF��욇�C���
�l��Y����iqWO�1~;w+�����;�_4v4�^�v���q�n�����\��,ʰ�Iy�cYӈ��%q�q�m�+mԅ�~-�X�r���X�/��N�C�^h0D��q��h���G� �v�Mʉ T���_�a�<g���%LuF�����L(;��\P}g�V.����߀�OT+O�Cr�ck��X{	k6X���s�1\^�������&f
"r�����������π���|p�)0;�c�ši���.�Ex��(`�P����$��дW���u�UI\d����39�o��YbT��P�V]��N|]ld�Cg���@m�X�ђ�
��4����4�4�Iip�2�۵��%:Wٓp v#~	pԅ�!���4�H���^��𠐣5�1[g�(Z��
�eq���P�2�S����*��g�	N$��11�e�����*�@�U("�xۑ�c�%>�X��� /㚣>��m�T���W'QJ�r�p�-^�=4ͱ7��钄H�`+�ʰ�aR,���E�o�2��S�4�Ű��`:���#ю��1��2��}���Qr��'N�x�s<��bF�J�b��X�j��RP��W��	�2���Щ�| �ue�"x!���~�f�PrUW��vfi�FnB'վ��4�c��yZ�𷠗Y1����V���/tM�w������$�k����S�=U���]�#�<���GBG����8��$�u7��tf� ia�?o����2�ʅ~������/�j>ϩ�}���H�*M��D��� ���+�{�@�@-XQb����)����JL��=�(�R�~ꑇ�.=^�\�"�-N4�=\E�X�7�5�	7`�1�����)���8M�����ƽ+�tQ�_���bO̶�&3���1>*_�J���:��0Si�'?�X�r�ׇvslr��p����;8w����R�I5�y����+KH�ʣJ�l�w>�Ȕ%��j^���g�~g�a5"��|��xTx�L�.�!h�{	ڻmTAN�i����2C\�T�^�w�Lb�q�+������M��G�:��=�qJ�7t��1H���ï�;�zk�3��҄]A,��0���hޘ�/@��թ�AA�U�Ö36���UXJ^U	|��R�L���k5�RUG[������42�������~	L IBy�ɋQٛ�3�3P�)�
�T�H��?#�C (���}�w����9s|������:����_pd���$̴|5M�n;�6?A
L��\8d����c����u�@�8lk$C��l�d����(LK%ĺ�%��t{�u+	7����
p�[8��h�Y�2�J��F��Y8�W©�jz�/%�\	Lo��9���"��{�م}3��`o�AS�ya������SQ#��Gօ�]Ǔ�~�_�3�v(�R�)�dF���)�6�_ke��wb���QoW�L��X.-��̈�� �fM&|G�өu޼s������MBp�bA񼖺"sF����冹�|�Y��\���=Oۘ�@��o ��w�U5�ܸs�4x};��kem]�l�|;U1vA�X����U��z!�� '[!�@vSX����0�S�@�rq8��~��Mc��zO�M,�&�D�W��왝:Á�+ud����J�Lk ��^����_�L�)d�v��g\#k��P�i��2v��mzB�Ϥa��^'[W��� Ǳmh�ȝ�8��[ڱY���[
8�e	K:����Vo��`48#l3��E�=t�?��;ՕCd�QV��ӕݳD�Ņ����,�$a�c?;�]%#mI�\l�1o5���$�����*��W}���s�W��!�vb�6R���k(k���yDz���/�ޞ�W�3�1�Q����3i�&��s͕���B�@ܛ�wT5�.cp��Y���.��&p@��s�.����������X@��������_;���2(YM��������Mg��{PP1k�q�R�O@רƳ���A,Yؘ�NIr%��D�&�<(I��9c�r���_�Ћ��|�(���� �x�!�M��#l�� |��Xu�RQ����/�\����B�{3��8�bN�>��[}�?Hq���k���a�:I9dKDE�`�����ƣ 5���:>�ʻ*E��)r4Vy��8�l4?[�8��%*4_��6Ԑ;�%*���w���
��r*(�j��� 5��t�0l=� �E����{x�ZS�n���ͻA�����tB61����zFa�!tLu�4@��j��ь����x8O�{��&Z�/�m�!�Nc�W/?�#d!����U���rHG"�y2���'a뙢�꽉Ly�j�:�>~�.
UlD�\��s�ņC��q�`)Fv�Jv:�����߇�M澬�/9jF�*������>S%yDľ����<wt1%5��c�+A�:w��tH�����i/[{Y�ى��P0�b7椈5��ʩ�25;��?�>��.��C�
��zB��e����n��A��&��9?��Pq����sp�� ���*����"u]�yN�7��DʅU�i�"�܅xT-`)X8��;E!���Ѷ�Q}|��aaA �Zv�ok���́�$ y{���@����W&.p��Po��D�l���S!i$�3C�J���4DE�ˉ�ڑX�JW�+z������n�m`e,��	!]�*��#:���V=����Q��-䠛��|�&�x���ޱ����)�5��Ұث��=��Z�$��h��{����Jǲx�#)��nɃ��n�-��W�¹W����"w}�f12������]ߩ�/��C���me*O��m�P�Ff'����@�����{j�(ߘ��͠І,�jEI-� 8����1r)ĉ�l��� �]��B2�g?{�`���y��:�r"ť�C>���!Q��C�����b�L��(b�E3��TM��u��"�[� r����ޭqj��6�ܧ�ա�L@�����u��A�����������f�̝ «�h��&�I�,�0�[g^�P���8+�R���nOP��j�zuv^B�6��z��Ma�?Kb��\�}��"�IؽT\&�s��/��w��\�����q�����P447֜��@� )"*Bc�坫f�U�=�[����b�A	�\|�.�͌���,_i�^�`y�ݴ㬦X��®;��3k��`��sGȾ
�3
 �������z��m�Iై+Q�_���Q+~���$��L�X%����ӎI�ѭ�{K�)o�],��_������.�D���(�.��`��ԏ:Tyk�qG����I�Ű�~ݵ$g|����=
��)~�#'oTF�哗U�c�F3�X�����,�{E���6T�Q>(�Ҏ��nY�Za#���]\�����U��%��i!]�$~��@2����1��B�9t���:T��}�j���f;|"��	T��:��
.@o]�;k��#��T*���R"��9Xː�#��+)����ٙ����o�n/|�]���/c�dT�������c�E9�׷ޛZP$B��7��_*$�K�4�y�&iQ�6՘�b"��/�| w�1x�(R����M���d�>|��(�����q������M�D,��nS��Yc�!c�"A�[�w��nI�=/r�8C��A������z�ק8� ��B�ЯB���P{?'�������H3XQ��`K2���2wF�� VS�Ѓ�����Ι'ݮo�b.����˵��Ex5�5�88i�q��* #NY�H�bC��j�p��)��fIE�,@	*��꾯˳k���
��QN��ݕ���{�=#��>Ƚ���V"#SB-���n��Ra;Cpā�Ξ,N����k�o���=�����W0�V$Oz��b�GU ��t|i���;3s�~�����ҫ�-�|���m|�P�6�KQ�.!��Ѹ;}����zHjn9����y����0�cM�Y�j�]K��i�����i�r١�DW`�D��Sְjh��&��w�ڂE��?���#��7d:��0��ױ��ō@#컣� �h��(feCz���q����QZ�������*���A��_�{�`�p�w�+&~�4D���Q��M�v{���ϳ��K:<E��(<��&�z�/+�h7l��)^�+��8�*�Զ-����M�z����́!�ó�o��c'����'^ ���c��i��S�f�"n�ކ:�߄�w:'g*�n�k�����z�hԤr�'�𣽽U�6�9~�-DٷT����e��� X/[@i�s��h���해�'~Kvb�	cJ>���6������w8�[U'>��]6��冰B��ƽA/��C^4���줭xg��||SΫ�>�."�@��;5��[V�[�"�|�L2&����vg�g�tPd4G�X��B�����"���u������5M�|�)=<2�1P�M��c!j��Q�r�׫�[��D'��r�O&dV�B��ƨpS��Y�k:+l���@�ZP��k�v���}�in7?��x&��h�C��z�j���@҄�PP�y��鐼]�� ��\�8ot���[g<>�E��Z��R���8���M�6-��� ��½���SF�B�Ǫ�����?'����zPKQ���gD+�2�C���:Ѱ�&d����tkɅ`w�{`$$�"o =t�`"h[_*t�
ꏫ��8��1�X&`���/0�����]F��$�Q@!6��=�V|M�n�&�z�lc�ڈ�kfV�d��͂����T���H�A�X�YGԕ2���gk�5��ݞ���$��� a2��1��Į]Y̔$�� �yĝ���H��m�kx[lh��YT�l��AU�0��\�q�Y�~ص}��{i�Jc���/Ъ��q�ǯ�	��X8���7X��X�W���k++��x+!:}��u��NHm���*>�Ɍ~>��npD�8��X��p'��4M�-��`�"�#�j=�2*N:vY��^/C%Eg:,J�9�7w%�qMx&Ah!�{9����'@��	��{�O�d��rY�#�=9$,�6:�4(�o2� ��ļ�����/O�Ŷ{�}mm��M����R�1h�X&�L:^?ɟ���S�r�:���@��X�ZL@G�;�!%�闢]e �N|\��^?������{R��d�P�O�X� ��i���)𢎿�����-M�$�"�qY�����b�> �����Mp~�*��
d6�س`�-hs�U�?�覧��Ke�M��!�Iz�9B �w��ʍ�R $
-!�W?�%z����� ���+���{-G����h��H� �_�U�C)�֛�]�脦���Yہgβ����A��+���S@!*H2����M0x���7Fbw����|N`�3B�*���>�Y��J< =�+��}�$�·�k�}u���T�>����N(����#���hY��A�#mm���t:��g�|�0"���nLB���|GgZ���띾/8J�x��a򅙴c���ꇧ K��L�!J�:J"�?S�H
C�Mr~��(KB����;����v �����5x�3ڲ���o8���	�I��D)A��m�g �
�G��j���z���G�S9��Z�d���g{E#�����<����^��-/�.GH9��'�ڧ�,|���zP�ύi�?��%�����خOG�փ�ia*Ht��_��})���)T��B�Ԗ1.�)���6��J����Iy��j�Bt*؏&�J���!�?��ic�ܤ�A�\�����@%��NW/�1Q�a����ue��5��\��΁5���b�D��nY�p̿���ls4YfI�/Y�J+����]&�����R�x�������xiO�X��V���3�h�:���R ����3X��rޚ!}��
7�h��{�T��}��ɻ`���EV[�l4w'Q�8�d32��;6$�����&�}X��FD�{i!A|�wU��+ϘVd���Q�쬵��Vz xYlh�*�wzzܮ�g�,�xd�(���;Y��8�2q���03�	�sҗa���b����T;��f����Lښ{}�eL�q��:,���)BWG1���	>��{r�T΍LH�Akj6	�e���
P�&,識56h��*0���}��b �I8%�"���&G��c��-v�x����?M?Du��gYQX޶�����5d���쇹�����GJ1
�:�g	vj���y�C��m,��}g��l+�Qg!?dP4Ӎ�ƅ�`�>.�|��-L�>cD༙B�C�,�ܒ�:��NX���)���߻�.B����;3hS�,����zw$h���^iR�̿H-(th��Vr���X�%.������4��w�{���7��
Q����@-���=��W��BL��P��yh��2����ð9����d{C�?]&��j�t(����r�����òC~A�p�[����^�&����0���:S���A��Fp�e��B�>�r�i��@��K	�U�c��2���6����)��N�����P4@_�Rrj�{��i���X�����I��x'��Z�`�SwC���kh�~B��R� � 9��9��R"���VQ�Cޟ�F��Tw܃�P��� ���OF�W����Hu����P[8 /Ob- ��̡aq�|6=�_����[��8��z|J����9��'Rg?#(^�=�5l�El�Z�����{���C�l��oi��Э��Sގ$Tʙ��N!#����|��w���?5�U�z1W�05]�DK��0�%FD`$�@�7�,��wJ0b��'k8O����Ey_���QS�3�dؒ�g7�lUG������~o��Q�.'aL��BS�����(�w=���*k7Y9�'�[uva.�ӌ��
қ����*�l��j����I�����b�,���h���7�����L$�#�S/X�t
qT��$�N�Ë����r\�&���=�q�����b�S� �[&�5�t�\h Z��j>D`��c��B-2X4m�S� μH�`��
��ۓk�����Hn��#M�����!b����������g����s�6��P.�+Y�|��_����\6e.�'oڤ=��a��z��[s�� ����j�γ����������iW/���E��"F5 mT��Y�����V�*�>�9X��,��k��p[F��e{��C���� ��&;w��C�L�6���I�vROM�������iC��߰����� �
b^�"��!���e� �s	2�����]q�ė��靬r
��erIne�M��;O�T�B�`�Su���eݘ?z���_8p,/0�/�`w���sU�����8�:�R�:�aXo�0�Am�}ֿ�,�����w+��"������g@m�r���U�� r,�� D����oZ����x�
�������BZ�Q�]�oܒ�B�0��Q���ͻ��s��$�����)�1����i>O_^q!�$Gr\o@[�DQ�� ��	���P�Z�=��{I�؁	�����C,=e<�m��V�3���I��L���t
͞2}��$'���mu�������HCKt^R�"�J��s���;�l�q��|��PԄ�	J3�
?�Mr5�����>�P�p6�q3�b��N�c~�Hu
�reV��l^=0�9�+����t��w�N�,xiL�)�O���^�!��ɜmV߱��N�f)��l�bͼ��,�f���P��s�,����5)E�:� �(E*mzW��M���J�+���<�	(������ߟ���:ao��Ghֱ�m��mKW��wn kX�u�++%b���n����b[��l��6���e�؍�l�H2R�I�:
5;O�o�8�!	�"���xǀ�ch�t0��P%�d鮪�j���;f�]q�?
���Zy/�_�0�_����D����l��F��m���,C���ʍ6��q���!����	�� R���͕gー@�?Y�%�a^�N&�W����w�`" �*:yP9bЪꀦ�7�Ж��s���C�����޳�iJݶ}��Hig}|��B�����8e��	�([YD*+����<�?����-U*:��<�B�V�^���#q�z�U��w���%�BO|V��W������{�h���"w�ۦ�{Q��8s�Pא!JHJlUw&D���\�- {�8�m��2�����Z�zc�{d�i"|#1�)wi\��&�z���'���]���n�f&��_/n;��8�V)9S`P��%������y ����4��7�D �o�"�g��O)�����>�}�1O�w�J��S����ޤ�no e{�q�("ll
a�� �%1-R�s��]�3�RPA�R�Xi����9d����ʆ Z��B�q�;l��X�F:�g_d߸S�4��-;B�D�N�Hm�e�Q*�� \;#�M�bQ�概=�mD����� �|�{"�n��fXI�o{
�
 ,V�7ޥ� :av�'��X4�t�Su�{`F5�n�-]��+}�}���Fg8U����L��.�gj/�D�~��qȩS���6>����X,3������9.���.�<�1*��K�+�9���6q�H����,*��
��g�������q���c��I)y�-�ƟHo�f�3ʋ���}@���Ǧv�=	�����Z�b�"ȝ��7}]j�o�NOv��pjܙ�Q���N��f��������o�7��d�v:k����5K�c�&?yCF���z<�6����q��V>���:�#t�F{�h\��Q��}/��/�	w?�@�w��[��P6=����7�M�]`H��[���*��oz�B�EM���e��T���w%|��I��ڽ;$��#�&��P���h_dO*l�C7G�%d.08k�ۍ��u����^!��z+����h�f�<P��¦F ��3M�P����n98xK!bE���3�Y^�s���yz^��=�f1vN�������\3���U��S���~���S~�ڢ��  n�pp�ujх��98��F`�A
�φ�㺢��8��(�o�o	�i8<9l�?�kz�	�M�}�/{�4��wv쑺�rS�H���t�
�����3�Z��Ł�V߉�{ّUr-/
4m#E������(��/2������;�|<૽
u�Hj��e`yjt�b���
���RB���(ڣ��:�I�{�c�)&f� �٪	�a�r\��7���u�)��H3�VO��ŝ�i#�3��BM;cC� ���B��U� ����KG?� ���N�Nݵ���K�����������u�C}�1
�GhƸn^�A����R�r�۰d�r+�ܔ}Z{���}��f ���ª.��	IR����&���i�����I�Dk�|�x�uk��?�۞e+����V��q��� ͈%M�޹3���,L�X�mf��LӽHO�Q��9�yWS1��x�J�	/�ݡ���D��j���*��M���������o�#���5&�����?�ĭ�=>���x�wҸ��(Y�R.Ch�ah�Q�6<�C�Dۑg�@Ԇ R���Ah6��%�g�~yXގ��V�t�۞ɰ<X��d��A��\�߄��ѥ뇿
�w^E���7j5��m'�Cj�c	{�1��ۥ�UZǜ���hӝ�C=��9h��B���J�]�ˀL��[�r���oZ�D�PK3  c L��J    �,  �   d10 - Copy (14).zip�  AE	 �I�B&:��Fɼ�I�v-�? @�s��tq5OQ����Ş #u���j�V�s�|�i���T8t��i�*���;C�`��?ČVj�J�z�\�T��ς�L��9�>l���-)��!ԧݎ�IHk׷��--�?���a��3��[W	���sf82{�zձq~۾�6@�ܔ�ǭ�S��V�� �@��=Ќ�Ir���C>�quiwv@櫺�v�ۜ#U�Ũw�v��2N]-�O�۞���8�A`[�ʖg6�J�L��P����bd�@��@�t���"�_��u���@��$����c�AqM�z�wZ�3�$�=�����A^׿sA���GJ�� ~�D���A ������5��CA�*��Jq���4�	ݹ���?�ȼ)��a�RVܠ�aи��xPFoϨ�NdҌ\b�ef�)S!����������O�[�W��/��ȴ@�7>W��C@��E �c�&g ��oK�����%����|�k�FӠ��B�f�eu
�Z���eJ
���/�P�2+��6��$�u����y����\�w���P��G]���	`������1�>�pƀ���o��u�0?X��P]/�ץ�_�@�ee�K������,x9��P�v��t�{7H8~,��ʵMy��]��U-��*D
ܹ=�=����n��z}z-_|]5�#��,���L����G�,�b;�>��́o
L\�\��9ҿ�̸�e�|"7��e-�4P�{s㴔�Խ����T[��Tn85P����9�%}�I��*o�5�
��2{E�/��?���q�;�_���jx�c��0L;XA{;Ӭ�'���J�o�d�`��岃 ��{KhY}�(��Na�ܒp��	fb���rØOC�R��_��m"�PD��j��Y��&$�2�[^����޾�)dQ-�`��������k���HoRmnhV�}AIsΤl��+�X����7L���MҜK}v�S�D�:<��N?�ádY��3b{�`��^�/� k�8�.*�å�8���n������g ����|H�!d���)��!�8�(<!��2��du��O�a��#2�OmYi������ $TU��̷��`Izำؤ%D]�e8q��(�)�߾JJQL�=K�����%�|kHfE���#���I.txf���쮹�ٶV �ȡA�M�q��M�6l��+Y����T�V�w���+�H��S`�X�k���Q/qv�mK��ؚ2��]�&����/��t}%�sZu;��8zp����>�^��!?8�v��e�.}9y{����Vb�љS�d���/]��|o�j���D��s�D텀 ,l���klJ�ξV�����ˎ�&9��2���@�?��^���e��%l��dG�(�ʜ�\^�)�b/�S [%�u�l��U/).0$�����mAG�/���B�-�N����FF;m���/�h���)ӣ�5�VJ���7�2�=�=�y�O��J}�{X�u�
�iM"�5:��'�$�.�ʑ�~EƖN��y5�E�m6���>�:����C�9Q$th���/���oΪ	lOc�<�[��oZ���=,M�e8��	p��|c��+��5��P_�lr��h�e�vo����gDq���z�*Cw.i��JV�g�h��Τ�C����S���(yjOs�KkC
;9_.lK�䀗py�h\�U�|V�	82F�˴8��j�����ʓ ��LK��Z�b�4 ��BH,SS�[�W��C��:��S��ܷ6 �����֊�έǉ_��x/��M�P{��ӏ��@G6'RL����ڀb�8ټ��ʏ-�d�hdk�}�B�lt>�d	�Tp�أ���gY��i35���3��T9 ;F�8�<������D�D��Q��i�i*�'��f��v�EXʔV��q}#�~�U��]���.�̞�W`��By�թK�J��@k�ŕ(���~/�(��(�*�"�YE�C�:NS�qx�Ih�1�\-�Z@fΔ#퐉���G��f�-%��sA9'���K|=��x��䫝�8K���%�r�^��&�=���1�ɖc	�*��@7����z4��F^x��
?���?n13������N��"��o$�+/�����X��&������/�y��8"?#o`�4#� ���
���(��e���I��M�7-�J�,V��/H�&e,��N+]!3���u�%>swv�NQ'�\%Qѓu�|�wX�z���Fe��:q~>��0""z�Q���OÓ���u��s��*|!�\��]�����YzyUT��<u�&f��k���Iɺ60�,��rc���#a�i�%����S����B<y���&nT����t=Þ�7h��o���Zy"�2RQE*e���/8�]_�_3�zZ�Z��v��q����2@n��a�I�b��V�s�0mDF{��!�T�t����U��&m�!䵪�*^/���Bb�7��n�g����!9kj{��ǃ�mF׋1�ɣL�G�k�&}�gpDi�=qa�+��N�WO�O�i��`5� ��m6>��k����*|�:�aH��\F��:�N�鑢N��I�l'G��P+�{��*DI�I��ܹX��S�����Y>V���˯B��~��x��Ԩ_�8@���pw�+���1�}Ρ�9��J|���6*]�����;Dz�O$�g��ġod�A�|X|I"?:;%�M��'���9؁��Zf�����5HB�х��b����|�Lz|�R%�|g���F�g�.+��F���k)��-&��'���ǯ�[tD6s�΢�Q����5H���A�3@���jY���$�r��~��pɨ�{ ����/��O�½�9k&���5�j��Sm-,�����d���8��?'#� �����W,l3�
j�ƿ4,f���ǰ��xk� �s���!���'G�$��T� ��6|��L�ϑ�g�vZ��Cր)��[~�O�wex���Ȣ��o����uׅAf��g���lp.���$ެ��6���ƈ��u�N���/������ ���`C�E��T��PY�d�L��g�a��㤻��3x�t��n�9����8K���\��
�a�;#��W���tJs���f�~pZ��xP� �� s�Z�L��BiX@>B����W�G���t��].~j�ٻ���k�k|ʄ��b�&!V3�DT3�7t���z��*n������i#�nÈ$j73qp�_ F�H��!;���^kʁ�|�J(ei��/�!�E?��5]J�kV�oS`tlG��y���T�;T�t���I��7�J1�F�7'�X�@ZH,��N�
����)�t�E��\x�)a���j"�s�I�~��@�,�k���ǿ˷鰹�I���c^;9&|�X�@-�b'H�\�?lŖ�-^H�d������f��0��-�
�׹X�"���m�:	�ҫ���f��Dp��F2�zѭͱ���WD���������Pw[�#��v������&C!�`�I�-x-�M�ŷ��\��0N�3[��f���Ir'�N0c;�M���Tsb?5H6� ;�,�x������?A�����ƕ�X"x�n�.|k�E��F��g 뉭��&�Z�?��+��I%���a䴽��X�`���A�i�U4$���9�;M��	�q�!���ɢjI"M�94���o@��o�=�����T���[kӓ,��t�}�t�J�w\��=6����E��*?���LW����X&\��B[w�_�����-h�}���I�=�/`�)`��i'f�tðb|t����ط��K# D�\/A+1ī� K��5W-�B��G�� �uO����{4�n����),<^���RaG�=ښ(�G�T�*���ʬ?����O?rZ�>���U������4��M����O�u%2�A���C����s�xCW'�T�A���_�@S�˥�k?�s��<2�E�|\2���|�u�[B�(M��<n,��j;����l�񆾒[�ѧlp��ĦU�Ҵ;*"P4��A�yPx|������|�X,��Ʋs�U�Y��H�~��r𣫈n.��4�9`�;l9�(�ȟ�[ja1��%��+�o��	�<�} �r�B}�s�\�oɋy��os�Q^�Q����B�	B�u���L|�<sT��F4�  ]��nK�d��h03�B��ڳ���]9tO�
&�!��i#��z��.)�^��]��C�n�f\�4����u=%��*TS
�m�$G&����c8_['�~�� � w��-��8QP3;���K��cQ7���	8��i���;.���܅��&5!G���T�S���~��I�V�¦�EM�ɞ�깪X��DK"ǉ@�
�t����>�m~� �;����a��µ8��M��
 �|wYY)!l��}ןkk�'a�d���ĔxB��o��T΢�%�I7^ǦM]����g�aUXL���G�;9�}dS�\�(�V	��H�;r(�]7��P�rߌ�Zx�@Χ�����Z\�JU𚺕f՗�6��C�u.�2��r�ê�Z�xZ\�٪,�Wv�_���ox���.*4P6�DS	���d�¼5�T���h�X�|-�w(Y�+qr;���������a��]�T�-�Y��,�/�+����>?2�DG��g[W�M���8����^�ل7�t�K�(@���YP�l&
��,s��h��g���_�ݏab�Y_T��B�O�.g�i��jȼ�:�*VO��i��èD�me[Oc�H^�d� ks�_��O Ƴ2��>��Q��}����������V��L��ʾcϬp p� �NHk�b~����~��:�o[������!ӝW9���G���>�[�RK2ZW�[gdh���+z�qD��=ӂs�G���F&�﮸)& P�@�t ��
SZj�Ut�&�u�X��9)z�i�W�|�=m�C�����A6I�����-�i��s�ߋ��*���4�����;���&C2�m���a3k� �O�xU�RP�	�Jpӄ��Q������d1)�gW��R���Q�&iX�0�j�(���n��I��g��uR�ڶ�� ���l4�w�m� 3ƨz�}���Sq�y���d�
��ɼ0�L�?;�<@�i��S]���uL|d`���������:�U�ŞY��Ei�z�Z!����n�c�J:�{�R�iN@����8���#L7J.۱���f�JF�q� 5ɦLW�r��Ir�3#�1$�ѷ�0M�sך�}��Uϟ ήuن�3р��
�%��Lb�?���L���R���9dd!��x]w��`�+��!�YojD)L��		?�վ��ӫ��ǐw�E���v������X27�!r��:j�M�r�ʏv��Vo�JW�Kړ8܍�l�e�J�L�ӡ���0���1������4���06�{�>b�Ù?t?"\Uqq���ב��&8X�M�������@'���t��Z�&�u_��T��ѥc���{�R��*!s��7r����_w��K
��9�G�൥��TYn���d�\�58U�2�nzB� ��Up(h?>X��{�eD�/��N4�:�޹}�6�CF?�D-׫�:it���;�И�zT���*��tl��6r
���2��;a
�e��*��	��RX��
�oI��(���x�L�� �8g00Z~�&��ϲ�����Qj ��%1�vʄ�'B(+�R����s���Ѝ:!�[2�1�y��HM:�:��T=�F/��Y]r��4ǐ�IS�z��L]�%��"�²�j3����1��N�"X^��tȄFrD��@w����e�|�y|�q�g��}��21�Xd��p��+O�]��.�*!I8p�]�\����*I�����ͼg�a��ɬDO��n��{N^`yt��~g�Q��|n��ť�Q]��p]����cħk>�7~�n��v����<Sـ�} {��hz�R� ����G���_Ϥ�o/A�9��r�<���V삃�a;�j��+����[4�J4������.oy)a�d���>D���U^8d�R�f(�b߿����G ��T ���S�y����� $}���p2b�j��� Q�?t-gl�	�U�HƖ&v�}
K�{QݡRc,@���5WYj:IS<�xq�cǰ|/�&X���k�ʢǚ�% q�h	,v5�ETA�@�Ӗ�����Y�(��3�<ڶ���ɘ�W肒���`*�֜���p�
e@�����:b�7:�;�sO��^�bJ����d���h|��z�
��S���	�30�!b��[]N0��I�ܯ[����<�`��b�n�G[1@��i��c���B��&Rjw��6R���]oG����:���& ��_��\G�������\�ɹ�r��*���%�*w�.���ȶ3�v����p���
 �� 1,�G����}�Ǩ?���G����n�_�cu�c�mc�.�m��"%�$��t�IY���۸���'�v`V�t�:N�G[�[�"����+�Hm�V���'���6�β!@ZQ'����u>�>ywg��Z���n`,�p��f���|��r�'3`C��d��� �~e�
|�`�43���F�d��A΋0dH�*KM]J'�a���.W�_v���ټЉ}�7�����N���GS�h���S�Cgx|�Z�]��e�?_�I[��X<���Vt�����iĂ��G-"8��Q�6ԺM��v94��N�Q�{c�<�3l�ׯ�Q��8=p��OzO�%3l���g����T��c]@#%z�~X�D�Jy��9p{����/�W|����8��e�4d�g�N����4p'�7t�Y���%b�`�D�0��9�nM�h萷E�B��qq�S
�IR;@��8~w./�(x�8��Wb$��OK���\�E�5j��A�~���5g۫NΛY�����|�'`�/��n�޹`.o�F��2�\UP�@��	���X)$���gT^Dx�����\���[�<Q� @ړ_ե���<�O���DW�kUL�b*�E�[5+rBi7��h�o�{�$�ӎd���-�an�� ��1�̼�#n�[��4�@9'�>�֧w+�E��d-�'JHm�6~�z����;�D����j�H��b���0h��=������bN���-0���b��!MiI�.BA�A�{�SHA����1�--���F�t��qX�CV�yB�p1)��|}�O�Y%S��]�g��eֿI1I;ם�1���9&-��u�{&�eC�ɢ�TY)�X���{F[�sz���TeC9@�goǪ�z�=�aꙥ�,[�9��_�k��ϴ䳶�S�j�L��)�T��z�����wrGq��1ڎ�m(#
�ɼ�<�� �-�U��.d:,�x\>>�uۥ�,R-1�7F^-WR�ȩ�[ۡ�����rCI\3�n7op)���.����mM�����Ӑ�O��m� �����+ܸ���'�����I��Lak�S��M�/FQ� �!�!����՝%�W��6B��	[�Ir�� ��h���:C&��V�Gq��KXN��|m8��푊�]:,�fp� kVLw�e<�R��M�/n�5{e��p���'�������U��������-|�E?�kPHa�i��u8e3�Ĭݚ+�Y��"y'��Ff۲��0�G� ��ˊ�\�!o�;'3���(UEi���/�`d%�����2u*2!��liR���n�B�!&�9�&�#y�[+�x	���TF����Q�$�y��@�6����=+ �[��t؎���i�)��L���NR���-,>8&�H�#%�����3a:J��_�O��y��v���-�yɔ$٢����}?��?ANR[�i���Pn�-�����P,�W��}��mZ�j���"2�-��c&^�a�����7d���n�j�(��	�&��B�Ke�ˇ�9Z��Z�14�L^�w˦jW�0�J�~��S��v(�jK�����z�����$��g�nw��)9C�!Y�&���{[��
�f�8��_��wC���AL�8�9�E�g儾
�A��'.wo��0.)��T)WD�ο�-��7X�:�d���	�������r�J�]V7���;��;�+�k"��mjEd�L�5�ЬF�>4䌸j=y�$kNX�봰�j�u�;�m�3��*j�u�&MWṥ����.�R���K�R|mB_I��6Y�٦��Sט�0�e��U�,�e�m;a�_���ad�N�g��n;`Q[*�����v/�o�?�OVOT��_���oTXG`G���[�
���!�
�q@5cpQ�e�-'l��X�n�����-�gk\T���X�S�V�"�eQnDR����b���TQg	~XRFŽ��&�?�:|t<���S�5���2��|z����E�xI����X=�^����	��g�&Zx��!T��%$N��̹�j�f�f���~	�����a��7!g��r�x Ĭw����s1��QE\�+!P����WV�tc�i�CZ/)��&���G�t���2�5�ԙˠ�ډ?KM�Vm�2���h��=�^Qhk��6�td73	^�&��(���a��
b����	��k�7�~�m�V������\qz���\��b\y7`S��\��D;0�1U���L<�D��'�z�T)	�� 9�eW+[�t�|!���x�	�&��A6�*l� �ނ��C��{2r|�m�>���4�V��âZL+��I9�c��G�[�#5Td19��>�sz��oA 1��Suz�.*��9I�m�c�gO�ٵ�˰M�}��{Ye�|X��5�s�&W���4x��Ա���򐌢�p��r�n�A�Dqc- ����O������D���#)'7�����6(�v��|�Y�k�F1��"�;��[��a͔�����%+��v!R��~��~%�eI�cø�M���u��C���'�}q���J���*�7��g�����_���g�^N!h/�.��Ϫ�d��})��ң��ژȘ�m8��Y�q�D�[/}��C.�A"� ��Yn3|�2��K�f��������3�&���i�VNm�_՗,�8�k
h�����@=<ҶƱJ�T�t��tA��]i�_<�镏��U2�]�$��%�R�";=�i2��^���f�'�"p�R�06�hA�'Dq�5t�Z��P�t�n��mܝ�p���}Z}����O�k*�"��{}ҡ���/�b�Y���ON���*t@�h��m�������q�9�*tQmRQ'�٦��ӹ=�M�>��7N<��&��Z3�o��sx�k����wyޥ䒈�,u���S�yBA!AٚTu��h�S��v&�|ЌK���O��j�x�k�c��ݢ��������+J��k8;�V������&o��fҀ�_����a=��O��s-��"R� �m��j�P��G]��(Lq���雅��{��>(/���������[�m�P��h^@&���K�uzNd�z_T*����E#X��o�B1�H4�qch�,��DSA��W=�75P�<��$"��
��3~Kn��%F�`#M3ȿۂ��P[�+hÌ8� M�'����V�j(�W�)D��s��jܗ�S����6%5��cr�B�f�cl�E%L�e?A/sX,Pl=��J�J=|� ,gh�{�%������S�����4//v��������|��݁�ld�N�����<��s�]�1������i.� �/ 'X�%�s2���LѶ���E�kN�B���ɯ8 �`	d?��9�H@e��P_�;!���u���{��..�9�$#�G]X]�@�%�����5�+F\s�r]��/����5���b:�������Q�V/]Xy��آ�P�`o-�y��c�MS��k��Rl�8�wY~o1↪D��+�X!R�7b8gt���&:�i*=d\W_���&��`�
*��R*�]ь�3�<��٬Xw�Z~c3itrY�p�bC̉6^��.��8��O
��Ci�?�a��V�g��88T)|�6^�b_�4�[�� ���ES�j^ifDD��PC\��㊄AP�$��#E<j�Y�B�F�~)�!L����&��N�X:�<S�)I�{��]����vD;\S{q[L�X�U ��3���Ř:l�
e��r�����v�5�������Y�g1,���mVŎqp��h̽8/�*�_(�H��M��Q=�Pd����bdStm�a\U["'Gٔq�YcI��xA"t*?�^���M�[����Ѷ2CΑ���Պ�V�nJ��/��?�(�;�1��*�4���n.��pKh&���מ6�p���Y�DE�Gx;V-��ZE�V�	���h䩑L�:pڨ�+|�j_:��R{%S;�ܪ��)�H�`1 tS�S�b���+���a4	VR/�����V"�Q�����c^���fp���{F�em��$ ������"������F�{"���$w�ŏR���$��W��ttM���B�[,h�`Kq��H=ܘ�Ucg�x0T�iqZ���j~fUz�[�R�K�Y�����D���2U�F��3���qʾд���߃�
q.�z��'��%��
�4�y6q5-����l�����`?�9�"�uڟ��Z�ƴ�1Ni����Ry��2��TK��Ս_���'*�6$c�F�t�;Z�7B�B����Om�) E_ppJ�Z��0�U����BR�q!��}Y�.:9cS�b�C�ߟ���qy��֮����,*}�]���͓�����`/��� �N�]�m��,�����RiJHT�e`�+�N㉽��g1�]6g�6[���חiUƥ����-�U_�bY�BT��6���_�<���^��7r	<8Y��|�u���t�Xe~zwk�J���<SL�P��=��!���H��G�����+�,�,P�Y8�ޣ%��Rs�$5�OL�o���Z^��=����r(���&�b���v!���U�cO�Vl3Z+3�' B��Y0R���Z+��N���Ӑ^��@/��1�HC��<��0�v�g ��D"<
�7h]�:� �J���`iQ����l�Q�?۩��Uk��ʩ�w\Ţ��C ��w]�F�D��$q8Lh!�I,���n���X��Kq��K��	�*��nH)�9R��ݨ��ѥ��T�H�!8����E�(������IE��a�?�umD��l/=b4�[��	aB�ѸX�ms\��Q��2�v �q:��z^1&�j�؛q:��PK3  c L��J    �,  �   d10 - Copy (15).zip�  AE	 �S���\�j����TK�^����n�}ƃ_����,%7�� �+}F�
q��U.��ZA�Q�d�;�!�y�{r��E0T��W�2Wo�~�^��?��ീ��ҷWr5�^՜!<#I�`���n:t#W{�e0��"xA,v�_�S���m�eȥ���%�"�,�h*3@q�>�z�u���"�d˿g�9.	z���dx�]�E���w_XE)�>\�P9��� �`�����D��vʛ��{Ii	۬���֫g��������Z�o
��	\��擊��&kY� $����h�����e�z�2��A�q���-h��"�Zs&��tD��a_��|D�#��U2�_�\CaFk�qxؕ:�Sk^(�Բ;-�_P�O�q�#�YΚb.��� �ԍ�l�ڛ�(��F������I����L\��rnovX[���Hd��^���b7MKΖ������1����u躗eX�����/U�Z!��Hq�^���7	�j���X��6׹$��~�)exL�cu��3��-�s�/�X��X��N.�ݠ�L�8��1�L�T���d�'&}Ƨ㱭+"�Gtf.d��N���9�Ij���&�ێ�=�#���qe���F4H(���QK`�H��N1���%�~�k��ˡK��F���uI�<U$�� fHjU�p��i(�
���B\����;�g��Y�wI�'{��5:��:s�<��~t���:�5d�]�	i�b �W�շk�ɞ�nd��܆�.�M��}]��|�5y9�Qw�؟���ܟ0"35���T�)�bֶij���#���W�Kա�=
ҴS.����1A?QBp?��Kf�A���\q��P�R��b�a~�[_��d=��p���������OIzdǏ���s-d���2���"Q&+����bsC3�!����4~�P�r��9�ڡ����}g�k1�����6�/K��e������[�CJ&��U3��C7Z�{�D����ܠ��Oui�����xP�E���CF�y]�"Ƚ���h�MG�X��A0Cہ����">#F�%,~�VM$�no̳V����n"�-��a$aW'��ͻV��K(����M�ߝ5"E��>�F2��1!N[s�	�#>�DGwQ��^w��$Q��A�x݁xEƇ&m��9�B�RKDsxG ����6sc{?��l��	�R�B{@�����x̨�r�Ϲ���T�����t4M�w�r���!�L�G*�����G�mT���t�e}:���@�p�k��m��$KI
�� ��g� �K�zT1C �1�n��o�E����g�NX�i:�}$�a�yj�NP<Rgb<��p]y��SđxH]�u��7��R��>���ޘl�,�җ
�U�8	�_T1�.���RY�G�l^�oX�6]������Dh���d�IY���L[�ob�K�ѯ�vP���ث�Lf��v:�~���u�W�a
�6��h2�}�҄PnN #C杳�9G�e���u�0{=�8��P6nR��&�y�[d;W{Bw��-�����;>��|���*�nϓ��{����So��JVJEB��5���3����qxo��浝��f�0	ԌZ�tX��.����,tܔ������1Q��l�0��̍>&�
��*m�),�u)��s2�������Qb�h�!����	�C�u~DEk�&?��j��t
�������r�^"wLR��N�A��C]vHTr��|椲�tX����v�`y�;ue4�s���,�)8�(^_���Blc�R�ۇCo�+�t]i�F~J۟S�e�-�g ̋���h=Y�dӞ��ls
Z���*�R�_um=�&,+�m5R>&��Q��0��!���v�~Q�>��~9�����	��Ԣ�Q�HY��k�e����r��s0���x��P7C�����Pa�-�|�9�ؼC�g���A����]�{P,f*)o��ᠴ��~�z�k!��)��;��!��g����V�N�Bt��E���7�O�ه.�WL�x���o�ȼ���x�ͮ�K �^@�?K��^N������W���.��JN͌IZg�g�Z?��6����b·S'x#k>���s�+���$_&�΄P�(a�T��GS���Ae~͉��7��Xj~i��T ����wq��ξ`�0�L]����hm�&5�Kc���Tx�Ç��w8����|��m�wv��Q�RļB=K�f8��Ns^�r��U�m��A>�JA��#�h��m
Nhl�7���0�F���X^-;NM/�6IR�!kSU�ƙ+�exC��V����]���2OzΆ(���I[x$m�S(�����o"��)�ԍL L����WH"&U��V`᎕�K��Wu/�C}}Ⱦ�?N���Z@�pI��W:��*&B5��Bk �#���'gU(g�pW��j�D����;3c��&��<p|�
������x��r�6�<<��	�yZa�/��nU���Z"�s��
Pq; �ƃ[��T��b�0�0��qn����V=����"�Fm����5%lɜ���y`lgix�&���,���Q�l���]�v6�p�Yߩ|(��^��Ηb@kZ�_ᅋ&�V��ι>��Lx�I�?6��_����?�A�/���C$k��1��<FmO2����,hn/��:�O����c�����d�@�>�bA5t$$��g����g����U��.Z�h�
ў��XQ1C���&��s,�ʁ�p�����x�{���^~���cl�=��Q�Wط�o������9u)#����>^I*�h!��J����&ź`�7�c:�}�#�A�<����S�X��H�r�(��8E��Yf�I�����;�w����BǕ��4֥�I(LPO�9��2�W?h�7�w�����
Ր�-`Q?=x�p�/8ޖi'� ��Z;x���/�e�s��R�sO�	�I?����aMYF��n���P�J!*	�Ay5���ҘWpo؟O߶D�V�V����6�T�34F�,.14+�i[V�"ǀ��]�J��Q��
<{w~�YB��`K�1(�w7������P�VШ�c�r,���Ṋ���J-�r�V�����lp�)N4o��M�#�WFJϾ�t���Uy�����&lZ, ��M@^ܒ��wD=$�]a5�n~`D���Ē�O&�����vʝ�����5�Dӑ+ؗi��(�t�- ɦN����
9X�8w�9����U��˒��H�:0���#�GVM���y-�:o�[X�p�I��%�;�ͽ5�Ϭ�ջ��u�_���v�@lAh�� ��!y!Z��n�� �F�����+RJx5������ȬV����?V|���.`������,m�~�KP{-��y�~�hP�=)���[�?1;�$$�����k�g*�-!٢{�*{��L��B��Gi�p����I4���Ȍ�bs�� �C'VʎRc#V���џ��i��A& �������J�<1��G�۲��nO�z3�g�2����w3�2���4�7�c)��#*);)�!��m��C�$x�My��ƴ׼��wG;�S�>����ѻsQ?кϵ\ ���8�$$���)�*��<RǷ���5������/��~���84�et�,L�L�B��Z'0 �Nhi��R�x>��m����%�������Z$C|3l�O�7�SP8��(�r�O�{R���.zj�-i	h#��YZ0� �/�j�{}��/b丛�u&ӄ@�U��rzI%��}k�K�98��G�=�3�5:Z����9u?���S"]����S  ��t<�����#�-�ݚ%ʄ�;�l���	v�)�c�c�����-{�����@Sk{4VW�����`\����l3��{�K,�>Gt�e!P��xwԧ=�.�����q^�YEvlCL��oմ̏=X���C혴Rs���ks�sg��o"���W�9<M(y�����E����@�鋰�}����Z쿮M�v�l�I�-��k_�i�n0j�u��V�mʥ#=J�0�������BUT= >���a�uI��$�)��0MU:�@�11W��%�η>��G�ЩK;�lb��pHG��������g@C���ù�0�xnG���
o��S�Y���@�l����U+9�Ă�3�\����k�ycRԧЎQ84��>��.⸘e!U]��Ր�am�ǠĲd��j'�<�>�|a,��.�P
97=�V���ɐ��x�VJ� �e���l���Z� -��
�3����;y/\7��R"^��K� \H�H�D�����ՍӠ	�<*g�hc�]�� "�s<̟Ad5��4�mi�����T��\�յ/���N�i�e�D��S�[.}�<V���`� o-^HY��3/Ag��HDh9��\�CzO(-��~嶋���� @N��(�DM�[���h����N:�G�f'���B����n�?������	{1^qE���]j"%?���*��(�� ��m�});%g�Ԟ������ygg*�bb(��X�4T��F�Ή�����9�fP�����ȇ�!?r�L�%I',H���7Y��Y
M�c@ Z��ǅV̲�Bl�ŏ���5F�g�R6�m�>��Lۣ�P���po����^<�}�Pe��&8�m	s����wB���OH6J*V��A��h�����I��#|n!p�y�d}�r�sk��%YRf�QVhd�&S�����h����q���-o*��a
��O�hv��z�����U�%�NM��*Ʀ�V)�x��v8����1�A�Zg�I�'R�����v��R�(�l<�m�!(YC��J�O��lp(_V�6��K���c��[�>������ �Tlvr萇��uX[�?c�!MC�"HH���D+�H�`��MA嘈 ���.XY����jd�N{n�����A���3��?��0p��5���/�!sٺ��I�����>� ���Ե���4�ͨW;������pLmT��jޜ78
�֤�;�*",je3KG�H��s<�M�0�R�[�.��N�²��V�[w���y���oZFDPMOj���rΌ��b�	 �ɵ|��X���ⷋ�����X��P�O���q�p����s��X���:����Txf��ȓ�A�;�J�*���%��}�Bٕ�i�e$�kj����,x�FC�{��@�:v��L�@��Z��L!�ÇFJ(q/6�vR�G�a�{��� hI�R�T��
q@�c�/���1�S��)&<&o�����{,tV�{s`�)4fs>�
c#h@�F����/� I�m���7��bhE:�>ӗ�����\vj���uB`�H%7MH4���v⸾�j#c���D�jWҞd.��F!�U핃8zÄU����v������YVH����rT��) �2Tc#�J�
g�sV;?o�Q������u�Ul��a�}�X�4�8̨���+ΝPC�4��'�H��wt�n[ʩz��iM�����2������'��$������,������X&m_�ݝ~
��M��޶��("hćζ �4�ȉ�\��h��x�j�'�4�_�D23?�>���zO��j|�n	{
l�gYf��gPZ�81�吴/	z�!��k��N�/��!E�s�m��V�e��\٧=ȍI�`fc	�W���H�D��XV}d;wU�მ�>1#@Ღei������I�
�؜����o����p�w��wL��^T	�뷮+$ㇲ ����8��&5�
i(#5�Xc��v+�>�Te}��%4�k5���%�>a�y���[{�OI�'^
2��%�]YX"�&�&i�Ơ}�c�^�#���%�_Ϥ�̡�������?��Ce"��ۋ����?S��4\NҠ�K�9���X�9W7� Od��>�G�Ϙ�����L�w�$�1�?��}����N���*(�����{؈�
�9g�zcywc����]b��98�/V�λ-u��R��}}���3�7	���3��|	{8����
ͨ[�Ϫ�!�|�]]�Ѓ��<��͒]�z 1�Vf"�z�#��L�����Vq��20���������&�Z�������h:M�͓J�~ �H�;��j�+��-�=�Gz�
���'����C�[�rd�0g�뮹5k{�7*q!'��:r�3�n�gV�gRЇ�=Y_����;�ؐĭ�	h|{�6�'�P�M8�NY�ϗ�$.`�AE^�_2$̽�8�/��l�Qo�����8j1h�*�ym6�z-47�o��q�2Hi�!9,��6¹�y�#e(�A̟�=�1���Nh)�����~��ٳ�6�i�]����	p���*�6�����5=���g{�3IfJ`H&}۱٦Q?a���	=A���vh�x��&96��z��O�����6��^
�[to�;HM�6���In6bբl�D��w9(Rt���C�m(z��g�Ջ������!�U�A�X��)o��`�L�жD�(OR�S�3���0�ڧƣ1�Էn���X�x��-�&Y	��ˁ����9�n� ��M
[x��+E�?<%�.G���M��o��e;��[��:��Gs���a/@�v�rvEi�� ���<M�q>��}�t�޺TqB.E4�} �K���ӟ�U�%�ݶ��v�	t�C81B���"�y�����h#������e�Ǥ:�v�UR�*�碦˻E�14�y�!^0�W���|���ꏩP"�(�r��8��w��(�<)c��*�[�(��?bf�h�Ņ�coH2���cI	\�M����񋡿଼��M���Y1��_=���J?���9�ۃv��a,l/>�wI��GLGk&m��=|�m=��;%�T�ֺ)�W *�����)Գ�Z+O�L}�&���d�� �V��i�]p
�������L'���M��%T���c}�<��ͩ��v�,	^g����+ct�dʨ�O���q(��@�G��$�x>��!RE��1Y�q�O��������$i���7��>="b�=�:z2����{z�E�����Ap�2����򚈍���{���tw��<����z�9�T"��-��Ao-.�*�s/ $[������������N�v?��2¸6s�PF�Ϛ;p#(Y39���-�R�magL)������؈��j������+��N��R�::T��gW!W��Y&�]hW���'�%A���R�c���/Ip:�CML4Z�nY<��C@qp(��8���}8͎��أyA�����G�<=��jM.�HV�e����r���9�:xq��C6f߉�2���jՠ=�~������/����|��(V�MY�s��g`̮-��~��Ҳ�@I���A�6s�`�WH!OP��<t]L�����L�X�-S��<�O���>�/������Ba���5�Tq�g����>�:���t����CV�o��p����
����:ʫQz��5��ƹ8'��y0�Wf�p��V�[E����ԙwv��L���C���#��7�+ve$=�l�1u�)�K�=�k���dḙ
̗bj��M}�߾��>����EKxa��t�D�a\�-s�&I`O%u�@y���V�o
?���'��?k�.���T�r�PH�i����f��wp0�p��F��.�O��e�3%��s����r[Y�h3L�~��i��[c�[!ʓ8�/"�2Dy��R7zr��*��8��W��-7_cV�w��$a[[I��a�Q
7��*JcvyvTO56W}3����m�s�էF�'�P�-��L�����B�����|�����l�:`mj��14�|]J�V��}~�0��U��J�j�.S�G��/F+<w�ݞ�R��þ~�CTY���bz�5bbZ4~V��
U�p�*y�[((�t��!��]g����ݾ�&!5 +5�v�=��+�3�ݲ�;�jQ��N ����ac��m5 ��T_�����c@���x�L�a�M��<�cٚ����=����|�s�z�A�ƯY��՞��r^��S�_��\��X���Y�������$�����-��;�㮍��w���)W"��t����$�(��/2,h�;T��w�t����g�����E�܎^6�n��,5j�A[�+��q��ݓP[hH!�����c�ά��I4���H��J���v
���2$򘈧�,����F"�080H�XӸH�S��?����!��UŔ�{�6t><����i[�2�ñFS���i����T����^�2����_).���N���8^9�C۔{ �C�u+�#@#K���)x �0��կ�i����|�UO�X"(4�P*���ϳ�����%E!�$˘�@��@vڋ��бX!H�T��^�m�D���[ zlu/>��u�����D�5C_�L���.|ՙHo(E�u}�ػ�-@�d�����k�+4O��z��>�~ꓰ�S �D�����ο��Y9g��;����1�,\��D�eB��i�T�C����|O=R���r�d�A��6�	B Y,؜)-ǟ�� ����&��S��]��o������\ӥ��F�RG��52�O
�;�H^�]ಪ4��Z!9�����\Ĵ���JނB--��&�|�-��`B\0K�f0`�MvL��1��՚���<��{��Z���5�"��,�Ӌ�$��gm<4��N����H9D�B���胇V@
�)���%N��^�$��}�?e��[*�G���a�Òb�^GʢbR�6�n������9�I�rr��߄��^/$�+��t�iMylO3��!v3�m��>�?��a<i�g�l5������x=����E��<��#Py�F�(���DV(2L��X�1�+k�����:CG"�֧�>[����D�Y�|%ɛ�����@CD�	^0�ĥN�#�L1����]ږ�����٧��V�����O��?Ҧ"�U;����y�=��K�GN���rR�Hb���2�)7�� ��ۡ�r�zhI�N����UT��.��6���{k�$JO��	�}����Y�g����GŬ$�Q���\���H���[�)�=yfj#ፀ�tj��	�����U4�g��[���2��\�;`����W��\�~��3�ɔW�QT�=�##�&*
CQ�※��bً�څ������$[��k,���W�Վ�<A�=aG�_]T�^U�� K$���j4���ż�e�hP�������sPB^1�����L�]��23����w�B��K/��A�U��\���loA�8HD�谠��G@Z�6UOm"�ԀR�^/ �,u���q7Ysc��ܸy�];*����VO��������6�sS��Ӻ���[ݒ�T�ž�L���m���,�|}�8|ShȷSf*���T޽Ϯ؋�Т�	ڳB��/c�͍��r�
�L5����a��*���P�wy����v��
��*1I�iȫݦ����+��v�=e�e� 8���R���e+�ԪZ �.x�պ��V����b&�mc�/F�+�$3�Tri�U���c���.�ǗsϵB6��97��*vH7�*te�P������U�]!��4a��6)�.D�qz�l���_J17��E�V������b�C-��Z�(�Z���S���_����	�����s~[���c�3H�d{mwX��?'��'r"�
!�n���^�w,�%��c��z:��M�}r���ʩ���=ك�e	X��[#<<��#�9
�"�.G z�u�ʾ�x*��E�2r¡�^C�ѣ=	ɦA�
�QON���e��c{q�l�uR�{��YO��0L0y�f.x�ĥ��Iֹ٭�t�7ڿ�؀���W�Ȃgd�&���nM�	g\ ���j�ˢ�aG��i��
:��o��Z��o��fhm C�O�!���|�����7`��i�i���
-t�m/��ˊQ�|'[-"Y��[{yJ�� 	�%X2��IlxM8|��S47]���}Vm�?Ki!.�ْp������۶k��Ε�����a��8p�	�c��w-������� %_�@jh [R�\L8�qV�E�(.g5	9����9s��9���+��Z�T�;c���xG� �� ��.�ް���y�}Y�t�Ͼ��X��� �=b�7#�`�׺����r�G�ܬ�F#*I$*���å��8y�zǚuM�fz�~���1|��*p�U+�IW�P�#��﹤�M	�4�O��vs���M��y(b#pQ�xV����x�*g�4z��O�����ٲk�3�os�U��h�p�*�^m6��uK�
L�r�7�[mU�'�}|�GS�Y��PƟ�<B�H~P��$�+�N��cu���y<��/�Lƶ��i��ǘ�:�>~}�1-���\ko\$r�H����t�!K%��>ML��f�(�W/�u����.�}�Q�#s?�A;��{1&��W������� �ܓ���'�ْe�C���w���JUL�G���7Ѿu\ܠ����l�`g�F1��@%��*L�ѩG��VmfcQ�ZvX_8IC����� jm�لʾ����fS暞���m��!�rn\��mC7(f:z5�����^zq%6���sa-�^B�Ons��ib׽|��s
�����C��N��K:X�V:�F⑛cA}�! ��@r�����:M���5��a;�]$�<<�}��n�vjТoF��4]�bu��PS��7����rM<����=��eM���?jK��sNe�?qr卥@��_����H�o���J��������=O�?9c#���f%��i:@�,2�Qp�Q���W��8�0;�����(�������ҫ�p��/\��"$�&Ur�O����*�Ӭ��FH�^)G-��e�Ġ��}4�����t<{2��+���~�#�7�Dt]�;���Qd�4�"u���n��{b��ǫ��-�F��o���nk�(��eʛ�p�]kZ�EAՒ��Ce[R1͏�t�"�,��"�P'��c�y��:�IY+6�fV
��Hс_{y�`g�>�O"�/m���8��Ү\��{�
4 ��F�;I1��v�Έy��8؁��C�cS�,��Y�[-.Ո5o��@�B��nwÛL|l6k�"�?��"��
�׍�N�����T����BV���l,������M��v4Ģ���T���t�e'��Q��q�E��C��a7������v8�bDu�6�_�� ��c���n�q���� ~�����8b�W�~���m��
�V���h�PK3  c L��J    �,  �   d10 - Copy (16).zip�  AE	 ׵����O*��90ޗ'ޮ�@��h�+r$3�^9	@�����}�V�x%�C��ӕ{7��s��v~t�bJqgϢ	-��Q���9�=r�E�Ǟ��!�y���Gp}M��(}��q��{:�܅{^Q�j��^� 0�� ��K��, =��,�����2S�����Q
��G��&�Ĉf4S�G��R����r����F[�j�3�I|�y���a) ����rW���H���L�-�Is9�X��y��Vs΂�돔5�%ơt��/���8/�1&{{���>���S�����N�l�&��9���s_�5���!=pܸ�.%�
N����ɼ�>����'��U���s�؉������=m#_������E�f�T�f0ۗ�.Y��:#�'��[7�2��mƬA��p�Ȳ���;�����~���>�Jmn�OE�:�.�R}�S�č0�oV1J��o��:��h�]7�O�.���/2:U6=ta�uIp�/e8��ZqS`�1pq��i@;˷p��Zd�e_q��P"��#�}��ࡿ�qk�}9��\��9���
�]���-GIl���xj6" ِ�eT'�ԹO�8��qR��O���ǃI')Ѐ��x��a�6��:�ip�B6i��*H�]�I�B��|��p�"�n@`_.%tb����Au����Zh��opMj���T��=̍�A��VIlc�|��<Q�+>�JJ|�����M�t��c`��T�6��%G{C�^ӌ��O���.�ŀ4�l`�x���gm:tz�+[��Շz�����g�C�R��v�R)�a"m\��i����}ͨ�1�Okyє�]�@��'�n���[�U[#ya��rMp,����2D�g6�T1<�;L�(^�~�y�x����?R+̪�ܺ�"��,����5�!�970J0�=���O��r�x�xj@z�����M�u�=r�d���u���/��c��t���@X@�-��1[{&�5�d�>�Q^|C\���@~�%!m`_m'�~��'��������Ч��SR�K'��Za,`�a`]��O>���d��"fK�;�13\+�8�*@
ܽg\v���,���*�4��\rfs��D:�࿃OKԵZC/��l�rU��\n�ĭJ.��mW�u�|�|�a��a*2��]��v-
UX�nڷ�C\δ������6�a�3w��e�A�R�B�&�+8��>.���"�����<�uJ�����;��O���i��+����M"��[z{F��з�U�:_R�4���6[S�/�C������}.����i>���`��Ш)��������t	�d�q�2G�p���������\`#-� l3�v����a6B��h��%r���Ü�6�R�b��A=�!@~����:��z�5��,�Sq��w=Ȧc�D�%�x��X9P�v�c�Z�ܐ|'��+��sG��[|������/c転��` �2�F��(�F�/�u�,]'{H)���|��X�!�/�W����i� -?��Բ޹Ufg}� �m5�F(�� ����.0U�O)oX���꯵j��ل���&P�7��N��@Uf�
�����3�-���?ۼw�A@mQ��ҫ���5d�P�G�,T�}G��%�hR��1�Z�1gi���P!㊯i�<n˨I�*�����6�k�E�Xwt̹�����ֲ4,vPi3I��(��d��Ӵ@"�e�����U�X"}4QrK2w�h�*���?&�Rv���m¡D�)T��v"��ڦI��84䑳{W8w�[(!]Rա��Z����V���}��=	-��qŉ�	���+�TJ0]{V�b������=����r_��q=Bش�A�k�UJb���U�������U��u����n��|���ү��%�4�I�(Hǣa����Ik�3���]�\B�o�����V��Zp,�ۮH�r�����@�3������w�o`������ݦ���@W�v�7اd5fK�X��d�Y��g��h������.�'e����wOƙ)���b�X�jv�����k��WP	3�8TmX�p�#Ha �?G�y��������!F�&�}Θ�!nM��ޜO���SJ��Šb� �D�LI����J1������O��i����w���������ʺS�W��$cx���\�{4N(Ȥ�$�VX���d���h��"���1(��Lql��p���2d�O >˰0B�{���<�����t텻f��[ߙ{!i
8l �LWU M����Ә��,�޶A�-���H\K�D�>Po�#37e���sN����y��x�yf�1���6��
� ����6���)���K�JY�->�_U?;�hk_s���r�L��"3�t�l�嫾�+}�^$׳�W�M�M�Z`��FK�����V/��>/_n�z�ű#?1����m-�AJJ���9�"�ٕr�ʧ��3�Й֟����������L�̏���0��g��T�Ʊ{T�KvM�2��W�`�eĲ���K�����QhA�ڙB�Gw�gu�5gd����ÒfENU�M��2
�-�>0/|�:�a�L�#���6p� �54)�&%�l�@"��F�W֠��Z�[���4��S�Ǽ�)��%k��9��\sݭ ��F#�D�d̉�Z�UR�<�Q������2q;YG��]Hո��y��u\�ĩ���{fnjѪ���zj7�I�KD���x�Aw>���":��/��\�q3tK��{�:�n�7</Ş��E�� � =�|��o���qt��
�O�pO)ˌ5��g6 G����Hp�C�~�X܂ku�_��Е�%����z�C����J^2[��iN�,�����;�Z"�/���Tk�c7��ֹ�0}{KG��g������xo� W�F�ǔ�MǊ��f �JIg�R8 #گ��<Z��;h�S٬n~�l�� �A��/��b|,0Z�ò����������������%�ik?��>�Y�j8Y_5��*�a��9���g7ք2�>f<��GoH��ab_ȴE�׽z�4o5�r�[��|W�e�� �)��y4�tF6���"WL�/�jQwt�4���F�� �٧�j�%��N�)_y^���ܫ�n��5�ވ}�{�ŕF�|�j9�M�z�(�JX�}�
I��-�::ŵ�V�Tvݭ+�K�����Y{E:��W�?�!PW/A�SM1IO�@�2~��[��fN�m.8!��&�7��e�������j�nyuȝs��M:��A�L�ֳ�#�
f�I��ђ� ��{�'�W�k�L�.��!d)+�m�|� �ڈ���U�+SKK�C�5~�V��1�� ��f��|U��a���d�fG�B�X�8����,�W	�/3�~�{߄eo��[,/*����2�u`�C��6�za�6np���޺7�9ǉ:�������r���1�f���V7�|9/�q���Ȝ|+���q�������ü�����jh����ʵl�e+
��{����y��L�
�e�a��Q����q�]�wu�o} �x�o�@���5'���uI��)�\Ux�F�3�d�;\ě�J�.��x֘��O��s�^�ĥ�Hm�2-<8)�:������B]�������Ǯ1����xCo�<�Z�I!��d�e촒
�@��ĝ>�����C� ���R-!�{�}�J\����Y�}PkN������VA�E�X�2�A�'���o ��߸;L��U�:�������^�������=��ѣWڞ�L��à������$��0UX���]��W,��	B�5dh[���9����]����jQR��_c)L���o�e1$tal���6�fq���nӟ�JX4�'��j�2;�j��aI�<��*4�b�jAT�����eA��_�Qܒ�´��]Y��!
�J��ͽ���Uqu�'����3�CP:�w#���i�B|��� M�����Zi��8��ʘ(�S,�E�7|��7(���,����n��Ih�q`٦����(�(�_G.p�#OvKKH��v�E�nhpK�̰�]�7��rL��'�VR���{�bd�T1�+2�]}%��w�|�h��uMp���p���C%H!s4!�p�~�Tv ���/p>k�l���T���U�p��/�x*�o]�{���	��BL���$�7���#Y
 x����$�t�]��nؼP��$��z��.v���.b�諸�ZJ[���w�D L�#��F�v{hiX��S]D�)�*���R+�d�������b�1'�� �]�9��6ؤB�ްi��a_����.���+|� �}�R%>�H��*�v�KaC�L�������V_���h�mE���^_��U�4�$@�W	���@͖��qh�<���2�愡8�~lB	�jX^�����~)��k��v��|$5Y�je��*{�tєrd��%�0ӫO3K�b����ܓ�D�
�L2 B�6m%�XD���M������WR�N�yk~��
25@M.�3ج�~�%�ɋ%�z_`_��L U	�b=V�j�7�%�j5�9�
^hqM[3	�B��kԥ�=�aX���	dn�w��+�X.S�Բ��%g�o�p���=U�f���O���d(ט�Ft_x�r��.+�-o�u����{���������y�	Ö������r9Ǎ���HXZ���׈��#��mW�W���%F��2ah ���u�z6A�ҷ��E�Z�6~	
�n�� �����.U���ȡ���B� �к��\ �z����k���]+�������{�DTR��D����0���޹j��|��/��j�觾��fE���fʤ0��-��w1m�����<�z뵍c�sa�J��mdn��}��h8kd�P�,���@L5M��`�{8<1 ���2�f_�m	��T=��:_�o��>3�9���T4/�܅�N������}�һ�׆E���Nu�ᔼ��3O<3��2�F�C�`V���Y�jE�0����5��5=�[�H���+��>��lSS�4]��U�m�E�X�Cɇ	�4x��U7t���hO]��������/�8g�����
��g���l��z�'X����	�"���C���[���.��t���}n*���YƮ4���}�t�?� �	�I3�܉�7��l�c�l�j���=ݦ��q���[��D���5	לw�:B�1��8��N!.դ�܉+.���ψ�*]�����h���MH�=���Zpif����gk���0
��B�	�$�Qg�]jp�>qBC���F�j����@��(�9����7�*�%�Ŏ��/g���[�0N�z'�ہ�uv�12:����ہ����T��E<�:T�jͥb�w�Z�Ї<��B�M��/^L��>�BY/�vda,����_�7��xA����=K2�N�j�9h�̏2�����1$KXa�hM���F�_���Z���Q�[L􌧬�!��6l3���k��^B&�sckiq����DEV�mJ�o����G(��2����lSګ�E.�x
���YZ4�.��,ͳ}`:�hq�G�<A��
�ȇ��}4g���o�fO�c����x��\8鿵.=��7���Q�sN%�G�G:Q��l��U��!x�����Rjm���e�r[�}2�v�*�>Ƹ���#V��TcY]4�xٳ�>M��-Lʻ6/[�D��U����XPPKU�^��X�6),썃A����V����̈;j�TL��`���@v���<�I�ݣF�*�#��޷%[{]�#ˤ�� ��Z�5����B��M����ec�pQso��������Jh������QQ^�//�YM�=qQ�F��v`x������"M�1�e�"ƎDp�׉4�bG�D?v�bl�D)�R^9)��p7����� �GI��~I�r��l�r�H�|�˪I)0p��2� ��W(�hM�X���\"�5-��:m����_?`��r�Jd]|`̨W���,��ē�ʥg�@M+Z��߸d�=o؏�J���hSo��_���b7�ޜm�l�3q9Eup-7��z��p�Q���Z�W�ET}8�>�Qqb ���T�%�~Y�+Zc
�W�
���f+i��a��V�_
�v�ϻ�����Ed��g W;�Y7Ȳ�#�Xr��ԭ�u]�V���x���tv���
���1=���iG[;cm�PVnp{Z$�6��4]b_�Z���p��f5�t� �V(A.W���ו҃��x��j�R9o�մ[70�ؚP���s�_��F�sԾ�s6(4�
�����V��(@�t7ǟ{20 �y�r(7�������
�C	D��c?��6�*ઌ�Z�cVd��Sd�>��9_J�Wn�>��ѦW�ː��Ҋ-���ZtC�*�Xc�Q���梑��a��|sf�Zs�2�84��9�'�lk#R��ʟLD�4>�[�o+g�r��<h�<��ac#%(��ɥ��l�+
Aw�2Fy��	�
��b2�����{�u)BsM����^��)��E�8� Yv�H��;���vKä)תc�/N�JN��O�	��1Q��FƿJd��t>"p�	�6�n�ץ�Ht�ؙ��wo�sE���T"2�|���F�4���e�-t,��i�*q%r���,�uW{)�C��y�/�S���-r(Z��G�C~H�������oU+���d>��FF>������v�M�t�G5�G�/�N^*	����f�}�5x����_]��t����Efjv�B�M�7(��en���MKp���2Upq��=#�ψH���\�G�ԅ��H�E��~�[--?��3������B?a��Ϲ8��T?�7b�o�~d��1�']��Y�Ľ�˿r��U�>�2�X)�S��C=Jq��G�BN��E�aWP1��ےn�K�`Vym�a��h�TʛS�?�b#�.�*���Sv{$�=����i�膡1(d��q8�f��ਸRA*p���Ҡ�sk|�@,�n�x��t��v=���_�!�������b0@ky��#J{�҅���Û�������O�av�������}%���M���	�!�/�#j��0�����d%2ׯ����Yw?�WP�����a4[�l�qZ��(�7�2��p�m*(�f�[�'|:��!��]��+[Z�
���xA�>�<Ad77~��ᨖuེϕ5DL��τ�aB��tЕ}�!G�|�ad_�;��=�/�7��Lg�C?8aon{W��� ��`~�9&�F�r�lg�0/#�Ч���-�i.3�i���#���zL"��N�|	�8��	��ti�GQ��W�w[�Ӥ�Y�$���iT"��@���A�� Ma{4l|��:��U�ҏ[��&�������X�m�o����x5c�O�`���������k_�=����_͕��Z@�	B��5�RL��,S�PT�4���F<= ��|֬{�֧�b��7e��E�?�C@�5^�{�ݎ\�b�=t�lsy�J�U���5��t�~z��R-nJ�������RF��:��P�3Q%6pۡVۢ���p����X;EҒ�VN6C��x^5��ɓ�0��q(e#����P�(=1��'l�{ӝ��]����#�V��:���:�d�ѥ>��@�c�I��<� f�p2.E�T{�vkc9�w�u���@%X�'/Q2�UB!��Z�F��������^9.j3��}���)�Q�A�Ѩj��P�|�&~��tJb����H��/��&�ՌW�I.�[J-9/��c���oh�Df׈�fN�)�RdN�����Hkea$��#]aƌɄ��r�w>���Z��{B��V0�b���l<��o�ӆ��������f�Ǻ�B��a<��䪺�40h��Z�ć�����R���tw�A%�|�CɃ�E�@f�����a��%%>��G�����?^$)A��%�yAdd�(\"͍Y��5C���]�<R�K��{�-G�=�V�`��B�f�Ȁ�ԩf�����!��3��w�?zu�`k!�ў�	w2nK��q����dj�*�Q��DƬ5�A_�δ�c��U��1pԢ(q�:{dF(:F�j�9cI�������#Z7���K1���o[2X��I��͍}Gmٖ"|�%a)�:�C;+��.�HAȇ�~��r�ٕ�٣��Q�n.��w���v���2$���o�x,�@�k-Pz�M���anep(�����ȶ�5�)=�?�%�a�_a���S�Z~��7V#����4�\�$��o��4(Za�|������P˩����@�\3~~l��^�L�jh�����N�F)͹I�G]�-[�-�H���d�,�+A+�p���DM]瘉S�_�.��u��`��{��w�����N*jD	����c����	��"1�,��%�[1�!>������Oΐ��xj��N�k_�p����֔�U�Uzz�K�.������(�� S�黸xj�G�yО��W�Xm"	o���P��t�ƛ@�̂�W~K�|Y�{5�1И�K��Nq�i�A+&�s-?r�1��j��a(!���2P�Lĕ�"x�6	MiG������B?o�Ϛ_����ɕkr��T�{�ou���Lj��N����R�B˃V��w.0�F�ŻE�\��ғ'�7�!���ZV�oD";�J2/q�����H�0Q��ӅEԈ5�^t�Jp�\�DT�Usu�q�4� ޘ�_��8��^�����#p�(�7ֻ |��ti�,�n9��AKɲh�|E���PS��ي�=�wz�*��r �b��!�9�~e
�x<�D���mV�Ր3�����������XW�����J�����;�J�B����̞��i�"|���fz7r��J��1_�g�qe�yq�{�`n�M�8I�N ���S��C���wU�r d o�v���b��O�D�3���J.d���L��^�v�^�ǹ�fsIO�������*�t��Ƀ�����e�ꛩ͑aT�E�Qy�,�RO��I��eE$��sV_���+LP6M���8C���C˝2"l���̀4�М����WH�7#���D����!ϐZ�^k���*�4�#����ZU�1i+*�����5� � �C��Y�6�F��,�N��tw�����(�|��� Ǝ����\
P�.�sk����y��F[��+u;&msY��w��^)�g�]��[���b���ִ�)�pk0�A�~z�xNN����B�NO��@�	J�q�gܙ� i�7a�"j-�	�#���c-0��B��/Ę��O�28�{�m�v*�8o�����	�'���{�*�S�
T��Ǚ�i'��x��҆~S�n�7��#}��:9���?�k�aŖ��Q+�]�'>��O�_4�������,=%���84���LM�)���]�u)��u8���õ\��0T(%hY�е,;x)؋��vqj������U1\������ܷh�ӭ\�]@z	4F�in�*�qx��e�(���s1+a��q!|k���G]<������QA��MW�3_�����'�c8h��P]�ؕ��pu�N��9�wn��vS�3K��'nC+�A�ğ�ؑ?[YgۋM
7M��ʥ���i~��fYN��gg�d�J��F�^�u+m��2��li�,�A���ep�1����اY�韥�4����ǓC�H�������2�~ft�k���O�OK�t�?-�N��O�M����|ׅ�8� ��mQw7Շ��u��M�6�yQ%�"=�iR��;�Cx"p�{�M��)n���G1Jp|�fP�
���;>f[��.�T�@2���n� �!'�X���u%��!��8�� Y{N��ٕϔJ���(\��N���%c��"���#�$1ɦ0R�KMU+<���"���Kp��a�����%
 ���n�µ��9E�3�]Fb1�6_�VL1{�g֑��IL����y<|�����!�nU��ۼ��5��*H��󊉓�!��dF�����]�J"���M;�n��^#@Q�l�Urˮ|��:�\��8�T��	�R"��I� ���)s�� �[Y���1��uGV5��]w��d�=2�ޤv	k8�l�}��2�!^��+KJ��!�<܌&e�˒��M����E$18�0�f��`q��{N<?�	Z�d*p�SN�:���\]�K�q����)��_ޕ�j�%�� �����wR\ߠ�>���v~����_#��죨�r xi9�uxf��G�j��� D)��J3�t���hO,H�m�.�|~6+Y���`ē�(�/RG�I�U#=d�����'n�M��K_��f
��x��F(#�����% ��U]������^�%g���3ly�l���cT'�^��J:e��I���g@�dW�ߠ2-�Q/�/��a��<�)���rꖾ��ع�%Ux���e��}�*L�f�� ��=��<��x����yp���*���g��]4aƟ�d�z�9�ϐ���_׫���NS�:��nZ�����yX IM��mO-0��/����A*���9l�q-�máb`�l6������ސ��8C�on��B��HG���_�xFá�B� o�4���2�|Տ��R��r��nv]���Ĕy�
Y���H$���ێ�����t��͈��R��[��g h�l]�(%��W���(8��S�F����d�2�y�#>�X�e������[�Jn+F(|V�U�*Cz���¶a���1��ߟ�g����W	��i�s�(�f����+H��׌~�t0a����b�-3*�C�:���Ny�`�p���pLco�����kLfoz�4r<	q�J�K��C⯴QP��S�*Ε�qG���k���r�p8�5dy_A�pA��~<��<"b�T�J~�~VPL�Ӡ��
Y�(�p�rB��$.I�1��v�RKk
B,k��.�8�^����cc�d	����K7�,� LD�X��$}����vt�2H�G߄�~��)�I �������s�R����uR)�ҴF��6y�/Zs=�����,g9�������xNh��(�y�qI�o�X���hy&�L�<lLm*�u+��dװ]u�3a���ŝN�_�x��؝w�Y@u$e����J�K�k;6Ō�8[�,/~�ұ9�v
*漥�{�[����ԟA/DX�I��-O�m�k�[u��d	1�/\-�����n�Do����	�V��2��v�aV�ܵ�&��ǉ�N0��Tm��z��T?���T�|z�$թp�˹�C�q`d��;� 9��GLõ�PK3  c L��J    �,  �   d10 - Copy (17).zip�  AE	 A������J@��)�f�����9�a�v��K�S_�EO�	N��<�;��?��1�ً�7@��F4�E�Ϧ̈́bz�O>��P���O.�ݎ���A�޽��D��Ƀ�i��n�g_ �������̟n�L�X���M�h	�L�mu���e��m�em���`ҹ)�G"4��<���-�� �+�b�БJ~�Z@��?�F.��w�ƾ^�5�pj|�Ϻ$����5�,�>.�t%�M)Q=ǡ�.�݀����tK�K�>2�2Ĩ$}[�/��vB\��2�[�/����2'u�}�ƂH=<�=m�^��Ʉ}�c�Խ�&nN�@<�̶wǕ�~�7��%˰�)=Rc��6�F�}�V���.GJ���l;�W�C���C�[)W��A�U1��*���T
 :x���*��z��Ӗ}y����)B��&Ϋ>PtL�\�Ƒ#��5�엀I��~�i��Ǿ��v	�V��K�#��mE;��W\�5���F�'M3��ӟ�Upes�D�k{���u��/	8^�j*�u�5J2�[V+}"^v�˗_^��m��@N�>#쌡�G־�R��� �i��� S�%[�jg�[�Q�������bRE|�H��h���2��g+E�C�f��F����~���q�Ic�=A���}b+�� F:Z��B��`�h�gF��љf�П�O��=�1s��g�0Y
 �1�kw�E�J�n �D�:�b<Q2籯�����Y��Z|�����,���4�i?*�C�4�� �+�����^T#�[gO5�b|���&z�R�(=���0�h����Sm��N���~�C�36�$H;�Y��I����n�a����c=JW��
O<H&�"~?�f*
�)M�e~-V�Ƨ�kԚ�H��UƊ�K�x0Τ�&��)���w��w��0h��̷��W�vg�1t8�uu�HE�]_WQ����O%�j#=3�m4?�Q�*���|�kf�M�6�w�Ҷ�`��ǲUs�yoJ�U���n��x�ܹgp�g��,m��`ڼ�Fu���������� W��mU�1P�ᘘSw"��R]����1S���H��F0���+�d�C��#��о�i��n��רFZb'_8\J�p�<�(j�r���wp��@�6Tv����ޝ1Y����X�.���R�g�'^��pCީ�9�,T+�����e
����P�r�R;=�l㶄��i5�a-����>�V�b.AE���?�����|{05;��^ס~��0g"��ߝ�v�0NF.k�j|R�F�+�Wml�G�+��[�����ة������͟e�iq28�[��1��[��m��c
U�8s~P����m�q�2���N. �17�,{�¡�I8��v�ze3�I��Sf��٪q�8��4��w��$�4v�K7�\F��v���f2�(vg�Iف
��H���{���F�V��_N%n|�?/Ӆ��Ĥ���v�PT
+&2��)���1f���\:���U<sb��/+��Y�Vl�&��!j`���v�p-����o����i3v��M�{N��������7����&��-�٧��������a�����������L��s�hKί�YT�o�23!���j-c��w9��Y��S�ۢ�&]{מV>�f���\��[.�#Չ�Z�@2�����N�[$gJ�T� �-i����_ԨULK�����[K,���̧��k5�����#�\C8�at�R>�9	�Bv9�R�d��Ǆ?�6P^��f�B�[�s�9�L��"���1p�P8�3^�,�b%ú�����78�\�w]��o�S���������T�):����[���b�WeƮ���X`4�G��5|,�!���n$�
�����ɗ�.ugëv��k/;�~\�ֿ6*���EM.��K��[d-B�y���q��?3��-,r�'��vN�_�7�?,S0��Po�K�o6�����b�H%�����{��:�b^�2P��JY����x�s,�H�n�~�= 
xd�0��)ۄ�C������u����J��6�u�HG���zk�� ��bˎ$'�ݮ��$�N3��y��|���)�?OV��@K���dA�И���=�F��=0"���nN�'(Q�ߪ�� �x��ܫj�K�=6�Mo9�}���J"K��b3'����/�ί�eȐ��׎��v��D6����E:y��^~�h��H>+h���T��#������;!=l%fu�~	e�c��Α���g.#?{f��;H��f�ggt&��r��S}/+����?�������z1��y�<�=@�e+�=�涶wq�I���Dy����nu(w5���R��g.��ۦ	d����ZŒ���{�E��0x�9N		�P�^B�8u�h�&�ݢ*��m	�H.�t4ax�N�sة����-��] ���K5a�sIWe~��;�ǅ��Y�?yR��G��r��W�R|O�����<���j��E�Z>�f���|���>��EKh@��B�ء��5�6��:	y_�6˶����|�j�ޛ	E<����vQ�M�H6��it�K%`A�ք�Ѯ�N�
�#�ٹd鈦ڈد�BC3�y�єPƋ���%!	�ٔ^L���Nsd����>-�Y�,I])�1�GX�NB���{!�م�u���{&�t�b˦�4�<R̻���v�x"�i�����8	�TmQ�a �jf����Hj:Z���*�u�J<�n\l����3���v��t ��
[n:;�K'��6� B����/�88�uEq�H?f2�h
��
�]CD�{��(cK9��Jc�)��K^oCW1�-Q����)$�a���B~H^���dV�%��E�w,x�	ivը�oW~�4�T
���$"��6׫�&3I�m��>�ӗG���&2�oƒ(��x�3���x�W�����Έ^/d*��A���$j�yeV�C���m(J
c�/YkY���co��ǚ<�����:'����kU���p�I�xZ�
r��h�tZB$f9@$��Wdd}��PfJ6�s�"L{J������`y�,�]؁�2{�H�z��p;���+�vճ�+���"���"�L���AV�Z�Mwt,^4D�� ��zWs�![���Sr�	3|#ʱmS"'y_��w��=C�L媁c��Z���=O�@@Vd)�RC,H�"p��괆��5��u�L5K��v�����Ui��'���n�,�7��z?��?�r�7;H�j�ҩ�p��u3g1L���6�����bK��+$�c6��R�nS4x��U��~�w�"7\�͐U}����;�T�m2f��1���:�d0&��Zo'I�1U��D0i��h��R��EJvʜDH�\������&}���\�9ܦR���%�{���%������>P��ɸ$����;-�s�I����e���t�M�h��������Ш��ǲ�jNr.+�	�� ֧^AV���ȳAB �l[ � Ez���d������Y�6��_S5�����z+P�6"�.ƅJx�A/�������415|&�B��/���z�_B��Rt�H�{����?Q4��8�%���ׅm �h*�6�HD�U���������Z�}�c�3ՉghO�����Y�́�����bW.�P�����g�9i�kb�E�6IM����u�+�2��u*��9H�G�<�P/�MP�[$�\���O�2K�?���c�s�FiP��?�Xg0��ޕvx��.�ɽE� TI�l!��v�(���]���'i�kZ����6P���M@��0o�T�i%�4������^Ձ5y� /�����J���n��CM�B終(���&�:�T�$YU�>���b���aA�;�f���_�mCZjd�2�\'0\2ơ�/jf�p��� b�$��DsN�iQ�G�An��z�o�Q��-���hBg��d�x�7�����3�n�\�J;�
.�W��{α_���|ݖ򴡝!�-�R��d2C�lJ� S���I�ģ����[	iw���TRT��B
�GI�s�*�uNĳ=j9�+�T��9.h�C�-�:��޳§􏀆1��K�dbq�H���Vu �>�5���,�ȞtR6z(��8��f*�U�]q�`��#��&-���3CVo\����ǻ�2b�+$���m�D�쮚"*u��9L�G�m� ڟ�� q���A�2r(�Z{�-�"L=�	�)]��	&!����2=�2H*Z	Cಠ��I��y�	�Oþ�~��3�ϭH!{z�b���ƹi�29|�����`�@_�ߌ (�q�e��-��C{�~:����&�~���&*�V{S��Q>h�	��;��&`i$C{���LV~S�{�1���Z�-D���!/-�8��BY$�־o���ݑ�����,F&�|en����Xc�І�J<py�����^�b�����f��>s�˂��:���piL��E3���J3����Z4[!�jZW.-�G4i�L�'X�r�잤��5������9���0���MǦc��^�ynӃ�r�?�1Μ
�٨?N]����8IC�OEwC�2d�Q��!�\f*��/��[��#�ـ��Ugft�BZ�H-C�G��c����m}�+����G������vb���LoD�}��F��6G<�Y�~i��F��ґ�<�,�|�:��K
B�#5g��p�Jև9P���hFJ�[h�:�7�i 8A!dj�C��`H�G8����:�����Ԇ-���30��9YuM�K����.�X8W��
��nfU8�-*�R�c�?�a(��aY�ơ�c,���S��
��$ʿ/���Oxۇd�魇� �G����@A�	���O�$���ͯ�+�B�[� �4�Xn�f0vF�7��D�Ь7�NlT�H��{�^=NR���`��,��гƚ�A�7B�����]�f��_u��[$C&&���F}��a���Ivy��>��IkƤ�o��a=0b�Ys2ؑ�,� ��\��aY�n�t(]ﱢ����p����!���~�BsG��@l�&�q�_dʬ�G�9:���}K((�J��E��o�G�Piًwoܮu����-�����&�zP�㷾w��m�T�#�b�fm���9��Ը���A�������"�͒�U3��Rl�������X_3�W9څ��1�S���*k}�?�F�Ⱥ�X+�Q[>r�Xb����UV-��c�M}�t�4�_;�#��4��*k��&���9����\λ^aH��8?郞��+�bU�/�j��=�8�����+c�ۯlP�"~s=]��ۂ�OG-Jw����3�ll��@tg-�O�Q�.$q@3*��+�p�4�Q�Ex4�#��fK=�u{��)4$s%(�h�O��q��0�-N{G�uc���]SZ*3�w�؀����2��W�Τ���2�J�zF����K����i=���V�dZ�yF[�ۀ��+��K��1�Iו�8�^���y����i|2ݦ����Q�E!�9l�������|���H{1�6�\ �a�n� �	mJ��xi��L+|L���#�;�Cյ�c��R�l4��߼���㮻�*Ln��7*���a2G��S��5��,��ˀ���>�u<M�MH͜ź|.ꞣ�Ӥc=6����s�\K� Wx�c)�ؑB6��}Y��q�ᢛ���+�a{O2HO��B�uK�w�c��sn�S�$�@��K���W&M>�L��W���3�ͫf5�/���/�4��!��;�����~UJv�ӌ.�����c�����L�'{��䌴g��K����L\�k�o�)C���C�z�G�\e��}?��Big�'}4���u( ��һ�Y���V2���!ӵy�L%�i�6*��JS�:Q>�K��E��8��@sS9��o3�>4��gL��x�7�~�+�ə��5�P��+?g�	�y��]=���<���Yexn&ц���X���PdA!�ƞ�U���f׻��bvW�Xy�]U�oju�O1k�3:����L�^����f��^�,_8�o1"y�
o�rk��Fjr^,Υ�H��x�n��
o[91G�?��W�o�j�Ժl)1a�]ĪpB�lrM{f�n	�t�%����=X(�����/j6����If������x	�a� �[ܠY$��IV7��v=� �e+�r���P�,_ǭ��^�:-�$t7V��MS�X�ޞ�P�v�^�9<��N36��ĵ���{6�ZV_J��1��V��:����V��g�fȄ�jy�r9p�����/**ڝ1a!^�8��F�KTʂ���
��E��>�1�q?p"�%�Ą&���!�����p�_��Yl��^�O3�!j���:[?Q����c��9�Q��?�9 ��YO�ʄ� �N��@���PB�J����'8�%D>z���_�Z��B�_����IMED �B�r:dJ39Z�2�P�;��CfDV�\r��:�2`�$𾴏���+��,��N`�*~��p�bB@LFg��}�Vi5�q��b8Kg���R/���'8��yIt��_���H��K�,̳RϹN\KC�L���#k�2Y�%1"�0_ EҗxufS5|k�a�O�f �g���?1�����xmH�v�1��>1�;�Vק�Oo9����y0"܈�D�evo�d��0c�HrLs�s��D���@Ô��mݢF���y��+���
��y����qv6�R~�̘2\�f��N�r�	�B�e�G|M�	:��m����v?-���@�E�!��ۖ� 0oM���P���#(N����y�W��o,���L`�)�rN.��wD��J��BW?�Ir!K�aJ��j^R[��< �\YiG�X�Dg�[�_����j	�b�"�#5��W"jޗ��(q3�P(Ԧ��g�:�B�7"L�p��O�^�瓋���Tn¬2�$���5��h�8p�Vf,eU%��������������0(,8B<���*�Q�9#	X���t��>����C�ku��=P�y� ��GA5OW��x��Ǻ����rZ7kխq����
S���/��g��G[��?�y��~u��x~-¨��e5~ʡ<	���rs����*T�q�:��Q4����"���OL��[��#"�߳!2_�rm�r����ŷ�;Dߠ��?jy�N�B������l,�ød��	+E��bo54��2u���ϔ&����b)\u1��#��E�ب��A�����T�-�Z�iXV;��r���ߺa8��繣Oy�N@!:#���5w��j�7xH��f��k哴�{X�w�1�A����jY�����@~��oj�f�c�����*�6W��;�
UE�FSnj@5���|�T`��Oܩ5�U�@4���G�%@(<r��Sr�m��TǷ�6��~e��iFj�5n��k_?*�x \�x����?Z��Ϝ��ǔ�����Y�qG��_������`��v��%�%̌��\W4^8�.�ֻ7w኏�0ǈoQ��'��UŌ��!�m��'��^�h����<�d��$~jp��Ĳ �b���!�C�����V��!(-b���t��%"cB_0��d��va�tP�Km��ldg[�E��
�(�{��:š��'��
e��5�f��N�z���*x���g`g�]m�䷷�sU�푗�ؗ)I��ŢHC`�_�L��ɀI���qr���'�	fX�q'�;}Ͱ �>P�K,�U̢"�؄E�o�KS�4�+�JN�/�BzE�O��)��a��E�f�sՑ�vβE�Ζm��q�n�'�f&�_�O���S����!Ɛ�v���4�?Yz���{v# d�RV�|Ȩ͠g$;1T�����0�ڇׇ���M�W�,���4��l�ώ��Y�@���2��'���!fL<"!�r�@������
�J@�[�nG�{�.��^*�FE�b'8w��?���/t��Xh����ܭֈ��J�Ң�z��tk]e7c��N96�����M�^#c���N]8�!� ���U*HjD+�|=֜�w��$*����+@g%��%�#�$�����,p'�#�&0g��t<5[��HP��,�D�40�qqQ�#�W��ux�u�;�F�m<�|�	�SMI��$��K'�Y�i��F����#b���.VM���?�vr�{_���ps�-�7q��4ڼ �&(�~��#K�Gt�Z�f�hmDJ��Ar��ʿ��Օ�YAf�;�V�,�o�(���k����9�	�����bǭnt 6�{|l�tި�0��8Tee]�z�8��"�T0�� �8�?����q�����F�p�A�6؟. ����3mŠ���l�D�H# ����,�Qw��h���^����ܕ��i��!��C2�r+~��A�WS��!�
��V�["����N�g�0��#�gj� ���Ҙ��6;�Q�������-54ԸHag
+y����*��r��'l�ppu+��*�Ɵ(~h,� ��+�8�h��@*�|Dm ������ACz�q�,KҬ#I�#F�W�;��O[�B�������-�T���|Ȇ!��Y�����L�cw���A��gDa'�rծ5� e=��c�`�H����T�����NJG`�J�HKx%@��u���UH<[e�a8��6��[ek�$�)���[��R�Hй@�g-}��#}��fZh�f��� ��nTM7��͋��;zږ1�m�>�ް�_L7��C�����O�����t�$
�ͶJ���͵�rx��{L�����Ѝ������zFi<�{�ƞS��t��,���pFh��N�Sm��?��-Nc�I*�P��H'��,�=7I�@�iɂ���aRo}��u���qbg@>;
X^Mr�g:��B�����'5�԰��f�]BZ%����+�� ����XHG�ݨ/���=�5��dng�)�i��������W*3�h7�*����k����졜�+>���1�q�%M��� �D��	s���1ncŐz�͒_u5���Z�,]<6��R{����?)���K�5.���&���B�y��ؚ
�q�v0vǥ�����:
���#t�T!����D`�2�T$]���Ww�:�-rN+k�QVY|Ue�dF��Y��0Ջ��ᬙ��B��A�hv���Qe�«�Ն&�h������:(vԐ�y\&��R���헔 !'Ma��b�\�NG^�ȫc�3Ck�0q�0�t�k<8Eu�'�U�
z=�-�?I�㡍�K��V\Ά�ϹP���R*e�؇ ���Ew��r+��ִZ7��|0��ձ������;���-wXF���+��W�"|6[4��&=h��z.g��o-f�����k]=UMsC6�N���BKF�A-�Q��$r^ԣU�T_4щL�7�+<.�k<A����@<��q�[���6C0�e逼8a(�5�o)ɻ���_�\mUC@�~�<f�����*ܧ�)��ʌWA~AF��u���d�s����5��yЇzOL��uQ�lj5�R�s��Xb���b�'���h�5p�D�$�ɿqP��__sR~gBZ��.�M����_�gF��l־��C>��3�Njr\�O�3aq|�;YxЅ�8MOƛQҎ���FǊ��E ���W���^_2�5Ҝ��h��	�^�}�J��Q(_�i��\�{�C_�B���l��p����/��w�ǴwG���y#�ԔR(��U*E�gn�tl��1UAe����% O�,s=m-�����B�-�"a/,��b%ٮk�[9��y�G�<>i'�W|L���|��G�R��)����J#{���ǂ?���SE�~��8~��я��+A�b({ѲU3��-R'A&io2ۍ�ہ�1���s�S��
��_��:��/Q����� �\�r.E�/����Yl+Y�ƪ��۷��F�O*EI����#%�(@�A:�ya��B'���w�%OCЎ���(v~h�X���&M�a����\m\|<�}�JN�o�X���+�%RC��dz5��w�ycc����*�� P�+���1|�Ѿk*�����\�b�)<{J�3F�Fv�ܵ��QGN����V�ȴ�z��f�?��%&���=������ע\���ֺ�9���zǳ�.:)���dBeޒkCSپ/_��V��Lɂ�R�1)�	M�˙��.b�BNO�"�b)n���ʩ�Z�KF�s.yh��g�@��d߬�֚b6�Au���pq��'�&z��0�S!��
�
��\�ꏕ�\��r�" �^r;[�1(�0*[����r���m�g��c�)��E�@�BnE���h]N�-S�h�UMΗ0W�T��զ������E�h�/RA��&�VW����}7l˗�˚��4�+(�`"�T�.�Ө��L\���y��V�e��(�'Mw0a��}�٩�wcQ)76v�`�����WK_��q��32z	UH��!I�C�˛���%�cՒ�V�n�*�b�C��q�߱�n���7��2'`�7�9�K�䘳���\�!%_�,��Z�S�` �����x�$�ds���)��xu(W;4��$(�,��!�);�]'����z��T�BV�ξ��
��l����mzl��"����&�����9	�,��$.t;&y����0}��ix̒�4{����)�y{��4��ȩA�/�˾�펁3�sR�*ZR܁�n��؟��bI�!#�)���s����~���M�\N1��rY���F����m��1�����&wI+'�T�(owx8���̱t\����"=Y6?�K݇`&O�띙�� � �*�V���H|�T;�v?�	v�>�!tU�l�I�����ww�����8|xR:��p�8�S��9�h�,�'y�^\��m�l�w0+B�����p���I���^���F�A�i���\~�N��Sqi} ����f\�a_Ö���,���3 �������5��𝕷X��p���[����{�JG����x�į?}��w�z��ƧM� 4�
f�A�M�_�`��Q�2�6)C�e����`����p�	4�18U�|��Qh����Jȡ�����������a�
ږM�l��j�C[���;��b	;��ĳrz3li��E�z8�xt�i�S=߯���ԊQt4fͯA�'d.Dpw�Y�����C%y�%�5���|-<A�#C�?�@ٞ.���K��1�dd0���I,rWA�� ��%8Ő��B�Fv��a�PK3  c L��J    �,  �   d10 - Copy (18).zip�  AE	 �Xl~ʫ,���>{����<G�'�򦺟i�8'�X,����J�m�¢ :pp��E�3gP�nnw���\�JZ������Yh����'ʀ�`�S��	J�0[O�.2�6Z'mU. ]]L�N	#'��N�_���ҹ��V�<r�Q?lQ�qT�L'�J�z
ZҔ�,���#��ys��?�`��If�/:eK������w����.'j�)���,�o�i�w ���0���ׁuJk�}!��	Ώ��b*m�6�H�M���=W!| g�C�NO|����F"�/�I������t�O��7� W�2R\�/�$��1Dq{˻[�]�6y����I>�9�|"�U^���aF:�ʨ(�(pe/p�5Ġ����
���2�:^�k$���פ�ų��D0�%T�c��L�e
�2H�:�ST���s'�Hˁx �C���R��ԗ��H�K��%�P�u!eN�,�,�7�C�#��o���%/�[���"]��4�,��/QaY%CWJ8[�+D��9j��c�l3H�/��.y��pU5P��y�8�E�Z�4�Z�vsM7�`�踢3���䇈�p�x/~!!�;���%�`�-��x���TR��<�W��j�S�Wq<�!��K�7�Y@���Qb�"n�LΛ�[���d̀Vn�I�4�3����0{-�N��xV�6� 2���L�����V��uW�C��ӯE����w��c�k�ҸQ�&[��G���h�p��w2Z�MdóN�Ďp].?P��Jۚ$�ǬS�`�/�)*�5�ak~�ɛ��l�`��ė�7�O%���}�G���
0���d�?�zQ��q�'��~�Zp���u���gkW x/�E%�ʃ�>��r�Dw�Us��f|���5��k�����d��0��%�#����hЖ�2�����"lӦ�|pY�Y8��_y��i"W���O^2,���a���n�!mJ�:���qС���a��~�e�>����<q���@ESg�e����(h��sB,��ʃ��
����v�	��Q���i��KY�Һ�P��`���̏*~�Mv(a�7�͘�B��2���/�ڎ��*qa.����"3�ڵ�Ø��L~�0Ϛl�UA�t�����R����?���K��9l���Y�w���Z��=�=�}}�#��Fd֐̱|���B>T2��2ٔ�~��F1�Q��	M����-&r�G
Ee-�.�r�>��1X���Ki#�h0[�.�f-�|Q�c�-���}�L�U��	ʍ�>�g".���|;b�x%�B�b4����y����V�^��@�t읾o�	���,"��)�Tؓ)h�2�	X��?E[�lC��?�T'߁��ĿD�+V�BX-:�Z�)9V=k�U��D��1�;�S�M�p�R	β��+����.���Ut*g�,/X�!=׹��_ ��-G�i�S��i������"r���I���-�Na�0�g�Y]��g=#B���14b2la
�5�Ͼ�U�*��(�
��F׆x�ws�Q�U]��k�׏8��ԄD�Լב�u���v�Tx�gES
��L��M���[I{L��2��(Q��V�������"�$uલ�%W�l�~��d'E���@Q�bUN;��V�o�Vb�W(�����˅��lL�v���9{�0\��R��A�H������}֓@A�B��u�vm�R�&�3p�K��b�@� �5�#0Ľ�+|s���j6A!,�$Q��q�H ���o���~��Po�b�K2b�9�V�j*��0��vͱz�����)�<s�L^޻OR����h�ey;�+v��UC�^Ί�7,������~t�r��Ś��N�GH�Ŧ����h����c��Ox3�2S(g�N��Jq���GȺ����w$}Ʉ�/f�v���9!����� �4��Fj+Od4�VP�@��k�}1˛��i��O,������=�����"�O�����.m�i%����D �� q���c��:8X���oT���հ<}S�4�
�Ep5x��#��������_���U-�j�gҼ�B�	z [��^l���ȍ{��b���3k� {V�F�����)BPa#����M��݂����IcJh�����.ک���i,}��{3��w1�/���Y^��᝱5��h:fc���W�nϲ�[��-B%�f"�\��=�?�D��7��8_�S,�X�2�Ԛw~#-��h� �ţ��z���F���Kn7��E�Jr��Q�.����I�`�3O�I����ۘB���g��E[4�{=dx�1��S5V��u�T���
B��0G\�5�ϸ2G��	��)jI��;QVq���0 C�{��;��+�~��כ8Ev�҇��;�I��O���*ߺ�nX�9�=���A���G_E�t�p{�� ��?
LY��D9݋N9 �����vXh�	/KO*"��Ě�;����2��X	�Y%q6$��T�{d&����K�GM�_�\��m�8h�l�?v˚]�6��4n�F���t,�Dd�8*�O-��gV#ثo������λ������t���M�������M3M{��lb�M����ظRG�,։��A\�2M��e>��<����P��U�q�����_���r�M�T�<A�����M/��
56����I%F���~h��Fa���<�$�s;�N*H��5��	N{�RƅSA�{4n�d��i�ٷ.�\��uG���ھ˻Z�bϻ>��B�w���Q�>'-���o��A�N��L�����F���98�_<�<�V�����^��}�Th-�QiZ.�&��@F��q6{	v��,Ɗ�u]˲�0o�D
V��被���Gm�㩡ֽc$5�ΠD߭˞�H2��T�����$���^Wkw��E�GY-v�JG�H�I�q�\�B0W���+Tcr�1�����x�ܒBq�+�iی�)�+ޔ!UZ �I�r�f� 4 �|w//��-�ٲ��EF����=�T)	��g.��r��7p�|uN�(�Lʻ�"���81��c]	>/b�)�Q+@!Q2qթ����}���{�I��?�]V#�>2��@�K�"�ʬMTc������F҂�}n�B@J�<|���(��tc@�y05�X�|�:7�gn��\�r%}:��1͘�;�#9���j�������6v��&0o���@=j
�bU���>
�vݙ+h�ѩ�M������Op���0p��s��"�Җ�p����O�(�P�&��4>`�"��w�8s�u+��	��QѸt�o��	����
���+�0˔�JT��yS��=�xz2�dJw�"̊�y�4�������y�l���N��4�S����1�>��H)��,:���r��gɾ۶��)������V�����#�i�0�A�I�[T�Ǽ��,*."�9Œ�"`�-�������}����B_�^��n��8c�f�^P���n�Ty���헽�`�$^�l{
��*œ���UF����bb$ΕW�8:��ߧ�n�"��k�ݞ�̘=�a1�2�H�y�nՙ6���Ǉ�f[N}�ʖ�4|>,!�w�{1bL��5���ilY�.E�ve�M)h�X���Z�aB1�Ў�j!`�SL:�5���԰z^��t�ð�G�R��b�S�	��	������s�a�^���γ��[8���ԗ�d��d����bx�ʷ������H��^Mps�K�i��2"$k� �B�%�m�#(�^�-�"2��7_	��o�����n�+����T�"�-�s%j-u��7���VU��{L'� �~7UlySQ.�6�t;��$/�N�@_ %�$��6Sh�f��j�35>��U�oZL��-xװq�V�q�7w0�5�0�ǒ�*I�3�yC*�ŏ��a�`������}c���q��ᕿw�\�̴���[�_�Q��g�� ��M���x��]�G�7��v���PܴT���I�1�Ư[z5g֙�7�ϙ���1���!�T��Fe��ڄ�q��
�_���K��L��^m�Iy����3��V�*�P�������4��*�c�q���(�Ą�\!���0$E!h��`"0�2+"��u��7�w��/	(�bѿϒ '$�}f����έX @��lS7y�C��%q*N�vh+�n�ʜA{u*�BG��=�3����k"��u#�SO�O�n֬U^GZ��t.�t��
qnanA 3���֫�q���8b.�ׂ=.�0��Ć9�(2�c�;_W��{���YU���i���-a��>���]�"�w�ĀV��\k`�}��<����%R�'/�ʉ��%Xz$���R�IkY\8�w�`��/�4�E���xi��B`����?�<ݖ���s!b\��Z�<:=�J�a�p�j|
���<)��z�
j�/*�T�S�"��xP^�W�r��{����>ǐ�)�ʁ"�7��0�H���V$�D%�ŷ��͡t�5I��� ��$����U{��~:>-v��V~f�����������th;��ҭ����}9��I��������*Sle��C!��Z> ���}���]����`�e1��U-�Yę'>,}�3���J�p��9��U�h�P]@���Q�)׮�gE�/��^���з2}�A�tS9q��%C�E/�f��h[k�B�����_@��������ϬX� �gЈ�Y@�d����5��__�����T����!�d�,���ΩTr��G��䓫'qҘɡ��k��w��+ ss%��ML2�����,	��/=|�R���V�k���~�QIkԏ��~��؏BÀ����E���"�Q��dYD��ˁ�X�v��S{$a.�h�ɷ�b)�m�=�LL�)G�E�5��V �ذɱ��
�۲iڣ�|�m�3�?���Nš�&���=1Ab��
�! ��E�9�<�`_����no{~�fd0��rlRK+Z.�Cfqe�k��lY���x1�����|U��蹻_��h�� ��$	����E]wD��:1Q�H� n�h��b� � �ȑ�F1�y$f,U:3b;>�f]+���s���د���Jy�x.�S��,����!d`."<ejJ�(��Sr�+6pR�ZW�Q�Ǫ�����;�']�̊�\"������6f x�-�?^i�*ɰ��Z"
���I��P#��I�\���rYE~�r�E�w|qo:p!���p�P�����],+(�)�(���)�!���Y��w�5W��G��'��I�!4
��<��3�=�(?����%*z���s�0�J�R�����&F��#n�X�$t�x�)F��ւp�������l1K?���so*p����F�6ˉtmB!%T`9�F��k��<��y0	$�>�S���Rq�λ��ja�ΰ{��l�s2u��P�1@���l�H� �S�z�,6u���J��/�&^׸��e�k(s������@�g�z1lK��ע&�8�U���wpY
M�k3���x�S1;��p��ǘ ����%���I��󗊒jX�A��6O��5�^�j�>a~��Oڮ��Ha�R(_>J���B�Ϋ�D��|���H��0���`R`ww������=GYR)Ǫ��Ae��|��By�S��Ѥ�>�/�lP?����F�Ԝ�U������9�1���A|z�̻U�&d<Y�v`���x��e}��F�6��o$Q=蒖���F���҉��9T�^�h�����B�ņ��h9��T���b'K���tK����=�*V����Ñ��	��yv�����67q��6C��Z���qv�����(T|L�k����1y��`�aߖ�h�^��OJ�ЄФ�'�3&.�݆��p������p��X�'�j�C�T���<���$�׻��}�a�oF�`)�f&��[c��@��������a..���5�Ua��u�qxH����4����n<�2�992�S��bY�
��~S�Ѝ���PY��ж\"·��J�O#{Kj�w����;љ�^ir;Pz�}�P>����ao!!�TГ�+�\�����'��\��*6&/�5t�@N�\���"��}Oտ���78�y��6��&s?@22���R�V[r'Ia����~wB"����Ŭ�j�)�(�q��_�,5��F��]��n-���ye������P�YNq'��s�R���?T7���D���
��,y�bZo9h\"L<-jSrN�ۄ���U��(k�
��-�j�W��*$�͟)�ec�s*
��&�а�	�A��R���t].��9�ŦRKu�ć�A����7oͺ'�A����'�N��Td�������<�m@SF���%K��n9�n���]B򻽫�6�@�FN�Ȍ\o�6�)D:6/L#,������a1R��հj,E�G�@���n 8�R��]hg섴�7"e�,1Vn7�l��4�eO����y�s#v��X~�s0�0��ñp��H�gz�A�z�UtB�H�!:hL�guA9Jyj�g�{r��lD�h�a�#vˮ��5�7ҕfz$c��h�7��&�wͦ�_�ն��0�<�R�R%eB/�������d����4CO��$c��j,3��rm�=o��4�K(�#��B�4�� m�`�Q�X3��rtn���� !H��=av��%I�v7��U��:�Н1E5+�bQ���֑.R�lCV\_�c��h�
߫/�n;(L������efÜC�Z��*H�p5�=fQ׿��	�@���ݸ� �rd�5O����1�E��&K�e|������2?taw�r�W ��?���D�f��K�����*���e��Q�_���|��ej�:�P<O����?�n��S�̩铕��y��;"q��W}��)�r�a_<�)u�b�����F����=tt�cL�����>4iJ9������$�֐X!�ˉ�Tvq������߃����2>�.ģ}�~� xW�q���Fv,[���@��M*�����"=8}����D~D�(�jcA��'�2�VHF�7zW:�L�Vt�c��su�ٽ"�n���Kw�p|s���J�?����M��ؗ�S>���\�,%�Y+������	o6?�Y?�p�Z!<K�hb�������錢(nq������FL�ub�Y{��^�h��Y�8V�ZC��ODD���\�&jPg�6f�s���6�Luz_�MV�ޥB��*������EQD�}rN�A�ej�����$!���M�\�1xr����t%B�"�	p����*`���x=G����=����R<�Wı�;+�M���z��ʴ�
-]�T�]M�@@���f�C�ä6����J�%L�[x蠒�N
j)�9��w4�]���͐0�*�]��N��3� �3"z��H�*G4��s���j���[
˃;���8������-���ڤ=�/q���B��2�a#�$�|>y;�g����ụ�}I�6��u����G����1���U��a)AY����O2�����v�,��h&� �e$V�yV2V4C)��;&��ۼ�6�!��f����S�[�Z)�#륊���x�����.��h#�#�ή�.?��B�=B�'ӇbI�r��m��q��h\,�/N���a����H����BY˚x��BD�S��8vDc�|.|m�YI�<u�Ka0aN�Ν�@�*���ar�2]�k�c�'�|�몴9D���L��`���M��&Z�i�ٰ�]/)���U1�0���~(�խ�C}�HИ�����c�m��y^֪�~��+���3���ڃ?�YWg��,�5��QY�Ȩ�E��O-�p^=x<�����^G����XE~WB�"�ܫՆ5�=��P3�7D�xAgy�D��ѐ�����AJ�聐s�����t�WhXp~Zy����w��<�_����}s��\< z="��^�cp!��H�7�X�뮬(�����k>
z�Á�J�)[��ԓ�l�2�a��S6�5���G���7�Р�3��*(x��m!�����[����GvheA�>�9�=�s[��h���B.���H I>*d0b̤�C��yaP���4�Ɖ>{C��$=�y{�.$����F!K�ϽO!y"A\H�r��,��������R{�R�������%��j]A�n�G��K�_�5���x�M��C5T�SW�c�r���
R.>_M?��Fl�(8pRH�p�loP��ˁ���Q�jƞp6��y�L��^1��A���
ܩ�4G��[�WM|eY~�(�[�Ȱ�t�W�3�%f����I
#���W������eX��P��?^���B�"9��P ��j�a�p��g��Z/�����I�rb[4�ۂ>�+�Ačqi��j^%jƢ��o"�������V��/ܐ��e�ι+�YL��3�?�;&n����H�G%��?�(zT�ʏ�(X�]kEL�/�@�c�M�U��\sUDMN��v��؊�k���*��u����)�ى-t0�������ӎ�{�I�"�xe����w�0�����E�$t<r���_�����;�z�87��H�i	ȴ^tpc{W�#XF�f�<��:�U��ma[�����ө����F
�q�@�c�H��Ͷ12��sCgh��P�>�L�f�B�;rUG�$S��6wvS+���j��C����Nİ �X��i�� �p��e"��w��D� $���.��ʑ�����+k�-U'��ī����5��Sp�I_	%��ƠZ�m6(�� k(q� �y*�Z����\��d��[�{`-FZ�@�a���y2�~�a�,�U���uئ�|k 햔���	��jAx�	�����[�4	��!͋!p[7�t�L�3t���z��M�,>46"�x���7����7⨳&�lV�kŷP8��$�:�)G��r�8���������"����?]�W�*<j�H��f�"���R���;�B�!�!R����E���b�dd��Tj���k�-�GΧH�����~��3 o�Ss�g9	7�����B ��t�*l��:�Zq�*,h���W��,ҧ��/��#�K&����%�S���Q�ȋ�t���̮��i�&�K�fm����;Q�a�2����� ��_
u�7^F["Q�2a�-�1NB�*���'� t%���yJ��Ã4�FK��`B�$¨d��ϵ��6�����&��SZ��A�o;*��9�&h� ��\���!��V���3}�Z��	#�pK'{ל	>��|�^�l�%g ���AU6{�3 � �9�߲t�]���8��0��`�K`�j�����o��T��z��iJǄ?!� �!��xי��w���l�����>�8��'��җ�z�C"�#&��'�t�8!�o�16��r����hc#�g{�=���B���h(�h����vV`S`~O����ipGN����@|>�XH$�Im�ı�g�&Z���)@�@����ҒX��F"3�a��8�!�L��!1�YHJy�&h�9��E.�B��ݒڲ/v��FNNG9�|�i�XXh?�ʰ���`?�V\�az�hA���A�N=6"� �����+��2a�K�td6ڳ�6�� �l٤�2��h��45
�Eʏ %��a�\��3��}�d��d�9ؕ���I�]� �K�A,�1�-�� Uf�Ա���j�����4�[iE\B9H��|�<��� �����K�~\+�-��'A�9JA�V�l
�w�3�@-p��#5��t�+l�8���X}�	�AZ�0G��zڼe��j���f��Ao�-[.�*ɲ�}�6d������M��$�̠�*�}��m��#�+J�,)
$�1 �(��q��.�~����/�մ�� ,���h��Y���)>�b�	����ą5�t��qC֠U�5?�0�UrȮV`D���q�{Ӭ��@]I���L~/Ce�|�.c+� ���N>�h����m[�����&i��]ލ�γ�b��4X1���|���
C�Z�j����ZA_ʘj˲��w�A=*$
e�g�G���]�)Zc�ݩ�g���߷�Ѧ����Ajn)���OZ��]CZ�T�>X��.p�]q���ӌQ����7��#�Z&i�~E�T��o�]���b/Ӯ&�C��A���e�[��[�ɡ��(�i�i��P��Bö��:,�W\�±0�*��/1�K�zN�9F������v��9Gt��B|Y���g�Y��2b��Y��f)��]�O�}oc:Ntev?���}�}<d+<ҳ���u���Ó�jQK��tP�x�~Tt⃑�7�ҕi���E���,�Ƕ5J1�[|���T�U ���_� �9-3��c�J���D1���lk��$���l	N�X/UrȚ��M�����n��}���ܷg0�����,G���0��)P�Q�o��k��/�^�����5�w���72�и��Cc��з�l�$G�S�~vR�����i5;�Е"���(o6k��C%����㟨���T�r��y�^�'+&����@��	Ź��o}y�L�O}Qh��w����)r�e�b��YlG���;C?��Sj�������ZG=�#�1p~��:��^w|�hԒz���u}s��X�_����]����F�`�/0E�,Q�0= �/�3�B)���"���
>�d/9�9m�ZR�X�#T��w'U�Y;| ���f�i/8��S՗�T�(x .��6&w��|RAB^���$�e����d.�u�R0h�#���� ��5���	��kym!�����/�f����z�/"�}��>�帑��	�6��Z����ԡ�����C�r�arL
���Jq�`�1�<3�߇�e�V����L$��o���o�p�%X�m��d�l�Uz�Y$�ɥ$*���Q�'�Q�)���cfу��Q`*^5�K��hoޚ�\l��\�4�x*�Li�Ө(��}|�`��?��ڗ�ѹ�$�zXyDP��yȹЎ6��W!��^̀�Pa &W@{N}7S�ǵ�S��E���ܣƶLq <�q(>ҥ�����-+�1Z��Q/R��s�w�)�%u�����2�M�j9ng�GEe5��Z�9�9��r�-1G��7�H�aOY�5��?�5}��-���-��4r�]o�z��W9Qz�>��Zv��QpZ˚o�l���VC����1���``x^{g������io�Llٹ�PK3  c L��J    �,  �   d10 - Copy (19).zip�  AE	 v�A�^�{����O�5t�%��Og�:�븐�{���B2���J-.:=Ѻ�b��A^�u�ʇ[U/OB �����*�'.��<]^���"P�9��^+��^!��(ȹ�sr�.a�W�����+Ff�LU�-���a=�8�ӡF��'~��e�s��&�M�v�^
���_v�O��+{H�.7�O��&벰��!��/- �kl���E��}����m\*�l.6޳�;��@�MI��Xї�o�M��:/.��)}Q��d��@�qs� Ԯ��e�>������Ks.<L����} )�|4���Vy_V���eY�aU�Y�f���I���g�b#d)̐%3�\�hsp�2���Qw�����$�ip�̃�
 4�1� ;$e�?ze� -��BL�t;g�׻�x��5@���[5�2 ����<���W����$�����X[D�#hH ŏu`��߯��Ny:����f�.P)*��\�:*7^^~��p��	�Y���X'=��Ց�K�rt��=H�2q�F����3����Gp�����d�0��2�&[Z���=/Ԧd@��>�>�#�T��8��\W.�(���7W��]A<��F�X�j�.W�K��(���|�9��>>i�*����^bZ�b[xkD#WX`亷؏�b���9e��]F��u���b��u��$�������ާ�5_]������.��-�{�
�����q���
,��Mؾ#�f��� �ĝ�?i�����xD��T� �A��ϻ�U�D�d�,�lk�E~�� �='N�ʤlP����^�P���A�[j)D������Ծ���:wk���F$
O�׭K;���F��@�u�&��,�YY��"��93�j/�����������?å��ᔧ��y�/E��G��iJ]�������[���&	�z��aDcB�I������[,[I���R�l'}� ��sl�5�0���=����5�������Nɿ-��=~W{���w�jU��8�ډ�[g�n}?�Pdh��~��@��~H:���5����9/�CB^�6�;������"�����+H�����z���Ă�r �"@���N��E���/L��s��X�AKw���Ȉ3'SVW�j��0`�9������9�DE߹DJ"oI�@��"�3�f�t�h�v[�תPA��"���u�}�.���O�j-,��B#�=��X��]��>.]�� ޜ�j�m@�N_�g��v�J�倶#�l5Ms>$�v����ޣ$n�B����b�똢(�M`'_�M�Bb=�	��m>�R���֒b�3s�l+�oҫ�#�o+O��.��P� �D�_�iN��<������+�_�dk1z��?�}�M�/�w8�۹QAY�6�ϵY�T%�{**�FE���ɩck����O�S�=�䔰�%�o�>��ɕL��vh���}A�"Ğ�ޅ�����g��<���Q��P��qR��O{pw��w���!�+lt�;PN!}�IE%A�A2\�=���8p�me4�nn] �[�g~6���5���7������E<o�6�T�oKD�,	M�Hڞ��G^�͒����M��
�B �IA�h,le^��F j&L'dki2I0�	Ȉ	��s"���� Ag%��ȼZ>C���X���n�*NJ�!1ꎓs�@�Ar�-��px�~1V�]�c��F�ś�Q�R����!�,Jlf�?,�a�@��xm&�Py�˙�:i��!�m�
��%\2�>x��B�>�-f2���J���7��|�6k�9ŝ�b ��C���g��_`l���;}�u��s���XĻ
U���'n���웨�I�|�Orj�xt�z��^�B��K&0((m��D>M��N��kodr�I���vc<��)pq��8��s[�_OHr�k�C�ۺ��[�j��<_Ӗ�y�eD6j�7|q.���'d�����I:$���?��̃�]�������思kl�B��n���4�0��$kU#�)`��:_��ɮ&
&`%_�
W5�JRT���O$�(ݚ��s�^"�L,^8وy"챾|��~]��&�h��0FrF��0`҆N�Y&�q��fG.K�3=�/7�2b��o��8�����*�>f�e���ր�dЫ�&q5m��t#T�ʜ����h]�hP���
q�K�
�If9qmA3:���	P��fT
8`T`�a:��k&X�F��g�#2G���/��#oJ��D�`�B�v�vR�R���(|�}�>�~��>�E����! PH�.���j-29������`-c~��0��N�H�j���L�*��u�P�$��f�q�'[�����@��<f�i�SC����i���J?��Q�c�C�8����R�E�뙸��t�D�'1J��ר��-<~؃�
�����]��'4����Z)���100�sd�kK蚿[{3�6����ͧ��OG���d�V��zI��a�����N��t[-XAErK��D�|jl�h�l���n�N+��4���ٵ�MSh���N�����$f��--��ϖ_��-���[�ݜU��5Y�FKf����jğ���M����fmO��%��?���Uk�_21_6��+����ܧG�މ���._�Э4_]�uA|�'X/ؽpNɭ�Y�Û�{y���U���NJ��^� �̃�e�b��x3��l�>�iG��m�"��\yF漼p��:k0���֞����U�7c�^���ۯ�֙HM��6Q���=�>�ŦPI�]1H�1>^��O�Qb��=������q�h���fXG��/��69��JC�X���R�ɝ�P�a�LY�A'��iH�QlfX(艐G��x�#6��Zo�WYX���5���i��^�2�� Lݞ&Mֵoã���p�$�I��A��r7�wi����Y�+�7
����Blv1ڳ��>,%Eg,���6�;e������xg�t���D��P�ɮ��gk񆦐7�2N�{�2+A��r)�S��8FF��3���Օ1L���ݚ0��_zů�M�����XE(��e��7�ʞU�wE(�����6+��r�Ym��k���R0����Sp>��E�����C�RG��S�GR&`���r-�޹��Ds�DD��H�|���w���e� +�
	���X`B-����?�#a�na�[��L��߶.��d.zC�=����]꛺��(L6�M�ߘ�9Z�����(H-d��f隂����(���R]l������1Eݪ/�>90hNW�r1��zw�>S���t�1��5��2/��X����Kҕ<�Ƹ�8���o�<_��+㬾i��P�G�a<UQLՍ?�D�H��������͐c�ء��"[��nU���m�y�	c�o��q*'T���V��xe1J�U���zA0$Ȓ��J�s+�Nȁ�P?�MX�f�������3���H�9���I�t/���ߕ�����Hq�pٌ�t��E;�!��ϑ�ܹy)ׂ���"I ��+�`������#6��4x�j�4H������`rLͬ�nRr�E��@��� ���ƫ���B���^��S���Z�!���C��Vh�,������p1��W��V2�E�޶�;lR�>�����΄�Y1O�(qFt��߭�_'��ܤt�����Ύ��ng�{"t�rQo�^i�H)���W ����C&h_F�pJ�=s$���i<�����n��(u֍��R~�h0��<B�i�Q�C~��۳��j�Q�K��$:D.>�"y2�r��u���_?���mI�^_z/Y��1b��!�29�u%C�q���'\�4[��"w
nᗾ�z�G�Q�!�D1�*�vBK+��nb�
TŅ�cL	d���cRIvq�<��8�I�Q�c稜&�#G�9�W!��!��c� ���]� N��^A�t�t�����jfP�ŌSds�4$ơ���<�J�qVf��.�QE��
B����%��M�GYG����	���h+�T*�A��>8V��¸c�Rn,��KZlN�D�]��Vi�� ��[�p��������r�Qt6����6�{Z�Ȃ���	q75��M�� ?M=A� �&�sd/\8:޻ �-��A.B%-������k
7���^$���+"r��T^{U-����,����0��/�q@�'���9�ڐ�916�X�BT���-�T$[Ax(�C�M�UY�)@���֠��w T�LS�������?Z����^]��W�GxiA���;��ň}����4:�A�mI�P�� �DH�%C�x�a7/�-�Pj
'F�ܼ׾���s.�� =���HNi�c2�@��:]��Q��A���H�jZ�����e&��w�I7����/�KQ �̄�<\�}*̒ܓL�^��f۩?�jW�8c b�B� {%D^(����B}��S	-([$|�!E/@L<�V��T������A��e��3O�Y��}q�d�{��K�vȵ+j-8aם�� K��Y�ndŎ��6��ݱL�u��Z��ќ�T��!_�Q�_6��<D��[a�'.��3OE�n������˅y��$m���2/��~T0d����+�8@���>�����Q�fW� �g?�<�V�AI���b4@��zQ��Ȍ��Wd2���G̩�K���H\@D+��Cݣ�Qlh� ��0ϒ�ڄ���j=�� ]��6����EeMMg�9�3�.�&�A��!�I�����կ'����;� �M`:��@�q�����A;�g�'�5[g���e#�v8�{]Ͷ��}����v�Q��F�i4ȕ���'�7rN����a���� ˌ�~���;=<]��[C?rlaJF��{��/���
>���v�i]�AV��^[m����񲽽���I�C���[U2��T�5���Xnf�C#U.y$���9�C�b2�
��`TD����(6r�|xX�Z������9 2�8A-A��4p3 AͶ�R87�`D6���?���n�?dZd�����a�ǖ&C)c��(�X1gq1oЦ4dj�>V6�Z4+�w�Uݜ�{R�X�}kY}QZ1mzs�"t�W�8���W	�bBnŜ�Vh�S���ͭ����6����9R.��X.EvצsؙHM���Ol��c�4��[�4�^6�^K�r%;=��0Ď�R���hȞ������t�[(��6:�e�%�534�}c�Uo��FY�:�m�m�~���m���P�^���쯼o�[�P-�	|�{�:��V$`� '�,{��霡�<G�����F@釘P:k�.K}�?�_!�Hbt%�=�@w^J�l����mj��Z-�x�>��#��B9��?��:�d��=}ي��lk�٤BK<'�K�A�?XV*���mȩ�?�E��Uovy�=ԏtʚ�c�G7:c�]ΞBއNYK��qΔ��ߓU!aˠۿ�:�U�A��ܨ6f����I�{,$�ym��В�Y�&�6����6�k@����'֊ 31��;��'�Tݼ&�O�0�����8��И%�\�v�	4�H���ջ.�ff����]Iv�L����.QH�.�瀮��elB��m���
��e�S�>�F ���	����J��n}���m�;���=�o����:_g�N)L����^���&p&�Qk�)D�,}n��,�F�z$����T��4F�>;n�at�8㠫����y���ߜ���O о1Y���q�	�Ʊ�@����W.3PS���ww�yV�������>SkGd��\�~���k���$D���h���w�"%BϚ΀b�0BgS��_>-ye
��r��%(�vP&<H�&��*FpNm�^���.H�>I�*v���^V!%�Ŧ����x��b�w�ˬ�T�>�w�r��!��sIw���B���u}L��kWjZBD���W�ײ�Z���P�,��Z&��E��ܶ�Nq�ys8~�&m��ЅU^�Y}�_1���`�����<�a.�SXq��6�]��SI�����ir����E�B �tZ����{U2x�tH_>���i����X�����M!�ǔ�(S�cW�h~�JҌm�-m�{����CлX���f�8�=�0�6;3�h`L�.� ާ�G�}R�j��h%pb��������=n���u�k=��;�o8��p�<�=�jG%~%�"��,Ab�w7��9n�И����~΅�Ӏ�q�4���o
�@$O�?]���qދ�'w���+M9Ve��ZN��֒�K-ģ[)F��	'�����;S�J29��b��8�E��Tg
�ĝU�J�/F���pL����5)��*_����B�\XSa:��m�6p�wi�+j�x���˪K��n��<����h��O��U �f�������U�^_�F4���&1��{�N�No[�x�S��\��F��<gKK��!+H��w���([�*LQ��	�A@�Q-����OI�݄T��UK�I(�0[�U��l'0�s����}R3\�X���M_x��F���b?㰹�y���1?a�g�
B�)�׼:dC5�=���t(�Mʦ�]���5���R�	�2h�����������q��o)y�mA n��Ͷ��*A]r�,��۴ln�?�s�A����2��^jO�n �^zY�p�z�I>��d���{�\�;:c�w�P��NO@������0�[Jm�Ca�$�O,��WkQ{&*��D)U"\L��??{�@hV����^��e�Xe��T�񐨭;H�K�g��]'"l�Y�jT��"#4�@D�<�lߙ��Ӎ�[����u��=�YWM-�n^�=:��n�=>#I/�&X�8kɯ�1� �{��N0�rOp�@�gi�}4zj��oA'PH3I�o�� z��f[��k��P�L;��C�0�桮�_�)��z`����+M�v�Wz��[���f����T=�3����I`�I?���mv	�ז���C��|�X�hUI�N�� |��T�����VF6��I��7�a	69E�:N�)��q����<[R�TOF����1�z��dQ��1c�I����k�
�����t�ރ,{� ��w+�C��y������[��g1�Ƿ�TNŪ�)�M�=#i~L�z�2Z�m���6��-��)�~�e܂�N�����3WHH��P6�Eި#.��<lmL%��u�th���j��P �ì��DH�q^�_�5�G�B~��X.��2c+��}o������-DFK�x�O�ᅿ}a�7��F������X��λ��	�ńP7�\�JPv���� &B!���f�A�:�-���;xw��t��vs�|z�5���b/�҇p>f��eЄ����������`f]��Dچ�A5�h�t�H�ʔ�MB	>��O�<`�F��x�B��r6�oTj��6���nGP+�)�{�Ԥ�9B��F2�iUڒ�7e�AT�kï��I�b���tЂ���'��t3I��w��~��:��=�Tq7NJ�-ɿ��0�����W�Y'�Nr)���1�G��E��륝��"^S5W�v��r-����i�7��=T�{�e���C!�L���u�ay�q%�  �/��0��,#��W�\�Zh1W�'�K�U� �+ḏr�t$ǣ�8�-�rU�x�u�`u|���!�9�}CĚ�$P���ÇG7Y�Ǜ	�	I��[��KO6OoȤ��O�sP�������=+ٰ>�ҁ'��5b���@��ݩ 3��t�?��-��9�õ�
�R\fѬC���3�������F�����V2%�a}GH֣=w��.���[���\T ;z ��O�4?,�kF*pa$����F��d���7��P`������f֤�eEt�R��
�9���t&�i�� �X��#�����nXT{�@U� (���X�i�-�e�l+��S3�?���#�a.^x��,D�"q�菦�es�p>��K73����P(�C��n��%3�N��dK���ۏ�C5�G��/�YrZ2�̅��U�k��'
�������Ez'�]�#0IH�$�(���lt��A�R5蝳��YV(w��� y���v��xO�a��/�k�㐏.h@��	��Ґ��D�Y�]��@y�`,�6��ȋ�2T����1N�g�H��0���J�~-jG��@��,Y���0i���m�*g�I�f���F9�iFGkh5�Ҡfط{z~�,/�٠�1�j��y�h�?���ūd�f���GƔ
Y���)q���M�ީn���D�9&ɞ�˛�:�m��5P�}mQ?�Z�B����Ytul/�cy7W2��Գ���UX�_�/�b��j�0UQ8/6��h�,�V��D}n�E4aE�{��qs�P4���W��!�Sq�<�f�QozH�9��OMa3�z�2�%�N�=0�/TXv�0ɧd�⨃`�r#��x*�f��Q1�t����-�/%��|\�L�7�.�t<���d�㏃hri����V�{�����zέ�
�|@�v��R� :�,KU����J	N�J���a���J�����zx�>����:�G;��FH�J���C�-�W�<�l�����r>{
�1(Ն��`_(��1��@��f ��H|���%7 �l6	��;E��Ŕ�O��\PR>����-;���}����f=�u����U;�s�I��/SM�O��[2�c���<z[#.Wny�@S�'0��>B�gz`t�]�쓴Ph�^z�P�����#���R@a���I�f�b̑�$�%����I�W��p�-�FSv��KSf�̥�ܗ��	D�*Z��E<G�P�t�Db��V���a�,r�zx��0���ȭc�3��|+�nL2��nOҰ�;�%�3N�7�#Ѱ�kn$��댮T} '�ĺ����H�L������샣|�0�a��f��fC@s�Bc���@(&!e�>!�eA���]	��|����D�̖W�ZB�3^ˮ�� ���0�����{�_��c�l�S�y�N���*��|KCzc(`$�7���C�Iw!�U.����-v��H~�㘅?p���'����M�*�YZ ��|h�q������Y�d�l19D7(� �$�9s#N�-0cJ}(�X7��ƍ��*��B�	B�"ݹz��R��a��T�g��e�,_��]�b��`�K7���iӿ�ݟ�'��Ǳe�tfp�%M{F���DM؀�V鞜��ٖ#{��Vn�=R�D��;4�f�4���<4e�+,:�6�F��� [��rP�A�zA��"��0?��������u��U�s>��2�;��O���/3M��a�p��v����Oe�=�������@�4����N����u3���{y�=袔��rA��ƭ��ĺ��[��j>�s`{�ڱT�WL(,>mU��L��P�-�&gP%҉���֐+���h��/י���Q~P/�%�-��N���f?�,z;�'Aq�=Ú�MoP(NŔ^�\e +JU1`� ���ׅr#���- de	� %�QFo-�>[8KHr��%�B��D�.�H��5l9Ǚw�侯���h���B�`� ��P���|�`���F�Rd5nٗT�H��e���@��C�� ]������Y[ 7(M�A�кT*�z�6�}h�$����o]ܭ�f�šl_/z p.����$/Q;wRB���(����:�r�1K�q��V�PyÛ��ݕ����*�1.�!���ǃF��_�B|Y��핋6������(@,(��RVuͽ�L�ujm�ߍ\9S,_־��R��������xQ�*maxf��e���u�|�{2��b�꯯�ME���?��I�=1��&��2�L�a̳F8��+g��=e�ڴ��q���(��B���:X���>F��C�8�� v��H扄B.7�"w����!Ά͸����1+���+�gַ����G��0s3���?_6Y��].��lj�Ul��Q���_8�Vz���@�4~�������eN��Q�ރ���ro&��R&Tl�6Ы��ï��9h,|?JdU����Q�#�
���^�� ��)�,��,P(Ie*��+c��>[0���`�߯�aѾ�k �y!ίGQ�1�)g"A����g��H/n��I��6�$e���.��e�����Jb�k�� Z��8p���V9q���g��3?�e���^nW���{��P����X�%bp�IxU��y�A��nk,S��5$�	�ʠ��{�IԱ 8+e��.�c.?��C���׌��u&�xQ?fH��Ֆ�F�ϏT-����	��yȘv�&�Y	oM�r6�Y�EU��p�%�]4��F���Tٺ�X�1rq��3��9�"�B|��)ov��c�#sil��gGT/�ɻ4�(��Ѷ(��!��"���d �Ԧ��tW���޹I�^�v�S]�h%P�~�]�U���$p�A=7��,�$	�$-�"l;���GY5�jM���fT����
Ȭ�=C��%;ʷ�-��׸��a6��7�\�yt���Z�UV\���t`D��߅�u[� ��畯�Ӽ������Avt�mp���v���V
���Q݌1���J��6���*.��F�_�El,@y�
y��4"�L�œa؂�Hϵ�0�v�~N�����yx��h�x��	)��z��
T�XBhF��6|Fv���lC���1���n8H��moOk�v�d�a�ʗ�-��:���:��Xp���;2g)�L�� ���$�(���*�g2��������2[.�IA��	��}����ρe�npȭ�5G�ѕhO#�����#⢮�B�~Nd�n�Z�%t��p�l�+`a�6�-��tKrw�>ԙ�YdсK���p�/c��B'm0�
f�������kNXBo̎12��pt��|�.�c�,�.��/��Xi����t:J��j�Px֗]u���vq,kۏ^[i̢�)^�[�.fQ�δʾ{���=b�態��-.$���6��,E��d��N}g�[����씱�`���$j��S�����!E�ϴ��J�qw��|c��0c��>ə�#�᜸�qb������,��z��ٸn�X� l�x'YT �/07HI���*����gk�F�$I�>�i.��>v�"+�%?��+�v#dφ;��b�ʭp�$�<(B��` ��Xi��;�x�~�W<>�O6k�ě����� ��j$��Zc�
��Cm�[��ƚ$�	���9ݓ��f	L*E��:{��+$���A1�rF`:׿W�s:�F�j��d�������Iҗ_PK3  c L��J    �,  �   d10 - Copy (2).zip�  AE	 ?i�?n$�}�Y����,ԯ�5=��(6�}����z�c��oZ��<-t�~'��&˭��5s8{���k�q����l�ڒW"A�(��sWn�=��[N\��"]2���t0��I:2�)������./�<r��㿠Q��'$�V��V����Zq�pH�k[b�ɑ\ģ��s@	�9��>&BԦ
B�+�(LKW�`+C�*���U���;����e�f����!����O�W�͖���T��=�u�<�4��U�+�~V�����x�l)F�{C1�[��O��l��О`�_�Ѵ[�RO�k�2�Ga� t�Ɲʎ�O�qr��ĕ>U����'�g��qu=,�h�`��ĝÁu ���`���<���j�أ���Kc���5��f9?��&>S�ݮ�%�xO��<�3ڇ	@�;-�%�j����ޡ&P�W;��J�懷�+׿<8��~N��%5�r{Mj����Yv��>CNYl�#!c#��Ƀ9v����2+)���pqeD���C�!��tq<�@��ۼrtȌ8Dw+/*IPv��v��T�YgX� ��J��I��Y�$�G"�.���T�2��)<�7P��ٔ�n�ס�@߁بs�{~	ra�D*/X�q���#���|�R��шjx��ǮH|\�Dl�ƅ�[멥FP�z�2\j4>]f�6�	e�Äb���� �XLKw��3��VZ�������������b�>5)5pmDn>_�	��-����&��5�P[����]���Wט�@^���0BZڌ�p���S���ˎxN�:����ϻ ���_l7����5��~�����[��vƍ�#v�E�^���f�V@c���t��Dpn��Ƕ����	L��@��Pr�^[I:^m$��ګ[�mr��E
x�]-1�:S,uG�4S�f�����f�+V�oWv���T0y�?4��q��g�M�8<���y��Ͳ�����Cq�)V�^�<{rM����{۰���D���{#_�	��5e������xȭ��`��L�6;
S�q�!�M-L�q�Ω� �!YIۓ��}�T�&��nD���������3�W٭��pNg%*� '��1���V�eGA���7������h������ЁD��\6VL-Â���"0ZH�Ѻ��Z<{�g'�d��<G:�'�Z��.��{P[�ueDa)^O���'5x���gq�"�G��j�Yi��	k3	�����ul�u�C�g��:�B������j������#-D����)�˃��!��C7`�.o#Q�Mg�i�"���UǮ6��\l�W�N�SЀ��`
:Z���?;�]o0�6��E��%���ש�/򌄙
�R� e��l�gJxϊZ��ʆY�f��,�4�k�'�n�ٴ6=���],�����3F�/��>��PZfFO�sG�?1g��A�F�Mg4dw9��E�lӌ��m�k0�f�KP�������/�����l�S�,���k;��B�5��u:6u4ȩ0(�!�Ȕv���S�=�%�ص]ݑ�˱�ߚ��g���H=u��˦U��	���_��������Gܱ�����$9���x�����iU�V�=��'�G|1Hqb:�J�'9]-j:b�=�����)�;��̀�U��s�Н�IgP�����N��U���������O�~Vh�����QBь�vV���L��&k�船R����@��n��f4Ê��O�͢�^ ^0��PXԭ������yx_7����Q�i5�cħ�pd��/��I�w��K�g����P1w���%aֻI�9���N����ӀcWi��1F�Ɇ)S�xS�O�Ⱥ���/'JEHԢ���~A�ď�ߢ�~�㝁z��$�nUl�����c�[�����f�2�d+֢��!��-O{�ZQN�LϘ)��>�b��ɓ^��S&Q���zKl�1�E�^��~�$������!�͙`������0�(�U���h�y�h�@��P��"t�)c���/��^������v������}�z��S��3m���	��x��[B��:��5�zpI,�6���F��Q8��=Q���B�EZK��mc���\̫��#��� n�����[!c;��g��֠s�Xߚ�ݷ�/�O����ZF��]�Q�S&�� �<��[:�������H�K�Utt�����~�����6Kp3`� T�F�i��g$�y[�]zi�8�n}�͒y0�\���NZձ*ZX-u�|��F)�dZ���{�.GOg)�&y��2�嵾 ^�b������¥��ݟ���ԹӼ>�4	�9RPH�NU)]'�?a�����!e�Y_��5�̢"���t[����u�kf�5"��'K�(%�q�:\�v�Q.[$I��)�Q�I�)���e��g�q�_t>���x��,�<U�޿A�?EW��;6򑲊���ZJ�p�|������H�Xd��>#�����>���fJ�ڗ��|�9��3�� ��Oyu�vL1���BI���X��(�Y+�����W���]>�s&�?T�D�Q���L�0GmC( ��"A���Hpj�U�!���&�pa�_�z+w�bt����3	����}���Z"�XJ�p
���
/9����IBS�%%<V��?J�6^��7JP�T�n V�Y|��qG�c�Z���U��HHPB_��,��:}�ɆO���	yq�`w�8H���}�HZ�|��|���K�}E��(M�?��b�I �n �E���emq��CV�L�
��Jd��yzۛ]g�ؐ�#ɽ!�%�
�@���l��t� \Z �Cmy:A���1�{��7�O���M�U��
e����P�8�j��<��J�"b��Oٱ�/��I����K�\Fv��l�ކ
9� L�IC�S�|�׈!�� ��?��=	��;��m#�?��Mͮ��Я;�OR u��E��&�{�Rx��E0d�S����h��?�ʶ�Vӭ�q�+����C�'�?h8�2�~�P4�����X����8m�~�z��9�*�V�,P�hg����^�p�+�;���ϑ��O�၄*�3E��p��3^����M9��!6i��TU��ev����i`8��,̟�n��V�O4%0���܀�\��U�4@L��G�:�.�|8�Uշl@Or�I�=�Ŵ���m��d�Q���+�R�\6C$�9=>G�'�Q��͕N/e#�{��Ұ�M`�tA�M���{�@����QB���8{��Es�y�Z/:Lw+��e�B�]t#�`O���J����o7�y�G��7Hy�h�z3�t3�G��s[���n��Y��p�EZE��8f��̇�B�~��>���5�{
��cF��[+RǭG_S�m�n��2�	z�S�WH���o�p� ��8&�,�A��&f��ELCE$-]{����3�HB��wu����@���˥ϜZ�x5����]�I2��"�S�T0}.�*)�5N�e�O2Dh�(Ԟ�@�����z�c؍}xd��'�wq�B�xU��g������i:��E�O-��/\˴�E��ڱ�NuP��Z���� 
T�9r���;]|���ˑg!���2R�@r�L��ZŠO�+A7��hר�c�lA�䋉˰�ь��'IǪdLS�9@���N-���6#���'YU�U鲮 Y��2:Ա��.�����G^�Ls�WD�-����&�9��D���#ozo���k{@;[����y1%DJz'�x���i���NR�Ŝd���'��u�(�nn�ZH� dv0ڢw��40�AR�u�ֳh��ꖯ��6<*��@�N���%�߻<��H�̽U�w� s�!,L9�ƫ`�$/ށī�������'�P����!C� T\?�[�.��}#��Ld��-���4D\�*O;�G#ԷN��67����@�����G�QO.g���ɀ�*��vM	o>�n�` �K����;�=�;��n�g��x0��R��Ι��E��Ɋ)I5xHE?���9I�!2�K1V:)��W�ʊh-����\�,��{���T��+.H��+��9�>c�
RJ���oqf�ޖ��L3��j�����-��V��Ho�F��%�'��Ng�@B��K�g����l:�G`�m��!�r~E�0�\M�a*d�`����Pі>�2�;�$�nQ��G¼$�J��*/j@ 񸼺�Vs��cz:av�"�HK��7�t�d���̰�c�g���! �3V�(rFv#;�jnܗT���9K����=k꓾��G�C'Qt�.��5��R��:�Gjˢhڧ���h�����Њ�7��m/w��r���4�+s�s� 7�yQ��|�]w3/�j�����[��VXP��N��@�Z$�In&s |�;�pG����[G9�&xc
FH����ƠKW��
I7�����!Z�CF�|NA�ȅ)nX�Bz���`�Y7w�S"�����!/�k�{�`�C���1��S�)4{�5̘$�e(-��Z���42$���{���hw3�ۆ�:s�'��B�M�ƕ����^,�N$�ځ_SF?;䏟y"S{�j��e�']36-���+��f`:;;�jH����[AxD�̚tq;7�B�_�5��_Q�T��%���8o�}�$	֛��`b������X��$��@�]��#�Ȥ�ƃ L��y���t��a:�&g�כ��n�W��|�$^5�1&fs�E��ZG� o�v^�)�D��x�m� 2�z	hX.�׹����1ּ��w�.�)��ܥ<*p%5WJ���
��u�u.��ZBw�,�lPV	�N%�Ƙ��;�Y�p��|� Wb%����H���%�VN�_�C����7��`�E�Zۀ�X�q�"b���H���yf��I�dx�䘔|6~�`w�p8$�U^d6�
���kANa��\�	{q��af�.����V�\���ߧg��t��ӯm��j��A+1x���PseTˡ>���_�Z5�_Բ#���9}hRD����]�ԌuacG=�$	�c�=q���.��{�9͚����Up��J4k�
a�$��P����x�k�����]����+�3ٛ�<�n��V3Tv��e��Lr�7�!o����]y�m.�z�xX�JZR8�w��q��T[
��X�5�v�B��~~2uu�*�f�}V��v���+���rf�|����r%�,Gd�-X����ZY�c����z�	�脵�28�;��,'�?�ܣQo��<�ɑ7�r�O�y����8�c���y�P*�Q��_�O=�_�̡�?J��M��^x���+H
\f�u�xҫV���EX�5�8��61T��Ӛ̾����6�>L���`Δ/>5X����N{��7� 8����<�Ɵ���)��G���o_�y��<��ԁ����Y�5Q'���-�a����wf�]�~�M�妡vm�z*�*%S
Էw�o1ڟń40'��W��W@��|N�����gzEm�!�n#���Bʹ�Y�y�H�00�α]�|vV:	aQ�Q��^�hB�;�:ɓ�#����F��/#dr1�pק�D��Y��x@�lEP``3m~D ���_zۗ|#�͞9.��x�y��޷�O�iV)� ��u�6xm�y�&���4�椏r��&���"�@���>v�o��8�=b�[G��v�=SWǣ�g���dے;���V�� ��C~ B�=|7M�x�V�_�1��Srpf�|n�k��jV�C��Q����
/��Y��*2��wO8n?�����ُ�Y���+P^�)����#����[����� �|�3+��ѝ�M�o��<��l�B�;�y;X4'w0�s
�����9����P#T�ꀂ|����|�h���]���D�~ z޳�9�Ф��[!�5�F��O��O�߰P���ryů�/#�E�bɰ���W5v�HP�+(����ĺXv�k݀QK��b�$4�`��� v���T��;<��.������-+[��wxz^���Gr�;0����h��8�I:��BX�:�X�Jb�+��n&��Ep�o�C���S2.8rd�ŪU�r	}�<b�ť��|e'(yW8܍�t�~�m^"���w~�ѽ�O�C�%��2��6�F�����X���vKP�Eg1y��?R�&�]<��$�x�z*����|�|���$xh]+r�t̯>�Xa�����-J=��J�=\�8Th�S+L(��03�e球G��יB��`�_���=���%j|P�*�g`����v��3!ܳ�K�g	[�>�a�t����k��2KE��O����G����1�P8��殌�hJ��HC�瓯�y���%���~�sḚ�)�$����T%��1g4L�4��G¾��C0G�N3J�U:P�=Z��k�v7�?�u0|TE<b�����A��i-X�XF"���"tb=>x��b��KM���E�%a�i�c�ݮ����(i'T�O-��ñ�=~yd�E�*�o�7���ɿQ"|�� �����Գ6ӫ����&>$�
[S��w��N�L�7f�H���kMuPzf�c	M��}�*�d�X�'r���r�5��$L��P>���G,x�a�Ŧ�6�thʧ!�d��Ӧ3�i�R�dK��v����q�Ol���G�_�L8*H����9�M�K���sdN�[�h��82e�!WL}����1!2�� �]��bD�Ӽn�a�]�
�"��y8O��͠����\��3��'��yI"��`P�6�Z���K� ��l���r��*`ޟr��)���]Y\�� ��*���٧˗�(	2���:����]��
TU`eJ���n�YWG�$ڈ�3!�sQ�u��M�̾�J�WY���
7��r���� ������gf���\(��Ls�����Q���f@wc�M��Ƞ��	"�
�[@f>�����I�^#3�d�A�]w��A��b�>����4?��F(ܼ���0*?X�POT��e��3��<���>�8�D�b�a��.}�4ؘ��p�Ϋ	�����&xP���}�� �/$���qT�ș7� ɮ�Я4 ��m;#u�Z�a�Ų&a���� ��f?�5�c'�c�IҸ���}%:���G�=�W+�aF2�h��dU{�S�7�<�:�ټ1�+�$�����ă�O��-�	ȓ� ��d0���:���'��m�eu��\>	��{78<S>��Ѵp�F��ǋE��n��oY�K��c�\��}��w��]���㞓x�9��6�?���	vk���}G�b׸*��d���b_���nߞNe�g�'#����W�T�ɷ�6����~K:�T��B�x9a
TI�q4�����^m��-�
�wT�k/���ʎ�s0�����D|R�ֺO�t�e�<�i��A.a������sR�ìA��>o12�TjS�FY��.[j�z`��d���|8�WB�H��<���GvQ��Y�U�$�'��,Q'8�v��Ț���0^1���i{'��IK�yV���ڞ;�����Ϲ?�1%��@��{�&�ZeP�&W��JUײoHz,c�T����R���mM��nf<��p�p���y`�$<Z��`��1i����ݽ*S�$u���+�k��/�K����JdF2d�|}둃�o��2��+�]��23�Ye+�Q�O:��K�M'�FB�����>�P\��H_�K.wxw�l�t�\�~R�-�M�%0mT��F�+�q�g�՚��`�S��rJA��H��߮=�b��]*H��E�VaTf��+���$pL�;��4qio���%	��C�̴\�'���'�����HCM��E� �]"�1@�;Pu�S!�0ʊ�u��}I���s�2=b�x�.y�5�~Y��FUt4���׮hF�'MH�׎�����1?����q�o��i��6��1�8���肎��)�7�A���]�te˪):���N�~ �.�Ō�Z��>������Q�^�Ht{!\���Ŕ#{��
^�%b'd�������L��3�L;��^��'��E�Q�N�fTH쿫����������xG .'1L��T1Pӝ������d�*5C%�����6�2%jv��N���YX"
�Z>������w�a�N�����*Ŗ~+nI9�Ҙk��(z��ޘ�q
%������0S�c���(}\�j�o�	Kf+��թtP� �W{EP6yH�ij�2&�rMq&�&@�$O/��S�華��"�����B��7ߕr��a�v������7������QĸhG'��JQpP��(��9�0K�����خ>���3y����d�j定��|�#|Ed5˟�c����uu´�oȟl�%�B�`� �
jf��G>?�w�k.��ԵՐT�okSr0'e�U�<���tp�Z4rP���.a�ʑ��cV�ږ	h |�2��G�eOP2Ӏ&.��fZ�"V���1�\~�ӎ1�g#�|�w�b�����?�^T�:2��`�qI��=��qG%�X�V�f��*U$��t]:��S�"��8B�n�K[�9Z$J^��=���!V̵R�C[��w���6nƐ2&�n����流p��]���_r��>mDz�ˢ`��ߨ�6Ϝ�[����Ɔ����Tru݉�Qrp8r�u)�a쾡B:�� i+���~��������V>1�$���|*��d� ���vo\ɤ�G�'^�?ں}q�Qhh@/��M'ԫ
n
��F��NR?m�C�J��c��Y�*����O�t#M�i�l��g��e�@6�R�,��%sD�;��O�����/�T��R�E2qt�l���mD%��w�����{uFg�a$�b*#�]⑗;���4�|?��)B^�b�<=T�	u����^�Q�K�����}e��j��_onPc��K2��k��ހUW�eky���vZX��PN3hůp\7�(��	��(_�{6I�R�V�eE�e����P�6�����L[V�q�VܴtZV5��i����^��6Zw$ׅTqW$�3��!g�[3hQ��B��8Ϧ���H� �B���
7N�"��N�D<,��k�+d���7�.��Y�pzο��x=����3����T-=�dة�]k��q���.�"@MG}ӄ�0~�13҃���p�á�a�Ke�B�9y�@�t�߉8ÆFņ�]��������B�Y/x񐴈#(���w �
[;��k�S������%��!K0l��ݰ��D�����6ׄ1Ye	�k�C	��5΋�+V��e���m9�qm� ��(��!���F�Z�&�l�Ç�c:i��X����L9�L��Pa���j�!�ĺ���/�bPRlhY�¼B�9=6�P0��s�劌�9��]�����!R0&�'�F�۩8)Aб/�=��w�m��N�N[(�=7sz�Sa-x�n�Ց�8t���1#.��~�@��������ߴ�,`U�}��L]*�ʖK�o���w�DA3Ys��O��t#u��A���[&ɸ�+��{T0�sý���X�p��l��T`��g�����
�w7Ìq�z�~˰�'%��x��ݙ��~#��ϙS���1��y@ ?�&A;G�qz�l�y�eBi@��Î�AHO�:��-R(��R�&׶���6B㭿'C���	2r<4Q|�lX�;�ǅ������4;� i��9�ލФ��uֲX�}���;V')>7���c$3�Wlc�V�/�i?�4�=�g���)|�=Xg�U��i%�L��,�g?s���0�wo>������:�<u���$��	n#\����	����06K��u&��U�)P����؞Ư5�5~Q���B�v�*�*͒�[�NP�E�Ȑ�ː�5	��_�FZmr���ݙ��/u�C%׶D�"�y��+3�&d�(/��-H^U����MP�BH%b��֢%�g��Ң�WS��+����s�i;a��=�Z!��4�G�F�(�\(!#%�������"z��`^����:�ߑ���L�`�e��2��aD�ғ�.�JL[*�눫!�w��[��1J�Q�����O��O�Et!ϥ��i;J���,��#�"m?8���Hy�BJ��܌�Ӑl �(�l��c�����F�"&�ެy;��$��I X�\7��v�/j�%����&�WD�|�������Oqme��+�-�\�.���g!*7��s��p/fQ��Ԋ�}@�i�� ddX�dR�e�>�P�z+�	����P���1qg�
eX��G[��F=ke�x";ۚ�����+�rC�TJ��1z�	���i��Yz�%����n��1��#���;�<���M�x5�Yds�L�1GV�����ɦ��C���N�a���t�o�`��ڄ�Z�-g�1u�[�����|�{k%�ń]�8�K/��^1��5n���q4�q��s缈��g²���0����y�f~���3(��]arW�������'��u?��+MV��!��q̿!�xB�������H'#$(*�ᥲ����J�A����{9����)�A"�N�SXu9i��Мx�tu��{%�d��(�crؼΘ��G�*��`fOu(�� Dܓ��I���a������(>D �=﹚\"�L�E�����{�W�E큭�7������7��g��L�!�}S"���u7N��d���.8�B���$���^+`*���T�c����I-�T�#MJ�]L�� ��ѫӕL��y��M��>�6�O�	J�xX�NN��ˣ���s;bB	y~pEū�5����1��������\\ޞ��x�/��2@6�(��IQ�n@�vO ~-��yo&d�h�B�#�h7�
��۬S��Kǧ��Ԭ1�^4�:7$F(6y�_�<aT��c�NەgՑ ��U3`䪊�晽i�%�zM�������J�Ǩ�`��}I(����*��`+
G�<���Yi�}��CʤWz�j��I;���y|�j�����p-�s}y`Y�`�2���A ���[�;���9ѭ)3����M��]{�?m)nQ2��2򘵊���)��fe��7A�*��N��j#j�}��V�Ʒ�0��Q#��`S�g݀���JYjN	V�A����F����8J5L	�h��ߏЬ�P�+>��|J���!|�>B��ut=+����Q��%���nE=��1xB{�|D�c.�֌���<�T�K�4\� "�@����ٌRp�#�q}"E�ih�v�h���ə��X�O�	T.�C�S�0��{h��p�R�\g��ԸH��P�	sQZ�Fl��	 ڷ��x��㗬�9lU�k�PK3  c L��J    �,  �   d10 - Copy (20).zip�  AE	 �F���B�n��Tp����g��R��qF,b�c�������;�^���Q�d��� fv�^mn�~�]�������^N���:B�"�pu��l�E��J u��#�J"���`
��q0����h*G�6k[�+�:uF�������F��I�)�0��QB�~Y�u�W��� ��+���9���]"���AWeH�J�)�Lb^"�H`���yz�����5O"Ո����}�}$�8�R�R�(��߭�$�8O] n=�?K'� �-MT��K2As��Aof�!��;T�HЛ�=J�O4�ȴ�3ni������ו���$���]˥]�P ��ѩ�H�l��"�H@�r: S\���dި��u%[���3��vj�tI0ڏ��>�nb���~��1�o�CJ.	4�(��OZ3鰵���u�Pdư.�"i�p��(T�31x�����Ʒ�������Z}�X��
��:��cvJ|ŘIZĚ��=?�Z��:"�ۂ�2#�G���S��no��e<O<3�KB���_���o$1,Q#&�E���HoEZ$OD޴sC�*�1I#�.JݢB���k������f�w�';�'\o�}�֫Ш֤O`aM��J<%��&>���G�����L�T�<�Ǝ-L�R"�� <�s�X�]�o���`my�Z�ʖ ��8�'�vb�H�^p@*�>��"=�AdN3���Q����֒�$�3u���l�/�>�y�ި�TU3in;]J �f1<��0k4d��mMC�9�.�A��q����á/����gd��Cw^�}��*�����䲹�H�3��ŪTG^a���P����M�u�?څ�B����ff��O~�lW���r(}�kѐ�^F�°\���mk�*�Z�2����x�H�%{���rp��d��34�@�?��P���y�Y\�0T[^��Y� +�&�Q��bqX��s�+��a��@_ag+UnC	x��:	׿H��. �ć]9s�ig����+9Z[�i� Q������$=Ρ�������C�/�Q�Y��o�A4_k�JDM��dc��#\�oЬp"*�~=?)�cu�⅚?Q��o"�7cf���~�E��+�����'7��"[��"�v/�"�v��T�\���o�J�����bʭm�geS��+�`dc��P�oQ-�R$����8�6�n�!3�:��E�Bb��ս�fM�
�h��n�sh�T	��p�˻��Qd�r2�SfW��S�l�� �XƉ�L�=���K_�p#C_�-��)��R�����j���o	���%O!��C"��@�Ss�5�s'�Z>
�pp�
���=�%�����΅N�0�+�+c�f��Zӻ�����Vp�IN�.�I@XE6?!��Xd��ߨ�WS'�S�i^_�`7�E�����(�_����~�v�y౴�2�y@�/���.�K�,��(�M�b>�X�a7k�Д'��_�Y��\#��;��p��"���c�H\�)U�O�+��մ��P��_e�G���M�Q�!��z8�0���n�����l�5�GWv�T�7���_�V�~k��~]5��!N+�ɦ���M��T���;X�$��Z[�v����T!!(��e��b��������r�r �Z���R[�ju���ဗ���8b�M(j�;�O�':Ǘ
�,������P|�R��c����x��5��l�T�h��ʟ�XP��*]�mQ�9�g����N/�f�Ϧ��s
d%��Mƣ�5[���� +��s��!���b)g��0E���h1H������Pt&(,;��g��r0"�VE��cR;�h��RC�CM�������Gv�V� �*�%)�	�_U�
a���҄S�k��YĪ����4�Q�7�x�F��M���\d�S���ߚ209>q�z������J^ JE�N^2}�Q���	��� �q?�������w�d��CϬ����ˀ��&��7gMjJ\^�XTo��W�q.H�����l�q=�.h~��������ɟ���_�qv�z8V�ߊQ�	��.���4g5' 4
�����ѝ��'�&��R��Q��8�Kᾁ���\�L�AW
�p���[[[Xi*-^���$�_�]�-��@�#ްs���U���܎��tϭ&�<�E�O�h��D>e4��eT������C�E� ��s'���J��+ks�R?l�I��������Hj����-���P� �P����0��W�5����䍄Ɠ�����X��T��NA�!/���޴�μ%tS�RU�)��,4�w?m��Q�%'�b�$��X7��kZ"3���|�T��ʢ�T���f�2k~ҹ��1ht
{�̡k�^�,_-n�d[zh�(�T�7;��:��������h�6lW+�Q�hm���sJ����`AR�qAUm���$ٵ�4ޫB <>P���@�nr�1P��>�A�u�I�}��uO�8��f��
֑L{e���d�K�BS��]u�(�����ON�U\uF1Z�����r�x� &�{�=o�d���m�?��'둻L	�%��9�{�H4Py�Z�f7��"HL|\6oh�dN���dg֙|t�nvI'%7�y��N�X��	L�<���>ﲑ�GP��Έ\^���&U�4���*��s�� �wȌdn0W����������Nj"8f�P�T�7�·���ī�1�u9�b��m�m|;+9�,��`"�\	Yx�5�5P��R�u\�},|V������;qx�] �)7,獆/w��A`��a�~*I)��D��I��Z�ޯ��<+�V,k��y��z3��k�::�	�#Mm�
z���I`��/�e�˿���ﶌr���YS4)����*��zC��dͼ�@�Y�5F�%c��Ӝqc��զ�?�am֌'X����5�l��p�j�`���[Ҭ�l���%���"���{!r�L�=�?�0Z��N-�a�@\��UN�ؕx�[>A���4T�����U��rFK˓�����AwAk#Oi:� 6H����CbVM\ >E���S(��[�'�^9�G���
d
ߔx}i�Y�F[���n�S��Zc�Z��9��o縯��`]]hY;��逆S/���e�<�$1��w�r?`����C",��_�p���pw����8�;M�!����Q�1P�I�+u��LI@��������}h ���]\�aVnm�|`��Z�*��yh���O\�����ZB�<��Oq���8��JVo��]5U����O:��v��R���
�Kw�I�Eޠ�LS2Mo��c�K麤Gsˎe����W(��+vc�Q��׆�ͱ{&��kٟk-|FV�h~��Q&(O����&�d��
����q��]!"7.��~l�;؂�T\t�|�g�D>�	Qʂ#[G����S��;�UA�`#��Ew� �j���E+{֠a<����K$}u��m��	o��_�����~r�soɾu����U���	�h���AJ�ӟjُ����l���O�'�����^�nlK����]SI������C���<]���@�(����v|݄�Ո+���n������W�JG*��௓�We�nE~��r��H��M�*/��n8V�@{F�\/J�D~�|C���2�Nd����l&�^I����֙t����5"��JM�c�C��6?���
��(�\�+�{�n<SJH�=[zw1Q�*���9w�y��!*z��fY�"
u/P�_>��9��(r1�2qX
�L�
�Z޻�W���\�Qe�4����b8 ���v������uFI��C�U�EYn�,3�ո(ᷝ�0xa�s?L�8�s�7��4��)�E�� 9l�R�r��g	LP��`�v����o����K(f�4����2��	(l�d�B�J�N{�أ-6y��e�	ɀ!���ۆ�6�,�k���i�=�N��Yx�B��|����/9,��r$ T�FG�]��C ڼĲo�R���Fk��>`�~7̜�+��FT�sG�[��'�uu^���/ڷy6v�v���:�a��ѫp2qA�Yw�����az�"�C�ӲU	;$�
�v��VR�������:�6�r�PU�~�qA<�ډ`u��u1��.m)�dy���3K�.�U�s��������Q��g���&��LVIk}��;���G��v�p�S���С� �����Z.�Vb,�oHN�0"]u@8'-��O���Y��w���ֹ�	 ���4�S�� oK��o�����~r��Hw7����':0L{m�bEu�	�@��[W&��D�h!ҦБ+���-����y�8���:L���j��ͭ��3�G<�8-A�SyJN�tqC@�����M�s�O5�{��zeIm����sQ[�u�����Qıl��4iC�n'>>EӶ�V귋����r�X�ЀD���&_�����*)$�t��p�rs�{~S� ~�,8Z��C�Z69�'��9�����a�?�R�� Qd
��;nۉ���H�JQ׀ŠOkO������%��ģH�F%���:�	z��頪����Z�E���S�H-]�?( �6˲����-� ����p��R��6��:�)�_[ǝWsB��(��S��֥"���?ϗ{7�����^A$:�m����01�PmQl%-�L	���܁,�'�[��()��Gps�D����9����E���/�t�pW���lĩ9=�uyϴjkRO���n4)PzB5��u"�t-��SK��|�{�B�S���u���p�<c4�'o�)���_|f��߻���RGzðu�`iv��cCfQ�%A\�&La�	�E��������J�#��f�5/ƶ��8� (�ϣ@^�W�L`q�LV����J��q;��2$E����?=���y�8֖�AZߵ%�Q�����J ��nT(<��~Y*tBQȸV���^�S�o����7 �@�Mгqq�m�Ia-�����q�] b�$^��FC�/.j��j�A�����e7��@�	�w�� d]:0�:Tf�����LP����i�j&>�e��WA*��]*A����\Haz9!M��jF���M �?�v��Ŝ�*�pY� \e2���4��Fp�	r@P��u����FD���10ܤ$	pz�O�,=���EmU�8H�~������<�I��e�=��=~)�r�۹��_�k��yگN'��(�����0�N�*T�V+C����g������0.�u��O��JZ(�VVJ��Ӗ��B��{)	g�Ҡ����go+J��G=y�h�M<�,6,˫�z�ص�v�j�[�dh���&�����7'A�'��r����
5-�J��ߛK��P��%ۦ�!��7j���}~�SY�|�~�y>̴���u!��?X�iB���Tr!�u0Z@mq��h�5 Q� �O%;�b0I��+���v��Q��PM�M������[w�z��w#:�M��F�H1�ʙ0Z���͂��e��Wk~� �@��h ����s�u} �H��8����G�>{��#|������/�S9<`�c�z_����?�9���843�=��Qt���t?#�<We�҄�m�q�n���V �E��ҁ��Eͦ�;�6�;�����"Z�D���V	����}U���E���Ê�)��H����mf�>ʪS�s��^fQ�����Ѱ����g���\Cɏ��X��4�VPGH�P� �z��_��)˄��h�ۅ�sY{<v�����o��� ��M���:m��>����rP�}�|�/�gh�P�V� ��_bݹ�sY;Ķ{G���<	:D��e��#��9F!mv�Aږ��H�`8�#9�x��O���@�����I�t�i?�^u��-��V�K1�\�_i|��	�"ٌ��
מ��������u�.mR����9���8����'�n���b,0ñ6�)����#��v�d���(��D@s ��/Z����W�R����p�@ ;���,0�%O\��w���cC�l�4�p�;�$�%�7�X�1�Q<��� ����o?�O�A��"�%�\)VNk�x3o���r�s$�C��P����v�x�|]V���(�q�g�oZ�.��+ߴ�Ds0i����ME���qo�\{��ݨ����B�!���;�'���~�(3��)���"�w9q�y�<�%�����o�6�"B�V��!�]}�,7��g\y2�?*���3�0��aU9Y�ԛ��J9�2�ԩ>�L��)( ���Q� �뼊2X}<�K|o����?�=��RT�Em��=��יѿ'�i��?�"q�ZP"��-&#Ej?U�����|��MI����K�"��Ĝ��IЙ��rƜ���\�{�,���		�ĥ� ���~�v��?}�
��p�C��V�S�L���`�a�{��Ŋ��
D�km6~�jM�Esb���d�����+0Q�l� 3[[4�X� �Q@�D��B����ڑ��h#1y�o�ק���Na�6��b�u���*6Q<č�Y[1x5�I��iy}6���[:z��S�x1���|�Fa��W�𔪪4)��ݛBM���p9-9�'�x�m0
Y׍(|�,ޒ7"\���.���yZ�-dK^�(�0���P�n[�$�C�0���q��>!�M�bXѧH��B;8��`ί5'L�p�0�Gi�P����Mcy1��>5�*�Fc�{V��p%<Hy;;�#�Ղ:��&C�#��I} ������j���D��qa�a}�e$�E��|�Ǡ�G��ٓpv��(:[M$bKU�FZ��(Ww��DmFįX/���%����Z�'�9S�[��i�ZДv�lL&Ҙn(�O��S��ivI�bYM#��߇7�z��|o��)#%�-�����0��H����P���U�h��Z�Š֙Ytݮ�'�J���`\'��_��x(%����жp���Uu�.�8j����mS�J�P�ݓ��B�Ƿ��a러�"�pΔR[��Ty�9��dZͲ��������!�*�%���q�N)�O��Hu���%')%D帊馯���9�u���� +o������$�>w��4�Mp�jVǼ�{g��k�0u�R1l��{u)�q��D
��诩mHEMG�ߪV�+S���]U� �~��sT� Jx攉E��,�غ/,N�BP��#,��#űi�E'�ב;g��Ё:ڲ�d�\��G���9ۧ�
�
 ��ft��5���q
�dS��)˰�p����'0����k_���p�b�`6�F̓��z��;;7�^(d0')W�΃����3��Е������p����d�:�Bf��|�}�.�c%$���?>g����B�M�B�����X?]4��5f����|[�f�?��iOQ��.T�E��8"��!2�r(����Q%�W�&�_JҔG���e'��Aw�������I�����S9���e�={����)����������$1��'!��&��<�>��{.BZoW5�DL��jΐ��ǁ�Ŭ�m(I<I"� (��t@6�(�A�м��<� ��2�mN�<}��
�ڐ����c=ߤ���@1zGȳ�ߗ�.¥��{��
N�x���o�߯�@
V"��~mNێ���P���`6�P��b�YAX�}®��FQ�a�]S7���ʍ��#���:�'��w����1d�)}v����U�ͲA?��>�����x��8����|��qߺ�ȕ侓DAn���tm� ?�&�	�����ZI�C�2|�\����VV���|L��Kp�H�4��Wd� ��A
ɩ^�B��/61��h^���X8pb�"�)���V�j�Ww$���=cl��\� ��<�H�d�G(I��Gkn��7y�:��IS��O�;!d�y�2�Q���j�������u��duA�q����qF��}@��_KF�O��v�f��z�'� �.�)�RJ���E�ݶ�0�#K,����&�Q"c}�g����{<P�	�ۯ����;�Y�R��_Xn�k=����qV��)���sp�w�<�&%����,Ŏ����j�V��4��	��c&�w��0�QaS��iU6@�|Ie��2Epc��:�L�G�dWk��?[z��c�r���{�f�W���'!x��	.����ŋv��ջ����t:������/*]AJF/�qڮ�8��f�B{Ɛ�u[FS�:	�����ߒ߉�+�������X�·�R�����L��RV�Xޢs�s^d�H#�Ѡ8����#�*�{	�AĶ����a�q��S8���%���]���i�@�Zl��tkb��'?_[�k?$��UZ1���y/V�
7��xc�v�I.��R���b��)
ƪ $P�q�M	�T;<�J�c:>���++sCI�Z	������Up{OM���" ��g��)��yB|������I�m`m;�6�bF!�r\2J[�F�۴��2r�L���F��1��3�#3�.4uJ�=V{	�,&f]���Vŗ4.�������/��í[� ֿ�U�V��.;$(F	?H���9Z�G�4�_¯�9h�G0Jr��Q�bu�A��]�|0z��?6��>Z�6R. (��=�����,�=K(��p��q�(h�%�Co�w^�﫸����W.���U�h�T��2���0���?��������߲{�2x�/�Jl�B���!H3cn淢�~��C�1���L���ZV��z2������x�~��O����L&����
;e4�l'�[L��^�9�����]�r��}%N$��S��V��*c�(6�#���hRN]�8EQ=*��m�J�(��~��'\���l��y�b�Re	%'��~�I�����a�[u[y����a��|t���tܲ�^�/�x��jk3l�����K�gڈd��C��L��.��gn�]E�w;+Y���<��̳�k�����"��7G�� ��Y{X���ŁЂ>�h�EW�Fm$��@ѢBNɸ0��h��}�Q��m�O���Uנ�F�T��(�ϛ���;)���t�,��7,^��9���
��.&"��?d�9Qk{P�9�Z��X�zW�:��c���

/��$������Up���tb0�Ū)(�f�Y��AB��B�<XM<��A�h�ȊS��R�6�Hu@��}@C"�V��*�a;��8�+Ű��0�&q�ů�G9�:�U �����
񓙡�.�U*~�͛��� �{,�D��"4�g�R"�szk���z�E{$����[��K;��7F��aH�u��x9A�^2�ڡ����p9n�N-a]=t�q�$�|���?9��tඥC��1�ѕF��c]�K�b��W������?B1�u'�^�>��RbΟ�7K�����vm���_�}kE�D�ߊ0M��W�N�u�������Mj6?/�ЬJ�.��C%��؆)0V�p[{�uu�'G>���n����\�u��B�Z#�
H�UhM�\@s��*�<���TA����U��5O�
/�Ll!ovV��D����<&uw����V!!b,?7p���<Nz-+�oxDEf�K �E��j�������� ���v 1��i*� F�*Z�P���$�c?�yBl�5i���,zz]f:�0���=�Z����)���0���R����nA����������5iҐk5v��wnQ{�F��!���K�4,��W�Yڇ���PW������/�uU�kD�!ٱ���^��"m��(n���Ϳ5V�dmcj5�E�[�i1���0pі��p����[��ăL��Ʌk��J�o(z��I[�)X��k��]�ưK��*��S���%Ԧ�t�Ui�����o����/��e��r"8	*��b��R #��d�x���"���G�23�����dnwrEG�Z,L�NZ'7�w/�\-�|�h%��ys�A�dJ@k�5���[��@�#�����k��x�i#+_o�%�.�G)�{�E[ynB�,���
c*V���v=|�;� ,�?/�f��� �vmγ�HfJ�`<�� 23�*�X&ؚ�ٷ�#�g�B�OZ������o^��D� fU��/t��G��k���Ϗ)��u���v�=��{2�Wo�UY�����Ω�bpO60D�4���n�	N�b���C3�,`���Ӏ�P�"ʠI�%�\M�I�����B�K�
��(k0�^�y��0`�,7|�N��95EɧG'��$G���H��	|ɳ'=txn��&_(�ձ^+F%(6=�����b籿����0h��/�%k�.��<Xз�P���БH˟k����F���j2c�V��y�������[�3y�w��7��l���su�9(�'��bm�/����yf*���Y9���vPa����5�]��ht/�+��U���-͏�0"�=hqs�x����'�6�D��]�����|7��k����'���_hq.�}��ޅ,Y,=[��=�Wv4%l/[U|�=�5P�ǜp�>�a_ 
��#FY4�kcԿ�;Rr���' ]gZ�4�A���X�՛֎r�bD(�ְ~�֠���n���pmS%z�s-HMݬ\����-����z�f8Q;>+n��Q�W����Ӫ�,��P3H�i�*���<���>�\�t�P�3c�
=%��F��U�Oԉ�0�0r��n5��$WV �0�Z��;M����;�w�G�C?�
�����_�i�]Hq�:��Վ}�ꧦ��խ��
8v}z���^�N�=-�;�A�+dQ�a�����V�x��^I���ȫnNp��:�í��X��I^����+��g܈2�Y�]�$u�̲������Ş�n��B)
��w:\W�E���&X㻢$N�p�9�ۿPm[p�=q����	��/��m������ӈ� �z[�`D�6tm쩒�)�ee��é��c�y{��l
/�����U�o�|��u�"S�8�M��xEf���	�1z��M�N�xdbm�~<��$�:�L�I��sbs��^0�UU�B�������p��`�r>r����I8��)�|!.�S���j~�C���"Y��[;Wj5E�ħ���Ag�r�P^`��N_26ݣR�~�~��f<i����,�h��?�:�}�� ���b��a��N�[^U�Q��t�<׵V��.��r�%��HRH4zB��<��g����rs���X��8F���R8W�$���5��V�
�ki\��}��M���,,Jb��PK3  c L��J    �,  �   d10 - Copy (21).zip�  AE	 �$��ҽ�N�h15���ȩ��h(&��|-�����;��,R\ �g������'�V}j�I�ւAF��$���\�YF��6��\N��9�/h��q�zW���V�L��7���鴟bu�QTK �-d���tE�,=F^��R��lUnQ�/Zh����k6�/Z[C��(�����>�$ItJo'o� ���IE�������l��|c�`��^օn����=�]��?��خY!�f��Df�؅jv�.��@�;��P��F5��J�$93�ѩX{�N������~{0��d���'�Eo��&P�/w�%��ӱ2��O�u2 ��`��J32P��	�|$�Xh 
6�V�7��ͩ*�s~�LY$�^	�)�w��n��4��,����=�+Krx��[I�OQ��%�efW�#�@�
V����|6;^ 6�8W
��z��X�Y��(��!zH�f4%�n��v|k�Ѐ���2�u ��f�O�A�~���B��:��� е/$R1��v�X�.��}�$j�{���*8��]]u�Z�����Ũ&��(��$k*�U�IjTJ@B�߼��� �pI�H��H�GE�X٘�����W�>�,j�J��),�j�,��D�m�r�HN���v7p
��D__r?���B<3��r�/e�(ON�������Ԧ��@	%�嫐��(s�ݾ�Xvt�j!��OG
M��� {�,�;�E�z*Vh���^Lt�n���&.���r�&,���'['���@~��*UPD�y��!�+ͣF_�&��6�4gI�W�<����� ��_
�
<�9s�ߧ�� �=<�>v+,��Ac��?�ѭKX��=�s���_U�D�O�����BA9 n
��bҡ���,Us�R��������%���S���C=*�7�}g&��z���kN��aD�$f�$R��ˢ�.��]���x��%D��
 �����L}���={x[�,�#�N9���S��g{��9���焏�huM�*%��D=�	�a�Yk�;����;��%HrX<�eO�]&�KV�`M���S�\ �C�"�iv�8��xm�\�)I��+^�m�v��V�k�6�8p1��=�"�� 8j�l�DFm�:� �@$-L���1����ȃ�-&�)=]���*�;�F|72�$��f��L����$����Id��OKt������z�� ��7�>�/���.<��,ֈ��ѩRU{�w$m}h�F�Ƣ�X��e�����{)�[�^ɒa�����-�L���	�u�d��5f�7�*��� D�dM��_@r6��*��l>`:Q[/�tw�25�T{SX��a2�c��MާMT/�X�W)5��2{_��?�����WJ(��KZR��r�׽N�n ����ap�BI���'�;è����l��H)��CԸ|ϠF����_�o=�;�Ʊ�Z�M�\�7Tf'5�թ��[�� �nlg�����ri��Z�p�`�ޤ�A63)���66V�.,0f(3ޗ f=ǃ��{��[A����^W5�7�.EC�M	/4��U�����:MZ���|Sp�mH>}�/+��t���WYY���]�Me�hq.��SOY\⚡5�@)�a3���s�7t}��F��:�%P~�v�μ�,�I�7�3��̃��Y����b��<>"`��*���Jv��@��p�>�� ��u�Ӝ������������^��Q`��,�.�R�� 5������Pu�6���u	�S�a�hgf�mx7�Y�VDiτ�:�NS�Ô5���'���b}�za�1�&�1�|�l��Dena�f̆����Ǧ.��9��'�oo�zl'x��/*=A����G�E�Y�ʓ��K��ׂ2���y݂y5�i����tml
�#�/*�J�� �n�;�� `��&�788BU/[1�*Wl��$�X<�!�1P�%n�ܑQ�-�
�������A�����J��R�C�I�����L�����3��6�\�U�~��꜓����d�E�:��y�q(A9�G=K���k��K�����痵�V:�릆��
����$A�`�C(�@U���u�c�c_�I�$����1���㖕Co�	P�g�7�uŰ���sA�Ň�3Pӝ y4|������M�ba���P���^����:�7n�,ۨ2� ��P� �	.�cĮP끄�J:��r@~���G�R���&��$��i�	a��)L���Ul�Ojף�X�TC��12m��_�}b�m{֓^5_�{�����g���f���\��p�'�4_�
&��RFL��:��(f�O��gQ�U��������ה,S+��2L�NE�9@O�p<d�7���r��G_�Q�c:Cj�Z�y~��a_+���I�߳s��T*
��WB�G�1�q���X�O��*<�7ui��(I��E���J��4�*����-[
�h&\J�5���xo/���1���Z�+�-��2�s�ɛ�^�E���]�Y���
��"�#�V��Aw-rR=�ժq� wjh����ݠ�a��̕���K9w���	�c'}�W$1~,�m��!;��a_
���3@�sk���(��s�n����A�=q�Q{�/;Y[S�`&�J}	�Ŝw&��H�bkORo1���#���z��F$�:�E�}w�O��2Z�I,��a�sH�!��W�a?Q�J�ǜ�mV�r��l��=So��]ҵ��ޏ�e$���I��{*8"��jP�Hϝq7���"�~�1Ŗ������;�n�'6�au����ٱ������5v;7���[&�C��G�F#�����6o)Asq�~(�����e#�ř��C*D՚�)����6���J�mF1ژ�"���`c���U0AvK	�6�,F6�<"ĳ�qDR��Xk��)3�e��lw�݁�nR4 ���8C2�d�i<NW�-U�ac�]X¬0��14��~}���ܡ��� <�b�
Q�����tT�z7Рra������r�9Yz�`3W٣~�̄]��͏h�П�I!�eZ ��n�w�9�%�{D��LJEL��r�ZL�1�q8�Z��+v�Ϛ��� �c���Y��g �r��]�3w��wjV!'�u�:���m+K�Z��~GX�27;5�ڿ0���)�&�%���i�S�����c3rd���⽟X��:��4l�5��� ��Z�0��%O6�9��#�k��ITU'n��j��v��}���,tuSq�Y��C�;��Η�KV�V���u�}rĪ�'� �]x�P��f�0��D����Pp����н�sa�2]�V���M�¡������3)܈��70J'E6fv5�M�?#�J$� �.9�C��3�����2�:�Z�,39H�.�HlIU�Q��7�cEy�{F?�?ab��B���oC(�4%,*�e,�;�PL��=�7�pn=Ē������xB���E�gbr!h]�Ze_��W���[�2�%��EԿ�X`>����_T�l����#���1��nG!&���i��?b���]�:�I������)rkO�p<4ia��eeH�ۑ��G<�b�Z�+PG똝(�b���B#�GC���!����B��7Z1�V��wX�pk@��1)>��&�?�J�A�ñ7F�6X`-��ÓU#ގ}�2obh�Y���6zd�`�-�S��_u��u�Qp&�ϒ��3m������x@w�q�9:�5v<8!�-/����?�����IItF��{���J�=����Lـmˊ�t��8��6�V���Kn�o���Ȃ���h����9Y� �
�L�nK>M�#퓞q�
�U*�:��Nt�w51Գ{��?�(3�X���w^�*���Zw9��-�G�w�C��4zb;������qVKz�4���ɪ�����j
Y5˱8Q�CcvAoŵn���R��'��!ah���Lx�:��B��`�z7��+.���	i�,&�^����g�O��Q�pL�_�_O��m�y0�dY�`&yfB����f�? �S](����y�uMCW������K��5��
��M"<��Q�b��S/���ف���[�ҧ��(V�������^�H�H��6��1�`��E��]�<� �O�*E����W�ZL�p����-�m���z>��)�	i�~��j����@���s��2�w\M��m���������V:�ǝJ��K�Wzյ������_��G�x� P�]uq3��9�Gb���R������|�L�!/:�i��eO%���,�N�(�U��S,�x !o����W���+N�!�I2����v[[O%zȏc�h��!ļ��5�[z�pՃb��Q�~xֺ@�E6ya{��R�h�����\��c$��d��Kl�=�-񌉨C��B'�jP�j�Su�'��6Y"#,M<�u��kǻ�4�v\�4%b]�L%��f�+w��e�<`��Nx�	K4:�m$S�Ã�ٛR2E@9E�ф[�ǔ��tt�Q�T^�	���s$�1Hrhs2h>�}(�ĭ�%�^����[T�(�^#ޏ�IE�vW��t|��L�	X�Q�K��H��0�ڇ�uY���O�0��;-��Q��	UC�]�+�J�E�84��F�î����ޕ������D�sC�\!�,�1L�x� ����OVwB�m�h��[d?6p���<��;�lPdz��_��<I�b�Z���y����HTdx�%�>^t�o)#�ֱ q!-�u-K����U��4F`�ʿ̕��r�p�m�Ϫ<L�?(U&�cY��ިp�JL��'��q&�Qcr|����W(�Z��U�J�9�vc|ӂ��_\��Ś2.T>C$���i�P��O���Q���<���l}*q�s��i�|������h��)l�2��	��$�T�Mwܺ�꾘�g�&m�3�-�/dǫ,�4g&����:�ư9LnY��J���p�b������&TX��m�3�Byڽ>����{�/��	)w6t���m|�*�#�ŉ�w��&��.���ض�y���p� ���.VS��u�^�yo��lTeO�DJz�B!iSY���p��7J��:}�X�!�5��7���cl�I4�gu���`�Dz��@?�������v��v7��mP�NZ*�V�\�B@��&X�|�k
��G0s�/��Ўe��{3���!��js�c����y/�fĽ���jNbߍ������R�A-:��za��QY �	��X��С��4��m_�1T��%)Eo�|� �+�q6�	����ZIV�U��P�]<8_�=�V�]���-U/�r�]���4O�.4",����w��fV�h�3�'(�T6݄���IV�:prw�"��źdm������c+�7O��n� ��H����LLMS�3�Lx���Y]�N��4�G&��P�=*�Q)S+8���ҽ!J��Z7���8/�*w�����M/Σ%m�1V�m� pB$�su�02k�_�걜T����0{���e�B��V��@���i���[�oq�.�#�B?���C�77�W!��Me1���U��YU+��9(�M��F���wֶ�Qb$M�ɺ�oxS^����\�V;P�a�t��M�P""L淴,�5��[-��Q-`Zz�gHi�of�)�)@-�Z�2�W�Dt<�l�E4���<��>�"��r|Uv����vH��R�l�gV���	~/[��s'��04�E�{r�Ō�1H��'$����l��)D"�� �Mh�gʕ\�=���q���v��h=����{��!_a�@����¥sB�,�'�fqBT���Ƶ� �lq�\E�h�ͫY���?Ec'�v�q��a��f�W;fO�J�G���u�tN_1��3�m�_#.duޚ�1�4J��T$��[+R��{<�UV����l�����C�&��ڳ�'�^��90������A�Lb�-g����ܛ��6<q=�$� ҈O81D=�8����E���g�YYE����}�<�l�����Qv��[X�T�۾�2��g��HdRc.���O�p��5*��?�`�Ŏ�d��������J�����@n5�Ϻ��%�Bl�a���ó�{e����z�npD~�#P�|ݫ���.��Ճ(���}o&%s�Ǒ�K���xyCT㶚H�п�����\�}�r^��״5%< ���PX:|���g{ҋZ�n��J�x�[2/��=)[ 	��N;E��5�ӕi�#n��ڛ(H1�-��/�J0�)t/]�
6`A)Dc��K乺���lՐ<�U�qjS� KjYg����0���qi���%�$�)馂4$��f�m�"��Sx|ڣ��Ǩ@d_��u������W�x��B����e��m9\�3$�!\�^F�m"�κj�O�4���HH�n�'b��̸������`:���A3������O�;#A�J(�Li��I\�} �@�/~o�w���M�EE*"�,�|�_: �#��ʰtD����@0P:��'�2*�I"Q���>�e�};S���t� MuIjC&r͚�5]��}{��1���%yM7�Y��I���D呝��3z��2;BH}qz���;^�+�-����\�>Q�����Ż�/�Btz��d��堆�e@1�D�x�u�P����x�n�b@7784Mx�/>�1�Uq� =V8ʧ�*2�$��K!,�wW��x��ͦ
��Ә'˥�ū��e��'4ǶV������p=�,sY4iU����#�4 $���dn�4��l�UL�m"������SŪ)���"x�A<3��-�Q���y؞��}�a��a��S]ne�j�q"#�����Ǘ�S��nT$���w.���͟�o�E��lMM8e��à�,2�u�o�Γn}g���l�)�r�ϼ�|��o
��QE�r �
�=P�����O@��:i;H���������%E�5&�������qM�c΁[St�7��3��y:�%"�:��4��W�=��9ߎ�� �C0kQ*����0qU�r40Z��|�5 ���j�G���Z��v*d�4���u<^v{��qd޹ъ]�Z�Ġ2��f�0��=Ђ{�X��MDQm�����.��P����lS?���s�%�#��m�Y�=�h��L���ﱌ�Fk:����炫Ԙb+��
���y�CL����/�d���H���[f���3�k&���$_��Hq��+��е��c2R��i���J/�x�~g�4'Ty4�H��N�8R͝�{atQ�Gvh�'ܗ�z��I;��x���HɎ-#�ᐧ7��%j�޴k�u+�*��+S�XN�k6�_���eRBV	����]��b���</h�VeP�y�nb��q�������;�|Mȿ�e=��2���̇�������LD����H��U�����Z��t�S��H�����kT�Q;Kr�a�T�0	����ٙ��&@�B�!��U&\Q�7]�e��i�2Ü�Dx�2OX.�H�ƀ/D��e�`��|�l��@������5��|��KL�������z�ˮK �`�'�����c��ygq*_m���g�]��Jo�m ]ہ���ʮ�]5r¢N��IqB(I�p��{yI�IYֲY�N��ТR�L��%���D���)�$�*��_�D��#fue<r+�m���U���>AO�"ϼDPgu*]f�0���Yo`��'�&���)Yt]�`��'�kO>��=����(c����_�����c�C�����?�&)�k��e��	Ǖo"��J�ί;��6��<�ݬ=�d�H�=��{�r'r�� Y+��~!R��-���AA_s,@f£j��l��Ր�>Ai��J�2�wf>����p�$�+�	�F�rV�
y���)KKJQ��6j�r~#T1�����Pe�7t8K%���햲� h/�o5�������֞��{)��REv��	e�!�S����Ӎ�[�)q�д^#��%�:m*}�\NA(%�X�d4���G�+5�r�O.����}��v�hk��u̦Lz��v�������ƞ4��Iy�SNk5z��p��<r|Q�1����R��#Ѳ ئ��r�\��NG���ꫤ����w�X�\�;c�n�9�<
P�x3Đ��Q����J�0�����~I�Ēy�(����փ��\�d�xxy�Ox�h,� h	���!�D1��qa�S���Β=i'PQG�!D�_����Ⱥ��&�����o�\������rS�<��'��3� l����0h/㩶\�Y��}�M�"��bo�YT�"r�r!!���#T��gb�Bt�:mܑÑ��WHB`>6�ٮ.�ʷ.m�x^��M�?�k��?>;���]hV)��Z�T�̊m��[y^�':���x�6�҈��\��a�9Րּ�����N۪���� ��H��K�	�c�~��X8��7d_��)�����@�^�&���~�l�eط!��K���ou�(���
ZTh�;|�����_}b,'4�cD'+�(�恣o�NR���l�*�,��"JCs���2��ٯξ��&��yN�̌<�U��a�;"		/!Ud4��qK $���:!�S7�E؊]D~Й�i�B�.)nb1��U��W��>σS2�hK�����]
=�
��{�R�o�(-��n�9k��Q@����`���<��.z��b�be#�	���$�%�oMX����;��9,̥F�xX`��An_��FH�����٤�_P��$�W�u��OY�h�C�������qi��
�J�%O����_����GZ.�D}
����-�#���ׄ�]
oD�4����Z�^zExZfg����11��۶HRi(=�465̹%�-]YW���H�!,�F� ��x�KrSCy� ?2�k؛[���oތ������I䞦R�N�YU��V��#��a���0:)�@uuB�ʋ1Y��wg�/D	�
�s���r-)tr�m���	��I�ڻԾv4K$2���>]M�#~���RFnƄ�}���-ǲ�uTs{'s��t�[ƃa�=�?w���gh9��|��?�g�?�Q�VPw4��@n� Yv�A�B}�_��V�ɝ��g�]$I��r�FW�⦡�A�[�9�$>G�S�K�'����m
����=���i�����q;cd6(��D������t{3B��]	�~�KL���9�"ԭ{`�c'v��aO��%Oi��0�����"$��N?�B0���@yw��=�q2�0U��ӨL�[�{F��~H�9_WR��@�Kb�>�Wz���z���n����~�K�1)��b�6���� b��80d�/����&e�T�B(=ɍ�.�.���QEw'V><���K��G�f a��3l|�u�TD�+4N9��?����ؗ>��]KQ.̓wl�љ����!��pҗe���n�[Lg�܂���
}i�F��z}}U�ֵ ���bf>*P�4V:;��3�<��d����$��Ȉ�B̌h��	X�j`]h;\#<}-Co{_���$��q�~�����<��n�⺭�&�H��^����,� ��ʹK��v�
�Q�B��qm�~h>��ss��=g��i�v�^��8�+Bg��Ƀ��9mF�V�@���tZ���XLn	q3Z���En��t0��)��C���5/Gz�G�T�W�Yɜ}0|��Ca���qi��BA�;��q�;[n>/�e��t�tSO�iT�����]8�Z�R��s�EPe5ђ�]�i皐��fp�($r��5��c��sӺ*�g{�PX$��@-� ��1�B��R�L9�ekT�y��L!Bh�m�h����>\��v����]��vӭjk��� �W>��o��͊�bW�>Y��PCC1
:ڜI,��$����?ۀy��)A�Q���D�-��2�L!��T��/��l�L�4���a[��0U�P 7�
�k�m��K5����э�9�5�R˸%����c!�םi�����'ӀsoC����q���K:w�e�7�Q���>������Z��{s���8dۑ�L���1�F�w���+�	#�b�w�9a ��f���`W���}>�g�����91{��� ��4��6>���N���n���z^��VY�B��W<�'�g��A���8�|���#R�&B����}�բ�6��\�I�}��h� 0��¹a�OF��`p^��ȚBޏ׫8�6;_f�����`X g����?S0a���f������ý�V��e�7��)��ix���Ǔ����)㗮���� \���f?��3ѓ�Qc����(J��� T����5�0.�-D�[w<6�'�$̈�5�s��O���t�;0	^��y�Z��TV��E����_��ӡ^��0�?�M$�_��`�]v�%ĳ��\t��[Uv�A9q��t-Q��������A�x���ȓܫ�����EEz�mX�u�,̚,l|晩�����؃����	�)��e��f��*Zu��S?��_���%��M&����M�h�u��^~F���SB�K��܃�ar�Zb<  �/Ē�̼$Nd(L�����|%�,�B��"���`����Uېձ�׫��^p��O�)��ſ)Q�:�O��ʣ����Wtf�ʽY#�hw�6�^T��U0Ao�he�ϩ#�ARO_�5��SԤ_��(A .�NN�� �U�J4D���Ζ�M�ô«(�~ �$^Q��� I��r�Xo���-a^�LW�ˇ!��\FW1��q��S��<Ĩ�>^�=�%B� ����%E���M�G�j�\ƫռ�E��L�bYP@6p��T%�5�)��Od`å�r����Sn@��h5[��S�KK�g�B%IT�>�(��3�_f��:HeT��O{��~ڣ*E���
:Y��-Q�Џ~�4��D�������Ogj�l��m�n4[m����=�r�����/�N�>&��Z��k�!�bTI�\>��mWl�;(F�����u渮��QN<:U��/���S���Jt��N�k������K��?[*�R�
�zvw��+�_8��<G��I�y��B��� ��Ok{I�'�R��n��&�'Np��Ƭ��whԤC��yR�#2��O��Q�s�<�$*�"0##��&��`��㒙z�}�T����X��@"y
�kD&���y��<NO��>��^6�娖Fyl"!��}��?�m?��-l���[�\ R�$[\X�@O�9����Iͮ@�턕d�;ZP��-/�����PK3  c L��J    �,  �   d10 - Copy (22).zip�  AE	 ��.ya����Ś?u�uI�G�%�t�A9�eϐл��� ��Oօ8e�^-�%�0�$��{8C�ٴ&����v�3$#�{v�v�������f�Y�ُer\��ۢ�"���mA��N�� ��.8蠍 ̵8vs�26o��v"�}u���#�%cd��$�\bd�؀1���>#�)<�kQ��{(=O�79��KϧB��
a�������茀;��ۧ�j���v������|�;6�h���f�icH�(cׄ3fh�|�Ԟs���ud�t"!�)I��Us'�<A�#�įpV��kq����<˙�̺�7y�=�\��f墌	��N��n/���#�픉e�9e!����Ip�<�(KލM��"x\DTC�"+roqT�GB^�9k�� ��G��˗�fdN@ �'^ٶ?���ŕP�/�e��?0v����Š1�,�W,�f_#bՠ	���B`�	���fѽ�sRD��rc�E�P��=v�`�KB:<�7�m�`i�PS�?�A�Rfhk��u�ү�Y��ȁ�][�$EAQQ���A	�����/����(��u�É�Mr������;��� �&�Ip�9���(��;3���YUR��]���<��0��Q�3����Tv���;J�����G�1���FH�]�-���'<�Lpƛ�Ӊt.�8H,MW�:#�
�x?�c��;ʚJ�l�.�l�%����+l��5im���nY�].�@��Ҹ�~%Y	�:��=K��+,�<�|C�>�O7�����)ʳ�`�
�m��PN鋟Y��h���#E���"��}�[LV�M��k�3��?�,�� $���X�L��@��x�� 4;g%��~E�Q~V\0��f���y�)H���b�l�>A�&����mP����_^;��~���۹r>�C�@<�qօ"�N�1��鉑0&��\�+�nV�5�qP�`z�]O��Q��#��8�/H �r� ����5�\�j�r��̦~�� 5d͈�&j��]wg�M7zh�32��|R�4e������D��e;���r���(Kj���	K?�~��R)��o5X�4������s��IE4��P�{��Gm��"�%#���4~���l�n���%$8(����^��
mgh8H5�F��g�0�|�w�-�8��C1UM�qҩ�2�������׏F^V-�J��^����B�1��`�9[0�魌ܤǕeB�X������l �
m����O�_Q����CX"%��y��({M�S#ȑȔ�ʰ������(�s�1Y���Zʛ&-��?�Kbkp�s��Q8�dm���9>p½wX����O���Ҟ�4{�U^U�[�f�9��B���G�WI��*��['�Ҵ������H���g���mC�|h�%aa��{���M|�s��Y��E�[�%m��#��h�iQ�ZF��z�� �_�z�L%b�A*�j�^�d��ʵ�k�Z"KY?�����x��۝6�D���6�V���?Zmɳ澜k1/,u���`(O:�������5��#"�JS�+��W3�1��
�j7������HXގ��ևgA@3m�1!�c�����A��|L�n:�HŎY�n�HE��ΗrNG���H@u3�5��wkTgn��� lwN x)���Hq�><�v��W��vE��MX> ֱ��;�I����e_ ��s�L���������n��Q� ��zݭ,5;)ʅ�ˑ{`���s��9�g�����>+��
@��דqlg�W<DxFd�7$Cx����v��E��:�_�nįzֽۜ}2>Ӕ��1Kid�2y��۽^�^�0�6H�I!� `I������|�hO-s���:�&�D���hV�it4ډ���_.֭��{�N*3������i����}��c��1��Ĝ���Lj6��Kf]�J�X7z�w��p�ƸZ$9ǯr5�b��H�q6��`z�Y�ĥD�S���2f-������h�M��A���L�t=Z24������m?s����g������V�n�$��:4j��pa+ޜ�=b���m�K��īM z�B�����W�J7��}eoe�*�{�j5݂�E&o����ċ�0"���;��^������0TmM�
��^R ][��B�u��ӆ�W�@3�O�$���Vh��$2Y���7�:�r��t��5Ë�xH��ل���k���@���Ȳ����"J��b�V��"$���dUX߱ +��cO]��� �qy��b����h`�ި�t�'�����|չ�2����+��ɗ�Nx+����Z���0L-�i@|d܉�h�������F����B���5�1�"FZ-r��3c
p����y%@�W	�~��B��C��䞂3�ұC>�ﴪ�]�2���@���܎J��-�:�$�Ԅ�J��A�tAA��G�P���L���D�CሻGnbF�:�S%� F����5�3YԻ��z�ږ�����#����_D��;�˽���iV��|d�s
�E��K��4#\���*t�;��#����.�|���-y��6#	�:��<���C����v0��Jk�q�ʎk���ZNB*��4�k{�����8)���n���w�'�/�J{��L���Sв;�}��ty|�uF�[b���� vb�����.�:�>P�n�JV���a宴[�ڇʸhY6�m����^-vF!w�!��ۇ/J�5�;�0�n;Whm���J��Fǉ\�QD��}��Wu>���G�Z��)R9���f�߁*���]�a���mKȗ�4~v�ܕW�]��+���꼄��yӰh]�?B*W>���xKޏgx_+A�L��p� q��VΕʳ}9@v
c1��CqC��Qg�d< ��_������p~��o�\��D�DDk��+)���b�)mS48��4�Ƀ`� Ӳ-��g"����^�&�D'�i�[j�i�D��f�<*',5Ӣ��G�-�k���@*Ȯ�J�`$&�Zt
�>>��u4����)�	�QC�uP�
�c��7y���=Xfɷ�h��D�ȕ#��t�7��,ܭ�N���`;��U�~��Tu�Ӑ��Fб���8p��]"�U��ip��&ܲo>iK�"��&x��"�eP��L�>%[�u4��mЃ�-�k�d�tg�γ��B�o���5�e"��3L��x���
�Jf��Xn��T��wވwEXhGo��D�t>?}H7�"�ޑ�A>6�Jf�k�[���9�y��떈"&o]�\���g�;��/��I�W8��F���~c��*�"nO�$m/(����ɥ�ͼ����7m����ڷ�i���0�*��>8֙+~��,�f�&�c�n���j��w)Ś@�p�½���mLDoro���V>�L^l��l
(���<�2%PE޾���H$5��:#|{C{�GR���P!��I��z�?�*쌼�G��;"�z!���������Nc-��d��q����|8�Ii��!@n}2پ��ZK�yUFRi�fL���k����q�f x���a3ؽWo��{��'=��dڣ���Ue�|I��ʽ(�jف��>P��"��6~�g�~�	\(���m7 W�W`��&\�xZ���R�3;��b��P�)�QKny�G@������Iw(0=�b؊��2�qXr��G��7qD����pR�n�2�p���vg��5lyD���1jZ�_7�}�k�����=�O��I�xA�FDV�Ĥe!lly�����6�K���z�w�6&|->h����"�U���W΁#&uhw�'p�����=o��e���T8 �
R�������=���p�E���� �g�qJ��*�=�x���؅�D��|�u��n�܈�Q�(��S*������)(��A�i� �c��|�7�ڑL���:���1>��!���i�}y��b�/�`������@��>޿H@^yGD����(�B�{(�-�r�i����82 l��+���@��(�|��GD������l|+~�(n��1�M�6k��u�c�[١����DWU��I��d��Qi�u�ОZ"W�ժ���q��4�`2v	jK$ە�%��ܦ�,��;�Pm��#����������pI�`f�q&y �Vo�REn�<kR�5��!$��r�A���Α��[x��AJ��sYu΄0��%��ݭ_�M��D��'H��������k�$��	mg��o\eW��`���̿�Q/�����hN
��D2	M���Τ;P�a�x�����/:�W
@���/���յ�wfv��.^X�B~|��
���a�X!���]o8y�I���r%i �L���xqѽ����5�-J.�q�d�f�oN��?�q:n�ܩ&HQޡ���u��L��� �]��,��T>:�><O)M�I{�l��Y�Ba]��¯��]KR<lF5�U����-d-Z�����\�K*�/��R���sDD�*8�^[���#yˠo��Ai�t�	4�޶6�Z$��Ѵ����W�M����˘:�dv�f�pS���A��O)b8ㄙ3��������G�$�ԕ,��%�'IQI�|H���t���ǋ�Y봱����q��4�Ü����g��ͥ9>C��8���.��r�|jjX�`|Q_Y[��EB��$��ɣSO3����(��,�n�X"���b��Ƭ2J���U�,�-"�-�����\��xB�e��-�/��g���a��ʵ-n՚P,F0�4q�m��B��޷�B{�eԻ&�j�c�:M@��\���[��a*�6�� ׾@���c<Ҽ_b��߶Ƹ7[r��&�����w?�z���ݡ�,5�\у �lI��nZf��dO˟�i�d�:qE�H�,	�h��3��;}RZ��H//A�5H��W	������n���!����<�V%�c����ԗ�u)R�2�r�7��B�#����6��4_�:Tul�zHINrEW�)��_e�n�0Y��ҡr�J)�D���='��/W�L�tɰ1��2m9	�h���bt� ��9I��K�x��:��C�����j7�J�,XR����4��W�U�������)�Y5�e瑘�b�wOᒙ�'�x7��[���
��}ãJ�?D�EV�5�1� a�T����NK�qp�}4��������L�;��C���9����2�*oF|�����4����+���P�=��\kT'��]����D�A#��*z�4X��EL�Au��:��rb���y�mL�o|�J@�	�0Ɨ$\??�O�C�W�%Ɯ�l����+����r��u V?1�	��ԀL��.|Nl	??�N����s3k4�ʗ����$m��	�ij�&�*<� ���1������p�E�q�#K�'��XY	�o�&��y�ـ��u{{��l��_w�'�uN/��6���A��1E}=�9��✉�@��Z:p24�o1s�e���W��v?�u�����#u����1 ��y�"K<�T6�9@�L:6M��OL��i@Ԇ��(&"����Z���G�� �Y����đjÉ4*�. W�$N~D-)�K�]������L媣_�����諈7١L��:�9[�$�t:#1��Y�~�L�Cˤ8�GaK�+�{�{e�/�o��0Z���P�: y�2�(U ��m���ex 
W�]�J�u�p�$�n�/ �.��&������cL���X�\����.�Z��%����M�x��� �J+e��1�G�b��HA��>��֧EPO
����>�W�K먽R���c�$��9��aJsma樯�C#-�����cx�x���[7�L�[t����@�rX��4��5�oHn�iVY� 蚚"��P�&����b�n��M%�;�!�1_	�q�\MC�j:��O�:r���0O�#k�Q�^ƸÃ^X���z�mv�V�8K~�˻1]�C�����?'����Y�)M�0:�~��>Ke��~�2�����څs���@���_�Ȣ>?�Ys"�y:�N�GЖ�d�a �����՟֘�%��j��{�g+l�86W��,�-eֱ�#���Q&S�-� �)[Ov�����Ș몈����+�,��8f��If�)7������Z�*�t(&9<�}��_��ELv��\�
9r��@P0�$�6]��h�*t.�R�����ԅb�� �U��\�p��7�X���dLnOx{�x��fZ�>;�s�_Da�T8~XOՁ���)؎�x ��d�F�Ƚ|'�6ܖ;�8�`�'ќҜ)�F�YWڵ�:�ͩ�Sk���!�Z�E�\@n� �q�hi�3L�֍�8(���V�ڃ#�99�9ٹ�̑,�` �}�5��5\��1t��jR��k:�Ѹhi��a��`����M�r��ur|�s��y�0������"I���h�DJ"�Η}�0og}��s���?�N��.ҿ� �~����c?X��"\i��m�#x�Ա�L�+�4�6{�4tu���O0B[��Q����pO����tn������� ��v�� A/H'�$M(���K��x�B�����o�
5I	����j.��Ҷ�_���#�eH3[B��S>I�� (�2�lSp��tNX�f��K���n1�{q�A?��Tr�wJ0+5�n=��H8)��e��$��hϢ!���肈'���'�5E
���A�7���	���8�QK�;ٮΠ�{�妍�4��ju:�y,����7ָX��ὠ�rî�o�>�ΓW�)���i����rM��ȯ�)�2�~D�v�)��!Іlg����:}��OZ^Pa�e�9o�p�Pc���(�O@0ɻ�]�
c�7�������2�	ٸ�.o�4�R-�yFЋt؀��	t��Yzĸ �'zLC���:�ǫ�D7ϧf��&�&��~	!�-���Y�z�P�^	��V�N z�R�Q�!���)�_%(�K �鎠T��9'CK�/�j��10B�L�|�"�'�FAh������ڐW��Q{���΁�v�~����e磂��wy��X�hI�,-�FU~�fxOct'��=i�,��v�Ȃ��ƍp�W�M�NSp+VĢӆ���u>,#?a"�)�6�6�٪�zP�U3��B��sV!��2�N����(%��+���>2-Nt<4�xR�I��{�T3��]R����Y�C��'���ɘ�)eYS:��D��lc�RɄ��^�Zv,��9B�k���){t�9nK:�5��� ��a�-ӹ9��뗅�,����1N[��
�_L���ܳ�yY�TL@���a^ڵh��G�H�Of/�Tx#�񊕌�8lְ.]� p��c��L�m�_|t��sB��� o���5�(��M�՞w1�J���D������yV�u�D�wb3�o	��\��KՒ�#Fͳ�!��wq-��h�Ò�>r�X�ڳ�n�����n�����zC"ĥ�����ʅY�MV��Lwn�"�&߮%6�y�3�\~�pS� ��&ƭ��K �~��Aq��C��*��ړgtJҊ��IZC" D(��d�@�RZ2G���J�H�ܲ�SU����TJ|���$a�hà�9���� Hk�,��b\�2Z�#ݓ��x<���xU�/����͑���JVH�����A�P��K �˜�(��� ��8�Z	]w6;��8�P14��d9�><c?w�����*4 h0*��F��8��}����DG�d��0��3=t���w�q�7�(
"�6�.YU��J���$��v��ţH����4����H`�àI�%"�\K�+O�,Z��v\�O�g�xJM� 86��0��X1����J��^"j�P�q>=@n�\qO�~�|8�<�\��h�M�8����*��J�%�^��RWX
��P���1LS}�u�DOK����>��d}h��;e?�O�V�N��+u�K���H�+䛛�4wu���M�}.���j����Е%X40��������_�9D���M�s?���=��
~��ƃ��ޗ��-|���ң��5W����B]�󤡑+A��Q��Q�Z�,u���hd��?iۉ�%���d5>�rKkM�{�V�����,��-Ж1�6=M�xC��`3N�~=�"���ok���6��㔝�iia`w�Vݳ#0=Id��d[��ϸq��0l����pܦhѓ�
������M��\��5W�fձ���P��Z��F�o��:ݩ�P��@�2�\/v��!CWi؄%^�ڝ�>3E1MW��
��f���?� jZ�b�ӫ��������������^�WQ/ӓ�޳/}/g�%�:?V��ku�3'-B�f�)���}��sY����
q]\�c(���Z���{xW-r��^���ӏ��?\��.C�U���Y�/�G��j���J�RL���6�xD0/����БY%�W���7ʕQ2%T~��[��k1�=��S��Ԯ
ռH����` �F��%<6��L��a�8�������T���{r�K?N�'�:N+�s�:Q�/��ͷ� �@;�Q��KMu]�Κ)a��z$ZS�Bh�B�z�YU�=�uHJ�h�7z�� r���'YX��vXO��M+$�c��;2]j�����W e�*�ۏL����#r����.+=
�� �����}�C�#�����;�o�C�݅q���h����j@�w���Gl:�B���'V/"��먷*Q,��T�����0Gɇ<r\�|HUI�) �0����u�q%G�o����f��e�����F���ꂽ�!W�s�沖�>`w̵�c䊖Ah��s��>jH
j�W���ݑ��m�I ��TK�wG$�7�����#QP��������m ��GM�2��S�X���-.ö�����R�x@/��So�T��7\A����8�#���|u�u���̻ ~���G���.��Yܺʘ]�_{P���V���^d�o�J��tA�q��y���R\2��A��8Z�㺰��'Pg�����ҴP������,(��>FZS�D�Y�f�ho�0���BL�B)o0�ѱ��?��֯:x��3T�6B���R��x3�Lb�I�`R$k�4:�cvr�F����=�o:�a_ѵ_���0�p�7@�Qp����
�`�&�N�!3��g��X�/{[���u�Г�i�Y��a�������"m�קSe,�%��f��\����_*��j����}�k�,��Ek�����N�q�a0y�c�ߠ�,����t&�O��3�bz�3N����g�a��I�jNj�����p"�$i-��C9֝zF�2Ζ����A��%� <L�^ȅ�zG�s��rٟɭ2�F{>���7;�ӀC���i�w��y��yi(�j��W��`��i@��Ɠ�t��'17���������9�O*��B	{&i/�I|zK��.�F�-�(;�t�����-&'+����?5�g�ݝw��EaC�.��|h�P�mL�����Ƙ����h��h��yO3S�#2�.x��RH��N)�ͤE�C��^��`S-OY��ɥv���S���~ �R?x�a����P�腊:�Þ~�]�:�0-����]ÛU�ToT߫٧	wH	(��^�� d��\ቪG�آ���ş 	ɓ���3j.�)�d�V~*�*>�X�O��87*�u�U�n�+�=���Z@�2#�Ц�f$sH��O(��_SU�y66�?��fR�3ꯇF���m�ޫ����]���)")��[�Ԩ�dc��)���P��0'���'b���fG
��I?'QĖ���S�
�{�R�F�zy�����P,D�%kc��5���@_��0Yǅy@m*B�x��;�\����0"v�._��x���3����te��E�醸���PE'�2���Y���끆W�%��ȷ��4@\�;v"QcȽ�\�4�|p�}Ax���7r�B�E�v�̺���Hh�����f�Z�C����L<3�y1 �:�����\�<�~L��lC�ĜQ���_�,��~�7Ӄ��(�ǂx�wz5��3�\x:�"�ؒ����������jF�
�J"lĦ�T��wdB�Xx���@RZ]�v��5C����р�\�`5E�k�X1u��(y$I�AM���*6r�?� �˜�X�#P�����%Ȝ��z�hT	]�86��h�H�����Op�"���7PMh��1����V9���i)q��Gf�ʓYXL���d���l��6�'����Gd����b���Z	�\�{t��{��#5�Y�K���1ݛX�.�1vs0�dt"�J�LCa�[��pl�ʆ�dN�d�]]���B{Wyt�6@��#H�]Y��8��a��Y�a�M�2�V�S��9s���e1����`Ӫ�~U�'��4꼭l��65m%2�ȯ����t�6Y3��b��6��y��R�B�!�J����s�����[�o�`J/
TB0n�W{5z�3��Z��	���� �ɼ	Z�E����Ι�A�����)�6J���IƑfR�9�ۡ�2�>�4:1����|�H���]�a*1�v¤r4�S���GG��Ό�5ĸ�I��-�$����b�8$���w��&���R���������O=$���ƴ�fG޾Grr��\BDyp�,<��5����n��E�qA��8�k)c���U�m�#�^̠U��9�<Q�/����I�)&���ČᤋevS��%}�S-�����Ҍ^J�\�)!?˹=�#jQ�.�܅m�m�x(>e5>/���f,������vQ���,}F-�C!#klJ���ͤ���΁w�B-R�N{�E�ȩ��?^��[�n!Sn��:X��3g�� ,�{r�K��g�rB��ǹ��ɳ���|�c��"�G�.��Hf
~���p�Cw�jYA�<��.����~,���v]�3vP��J��d���mӒg*G܋^tJ��?]5���&���7-�E )'v�
J?R�Ą~	��l}+G�D.}_�n��dZ��u������I,(_��B�������/�eJDo4����ں�$M�)���<<O�$�h��������Rg�I�n�S��lo��"�*e��Z;A��pR�ȵ�%�x��	���l���[�'�i��&r�y�4�K.pK4
���8zG�O/�������z9_��(���$��Oq��O�if5����I�ڄ�3FzQ`u�(�+����c��$ެJ:%�%�J`~��X�ާ�7�Vd�+bPK3  c L��J    �,  �   d10 - Copy (23).zip�  AE	 �����s��9[�%%�ˏrg��v�u����f����$�лXٓa/����{���9�c��7���b(T5=z��~���Dӳ{`q�;�.v��m&�qC{'N�� �MFэ�͂�`�8����˭�z�Ɉ�b#�G�1D�w��'�IX�k�)d^�}T�^d�����#ѦņB�/q�5�͂�E{��Ģ�X|Ľa��V���G�?2���kd��]�Y��(�H\�5��p�����%��_P��]�XQ�A3�,��VSZPP��z�ɔ��)Rui����%C��ܗ�B�� ���LĨ�"�D���9��=�*(�p�cMz[�K����C�.1pvU�Q�o�I�x1㍴^���r���H�b��N�'w�6K#s9��vRq��T~��ukL�C��fzM|����*�bT���^dTPᯪ�B˗�	j�����vg��/kF{O�%���MХ´V;��@;fQ>���O��R^5 �����>�b�1�q�r`0̻���\)#�nj�X���:�suϤ0Vv`�Re��HYC��Vy#PoP}l�(8FEȒ�:��=�Zq�:��O�Nl6�F�1��sy�f��w��ft�>�"��
���
> czމ]�+�]v������^�Ƹ������E|�N ���W�*��?Zmd���/]�>"��Z-i}	ȷ��m���l��[ݍ��V@�d""ٞC�t�'a?��������b��M8��/��;ˠ4�Ȯz����B�})��!�5(���c�O(aD�u��３���;E��?ߪf��+�`G�U^��+7���Y?x$~0p�Nq��HM��.G��M_Ǫ����9���4T�,���I�Ȳ-�M_-P���$`���qol�n�њ���E!�����fUF�WcޗAy>�zl����۫�j�o���BV��#oq?���!�G g�ؚ�c��D�S�ծOzH(55���=B	5��W���H�%��}�Su,j-5I�m'l<��x=�=R@������\��e-(�*����u�n�6j1�X�"�ݷ�:�xPN�(ٕ��;ʷP�X*�S��QU���@O����<���<��F����/ޣ�1�;-^F�d��*�h~��p��Dӆ�ĵ�r�]fghm��΍/�.׺: 3&a6Ye��w:\ �\宗V�1H�9����J1`��iί*�5S@��q����o�>�Pe	<62�}����Y�Vw1�o�0:�����<lJ>���/�d�� �6�!#�R#p�x�����L�z��a+/h��J �j�L���{}E���k�mz���.�gA%tX�Rq�H�2N��* ��8�\-��hV3��*c5W<�ŎĜ��4�7�3,�}Jt�s{���ԇG�_ԟi�ܝ�4O�o�RS����{_�"7fM�@~�K\4��IC�da*�5`<2�ޡ�f�(Z$߂�n���_�۫;]GڮU��>#�Im�"��!%�<��g!�b$��OB$��j�i(x� x�2:!y��*u;�OR�-u�s��
Y��v:Z�k������D��d=��I�m���"M�+���qW�dI�c��L"
]��Q��O����S���#�u깕�i+�GՃ��=�%qxt�M�/�k�H�,g�����%����@g�5���8�6�Q�����]{sQ>\rMv��S��q�����3Rl
�?"��u�s	��3?�����aj/�d(T��<P�e�FBw���g��e[ ,�]�5�Yh ��^e��˕��P��U��&�
'yMKk5~|�>�3O��	C�$�S������TR'I��vK�u�HA�N���mu�Y<��1��cՁO��c�,:4̌��H`�գlΜ\�n�J�Q�����w�U �^z-�=�����N����cl��/�E��@��>�����߄t��H7���9F��v��=o�db�֟�h���X-X�F�~����	|�MD� ��)z������0Q(ŗ�^ut�[�&��\8��/�)b+4���t�f6"����.	���S|��z�ܕ�l��cv��7t�dy/�֌{�Y��g�l���BN�R���J�r�mn�e�.9��'��eT(s}��Rf����%�5�l�+��{7��8׳h/ Mc`�Í�.����X�����@|:];1�����#��249ā�����Ÿ��D ē�+3[^�j�ݒ�_WO{�,Z�).�=����͝C��4b�:)B%�U�Kch��!��n?�+�	��p0�����G�m���W��y�VZRGÊL��` �LH�X�e�wQ'��,����˩L�,!����揑C[��vmo𰌧��eWt��O���W
x��/�a�$��@�m��
���6�F�8���t�ٕu�V	�؝G�A��t�{�p	5�p�牨�BnNY��{-��vw��e ��,�ƪ%�f���Y�,61B �7�s� 0>N��:n��0	#�$|���v	Vy����%�a�> `w������iP�j��R*3�]"Z^���[���j��@�v���w�`���<B�J������C��u1B����^��3F�����[<��$A;y�(	5V�j�E"�v<.+;��I�H�X��h��Pc9���1�؋ւ/�j���7�"l)Fǧ����ݨ$�u7�A5y�A���O�§_j�R`������=A�[�fb�������'�y�k��(<�;T矉�0ɉ�E��&�SQ��oG�lz.�^��O��	`8Hn'q���%z'���������T�q��V�ց�mD�Q|n�3`�5 ��[�r�%�F�5��ႝ��\����C��N���$����
�fx��V-Z|A~x���Ӑ(�0�� g���c�qÀ`J��k��f!j����-_�E0��
�4(H�SqҮ��g�"�(¬'iq6X{LOp��0�!���!t��v�f��J2�⦃�'&yI��uS����o�|8���鎽���l܄�T^knZ�Q���E�Պ�No���o���6�lp��>(��
���$V�	n�G�t�&��_�i�������U���E���b���CJ��QxԬ�1?�[k��sFvPDXf�&���`SjG�&`36i�ϓ����ʅ��倌\�'?[���D���l��Z��l���b"GP�al}7�-W���!��nγr�~��o��4��a?#��Z�ڶ�{���N�s>�9�'��	�@�֘�0H��i���N|�����}���Z�?W-F�C�W����$��Z���5O�u�Z���q`.���^��j9��o"���tV���c΢D���*�LD�R�N��`���t7�]���=%G]n�E��sb����R�Ȉ,��I��m�D�c;����8K�Q��ՓIZ7�u<�m�$�t����B��0�X���^�vH��[�ϟKh�h���A/�V�,�qt��3q�%2@�/�����>���:��F�4�a�qӠ�K��n,U�i��28;�k�9W ���}I�E�!���^�G`CW���&d�R�T���7�O!��~{Ncd�3a�i��O͕����Q�vxs���f��w��`�d�ؠCI_]���
D=��{R��T�Sm.9�r�A��+����EN:SB��Zl;۬��~���c#�y�G�%�K��xช�	H���7��^kї�7ɣ�|�����狂��y25�
�\�t꿚0��!��Nc�UY��v�������q��E<1D��#�r��C�p�
�K��]JՓ�"��W��늚~2Bl�ad􊞐���CpE$8s�$k���ŉ^'��V�cE�h���R���K�w`VY$1���ᯎw}�#��a�ćO��oʴ�C�t��/��>�Z�
#�O�}��z��H��)���Y�?�!ƲF�j��wCp�sJ��<��o"�H=@OUK\p��L�v�=�;+."-�O���QDA�.�]�P�c�Jr�sv�������� ��B�	������.�m�6&c�B~�&:'��A��68��V��Ne��L�=�o�H:�(�R|����">ͫG��R��s;9�n�G�/��jc=Q��;[�J�Nh�]�����w��S�+���~��k��忺�r��2[�@��@��̊��D� 0��w���^}���|ۀ�e�1uGx4��U��'����w4�v��������u��k�v���~�5\��:�����%hYy��Y��d6��Y����.��ڴB+��&�/��]q�`)�q��{P�W�We��-��C���[_�ئJ�����}\4�(?�6�0�|�,�R"����3�(��OɡJ���9�9�@�,ԅ��(#����3���q$a\�Y���Es�
!��*m�efo�d�ҍ?0'��d�v6ކl	�G���6��S�U�]��8:���~ZP��B%��z?P��1��~��-��>[���{G�n��GL2����]�^Vu�c���x0ߧ�d�@�G�*�$TS$^��A�^��cU��v{�,��Id��3�<6M ���yp�I��p��u���P�C�A#����8�]#�NE��gg���+�ѝ_v�|�5�����2�N&n������p��"k�C�Z�XR�X�\���BGQ�"��w�`��p�O�,�[T<=�l/�.V� ��![�%��=
��T_����i:<�8��H��1?��5�(��(���k1�4yA7!T�k^��'��i W@���O%��D���(d�K@pS��/����N�P��:ZE��P+ON��X�]x�?��Ş^���R㦐��"�Ѹ��P
���?|JW���i|���q������ =I���>"E��eƇD%c�O�96���( [�ǋԒ��$L�QX{%��A��E�h��"��>mJ����[�?Z��ct�[�����R��Y�3z�5QB�����IPw��{��C2G�٥������G�ˇu�R��D{����X.H�� 2���a%��D�� >u�����en1�7�iM�^���9/��t��~i5�]�y��)���R��#Se�V����9�A���l��Ӫd�p�R9�e��5�0G���y˲�ܕ�kk⣠��s͏>�T�t��*]ɾV�4x9�ﯛ�迌^�bɒ�)�"�*b�t�ϼK#_u#ǖ�H��ڽ%"`hU��hP��$���/���������m��@IY$���:3��k���]�A�7�s������XM�N@�~;��UQ�e=��Ի���T�j2T皚s�]cm��K��1GB����q��*0ʆ��O��2HـϦI��Kn��Kf�Td^�N��UY�˕��-�+����6n�f"o���
 R����k��B����ޖ�1@)�gMeU�{�	\sF�jq�Y�r��+��w�w����s0�����J]V���Y"����T=���S=��,H��ܑHc��s�eJ �z��[����f\^��t֓j���Z��Ɏ��?�d ��nRQ=��#���&�zcﶎz;�!����L�P�mIp� T9)`<����W��`��t�R��6���(�;)����ܲ���>ء�A����M��3�)��-�=%"��ty�>��T�ڒ:ɽ�a��c�x�V}+c3�����崔����L������V�@�ŀ�@�`��;�I�g��%���kA{�Mz�5q�Ҽ��kc�_�"��O��xQ����\ĢMD�Ǫ��7�UCy���8�nY�����o(�UHL���
��:E��8��6
�kLu�܊-��8�J�����W%�y��q���)��yl���=³[�	�zhD��F´�[���&��M�+q�e�#vŝ�i|��]7�1�^D��-�C��qsN�_�,���i�҄&�%S�O�13�^=/�q�e�ԧ���e�9^��*^]������XY��en>���S���}@�DZ:Z���R,��)��d�/l+Ih�Qj��
�k�{�X��HM��~?��5�	l9��nu�����"B����7����@�#��i��+R��c�Z�_:���9�S�D�MX�Y���-E(,[0 ȕ�W;�b�C�e����Q��q�T�K(�"x�ና�&r��,���v�E�]��B09Q������QW1�7����O�	Kf� )�=����<���G�T�a��NK��z���Jae�]�p0�s��,ى(ߧ,��ٜ�L��ŹZ[��ӛ~]��?���k�Q� �p����s{��V�k��ۋ3����c}/Ġۼf�1!�`*u�!y�E�����;M��C���FX3�+vP����NC3��3�X8���;nho6^��i1g{�eUᮯ�D���ۏCз��/?�C38��_��4�d���V#V�U��z��<�F���s��,"|��5ġ�
�n���n^HD�V�$ر��������B�7�S�����L\���U^[��������J�!�
�^�'��Ez��^��8GN��i9��3�6��r��T2��,�7���8��Z�wΊS�O�F��.���9��>hWqI��F�'��$��+I��N_=hSBo�[Բ(�T*5 ���}o�����)Όu�a�	Ks,'�dÒ��E�� ��{��_�?}��Ӛp����>f�&��jg�I�08�F�h����M��y�	ccj�^�쎥|�_���$VlJ�� ~L�;���d�IobQ��f��).�s.u�v9�(
l��!1���E��	���r5�.�%Q��񉧰1�=�������D �%�i��L��3��c����b��]���ko(�����Z_���p�RF�J�"`	����,�n@u�Qi3�$�EE�N~6H���* �:����]���2��Ts��F�K�C�kZ�>�b5'�H�Z��eur5�'�8�[!��a�Ì�-���1�f*���w*�ǰ+d&3���H#)3:��/l��6�Y����tA�~N��Ԉ�{��?x��4[�:%<�q�qZh�=����T�Jz���������_�ա.�[��h�_�B�Sy2�w��8Ar��[�#jkԟQ"�F�C.�.��o/���(�BܰO���|g�Ts��%
�����̐��� T��5����rc���;Vz��æ����]A P	��b"�G�@��*z�`��W�h���oT���'�e�6���5h��z+��(3{��*�MT�JWĽ��޻��(�k9��}��{|��nir�o��Og'Ѝ5�S�9�/v�ø�������mij)������.*~l.&z� �v�����f�Tpuf	�>#��J�]CϿ��'�F3b;r(�������Ҿ�� )�Uݸ���ٿ\�� ����q��z5�5�J*W� O6��ى>��]��~3s��̄���O�%��t�Zܔ��)��ҏB�H��c�۬	s=EJ�$!����P�]c����N���t��LBX�[[�Y'M�;o$�߳�8�w�a9�Z2kFX���q��_�;� u�q�.��%�V� �D�4t��*?-�S��|û'��>)N\�1z�.m�QYb۴��]�:Q,!�Eg"�M���Y�e㶠�Ɏ��q�q�-�ٛ��'�H�= ,�Ȳ��5&o���,P;��m���t��c-l��JŰ�+�����T��9�k܍������`����H������G/#:N����_��1j��Y��������AN�nNS�`�(Vs|=�t��	(0N]�OJ��-KZ�D�";U,x��L �Ytv�t���AЕ�[�2^8O����c���G:H�_������s$�`WC+6�՘-�ۊ�83�y^7QT��N�{��`��e�)K��]uM}�u�6ˆ����KX�C� ��$-�(�j�������� �/ >B�`Z<�6���FeC#mD7~�f�{�&����Z��N��Q��|����@�ze`ſ^0r��X9A�!tL黭��.^җ|-��T[T��рz�xsa5�18����2}c ס�Hi�?a	��>dg�b>r/v�BȐ��u�k�]"�]�/Ĺ��L�^�;�M_6�B�u�";T��!L��u8e�}iva}��zW֍hfG����y]ٗe� ro��e��_�Y��6�&��P�ed"Z��E�E��~��:۩{uDE��9f\��Bj�ؤ�[^��[�o�����ۃ��D��.�a����#��4RZL�H;lXPj!�o��k����?mB#/��_6�����#_���jD~5���B�a���_�<z�K/N��eV~�>/�=��T�^���%h/7-�Ͻ$G����; !��a�pΩio�x�Ϳ�뇍��k����vEJ��WhQa��=�,�*�c�;^	���n�N?i���"/�F���/���g<�����<��2�3������{\��|�v�V��:��:hd��F�=xݞm1N��o=}Q���#��-�T�tO����,Bc�i+_a�yҖ�?p�o����uժ�Y̳�jX	��vH!:UbF��ִ��vHU�[�2�`O��a���x�`��\P���#[ݷ�V��Z����6�u��R��m2���+<�yP�._��5.��3��R�6�C�P;�@�ag������V���s�i�|:3m0�+O8�5�vg����HX*��w���J����K8|��b^�H�Ű�7-�_���#�eͅ	#��?c�*��1�Q&g@�]fS��7@Hg}hw�(��=�����I I�1�`�E^��B
9�P<�8�܆��L�
ѡ_�����d-�/��uV�ڡ���%08q�/-���Q��X��WZ ��|X�/����j�ME��+H��|@��+Z�?��k���L� Kt��#��\��*��c�P�o*];�����~zO��혠o�M�r����M��a�F��Թ)x��h+<�9a�th��$�?�9p�Ym@i�FI�K-i6�)<�Iv��"�d��R!��rL�6;-�˼ �o�S�C�#��y�x�M�[:�� 2���RF�S�����;5���>��f.L�}�G��)�zS��,8��F��*��3�i��W���ur���:�����`���=�y��vnD�H�&��r�@A`���3RAfړ�;iO��#���m��ـ�Y�Ҁ��&Dz�F�����U��C�'����U,a���#���m0G������Q�R�&�[ �FIʎ>d<��!my�m���6�9�Wq/*&�i8�ț�MX��C$][�J�ɰ\֖c[���DhJm>Mi7y���j<�����k�I��s6�7�Y➫��V�ڇ�֦y8�T\�ff\��;%�e`y��o�_0=
�#p�0p2�� >6�0�i�`@9��mK�o��s�j��س�A]�1&޼I�Ṉ��1�5�PW���❡�SBv�������M�Kw��}�;��ݙ�p��7���5�`c��L�? Ʌ}g?n�`�u8!o��
r����Ó�~�t��m�m�sf�_Ż
���f��%f��HE���\�*�BQҐ7"��-����gXq�cp52��϶��[���[p"<�,�Jv� �W��ǰw��#X��)�+��2x�m��	�5�AM7�����>��y	X�(6y��x�w��yu�bB��Tη{$},��b;�s�P;�G��+�#;I���ye���B��ދ�l��S��f���a��{��g�"�3u���9b�vb�o�n]�qTx�|��A�X�WfԑVc�]c�+V[
�ϵ;��M���L�<5�S���o^���=g���<H*��;6LA�q���0�8��.
C8�����4g]���}��f��%�ߠc���P�37.�)'�ޭ�ʀ����sq���+���B%*��]c�(7*�#�*�Bb�h+Z�Roc���JO�&/��z�K���}m!"L��$[mz]i���e��)�ZhsR�!i�U�.�T����[���%f�[J��/#��(�'[y�9L�7v1�������c��󮑆>�>��"��=�"h������u�5�
�,�D�uq�l�Y��� �N�:��vr����q�^���ň�.��g5-� IKDA�E����s����)�hOE|M��&�BA��y���]h���<�IKa����'L�ţ៹������������u ��# ��b/����!��c����k�-��|\��0R�@��+W:ӳFU4�^ߕ���l��6F�dꂻ�@�^������2:8��(�����M�ꑐ{�.���C��� r���z8)��*œ$6E��%!��PVr��ha�8r�Eǅ:�3�ꛃ&bI{:�߁_�S�r�n�n9i`re��O��=���?�ͮ�$η�s�x-��<�-,V�^b6;'�Ky�	u�D 5s?�x�"�q��n��3���U��A�Ҿ�P>���g7!P䣵kʓ�t��2�W&���׊�<䱐�w�P.b0�cK5�T�����S^-�[9>�����*#�!�y����uƬ�YXU���mA_�+����8}��v���-��y7H�r^.��$�"ze�f�FƸo��8�� ��q�G��ºԣ�2y��1d�g����k�ub�(��ɨ����(�Z����MC�{��e�zv��������j.�X��:v|~�&B:�n����-6���E�oqX(q�^����8��n�p�#������f�c��,dl&t26j�bh��*�s�7<1�6
�x�ٜ����_��#�<hϊ�VC_����<��:� �e�y���kv��s7�B���þ	F�p�m�"���2����32��#�"k�1�e�k���R�#r��.C�jm�#X:�]Yo����}h�^��<u�I��X)9�,��Ibb�w��?�:m� ��!�t&+T�!���N3�w)�L\�C��$��~"��o�-�.=�g�)��𱕚!R�+��Pf��$ƅ�uX�l<
̱�ܭ�ҕ��geI	u��f��jՖvi�ju����P���W�g���Va�3=�r���]�"���d`s��1�PY8����� �"�?�3���_5�Cg �:27���ι�6zL{}��I��b����1������n���e���5�R&l>+�Z%����D�ej�e�7��d�3����uYJ�Q2	��컞�U�¡<�(hLq�v���{�7Hm/��i(6���ڗyzF��P��Hn.�yJ͠aGu��l}�pՄҢL"�,x*�]h�4�0���lR]\PK3  c L��J    �,  �   d10 - Copy (24).zip�  AE	 �s�W{�8$�&l6ܣ�vbs��Űƻ�������OXI�����~ru�I�d�i��jWF�T�vdY��cl��  d��%9?a9�p��+���.B��\�: �L��ߔ���GH���2�-�ȨYw��U��/�`�x3V9�Z��D�L�A�W�tx�56�ne1�}�aG��N���'黈�����*�C�A4ظ;4ӴJ�r����������x�UH��f�4iJq"i��qO�0}T�2Z��W1߮�|�_Ð�A�w5>�w���z���� �;vk��j�C��L⻽���^�"��i���Uc�,�xz�ɠ? Z���QY�w�Xڎ���bMK���:���&V�r��,�!��3j��Z~/�(��V�+/���Ff#-���D��L�k�ڡ�i��doI�-���3���=���0d�KdI,��$���{I f��_,�?�;B	Ru:�Mi�7]aK}-h_�^[���ӕC�|eɩ�w��w6����k��D��e�D:�Ȩj��n�q�#�Υ��<T�`ڝ��"��}�Q��+���W���t�#OhS�|
��-�:g���.��t>�=�"@*=}�>Y�G핏�'�q��9LDcY=~C���U��N���P�"�N�V�a�*+:���eI�y'y:��F6&�'���ҡUN�QA��Z����1��W�2:{ ɗ�D����=jG�#�R��v+��	ә�`5O���úpL7�c��0�&ƀ�;�����mimUn��K�}[�˙B���>8Rx�f?=����^��'·$+n!�锼3��Z�d�i4�dj�����
���#)��u�O����^e��g~M���5���6�2Y�Ep�#^j���I(���a� ��}���냗�Ӑ�&�$y{h��W�����Up����mf����*�C��A��>g�!#�.����Q�b׃��u��s�%��;$LUμl�?4�,���>)�YF��.B�6P7)�n��B	�K�Ȑ����p�B1Ad����?��NK)��I��;�Y\[���6�A؅.#"Q{�|�~�U��D�ZXkz�nϼ>^ݘ6�T��˞翐3�IQ-B#�b�{%w������X4�:���t݋n͓��:�`A\i���U�`�u//f�8�k�'���@*���ѧ���&����c�.����P�Z�6u���iD͗3���m�t��:��y�9>��6�����_P��}����Ť�Un���TX��ʃWcm,Beej����\8�3O*���:�H$�����!al�H��%�?�y����4g�N�զR�Hc��@{�njfFw�^��[�q��H�hV�X����W��ᔙ��#gb4��_W!T'"�-��v�L�@}�� i�oy����e��S����u���u��|���1��^V��x�����?)y*���2�9�hR�ϗ5(�fѹ�
�
�Zzi/$�ZJ{,�����^Q�k�xV���|S{�oz�<$Lol �1{|#l�!�����ˤ��	�j����a�W~%��������%,
�'���T���tq�e����,��n��pNJ~���4M��W��=}W�A>��@�
Wvq����A�H�h��� �f�0c���GR�+��;*�p��� ��U%�ħ.���ݫ۶v��g?ݰ��f���7)��n6!���o�����B�ɞ̔��D�忁�3i�񽜗!�t�k@P�>����n �<Lm�X�7�ך� ���c95���� �V��+�[8���@�eE������i^�D�9S4�����dN.p��pqȓe�[�c�uyG�7s�,h VEA�f�1�K�4x��*�.6�8��(:��n��D�8�z`X�41W�T5y�b�%X�7��u���ޝ"V��KG����S�8�ˡ�8i�������t���V��i�D����N-1کZ���/�[�xe�O��7AJ֫�Ji�,���FN ��{Ό�{`��הT%1b��%��I�*�{nc0T�9�cD�X��T��5���4TlL�ܑ���v�O"gУ�������:��,��W��C�Ԍ�=����<#n�"eB�X3�����A���[�{���s~3��~�7�M`70��h������]��[z4c�,�/�K��>-��0��p��vzH����,.~��M��%5�r;	�]�)�i�N��W��\P�D!��c�w�g��3
q����B�P�:�����v!�'v`��v��*�T��DV6�J8MC���vb=0�oe��p@�mZc29�����Y�"�.O�4x�:amb��WG����-5t�H�Z�Sk��1̟<����������J�`��^au��J}q�{��e�p�σa���������Ѩm�b)X�N	����A�w�.����bo��uh~Fx�U�r@Q������~JH���Ce�1����o���T����	�/x�8<����*���+�o�T�T����S¡'ӏ<�me���u�+[���(�:a�xf�AaƩ�(;A+�V�Z�
��/$��C57��0ɹ�gE�ٍ�U|2r���ӗ����[戝
�[���S��L[��7��H���5�V���y�a�ʠ��R���\�;�.:����r��/y��ʏ�V*�ܭ�Y��Z��;?����������>��s��5
/rV�Q�KK"V*��m����]�c�4&L�ŋ��}0��[
�~㶬%���h]*��\��MC�o�9B����kq��gJ��P�Dqୈ��1:Mhl��d�qd��e9��@��Z�!8��h2��4@�]%?J��q�׵��6f�lS-d}���B�*fM�f�;i]K��!��h�0訃�4����C^#O(�%#&�wZ�h�2��HQ��ey�"�~�Bը�~��<4��xӾ���>�ǝ+6�m�i��΋l!)K���",�0���A��)c��-�P����]I�$}���ƶ�]�y/���n�D��.���S�w����IU���8�˭�����1��c%So#d/�H��o^\�e�B3�H�1Yʩ���s��@x��M�{�*�]_��	\{fI���\�iU>�r�`�Mzj ��6q�1���;=)4��[�J�v�G�׎IvaMl�|Xm��Xl��R���`�lϤ&�̤���(�ų�}BO��L= hL��,������CLPs$��a%P�Z�[�$��z��f���@	D/��l�Iz�z���j��/F�j�sS��X�QA'ǐ�ڑ���{��! \R��������3��h�������G�+)IC{<�{�C�mQz�*������C�����]q��C�n�w�~N�}1  ]d��������o��H�wG@ڬ�Av�A~�l�"Vo��r~#J�&=������6�袧�UHK�`��Hg�u��>d'ۊ�W*���y
��Y�2ڃ4����9����]���R���z�P��{4�dT>�ũ�xU����)Ή����"���W�y����F|2L�]t
��",�X��'��^�?<��>v�$4�=�ܱ�)�����`�j��}��|g�, �`�[)���>�m���Tl�MX�3��o�j7�"Sh���=hN��w�Z~煞h&'x��i����z�-���сR��z"4@�U�~��B&�p����ha�RAS�;��-N�L�-*]�-C�ڭ"��)�G�N���¾S;ڍ�R�+sjJ<oWR�I��y}(��n�raz����GEN!�>ZA�;�/�WV���ߎ`HK�0^���9��H��i��lV��q�(���3� �z\Ԡ��h��+�����:a�ki	���ʴ~�U	P꽱(������D��t9\J n�C*�8sz�;c�e-;d� :���?�&��j���]d�OPe��Sb��O*��	>�B��OX�36WT<��S��Ûl4`A���Kf��L�E�C~�<��目��k%J_��z�W@��A�߅��Z���(u{�[����G|+���$p�]�62c+3��ᱫnM+k�`���MR`�����wZm�&v���lw��/Yv����VU �..��8�UOˤ�(=��CV���Rɡ��� wU\�h`�g��%[�4nz��WJ`��=ɟ8�S �j�$���c ;�ʋ���D�!-���G�����;��K6���L��҅�.���¿�~ϣ�[dU@�c�!jM[�6�on}9L/W�p���_Q�����p�P��U�����X6���$�w<��NE��ŗ���������X/�
��w[x��}=wH���8����^��Ŭ\�{��\�7�R愁���>�37A^��	$�{�b�$#g3F;K�!��0�c��ė,E>���+aT�C���:�:��FS�#�5�����;��l��`����T������$��+��TV�L�������yҿ�%��7�r$	����Y&e�1�r_�쪵-��������	guh����M���W$0���t1��&B><Qd�og��.�m^�z~<������茻���Uv<����|���>��FqG�(E"y:CVR`u�$����L0/6�<�i��
�b�"����ƽJ��
uю!����F��=&��1����m���G�Jchڬ$����GF&�{v	�	�:2��ؚ6�XYN[�l��\sϰGdI�.���h��Œ�v������אu���]���M�/`�_n�~J�}>^�\�7M��&YE�qh��O�,:�(l:�W��Ϫ�Ʒ5$-���')g�g�����"�`yD��vK'c��:�=����	�@�~�ͯ'&�7\s��ţk��Kp.���V һ����z2��@d<����9;)��E�2:��c��_�J��>���d�ɬ-�5oR���(���ړ�\��M����u-0Le���M�NZӌl��&{�d�^�L$Sl��cj��� ] .�Lh4Ѕi�W���������9JG���j���i�=�ڨnϊ��\�:ƍ��j��d\G�Z��3j��|�kG�jT6��u'`�<��ͨ0�зR��KG�������ub�>DA;�4��������e�&L��;���
��!H���x`J� (gh@��� �vW��ʯ���?�o�G�X�W-��}��(���뎮�|�0�^���`9��mU=T͊�N���ݑ7�2o�j����7�i���Ɂ�K�EXgD)G/�? m�>�Ը�$Jf(������B|mT�����y��-)^�%��oy�6�[Z�-ɯ�����z���)כ�|�R5G�h�B �H24B�5��Z������Ԝ����w�sO�PX��Ɨ:�r ��3^f#U�w�lF� 5�p5�RPF���0��nd9��G'v���N�<�ڝ��e=�����j�Zң���4wپTA��/h����[ȫ'�c-��o8�٬G$������30�W��%���u�}i��
@��od�QJ�"C�uX����ø�y��BwY�)|[��4�m�>�!���9z}���`��������U�H�ӯ>c{ȓA��*�'i.��)��?����,NL���eH_���e��L6��j��EZ�uT�:`�-�����e4wX���©LyV�	dʥh>�BENmM�KI�5��f��<y���`(�{5�y<5/�����)�\o���B��]�*�:]��|�n��ז��uo���5U���uHC?��`Ĭ2��#<�Q�y����{yKiyj�_��h�'jV���n%̇�4�6S�dM�/&D�!TK�|1�8����,؉w�h�#��.-��g��/��,�B�)Z04�<Gq�����<ɲ�	������}�
�_�>���<N<F��FX�TC-��W}_�g)�At�i�P�q���dN�=?�7���G�V���t�;��v�;Ǘ��K�N?"aa���țܥeӋ� ��m�ʛ_*q.��+���Dd��֏�lfj6O��}�b�˿yLS�zGXW���O�_P0=�+b���U+�C���������-\�N� :��S�!��>��	�x�Aע�& .9>�JLB�
�	q,ҟK�sk_0�V�9�.�W����J����@���}���R�j�F�EBrn$���m��9"���u#8-�^gAN���j	�w��|�<��'���޵��^�I�
.=Zj��UG��L�泹 nI:C�u�~Q,w�(��]�L��-H�߃�����^�>m��bc��Rn/8+n�ŚuX(Z�3X���?c:t�s�QW/Y�.�i�Wo�f�rH3�F��W���o���%^�o~6��u���5��TZ�x�L��V7o��N���v#�Y����z3-��g�h�����W��NRBz����`x0$��6֗N���0o̠����7Ĵ2Ch�E�HR�)6�*%~�$�b�:�e�ƭ���wm�7�Y�X�&�B� �6�5��Pw�Q��x�8��Gu���ʡ����1V���y<w,~?���Yckǝ�7.��$Hr�����:�ɴ�U.���>� /������n�;����;k�-��R,5��w��W��J����u&���⬡g�"��E~Uq������b� N�	f6��v�ܙA��^�ˑ}LM��r�B�}��-�����M����zՕio9��+=�J�X�5},��Rrv��%(��ݕ�3�>�i�.�?��X�Â�S�r��y��Ճ#�o��GA6d]����ѳ_�EE@d: [uN�={��D�trq1��u]��(,�!q�h<�ZX�My�y�:ݡ���֯���u�E������Y���3�}�c�G��X �1#��k�l�P��n&)��+������4QW?��s����0��5��"K5X��I�:v�o�{T����i"�vBWu!B�@��`��6,,]<�N�\VQ�ӄ�K�Rm=˯0M\ؒ�A�u�x����*^�_(��		G����We��6��3E-�@��{SZ2�t��d9�k�m���^��ACP���5>��lx+&�c)���N�w(ǁ�_�ޯ�C��R�:�h��m���i_�C�F�Tr����t��D�Q@c����\����kZ�գ���WaZ$ᜱpB ^ @��>��{�N ~���>N�5��fux�<t&�6k�n��݀T�p�:��Hx#�|�;�cC�o�H~Tj�	S']��V����p<�p�׃�������t�}Y�s	�sW��m0qK���<��)�8��>k�R�����XfRQ����/]���d^�4��+M���vf�x��nT4�
J]yD�@%8�}�?v���$}
F;�
���	a�k"�b�3�
t��|�f��:��ف�d�$&����G��/��x0��_71%tE��8�2��d&[�����a����)R�&81I�c��G�'Ż�����	��?�
w��h���;(�g.%��q���iE��ݜ�����a쒉�Ws���+ȓoO���}Ax�*��BRvE�*�sw� �[�@[� �z��27��s� �ܩz��N�O$ӂ�>W��r��� 
��dC@i���s�IJ��y��*���i�;ԩ� ��m�TA+�dз��lE@k>�D"\S�k�6@�,Q{4�r�Y����a=�G�x	���:s��F�q�8�}�	�m���)�m�2Y�`��� .?�,2J�^�C,�������}��8�>Vx�;<-�qzwK~�ғ�B|��]�C�/Ҩ��W�/����u���݉���i-)���>�<=_>ROħM	 �I���u��lޯ9>�u��������!��� �&$�%&
�l�C�Є���*�x����r�� I?n���;�+�Ԭ�����A͍�gBi�i����I ��nۂ�����o�ɝ�)�ơ�y�b��.��g�M)v����#כ۞)�Ś�`R���O�$��tN�E�K�U9��^�+�u�I5��P�W�w����ht�"!^�G�~����ѭ5�K�K�h֔O2�iqz�ؤ��G�,�����&�|p�4]��E��tN|�����/��O����"��Te���Ξ�5��^���;���T��))�� ���;2���]�g�'Z?�0M�Ȯ�=���tP����RuRD쀴kЃ��J�v5�3�~�͈�#�J�T_/�e�x�P��	�lf&P�"x�(@hTI�*�,I}?��N<\���&�W�ue�BI�T�n���O"y��c���ԋƲ�ǥi�D�x�t����V�k�8R�䀑�]A��F
��L����
C�6vl����"���R4[��<�5�H������z��`���_0žњ��<~
�w�ơ<@�?�^��
�·G7 t2,0v:�{{��c��R�g�U)4���h��HܾI*7���9C�A�d�>���h��X�$�{}��'N�u]�֣#�$�Y��3��q��Q;��d"�i��6�#@�?8�B�_@ڥ���Tuhf��)t:�𪼶D,R�
HރD�N��X"B��8�@��eW�0l��c�=�Cd��W[�R}�����[~����DC��lD�kx�O_{a+MP����?�7 ��r�?<Q<�a 0Uċ�b�&|} 8
��2_��.9��NْQs/��FXc� �7_�y[%����m��sԓ����'*v�� *�Kj��4K��6���6p���E��DϜ3�T'��U����ǡU,�*��z��A�K[iVD��Z�z�}+�-3�GŐ��	��e28F/:~Irl����m�����d�އK���z��9�6ԉw�t �uo�O�C٥>�'�t̰l��Xֈ��-��+aTk>3�hͭ�	_z֦��d�=���m�"��U-͟��|����mk��f�Wa�@��V����|l���a��H��~����p^^�'O�������:�&�Y��Jt��^��_���2�U���]���t=��J+X�
�wShV'�uee���cɥ?��& Ǟ����j�G�S��VK�S~�0�ջ4W����Xz@����d�����ؤ��H.J �v|Yk�v(_��ٱ3~���I̩&N�e�"؍��-[�i:3��k�%��YP�An�s�zr����=�;�&��_����r�o�����F[�������b���Ii5�j�`b�M�z���EA�u�l��I4խ��Ҡ���o����R|ٮ���&Y�-��T�^4㻽m[��<�io��O���P�3�9�f��*���.�U�7�/T��E�\��>4 6?�acdeg�FġI���"I�<�(��|h�ς� O��'�R�Fᵆ�U>W(��*~o�> �>�_�m����x�{Q�
�x!ƀ��'0W������
�C�Ab��N��zU_'1-�isӶ� �1�����K�B<��k@A؜G�}vuƄ�+�푧1ӡM�'��=��˘�d��0��S���F("��ؤ�ԛ�xj�Ɉ(z���cl�Vq������)C��"�245q��ӺiÍK�ǯvOf��X��t�W3��їb=��;��D!J��NB������5��a���n��b3O|�sxo�9V]��x� ��߭<˽��+��9P;r��2)�&8�7tb.5��}��0L�B����j#@�����eJ��<�]s҆2�)<�����Ŝ$gsa9�[�gZm�L���迸Lo�,����q|ע��C+Mߴ£:f�""[��f8��'�*U1�Cy-o�8����P�0��5�X�a�:����|���i9��=��J����[��'`���f�����Bz�s�g�{ D�m��=T���<!���E)�6�B/�DQ����"�4߼��#�k���?$�����Q�`�h�n��U����s���U�cK
d�t_��,�'�f��J��i���ǾփZ��������B�
r*L��n/׃TU����E�,Іj>S�a0�ߑ�]XJ��~rN�.���ytj
'k"����@�5��
�W�7�jфf��u�3(Qe�቎��V�+���rʿ��pd��S@�8�r��J�#�����o`�vz0���Q���pD�d�'������.���+2Ϛ/��o��	Dj�W1�L�\�;��-x�X]�d��r����[/iN��m�<��8�)*d����i�D�o�8����Z�on�".@�ܽup��Z1�,ǲݵ�!�a��Q�Y�����8V%'�/��X|^S��	�H��y֌|�7j��+ŧ�PibK	5��f26�����X�k �ɸ���ZȠJ$3Qj�b��T������{|�O�J���ΐ��X����񠷩�b��<Ne���Җ0�M��,B@B�״9�֖�� �NpXù|��U�=߼����Dz�I\��70��{���F�>��&���	��ҥ!c�I���/�0ŕ���K�P�"��Ud_Sb$�2F�T� { ��AW^0V*�K�1���P�E�<�m]��jg3�x���B�d�-��+^|&Hn�V���\m��AC��Чl��W����-��j�r�S�\9�\�Bò�W6�Jg�Q�|:�d����Λ?��F8݄.. ���H'DĳB8�=�p���"*K�fDwAd��4#%�9�2wY����{o��5���bn�O�;�ϰP#�ꦄȆhMwz��(�����j�-5T�W�YĢ-ʶox*�-�b9�1Ap�I���T�͔)q,�HXO��t���}~��6"�qgB��8��fT�g�<�&�0�n����pD�o�x�`4��R��@���e���E(dk�պϤs�&ka�e���5�ˋ�R�ht������b�����NԸ��p�tf��vp����F�J���e��y7�H��R�y5sZJ��{��^.�ѳ��M�,���]�L����g���;^�
��A�.����8
���btvc����z�n�ӿ��ǦR���K�ѓ�!���B�����x=���{��~6�^Uƒ8z�c�[��؏[u��"��Bu��� ���s<���
N�Ԝ�E#�	�4�(� ��z)���Y�>� CR��;V��B��k\(�5k��v��$��C7yR����i����GLA��=<S�V��oΘ蒑������!���o[��t�44ދ�����4����PK3  c L��J    �,  �   d10 - Copy (25).zip�  AE	 �BFM��e*<3�@�3���x�k&��æ�P�ȜQ88���
<���KV�J&lߔ]�aѩx�k��	w�h�`�~��;�dO1�@+�����*2�N8?mՀ=�q^�
��r)��̎��$ߨO�(n�0�&�Ͽ*(��eڻ`�
,>̻I��S�D��N���_��S�rBre��/a�t�ã��U����]�t��ͩK���5�~�}�v�)����B��J�Κ��s�h��J8Y����s��jjX{">{#m9z���EX�ڈ}"�����#�[K��q{##�")m�KOnģ����N�'5���tޛ�'� n<������֫}|�Ɠ|�����������#ʫ݀:��*��rX��F/9���c�W�6����^m8�:0f�0IPU(*�.C��R5�Ƙ�@*�R��c��]I��~���1�R͂s�0c ��6����K_�mǁĝw�b�#��n�����ͅ�n�F:�ß��K�ţ�N���Q���I�� �x�t�@�5��E��X�,�#�sS��M�������ԡD���94X�N����V�U��̠�V�G�"���+OY��yf	�iO���A��S��£�%X�g�s��<�'���&��L�aX!�����	�b�ߵGD�;��E~�m���OG9�����Er�c[�;�xiy�k*����HS��W���G�=�R�#=gjMG��E�uT�����k�j�S<D�2�iܥ��O�.�{�kN�Ezі�[+[���.�
9���5��IrdA�*�o[��ZL�EQ{~��x-�>����//�hgq���m����m��M����~�6} ��|�+g���#w*��.�0��W� ���v���ϐ�ٔ���
��xG�V�	}\�e���.��z}c�}p	��L5��j^���r퀺EH��-75���S���5�dt�o,�ݸ���.�L7���"F˨9�s,��Q��}a��|�y�mZ�� q ��I5���Kј�pѝ��ws����'ס	���;+��G"�eK2�`n#a3#Z<%E�!,�&��k�Q�|����Pa��6{�����.EU�-�y����V[��D
f^����F�(���ZA>�|��+ƥC���kŜ
V��غЛ�'�w>M3�V�rc��U4�!�?;V��g��[�������\�6�W��@����w�������gd�ȧ!�����[m�/�$���՝fu�E��̀8].z���ܲz)[���>-�Jȃ�N�����%V?�j���;�3M��6��l=3qd�$P���lA�<�cr�]5��K�2�Z�â�7��ȹ+zg%�QX�g7��[cv66ڕ�&[R#Y�� �����A�^�Ъ�0d��J2N~?����t��p��b��xXȟ�"DD��U���W�7�٠Ð5��`��L����q�[�)�e���-�ha8)�i��v�ܽ4P�g�z���b&u~�u�" �J�٩�-UZʷ5�)�N�@���VUeH#!nh�֠6$��$1��ޯq,t�?���E	o,���Z?9�\���I!+;�M�y0��~K�'l	��3ܔ���_���q�(&.�g?ٟK���Q9���v4� �rߛ��!z�wv��)Q�������Գ�8���oY%{p^�VE-e����r��mϫ�<������fS?�js�W�ez<��-+��/P��D�g\�>J��m�R�vE�Vo�����i�7�ƙ�T�/�*�A�C۳��n,d����:��'��{
���o�a��Mqi^XioJ��CB�1��D���Ǜh.m�<i3s����3r�}&�p���\j�-&�a[#)iH�ҹ�-)7����qis���o6!)��`�=Yf��.�dx��2��i��@�ٞ"���ƙ��Բ�6}�}����Ǎ�ޖ��N3Bg���X�K�e�N���'�߭�sn�<�S������ 8#s ZB�pӔ\6���3�qCY��&��-7���.SZ��Q���l!��V�q�g��ޛ�>�f���j�Sk��M���?� E�؜U_)�~���!��O�ݲ�]i�ٵ���'�=?��]N�����|���~��ə0<��C�����v�� �*3b3\�[~��<�)�\�K}���������S����k�����G��~�/��ɫ���O��:g�\(En�u������Y
3wԋ��>����E۝��$�D��d�GqM0H�in�C��3sPJ�m���wJ��	zyC�v慴dŔ��b���7�0���KA*
��`+�����T���Yr*����	!����7��6��a+�;u_m���	��!�ث��rWE���n��*B�I��R���U�(�s(\5�G{!��ҙz������`_��-.�*7rߌVy;�����~@�n�?��*|d��]	Z��4Yzd<Z���̥��(|�����´�/��_2��3�%���gҖD��5B�p ]�Sʹ�lufy�TtZt��H����x0���K͛�e��ˊ��X�٠��<dW~O�@���0Z��N���:J]�?���V ��|gNa�`�@O�!��G�C�(YlZ�C>Sfb*Jn��K^
�8;�J?������`)-*9!�!Ƀ��L�����ul��o�gO?(Y�����E��|";S*YP���I4�E(tT3�l��;b�ď�oS)�Ƣ��q~�rY�pJfV�}�����o	L��K�!�
6��
�YKb����"J��(�;G�-F�qF�����	�����h{��rK���H�����L��IK�.X[b�f�@~J^�8a�bC��f�)H�f]0S9�ʹ���.8I)��l����S���;�jt��[4k�j��j}1O���J$���ṁ`{>�TA^��I5K)� �fmd��?�~ȋVP��'���_���2��uͯ��w�� �?�^%����4���k`� ����w�8N�g��s�IHC�����%)��h�%=-��P�e��X� A�W�6���Қ���5���|���|��
y�{S��r�^Ҽ$IM&�B&I�����\���[s�gO�hRx�~���31`�3n�S��A� O�n��Pe:���Z�rg7l���^���rc��8t%���k}��HK����.�;�x>�e���"�|�1�,b�
6ϑ��:7���E�'��Z�x�i}����~F�
�0h�j""����m���*�������n�D���ˎѽ�2�2z�����ꜞ0�'�tT��O����r�m�g_�X(]S�O�0�w��fQxn�S�R�&2U�Ș(�a:�$�j��t�U��٧�|8(�jT��[�c���m�Eq��nm���}�����XS�j|a-O�wO���-���ަ��@F����>�DX�]|�F{t�%�[����d�W��A�m�K
jx� ��r�"��	{���m�mn�g�����>aT�B�ǦhlѨ|p�?���7��|H�F`�ݎ+?�{�o�#5�7X��Q1���ֹ; ��	��0�sϴP|n��: �>4�{U#���ȣI2��*��j?i�����~艏�G8�C�{	��G]9{WY�= ��p�o��b����6��X/+�v�A/�|���=��h�d�Wl�,'U�-��Yy����`�4�G!H� ��+u�W��?C\���M��VN5T&WL|�2�9p*��x}�%.ݖ�޵��ּ�˞^ת+8�q�o:�2㑵À���*��4�/N�3+	�c��{�J�H���?����S �ld���V���S_tO��M��^���>k�:� "]�v�%���¬%�� {'�.�9Rpf�ͽ��Ʌ����ά�FKXI;������b� \5�e��6�E$v*3>^\ �e�lJ��u�! h�%�M��x�l�6�	�F��|�}���z�&�eL�n����Y����̞�Uʿ�z�t���h�B�d�eJ�=��@��KԆ��j�~j^�I���]^|�dQհ�a5@�s\�Vw�R���q�|��a6���$<g����3���0?9(.�������]�-���_$ ��U���e�����łzf��jh�ƕ��V���@�������R[��`翸��'�\}W4�CKW������?jS䘢�r�o`���FKp�p��%�����0P��|�s����«#�$)��������|2I>h.�[�=m��]{x6 ����I�0�Ow��P��\���+H=�>�T��&4#&[����&^������hn��&
Tv��w���n�7��F��k�D6pne
Io5���6�YJ>�7�=��ǩ'f�3�$D��\0o"����T����� �~.�$ߍȃRi��٣m
6Z�G�maoB�~00���$��r�K(��S�ꚓU0bp���3;[��a �W_V�
Kk�M��BQ����iX�h5��[T{ Q�l�z`�#_ղ�ͰW�����]����.	�48hp��z\�a0$�������P�L�Ai�~]�}��苋���s>��lZ�<��W;�j��>���%g���0J�n'`���-�hIof��R�i/��j Bm���nZ�{�t��,kI�BُK>�k�D_�dlb�����T)�
8�ʜ�:HC��cS9�=er�S홫�zk��}M����ڈQԽ�(å�K�V5���K]�a�ވL�^C�&[�g��v��� �]e�q����i�B��}�9��J͊���sn�=���f�
�a�9�
ٌ��A����4*�/�]#����9풦�%;(d&�&���X�n�{����ȑA�:�/�_=��9�A1{~�Б1�wF���/[#��P�ޟ-L��}CR1�;qⅪ�k6��Kr� �~w]��Eq���4���ں��]�d�!I�.�����*�RY����K6�H
�Fl@\a7��U�������$H�L�J��[}�&KK a� ��-�}����y��M4i�����r
�ƉF��9�w����x�RW�s�_�Z��߁���(��ξ�|Z�$���JGT��'��dfI(s�phȵn�
ѵ�M"J{��]S%1rM;>!v3c|�Djr�]�D����j�)ȟK�!`�ܙ�� �#^�b7�~�c:�4��[��),1���h=H��N�μ#��Nl�+@.`r�Vbm��m��:��Y�75�x�n�&q�X�Lr/�EL�P59��ń������*�8�b�r����-�;Q�T������m�Q��;*x�����"�x2��Q�������"���y���1w'��L�����0��bhq¸�5!�}sh�)��@�t�p�2�apD���'	G�����֣߫��sӸ�ǪX������E�"�{!!4�)�Z������>HK�*-�0�	��N���(��1D�}ΰ��S��iry��:������-�.rز*�F�r��].I�qcK�فA��G���4���h�=Ot��M�&yH�!��"����8����/�+%��EzF�ӥ��L��aMF`�&>���x�O��q�]�?�+]�u�W !	��_�3�Wq7yȡC=%:����_�QD{V�������N&vO���ב$�i;���st82?�5�� >��JV ۄ;J;dz��)�|I��aNe����q��u�ׂ�Rn�d�a'�:�,��,1����M���i�R�Q�(f���%D��\*�9�ɛ�m�(6��J)�-�Э6�g��|��Ռ*���r|�
(2�0'Fu��)OД�s��<�{�*�i�]�%�kx5M��r���Ώ�Oj$�e��{O�A�8�\���Z��
�jH�hfn`���e�L�YWy�[�ەA*-���S�[�d �<Ueb�{��~��+OD*�͟�(���VC���h֜ ��KB����Z�t�����Q|� �{��݊����ӳ���������+�����9i��?�G%�,!:B�ՀKt�ƹ`΄6��L{����]�a�"Xd@>�($}B;������Y+�i��,��?EEB�6A�nuc>���n��gƺ��\!πe��k|=�I��R�����IV�kV,����;>=܂�;'�n�KA��wx�XA���Ɓ�pXC C.�=���3
�A��i]�[��72e�0�>!K��� 3:V�,S"���� ��LJI��D{v5��b>9}�2};��P�l��߽�i��β�ܘ�}� Iæ.ۯ�]�	D���b@/�|�W�� {�g���ȕ\��=��\�N/����i�L/);q��%�7ڑ�\g&0�w�:9�LtH{��^ �L�B1��L��0����I��ɘ��p2D���)"�5�x�����h��|�t�M��1pĦ��{t�[�U��}�S�b[��a��������� ���dr��G�>�ɴ���s��V8�_ǜ�*3��s���< �e��;��6"Aƺ���7N�a���Ph�Ȼ�{�j������s2э�o� ���{��+Ѻ�Ǯ� �Q���4&�2ܒ�&��ض��m��z�Wan7�O�4�m�hi��&e��q�<�y���Z�F��ɡף:fY��M+�������`~0w�c�e4�R/@gR�WM�q���K����Ϲ(��C��n2�`?��٘�oBuy�b�CP�����h�
.����+�!�sMƥ�E�уS1vL�˳���?��]�w�����/�šp� 9N5+������^D���Z�G�Ҁ��		u��wO�S�iX�k��y$�ǹGZ6~��ԥ=�p_EUlL *	�^��m2��0��Ks,3)#�^t�5f�c杖� �A�yu.��c`V���a��T���7^�����4By������7��3թ[�����6�}3���%���TXZ�'w8���7c�.٢�<��dq�dހ� �����~!͘C��+��z
�M?5�ĂK��E�mL����Ճ�zV�9�C����ܑ7j��Xbا�/�����L]����'ñ.���l�3Bƈ�bx�x �i�j���N�������g��\6^G��lX�G��I��U�����#��֘#��䴀�-�^�It_�S�� G�>���|�?����vk�.u�����0��ħE�a�$�1�[j�<�`����\<&�S���aS���'����Ɋ�B��`ͱ�*�9�g���7�#��&G��}w_����B�i�"3IN�b`��K�;Y(��f�6��^�F��̹{�d�}�zU�D�3�A�5���龨�Wg6���DT
���T��+Ӓ�'�i�1�r������n�rb�9eU>#q�$ku,�@��9��С�V��������Ɗ��\���\I���'!}|k��џ���+g�:j�"!��	po;�K�P�}yX�鱵�ȷA�w� ׀��P/?��v;�V�Z=��{?x�܋��	�W۱�Ň����u�򸌕�G��fU��T#GS,�"�ag�u�O�����1¿������~��^�d^2DF��i��"�i9�<`@r�(�>Q*5��6AZ(�/|���� *�U1}�ǽs"c�����r/�7$7��y���`J}��:
r�����4�<D.���V��d��6���&��^#�|f:h���׳��'Z�]��Á�|�۬$ݕ�G��9-�ھ�R`r���3X���O�h��I����LDN��C�l�l7���f�M�F���`�`���n�?j�>�k.�41�+��I���0=��`o�9���	�ɂ����ID*7m5&"h�KX�cu�]")�)T��y�	og!
�����%@q���^ߟ��*�����7Mw�|(�]�����:#�O�0���,:���'M�1y8G��
Q?���K�\��h q$��@����.#�xN��OLWyUa��P�-i8$h��%�;��"�Ⴤ7��ب����5"/��v���4�t��:]�bŭ���0	�^��5,��[��x�$})�sÚ���/πdQ�p>�{Ľ��)	&Yp�>�R<����`o����~�S'h��8�ن�m�"XT��NX���]^H����Zƾ�&*��._ST�C�9b8�����ӭ}b'H�����%�tQ1�����kpB��l��o�����k��2\�վ�R-��.���X胖A�9L��<G���9��[�E�l�˧�Y�T;u�N� ����K�V��Y3���k�-n)+o���_�� ���E5��R���!���Fp
ٓ�>&�J���]�h�3�F�T��3M}l�P "�n"�N��� cgÛ��"��%����#��	��(d.�(	�7s0�ԙҪ����P���liU����X��]b����sNyI	�	r�/��`���L����7G� S6U�g��O���c>�en�Y�W&[�e:W7���Fz���5+A3�/�3&��دM�C�^��7n���5�M��=��J̥\�!���������|����X������J-_�ڽ}��Y�d\�H�6=5}�B��Ia� ���+g0i�Bs,�{��U�F_�S+��x_&�_�ҩ=q:z-5�����d����g+DȰL&��`ݎAH(i/�7���n��`�xQ�ڝվ��z,W��i<��Ꭼ"��۹��L��<�t� ��ʧnj³S����I�By{O�=���1�_.=�Q�\��e���Lm���z2��1D[8�&��o,����Ń�!�
ӯ�M�1̾�.*T>�-k����iP�h���<;;�v����0=� a�H��m��#���Y�k]u���?��'f��_�D8~*<�A��W}������R�ܠ�&9q���YlA�̍K�E�O��׃E��t�UBO�-S/_�p<��yrF�}�9���##��O��>qq��ۅܞa|屓��!�c��<��J��
��2c{@+��^���UW��~��f�̣��T�f
-;Lm;f_��d�Lz�{�M�_��/�y��ԌH~�uVJ�/$D�aƹ�o����"�c�?�f�P_iA{�����,[T0�� �KF�A�h��מ�6	��sh��޹Im��L���ea�7`>NS�B唬�. ��������-�w$���5����6�l�:��1
 �1�h'�$�|
_�HVae����4 *��)�6��ǵn�QK���j� e�;����sA�É�d�R�,G�����T%É��^T��;�|��*P�#�cYz�=/��?M�9��J�����RG����͞���,��!�= �ƒ|ie������pL��𥽈e��a�('�H�S�|(�m�B"��W,��aѹ��)Te�Vɩ1=a��~nl�]O�? ~��1��z�CͲ!ӂU���x���2;���E@�
w��g	፨��1��s�a����|������u�������:.�X<Ŷ����1{��z�%U��T[�q �a�Ӽ^(���1�7�0.ξQԸe�!��O��?fyT2p����rUP1C�
m���!��`4�27_čA�F�{���a��Μ��p���?m��Ë�NۑL�����(�#n4:^^�f��|;~�J��.�᪪Gs��E_�ŷqI��W�H_J.Ҡm,�[�*M`�C�w6/����s�j���%ɊK���l\���t�r�	�e�A�SU���VN�6���7�L?���U�2�m�Ƣ�9�,��Z��g�:��}k|�#�А0N��>>�G(`g�5C�C���qmf[17��P��^L�ȡ�����NHwk���7-me����O�X��m�Ӕ�8�]@�{����\l��$�ػ�i#�$%�w_�������4�����j�t�B a�N� �&�k�Y�C��)g���5 ��Ӄ��%'QK�X��2%Z�S��)V����	yM����f���!���^�����6�wD�Plj�����
�1F�7�w�M���c�4�ÍX��V�38Y1�um/���?�i̐�S�C�e�R0�]qt�_�h���+��(��G�?q��gҽA����8^v�!VҼ�Ԋ)��V�7�л��=ׂzm�0��ˈ~�),=�e�΁�z�z�zz$̪�bzck�� �SG��Vp��T��F>6#;w�'R }�Ǫ�@~ǔm�L8�$g��,��x�׸�Y=�^1_��gɕeKM�=�P�z�]%�&�Q*��� �O�B/�G�lVs˃53�V�J
��v����*~�m��g���µb ]�bP�+�hߵ�G�м���;2�n �C_�1)2qӐ�3p���a���|Oտ0�� ��?0��Zt|r���S�A�%W�Y}����
n`\���'��H ؍3��"�^��<6��;�O�̱wH��O "=������p� 
���F�=�k�l\��.Bmz���E0�a�V�Q�݈�H1�P�������݄9��'���=-�)$�O�����c�`��=8f��E�*���0<��+J�V:�iO ��
erWK$4�w?e!����l3��k�~�����X!�;A���M��8J�G�4G�6v���K�]?�..jb�����KP/���#-뽧?�'�'?���اeȃjD���Q\��^GI�J�$�ޗAf1���[�e=R��CQq�(��?�3%���u�5�M�}V4Z(֗�����O�����K��-J�h.�Y�Z��H�f4���0�(5��KexGY.Wd�f"~��ܯ3U�<g�g��b�����ŧ;<K�圲,���V�EFR�)W~4�X�SsR/i3�퇋��\�TwR�gX�Z�<��
��}����mM�O<���+�Z%@}��_��>; j̯�EKL��MpCd��w��l6Z�l�1�~���f�.��D0vC��gs?�!�|�'H`織�#���뼼���J��֥ �VFܬzb��0�����m��@N�CF8R8 ��6��S�`H�5�����[0�C<`�U�tr+t���������c��l>���~J�>��%X�X8U5's�{�Df�3�EԤ-]�8�" o��i5�mbX=����*~`���Ι�i�h�VE�o~6����D��w loLz��^���&��-��ب;p�F���Ϧa��<�l�p���)�jYo�q=-�+����ݿ	����W�e���ϋV��0%�Sd�Ty�Rg}��w�gB�=�|�T��d���oz�S��� 8��0	K.��8fa{�������	��V�%����?�x��u��!u���5Q��CG��PK3  c L��J    �,  �   d10 - Copy (26).zip�  AE	 t�U�uњ��Ϩ?�G_x����>MNH�/b���:B������X��d���:m,�*A��[Z����2��Wl�Z��0���[������2U����5�fœ��Lz�F�[p|�����O���Q�w+��g$8zŏO��v�t���so�5²C{r'�pԂ�g���jϗ��L� ?skCIF��⃩z]����,JL�����fG�%�g�Ub��K�܎~:;#y`�ǯ	"��1��ZVﶆ���̸�65#����Q^��nW�p�	y�I]�z�5������B	�`��~a�O�� ��'���T�I]d�&�:Tq�dּ9���K�ך۱�L�>r(C$��� e��J��V�7��#�w�m"卽�'g�L<e���Z~��Z,$��A��u<���a�/��s��9���Q<k��<��o������}F���3IEGV r�"�"w�1��"��y��H���ul@r����Tn	WS��J w֤E��E���vn�k0��^l,����hHdZ�t��oI
�R�g�P���獄�1���z�������A���s��c1����fl�t�prZ٨������.я�H�z��M{����*2��d҇M�1>3r�\�ɾl���V0�G�����Oq��*xT���9k����c�Y	e�uC:]/��?���[��x�+��E2'W�	��������)B{���߸\l�bt�}!H��h�w���j����sX�ޣ< -��p$#��z\�a�5aD���qD �D�2C&\xIi��;[A����pp�� ��䬯�W�ޏ�>�B�U��3))���g�
���I2��9�Z�4nev�[�h��f�����l-~�����Y�NrZ�O��IsPs�8��iџ�+1��*��B�k�Qo��`�����?ˍ=�n��8F�f��'��R�&^�h����݀���tB �t�h��T�R�|������1���k�0��v��{�E��ܴ`^��W�EY��9�N+�j��	׭�Oq��#��W��C��;��[�BW8`	-q���It�UG �/��dm�=0"�Ӈ����
�48�r�8�ASo2�0�3}ek��BS.�zK�e7	a����˙�%p�e��@�ݒ/�,�:�E�#�� ��������Y����זԎ���(v4�ǒ����Q��	js��r��SM�w���^�"E�>R�����W�B 
8V��,�o���6Rj���| ���j�T�����v�C�)�N���	D��|�;�+ċ0��{�6�I��[w�V�%�P�,�yŧi�Ŵ�gG�zb������]}:��� (��l��G�UrG��K���s�6ћ^J�1��7U�;�q�L��#��ϡ�ǵ�Vl��1�O]΄�sTgp)��"��׊�Y}�{\)��M)�LG��N���}ADX|�����O�~���-������*}I+�\ȱ�Yh�%T�2n�p+�q��:����a�p=�4m�wv��DY}D,���l����D���.ǒ�ï���7�^�҅S��p����Uo�-3��y8v��t9���E�R{TȻ^e,��LXrʣ1}��l���)��F2N��ǉ��z���p;�pݙTRZ�f�X�"1i�Z��BX�t��LE[���Eϣ�w�dD�D>��I�O!�h����>�;^rbհ��Y<��M�s�g'a������ե�=ꕶ}���'�|@m���M��ڂ�R�"�@ֱ�#�Jٵ+eo����N��ƹ�7��k®T�e����.�g$7{�Cw�p��^�S�����[U�ְ?����7(�︄.#w�)���p�m��\�W�Tz�>`�GdN��]}/��tYxt��"U̙R���<6�����0�,����q��?{�z��J��8�^���_f7o�a�t�ϑ�]]�B�4CvV����R�����n�&94dܤ��ه6!3/���Ww��ď�
���k�^�(�|�_%�r��O *�y9��U�Or�@G�`E�W��"*�V����&�Q�B��O;�B���*���&�P�WO :�ܪ��E�-T�pM��c�j�K�K,�T��-Fx�	 1*�bV�^�njK '��a�󙂪�K>�cj����{�:��Џ��J�9k�N�P��"Oe����(8���E��
ҩ�[�p����R�7je5��Y�p�6�ֽ$u|�!F��)� }�a�Wbr�eq,��H�]�>���!'�YF�^�l��
"�I�s��Z�\x$�X��AyD��c�8i�NT���!9�=�,Ѽ��yq� ����8���F�Q ���x'�G��3h���5��:�	���wV��S�O�&B7@�g�~a׶<��|���E�i -ܗ�ϿtY�E��9�P$:f��z�a�.���a������ �@�'$ M�IE��qz�{�є�Z��b2o6@��Qt\��%���=��Tz����z@3���oq+
`C�� �I����TL��zyv��*�)𔆯!�~��;<��j�����	��P�<����Py�1�?8�mΞ&q�~W�/w��h�q���R�݅+�A�������i=��/�1xА�⎫�aU�X$�Ve�h�<��,h�7@��w+�7ʏ �H6&UP�b�/����<k��G 
	痍c�c�-���f��W�}��x���T3F
T�r������2M�2K��j���PI��'s�<t�9��i��s�ı��-Z�=�u��޴^d]01���X)�������I�����$��.RY4_Ǻz��3��3�OV:�{�QW�3²1���}V�0�B:��Uqqw�2{;��FW!��d$�u�#�.DL?���S��C#T���&9��IZ��/�V��4TX��Xz�oI���Q��p\W�=My��t�}��!��IW�,��{�I����X�qz9�I������E!kR��>�yų���]b�*~OER�w,�=��'�9���Gةg��cz�ٴ/9��*�+���	H�B��f��U��JOD��y-L��*fV��譜�`���:��8脝��7ٯ����h�5#�N��{�g�S�Q�`$�v���f3�` �x�M� �٪���FoU^	|~�<�V)�á͋���w��f g�Y�jiqn޷[r�\b@{_fw v��	��>G� �&P>�0����۸ѫ�i�MH��׽U���Ʈ�e�Ƣ��� ��\���(^�q�ؖ�#���h7f�w���Og|.BՍ���NN3$�_jsRb��ðA���q�/�K�U�����K��H('�U �Ɨ�8ɪK�أ�{k�������$i?�G����LU�h�t؊ ������\�!�(�o�*йKL�j�=�ƨ�|gqE�J�{c�0p�ƀ�aeI���ǔ����M4���#n�N�G%�n�PX}�d���Dh�.���W(Z��U9��K���k��f߽5�@I�9�>Tc���f" `�^(�'�#���=��$�x#�5��0��!���C�^�-��	-�Y�CM������L`H5)wu��������x ����X��m}�Hd�+�4e���i��>��]��?���O�X�W�z6u�+x��1B��\ōIE�N¨�!D��T��b#�K�۪�Z�V��$P]L5���EG�΢��g��G%T�� ^;CJ��I����&�%��'��m�J1&�C�4��?��p0)`��!ѕ*+�)��4�$��0��ώD�׎ɝX��k���un,_�K������\��*[@�����G��3w(m���W�=7OR�xMf�6�R|y,�Q�ߗ`����ip����@�M�
�9���./���U�\�ڹ�|Q�w����ff�P�%^���#�'t�m�C�[��y�E!��Z�E���e��'?�%�[�΢��gE�
YS>�|nj�Cc�>5�j��?g����1#ur'dj������[^%����"n-й\SB۴A�����e�WT ɛS����y����jPING,��Ap���/T_�6Z�l����u�oK�&:��2��V�rRr�4��G�`ɯ�\u����ӣi���cCSNe�&������T�dN�6TQ�����P�E �����A^���H�ʈ5 ��G�UJ�q<�+G8&��64E���J�
�=6��;�}X�5�!:�_�u��3{Ϟ��GG�	�7��R��fD��7O�th�3i�w��̡
����O���~����%u��� $ІҝS`�a�1�E1��㌉OI��<6��8��;T���ƃ_T�$v4�?r��Rw$�u��7�%bޕ*�x�h#"�������׸ă#ӏ�!�@��Qg�}�u��݅����!�2�Y5dO�aq��鷉�G�,21�`�'u|��?�KN�&Wt�o��1B2����HLIW��S�H�v)�.DJ��ԓߌF���Y+(�K�[�I0fFΆ��o�m$�J�9�";z�Bn�5x�����!���隳9�Ys�>���=`�(^ˏ���B��[��g�J>��A	���
�t�~2��9�Ͱ3~�<[%�1yr���A\���zF�`Y���c��!���^�Z݌�U�C�y���g��͑P�II��6���:���53�W�����T���� �B������)f�����1�rQ~Gg+CӸ�iJW��^ ^�a�4�k���������=oZߚkܨ�
��n��s��[���	�$�<��	�m��m��y�L�3\�����rw��^d��1�!%K����K�o�����#�]Вj�g������z[�H���f�7ur17ti�#��	�M6�}��_:�����P;nLmilOot�2>m�r]�8���n�$؅k {B��`�����:�@_#�ʻ��������p<xʻ�j�*AA'}4���b�	��!00r�UE���y ��@\�R��.��/l����bM���W%r��ܠh�8Tz�,9C�z.RK�g6�#�I7��n�Nu1��6j�n�C���譿���[w .a4��⣙,� ;��K�OJȽ''���� 4��CY�Ŷ�S�����g
����(���Ǝ�6������9�H0_!�K�s��A�їTe�!��?Bw�I���g�:�V��v|0�|]��b� �.�SuPa�!L<Y��U
[�.Y0��+5p>��K����#9��D!��Q3r�j�5�5��)���h�c���/]x�t�<(6��o���32�-��N��AR�\PjpZ������'�[���l���i/c r]�n5���l] Р������U�0˸��}�}ɿ�VM��uVW���ݰ�՚��n���'��Vo�w�cρsl�'�L��Jo5+smݤIG��\��J����vD�;��?&�J���Zؐ���#�_�r���h>���� �XF�~|e0m.��|���[�SҼ��\������Ry��n�9��RuL��=M�yr{n�s�rE�냚��E����}8\a@��6��X�����/s��b�(L�x�!�BbWC�dY�~b��Ǯ���X��A%��`��U�1�R+@�ߤ�0WD$�0�wy]��%pR���@=�j�^<��w�S[J��R	ń0ixyVO?��%iF���YR�(��r�f�2���GV%�d��=�Ϋ���9D�4w{�wc��D��,c�����Q�w�m4ųE�Q�����`|Sb��kji�X5�V����j�4��?��7�A�{i0���Ì)�mi��\
.����"3ڠD�m1�`�������5hS+������BI�pQ�yիº._��	��Lm�R�(/Et�:���� �{���Ȥ�5T�X4��0m�����i���\k.W���cUD�m�d��\��9cZ��WN��u�݃,K>V�f���/e�	Rz����|<А}�D���¼�O��q�������K+�m�țY83V�W�Q%�$	u_esX�E��@h9<�爚�d�(7p5=5ZV�ֱNcs��{V��T���>�8��S�w�;�c���P�P��;ns8;L�d�Z�.w�;H��I�ޖ���6kR�OŠ�=�4w`{���.�ێ �s��Q�)��5VW_ʬR��N��_�[g��W�����5`�m��hʴ�a��������Fo@�Yͱ]Bw[S*�Qn{�!a�q/�U0yL���*?�}�.Y�0�.�}X�MX��3���8���O)�+#)�_�s��K2�㨑��f#��v ��1Ո�3���ot<J�7���4��W/���&��N5��
f��fA�㕻Cj>��f���3{鯪(6ڣ�vo+N`�`ԳL=I�-�:>ٔ�NP_�$����&��R�P���,��v�)*aW|�a6*H�֖��{L_�j���|LO�ňxd��FA��9q��ߧ�{�j�8U��q���+VT�a��B=�a� ����P�m'+��%Am۸�7e��~^I�>V�d'���q&p�)5���5$��y?��u=q��p\qf� OZTFJYZ��5���۫��2������}�¸�>��@(2��*�2���o�-9�^�q�/� �t���������HX2I]Y)��!$������ ��x��>�q�E��Њ�PT���qV�`�y��-ۑcB�Q���Q�;��1;q�|��ݾO�8���f𺱁/j��j_��o��5��0�dJrv���!�q�*�m)�!+b����4�3�Yc�44f7DXU�V�[�<r�J���b�yEˑb���S�\d��(�'��T��0$*�5d�C����ez�Rr9���(���8ø��z�9�Q�iUpͦw<qv�h^I��ޕ�h<�{�T�{M��r��RI
��/e��`G�hS����w��v��~�}�ؠ��8�an�f�N����/��mIO�Hu�{R�+������L�*�-WQ�@t�A#p���^"���{'诽�mib?���B@�a��y���M����*5�hȧ8<�r��P��؋$�A���9�Z���C6i>|�w6NߞB����e_�Ny�%rl�II��G����t�3z�C�Yٜ�|5�ξ;��tN�����jJ6����x��A9I�W^������r�O�Q�b�y�6�Y��a���఑S��D�uf��8�JpⰘ&$%�˸=���j?W\��{��Mo���"�R#*�X=��X}��u�^b%�+pV�I��	dx@*Mq1����	%k��(~�,P�m�G��ϱ<�!����G�4b�Q��2����[�W�l�NZ�������0���#1N��@���P�{]0��_۴&p�bY*�n_�B��J��fE���D���*S�b��v %D ���#���@���U����W��U�?�׳�ځ�b�5�V�,�CN�5><���~�z���ߙ�>�Sd���6ー�~j_���Q����'�砃�`��( {�tک���;����J��ϖ��,����@YX���։��~�j�Z,Pr�������F��!G^���H� �J���T�4k`�4,5e���1R��с�M�����m�J��TQuK�@�R޹y`��O�h��Sןɼ��
O�Y�U@�_(��Ki��If�"se�_���z�/s��,��5�At�+\w^���haI��Rk^d�,-Ǔ�xy�VJ�x;q{"P�w���&Fb��X����A]�qG�@&������4̝>(7���(gK�K�|����*VOc��o�R:�:�p�ra>�����B4�v�tʙ5����3��2���h o� �����9��#NaN���3�;��M$�F�W�윗)R�;�c��=���wW?��h�7��D ���2�w^�گ,����y���*�v��Ihܝq^)ೡB45��C]L����(EU�߼ݬpy���ߢ�ےs�GcH{l@�
 ����Ȏ�zO�yy��W����@7*�|�YƋj�؝~Lw0���<m$��B�����q�X�����E�^����z[��]Oa��Ru~���$�Cu!��H[{�TI�R
��t[�M�z��J�q��	�dd���σ'Mj�.�_�6M.�G�b�;��G@�n���9�BE?��l�E��=J���ݛ��iԙ��{-G7����^���@/������(5���L��ϯi�`/�k�a�L�Q;D�7<�P��`fHcf-����x8�8������4����i��%mZ�b�Ky�_�8a�[�����Z�iz�8�r�s5�3Гl�C�b+��j�[��s!�@K�h�b���V.��!��Z�g�kGr'q_�6n�OX67m-Fd�Χ;����Qfo�!8 �V��ň�����»�73ߋ����+���G���+
0�V��!��_>�fC�+Pǿ8�3n��I�Dl\���]+>�E��Byb8�'{�\�Y�J��L�S]C���X��+9_��W0|��$�^�@���e��m�í�u�bi�~4�L�K;i�Np�I���X����?�u5��Яe���eeV6!��k��(X,\CayLԌ�Q�"��]���B�bV�`:�7s"Y���.�(��}��4�V�"�\3�V���&ݍ1�jh@���=k�TfӠ;٢���?��꓂ȷW���l9�{O��XKNHI�1�Nt,3���|�I��z��o����T�O�ԇ����gl�L~�����7�[� nh��,9ٷ�i�Z�L(������ƒ��%��<MHR�U�6�Dp�IA����c4qJ.�R~y�@X��e�4��y�P�S���.�T�/��x��'h�B�.)Q��i:�����t��5)]��kBY��n�9��K>8:��q��Q��L�h
�EqR���{�K�L
3���OY��b.s��m�Q�R�:N��0�t�,��ۃX��LV8�kP��pP&h�Į�B��R$��>D	��׏�*P�8a��8�$yn��mQ<��������+�^/�h9[4+��ى���q��
V��?'5h�?��R�Pˣ
\���~��d$8���`�=ph����� !��bϻv
N��0\	��{�{�rR�b�P�����n6+�p�/�K&��->
��(�q�cˉj�n���вLUW6Aw�8 ��+�h�o�Ԧ:Oul��+W�#Q�72��O��	x�~TD
:�w=�ܣ�t\А��S&t��ss���SuB��M���R�l������!�i�'G�il�^+��r�d��H�2��xk�&ůXCz���Oݞ�Z����4"w��.!L@����:�z�^4��&ᕧ����Pj.Z`���1�i�r�v��DUkտ�k�W{�#("w�����ź��$I���%pD�.�!��ϭ���ۆ4�{�E[jqf!(��L�Uצ.�Kĩ�9�T�pam+�9r�U{z��H�>N�`�}{l:�3P��os�������:����Q5,���9�di���ގ�7�2j���P����b&�&�އL21��=�sH��tJ�X�1`^���sZ@A����ɢ��|I��8p�}���"E�{@=���O�l.Nu�A�ܓ�hR��.uԷ�E��l�*a��z�M��E�����h�9�ú{�7�L�9��djT�H6��~�L�Oܚ ������8�X4�=��i1�	��2|�FL'��Z\���i]W!����1���N�=]���̐.6ģ^��)��ٹh'DG�8`� W��%���X����(���YR����l9��S�ɷ�N$z�Ij�↕��!��bLL��ą�����_.���6�`��v+CG�,#���5���Hڹ�^��=벘N9!�11n�Z�T:��38�^?���E)�a}���v��H�z���&��ޡ�BW7��4<�M|�(�bL$��m��BQ��v���'d@_eDk�����7}­R�R�s2�EU-��������6=y�_�5'y
h���ʢca��$�����I���ؖ>�
�3�����`����{�&=ߙ$h�bEl�ܒӺ��7�u!wZ��DB�2N��.��<�b�G�^U�!���$����;���q��y�Ns�oR�� p�"����{b�y�a�*��X���컁FʐO� ��J5?D���gTKVt1�=�t� ��0�v['�!��c��>�t���d�v�s0� �A��\th��G�~����~k4*��Z}��`��V��-x	Xļ�G�s�	8��A ��Ɓ�\���
%;8��v9A�ؖ��u��[�,HLa;�T��J7��)5VE�ܚ���>B�?����3|�aDxV����C�f��H����}*Q�&U^x�\���L��נq6Ҍ������3U,�����*��d�dhS���-�������V% ���0TN�+����'�զk)�(F�a'�jM��F��*@S�k���
Q���T��YY�\4���W*Ah�R`-��k"N�|d�����[��������|�ka��c�`"�ֲ��EEr�ף�0���[+�E=������9�rti�c�H��h[?i��Y�4��mMv��ʩ+�鄸k&��5��9�Q��Y�f���vE��7���x��u��!l������}]�}��r�$3������I��.t;����o�g�B�='��n1�b9X|e�ٵU|�:V���c�T��#l�(Э�ˉ2��~������K:�S���ؼ}r� ��Y�������A�����ͥ
;�0��2�GL��)��v��t��H7��D��� ����޾s�:��V���
]8�սp���B�C+|,�67����k�"�)F�Y-���夊m��(��?���"Mp��g�c��F)&�?:$D+L�%�l�EA)�i0G=�I��S�����k���D,�= i��$#i|g�rMH��	(�g�x�]��m���kh���{/�B�ȇ�.��7�]'!��4��������^)���l=��҂b&���~�u��n&o����ޱ�����2A������2S���[���M�¬���w��y��)Q��ɢeUX�ْ#��I��.���?AO��ρY���Gyעǜ-xAX^Z��G�-ݐ�Gu|�xp��t�v|ptCU����'I����}�H�i�����ݬ-9�9Mw��͂��}�v��������cn4k���BUoJ��%��L@��τ��+�a��K�cMQ�׌�5�4��U0r�~�~T�,\:dd�>l=w����1wIF��H3��xg���7KsR����^2PK3  c L��J    �,  �   d10 - Copy (27).zip�  AE	 ������e�j+
{Z��f�i�8�*�)EK�cn���Nry�)�H�FQ�k��2
G[K�\E�\$²��n������~������	W+A�;��U|�N��[���E������B2���2��i�����-�ӯ�蔧��	G}W�Ί�q�H$�_<`��?��Q��; ��Z�D���a�'���ny�fa�NӀG>�ĳzu�Ӽ��w��h?���8Mt���:"Yʃ'0���)Pyl��WW����&n�������C)2ϞD��Ӕ��-T4���I�4���|�\�	6>c��آCj��R����01�i�L�����q������ZU�+瘰�L�l��d�����YL~D�PރI=:Q��������F3q,�.���0�n��_��N�k��"�H�b����[�`7���\|#m�ŕ{�����C�)�-�g�Ё��Q�{c�U���D�-:�|�)���M����Y/"�h_$�gT��[�����-�\V����ah�q��[0O
�����"�U�9��Ѓ�|&��2��}&.8�%��t�4M���MR�l�A�+U�� ��B�^�3X�!����,FA�P�0H
Q���q�<�w�F��(��,��c���]��&(���n�t�"�%bd�#�l�η�Tb�-W�׻B�&����:R � !yw:J��}Nf�o���������;�c�����خKS��Kȁ��K�v�]@?�avS�j�e)W��L�!4��j�K� I��ܲ �9�=�w9<��* tT�ʄ�+�����f������8<�qu�a/!$��P�u��VT$ć��r�O��d�ÃzH<��� Vx�r�G޹JG���rF�G���/���p/��Z���Z� ��cЖ:�Sc�Iڞ��?�Ymq���<x��5�54�x��-r�3_�_�*�6���߉�����(��8�Ќ@|S8ӹ�4 ��Z�O�Ӆ%�.�b��z9��ə�.۵�ȗk�rLզd1�����=�6k�u\W�����k70����́�`�����6:�w7&s�p<�5��}�ޕ�E(��? �5��8�D�hR�b�;i���m^⾑����-d�#SL	i`o-�٩ܿ,x�ZBzϊ_�dƿ6&AU9��4yC����]�݉ȧ
r�:^����'�N>&�k�Vu��1Tn��4�bX�)�J�߇�����^�w��9�v:ſ�ś�rl��aP48�nn�7D��1P
�0ߐ/ZWU򕢴�1%��@�����'k<Ai�,E�����_?)�6/�U�}D����8X�W��0��p�s8�@�� {g�Ūx���aP*�ol.`Q��9 ��z�u�9>��n��?vJ�UU��@u�C�qɮ4$2��:d�P�|H������Eŀ��3/�a�o�(�[H�c��yEv\��m	J~��.g��1����8&��krc������#t�*Sjp��L���=�o���g�K�?8�څ�U�0��U ɼ*&�ߛ��!�<W3�t�9��`����-1(g��<L���S����C�*����*����&[|�x�A�o(5�7����?�����'RV:��2����H�܎�c�����_��롏T�^s���,o'2:�(�P���(�$̪";1q|�E��p0�T�P�W.@�FX\L�V��\# i�����z�|.N���!�ta-��^����ҏ�*�
/P5<��"���1�g��q��pHr�chg�A�!6����'�5��QG�S��.��*�g���%�,;�_g�һ�.�w�oS7ֲb�Tgm)L��(S��T�Ţ6��e�����@�s):�J;ʡߝ��[$�+Ċ�M���P ����]m�H�p�Tbs���&e�0�x�_�4�i�Ltw>��T�� oZ�R��ʋY��%rǣ3F��:�au�Cx6�Um�B=�hÆ2�t��*R���J_�N`�~���ۀ:�>���Eh�T7ȯ�8����~���1�L�d�]<��(i>�Bbʇ$݄�� �S��h)?�|�U�)N�z��ː=�/��_Y�;�w�8���?��?�PY�f;�.������7�i�57	%\f�@8Xw�SjAT0=�&���Pw��	V� ��h�$Qnȸ9Y�ñD[r�D������Z��Q���$MJ�z ]d��A��<�Mwa�B�T[GFSg7��>��0��M�648������݂�^�.܂��y��$�s��In�d�U��Mz��xU+l��J�d��!���7�mE��9?F�f��3������]I�S��ml۾^;��/�O��V۞ڸƻfO�|?rH0��oH�J�q���`����,p�A���#�R}�A�������6ޞ%�΍��.�'�<'����x9�]qG��w������(�a�Y'�~�򧒟���0	�Gs�g��z���\Xr-O0�|��V�2���і��������*?�\�2�0	�R;@��B#3��`��PIvv^����zt��_	 p�m(�5��h��s� �����kqʷo�P��+GÉlA��M�0�	�?�a-1�����9�X��LZ�`����C��Y�,�����:!U>�N����I�,Ě���^�>�p6@-ӄ��-t��v&� �x�d�����b?�ٵ5M:�:�l�(Ս�Wl�H:���1o�`�.�� ��T+��X_&r����c;��P�����43W���/���Ŏ<�Hd��?jw�a��|Ԓ(�&���L57`C���uRa��f�������y��G��4����%;?�,m����)E���sr�v�u]�ƕFA��FK��i'��sb:��E��I�G\&�_����'�*�@��5��A��$���504���rA�J�ܢ{()�-f�n~����a|n�Ձ��G��oq3���B�d��Bg�x�Ƒ�<]�^h�)�!+Rt�Kӓg�r[JW"P����[]����̤�){70���j]}��\,Z�8���v-���e�5��˘8�W]���g���X<^F��d_�� ���+n�z�;r�Ӓ���Ih��*b�l�M�F�*����t�^���Pj0�A�{���/B�DI�J�Y����0*֪���� W��?K�u[�rq�1����Z���Z�C�Z��AQ� ��c��.ޝ�����`�t�!Q=@x&%���~�� ������(&�ExN��|��2mb%
������&�Tu����JDw�v�{>pef)b�°o���5@���aЂ����5���5�.Y�:��^�{�K�i\tI>]�e�	�H���⊾�yM[���EJ.�аfv�D,W��U
>�Q��_.�;w�VգW��:K�ES���ٕf������@>��~o��D���vD�����6d��!2.��e���=����|��'�(�L�mX��I��% V )ɚ$9����{���$*���zs�ib�E�yy���
��wp5���� ���(���:�${��]+O�sH�kk�ϛc���BL��bxx���h����ǸU��)Lo�	���ۺP���C�1����P���z�P�# ��e龜-4A6K��B�X����@��B��Qzofl9�7l�_�:k!�;�Fn}*]�{w�^k��lћ*� �ܣ��]�h��}�Q)��^�z�Ī4��M �w������9q� ��� �}���u�ڪ��;U��-_A[���V��1��q��\�g=��7ѷN��O��d��$�����`v^�t|"3Kօ+
`�����M�/�V*T�g��ih�~�;�븪�0�s-T�"���Κ�(�Μ'#h,�c�O�B�Y:�֤��lL8�ݑ�썬��u7A'��n� ��j�$"U�WF�]<Ln\�zT3Ev����?ok�w���"��B��xVi�3��Z67�ާJX���=�S}��ݺ�U�[�3�t�4��m������%��%օ��&���vv~Id ���1P`�|��D@.��h#|��Ql��oo�$�mo�?O����~���H�!B]��E2g��;�^�����+�!���E�:m}2�<��w4�X7�V�!����@�x�o� 0_Xe*G�aBi�V"�@Λ��P%�h��+( ��y���#+?��"1i�|��a�·�+�1����7mUsLJ�e{ȧkk7��C���΍�x�G2f%r�9��KZ���L�A��iG���,�9z:MJ�P��9:�Psnl��9K�f��$���牰�Hl�`�Kk�#�3�{?m%�E���;�;p�{�cZ{?��Y�񖍬��l����`�o��*�`��Čm,��"C'q�d�w&��·3/i	a���as���E��s<�9�mi �C�� ��/3=����)�de��x)�q'�
������џ$�ujq���ȃ��}KXg�hY��U��s��B���)o�w����IYY]`�g0ܽ���70��U� $���dc�Gٜ��xT��O*�gG��?&Ow�^!�����k�d}�����ީ6@=l۶"e��a�*�E`'�_Ozĝ�b%�{�:a�t�~��f:�4��#$ �1�i@u�+-�:~ҹ$�hLF�z=%��%���ۄ�?�`�����V��8����Ii�Ǹ��/�}�1��Z�ᐂ�O���F����QJO?�� .2�!j�HA�`���2��X˞a?���4��ZYn�f[�ܨ�o�$�ǣ�b{�������g5�×�Fҙyr�'��t]]�ep8a�j�>�5��Np�UY����_<#��k�ͪ����F4جv�� 4�+�8�=6���|d��E+7B�b��V�y�d��M��l`�t-�!���������0x�_�z����}�=�1���^ޢ�8��L$�&�~�������PHg��t���Z=��.+�t��͆��B��q	�	0<��I�� �?�AQ�y�,��8�n������uHjm���Ǎ$�zwb�u�XE�y�G9#�-}��학+d��}��>�~"��:��'��/e%��13�ٸ�U������[�d"'�s�kv ��+۔9�;��支��3q	_�i ���f���)U��0��ש��6ĴcČ8��9\��%�١�0e�[Pr�!�Iq��m��Fݼ��=�v�+"� ���π�'7<g�G��?_[Ʀd�n�G�G���I�g�S�#H��1�"C�pLE�̓��܂�� ��K幀��˧�]�<�qJ��bD5�9�gbd��.|Z��R�W�8�]Dx!Z����� ݗ�<��"���&�ɜ^n�Wi�#�Ql���Ka���˯��8��R�"�ޞ���f�A֣�V˯��O��,���p;q48(�~K�%|����$'5���h�^�"XZ����ʵО�S�>���� ��k�ڒ�q?7����&�9��$!��`9���n%,74���4�v�S|«p�K�IC��r�{�f��K�8$%}�p������DN�������F@�-�b�>�Fߐ������j�ky��]H�����`�U�������4hA�	_X+$�m3����.k�@�au�`�������V>�"T������j�=	�����w�y�䥈����Ɓ1T�-k�f�k�����$��n<T���#�m�-<2� ��+��s��T\��C%�,lР,���ek������}#�!��Ӳ]�GV�f[����}A߭�2`�	�rw�3m�[�	�� �F-�M����ч&?�_7u�Ifb���+H+�(���Y;q��P��S�Vw��*�S�n_o�~��j��2�u:��q�j�$�0r����j�`�/lkT�Şp�(�}�Inٺ��cF
����h�2l5��=Qh��DSyy�����]M��.4%E��3��֔w��?�4JAG�Uj�J�����#:�660��3p���v�d�~2I~bJR��C1�9*�Z�"S4�����s&m�&`:��܇|�_�em����8o�*��hmd�N�L6��D�8(�ѠT#�؊�t�N�٦���v`Xm5F���˽*���F�ӣ@$6�T����5o�i��b�B��q^n5v���y�݉���s|wo�88@ľ?�z�#��'���ᐏ2��ICbO9��$%*���\i�#�Y��B�7��@��@Ç2��MI��}Sk�� <<��0��I���G�6:�?S	<��u�a�b�+0EP(p�r'�T���ax�Ʒ�� �	��B�%OF C�	L*d_d���*���ET�;xZ��s�A��Z�x)n���t������mj�*�53����$�	6}����U��"ңx���6���-�����[�V~X:T�c'`[�==j�"�ç����_�z�� ᖂ�H���B�VZ3\��9��~���쏤E�PM�oYi� +�����p��I�A=��TP�@�x�~��N�1�@	m �
���E�k��↴�RB��G��#��`���Pf51,���kU	�R���S�����v��0�l�I"�ƺ7�V��-1�S�a��I��I)ҵɛWOɪ���1C�i��6��A�p���pS����A{�����^����N���\[=�I���@�؏�����#�;�2�Lk���x\�g��y��NT_� d�V��.n��QY�r@-~R���XaΏ$��>N��0E�P5�f���d�De����fZ���c5mԙ������tH׸tĲ�x��<ݻ��odl�Sn"斄��dt�����ٚ0{iE������Q׷�ް#�ʝ̹V0,���
�y��dE~�U����bg�4�����Y�ܷ�E����9
�y�I�g~Z�2*��BzX��/�0�O��%V*wo܀#	�OV��Z���o#c��tFi�S�=���l�_��G�ɖa��!2��5b .vw�4�4�a	�9^šj�;�� G�*�$2�s��S|�ҹ~�6�?����jY����j颀�#'[����-+���賥��qт$Go}�RK�)��,��i$���J�Z�=�7r�]H���S�����ɨm�K*�Kǽ)EE��oA��@:ߥ��86�9������~Õc���;<����,M�9��^��d�c׻�Q�P���w��<��qAI9��	(�k���O8��v�'���.�{�BT�0 禃V�\[�������}�C�~J������l\LUgd-�'�#�s�Ԝ �2���{�$�
u�G�dj���������٣��c�n���S��Ԋa�GJ~��i�?��v�NDчs}�b��}�a�}7L�����f����2����4l:�)#o�K�1=�p�����oi5��hȩJ&�Aߐ�2��@��%-�»^X�$z
��������j R0z���3���̥$kE�[�?%Jc��a�ϛ~y���_7�}�YgDp���r)���g��y��6s/4�
��R��A<���H�j��	x��7��)�J�+|���K���9?���.�&�)k�R��+��]XІ���IJ��,��N_��6u����3\MB�@�(�����Uz�w�ENȻ�5	s��rm|\�6E��ʳ�MtV�®�m�]^����� d�'~h��T�vb��Ki�l�T��y�㪐2������Ե�t�~K4T��D8fk��KU vg�]!U����jȸܮ�[�jٝ,8y�ѹ=��Ƈ$�n���h�Nn]ȡ���RpՊ�}��U-5	J�h`,KCG�	+p�����I؛�;|�?�}���ޫ6hآ��P����ձ���q�~ٜ��w8Jܵ5h^�ɘ�C�9�jB���%Ї�c�q��|���w���A�nuV��`�J��-�H��÷���z��pNu9�2�fU ��m\2 ��%��%UC��{��NB�*�ȬW����q�U��������A��`R��`7�^��b5�0��������mZ窕W���.x�pD7����?��恈ىK��Q�tܴ���Mi��ԥm���u�p�Z��H4��UA�u(����-{c�D��3@&<�.�(�ޕ@*��O���#��&aJ�L�x��(Ƚ���\[�`<GE�����y5L|ꡐ���ݱ��*D��P�a;��xc��n���_��Q���;�o+�h�W�g����b����b9Z1 K�AG�yZ%y��QY	���}�{��E;K>"r��!
���x��V�G�8mkk���p���8�#R��2;y��n*�L(�5Ot�U�w�H��"�7�_jo�j��n�<�þ(��6�� �[�n�q�y *n3�mh���©�G�=�Y'�\��F?��y�Bj:�|�S6�S��4x��8vVr5���߁��鮻ece��o����3��Rw�N�8sK�����)GT�Z��>�i��<qc��o�H����O`��d��t�	�c��@���$�U�J�6�A6o8(�لi�˜�S�@w�7�����zY�C~Z�Tw<H-y��;�~&Ī,�[ގ>�iG~����R�&2à3m�ݿ�t��=f�Z��F�=�M\�qPҀ�@ʝ9Dj��	��; <�M]�T֐,���¢b<!��/ȶ�ƦX� A" �II���`��+��"BdS3��/�U�ߖ�,��(};��q�m��;��]jߠ��7ɒ�Gr��^㦉@FjjY���#:�-�sFYg��B�d�J���q��M��"�=*������WW��]eW���E�CUƑ���퓬%W�-'���R�Ϟɣ�
l"�!���3�����1�	�Y-y+���CdJ�e��]'�4w���*���0������r����g9����D��(ذw42��۽%_��}�p�b�/��2҆-�p���R��	�b�do�.c@��N�0�0�ы:��m�q�ʈQƣ�����Ӟ�"�n��
z�j��"��h�-��k&�R�Q�-WH���%���(�����[�p��$ƿ�79	�z����&i�P$u�U:���{*/l�u:7���K�$,���[Ppt�齣J��Pn��ыe� �����5x����E҅��$T`�瀖�����<n�rصR�(]g��w��1�*c���ط�[�)��J�'�8�cċ.��'���2��9��0Iqї<پ���܃y�꼪aH�)% ��N�_/�($Ȇ�m8�|�RT��$���w.�j�J�&&�9s�@����ru<r�T���+��3�`��I�n@�lv�qg���}m��'�]Ȗ�̄�*J��D6z:LP*f.M�?�����'0.͞,|�IU���P�$��[��h�]V1I�Ȩ	�O$'������W�*�-��9�2�0;�����gPj�B�S����fEiwD�pZ`$c�2nr鵳�� Eٓ�̔C�~[L��H��ʥ�.-��`d)�ӬS�k�4���[�eϡ�r}s�
��2�9P٩�@(��g� ����<�� ��»
w.�6 �ε֯����B���(�����UZ�-X�d6�-�N�º���`Z��p30.�dE�K��_��Q>�I�tHU:�Q����]���WP,Q�o��������:n��� Ȳ$��5Z:�Fˑo�.���K���n@#��x��xAR��1�ϋ��!����,������a:��/�d3�kg���sN��{?��/�+E�By����<6N����i=����x~��Før��m_>"ކ��k�A�L(ۥ���mo1Uk�w�`P��T�I�<��
���U!J��"映)�� \S!��_&W:��.j�/��ޢ`�z�Y��Gs�W�q������uE��!��b���!X8��1�Ӡ�y��9���V�'u ق&ג&�{�4��ឫ��������|��1%���B��a�� j��&���.ރ�ޱu��Uc���.���&Ѡ3}�3�����V�����8��I>�`4��>�Y��
wr�l��/Mx��%��?�A�}�O�tx�W�28��1����+�&Ϳ১^\ݾ��4�2;�1��ow}��
޺^��;��)�h�K�����	��V.���X��d--_�ѷ��&��0_ n�T�-��ߝ;�}	=���(�	���Hj�, ��;C�밶"�zL숈<D���'�"�	R���k)(I�z��
�C��a��e ��X�O�'���ya�~���4��*�a�C(���B���k�<7��b���'�bŷ2�>��/�/���]>��9�2���!�tB렘qD s�����+-��L�����hpx������Î�^R|���)΁�)@�B/��`��I�<y��������a4jˣ e�Sa4^��������3��ױ���S7����Y4k��� ƍ:����������n�Y�)�g�E���=��H����e��SǕ0��K�<�"oGɾJ{JЙ��*F:z�s��6`���n�6��ԁѸpw�mX�,7I҉;RR�tn��D���J�&r,ԏk����"��r�F`�����Zs���4����2��	�ǻ ]\��m����I����pB96*G�����##t�ht{�ei�:+������`��976:#��>�\Ϗ0��|�i��VdO�k�j<P�e3ͷS�Y�'����N�as�[�b��Z���J\<�Mv�>��ς���dK1�P+v�mg�]� ���{Nŵ�a_A�����u{3N7�<�G(���e���J�F��y%�1�p�J����N`RT�F������g��\��RH�zI~��R�v�u@]yF��tjo-�v�l�������t�3��0��V�57J�Kwp���ӌ�4`x �k��F(F�Zs�F��f�"���Z*�Y��4 �F��R@�s��Z�x��]Bb�R�����QfC�%�n��0>)Xs
:�B��&
hy4n#n�R����`���eG��
?��f�.��c����s��j��k+�%�rB#������-�9�T�a��D=t��kP,�9����L՟���=��
��+�Dc�詾_e��݉sq�&�)3O�K���V�;-��S�5)f���pC^ Qv&<�Y^�DL	Aj^��YK����z�;׽��ܗhʉV��a<�vА�cԑ���wW�K���J���J��T�a	�B�4= ��r�O�_���4�TMW�|���nU��VM{9��,(�|m��ju�⋻���yɨ��Ԝ.ڂ�̇0֜^m'��j�I�6�|#ؑ���v���PK3  c L��J    �,  �   d10 - Copy (28).zip�  AE	  ���GƏ6����/�G�t�EH7w-�'�|76�[��?9����ݍ�?�2I2����r��6�
�� t��5pۜ9�'d7$�����TI�����b`t��g �c�"%B��?#\���iT�����5' �-��: �,�h����S��*��U"Nb7���.�5�{�1���t��Y���{�;�u��tstI��3S��?�|3K�e���2�ZL"A��IB-�;E�U���=�k��n�"|�5M��Ն�Èk ��(��6ڂ�Zϴ�kJ���m�X�#(��+q7��c�ZA�+QO��ځaClu�+ L ��q��ߓ:��:�S/�忷L��q��a>m-XߏI�XE��\^^A�nꑡbnz�y3��&�Y�a8��Y��7��S�wJ{:s_w��2H�0�N�{Ӎ�3&��`^����Z����>g(!&�d���L˩g�L��=�mp�4�I*��|։�,�Fu%j�c(�*��`	`ǂ�V��q�ef�o��p�y�Cpo��W2��Z\"Y�kSi�0�q�S�q$����~`g��ʢ���W�Ap�d^�� f����ܽ=�C��T��d,�J�;��LH���I[��Ƥ��ޔ�$��47���G��0�4�M��D��K&�}�!&(�*Dh��$�"ћ.#g��9Au:� G�z�]�C
nnU#��+G4t��1+]CZ����`*�i�t�q@��I��I�Q��?ۋ�\���k��C���j�R� G�T��s
�o��8�4�oÝV���)��Ƒ�d���k�%e1%y�$�AX.�[h����d�4n3%2�ѽ��r�/���tPb>t�78鶅f���`9�Z�����C7E=x�ش����.�,�H���@������Dӧ��@���v��"���y3S7��@9�������yrH�ܕͰC�d�R��B`c�4T�����v~�b��Q>ֽGz�n�Sތ��) &��l�q6�ot<��[�;f9v|Z��S���[�����f�ӹxу�II��5�i�ZR���/qFd���m�,�w�o�-t�i���1�mso\9Ѿ
�ϝ�"�~c:�%��W4l�>l��yÒU���i	����V3�\ZAʛ�b�r�a!���<�DC�;��S��a+���Z�d�Da�y0U(ɽ^�<b�QdK"�2��i�'��K�Q�c�/b�P��m=��w�v��ؚ��`�Q��{�\�����R]�:�_������,3dE�G*$��^�]��I~�g�S���P���{( �Yd�z8'z��F&��jCМh�]�ؖz����a%e��a��x�y���]jO�᳞!���&8�<���}�@	w�a�ږ���-y��nf��g��,6+�����G��vN2��Ia�Ͳ�~7@o��ӰMc"���1�׊��b�X��Ѥ���E�
挋��ؖ�ʆ���?��}��F�z���N�����L�hkyVh���HN���N2k�['��rq4�b�������2���Q�����{�q����tn$p"�$ޫ$���I
y ]_�A܈��o9&�4��'��i72{����L�`�oZ�~�M3kv����[�V�Z�7�Xg1| ��kq���)X�t3��0.F�P_]�P��}TR�sv2�J�/�fm���;p�YA#C��	��D�7Z ^'��!��jy��_�����S��F�'��ǼGjB�����/������a�9[X_���\}��EJ�%jeۑQp1��`�����]��.���������,w�:Ź<��AM���aZ�M�T��[���Wh=�5��eԬo9���O�������r'?s/IZ��싐p1��>7�8d�,�(�?2����e�����9���2���6�)�,{��7�� �@B�;M1�[#E�@�	[���K�f<�ÁŖ{���,$aʈ2+y5^F+��=M&yUE�ndf���G5�g����Y��#���J����?�ƽ�g��;3���� ~�wS=�t[��⧃��a�VT+pH =��ׄ�,2�N��M��c:M�jC#g�Vd�Fǭ7qfPZe{��П��?�4�me� �A�7j3W�SJ�����c�MR�����dL�wK*��㹉�i���������TA�a@�"cX�g����K���KT��!y�-Q/
�P�e��Z�ZŅ��}Q�^�j�уB��ql�{yTva�{ә�0iL�3��BU��Np������8��A�I~.\�i�3{Idֲ���qf1���h�t�*2�PG��1���ߩ⑌o�o���s�[j��v\E�#���O��Ў��kz�+����J�.�H/P<4���Ƿ)ʴa�.
�.ś��,��2W���0$�$/�
�t����,�Ϛ
�
K���#"J�^/�O7��%b�ڑ��� k�2�����?!��b;�W\o�>��7�!4	@�;4�~C2��)�1��j{��♝���}�UɓԼ���Iu}��\GK$	Y���1؍te�$pf�r�-��X�G��Rc;8�>i���S��{a\\&nM15�8�:71�ԓ͠2��AXP@[�HBq����0�4��04*?�Фvbœ���v�\�����B��&wx�p��9��S�hf\�r`�i�{���v1��zK����j\Z��H7x8͏^-~ӽ6/ٚA_����!�W�4���=��I�W�Ć���0��~3�T"�����s�"j�D0�����L�]@`��u�*i��Y����~�f/������6�T�	�-�5�2���gf�]Y��7�Ղ��/���:�wą8�#�ӆ^�NA[�Z���v�D@��8�ƞ�Uh���e��<N�I#9��~x"|��PX��<�,a�Vl�����X��?��Z׏v��y6��i��co<�n�����xޙ�E[��@���w)��ܛ�.�=���qe7l�ҋ�z�����\CU�c�xic�G:W��6�v����䦍g�:*��,����*��+�o��A�f��IZa]���mx��J�H]����y�N�����v��I�,�M϶���;�dL����5�Ɲ�%� 5��h�c1:�m�h�kr�.�#b2=�4d/�m���Y�'>:���L2��\�}b�����V9|���k<%5L�O��2��u `O"�����n��Tyy��W�{>:"U��qe�X�"�$���Z6�~v�<�t�|�`���^e-So�_���9Ü��z��	\c*�����`��1��`}u� &7c���플�q�o����4�z���@����Kea����e�T�,5�Z?�<��W����s^i ��5!|�
n� IV$��f"���0M��b&�Ua�m�B�!�W��R�=�T�uxvV	.P���~�X� �3�`b��a�䞬��3 �)���_�Vt�SN���V ��F�f��bt���O���@�/!�m;��K�5D�U�.n�����}%���S.Uv�Ct�s-zLe{�\��v�H�6QP�Y�H0;�3	0l-��gGF���F�z��7Z0DURW��l�E�s�.FQ[�@��SQ((�a�b��Ү�$�`�z͈�<T� Vt^��T0�w�P�:�E�[�L9M�,B�L�)�VY�"���*�g�*(��p��\�Y�F�7�\�A�!5�I���@ $<UT	ww^��,J��q�I%{��41?S�$�驣����Ѩ�Ȳ '��@:λ��:�/���dw`b�}�v�K�~B�b�{l�����?�ѥ��渎��+{o�dٕ>qk#O(ߝ�3q_���TC������CdQdc�����y�a���}̤�aW�'n1����Lg����8���4v56�2/K��,y\c�����u���R��|��N=���	6�ev����+ˤhǿ�C���a`M��'<6`��xb���l@�u��繝�N�����+�tk�z��*xg
v,cC���c E��<��7>Gb�uJ��7��m�v�:��k�#��_#n[��K�_Ǉ#]8��,"!	����]c�H�	9��(gL��cͲ�Ϯ�
X����v�ܙ�(L?�^�)��;�	��{��p�<Y]DFe ��Q�@[�z�^��!f^�Ax;.������A)��=��G��ki� �n���$�='�(��������z�?.�􉽽�s�J^���&�A7<)(1r�i�͸�h��Ŷ�@�r@�T���
�hjw�P�K�.ٛg��V�V�ەFK�_�W]l`!��I�j,F������_�V��V�vSĶ�4�ٰ�P	tc�@\>M�jQq{�1�I;���h�X��X���#���KΚŅ�n��8{k�i��6���4�^%u�R�f�Qm�+|EX`�>~�î܄��O�X�0���.z`�5��^֗ !#���E}��s;�[��ˇf���"����v4},����*K�2y��v����c%�u-o?bk��­f�F�F�V��xeY4�~h>�'����n�~�?m0�!&���F9��f�5�eUɌ�$t�y?���&;JU{t����3j2��4���ͽ��F��~���uy9>��ѻp��SW#�fE���k�}мf���a�/=�L�?�g)M�p��,��n�k�d�����&���S�{�_�jC�r���UT��%g\��
���:�݂�5�J�Ȕ��V�`�\U���K�5�AH*e&$GJ��;p�m(���1����a�L��w����W�{�U���V��Uyţ<gV��슌cX	99J���Y���yk�; WB�K�:��:�K�i�ŋ�ʍ�:����LP�\��RrW=S��2k��8� ҏ3��������[����l�C�L���y7� �l�Q�G��9U��U^���x��yY˜�������x�Ƃ�Z*���C�P!$�]n�$lhh��<�����c=wD�4�U�8=F�*�[[�h>�f{�C����u�F�r��~�bNpܰCVg�ܵ�e"Tf�Z'�Q�����t4��g��B*���rpd5"�qȩ8u�F�$�o�)�����_~���_�R;~,���ƅ���)@,��6��o�iR�`�	쁎�L��o|�{�:^�J�q�?�I
���-�Hy���G�t^j���Ϣ*��	y��V�B�FǢ`�I�c�=uc��?5m~��~����S1��ҭ��foEO+g��Kz�3�?{]0���_�� WT�;�]��Xqqo2Qc�\}ϡ���5�0�Q�Wu�T������aIz�0�|pFt�Dy��:�
���,�y�Tq��"�w>�:=�!`ɒ�Y��t_�'�C1_S<����W^��&H[0���uM�=�S��tQ-�2�wM�8��~�[�n6�Gg+ EZ�)h�#��^5 �� ,c�.���5 ��®�@��%���!�B��X�LA�y�d�!T�	���LXNZ�Mi���	z�z�����"o��P���Ә�S	q'��E6j�������<PW3 �d`�nYO,/פ�� f�E���x4��ţ�D�c}������)��6�ߋ���[�i�������kڸ�U�� ����a�~ǡ��$:�4,+���dw���A[1SÕ��n�&!Y�a]������0�����W��=����w�xx��ո���Q��|İ�����j�!ӫ��\�Ĕ��{R�Q-I��g���lw�Vզ=�A^��/�w�&s�AU9�@�Qr-�y��k���&f#R���Q��$έe�# z^R��~�����Q�3D�T��^SL^*���j��ڢ��2��7���=�9Ną90��7i$]Y���I�t�噴.���9���U��H��(�$�@��͚�cy�	�m���(g�1�8	���6�̢h]``U����+:�YǄ�����ɧ����W+h�� ����u}?�\���(��ϒ_��lOs[?T��Z8
%�%~0�Ag���~m��=�Od�*�*Z󱒢znN������	���ϓ���, �d�)�~KGO~���2I��(��0-I��pT�>J�=���B2���o���&��}�>Tdd��#�+[�t�Y�1�����AK���e��H�  �������QWQ�B�=E�( +�g�E��/�^�}���i��r(�p�<l���YHF�F�W��O�n>�~�)d]��o�|SaL�qʞU��4$b~��)A�	�Mg��6+�+�:��*����W�S��{E�.�*@lx��c1�g�C��;l�� �D?��s�k�6&�i�yd�0v��VB�}Gj|u`|Mؿ
�^bC�Z��&�!��>d�����rRh��m��~p��e��?3{��I-��;q��a��3@#��B �=�*����(u0����2�|��b�n���U߻��%O�ZH\%��w���!�$�:e{`���)U}��`�����{��c��]� ?7x�¿.�)��j�� /��O��ç�Zy�\/� ����y9�{�AC�q��'Z>��{d7>�)��#�6�����g�uAlut��4�h����)����˥����`��횛� ��au2�_�F��5�r#��Oj��9!�Vr�^$�kɆ+o�9��K��ȳ���E	��,Y�)W�z���R_f�ݥ���L��I�	�kw��L|uT�m����+z���O2���}Z�Y���&7�|�r��� ��3��T�]���45�ټ��[��Yy�(U	��`H��-�g<��cN3��6{s�E�ob��{���X���UEݕ\��N*�(��ίz�N�6;\�V���T3������`���`��!{����N{˂����<�I:m�(��dlm����.�����h�Sy���_ҟ�f]�iL�����F��]�+����PF�Ug;�Er�}�e��x�i�kpq�\����A(��p⦍����_� �<(���2�4F]�-��o�]>
�T*Qy-��ʨi d�H�qUd����u� �8����0���
q��mb���jw�3Ϲ��Ls}@���IS�rp
!�s,G5:��V�Nx������hy����u^�6ޯ�Bg�Ջ2�>��wGa_�U�G����8�G�ɬ-�	���e�T�y��&I Y�v��3ڑ!A(�)@�$S�F_��"��F�<��W�s��I�P +]sZ-�o�2��|��kO<�(���c0�gr�Ͳ,2#D�=ւʷ[�̃�6`���TY-v�� �z��2.���f��\�&�~�	��KW��Kv�45�B�ؗ0���/���� ���/�l�J��+�ɋ޳A�+D���G�ˇ����)"��T���P~/Խ���\�����/T�9嗤V���(d;\���D������**�A&o�森u��Χx�s��eN:V�
���q�A����7���X8�v;�槂pε#���J�dODO���J��e�A���]E]z���Y3��~��n�<�Ldc��K��EC-��KM�����#�)G ��{M�pǾ��'O8u�����_tH�5z��e��\.Q�Cl�`�`G�Q쒂X3q�-x�$*�K�ی�`�\��+���`�z�Q�8�WK�;m�.3�3�'���8�j:Nr��pN����
��Ѕ���֯�UJ�x��3p�Uc0�����h��l�Rڇ(ݞc��Y�����%�9"���Z���o�b�T� ��x֧�<7Ι�b�Źމw�7h~�߄~�\���UM���0��1��
�1W=\���M\����*������c�<3V,�{!��-NmkY�tV�Vz������RI�q��N�� j(�dmյ8�!�L�����sT�B���%����l�L���|�NkO֕�l�̀'�)&}&����<�BQ'�`���)E�#���o$Y�Oj���#����KRr�-Va��`M��ȫ�gh�_Ƶ�H��(W뎚�@qg�7��=�F �s�Ӊa�Gϯ.��r��������h���δ�hAC��#Ő��궽���g����!Mbp��a+��C$KXk/4}����R@H���I�oϻ&]<��Q�-�xF�+�A=�k�6{j	�(�8
�`fb�n�OJ��Ϭ�v����T|J
'�^����z����n�h)��� �D#�S��_4Bu÷Y�\�`r~� ��\j��PF�������̆����� !m�3tQ8ƓI����\ΰ�E��*`�|�А��Fi}lΧ��F������i�(ʨ�	��;�:�-��}z�����.Ww.��!�$#�4���������oY:�^�8f����E^��F� !��e��x:vLO�9�y@�d�����"M�G�0i�\SZ����	^��-�3�&��y64�/l��Տ%\����v�;�z��-(޻8��d9f�%ˎ���D���-o8\�,�2}N���=���ߚ�2���/�P�Ȏ��3>�/L��%P�d(f�%�R���v>S�e���CQ��R.O��n>zi̓7��C�K:)$�1�h���eЊ���'3P��mڄ�Y��B�j��U��YRv�A ���-��d�2K�gS($�%4�3Z�D��'AOr1?s�Hj�î���%Qas+F�[Mi�6E�~}~m���m�����/�dV>5d��C���ak:�@�X�|$QN�;Dv,X�L�o�Q,����V�G��!�ĊвƝ��I8���=ف���A�ZeI2`Eu�~�,:�n^�0p��5�e~��c����� ����J�Rō�Wqb����#��;��k} ��Ҡ�SW�z����cO�ِ�$0HZ��c��k���1�Q�A�N��:�i�b -��sB��M��O�� �k�ʹ)�CJ�Rү�ݥ��0�S�0"&�P�@�Z�j�ѪQQ��|����z�/+E8jx�߈3���>�y���LNծ��!���N`�y����9yX�*Y�X��R��S�u?�6��bBK�)����`#AC���(=0�f����= r��g{�XŔ��@-��z�Po��l�x�*Q�j���?����0�e�nꚲ[
�/�hhhKp�/8�Mk��|^�e�L�NbS�j�/!j����+��O��"a3c���
����"t�m�<a7 �R��3u~������6�ziq@`�x�j�C
6]��h�D9���G!���3�-��E6� cŇ�2��C�G^Ďq�K�!+0hq��z�Y��4�ݏ��1=����o;�9=�~6/�|��趆V�������Ws{�S4��7�;��Y�K�3�z[����N@$�E9�-��#9f�Y�&����a6�B��:m詨�@ñf��s9�6!F���95k�=Hi����(�|�h �j���,���
ʄ�!%��-��G��{r8�� H��ތY�9�S�I�L��t��&�2��2��B�zB2S��i�WB\�`�������O�E�=��^��n�6q7��-P�S�$���f9&�����/ T��T��>��E��>@��ƕ��t<S̟p�<�;̬��Y�!��{n�`�	��o=E��	�\n����%��Ѝ�0ͬ0w�Ӷb�� �Gk��i�9MK�ն�g4��S��1V�ƻ q9��,�������PS����3��H��N�f�K������b%%G��>����&�N]���v���.Z���}�@��J�u�l��,�r�z�7LZ�7��t�I��dڈ��J����h\����+���4�j�o�{�3~�����}!�*nH�+�V���9�"p����sӾ۸�CIm�I�3�5��[AYp�
����K����:�N����O�B��ޒU���f�KM�m��/k���|:��n�7{e8t����#u�	�\5/%����-�%����G�D���nH9={�fkȔ(B h�~&��7���jS�/�B�gGа�ͮ���?7`�03��9�X��Î&��wx��䵲�Yί�*�@%��[t��6�/O ��F
�*bO�*���Qu�BL���o��za�^����#���6Ld�T���[�O#K)��vs4'�M�����r���U�� "��u�E�0�猥{n���1m��<�'���v#��#��&�M5E1�mH���)!�3U&!��gB�m����Uv,�[�^�����Zg�oîu�h;�3E��`��]8��!��l�������aXc^�?�Lbv��1_�7�:������t�����!��h'7-��q��<���pP��٥u��ޚ��Ӂ4a\��(]�b MY�b�
�ߡ�R,�i���c,W�H1X�;D'�?"�l�,DL��F�����a��������o6b���a6��^��W-���6Ο�(��Ψ���W��W��b����~z����YГH�B������{UCz���{+�
�B ��"j���ڈ�ب�s�ng-6`0TvH�Ǭie߆nx�� ,�N2d���T��f��>[���]�?��]�������F��$��3��+�#ƿ�ˬU=Q}�j����r��xH�Tk�p}e�l?BD�Dv�I:jǨ��������( ��@�0����ꃋw��
�L���C��(��w��"�ǯ=��Kݖ�"�X���j��C�@�7c���w�7�}��]�R�Y(|�b���!9���;U0SO.�q�C_��b��7�aW�V����i��'�0���`�퇙v�>N� fS���j����ߎ�F�}��e2mH�������-��t9�A
^�a����e<Ӫ[p���^�\�z��0�ąB#�SSߣW#W^!��]��]�xz`{�_ƃ����؆�V-�1[�)dq�&X���T(�}��4[��c��	|u�.}��8J������4'���3����OvҶ��K�;�$����klV�[9`e��*�|��bDE�DnUMc�g��Q�P�����~��x�����b�f���j�nT�%O�d��C'J�w}����L��ZɜB�ȡ�S�Ǉ�`G�ɲs��أ�x��D����@U
�y�i+�9��7���s7�Ӂ&����ն+���%Żk�`�7�n��$�b�s��i�rD�/�~�������E�@��CcP���c����~_e��b��� ղ����"\��(�w���%�TēT�1���vώ;JU�F6�Y���ѧ8�ܳ��T� Z�6 �A���}�o�K �*C`�N_��ݎ=�Z�qE�+�����U���7b��g@PK3  c L��J    �,  �   d10 - Copy (29).zip�  AE	 ����΀�MqēFYD`@)��5M]
FÝ�3��x�6�j�!�ɬ��R�h9���~*�js����|��"ҥ���)q�B}OJ�7�U��U@�����g�x;����H���S�t߽�"5��n҅rl��
��I���'�H�(b�p�׉X�H��]�P���z6C���ﴘ=+ �F�K���=,xh����ls}���#z��r����_���)l;Iw��l��8��v��{�f`'7���б�U�H����Mg�0�J�ݱ���M7�G���E�s��Ϛ�Iw�:h��-u��b��)����n��{��|�%9������5���%=F�
�P�Qy��hymۍ������� �J#Y����z"�xYt�V����%4�0����ٜ �j�H6��
N��Qa��&�)L�ϛ���Kߨa�&*��2�]��Pz�t���v;��嚡�Y��Y:ۚp��'�چQ63�3�~��U����|�u��#��(�����i��nЦ�-��%�$`�W1wv���\�I2�T��(k�l�(�зj;q���CmxP="����p>��<<�7�)C%��5��nQqs�WO�-A������:�M[�~�w5=���R�h�㽫N��c��U�#��?TK��+����gR�ۢ;���3�i�N�V��<����S��T���T�'��;.`��[�O�3Q �_�ƫz�`��
���Y�'�N�(M�:/9Ҕ�Y}+��S���677�"�+Pv}'P�!�)`��a��d�s���nJ����ȗ ڑuA�~����⑩ˏ�,�K����B�O�G��������<����n�螚�,y"�<j��bIA������_��#��*���	~�w�0�M6	~��)��&�m��e�`�z�f��uw����NDA~pqhk��M4 �^�c컌Y�\���!k�D�A�)3�� �Xq$\#�Y���Q(PUTx�c.���r���YL�k�"Z)�J؆<���wțz��RX�qK�L�+=�ek�D���@9>4��{�+���CFh���I����������ch]-#�Y�q�Գ�� �.��60ى#�;�g�kP�9�r�T�n�|�d��?��N���8�NkeW�̚V��k�o	�|��|�����l8f����Y�H��h;��|z��%�xa�2M:'Y���M2�^W]�_�L�8���s�1�TU�a�+t@�so�2��9�|L.�Ņ4�q'��2��Okm�m�"E
B^�kc�}�ZZ��Y��S.i2c̽j�=����q�AEL����X!�
�z�U��c�&F�.�G:�  �3��Ko$0�0z_	��k ~z���0����[BsQ��i������C�yW�~��9ga��[��M�?�{��^)�w�7�Qŏ	����x�?K"F��v�;�B�`R{����H%Y�;"xƸQ]n��՞H���s0�o�>'��#�Slf������ ���kyب�"C�����r����������2$@b�2���Q��=wq������M��i���a��7K�0/�#ur$�B�!���� T��4Q__s���;��u�E���?�3r����iL������N�K�و�NKUҨ㶬�
��r>6��Vg�6�y�L"��96���zA�@�)�"W�5[� SX ��n�FÓ"��*M����1��D�E�C��D8�%��6b$�'�x��,�Z���մ͏V=�{�=�a�P����'���1Ep57�T���{u>"H^z��:>��"2x_W�&#�7?��
���5?4Xp�J|�s������b���'v��^�[� E[���]v�aco'H7Y��bR��p/<3�MJ�.3�Fn꼇	��Èr�HB�H�/u�v5�A�o����'1;�,"K��^���FҘ4`��R_��]F	Hp�k�D�tl�1[�K1�(L�%zQ�s$ Z�o�d���А����?@j"Z�J����c�b�K:��{jc�:'����}��0.�J��/rhJ_���#��;�GD�����p�'a4#P{�e�qH�~n���W��$ƪM̥��Yq�N�3'{מ6�K���8�"��x�4� �R�	MY�,l;$I��}��R+z^��׆��>2�L��l��<$-׏��Tn9���	n��:��/�&�ù���,W:`D�����=1�6���49�O��=�vÎ36i�(}[��Ms��!�����UJW���
��\�c��M���T��)]�5���@ch����;���jZ��<��LZ^�����G�蟸���M�<ORC�Hԡ��9J&T�~L�VV#�Ӏ�}�S,�qE�8�I���|���t�zP]4z�Fa�����ջ����!��q���t
V[^����g-����,�"�F�e/:�2�)7-�}0x����h�B4~�4��]�l���3l����[k��D�y�U��NSt��v����d���K<Lq�4e/7�����5!��[��_�)���z|��4N�A�{.Qz��B��g�q�B�Ć2����,����ubB�!�]�r�	�B�Gw�H�Up�\����D�擾�+����}:U܅x�$-}�k�F��딖����d�/�sT.��N�%�+�_c�|��avtn�q���ʨ9��Y�&P���|�_�N>���-Hu�PL 6�[�iڛ���(���*�D�khy�#�[C�����"��-���V�7�e����ץw�D���	�n��ܓ6���6r�ݡ���;;y�����q�"��i0���
}?c�r��%Q�y-��,�@ ��J�;o���%���r��)�5U�`���y>�rH�S��)��������b�Ic�s�d���9w�?K����d�	a�w���X��S��ObL���J�k���g�1c���D��H�ZZ�\t)���գ��#$��h��)���>v �+j]���r���cς�s� ��o��7LY�&�G�6�w�Pe����يS�"������i)M��� ����ϵ`B7�� 'j��,�3eX�EV�*+I�p�_n6ߺ79�i߉`��};���ӼI]~��0T0%�:p�{��B�I�9��G�vb=�I�,tfRyP�<[��X�k��&�՟g�!���I_6I!��5]��V����G<�GҬ������ِW�#v��}|�� �y�(�]���m�ڎ�\b{��-d�'K���fĨ�ݺV �%̐�0��6�a6�<Y\�q�H����ҷ��7Om����#���&;�j��< ��x���5�����q��m`�O�h�KfiK�U��%�!;��٦\�w� �5.�K��o�~jeq�ql�P�=t�Ȉ�`+=�xb����������#sޣza�q����/m�T��b�,&	1U�Zx�Rl� �*�e��C���<CL�[�k��\န�i埒��e)�U)c���-�η�zr���N� Y�n�1m�Y'��1�l�X�[��#���￤��ɯ�|�i8�r�@�p��e�r/��j���*�ֆ�����_�F�4�.����jǢ�ڄ!~�C�b��v�{�U;�]��x�CD�{�e[�l��u��u{��	��(�`�u]B�g��Z���4roi�~�a�Y�J�7.��u�*��j�k4�--�sk}*v�x\^��B���#5\�I��X�rL�@f~l�Fxa��"��En�yN��	o>_�3g/�]�,�6!ƛ���vjI^3�\|�ke!������8Sn��$}�
�?��k;:��f`�Ryk���A�h���e�?5J�o@E�[�#x�H��jE� `jh�m��Ѧ2�Q��	�:�䆈�bac
��y��/����.���I�vߍv;�4bpW���j��c��6w�6K�-�x>�k�����=0��(�:WѮt(�8����mtЗq�֘#��k���s�klaaqY�)U�.���i�p�}vvA9��QG�I~�C"�H��+�C8�"�/�m9����\��e�'B�([�Bb!�,�FRS�
���6{$��]���? ���t"�@�1Т[55�����{�5d�qp5�"i�R���0��>�S��bj>n=�N����g&�U�	�t#RB�Y�P�I��³g��掊ON�-se_�Oy���F������e��7{���r��Q�A�B�A�q�a<�0�=��J�WN���Ѯ$(�$�]��☿����5�aT�>�tNi��r��]�9RKz<Z�Y�!�r���$*�0x�p}�}"��j���@���k�h�Ԩ8^�`�khJۯ4z	�U���$�@#O�k��@'q�V��4�,�oU8M����f\��w��z��'�χ�7,薅�/<�%��T�M��!+�3�,�VqH��\���]'f��<����c(~�뀦@����
����r[�*����cnU�  �K�/_��Dg&hϕKc�qFa�dp٭�kTO�=g_dj5��y��
�ɦ��������~W�[��C��Aњ��O+���?�t"0�_ѯ������w�Θ�� �������{P�R �i�ArF����?_��<3�_�S\�
4�&��m"����m�:w?�����������N��3ga���!�[�i@9�&11�x/�
v�s4�~�`3"5,v �T�G��^�`���R�j�[�6i���?i��8�
��*�D1����Oc�AvC��Q��:��^�`+��|� ZLj�"��A�>f0f{|�� B�z�7����Q4Up_����-ZHJ��0��h�w���h/�Y�Qx�/��S� ��f_�O���3����?6{�^�j� ��ߛ/�8|�e�T�c���AQ��n��r^s�A	$����!����l��<#��Š�\��aT.ږ�`�t8+��D3a<��]>���g��R��L���H:;l��LЎ���}<X�]�c�5w��Z�r�pć�|�E�]I�����Eq���x>��L"���w�L��:�;��Ľ2[��_7(J|�&�1̥@;LQ����{��,}?�w�%L���NN�NFK��y��Tz�u�t O��ߛ��J��d�dD)c���H\*�h�Ä�D�7 � �f����¬���8�d�i^��*��d1�d��=.��+ͤ96���
�΁ky�����-�|�pA��d��d�zd���H �2��rB���)�u�Q��jb��Ϊ"�՚\c��r��2<�!�������`:J>�1���ng&��j�@C����݌��C�lV�c�Zx7p�M�z"�K�k�m���Y�FVIЁ*�dBv����M���ՁR�rt�O�ǧ���"+�Ațb�m���T�dO�b2��{��|�\� i\.���h�����t��,ݔ�U��?,Z)���~���n$U���-�4N�QP�$~����?�?��GZa�J�x@B�Y+N�H*�LW���ތ�0-�F�.sd 2u�V=���׼�ڈ������ǊZ|k������І�Bac��e������[G_U�;��� �N��Mxk���zc?��ɷ��x@�$O��ٲ�ť�1�<t#>|��A7
X`.Q��+8���X��d?{������:����-���7���k�z<�yFƵb��?�jA�A�\�֥���d����ȿ5�p�d�ݘ︓4�{N���>U0)��[vN�`���)FG�a��.�w�7}���y�w�] ʠ���pKX��Qi�?����� w;%�Pl8�q��3����K<]i\�=6b���$���=�����k�p��#L�֌��@�W�+�@��P�Ve�K�f8t��Ty�2�v�A�ٮ���c��\W�e���3�rl{jزȞ��i%�����͹2����������H��j���pǏ�-:!ᲥOF���o+6��Λl��
�-q�j3E�*z%�Iu�Л�'|��ɷ�y�7N�����駋|f�Y�q��>��|�
5�}Ә���{ν;��R����5�;��[&R�<A fݸ���cw��^��&7�	�TF�ax���\C�����.��\���5Z�@���2� J������� ���Ӷ�����
�eeн��h��/9��5"���(�����nw23�ur�ޛ ���c���;g�zX89۞��H}x�ǋ��g��t�_��*�F�n5�gT�M��᱇|*6g�Z�ˁ�
�_�� H�Jf" ������:k6)NLv��eV����nryh
f�����/
9Ɖ���IA�0Q�&���6��πF���f�#�$D_��
#����9�G����e9�2(��h�Q$��ńueK}�]$c�&����h,�E"�g�O�h���' �*�/��S_�m�O�Np-���U�˩���@U���w�)�P���c����P����2��s��>]���͚0A��h\��T��H��s��|,p��6;%��1�g$��Ju>��)K��>*�;�d�iN�ڣi�xf����G�|���ZKZ=��
 �T��-5@dç
�'V<�Ie�$,�&3� ��!눩���.�Q~R�Uuɋ�g��U�� z��{�dgs&Q��y���
@��e��ݮK��o��B�E���ͨ�P"Z����9Bw>_�+C0q=j�S��i�;Q��9�!S���/u_�+�>$�
�k"_sx�7N���'+�m�Si~�1���?�gz�OBu� �;���ڂ�Km˱������љ�v������������D7
�;.G��(��L
b_��d1nbG��s�1��[���)�L֚4����.�^���Ĩ�+��9��-l�0���9�
5���qT�LF9�>�]o���51�DGW5� ��p-�ÜZ��&��r�@�Ǻ���iQ���8��+fg��c��߷�θ#��r���u��{����֤JL|Rش���u|^�M��3��q�Rjl�4PT�pUw	�N����"a��p�7��{�'�C\D�V�ՙ�R��I!q�v�ρ�k��¤����J�vl��y�``'̈�4˞e"'�}x���'��L��`I�Eh4����Yțdq!$�o]�~B��*���� J��2O�78vd"�������A�k�U����c:`ͧ-�T�_=�[/���^e#~����"��%_�����(WEzl�Z'Y�Fz6�:��<��j�2߸P`�YB��C��{��Bo���L�Y)M�F�"�bu�{?���<acL�(E}��9�<��`��_G�XCT6c�.i��F�z)�;�����9c`��%v��3��y���O�\_���=��7��<_��F���\R���jҤ��i�~�y*�Y�u�G^g���H,㉼GJ����_��%L{{)�3_Ӡ�4bV�\*W١=۪~���^��qv�1�ءw�@���&�E�W$ЕgU J(��;X����	L����ʞhn`�U$w��Ž#{UH9eO��0��%������FQ"����beiϰ���>�Fڎ-�M�)/`;��.%M�/,E�
u5>L��.,��>x������|#�{�nk���f&)�CA;C���Ӂ��zd�,/��sy
A�}%P�9�'��y�4�����8��eej"(>�n?��D�Fp��y'X�]�`kpX�~�I��s!�a�K�a�!w��p,��ƖK��m�����og<��u��IГ��x�Ӏ��LA�H�ͻ����7:���z�iDW��3+�K����U�U����]4 d��ax�Y�� R?jܲ���)|@���5���61�-�*��#٫L�U�G~��uR�<g��:�l�5�T7�F��������䦓G��mq��������~�(g��à�̓�H��dVu_+Z��7��\}O��(��&ۏ-,4���������[補"{���_����X��p�<w��&���[Shr�t/,��f]nY�U5\e���s�ľ~��t �4��s����,�n��]��^�l�CC�@�8(Z����@�8�C�i�2O�>���q8���D'�_5��M�e�N��ݞ��4����.�p��P���-NG �qN�!3���.��o�8������*.���֕'�8AEh$e�� �y#5�!�VGb7���z72�C�cx��5-J��е� M?���7��"����@�P1uh��L\��!���m)I����.�H��f=͔iFx8��q�����{��&��̞��(͝34/��S�E��"F�K=g�͙���:���#%Z�I�p����ĿZ>� �^?�`�y����g�<.6K����;�w�jy�h��@_��`��Fw�9G����{�-��TW-Oਛ��̢x��sd�����l�{~�,�k8���d��5��z��J�J=MȚBQ�p�	���(ȝ����s0zYI�.r��f� �) ƾ��2�Y;�:�" �U�kv/��  qy�)�9/n����R�P+?ѧ��HU���~����f��zH�;�����KJ򲮖��q�?5���2`T#U|�v��0�ߖm�p�u2Y�M�Z�P�8�[J�*�Ǒ��2=}hT^s�����ĩ�񟕰\�FP[�d�k5��-N5C/x��lپS�ӛ�ke��*�E���Ԙ������K�F�Y��Z�m�(_p.'@�f�\��j�&�/��Y�s��s��.�kxbpMk�$�[ٓ��(�3B%���ҧ�:�pNE�|��[,��S�S'�2�<�<@�cN��C
�H�&��)��C��CjLfˣ�,`�_o�;�լȷ@�>X�����[�Ȱ�8q6j6XŘ�u�n,���i�We���h�&7&�z����#���l�� �բ��:���4�B�V��XM�қ�*��-;;w�`�ͽ��x��ƀ	�GϝJ9�y2�"�ItB=]��f	�v,d�-��؏�W����Ӊ�x�Ϸ��2�d���t0OR������{�����{O�_��>��J�t���Vy|nSak��筈��U �qw"M��l�B� ͅn�`F����ħq�2���������WvlY.��C<�w����sa'���/E��Hא,T�	�.��om&���;:��Z�2�{Ne��#�SQq1"�_Epi�3I�"�I�w�*�j�I������a'�L��9NBl�Ƅ�53o���QR��JZ�O�f����p!��DQ�V�x!I,4Io?i��?��VP��.ڹ�V/������,�͋���d�]��YȳY�����	yX��}ֺ���`{��t� =}�S����Hb��Y�T�5y�9)�{�P�H��'�t��,����TC�T��0k��-��/�z	Ŀ�t�.��_�u�k&��<0�߸���Q=?����I�?"�4�-��3�]Q�Յ���G�̛�]�Ũc�Z2�z%�ξX/d����g���j�@���r,Od��C�{-��|
��u���7�V*�bK4�*ƈ�8W3:�l�:O	~����+
��eNq��p"�^|xLGZ��x��q���_mԞ�m���y��Ŋ@��P%
Y��p�T��\�c�(D�_z��?���Dխ�d���L R($*���8�n��/V�X�6%�#��F� �/P�zj|�����Ɏ��}=��:�����6x�����|��&���Sy[�>��iL�u��ym�4I����5�wsMI�i&@����a���e�|5IH��b�1sG�1m
����;b0�����#����IM���뀢����vs����~&`˞�H�x_moo�yL�?���`hw�3d�������a6��0pn���>3CGz�F���JQ��}h�z�t���&P��>&�M�ܻ��,[a��!6`�>�Bi��'�!zK�qt��s���;�����Ş�}h�����m��rX"���A��/P�u�Hz�ό�ٖ��R�H��%Ł���s����K���N���Z#�P[( A�����)o��d�6���*R3��,0��M�e�G�M�?(N%���4.C�TR<�+��L��e�OZn����l�ѳ�K�ɑ�[�����������o��*&�*A�/|�{.TN�T��M| ʊ�.�s�?�%��!ϰ��tr	'�W���ߕ�K�	""%+�T�1r�Xyv�W�� 0��GԏQ����$YPa���!��"�
�7�J��4X,�zЁ|%H��&��k$	�L껋,f� H�Ao���<���3�f����aucU����B���)�JS��V�	1�Y+'������(���Q�?��Fx*���K��X�
j?�����i��������I�ۧ�fq"��\�d�(�z��7%Z̷�C��/mP��������_`_E�oY_-4�Ft"�KA��+��|H�z_�,�0��Z4���u�E���_0�_���/2j�y���MP)tts��@�>���M���d^D�}��h}�x�)����#Yg�'���@��́d ���!��y@����0y�}�h"��,�����!ة�X߄[� "�`S7_i!�B���n'4󑠁g�'r/�Y}���ۊ-a`)�S&Kwz ���雸��t̄mW-7�x5��d�Dµ�0��4�oh�|����2�u�"�f%Ы)2��F����o�oS.#����,��Y
 8�koު-H[�綶��>o�).����j"c[��Xe'�{=P�� �%��_�p^������#��%ĄnL[b\�4*T���୪��	�S",zfb��q|�E-��8�{��S�����)!�������+2:�����5�b�h��U����I~�p�Kz�Kx�%8մ�

��H����TNݼ���nŃ���hS��㳦%�r��8k@-/����5�\��N!m9���6�th/`��f@v��B��!Q>����o`��l�>[�-���÷��4PשLT���]�nI����(
��>�д6{"j���2XC�;��%$m"n�����A
!L���r/q�	A�Sq��k%�����r�&��Z�ǎ��cG�b�Պ�wkZ7!h�r4�Or5D�s�a$A�`�L�=��N�2�D���$Z�S��ds��0�ix���m�HS�s�^�mk��ϋ*�)r`��BJuF�����8J��T6��8�p?*,���s�� 
�>BI_PK3  c L��J    �,  �   d10 - Copy (3).zip�  AE	 ��B�T�s�ƧN�P�;t_��C읅���7<�b�?�m�����O�Bn��[�ZS�SĤ�/���J��-���硙�"op�AA�ֆ�C�<ƻ���M���;o{�1���e���	��	�y��\���4\l���\��,4��z�I���QBzP$���|n_�Dʐ*�T:s&n.���A�7:7.
}��䨷�	�q/��Cw�*q�n/��%H]����E�z���<����Zn�Y�K3<��5��r����d$(��X�Q�TvX= فaF��z0\�D�AK6����h���-
P�aĦ�)�ݒ�^��ƿ�t�IR��ڐ�;�y=��)2e?-gz�M2���n	\5����T§B�z���&!V�p�w�f/�q�.��"x��L��S�',�Mv�]�GK��n���N����ybw�|��^8#�������u�V��c�^{���x���/���Ҁ
n)����]]?7$�y�8(�=���V%q�3e��"���}�u��K�]%� |��:�~ƄkD6��=^{z��8�Q/��x�d2(
z�>���g{p���Or�U7���p%��.˖�)�z��/��t�e�/��x�na��C'�h�Mm�F�D�܃��u���rA�l��CYɔ��ڰ/�S�JT A���R�b/��51�vG�OtGfrZY����]����@yZ�HKh����(yl�����-;>I!�'$F$qr�)�s��KB�D��>t�W�l�1ׂ�#�ږ�q��Ҩ�22���Է,01�)w��ۚx���Z�DȨ�S8u_^��O����R�g���60M����WG���Ns�]��g���x�я/���_��@�CCQ��6>)Q�O;��A�8"p5�W0���B+����aCo�W
e-�et�dQ ,	|8�܇x��E�v��x�͞�,��ŝ�I
���7p�;����Y���]ˌ�[��~����Fr�D�m_2���2�V����U��l�b��s-�����UȕO��V����f�^�4kq����=h�*d��S�i��~Ҁk���j���~���S��`u���ǥ3��LȺ.���L��~�H�z,I��MC^��aE�ֺ�5�iW�T,~RO��jz�zJc��	hխZ�i~2��LT܆�/���0��B�Q�y�tw=�|c��S�'���)P���y����?�);�
}_�4��&�>TFͩ�`^0q�������s�Bu�{�C0������;r��)'Z��푮\p��!�9�[���*�.��9�A ���� 0T�x!��X�����:��.:Y��U�T�pٹ�C��h���}`1l�tr�8��0��O�ҐưI����I�>f
����;��+�'G���]���V
�����v �������}7�የ�Fj���[�7]���"h2�S![1qD�j��՞q����^��y��<AI5��Oӿ�An�zxɖNTcnv5�6���@�컗@�iP��=4���S��i��!�	���Q:��iDQ��U�e}�N�0�{@�/�S�+��m��'p<� �p�rZ�M";�ʰ?�j]�)
��)Ur���#�m��ϕe�Sz~�kl���8�f9�TS�{��� �1�Um����2U�d�a�̯��9�,hF>��N��u���*Gb���g�q�	��@�B���f2������4k��Hi������#�jV4e_�mi��|�x�|�<�,�mr�:u�65GY�*����O�Q��@�;�N�j�ae:ڷJ�/ym�i�e���NA���>L�����
�9H)o��'P��t �@{��	��1�7� \�Y`>����0�����&R���x�7��*��!�3Qr�q��.���h�~�|tm>�>�7y����/M����?����4��~�����o��!����<�*�z:��`��>d���3^U���p�� m�����k�"*;lNj�9;^i�^�1���I>rv܄�)�� �mGIo���� �V�ǿ��jk�v�I�%���Ғ��Ջ��ryoV2ŽpٕIm�(ݧH#:ϓ9E���'/�"�==����xJ�k$����!m�B��%�K��5ѳx[u.-��4ٞ
FN�X~��Q�������3�g��U�s��3I����u ����ק4��ƅƟ�K�zV:s�ϯ�y�%�*P�̂"M������Ļ�%%d���a$~��4�CR�����٧���8�u�R�"I�:|J���5�4�V�X(��OQ�9���X�����i�v��	,�ĩ�	�r���^�%��1��mr�i�b&O�j�+8�d���>�:�gDj�Ӌ������5�nC�����x0�|��KeA�T�ϙ���2)A,GOb7pW~�=�G�9h�Z�Y��7O��V<&9+��`�U[���Se�K#�C/:x��e�o.ZJ��Ȣj���碬W��m���e͋����aN3�W�Jd~_.dcK�Z�:�v�$J��ѝo9_��EM+ؕ��8��xǆ�㾕W�_�Y� q��mwc�M���,�� ��/Tf��?tB��>ӌ:���n:�m�jS0��PI5������9�n9�%C$~����W��o�ez�����_�E�f��Zg��k����5OVрCپ���Ȏ\7/ZMk&+K���1vN�H:~Q~9� �އOU���6K���������сrc,Z���!#�ʇ�y�,|$T-�C�P��ق�uq�`W��b���D�9����MȠn�#�����bN�A���o��2W�1�[���[�U� ��S?��ӡ}V�R�{E)��G��!fEK���=^��<������\F�kb��T�q�:�C���g�)B�Yp��YN�M9^*`z�>k���"�&q�LI�Gq#�z=�+�4/�g�=����5p_�촲�a,�iT�?�6ygXxu�;-1��}"&�S�a�I��XZu�Rm����N�J��]K*��i;���<���M_�,� �΂\�ޠD10��iH��{<���j`����=����}���$�������]��Q@q\tV�ꇧ��o�=jN�&BL�Ef�����?"�G{����M��e�J0������v��g�:�t�28���D��D���?��~�ek��aI�&J\�t���w���ˇݼ���UO�9����^�V2' ��|�YG���(-�_J�ߥu���횙
�`N��V�R���URiOM��]*��>��(Lc�mb��|������Hn��WhL�I�t��c�_;�)�jg.-ݖ���7a
��п���J����t��7	���
Rd,�@�rՑY��=r6s&��c�\�0:��&\?���wc��ȯ���z�*������sI�Ӏ�S��|y|1���F�KF��\,,�̠�}*�^]��E����v�A�0Nd�?\u�ti���i�8�o7�/���*Șޝ?L��?�iK�z��I�'�dZ�s���@G��B�������VE`k����L���TE�}�i�́V3}[�������`9F�P�)vܵ���N�����P����[��^%�WҬ�8�Z,�� ��R��0rw��YOc�H�$��xZ�"�F���o�k.��XEFZ5�#��N�n/PB���vQP�:rϳ]w����A�Yu�W�OR�M��R�	�Y-sC�WUp�n3rt�R�&=�j�)&Q*H��u�j7Y�#��=-�ͥw �����!�i�4.�C
D�(�ɮ���f�X�Ž|O^ Y5���?�lm'��M$��o�9��֍�����[X;;U�&p/ԙ��n˛`a�i9AREI���	҇U.�����-��4aGeq�МJ%�)��h o�+�{}�!�N��4��f9:i
F$C��#�E�GJƇ�ţ_���fW6���E.n�{����q��W������� �:s�	����=��3r �l#��(��*uԌCajP�	�T��9��g\2��Hob�v�I���
@�����0��%�	,�j&�D�����A��
5��2b�0�SхGmO����	N�IN�~�VG�/���k��Rt���/Y3d�ЄW6Q�)P��I���E����ǃ���|��`���7�v+�\>��?H��*ƬM���ARG��Sf'>ӯ�����Q��jN���<�	��@�|O�ぽ� S����� ���5�0Bjh��I]d9��5KuE����ʼ����T�&�;׀(�MK��W(��;p;�s˳���b^T΀��EQ�^�F6[��T�G��{�29�mz�ւ�2�O�p	�1t��6�,���z"p�J�nE�/,��vmOe}_Zљ��ܐVLJҤ^e�x	U.z�
h�҉��h$���b���Xho�߃L����PD��j)�/6*~�����@a���o�z��p�^��VNp����>�ۮ�gh&���کх$�}!E`��OO�����[�+�Q���X�݃qS�����p�sB1���oЙ1~�pՇ��f`�?z���<��
-���y?�y'0��M�w9V��U���x\ �M;	��̿�h:=|�	��é��j^�F�Յ�+HL݉�� �(�a�8�� �4��4\�]��!�7W4T�F�+v����%��g�l]`�U{���LU^MGn,�f��|�ռ�1�%�Zta=�{N/%(@W�&hcn�� x@zd������]ܢ���͟���I��&l{0��eu?�H�S7�3/F_�&��3�tR����T65�ZO���(�O����Z�QQ]�VE�T��
�JR@�h�iإ��'�A�{�����	js�J�EOd4+~"��n|���uj�I���[_Q�0����b��g��1+�<ώ�jZ�PJCu+�0T>Ls�&��{S�e�3KV����e������#X*���J}�3�-O�����l�hEW�-���[��A
����}��u�s��Q
3_yƨ�f�*ه�u�#�����ñ� ^��-��U5��<fG24�J|�}	��v`��K���h�n?ѷg�e�{$@� �����6o4���K B%���dNo�K�?U�'��n��FӸ���a�a��I}H'�Bz��`��b�߲;FL_RvW���u�l�S����u��"Li�⤱�
�ы�.Y�|�j�n�l�;�Ì��g���Y���t�hߢ�5�����9:U���%;�3����)K�Ю�O�YH	G?� 7��5'U�]�4M�ƻ dq��v����:	�"��H�z�`W+om{L���{��}��f��h�T^�`�Cx��94�un \Ȏ�J��f���#
d`��ԝG
��џ��3a������p�g$������iC<� ~Fn]u����i���vP��i|�o��j�]�+.e;��Vi��	?�#�VCV<>��nDu임&ȀmD�J!�-��,	緧p)K\���Ӓt�!B����gC�Oe PxW�G���C�h�!E�d����_6jl �]��þ�e�2HP�9�B��D��3v�D'����i&q����qN����O��۞)�W���i�g�Q(V'�4��L��gw��@�$[b%YT{��������e?��s�0��|��o'�6��[���a�h�g������mV"b_��s�?�O'-`��{�Zu#ﲞ���`���%ԩ��q^>��B~��H�a��%e�xW23) ����7sV �Cv��q��8���f�� ���M�x��bN�Gs�^��0nl'�q����%v���1Z7��3\F�xX����n�wi�F�il�MR�$!�`����Ǿ)�	�I�QUb�7��M�T����T/����k����@ׄh8����u*�����5@�����%���sN/����>��ѝӧ��Q��-aEX�/>���!�����~SH�r�ܸ)Hʪ�	e:��2��o(n��<29ua�n ���7z�ab/�h�	��Ѽ��6��<h�9��+���&S�0`�<�' ?ADj���Wu�x�l���zȄ^k���x��ai��u�쎂o�C垂[�%{��N��T΅��@��������x���FM�%���qj��ػ�a)�I��0��{���!Q�� C�g�礆u�G������r���+퐗�\W7E��]aJ�(�D�eK�����*��$Z9~�������U�|�R�̛E*z���;	2����Sc`k���e56މ.ȷ�w������E�W�Ⅿ�ZaZ�쮸j@�T;�1���;c|hjQ{�Į;�wO�(v�+5�b���TH~r��܈M�r4�pR�_G~u#5�����H�	N~Q����V O��Ս$�M�)�7I� 	c��r4n^��{��֮AN_�겷a$�0$)�! �A`�g!fI3x���F�k}	�Z5N8������Ȇϑ"j���Ļ�p�%r��`$�R��5��V۔V��AQ����	��o�+�D3>��drf���j�r���.'�D�.�G�!@y��U\a=��EB��=:&ֈ��KZȈةkC�dz@h�&Y��EPAr|mv`��?�Ô��KM\��x�'�9f"v@W���D�sx���?���v�u�,!1��@�O��_�Y��s�E+&L�R�ͣaW����p�>�f\ /�����q�kI������<�}e_�����m��+��y��3��`��@�k��&9�"�w�<� ���t�ǘ��c{��Z�P�L�J^��ܕ�\H	�4�7��_�*v��m�c�C;%ʡN���@��Y�ۿ�x"�-F�a�$�C*%reX�A���4��|�'্����Ӽg5��u#��}��xpU`����F^�y�(�B/�DX}�����wB!�m5�D֕��Of2{U,}&*��i	�{a��+Dg�h��K�J���`�?O�`�P����쭑�:��`�7�%�`Τ��W��TZ|��Y(6�X�<�0�q�ñ�d3��DJ\.��;�Ft�3h`���̜�Fq����'ۡG A򏏽�����u�׏qsRL5a��"D�[�_�Xa�bIލo�%�P�[�j�?r�f��L�Cl��/��T�U���Ʌ�� ������H`u��R�)����Ն�;r��E:��N�������J��&�g�#]eH]DϭO.ভ4<n��t�Կ)�w��b�'�c"�i�n	����%�[\�}fg�w�q�ci��cC�s�� ����e���rJ��&��Saa��x1~"�1����ڜ ªu�
m�p�8ۇ�X��a��	�o��~�%J��Й��a�m�W���+ȄKdw������	�?r�wE%�T�άa�C����<��j�����ssX��[��(��L���>`�ܓD_��fkTݛ4��b�CT��AU}�dL��.��<*�^=�?֕�m�lT@�-���?9&�ٺ�ɭ0�v,�Ь1���O껙�9�jr8�_�_�����v�g�K��7�(u�w�=����}�*���*�C���HۑL.iz_�a/ư ���~����RT���_�@��s��~��~�P�y�s�����&Q�i�(k�W�q��.���)�#3^4��A̺&���oי��H+o��k��\M�N�1dEȐ�f,��M.�?>�����B�ᢽA�7J�q)�K�'����'�a�6�sU��G�e�^]�e�h�>�l�����w�3�+������g ��m*�SԀR+#���z�4��(V�g���U���l��UN�v
�Y*��l{)��"X2��:y[�G��W����S�a�=��&z�/�<�1/�U猖���'[}���1�~U{�i$H\~���	��c��  ���B��أ�!R/��u"�d@��_S�#Ly�r�3kv��N�$���q��t�P%�4���
��<A�� �W��w���V���S�2��e�E܉�Sqd������q �{>�?�?eT>��д�����oN`c��	g̹��R�hu���-�-���t��0K�̤`*����@��l�����Fv��X�%��vZ��#Vb�q}�Z�׋$�j��'�C�K-)+7{����&V=-+�����A/�[���p.q5VS{�0�%H2Gu� �WJ:�yg���CZ��o,������=�+jb]A�w��̓T�\`㤁?���^��d	��$��ۇŸ��8fe�j��:����'���Q�.jcOkӧ�1=���J����Tl���{7"�[�2y���b)���A��g9���9�n�g�Ӓ(��U@9Be�-W�4G-ɜ�u���
wG|��Q��P*dQE`��. h�j��#�%��0 kɠkC��v��u���3P��1�^W�o�ިK~E�ʗ�i�&7>�յV5�|`��׾��,C�DB\t&T�HG�ɬ�q���}�r�	�x��N.�O�B���G�+l?j6ʗ��A�N��[�(��@k�|6=w�J�TϪpf
��"��w�H��H��w.{����}�VÊ��e0В�ӯ&(���*<�isz�?��6�1����q�V��=�ov:(�(����\�v{e0�V�a���@Y-]~�����g���n�<��u
��r����ʼ:,��3�g���Ε?@E�(=�^��78�T{=u�{��K�]�ǫI�Tp��\�rU�:ȏ�ҒH��/�ݪYN�>3�ź\��_�C}ߪQ+�[wB�#�z�!��S��޲����ܴ�i���d{�������E��v.
��yλ-��!��A�fV"L�-N������Ĺ��4�ö�D��'�Y:0N�C��WH)D�PH��t��J_� E��)��鏮����o%��M*j_+-P�vKkg�$KFTIE�D���w^�~;���!�o���� �����e�c�
��j�� V�mR�{9b������Ji#kk)>�IYrWX��k�bVd�[!��B�5��wl�E�4	W�A{ũX�E)���i��<�`Y�*�n��4q��^@�2�z���CB�n��Plz�>�^�&$pВ��P�ە�l=ʰrPpX�gX��N�U���i���o1�'�1m	��#����Ev{$u3�xz!�|z��(]'b�'��I5��nc���!2��'_{�XF�h5�7QS#���,����.E5�G���H���\��J�W�["wm`wjC�)Xs>Ac$�uUJq7%8��Î���\O�(�j�hwv��4!a���>sKul߼���'5ǳ�~�,��񜧭G�����N�p5�HB��S�p�]8���2#u�H��-���P��)0�x��i���ːʄ��JH[f���F2��./��
��JW���D���Jx�-<*�;p������%����`n=eC;WXxwKq�ݺ�9;�R`	q8����!�Ɛ
*a�٥oqE�xc��ό���tҺ�d�:�Z�.t��O1����S����bn�u�2�I80����`� -^��~@�U܋�9(wez�Kc�I�壭���_����[K�9�_�f6�"���O@���.�,��L���{���@T t�O��9ҹE\?K��k���3w�Fr� �tK�^o�.��KV(YP�V:�P�(U|��P������-H��\rx���#�?�_�xƍBT�d�6"\���0�P:�ğV����q�	�l��)	zCb�(����;�# B�wT�X Ô�W��-��w�rJ�f��ߍ����
���0�zq9o�5M�Q��$Ɇ-:�����.�^/��ˉǯƐS%�ܴ�۲�迾��ƅ�Ko�+�����CՃI)�'ç��ΌG����'���c�%��=���>C^ןu�o?pf��7�L�������E���v�j"2��ɯ�<����w���{�F�j�R<8��v����6$�Qw��+;r�+~��r#)֥��2�R��=i�J���m��j$�q��9Ȼ��ͻ�U�L!c�;4*䛠����l��+Q
P
�L�r`���I_8k�%���[�r�Ţ�$8iFo�! �����O�q�_�;U���mZ�+��c�s.��.Ѣx�XZ�c�].2�t�0t*�O�zD'���OA�G�������-���Ѳ�d6��GᲹq�Q�{$���.�Wj��	>��4ع|h��jX�ˍ^����b+��3�\������Z�"|V��
"P��@�,���	��V;9���ss�Hi^`1��h����7�X.p2O�J���l>�8��ν��f�o��0ĥ`OZ2t���Nʶ�Ra�+D/�h��O���J�������n��2d+ř��Z/į��L=�L����2xZ��Sg��f�ԬLI��1E:5�{��VA�fx�P�k�z��S�ҥ�X��ͦ�R�17��rn� yU��<�XǑ��ϏD�Av��v;��#ht��yt$7݋�!) ��h��N��`�
�2mu�Bq�蜑�+6 ^�uEٷ+&A~�?���ow0ӎ�6���w1N�hr<�A��J}iC:�������f��v�nřYm�AHREg&���A��	�&+<к�/��t�N�U�b:`�4v�j/U��%E:pTx�<��J�+�16�$�E��ڒ{�\^��
�ȭ����Ԩsy�����v������V��gN<
9�V��1��Nkw0��gK'7��K���:�� w6ᄻ�~b{���G����k��<��K����Q�vlt�1Ԍ�);b��c�
�����.���"�Z`pl�NMx�7�T�����b)u��?R���޾4N2C�RxV9�%�M�y����M�^~��)<T�3Q���:��,��zA�]�r���r�p�F���2��E00����F��`�ױ��ݰ'ɞ��2OH��'�=�Z��-)��UE)Ց��������:_[04�G�=FJzo�X6��g�WA����&F�������r��+n:��P�4~S7= �J�����:辩�Q>��"�,T삵����zѽ�d *�k�v��Ű�'��S�Y@�%l�V��
��늟h= �N������y�H��0�W���`���]!/~���1��:���:�Z�vw=[u;.!"u�>lJ���wYy���9�ĢpYY*�-5�x�m�\�y�� f�M��.�������`�CB��~@��S�";�Xm��j�:����1[�rd
t�bH5�o�i����Л`K�RV�P��J�_��x���)eP�}��9��%Z`k�;�A�PK3  c L��J    �,  �   d10 - Copy (30).zip�  AE	 E+�T ����	�Rϲ`B&\Rtln"���8�`s�#	��+�N�mǠ��e{>����\ы پ#�l��_}�K_$?Hj����Ȏ����(q�Yw
�xN?���ɺ��U����q�wkF�o��ls�'Z�`b nj�Cܫ=^�[�m���������-���N\j��Fh��#�4�❔6������JPG[�������5����?�ފ�2�C-�!r�?Yf�ya��7=�w����I����C��1�O���ɛ�.�%"����;1}whâ�7k{�����IB�f%��I�%OP4���	��Jm�~��IgWK����g��G{2o�NbwD�^����h�/d��T�4Ve�An�IGfJ�LK<@bYc&�q�Z/�����1���Œ�kSE<��H��=���QHT�sd�j~Jx��i�=fB�l|e�눩#̭X�fP�0`<���0����i4gv�ΪJ�lFƁ�};6�hc�BZ�������)�;Ae���@Nxu�t�M�Q�"D�����(>);/��޻��2���_����ȩq���HW�-�Qpg-���������5��R��5��@->��_�?�<���z��
���Ƹ��(�� �u�Z����{���L�Ņ:l�R<ZB��H�W,.c�P[�(�x���<�ټk�Q$i����@����b�D1��9���][9�7��J�,���
�a{��WrJ!�jl�ݣ��(w�
;լ�2 �1�m��R`ѷ����n��|�E��ixV�ٻ?����^W5���F�,�r���e���(���?���Vcm����R�)�ţ6vc<B�ӧa��1ݝzS×���c��Dd���e���I�	%�>_a�T` .+��Z:]�G���~�;Y ���u���;��T�:�5�{8�~Z�)��:o��a9��l�e��+�f7Ҳ��:�mk�=/J6z��a�#]�xq�w�W�J�S)��!
2DѨn��4��pD����p�����*�ۥ�k�����C�G���?
��c�����E5�*����'�B]��?�U�\�����!���_���827kaf����.��!��A)�|��?	fv��h�f)y�(�%t�&=Ey�F���H)WW)B.ɀ!?S3�mju�K�x���u'd�a`w�� �z1�a�xJF�*9-3�g͒<�L�C_��;+VD�]K�0F�rj��ңHl4�1q߾IX3r���0a.C�c����sEt�����XR�p��Go�י�Ʊ�v�?����kBJ����۝��$UͰw�q�Q�q~!���Ch��e��}ژ�v��5���-x)G��)ْ=<�Q��48P��5�)��Dr餇D7��ۢ]��q�ք�E��V5��ML����?k�|4�H��Β�3)ƭ
3Hݵ�9��o����g��Yz:pT�`Sr�:���d��0����;�P�vl�?i�0�L��8�ẛ�gh
�P���׶O����O?0��<q:@zs�+�av��B^A��p��z���U��&  ��[���������p��';wy<ۻ����80%6�?�k��[,3OT��pwQ�ƥ�"=��W���8��I�]�h>����|"O�B5}I�;�)�BLOk�rv�%E���f����
E?2jE�Q�R�t���פ�UQ��ӱB�6ƌ� ��K��*R�F-VX`��`�8#�2'����* +���oq i�m���v�L٠=���W�d?�{�C?��V�@��VFnAY%�s�V��k�x�����k{�Vm�!�i�X���؋__v�	�z�(�f�k�$����Æ�@C�S��q��fn<�B�Y�|�Y�r*�z�!�R��]n��Ġ��#���\-3�/��3��m�(���!�?����`�̯
B@�@�|OH��bÑk�uH��C�BMp��H�:PV���S�8DT��vv^��_B2t�>���f(����4�S��?&~���_�t$���8���Sm�d�!j��Σ;���4H��4��2��HA� �>�-����Н5]�3�Y���p�56�a��b&��iqΊ��C�E.g|F��f��P��=�16���\���`42al;Д��Y��ݮ��3��;[]H�qd�涠9��k[���\�O�6�Ar�S���u�L�z��d*��"�x�v����m������[���E��lz>��'&tC�O�Z/��ϓ�IA#�Co#?�"����3��;�i��ͳW��T���^��H\%����7��k݊���y'BY�x�t�+!�@������?���������C�;LE�X�ϕWMҟ;Һ�sqR�T�V�Pi�hE�ˑ�����[X])�d1�.9�$
2֦F!u[�%�7l�Ў��L%�ܹ00%s39 �^9�Z��os��@6l���t��
��a��l��ʌd���3	�R���������ֺy��cS:��������i�V�����D���F���>�� 5Z�X#,�s�B��;�&�s�5!��D6/�z�,�L�	���Wk�5�kd�'��]�2�~ �<ei�Do}i���F�76W./�N�}'q�b��p$t��!��}��w[a��í�<W���i&�a`�}YP|I���x��5���y�uh���H�ڣ.��8�2�}f��O�~�����떹��������/I�ؙ��1R��`��/�XE����B]���EU�.��Q�p(yF&8��G������RΉG�r��'��˥쌢_����m$$�}��A���S9E"���D�U��sn�S>ZxZ]�I-mb��`sc%>�6��¡��l��I��:R�07:�u�ʱ�,e����:�C�\E]�g�Z1��]*ѳE�Xa�&a��,�)w�c>D�|O~�i7z�WQ_X	Q�Yd75' 3/,b�d2lO&�HF6�I	�	�-�,^����LR5�XO־~�
�s�5��i2�T˕g)�Ӹ+vD�n�i���^}E	�����q�����Ú���|�=B3x;�0Q��ۓ4�zw���	h+��֚��aF!܎���P�Ȣ����9�.,Ì!q��|���L��_��|P2���O��t��np#ҕ*hv�Ă���΅���WxEcx�#�U�m$�v\*T�d2.���e|���Ǖ'I���1�Do��d=��&�5�����MJ�U�o\���Tz���yc3��R��_�'��� �q������I^��^+\cNl[�"���0��z�f���τ����'Bq=����C�s�?�M��)]�ya������!6�L�k�/Ud�w�DU��EN
N�^uMg~ed�]���n�a��n<��m��B��U��ߗ/6~�<Q�����/���E�l���z.�©+�<Y�b;��� )��b�N��
*���Y��E�m���
�����8$���]r�l#����Y!�H\);��_�UJ��\h:�c"���iY���va;
j#`���߅`�B����+|����gP3a#�WO[��t��s���OS`h�|d��Y�d�1M�������Bx�3NQ	���3^� ���k4�~�H7�o|�����ܓ��Tmb���b͝��Zs�Fw��X�1-8m�[?�p$���;W������P��$�s��T��Eb=Y"��[���ڿ*�*�;[W��vP���'�TA���YS�C��b���bk��>��/-�\�,q��bz$����r��=���<�Ɇ��Z�<����F{����p��Xބ+�6��	�*��B�c�Z�bL��ro�.����Kǌ>�}�\ˮ�>��c����
�g��IAl��ǝ/}B��~Ddcu���1ꥁ���f�΃nl�J۾����+81�{F�s]*���r%���~��c���u�1��m�R����Ao��� ��Ik_�����[ݿ�}#�������Fie(��譠m9�=�c�E�3�N���?��]Wg>�8dG�d�>$lAC.ѕ�
�o�T���U�m��������	�\�o��U%T��ёS�i��:��,��͠�Ҏ)�|�na�ŲGy|�K%N�"1A�#3��vT��5�l�[�f�wh�D��uX�_��-�Ⴊ��+�z��S�se�_��=h��-��V�vW�u�l�D��s%��ƙaF��~���x�ŋb�}/����q�� -CI;&��1g$/�����6�:85�7�g=�އ����!�j�3��r�:)��sm9�� ʳ���QW|Q�n�v0�Ot�;��-bE�';�lm��g�\���"8���U��6 �Z�z�.�z_��#.*�AEP���nT�	��k{L#����Ήh���uY׌���d����	�)ӟ��3JwXO�<ʝ{X�n�|6���M-�c<"R~_��ɾ�>'B�v�Y�d�q'��c��(p�={�B���Tˍ6=��}&�K����m�x�/���N2��r����ʡ9�j0�u[.��Ù�O�v � {]����n�U�]�(�[GϮ��ϑ����EɐRO��
��p�����w�R��ߞ�M�fP����h�����fME(��Gn>�� ]Pf6cA�.�f=oݮ8���N��
�W'R��U�8W��9;s�v����=�9B#���}��m),��wv#�S<h��9M�-�G��Ě���ʕOz
V�J��QrÒ�
4�3}�n�w\b+үfB�q��n�����j2�C2%���b)"��;��{�C���x��' D)7!/�.���==��hE�$v�g���ڄj���u�p-����P�q[j���ۃf�a��1�|�[%*��޹���$���sMCh��V�}��0����GHqo0A�e�4哭�{����ซf��d��-������y��k����;�8�d���hE6��5���*�P��Wpf+��?�0��?x��)�B��xs���
~����g��/���bz�p�",�Yvq����&�y���M�����5%Ru��1qp��I��
�3#
p�{Fۅ]e�$�{��I�{���졟҃�l�<�o��S x	�����Q.��%B�+JA�,�Y�w�Iԑ(�����E�N�w��g����	���Qv��J��~ʞ��\���;��^�!��K�?�c[�Y}>�X2$aBVn��1�'�c5��ީ��Nٳ8�g�鼺�H�0J����S��Oc���ٝm�>���=lMK��Vo$�7Sٖ���������+	` |zQ��n�Er��>h��Ė��i�y��1��+d�H�o|�&T������:�/������<�������)1�J�r�ug
�����ߵs+t�@�h�<��uم�>��f����4�Jm5��fU�U5��6���@���
���E�s���) ۪��)C&[8�
����m�)�yw�
Z�v��vE�u��] �0�)�
U���(01��ʙ�K�s�`�}д婺'�C>:�7��qM�i�\h[g?��G���g@��L=������������qX1R��v3=����C�i0�Vt��F^�QG�j���@N4��h(a�7�1\έY��\[v	ox,��*�>��2��:�t�]$"��jvB�8��=#�V4�~Xք$�)�����[�?~P�ǹ��V�鈭ࡄ���ӥ{���{�'7�Xh�D6����_�eK��<��~B��/�vv\��E~��ܰ���QB�3��d�3�$�g�)jL���1=ܦ)��E��M�I�ʣ�0�[p�k�iM� 
�q��.\�xC�'�v#=Y`��u��k�72���.=�
OQ`���ߜp��l�%bB���$�,P-O���_k)u��+�Z��u�=�F�K���  �D��sc�,M����Js�J�P��)K���=;w��F
�J�h�۾9�P�q����i�����p]v0��	l�T��� �c�yߗ�z�?ׁ{F��z�#�в�����~�qv^"E�[/��@�x�	x���P$f�WI��<S�H��E����u`fuu(k��Ck�N��2.*��҂yJj �}E�S��Y�ӳl���P��R#-��}`���?zM�Y=5�Nm�B�R�*�c�S��8�?e��]��V6��K_��q�T�F�C-&�6��5��
Y�k�dr1�|^�ya�N9�R��~	�����E��$98�-�����{��5�ľá
辷���/�%T-�Iqy�kv�J8w������j�u�c���k�\˃JwB��B=��0MS	^�~/J�ex�&�b&�h�*Z�{��?ye ���FU��C
��Q�"e�|�l�4å>_��0�kS�sX9��s��$�����r�͓���Q� o�qա�
�JQk|=ku/;z&-
���A���R Aw=���9 ������ڀs��5�Es��i�l̓x%+�Ky�
 ��V��km }U��
ה�gf���m�uf0+��s`����O��-�P�d�d�E��؋�VV5�[�R��\�H�s����{@��(���H�p���sV��Y�;�[s���Qf-�t�n�ͬT��Y��[%.���,d�>B�]�=P�U�1�b���� �J�D[����6@F�Xn�l�Q6E�m]/�^l�qہ�j�[�����/�oT21R]ϒD�x�� �lR>TK�`fE���4�)�˾����(k�S������C�s�"2@:�l��E���A��B,s�vE(���V*���鯼ly��a�9�����W6_�5º{�M����D�*^7iF�ꓳ��T�ļ��\vrMq�|��#`+����_��+�BV'��E�jھ^!�	�&�� �"<]:m������dy�I��7ل�
;<"����~t���Q�����?���NG����ß�y��1�N�.��2�.Zok�1�řU����n��k�Y䬫�}k�O����e#�̘l)tYHgT,�V:`}�T蹕X��Z5�I��n���aX(Y �\�f$�R�L�K#08�,�J�e�O�}�U�7�����"OK��M:��՛�,�����g�D�����"c0�H�PKK��|^S�?[�^��n"��~
�7�aF�T�Q��0Ν�c"ܢ,�us؟p�ŕz]�^1V�U���k��p�B�-m�Cc�Н�>_^�9� TZ���8�Z��`��/�]韑��H��Py�/2��nS���^��NN�d��z7�B��$�hz�\:�u�a��ܭ�fHjN�l��#oN$kHf��-Q�N����/�-|�(�J�I����L����f��ָ�x�:��g� 0�v��/D��6�����q��.~L�y�4�gԐ���y��(����q�= �KK2'Y
3�5%��b��V�'38�����Ih�ظ�ơqסs���Z�"�*؏���5��i�y��ɣ~w�XA3�q7	��uU�z�=��?T����,4�"Y�m:�U��R@F��_-Z�N�j�^�x粔���^<�����=`S��WЌK���q!��{E(�QiG>��O�<x���j�P��!I_��Oj����U>���%I��:#V%��/���)��'��oPF���S���1��I�n{Q?i����>��(��Q_��U%ep��ϸ�S��t\�t �|*c�H;��|]�D��R�h��IP"	�mr��5�0�O ����R��~�!@����>Ʊ_TJ���{ΥC�����B���D��]{� $ۣ��x	V�Z�Jl��E�#o|5���^�gq�jq� GX}��A�J�Qr��>�U�MO0�0$�r����{�j�H�c��9�4���`<��&ώC����cDߊB7�Q��9P��Q3��ʃ��a�B�J�]m��W.L�����-�9J�l���;���G0->�)B�3�?�S?�Ze��)ǲ�g��%EN��B�V�f�<\Ń
����p>ϧFo�o�H���>���W���):Ac�"�r���
wG��|�)%�V�6��|�˿��P��)Bn�$E�+qL	�nAUMܩT'D��%N�!8V^��N�_tQ��������>'yi��OC="8&M�Joߦ��T@���kP;Dk�v�0,�(��?ݘ�
8P�h74lY{o�V����53��{���W�"Q�^����o?,N�$g��6~@���ˇ���d$f���k� >�2L0�5f�>�27=k����OZ�%�r�ِ#V��u}4�2���ᭀ��Y��lVN嘣9;#�BwA.�}T+�:��w���gX�2]�+^���	��&mv?��Db]� �؋4�<i%cUzͱ>wg�:�+X��̕�=�P$��xD�^��h�9X*B��F]bώozn;�<����Ƚ�Ww��jhyu�㣭vL�2H]��~��R�cǬ���T��\V>�(e5�m��a�����\;l���?Dh��1����P0��T5<(����D b�5��>g�^,��8��!��Y��o�$�Ҫ"��!��K�3�nِۏ=}hl\��e�BA��Eȝ�mlRn/�߫`��'9�������/�����ľ\���;��fv9�Y=όн4�ؾ餰Y'��8�D�'��IQ4,��N����(t�P+�%���o���XF� w������}�S�����h���Т��7jK	�@3��3�x�Q�X4CҸ��H�Α����!_̻,$�ko�sZ�.�`��MՕB�a����t�(��/4&��x{9���M�� P_tN���
�H�~h�Ə3��uL+�Y��:� c��7*���'�,��9�:7�ɠ�S,����EG�25BXBϽ����e����wၝO݆�2��v�u��{��nzD��Ts'mK�� �n
��7���}l��1�N�G  ���v���Ȟ�C$��OI�n"?�t3�M��X4��_����Q�b`BѰw7�"���k�=� �fGON�حK �-��,m.Y���*�<F��V���LJ�Ȼ	��*�]����f�e������9�П�9���b#7�)p�-ro]�D�/�� �Q��3�l�Ug���X�dh�1b�hV��t[���=�E^d�MO����?�`����&��r�&������f�-Y��(m��)�ޫ�<"(���b��g���G:��M«!�E�Q_Y��
[�IE��\wܐ���P-[��ѓ�S�mӴ��ҫ <4
.7�Nw����#A���4�`�9�@l���{� >��Z<B�i���Z�p'���Ͽ�y#w�������DqE�	wRd�̮I�M�+�2��}&�l!�]�Nh6�#�`��1�+�$|<>)�*ul����KA�V�c1Z(����Ȉv�0 ~g��t��Y�B�" ����Q� ��s���e���L�ta-|҃}*O��/�j��q�,q2���+cb��.�7����?��� zJf.�* ;s(��F��N.u�U�����C�����7xX+�Cb2����s��E7D�6{�y H�/��`��TS2�whx�s��
�F��xD
>�@jI�9q�Ll����><�vA�O���ۇ�R�񸝶����Ihq���_?��\ki�	� �7����:}�ё����B�R7_�o(3��$E	���U�jS�����LB�d�$W�駛�p���~ޤ�-*h$ ��D�dؘ�s�>
R��W��'���kql���D��<�Uׅ��s��Miٔ%��KVKE��8B=^l�aև�}�̍.3���d��G��cV)�<�[���YS��,q�R�W���>�#�&,O��Y���[��(�*d~�Uf@s\�O�˭�#�F�#��Q/�Y�L��)�;SѰq�gآQ�5bDA�|��� �&�I�IF�����U���a5�t�w������Y��D8s.I�ӗ�? ��G'�`O�I���J�~��+Yx!����+P�G��H���[U�.��m���.��i�����9���.=C�p���'��"�f�o�� W�꺕�\mnq[)*u�яģ�1n,/#ʂ.��v���6C�@���'�k��4H�q�;?�U�Jm���\\['��zx���Z�=Iy�Ѥm=f]a�k�?�x��ў�kwbe�5.�H����Cu���_���t4��\�iB�^7�3�����Iu�\ ��j����s��ɝ�����o;�D+�H%�k����|�Y��)t��������eO�%���\�����عپD�6��*��28?dcn��I�<�?�r�ȯ@r�Uн�s�>bt�y;7t/5���z����ʪ����Cs�VP11Z�Q���M����rz!��G�Ӵ���&�֢��e�`^cS���Iɸ�e���cP�;����D����7:ưUlY�A��E��P@Q
��
} རWa�2�[�\��%f����]�=�������f�߽/�"\66��V*.%�M��ez�&�{h��lJZ���B��p��MxL\ϻR�m`��ԈrB�L�x<C֫�:�W���ѐq�j���F�Vq��<6�Ͻ��̀�:�H�#$$�<7%l�kU����	�%��SNH�o��/L�Q�0Y�T]���	i�!A+���єU�D$�y�����.>�5��1@}�a>�6���[�z$ot�5�B�R��H\0a�"��(�jI���	;��CD�X��A��R踫��iuB����1t`d��/=㐞~ڑ�9ζ�.�r����_��h���_���֦����S�*7AH�����A�-,r�c;�1m wq2��)e�0]m�?��:/Ϩ���I��]���ɒ����	��c���\��B(������n��@o�ٻ��(y�ʯ� z#����(�a/aNEQw ����	_�W�X����`�*Mͮ�H}�B7R���u!ѺV��P0�>^�Y�Ua+��eº�.}`�^\�wm,!|��5&@��F U`a��mX�L���H�5����C$	7gwO?Rm���[3���W��������W��.��ˎ�>����A"�Y�א0�-��[���n�L�lU��r2
/xXR�]	���?:w��5��X��p��9U(�m��J%��� |���l�����,�*:r�0�x���'蝂�a�v���뾭k.4¥�������J��Jd:���PY���i�6�Y�n��jvORx�\��S)�6oe������'N������Yj�f`��jj��wd���Ѳ�<�Փi�i����P�M��� q>��'��A���ya���E�)�{��ť~���@�O�>�������b����b	�q�{yl#,;5Х\?������e�;�*���w��\�z�6�mPK3  c L��J    �,  �   d10 - Copy (4).zip�  AE	 �jAÕ����_�?'W�~����:\>j{��G���W>QU^���H���~-A��^B���fh�/��p0¥:�iT�mcL��-�ٝ�L�U�LzK�%�J�ZJ3��	1�����6�8�X�5ܻ}�г��)�.��Q��a���)^�f��˂�&����n�a��w�7!�~}|�^�4�`'���J�
f�UuZ0�� ���ީi��|� ���e�ZX������S�+Ύ%`���B�����I�&����#�4��~/�T�RDH������^���\O`�"�ߣN���Hp3���P���/g0������7�R��I<��k��e|�XX(���#�|�A�]��'�`�i��!G��ϵ�Z�
�z	U�����M�<�BkX������e4,������,��F�c	��#���a^+���_ʾ̯>zb��	#~ȷ�RSU���#��P|�ӓ��m�<gJ!�/VgK���z�%��X�G���[�o2�q�)������Ǭ�@|AP�4+��w(�f���j�rx]��S܀Q�����M���u	Dv T�(��#3��Mj�]��;}�.��ҷ�ķ^V}�>�x�U��-ƈ$�s��
�s����lyF�],.�qe�����#r6h�~���;���.�;=����%(vu��[_�U.�����{C���×2���3�>�<�|��7�f�{*�DS0�R��u�v<ہUxq����e�QA0��0q�]p��x�����3v�/��c|�M�V�G�`���el<��	���/X!���r�M�����	���aH�����s�h/�_����XB�E��Y��� �f\����?(س,1�`�^ Ŀ��6>4T;���6��k�$��f��r�+�3W.����Z��P�k���F�
p'��#���������L*�	������ꎑ}���ׅ�9���quD���Ēt�	{�H�ЀX����2_٤*��%���β�xk���@ܤ*��|�r��c1W-+I��d�!�Z���Z߈�C�ꙉ���ˀ����@|3s@���o�Y�½4t�:��0�W�˂�z5I+
��R5�b!�	;�W�5��cz��~Y�=��n��[�CWf
�G���5��X���㝠K�n�b��<X�01�c�Ն���N��嬝��Vh0���F����Fb��7bLxZDAzѢ`�S.r� �O�Nϡ�VFc��$��F��uϤ������C}@a3�䞋��+�HA|�'��6/�"�!NW�������yS�&�V��!b������PS9�c���aB����{x���C�y�'Oac���� ���}�hX�K`p }�oK��M&��v�_��#I�Q8�Bvɚ���s�O&c���_f~�$4����*
�RM��xCHA�#F���v1���oJ���Z�(��~t�-���!�0��T��&����(�������?�J1�ǽ|9���a��u_�]E���&��#�Z�n����uï��SO�k8���d~��ۙ��<0��>��R�c�H�𯭬��1m��XNq-_������h���S嬴��Ҽ�����m-��|��R��]� c U��ز�Uq����,�p�4q���F���lg�f�VX��/uo眜�@�Л�-�vi��_�ĒT�!�;5ڲ����Ŧ8ׂ^&��ǚ#���Q�F��ݤ=�E��y�˨��;2$��K�;v .�!.��>��Bc��>L��t���]��٫�xc�`7ʐ��y�8G���l�V��701����+9Z_�+��JC�\g�� �k(�!i˟���~6h��|M�x��e����[h���n�J�JȊ�^I�D ^�mQ,Ý<2���o97@���"q5�0�蝪`��!5��RUC:<�f�ݿ�8��V�1�Wj�x8�|���?�<&%�?ߴ'�Y#:w��]ݲ��i �`���^P5���������ld�ǔ�ǫ�^��1�Y�C1[��s����y�j.�z�F&�#F2��#t�~6.�(lex+!/����i��x�)d+�J�c/�_i�݊��l��NB�2�m/(��r�򽞘;�$�剏���t�xyA<�Lq��.ȗ��6J:����(�2�(���5������	Z�E�_���n%#�+��*��nW.H8�/��p�"�3�0�?3��wV�m��|���?Fꌤ�'df��>d���$A�r��o�����4�P�`z@1��ȡ%�|{��;�4�[8}�	�9���:�/���m/��yH�6�A�s2̩���ӕdwN��G���C�89��B�]�R�6ˆ�!���L�`�YT�X�@�_�㜋��N��q�L������B!�\��c�4�c��iǻ�{�G|��������T1��ž�ؾ9&�_֟����U��0?h��nL���珆y�C�[�:�M�V��I��-W�қ5��7�m16���H��2���ڍ���$��C���ui(1BOJ�����7\�Q���hFyOt��L��5�Zя�I�Y!��r����T��8Tj�A��eOv�ϵ\
1����Ȣ�{=i��4��M5�A Y�ԅ��+Z����c'���&�-Z��a�#�����;�^a�J���Xz[�:��YM廿��,1tH�Y8�	e��ۅm�_�䆿+��HMt�:��[h�c�wF)f\ؔ�#�=�!`Rć>�PzG��M�{�PA��(�	�R,O�-��Y�T�8�=k�g��䙀�.p�P��@�+�U���AB�3l+{��j��]w�QyoS�=�T��a����ᵛWH/�˷;��y��%��UN;)fwF��O��عG.�[3���7��'����Ue_wh%ot�mzp������C�΋���q���2��ڦ���\���̮���[H�������'V0 '�a��8��D��C�h��%g���Y�1�b�@T?q��rB���SC	i�����������ܑ@�F2!j�=�`�e�PT��Z����n#��TQ]�i���~c̜�E��5�E�|��z��v��4�MM��4�N��=8�:���s�y|�����`��(�+��C4ӂbS,�>�uܙ�P���30t�-XƓ�buU ��)��?o�����5��\�:l>�6o�{d���H#�s����i �b��|��l��q��:b̔o����@7�b{4I��7^8t ^W����mK���3����:�Izr�G,a�����9��L� ���Ro�����_�Ј�X���	�	�~'��	[Sa�|C ��Oj�i��^ˠ�F�z1��g ��Ϙ���V5MJG���D�5�"z�����븐�۸?��*ZJ��,^ j*�w��>�l���d��9�CR�kg��:9w�u<��2	ف�	�b�|���c6!8v�C�ٳ6ޜ��p�t�$����d_豌n�/����ҩ� |�V0���2�e���,l�
�z�������L����^�	-�����h�������
ǬmH?��
:�R��"+�}��7yA>
��hm��b`�e�3�s|�h�
.݆x�֋��[�"u��Z�C '"a�q�64��FMi��v[t�U+ҳ��$}A��Klb�$?���"�	*��7fo
 f7A��������Lk0�N~Ǟ���-�|�J���|���EG망�5U �O	O�sL�, ����L������u���RK�}���O�?J�x�a���=�pk~��!l��%/��)*Z�)lj��J/��3� ������ބO���ل� w�wK������O#��+��8�Tu�-��4f�ƶ��|�~��9��,vV�m���ї�|�:Y嫴]�0!'���P�7ٺ}�
��91�	��(��mLQ���B��}�_e^�0��S+�n�B��֨�[����aMU���Դ�lH��4��𓏒�;�
,,��C�:x�C�O e3RPƪ}�����Z̢���V��%�Ð¦�0O7f�}�Rqi1���S!���be��V,�xcb"���W�.��0o����<͈���0���>��t���G&��w!���gin�ts9Z���>� ď�Y�*��R^!�i����uU�kQAs�����	�M��Lz��E�U���$�Һ1٤���V�}++P�7䛳6��q����2Zn���8c���է#�Ӽ�r�t~(�n0�8P$�ɪy�����~�Z���;"	1�eJ��#7)]p�ɍw�K9�THw9W�qq16]��CS� ��θ�rЅn2enை��a��h���?5.��k5�j�.��ܺ3�ˠ ��JS�fa,)!4b�k���=m�󛈿�t���u�L<��a���
��[5�ܞo�T��1�)5�֩\N&i�Dڅ�d?�fD_ �+vY�SE 
8�~�k�Ĺ܂�:N�7��o#3e7.�����iz�y#3\���=��4@��9�eæ�y�C��XNh�e��?��`�ϴ�]f	�и+�H����B���a �ݾG7nm�ڴY��o�=?�87��;��\s	�i�x�m��h���6z2�����
_w� �۠ٗ��Q���`h�+=�g2Ԣ��4����t�A�^R�]B�D��AH��m§���R2��YR��GA2�!�J[�����7�i5>J��
}
w��$����bV
�1"��J�-�r�] @:�̮w9��X�ף�F��T�����-��5�}��4�!T�-�}U��%Fsh���+[���6=.GN^�]���l����Ì��nJDMs񺐅��w�Bej�Y.��������C/ޙ��0%���Ƒ*�ߐ����/�a����db#�#O��#(2W��5�ܹHߴӌ�b�?E����/��_?�xR�����G�l�~�0�8��2�I�kl"v���̴B�j���$JġP~��e�rd�)�2�)��>S[�V�����B�5�Ï��%��
���_�c';�8��g�q@҈�K�c��p��7�D�]�ru�Dwm���2I�K���j���UQ?!UqQ��T*,`\�o��@�%ܧ�����B����� 
"�V_ �N�b;���r����eϛ����+�n�����E���͍�^R�9��֑,%EL@�p��>\��4v��2��Nzwā]I�Й�!������������F�4�o6�������ۨ�)-���]�=q��-�?]�Hp��hIX7�+}O��Cb�$L)��Ɓe�m�DL�d�����S���2f���Z�t�����V����;�`��2<&�ƌ���Z �I?�y��j�!I�$�-0�|l�{ԙ�ÃM5���ǲZF���yr�ΐc���S<|P�2M�����Xf�uá��'(e�����y1}Mc|�S,.�H����F�Q�d�jc|��*q�| �k���1��v\;���r���M�:;�������mϫ�\� |��*�l* n�Y�(:]ՅH�3�=�(����/�gD����tsY�������>g-b�@�����CS�y�E8J+��+��H����&������m���%�b�uz��y����H�O���s�S�=�e��:Ϣ�	(���}�@I���2�Ɋ��<�Ҥ�\��n�!��_j@'R+�y�0I�]��ڱ���i���)��P|w��WQ���Q����`�!c��~_��RuD����8�"Ƈ�'������:I��^/�ߠ4`0���3�4���*UXԗ�����?�yZ�#7x�q���1(O��Gv)8�����cµ�O�k���:���d|I�g���r�����L���Fu������Ra�_B���ߞ�v��5�$X�|��L��?����Vt��.��4
���H��=�}�������)_�C"e�I˯���Ϧ��HN(�4�3RE�����C#K���=��m*DVbG��f�;W���_uv毮`�
�}UH$�rOlƂ�Ias���@!]kV����]�v���B�~��n�~Ή,��0��1���� �N����P�K$�L�Y$�j�>��0ܫ�|��z��tHx����=���w9�/�{��jK ��	Ɛ��7����݊�@B��A$T�\��U�n@�:"Q�H���kΰ��䩖�����	��2~���1O�c�s!-7�vD�m��0t��|EB��g8�w�K��R� ��A#��L~k����W�zJ�F�~s��x�_�LHr�a>�ε����J��&
��K'CFB(���J���^����yi�6�H8�zU��Қ��` $��=�R����r�r�>�+ |�ٙ���^����E�~�n�`��7���sR����H���-��f��Pς�������14Ƕ7$�uoo�m�0��jk�sg�=�<�!�eQ0��c��ć��A�pL��R-r���H�:$m��vtQXF���YE�-��'�m@��s�+��?F5?�}b˼�����D7T�8��6�қ>���d�zI��-�2�SHN���UE�.��&b�rs�ʧؼzlᔖ|L�&&ݧ�+��/�~p}*x�p��gψHָK�G��>���,�q\��7��Q��}7��Hmz��b�*%�X��yo��[��	!���[���#�8���d�Ϡ�sm��:�ˇaSxm�H ��2C���~��VԵ��.x�>	�E/�"D��\�f
�a�6�z��t!�/+XC7p��]�W��u�p�d�!�Gh���Y_���vE��KonZbƀ����]:�2c�Յ���<�V�K�����eu�v^\�Cؒb�W���V>Z
��6AR���&�rQ4�n_���\Np�m�P��t��.��0�	D��������|�`0���y;(�F�v%.���n��m4�o���k��k���ƌCa �?_��"�(z�~����ϊgE�%�ar�O��}{��*3n�������μqg��PGE4\�������_=�]���iB�M�W3����^2ͯ�xD/�<ҹ�ʋ�{`w��_�x񒢡����$u��l�.`D������9n-�BGS�F@1T��Ejt��@ߘ��ϖ�9�8gxna��n�l�)�����2��,�M^��Nd�c�_��3��>` ��>���̓t>剽@|y�\7���x�{y^�CxMY��~L���^�}_����=ͼ��fy[��ݥ.Y����o��
-����j��1A M�9E$X��+�h��ڡ���׸�c3�����uT76&/��g%�PI8l'��B�k��^%����{��&g�Sؖ�C��ɱU�_c\�&-�������<��e]4!� ��R	T�ǘ���!��ѨI��05)%)����c���6����.�B�&����w��:���PD�+��$6`w��Pb�Z�z�`j��5��l�P���?��/)����rߚހ������D�Σؐ��W���x�g[��$C�4�I j0�b��1�y^ w�N_������k��gں[�t7�Z�U.�MM�	g�L`�����:�R2�B���wT���W������c�-L�b���N���r�C�N�0#��ݰ�!d_]հ���Y��:�O}ſ�D���p`��h�. �K�U��h�In5��za����1
��ڼ	�u��I�ac)� 6�>�x�ww�� ���:a
�����]<�CH������9��E�r�÷~%uV�I��FR��	��oz��䡉HK�"ux
�GIm�|��D�r;���Y�(��t�����A�J���k-������c%I`��5mԊH�; W��� �s;�Q9�<��/M�1���?8�$ez-���`�+ ���y�����B@�la���0�UWY�a��k\e8ˀ�SO�^�X��Vc����[O�F�4�I3���5�I�8T�+�n�U��HR\�y��ʲ���� Ӧ���,��I^���ٯ����)�@��>�!H�M���V������ -����}˅��1�6�H#��K����^_L��� .)��&Lj|��2[F=��E��z=�ԋ�k�"^��p0�Z|&����+���%
��>��U�-�*Z}i�,}=�L����1�9���<lia,�˅��3��p���'��\�6#��]<�+�����J6���O,���D#a	����¥�ѵ�w\�=ɷxzI��笥��N����{�f�K��
W���ܚ�U���&IZƾ���F>���x�����0T�St���ϲ����1c��u!�u��8��^����態[v£� %��m¿X�d���)�<��-�J/Z���) p�?NV�GC�ʶQMd�o����=l�iU$��^\���V0��-�@�3⍀�n�^�(�)ܜ,��3�-q��7���L��蓩�C�ɣu��kT,kk'݃d���J`W������:;�|^ٶ�y)�^[�p]i�<�^cxrY���@
�^R��>7�a9b)\z�p[��ɗy�3'�j��TB���&�_Fާ��Ƒ�����1^G���:� J�*�׷Ut�FM�D`\8���� A�e�M��Y#o��Fi%��sEd��������s:��bl�|	����5���d��j�˄�F�a�_}�QH_�S��.zF�  ��%�
�}K��S!��|P�⣟T)��7j;������>l��=4�H�{���<u��|Lyǡ��>/�����~�05k��5\�5M�Ȁw��Neg�����"�3Y|��dFX#t��ʚ����P=�g(�D��gE<]�
�6Tv�M�GI&fp���8Ҩv�8�� c�Xm��G�Z/��Ef�i�����SU�j�C���pu��i4���>�����JY�~V�l=}����>��nb��a��U�3[�m��p���`���\�����U��Hx��"l	5G���)0�qq�l���<Pu���sl��Z�CT�IJ!�8SMN�WHhbP�rԷ�y��R�E��gU��{���g� ����VH�q�՞�в��ޏ	�$��%������q��ʣKq�2t{��bY���uK D�N�r���#��Hٰێ�6IF�c=��>�ę�����j1��k��䈉E�i�=�л������ۑ�y�B�h��$i�3�5�i��h�a�s:�ovD��=n�&��<��f(z�eK4�pmh-�y�~�h:Z�1@���N�zo{�z�|.=��m��E2�l��G_�O`#�Q�l+�4�:���\̬����b����W��������"�бe�⬃�p)(�u)��o�X�H!��^F^��$�5ǳ���kTG��յ/�S5���d����!B��F�Э�@v�
���y���zM�V��ɕǹ,a����-T����_�.�6̑��%0�����9��/~0%�cc:`B32�2|�fl�`��n,ٟ�\	��n�K_����B�ҧ0!�o����|/j�toL<,E��@_ȏ�� >35�s�`=Q�8;�1�e��6��)���:~yf��Lm�;�/h��Γp{B"U�28/��motz�Q�� ��y��Z��h_拨�\��J��Y,�``m��>�v��q������|cT ������\�P����a�YU�xAUO��_|�urR�?���Y�,$+����~���f���/���w�N�z�5��ͧO���[)��F`��U<Yn!LϜ�j@����}���C�NY��p��HO�TbD�����Pn��oo6p��R�H{���r\�:��>�Qg�WF"�^<���w�r�$E��c#	�H���e�8?�1�7O��F���gA�te	��$F��a40�k��q����:O�H?�T.��������#���B�|�W�=�ު
��B�h̢dTq�ד�� Чԗ�T7#\ك��B�#�������X��Q�L�;���!3&t�(o�!{�'�!�)�����F:0�� �E���*�-q2��3�]��bI/���ZH~����l���i�v��ig�Jπ�0{�U�àu�Xa�-M"m� BG#��٩h��Jǚ�����~��N��ӓ�R����W�^�S����^�2�O��؁~�&������<w�O� �g����٣�����Ǫ%K�~������w�<q�\��~�J�ŕ�1��PĹ^7�\�I�&�7e;���EN~����@�4r��c�ب%��M��C��G����0�c��� 2S�K`_nk��Q��Pp�ܝ��yvyY#�@�I����ف�C�n�C}���[���Y�C0lV��}�J�w��@7�m3 \	8Sw�(�����Iͯ��/hAK�d!-����'�n�͚�޷c��j�Bf�p'��5�2�4h:,}r4�zw�̶���ͫW�_�@�@r�L9�?dXP��Дw�42.�
ÿ?�����P�h�$�|�I��ë���+��[0 (�SП�?��Z��|N2���"�?�Y0#��i;��<Cm�ݱ2��:��Be$��|O�v^���6̥�i�@�Z9��%}��ȉ�DpS�C�Yf5C�t���Z��k5Yf2
���͵c�I�!��3���J�U�4����+4�.=�$F�H�H<#9������c���|?������7��h���q�����.O���%�k�a�S���
���ﳹ�p.���H���vP�!�VLaH��V7�ý�DY�U�[%�o��i�c�JAڋ�������im�<<����#Φ@�1��B��sY��z�_I�H�h]�K�G�� ���M&}�(�1W�y���	&���GO�ϕ��gU�U��-.D�)p!���LWj`�g�J)����;ui|c��-!�-֓��54��w�������8����8�kw�ϗ�M8 ���VQ,����K�r����Mދ�v���֏o�E�'|q��������y��$v����:)�37���ˀ� t�,�zе��퀷�^!'_K�y?�8���H#�`�D��|$�?���׷d��i�e�N&	�F�d�����9Oa�H���m�k�9OEV�T4h!�O���@ � �??�y��2s��Y�c/%�*UK��/F�)HW����XW��6"9�����!�|b�����.A��IX��'Z�d���\�"x*����/���2(��+H[}[���{$ d�Z=�9�����Ҳ	�0�HF�~j��^�2�d<�A�#��?�%q�V+�����z��=�p@��iv��	<����h܏�PK3  c L��J    �,  �   d10 - Copy (5).zip�  AE	 *��澀��vy���F9{k��IQLS��k������ۏ��BW�@�a��}���@�QJ-��QE{���,�����#mߋz#��5�p�i><��IE����]g�U!	�\�&�+��ȍ���TI{��b�I��<�rS�Z-�߬6a��v' �@o�4���`�k�w��?Sҍ���rM`�g��$�a��ٯn���������U��˖	�����0��)6#�l<��_�j�i��a�� ��Gm(���ѡ��{��Ib) �;D��S;�oD��V�,)XJ�ú��)�:�;�(�?����>���tｶ�A<cN�,�0fm����q2�j�A��}R?P>6��O��Gt�q�t�/e�d�!z`����n+5��=�ɷ�d���K��7�ѸC?#�2c�,�A�0�j ����g�u���&�^��LG~>�T?�7:,?!���n$V��UA�J�[{\4�ZX7�Bn���J}�C{ �'pLj��u\�s�
�T�%�IY�19oh`���22ް�n^�M�j^Aw"�����b=l;�P���]�d� �?�y[�O;�d����D$ �P	Q��{c�D��I�wfʑ~�9�R�?�������5-��6)C��+o�o6;��0���x��d�	��1c,A�og���Y�ڤ�h���d+�*(��D�H:Ĕ�W(]^bտJ���b��W+S�g��p֮
�r� ���W�Q�+���J��أ�J�3�]����zwW-;n���Eu]Y� ؓ7�R�m��^�1�alj=t;9�}���ŏ:Y��#��?K^���!M�c DY�'HL̬�AC{B�)�3��W6n�/���62��P��������@N0G�Z�4%� ΐ��X�������C��������x���3�5:&�MA=�����+zB��gW�5��@%�R�S���W"�L���P���w�LGgx�ҳ�-�}�4ݼ�G�5�K7�v7����Q�9�.=o���w��'q|�Ƨ�
xG�&��|ŋ)�Np���Iy���o�������F )��˷�mKo)vN@H�	k`H+�0ii��mhL���s5���u�H�_�t�_�LaST���Md�Q��("!6�:��� |��2��I�TkΜJ��K��LYV�X��+	PT���t&&,����T�U��d��j0f���3��ew�����R������� ������q��3
�H8w_���y����+6mq O�+��r&R9P�n�3���Y�q1B��2ɝ�-D'�W{����$��K7}�h<Ɨ2�����	އA�2p�'�ԁ�|��������4�g��?W��Kt��@�����f���"r��s_(��о�jC-��𵮆��R���z�>��?m~L��-�T�rK�۹�g�X�%vP��T�_��{�쯜��j�f#�� 7�ɋ�[��,3P�5rh���� 3���`�{Yl`�B���'�
�ޜ,���x��:V������]H9v��G���Z�Fv�������;5B𾡴LkD�ȶ��]��ȶ��+'ZE�V,e�a�9<�ԯ`S3�����9]F5�wD*��j��C���-��~���9W�T���D���Ú�dcѴ-�(L�B���9���f�1QoF���Q��,X�cF=��i�6h5�Ob��ŶH(�|�Ȁ��1^-ؒu���V��'æc�p�ܓՍ֘�6�s�j�g'��NU��/��[�]r# l���Vr�<Q�jǏ=��&7�Y�9�ޮ��ך����Л1ֲ��M�O���b8�x!R%adC��E\Gܞ�+8�[�����]��S���c��qhY+p�@�.S�z����/+�\�6�«P��;L�u~�h��]��$=����)�lV�����<))�u�Y�:ȭ"G�®8�K���H��v�����Rn�����(à7���q�!��K�?Ml�
��7w ���-�	ZR-)y=yB��v^�dd>x`���&K48z���d��8!I'��l뛢A\%8�K[����x���f��;����7�������RH��rè�D�@O��o���Iלo�p�%`w�a�ΣE�лET�2:��Z-ȹ0�]G~�5kF�ٸ@{�����|��T�)�G�Sm`��KN��3p��V�r(���z#H��4�E׎Z��O|]΃)�v�Lw����,��Mh�`r�(q8Mi�E-��/����9�DaHm���k��Ě&�z$����c<4�z8��+gb$ػ��9��oŋ1�0�����Q��+�Y�,�T�^��@_+D�(��T<�R���6����"��� #��p���[�w���X<X1�1ѡ�s�:�����񺙘mX��%���8�U��07D���D���Lϐ�_��Y7�I}��ne�{|u���������1�bd�.:/��=¨L4ڼ�&-��#V����}lq�}���o"b���aW���Xb��V�����` ���"�I��6�3�\5��֭�s�o�q�������HSD�u���eƸ��dnKq�̭m�
/I��J)�¿ě������9J?wd�E@�l�b�faǣ^�ڸu�s�����o�p���M�R�CG�������\��@��6�¨]����� �I�?]T¯�-ơ���8�b�`_���O;�����U�>s=�ϒ�Ȁ`˔'ې�%5��%������`^�s�B�˲gG�|��1��-ř�� �T2m���/�V�����`�O'6;���W�חjZ6r ����%ң��|���3�Hl�n���!=��6��,6��*Lӛ��`�I��.f;ί�݊�$�>����Կ F�g��O�� ��ܳ\ծM�w�@�8 �)2�1��yB��9=U)�9/�P̈́���A�_K(Q�,[�>h\�1�l:����z���HL�N��-�27�<ٍ���e��I��W�a</�����3�3�v�҆oI����iBX����c�l�&�j6~��*�2��xJ&k�浛�K���WW�l(Ư&�n)�H���>�N���zʖ =�F���؊Hn-��(��P��z)E�<t�$��,_��/����vJ��x��㢵{(�.�·Y"復�x��Q3��	l�4�_1�' ��:��e ܲ��W�7����-7P�Un��tCd���l�$�P�-�r���=1�����r��re��s��];���.JA��N��?����pX�S�0MVj� 񏂖�Y'>�X����������i�K���)��{Y�H�~M�U�V����"3͂)���w�Xp��r
�"���d�v΢2�Hs�SI��_J�2� "ZC�=z@lu�H�.���J6��3����a�{,z� ��Ƶ����ot�*g�f[���؍��=#�O&���<Ҷ��LC<��A����dr�Ь�3@���Tqe% ��c'X:�g��xS����?`ʓ�)0+�q� �
�N1/�qu]��1�Z�ؖʔ́�@2֙"`(���A6̃��(�@���$+��B=t}�M��V�������r;+LM�:|D�_�c(yFrv���C̮'�X��y�����j$Z�����%Sp��*��v��������3��5�]��xTl�_�7##L]n���wgoDr���B��y�#P@ c2~͘9�	/4��ǣ%��K�����>X��'W˓�)�Bj�\@Zv�Z�qT�z�N�m��,ɨ�^YR�|j_eM�V�f3Ӂ\�0DSS$�>���Ob��s?�?B��1,{t���[�T�X*e�9 ��(����J��Mɞ3V��'��T��Zp���fZ�@��efn� �(p����H �&?�ƇH��W����Q�YN
�{}o�Х�D?ࢋl�v��t��l�o��=����7|5fbD2Yj@���s�&���|��4?�U��3�I��� ��OI`�nY�cBP���6/E��Wq�����&T��S�x5���q
؄��0/�"7|�����N�`����}��:�A���"���v�L?/Љ{���-K ��.<Ru؍�V�	#\�%Y�.�C��{&�{�B��W��?p���8-p�uJK<m�F�*V~���;=d@��4���&y�^���	�ٳߩf��=�ɾ綉����9N4_��)��vf0?{m�V��������p�]t�L�O��L�{vC�I�55 �t�ڤ�i�3ԭc�,��zG�U�^5Ri#u�P4�����w!Y������ZdN�*J#�$f��ℼ|��LD�m#���KeC���ǹO�X��϶���[�y�̠�&�M�r�xde=�Ɋ?ηֺ�$4�H�mK���Y�YҝjV���E�v[ ��8�T�\�]�>ޟ#3��R���;�I�8/u�H�9����Sh�L�o��#$=�ѳ<�Qd�P:�8��Tϛ<��G����*��gv�U퓠k��TT3�qꁋ�t�i(�p��T`x�.�=.�I
L��nϝ���1�5؟�e�yCǼlD��biq��ɵco�~p��.M#}_)Dvv�]����E�YԎ���M+o���+��"}���c-=1_�ȉ�p8ܧHk���x��m(��t�0���j�����5�J\�����u�ˡ[��`��P^C7�,s�2s� :��W_�������jh2�*`ő}�m��͘)�O[���h�U��%��T��Q%��������#�'1Z����wge"Mں�*~�k�( �*��wB���PH��q����U>H-��2
cϙ����v)W ��c��W�v��B�ѷN˦N��e8�)��\y�������#�/��s�8h��z�`���A��?kK'��OB��f�Xa�Xٹ���ʴ �ŢX|����TP�mk�����=6a�I��ς'���&�0�OiwM�.5"J�'e�9�i.l���^���Z�z�+4��y������N�r��4K�8׾�K���,�!�E5Rf�o6�	=��jtG��^G�-������	Om���v��KY׻:�}j��}@����Q�h�)Er�"Wƽ���Xܛ)����W~O���?E�Ҧ���铛o�I^�����K	���Yqs������lFcX2�,��z��C��B�������+4����߀�"/��eg�*&X��c:c5W�bELX�Z�Nٿ����u�Њi��߁1�Е��������. ��2{����	�/p4�A�>���8�����*v�D�n��N��u��&���lG���5�*/_QS��C�p����r_"�Ť�cEr�fR������7��8��T�I�@�3��攔]��L"K<�Ӎb�X;�p���=�G����E�<d�o(U;kTs��><���oi#�[�@fn��)<��,<N�*'^m�S@�yUOQ����|
��d�ز|�B�p�������_�j�9�"x���f����d�bY��_g������'⊩�pj�Òҷ�Z�R��'/��. I#w�Qy��("}$�#+orf����v��chG��p�z�7]�X��xL��pɚ*�p� �g+�3#i���;��=�gM���d�̜�NFRtn�ܝ!-�wq�\�L�5�}0I	�����>�C$ؑ�:з���c(z�蕵�/9�ֈ.vJ�x�)2�K���ႁ����n#!�j:t0�8���6����k��@��AQQ�Q}�]�GVWunnZw�s_��������{��Hx�;^q䓍0����!����SGcmBY\"�ItՈI�3$����/����Fp��8�(�Nk���{�]͠,�5��(�KS'��aj�� o�]���R��%%Kl5�<�u�� ������M�|�\��Rӌ�������M,��i;�2�x&4g�N�xG��*E��̿�T|'yB.�/ќI���Y�B�Z�6p�j;�$��+�yd<O��e��>S�zv���=���c�i�G]����~��F���2��*\W
 ����0N"�?��t�Gp/���T�B���M��	&O�݅���	�hRh��'�{vAR�D���i�WU&TS�ꪐ�)�@�}wBT��-b�`I?~+���hV�يOO{Ju��2G�3�r��4=/&m�DCt��fjB�f(���jA�Q=̗=ns�b�*�R�o�N`���V�Ʃ2MP-c}o<�#�t!�S?�7It��@�R�C�[?ڼyM�}҃`��ք�R #!�uU��[D�k"a���� j���b���!�U/�$Zj�MY�Bp޺�FE�� �cL;9{�渝o�Uވ�gWP��6KEl��>��Eb��y�v�碂���K�r:{i$3�/¸jo��䁉т��wׇņ��Iho��p��G'�#�.�L�O�)1�d
���͝��eQ�D'x�f�J��E�D�(3������w��G������jF�r>z�b�l��(��^�����T7��Z�!���U���e��V(3�TkP��Q2 �*���UB���z�9H���U���8�ґ�HA���N���x~��}jp�+���T�Y�Ic��&��gK��R����L����r���8I��ڇ����3�Ȉ��aZZwB�����e�u����7܆/R�*�m��po�^�-T|�3�$����P"C�{JO�!|�hVN�Ќ�7w�U��P���O)& �Q�� ��U�V����u��g��=MeW�x�����_�|�ܖ��]�./Z�iE���� �~����l��.2�I��D2?4Z927h%��<��(�K���2�O,�x�����/X7����7���`�dB]�ǌ�G�u���Q�Dڂ}��6�@N����DL�_������ �lc��ё�Q���;Yȇ�>�AWs7�T�����|�%G�
3-_��pKRkvbA�#DF�6���E�Ml0�ף����e�]�ޥab�4ݮ�$����+�;umI��h��S�$/�鷪d./6���:�⩅��%��&�	�CD�����#�:%)���L]^�.H�(�"�����P�O��aѴ P��m�
�E	���`����K iB�'hH�zۧfZ8��e��׿@��/����;�WF���,P�bҜ�[5n��۸v�dZ�ɍ���8�
�>q�L}�Ň��Y�h�@�B�z���T�8a����)׈u�Pi=8~'R��Q�J^����˱�yH��$�w�I��ԜH�jK�km�_)�AL�>uJ�Ӊ�����=���lP$T�B�ZW�OЃ�*M��ӈC0h�ŉ������؞k�S�դ��t�?ja�x�V�t��"iu��!8\��]5�qN.=r}$S{{cѴ�t��ޣ��uz./�|�<Ø�2[r���b3�ɗ���z邛Q��t9�h��بp�GwK�z��1\�-���q�4 `�sՓ���,n��j�qS���/v��R��~��T�(����s�lc���Գ"��9N���jq���ݗ��[z`v�[��V���1�/h= vb��=�*r��-[�P���O����6�û�LnGeU?���K��'�n�{��Ԣ���p�b�7�826A��.X�5��j�DFʡ�X�F�+���}I��f��B&[��\p���ߦ+�E� �U�_� x�9��M�<R�j�*�+Y؞�e<��R���ƫ�{��]�q�Su_?�lĆdMn5,�x3ߍ���z-�0b'��G����*h&-�4�?���GCfA�m]�*y�M�O_�E�,B����M�!|�0��W���mi�4s�6����g����N�� �w�3tQ^׭B+C��m��\Ңћ������-�����q^#}z��NS}���K��ִ�Pݱ3��Uݍ!Q�Fl5��I?�*����I���*=r{WN<p��X��XIQ&\UP�&�*�iaS�X����x�{���X�T�pՙ�{�r�C]j������pH��׭�i%�s�ʒ L�[�-d�nR���vB;G����%��W����҈�oEzC���*���zFV��I;����(,R��Y]��@R�NToSk���X��ZjEy,�C��`��~)���D-���{�]�9��!2b��N�'��j�%S\I4�Xq�f3��Y��e�z�+�����,|�TBWB�B��q�}}�}(��i��s0�0�#U�(��	|�V ��ЈE��y��n39yz��#�0���y��^B��2�qD'6��U�a+��y���֙��j���nCu��.ܣ9<E7X�VCk�Nz��B~���0L�����n��qz�Bޝ�Z��:j`3u,���GC8����`��ś2�{���'��L��wZ�_ץ��c"�ƍo(%(Ɔp,��p5����|�'�)7�J�@w�M
ʅ��F��?���"���X!����S�o��P�}�p�ϲ�,�.m����2Ȥ���b�N�5ȧ@My���b ��uc���������Z�j�;�܍��у����˒N*�>�)���eL!wD�-�_���y�9�BC���s�ŭb�,x��l����Ǭ��C&������O�B���-�Sq@���B2F���T}2`5�����7�z}a�7��X֞S�X�G�9�VQ��r�z�ˡ�
~�?m!���/}���A��bI�Y�n��	݃&l�{ ���{��l�E���wʅ�Iz@� .ӿ)�Jx�9�6��ұ^+�<�QVK��S�G�]o�h��Ov�
/Q�����Aή�ˊ�Ge@�{|K<��Qil"���ȩ������Z��*E;��6f�,ڨ��j���h���Ր=�LY�f����&�
8��%���ﳆ�S���
�f�Ą @{����j«"W����ŭQ�c��
N�� ��x���J�˶g��%��!�hOu��ǿ��))�4F�4���PIr��}-�ʿ�PE�,˫�0a*w�<�,������n,�S�
8���ȡ�&��hqE��rV�;k���߂kz�޲F0�!��'6�(6k�i�x�@4D��Vp���b�5��ʯ�?�]�<�� >$Q����G������5n�d��a��c��<����:�I��$��d�5�(�����阑4��8,7�2��o�9�Ξ�r���axL�쬔4X4���S�z:MD�Pې:%_I^Qo�8�� kO}h���y{� ����;���Ċ��6{��[�8�{�+�1Џm��+�lt�����3���3��`���i��&��޷D'`�+��ˋB����<$�A�|����w�bK��E/7�!�����w,���>o�Q��<d���7
[?͔���J���'\,��׵-+:h�ciA��a+J?����Ƨ��~m�#ٱ	�W|'K��^j�����F�+�' ��|�م�2.	�B�wL���̕Y��I��h<�ԴEާ�g���<]������އ���X<��2϶-�TR;<���N8��mL���َ(:�[i���1m̀���z�޷<:8lW��bq�b�,�4��(��sD�l�'��u1f�G�3���
��pU���I�-̧�+�\����[���E�q�L���
(u�M��l>:6R}���Ft�̄F;���F�JM�={gZ`�����2u�\e��Ey0�:I�rˇ��On_��8ߌc�s�<�>��l��g�?�O������>Ȉx�=���R!�c{ͻ�f�=��Q�U ��4�	^q��у��OJ���a�7��*&�=�l�|�7W,��jwX�@ϱ�a�/�?VsZ>�we?��T���n7�9t�H�H��|��%=7����.�^H�%w�)#Ϫ�}��6M���)�����I��C�Z���Ĥ�Ŷ��b�q�I�p�_���+�ܡ���� �ȨmC�yf"
�)ji.��p�)��Nx��?�WK�
��~z�
W����Z��������RQKu��?;�k�%r7��j�t����X/1�E�=�Z�`YؘS��śa�Pɞ�(��ɜ��5O�O3 ����~����*.u[O io��	$�z?h%&��;|�s�,�ҝ���>T�X���#�(m\�ye�W�5�h�6�W#;�Q������ l���m�{v�/p����"��������I�-�Z���_�Dh0�)4���f"�Ej�tÂ�Rh��3*�z�)�V�"�-���13^D�k�//�oL� �*���A��W����믩��ո���ǿ����i8V9w �v��1���f}J��|�� Ϯ���Ho�����bT�X,B~�+#�Ao(p7�ꖪ�]W`����f"��� ��i�c+Ӄ�/�C&�YX����V \���|4P��@U��Q)XJ6q��j���Jem\��(���O)Uރ\7���A3�@�ɣ��z�aK�Y��<�U�Qq�j�v�Q���j".2J<��/#�i��������U���ݣ=�����N*g�N+!�LO+'٢*ېGN$�B����ш�M�j#q�w�"����n�sqU\���'|��������g���{�e>���x���D�xV�2��c~�hWSϣ�@� ���m���55��D;2j�=����#X�0
v0�\;쾢��R��$��͜�36Iz�%�4.�|&�v�:&3ZI��\�8��6�K��d�*x�t�+�Z�IB���������9��N����X/����}��	Lw?D�����Kũ�n6^�m�R������Yی%a��6�^��B�>�={\������ ���.�ֹ#�珅ayH�sHk��{V��[Vp�Ig���ǂ$���7��UMͺ�����[mr��P��+�/)��$�VZD}Ԓϻl��,�撴�oWY�@�]|�j�Sp�hA���>LͪHt��z�(43���2`��6Y~~i�l�� �5B�$���U�u/�s�%q�`���x��p����7�>�K9�:���ɰ  �� �E3A誫<'՛��o�7�����R��q	��F�J%|����GDӎ�- ��F���fv��V�j�g��"h)���فGR�7,�e�htWЈH���c�?F����Ci�t/P�p�(4) �Q#���?�Ř���dt�$'���8J�ҵ9��Vb�>�Cm�IbJ+����;b��)܏�s#�ig��Pz��z�~u��43�T?��V�w�6p�7��	�p�s"6V����@�έlT�o8��PK3  c L��J    �,  �   d10 - Copy (6).zip�  AE	 �f��V?:إ��I%2�Kxѥ"q/�C�G}o�L���VC8�K2C�5��c�=�u�kX��;���5��ğq�����{NjVWi��|��7�k���	&M�c I���H�o����=����گ���C=�h�mGo��=kdq���:�,y�Y v��3+����_A"GQu��wkګ�	6������δZ��;9쉉F��&���� a������+y�PZ��308ʿe,��}\ۼ��������4D�(_����}T�[�z���kP��Bu�Љ-�ކ{�|��-���O��JY܊t�����~��R�SM�W��m�uc�Hk�LT��1g<�8��ùܑ��B��\b�.�*�F� �ǪD]�9��{����?���Moҗ7���rz���5����l�K7�*�qG����3h`���nO`���3��ȧ���}_5rЩ������"	�����H攱���qN��}�ʔ�*,�����ܱ�~���<�lɸ�[��m�Q#�X��1�8�p�5�
��GU��]�uw�F��DT�:&�KQw��wc�������*J3ha��$}�<�X��ݧ�W8B]���׆̟go�v���K9+��E01=��.�p��ќ/'Um��2�5�$Y�ƀ��mA��G�M;�-�a��:�q�o�3Uj�/�{��B}�6\2O�u���2�m��}�JUX�#L�,��l����Z^ �nw5C�sl��?́&���&7
�x�W�S�(±*�]�lW��-�z\�a �a�I��ץ��S�	�\,�����$����jF�V�>�u�@R)��<>�z��߆ 8R��ZaB(��嬱�  �q�c��jC�;����ٔ�uN1.����u.�ʢ%�X�i�z�,RZ=��P�h��E�4Ր��yja������p�o,b Yˎ0�*:d�MWE��4�Tg4���Q�R����A$�P�<�A^��׮�bC��#w9��u�O[U%S!�VΪgK>� ��X���߫�v�D����8���gp� F{ej��#޻ώ �R��a�o��xȁ9��_�$��Z�%clR"�$�5>�ߒ��՛:.z��g����A�dVa��_H��)yaN�类!�?�\�6 $�2\.-�%ޢv��<�����+a(q�������"Ӟs�f0��xe��~q=�#sZ��O��"N*����}��[��8.�n����)üO��=���SEI��O(��XA�^�R��xv�U��ѷ���8Ȳv���� 2t�8�$�7<,'.BS�R.}�QXM���9���H�/ųv��m�d�������=��n�Q��(�|��Y�u�x��w�Ǉȅ�%xỢ"�u�Yo�'��Ǫ�ύN��c�8H�<�F��ZW��M_�R����<q�7g�3�]IMdI��t/�EOi��#Mv��K&H��17dY..d+�-Y"5︍�Eڏ�#p��G����j�lQ�ā��>�.����b��%����ke�T`�O�Ys��[�
k�>x%�8�8;S���Ye���D�;�DK��������[��H�bp�=������6���C����;���my��?w!���!/����B�8!����!Y:G�eJ����~�G�]U  ��g	��aДtH�������Ǿ>�'a���I�^7h{���F�J��J+��]���4��&��o{�� ������-+W7URAj*��:�s�b�t���J���e
\K�Et(I� �J�V{+�D���VVi� �J�����L��'�*��F}uLZsq�)n���E|@�W�F�7桴!j�(m�6��M�lC�j
�荱%1i�l󗭒�$�L;��?t�ZV�$��}�����,3^C�-�-��(/8L������i��-��Ͽa2D���1CEu#C��r��hD'�*��+<�Ɲ���w�T'�GG�M�=ɳ3#�M^aa�8�h���%������{i�/,�f�)��c���&�ѽ����FZ���y|��}]��"�2�&���#kBJ�fm��Ɂ���>(�o�tg9+��o���hA)�:�6�A.F�)����g. ���-s��S�Rq3��0�\�%LE�.*v����l�5�d��oq����5��[�>B�Q+��C���	:��-9ݚe����5� o�c���w`�/�L�����w�I�yaw�p��+_gO7Bs�ӂV֕J�w�/D�(������ $!�3���w�	�p��vh_>� l�)\wM��\�U�9&��(PK��NO��"O�EU� ����D;
JCP$������:�IfjO{�߃����~��-_��?����D)��R��^Mڋ�\���ւ���M�g[��}�6�(@��`l�K���>�LY#/}U�&�3B|��
��Wȥp��M�]+|��rKpB/�G���#y�]% �_{q}����G�)>�Y��M�5U��h��V��b!>�SNJɗj���K�L^�=��\o��Z=��w^�Y� g��B����t5�2� ���F�a��"�U�'�Ӥ��u����ؙ������G��FSA^��Sk:mI��Ԯ����3��u��У���r2�I��G��/	��':-h�FA�P���}���;�]��4�j|�ޣ�H�G݅���"������L�{}ᛊ��D�#De�.u��V�w�W�!��@�^B���Y�8x&���@KM��_F ��~�bjA,`$sb���-�H�r|ޡЫO��^�l�����x��^����G��Sn��&;8Il��(B$f�����t�"4q��������������4o�Z@�㤕�N~�>�$u�����@H�KBa��Zw���/Mf�_���W6 ��˻�����8� ��rL9�+��
�U��{(�z|�\�rQ���r�-dV]�~�ɢH�NeaO���zV����Eђ���6�O"�N�%�}֮Q�E�������:>���׮ȍ�D�D8��P>]��]�NK�+���AKs<�ߗ �m)R3���p�x�L��h�����\���IF?�ԃ������e�� ���Mi�G40�|4��-\�97BV;�(����S��c�4�+q̺?C��q@1���^�J�wRM��ǾW�Ƞ	r���Z�_.6v@gk��h�S�Zp���zSF���1��]�Rҟ�~c�&��������� ]�����?L�(%qX��uO8�+�(;�zjQ�j|1�i�Z����e�U�Ӧ��Ι���dTn~�iu1+C�D�Nl����z?�������.Z*s�!� ��ɔ��Q��S �F9�h������3cӀ��Ѝ��Nj�XD�涙V<O���C���F1v�-з�_�)�M�[)t�)�	�I��E�B5���3=��K�R6ꐇ�)BՌ�w��;}Wd���-���8����5�~w}��������3jb|����Z��o5_�L<����?E�B_��1ʢU��ک"�����Ts�����W��Q9�ȥ:��g�o��"�i�rҏ:ה�>ol���Zx��Z�ܐ=F�P��/q)c؍��T(G���c�r�zR�Q/�n����Ο�G,��TqΕ�*��e-��Q����Ga����Ȥ��\%пd)1U����cU�_m9�Z����Al���إ�ė6Iw�2�kg	�NӇ@��������7;E=�� ��_���Ҏ�X��M	����)[o�;~1$0d���C�a����p�A���~�i��s�c�����b~�*�M�r�ʋ�E�F%�n�5�Y�u��z&O5\�����\P�h4Ѵ��" �������MY��"��C�[�n^��Bl"5e�1��hD��Wɀ�H�ZcL(X(�ك³��N���D����6#�A����7}������(��u�&��85N�j�Q��c{rK#��
"�+��Q�^v�nS�"b��Q�W1^�b;�NSd9T���n<5e��`�&�PZ�ۄ�]?���CM�]�}ln��a�m��㦁&��%�Q�ޱy��Xᇶg:[ni��s\�����m�j ��#2e�S2�YyS�w��Vq�t��ڡ
���<�1�ץ��J��<2��w��O��M���*����eeL�/�jL�V.F�?e�<����x���]'�那����E3�~GE�h�y��oFf�DNKߐ�́]���?���ڈ�P ?�8�k��y�B�:�s��R��
������<e�i��y�ݩW4� zQ������#��߶���Z��>(J�� �}��XS�n̩t� �0���4�JA,�6b�)����Q�����7���
���m����Fp_����M�	2\ ;F��.]z�M�>��Y�F���5����_M ���[Ѽ0�t�q�NnL��̮��c ����%���Z�
]�b`;B���0I�z�� �S¤3��Y�T���/�i��YT����N���[�-:� ��0nn�/>�<����Dn`��V\�V1	L>p�4g���x�{�dW
���3�"n�_ ��J^%�u ]��e,e1>l 9zƐ�6BQFJI��a��o����c9�|lб�����^�0�a����ы"xLe���A��{��2������a�"���D�jEl
d;���O�x���W�O^��F*B��:/b(�Z����M��T=�tx��z��`%�� �t>#�eP.i�]���jg��y�����t�m�E��h�	�g����0��R!~X���������Ɂ[ɖI�h�<�=��ܩx�[� �` �ZZ���ݮ�X�H	�ŏd��2w?���W�(��K�L���K�L��Kwt�z���@6�`>͏kC0D�\���M	�./P[��V������xHR���-�E�8)/J�F���_$��z���%:u^;K2��:�[�	�ٰk�F���w� �3�u#2:��ʫ}�ދP�����@ͻ]�M,���^�L��W�pSC�M�����@��c���?�1:"�V��9��uχ�=�fNH��0#M���!kV6���uB�}�W�A�o=�RG��-!�ɹ�v�#a�a=��Z-�����%��u��Ru>EW��F��8�X���_��Mn��H�iR��n���9�:ZJo��iB�!yڄ����w��-�x������}jU|����NT����R/hI�.�I���egx]�J�7A��� �*�\�Ο�:쿠 )��]j��%.�ȑ,3$~����~1���PU�|���Ɨw4��K�5=� �����(�;/��5DI�_�>�J�ɘ��v���f�2r��2;�����J�r��[yD��������Uz3��?�1���{g���^�ݢ��a@�����~;G��^�IcR>�����7���١f,��5�.T/�o����"m�Bŉs�������Gh�%�%2v�a>� �jجP|��0�G�����x�x���s�2����xP�>W�Z�QS�E��b�/`UXj� Ihh6C}�Y��ȓbX���Ƶ|K	�[{�C�z�}cV��:�ޜF���n�ӹ����9�
J�iEM�(�];7�R	��C�n=�q�� @A|��I���uW���)�_�B�k` ���Hh�)�����ߧ��D�
c���<�蔀�R$��}�b5*������g̙�JI�%��T'^'�.?�@�£��Н?H]Y94��Xg�jkzJ�s�������n�"�?�҇�!+�U�-�KH>���K��I3�
� �P���������E��2�yy7
�=����3�0[��5��u���ϒ?�=�x�����n�DU[��l�mP$��"@�jX�~ c�l�������Xy.�E��|w��(C����4�(%e��O��^�u�%��fs�d��%t`������0C	2�r3q�9״QN:��0խc���jͤ9�}�H.��8�Z�Ph�O";�t!,��z(���(���yǅ���̉)N$����}�t'*{g�m��2�4��������:7=Y�VEr��Naθ^���a��7�j�fGFF���
���ه��tULD>��i��T��xP�"a��u0D��E��TI!6����GZ�3��i`*��9{�� A�]#�'� iu���H�#��Hv��'�ܯB뾖c���4k�̄����ܣ&
D��t[��� {�bS���\k�U7X���J�W�bP$K�6�������\��'_�v:�R�P�/,m�@�O�^�;Z���*h{��U�B�Y�0s�ESф��a��}����x첌�oNk;����;
���֓Wt��pR�ZX0D���BWZ�2~�[e��0;�Á��mߋ����c��W�� s�^�\�}�t�W<TM���C	���<8��������󻆂a�jC��fB��Xg��l�v��K��Lr���(x-�
��4~���uН��8������֛�Z_ x�B�'䋾�N]^�L��s�Atg�H�%��"��~��?���DK�@�����֪���R!��T��%�2-�;�D�����;�י���p}�� �������(x��F��w�svA���ۄ;����է�\�>:�5M�z�]l �g�Nn���J�(-��Si$�r�K��G�ဿ���R���rx�7��N��yL#+���)���Z���]}`a�f�ߢ��+x������%� �︠�킈����	+�� �l�RC�?2*7��8rI���G��W�K�1��}O9HG���)gx��:�7�Z`��Y]p��xĆ9���Z��� P-=�q�0�k�`�R��=���*��j�2��o�o:��XR�ѧ�"G ���w�C;<�z�rQ˘��y���tR��H��
&���b}��Xݵ�Q���/�:N��)ŭ�E�щ�����ګQ��cJ����жD��`iM�7O=�o&TnV�z7�-ע4Oc���|_L~�Q���ܯi[Y�{��A]�)fkЮ�����V�w/{?��!+����l��2�rQ�����"r2Q��@���SO9�KEN���s�	�����R���t�����o3��i��Dv�D��X>�R�m*��!��o�Y+����@Ӑ�J��I�����\�}l��n����F�7�U_و�N<�v I������6�4V%���J�Ϳ�K���Po��m�s��':e��L���(��^O��Ri@��I�E��HD%@Kb]���͋E�V��k[�":"ZW�½o.Ŷގ��<3s��Q��q�`I��`�����NH#�L��Db����A��]�������6<f/���F�U�P0s� �K�����j���k�be!�[�X&p��jo�RǊ� 7�;�)gx�Rk�DeA�ȉ�CJ�)�a?��4�&+�:6~TД��|dš5$B��K�u@Ф�cR��6�Q��u�%��e�&0�W; f�;".�	���ěI�J��J]�ȗ57�%���{���eui��5�X�����G61ܜ�8�kL�@�_TՕB"�E�~�s�- �З-���r+	4��ڐv ���{��������~�\x��&�v�;��R�[*�������$�?q��QAz�3�� ;��N�����NF��'�t�r�~{N�F-�͞���>S��'.}*=��/���qPI"2E+By�Ν����l���'�^v���ֶT��GLt?�i���/4i��?�D�w���x�������[Y�U
_g��>�%_�V�B��hLm�����v���p��gZ����㙂�e��`R���3*6����KA��b�� ѸFn2�/u}q.p�G�� ��G	�bN]�Bi�8�vQ�dl��V��o����P� ��<�P�ҥ��	l����V�q*�<���c��$�!�?�]��#�#Ÿ��W)��;gl�g��{�]��=;nL�����������L�@7�
7�Y�1�Jv��&�gN#�LR��~+�d'�+(;!ɍNl\�KN$G���|tD*ʇ>JH���KqP��G9�V8�G_����X����0#�GJ�M+0'x.��V�U9���_�?9�:װ7�SN���?��^�I �]b����:4����|��o^y�7�2G�!�Z� (���~*%t������
�>4{�RBG �Q�@�Iè��������{�w��w�LH�ߨqN�:�2;aK��zlk���������Pa$��W�{�P EC$���>uB�Xc�`����d�mu0�F�)D]�I[���I7H�N�V����h��J���~�����F�8C������t�7 O�R�F�;�Sx�?���o��������b�DA��Ni���	�zݍ�T�V��,n��9H�!M��8�Ջ�	�=��[t_$��E?��H���A9�v�j
<�}����~<�:Yjє��)�n����G��Q�4�u��#�&@TB����z�_��G��'(�j2��"�;IY8I�8gn�y��4�4}ĺ4�rkT˪eƟ@�P� �������Ħ�Uo%�s?Tbv�PLX7�?�u���C���֚fš&��|��1��ǵU�Ǌ1w��&~(����?��(�����ݬ��{�>ƍ�E��ʗÃ��=K}}��Ŷ�Ǽ���_Y�q��x���8���l}�}Q�/�i��H���W���żp��1Q#�����N_37>�>�Mӈ�6�q�k�&� 4)QX���,���qQ�B�qgu��X�v�ZN���3�٧���z�U}Y�a�&��S��R��Y�j�W+�M|�3�u7A������%�Q(�z7�Ʈ���}f޹v�R��5;t){�����\עml,��;��!8T�R[)���Շ�k����zR�dڇ"�cG�r:?3A#���a⍤��V�SH��m�w�H�]�����\������0�_]S|M?F���q�qJ�;P3���X�%����F�0w5�Ю̤D^��S��f�%kzp��/C��B��%�:�º4�:��x/���*��]`��]j`�a�bﺛ�ku(�S��AZ�v�4�׎�g"�~$�p��<f�_��fLl��=��vD�g�Ԛ�|5��Q��gKzIQ���P��}E����3¼^J+߉�����Z<���>�1��埉P��i#D�A�9�eȉS�?k�}�jd���q�
��+��4v�7��۱d5Y�a�7M^+�%��Ot�_�,*&���E!@V��FM>n�],�	HDԂ�b�$ۗ�+J �~1�HP�,��/�����7��D�p)tjS�lV���L��t=~��F�2���~�Oo.��a�$d|��fmdm��Q���$����j̋�DT��p�e��45��s��=�Y	�c�|6�����6�~�6��ŭg۠�Dw1���pO�G}-����ȧ��O�����-1����#1e�䭆�g�|��S��-�k�:����k�L�D����W�����!A� s�`�?|M���i�ʯdյ���x�7v�k,'�v#{	�w�D*�W0�;��:!S���w��i��]Ǩ�����΋���2`�
�q��A�k	4;
�*ｏ (�o��<��?�%a~�4��Y%�� ��HQ��6�
l �EX��8�G��0'���O�;ͧ�)�7?����3*�er���{���z9��)�|j�a��6Ն��/�Y�3�����DB�W�I�X�6j%�|iwK��{��I��/2+���Z�'�����8G���XV 9Pw�s��٥hpSFb�0AgOI`[;�x_]W>)�qH��!�<��=��L���Li�5a�ִM�[�+G�wН>�_���C��b3]��������=��a�찥�ţh7�j��U�}d�}喨)���xu]�g��p�ز���x�%�u�!Bc�Ms�8�U���-�Eh}�vk]�}��_�����%ޮ����H�?&�b	ϼT��9a�����e@;��d��"�i�o���r��@�0�sE�i�sS��%�sp�8���2��N��-�%�-@�BH�W����bR�#K��	�]F��m�W���
2i������Q�DJ�I��U��nʬ��B�@^�˴��xn��)��5J�hU�1�q�q-]cf��V�/0eofk�����>Y��RZ4>qve��Wl�A���sR �ʋq�)6�&�e�f]�eaƊaǳ�m}��w��2�^�&��Kw��FO6B�٬5�v7�5rjNx;�`<=.��U��6h5�Sa�V3�ɪ$�]p������H �:D�L �	2�6�aɼKx��Eю&�:L
f�}��Qu���`���o��nn�/��B�1Ŀ����7"ȉ?/!�<)6D����@�?��7���z*�9�AtŴm��#J�u�	U�Xd@ĉ2ՃC��ђ�!*f�3�u/�Pw�Rx����T����ys7�K(��y���!7�
Rv�)w
]��c�f{#F����P����b��:�� �Z0�XY��]ߖ�s�K�T�Ĵ�X��C6p���R~mʣ��d�a�mdŊ��`���Ԑ��8w�{f��"�wp�UO-��<�Ql7����f=��Ћƭ^�9G�ANhvF o���3V�+`����W��i3������'��b$}T��y|���	��~Ove�Z�Y�)���7l2���SMa�0�F��"��"	:Ur=:0�p�{�i9���x����y��-ð��v�����&��6�2�L��8i���N���w&&j���/�$/ļ*�Fa���*��g4��~��^���%$ۂ���q�k�A�E"����?�P��o:�c.%������
U�J-bm���9��.G����8�ų�rD�������]��d=����'�7�wey���#��L�"�(���\O�8S#�%��n%�yH��GY���Tю!��(vwTD���X�L�����&B�����ꬵ�n%}٠��$����$7r������o@.K�H]&�uΝv�9F�����G���BS�6U�p�Dp$�Mp��5"i��c��#�0��v�Ԯ��N`�,1�}�룷�"$%g�Plak�;��UTK7�'�]��-�[���y�%z����ڬ��ZBۄ��b����&��^8��-�C
���J�}d���(�ﰾ�g�s�B�}�-CwI�2+��XܩzwT��8١^_nm���92;�/�ܫ�Q L F=eָ9i㜟��*�8�L������=*�VU��١���'������"�I�s>�$��K���f��sb3%�$�1�PK3  c L��J    �,  �   d10 - Copy (7).zip�  AE	 �T���b�\9W��Tsk�r���m�j���9C�__)��~�~P�+�������+�sju)�8M�7� X?�!��go�B�ʋ|+����=����nl��T˫U>&u�6��6_�Jf��U%�M�i��b�O�(�6��r��!9��
@�pqF�l���� ��,w�jh�#c)+��_H�'Ę�z���S�	���.��0��N4���ya�X pת�1Wzi��AJ��^o�@e�7F�0\"�[c��@:e��o)�2��H<� ts}3�T{(&�f���-6zr����N2zO�3X����N�������&ۜW��Wĥ��FU'p���"f��s��͠�?��A�
��K�+XJ;�9L_�:�u?���7~ w)�~|��w���(Ȭ�:�x�!��N��m&�rdy�P�d�z�� 	��9���i9LH�z��2X��1�s��ʡ���c�o��˗S��*��MF��s�@� "����Nu�?�Ij����Kcg[q�]�z/+��(W�&7��v񥑐.���q%���R�Z�b��v���A��cp��-gc��:02$S(�og4%�A]֙�G��a���J��h��X�Er����}z���� 7���&��?��O_����=`7�" �T�،�I��g&F���f��[��,�%|�c�!#T����#��P�]�ٮ���l]<zo���d`��1=�ا�R�\t p����^=���ZA��3.���!��4�!(��0t�/Nu����ݰ�GyG��Jţ��}�i�ḩ��+��3 �q�h x����%�{?Aʚ�u��$�	+��	/�Q�ʖj-�M�)^�u�:TTU�,�'���e��(A�n�F�`�Y�sP����T-��(�FP�� �_�P��d�|��ݦ	��EҚ�/�qbl8�L>
HSv�צ�� ���w���K��?�;a�v�O������lDnz��aC� h���,h賚ˋ
]F�E�&�x9��Qv;�R΋����OV��scA3$*�G3��\f�.���jC5=�c ٧FLf�E�V�~���6�̨��H,!�ܧN�(��>�,��7��l�	d����[#�r�B��{��ka齱c��^�d �Usz���6�OTWs~��Z����u�h+̶��NJ��o#_�煊a�{��h����[�Ӝ�6�������ѱnn��O��8X;�s�:܋8 ?$m��y���n��_����VB��>��&��j����c�L�s�#�#�A�x���6�x�U��Y.u�;���-�#^/A�4Tc��<�Jn��E���x�u+Drhݜ1t^�B�s������P�x)=��5�O�S�Y`>��@�O��Z|�rm��/`�V��"��N��Y�x��Z��=�@���z��}�6�_򘠩���Y'G���?��9�gDnyӟ�	p~v�߲���ef>����k=�����I��� �H��FB��s� L�.���%򌑗�'̉CP-0fcT'�Q�".n!�:�E�s�쩢Q�%������A9TR��[!i�fw;��_4�j�x�.�&sS���J����qF�ZA�1>lˠ��YI�*�}��>̻q����T]G���n�'�E!GMg���B>�=�7�Ǌ�T�Q�p���R�⽄�.A�.$О��~���@�"�	<A$7q���쬒M�� �Vƭ�K�3�ĺ��m'*��u����!�%��)���[�*5	V���  9Ԭ��2�{@_�i,�R�]1�Ы�=���wf�?�Z���6���0��ͥ;Rw��1�z1�j�$"~�����q������.Zm��\P}>L��wR����]Xڼ�ۂ�O��,�h9G#d\�{ b���U*��ݗ7X�K<�KS���ˋ���=��st�2��tm�[D��ڳ�e`G<@m	�X�Z��avׄ9�F_Vyo��{�Ye���h�@�,:��;*)�7��6fv���ⴻ�������r��M��i�X@�f���8��Qw�*U�z�/�A���̅"�I4M÷/�"��<�64=��R;�7�H5�h��nMk�6O�d��
/�G $uw�	f�@l|�_Ϣ��'Х+?��Bb�xMXQ@�I��+��r�)�J�i�z|ڪ;?��B�Pe��Xbf����<�m��ːW�(ҫ5l��VF1���ܦaQ��)1v�E�R2���7���o��)�*b��C����M������%H�Z�X%�td)�Rr�Lƹ�Ϟy0�ـ7 �z�2W���h�\��Q����b0�1��(+~�uu.:�K����_�U����v�zCS�Ha��Qp.ݵ,�3�e�! p/����gK���eA�4=Q3�L����A���F�!$�7���ϊ��Al�\e7`�cƩ�-�nA	���YE����LKԀ#18��-v�z�O���z{򈨖�&��n%���Xj9; 6�JcM"M��8��-��+Ľ���YAbS $�ffo��Bo?�,{�jpT���N�%���^��� ���4!�Ш�Aa���6�SDWlo_�W��.�����D� tm��̵m��lP��z�P��u�Sb]};�T���������a�Op�5��g߸����x&u+���F�W���D�-o�%>�8
�y�]!�ʦ���(T*��Nh01�!�~���j��&P���/S��^X��>�e,��q��;��4N���F����l��c�.DL�"}DY@]�^(JrE`C����}f5���d�O��g�����1�|C�1: �;�g��<����1R>�M>�/gk�3rOs+���:�ҫ�3c��mPf�{�⧛�ˎ�{�D���_�v'(�ĩ
�����������8�QKg2B��(�<Q�+��&�^���c�n��.���o��hAJ�P{tZs���b�!튺�I�+cۤ�EOF�Q��r���'��W��7���yH##��n��t��0��D����55Kx���qj�:��H���5$R�I��@�W�OY=v�u:ࡰw��!��8(ܻ�r	<���b|�c΋?6�,1���/KΨ�0��o��Iχ��ڏD!3����J�p4-E����#���dj,)�	.�&9`]P�&9&��3w ��K�X<E����N�P�7��,�q�䔵�ZQΘYs�]��������~��ҹY��?�u�0	�my.vޣj��Y�Sq|
E��*uASV��4J#�j�wh����|���[枚��Վ�GP�w�m_36�L��C��.%E��B�y��,8��>����P4��'�]�~Kb}�T$4a��_H�D�#Ӣ��P�X�,UG6��6�������ڊb�!�21`	x���
��-s�~E��hG�#GJ|`���O ����;�dZ�� B]7ofI�X��o�}�T}1
�w���b��0�Z�HY�v���,��q��#��~c���:dr՞�d����z����,C|�GVL�f�9 $��A����C�i���k�7���Ӻ.����I�L�ߕ�hҸ9\��Q��*_d%�Cp��+}41����������H���HD�'��U�)@W*7�S)�٬Y6oKT��-e��Hl��)�S:�d�Mt�.�0��v����
W�J����_&�Ո���=��V�>��z����Tv����oS�M��o��̽  ��y��O���Z~=�{��]����B��J�a���	�2�ǌ�����e��N���f)t��t��3W#�����	� ~��K8J@�+���N[�Ä��_���N�c���&7�D���:m��b�Hf;�r�5��@��E���.��Tw�ߓ�`����C��,�	X����E��Ë���f(�)����v�9wl4��1��=KDC��TF2=�sH��!p4f�K��8�m��`g���eׂ�Gy�9N�_U�V�%L|>R����J���%��;9�$��-���p�^@���I��ªL��jz[�����8˧pS�*��r���wx\;�BM�U�h�rɨ�����JZY�Kʨ�;R-���Zt1g��|����vy���{ǝ�cs~x��ĔF��0�mgS��[�O\A���6 Daŏ�<�ݤ�{�������Pɂ���C5v��Y["?Vk^��Lm�5�& W����tC�:E�E;���������Ȗ]�$8�`	H�)�٫n�
�=;�5�W�DF�xӎ�s��%%����Q�\L�z �s�.�^�,�);!�#Yg�V���Wk�K%�IoᔎL�U$��LބZ��QEF��P!�'����O��7ԼT)���,�1���� �X�D��z��>��8�_�����y���A)K7�
3n�m]=~`�M*��e�˅�L~S@�ܷk7@I���7�3{�$��4�紩�{���́�y�Nm�"޵/�;�~�}�%2Ǎ�0r��u�za��~3qMr"�!��%[�@�,�=3�Ǽ��s�Z�e5kr��pl�l�3ex�9ϱ*美[}��A��m�h��Arw�:�1�^&�D�Y�܏S̯o]d�3ƀ ������Ec*;չ�Z��u�j�9�#r�nH;�,��ج��
R�{,�^�����儶���4R��q�G�'J�>��K��� ��tdD�YϨثP�N�|U>*'1���u�RR#��}4�����	D���O 1���F�,J���]7��ԝ�?�W�Н'�4ׯ��|�ڟ��{䲆���)E����kV{�B!@^��P�*�o�{;��Ư����UX��e�c�S���UbJ`����{���T
�u�+������'Ub�Eg��rߢ:���W�u�֌]�Po��v����a|yp�%��6.Y��RP�a�A���R��0RIv.���e۔�n��eb�Ϯ�ԯ|E�L�X����چ����b����q��M.��.��5^��in���G&If,������b �r^a���)4P���ʬ�����/2�w�R���r��Ʌ��������
l%>5d[���[�&靛�B�G=ŷ��P���mm�.߇�t���}Rk�A�7�3�"ʚ�ͬ�zV��)�$K=� �rҪ��TA�:��b�a��͌�F���H�sD��_�J���<����e5��u@o�\���G�L��#� ��=�C\Cw���D��G����6%��#��f�i����t텑��<8G�ŗ��?4ˣ	W-Li���Մ�Br �)h+{y��e��;�����ǚk��S�ت��d�qy6���g��)���0k(��zN�EI+�"���[�4#��/�1���0y�t*�b�(A�0���v�尉����7��+y�ܦ���4M�|��ʮ��୲�?�`LUD5�tMW����m�G�	�C�W܄I,`����fHV��X�0���Hq�M��5�yU����!����'8�l0��x05�8����S�!PG*�jڻE\Wm:,eD��bn�D��" ����:my� E<��-��ג��H5���oe��TyK�n��7B]:��.Ext�E��0½�С�ea�|$n�D� ��&����D�����28 [j�S%Ϗ�H�Y�B��r=K�?Sk�^t��|8�w�Ӌ��ղ�(v(�\��$�ߍ
)�aM4�ɟUq <y+��75��oI�;ZĵMbQ6���D��[^�8�8S3��1���zy��(&����|�d�C_ǝx ��O�*��}�����4���f?u2G��@�ڴwҐ�e��{��q���7-N�^^���k��W��W��9j������o&���W4�<tԒN5m�|�HJ��J�*���"r���;:�-�|�ݽ��M��X�^l6 ���K���-_�����!`�� �A��O�"`�7���}bV���B_��Ef��,�9�5����B�C�[� r�g�%=�T侄eok�$��^ �bU�ӣz�B���@�+�:������s�@�[����2g��Y
ڦgރ�K����(v L��Wׯ�D�жBӿ:р{�F�%!����I��&��p~5�̪ǀ��g�Y�~��$���^_��t�߀�;���/��4�hTO�cc�A�T��3$��`ݯH^< �^S�'�J���9v�1{�V=q-���msE����n��C���$`���w�w<U�����M (�)��ƈ�DLAF/�]n�{�@�>��q�Dccr�2�Db�����"E���~�cmGb������D�ܨ�m��Hv:�K�2�>4�����"r�o�꭯ᰪ��Q2�:�\N�C(���\32c̤8H.j���@���咞�3M	��.}�~��n�;-p�g^r)ެg�x;kR(�)r.	#{���D���49��8�D~Nƭ��hdʖ�ÿ
�\�:�m��>���3�,�u�)'k@�nR��!��Ʊ';�.Kk%��9tA��s��"�8�#���Z�-� �&J���i�F;-dE����AH���@vx�i�[8�e�oT���Lx��ŢQ�X+�����g@��z������B���4�g�s_v��z�XF��T�A�G��8.?���%r�`�z*J��z����mPSxe��@F��v訞�ak��T{MN�!�:��C�*62�)��M���J���ڭ�ǺgQ�&_|��	"Bg����k�0�\�!f���qD}�KCW�{�Wti珎�:,#	�_Z3!+��`+AJѩ�B��,����H���R�,�>�ϑ����,���	��\g���s<�1�Ǻ�4�Ƞ�F�R`��D!B� �͡$�N@RD�g}E$�0�!Z��](���I��LN�1��%5revg��{�!�۰d n�D�����D�Q��#�_h��Y@*�P7mܰ��$=������4�C�Y%���8����k��LY����o�\Z#�S�uA_^G����H{v���MW�A�?E�����7�7c�����%��|%#�����X���^h�3�^O��$®.�s��D9QޡB!�H��r�S?�`.�K�c�K�۫߁��;����F՗����'yH�;���_%+o6����c���QiUh�#��ΰ�	�|Ȑ1�)�� {�ֲ��&;�.V�/̭�C�Ã�~Ql�t���� �_rћ�0����R9�Q]%$=b�7R=]��,xg���}���`!�p%��Q2\�f�Y�]�Rӷ`��P9��W{L�G��n��{�H���nC !�Ih������KY�l��b�>)��G�5�-0Tŉ|�I����1��nrZ�3��#���p��xN.���B����r�Ǥ��n�c��8J'�6=M#fP���w�6�Smt).fXU���C,Yc��og�+�������G�����4�izb���9r�@�وY���2���ݹ%�L\�V�p��m���*a��[ggV����G���ĨmL�dȀ�<Hj����ٍ���q8M?���H�?ۓ�َ��]H��;.1����O�������o��z+��A*X�)3�f��,��������,�Y�1���8J�Pk2՘Ӿ�<��Q?� �ps�ΦX�B��Q�Dk���������n�k췷ޫ �R�W6t�$$�U��>������|���� �X �h�ж�$U(�ubQ/�\��@*(v���<�l1�ך�����?��N=�=�F��Ҿ`�:m4B����̣�����֜��݆Fx��|�c�����J�v\n��V~�P'�1����id�,Q�i��#���"�;v&�5���R�[*̎����䩉�rb�EP�䲍�\
q�Y�~9fH����4�¯�E��W�q�Hrɢ�Hh:�q��("2g08v&�߸&��9�|f�렭�W���Bf[�o7}�)��y	W@�Ըe��6�v�g!j���	��	�Y�F�?"o���
$�?k˛8`����ԢZe���X�rN��Q�� �Z^^�.IJް��h_{�4(�˘9�� F�,ti�,'��lK5�������i+�t���h~-�C���}�Xd�v���Xj�v�U��9�Z= �����F-��hu�m��k�?�RpƅU��ev�^T��9�0?�/9�nnR�ۨ�Vf1%�x�R9A�����٩ �7;��>�Nc4�V�ba"g9��=.RL�d��6"����/��nT5{�oa����ϩk�9M��bJ�Rp:I�w9#"�u�x�]�+�(Z+|#-��ZB�8�r�ޥ��J��5��|%hv!�P�(d��o�sO�#�ɵu
��(R�����(�ފf�)���BX��@�����>�a`e�^W�i��p���2�vw��� ���a/�2�2aE��'�n��B6�]q�#��j�u\��`,�����\�l �g�%���T���Fʊ����������sՁ{��oT�IvD�ǨU@����b�	��v�L-���^��۱�O^*یh>7�[
e���K���cc�~j�l���Jዢ��ծ����3�$�w�7O�?gN�z<�����K��cY����Oa�l6̜��Un�Zf~��⧣@���(�U,�8�TeO�Q7[�7[;����{osit�V^@膑̚V�%��N�+����r��;�A᧣V���kN���*�d�<Ǭ��YW�$##�9X�&��8y�!��Й��H��߹6������9�Ő��!s-�����pʚ������+-� \x�� t[e�dc��`�L�C�l��#X�B��$���"קezݝ*A�Nq���lY�B�jhtn�WRE��}>�e`��`tQ����_3��b�\���]�i+e�b�ch�b�*�Y�k~ ]�Є;C�aڃ�\O\*P��]���U-��Y-p�����<j��^o6�<���`��~@g������)p3�8cD�
��|׼�v�BJ6`Y�ܚWk1!QK!η

n<���X��1&����<��<
F�oێN�Y�C�&̗��'��*Y�k\V]i�T4d_Y`R3�)&����$�50J����C��)vipz��K�m5& �����^�-�ՙ[�;��ٹ��8jYLr�n�[Y.jlC�4nb	H��cK��=��/u\P��`�����49>
��CY��(cGN�-$��^`�X,*�?MGKb}����{�[e<e�/އoz��R�#�|>�)��Xޯ��TZ�x�� U��Y��+���cc�\:��������c٭��^������9�i� Te�
�F��0M�b�y�:e�ki=Y�!�=V�K:��u�"F�ʠ�Jt��d�I�x�B�ןh�f��[��������A
���(~��5�1�d�(��T�pZ9T�%ň�ۘ�UX�N�O�t6�Q/��V����֊��僅���L�2=׹�%���)e�-upӿ�f�������`����G�F(�m=#V���!�w�: M5�H���D��͵�#��)�S���Y���Uy������,����rCo�K3��������m˺���F~�R�
��`k���j�I�?h���=��p����g�����pRHLc7O�Df���Z-���3^Ig��\��˿qw�~���Dm�)��Y�h����ލ
9�d�Tcy��e�6�s��즵�:�3Ûh������ht$z���O���MP���Jo���[���F9�n�p�GxüR$��a�k_˃��rনx��wL�D����pZ	��J�>nȳ}2�KF:�.�Œ�
dz���"LjMϯH����q=�Є��OUf�)�PC0i�j�����E�e��{_�^sj��!�����p��l�&H�D���+�����g�º�Q(39;-�����Z�N�]<��^T���#u/�(��</���?j�Ƅ�Z�b�Q"֫J�{��q]v���>)nzH���i���/̐g�?^@=GS��y�{ �nL����!������O�[��2�Mj�bO��g~�2쪸��d�=u0)�72��	2ċg���[��+��<���~�I�=��>_q��{r�Io'�e⨌�=�c������k��T�1���a��"M���i~۟����e�X'���_��yo)�M�MM3'u�q���q�`g0�'�9�pf0���*4��!�T�����s���*��z�S��x��#��ꌜg��=��U�9[���#"�w��/��U�u�6���lXa:+�?@��Xग��v��M����l�޷�Yh���Gk:� �np+����3�=I_QX����v�Q����Փ�_H�Q����c��r�ٜ'��j��)+�S�.W��4�ɻ1��5��M=.�L��_i [�� �7������ps+���j�����;���76z�k�hP�Y,?[Ȯ��M;[Q%)�%^�rM�_�!P������"#5煷U'���q��	�:,�$�\l�d̟���̦��F�'Z	WU[�NuE���"Pej%?=q�|j���ߔ!n �-u��7խ�$h�WihD�}y_�"����L��]��hNj�,r�D*��N�b̙G��!�s��:��v�;IϏ��`�>�݀?g��c��������lqcœY�ؒC �h�BN{��A��@S8r5���)O ��	Ŀ=R��>L%�7���|�� V3�.M�tU���|��J��Q	���(�|W���h2m�L��Oω�Q�Թ��.I�J�UG���W�r,*EX���m����G�ִ�Y?��|�pO8�˯Ǆ
�Íö�j�{�v��pF#^L���a�00�%�v��lvF�5#�-b�-�54�K����Lc��n΅����08�v�@��P�b�Ӂ���!��p!"	~�&�2{�x��o�,�үW[�%�55y��8�����B�O��O�Rr+��qTX����hg��-���@l7�.�Z�p�:�<	�
����o~�O�\��\7r���/Z��VI���f����҅��/��]� ��@�%y	Od�B(	75�Ws5�ea&X��ƨ��V��rq�R'`���`4m@����?�%D��;��7���RScA����>q���jܞ�@��q�7�{hbNM'�LlYu(j�S�v�A
�G��i������S˘���x�djUE���`�����v<0*�"~T�./f���C��$�qՇJ�rm?ݐd�������L��`2 ]=׼2N�^[N��
]�:�B�������U� ����a�Rf�a:�t8ͩ�m�U��w�uI��PK3  c L��J    �,  �   d10 - Copy (8).zip�  AE	 �k��SE98��p^�ps�~��L*�/�����$7������X0�������b�?W7-ꉙ�(���b+�P��g<��ɦ�@��)|��1��Q�
������s��k+����=�H{�eOaꝗ�>���k�DJy�H����=��)MyK�^1�
�b B�p���6��'��T z��5�IW]���8e��В����`N?pf�K��<�l�ϴ,jN��� �=(���<�i)Lō�_�!X��=u�����f�x�;*�4�	dX�&������C����v������q{�Vt�� �V
�qEI�>q'���1K� ��/����yU�!Y���[��ju����L7s_��vs �����4���ʁ%W^0s���gWI ��Vr�u&Eڝ�2D����c�7��Fj�(]�
K"Gx�L�4��ƍ��i��|�i�;nȮ�8�����A�Z��	�3 ���
���X8o�,]7Bi�y֗P�b�JL��J�r������w�2��w�:��VKFL���3��C5��ޫk� �;�Av��rO���4h�ڷ9�(��&	��6����o)�v2~9�'�v �5���������M��9ʕ
Bf�EڼNQ���V��<���!	:��vq�*|`%Y� Kۺ,}x��\v#1�T��Ϡ�.5��zd�/:�s�G}7,���Ux�|\ʮ�t�Gۂm��
׵�)<���c�P�h.�c��E*E�����Pe�@���_��u�2���|,'*��K�:���vk�:��5�^T-j2�Ƭ�AoL�v
���6��}2���|�Ø3�����\��q�9��8��arЗ����ѳ	
�i�P!a�vPҀ�k�;-�M�_{O[fk�p���+Y�SE�"�VC��xe3�%L�鴓�3ݱN�z2�����i��������JXVEz��r�d��	��7D�FwW�Z֭ݶ[��: �8�Ԑ��ܭ,�E��ɖ�a�/��$X>&��땲|�
$�InJb6� Z���~�3�X`�.�ebs��y�����OD/�{q���0����'˓��
���y೔Qp�0)Tځ�l+7��f�M�t9n+J>s�V (rK�B���1�\��vr�� (��k.�r��@�n��QH��9�5�}Gd�Ѥ��)B��T�N�K(��q��^��\�w!�R��2�����%f���/��m �T�do��`|�{O1J� ӱ@S@�o�Юi�
)
(9��+��E��6�jH�T�l�i����lĿ��_d�	�>�ɢ>T������B'-tSylIL�����wT_��Sa9�~[v��p��`!�F��c�2�~+�\���R���ǳ��F���RN�	�g�R_ʉj�VXm9��,��Jn�{���&�� �6*�d�قAн�^ҦF�ڙ�4��T�����qI��w��������������2v@�v���t�d�>�p�ب�B�NpZ�\�b�P�^�T�*�3���SX�\����&6�] �9N�(���]��7��q-�yQ�a�+��'�=V��b;aC�W�,�ǩ�/�����}.�������z��G��D���΄b�#?U-E`5�%dG���#�8�,Qk'�[Ӎ{�(t�����:��Eq��f��gbbo�<�/B��s#8M��o��]�/ ��{�~t�wW����*\x7W�8��ԛP��D����������Ψ ���y������QS�%R��ʈuTF�_�(ץԗ}9��ٞ�p�Y<����*��ʖ%�ā���MB���R���
*!���G��������w$~�������n�l�?Tim��i�{Aoo?dB%h�l��s��V��,����=Ϥ���_�j�	_Mua�A��_�nَ�
!
�	��J�G�.�%8��"ІҔ^�ѝ�$�Nd�j�O�AL�9ƻ=�$������Ҕ5����!��]Y��g��l���S(���7/��OƝ�-�m��[�H��ߩɕ�u����oO�[���<� �l�6F��Ȕض�(X\<�1�~������ؤ���q]g����֤�\&�*�����n|��+����`����Y��Y٩Lf����+�m]u����vZ��eQ�G����3f5���%�\��yͬ@?��l�}}�1�sI�x+PG�	�av��L��)c0���(!�pl:��$
�r�Qճz��H	oز�07+�o� tu�ޤ���3�r�r\�sA����E5V�w����6�����$@�f��m/D ��[� *�Ccn�!�K|/B|�U>��h�x{�tGC��,㘠ޅm��?:F�]��xe�&�73���w��ueTX6�ߋ;���/A-Ъ�J��1aFYz��च���G��,�J�=�����W�z�9��ڥ�п�jq�=��$z2[U���h{���J��Q�H���o�zF�'�Ϸm��>f�c����N㦠�wƝ�NK��pR0�Mfj�z]��ܾj�dPzK�_@�Q9����l�%.���2dv� �O)�<�A�� �Y��{1/��X-�����6ꕾ��S	g�0�	(Ȕ�N;�س�dqj�It͖�����^� �S�1�.?�7!���9e�j=�f�D��J-IL��!�W�	��ģZ�x���!XV{���cń�8�;F8w��D!\�|"���X��C`�k���QXr�`���u�Y\�����t0M��cWD��a���l����k�(`5��̠$	�l�^�2ӕG��,n��{ļ�����L!��B[aٶ`���υB�V�~�~I+��5+�3s>F�-[�>����j�û�����ڮ���	3U��)!����e�j�����8�W1pNr_� K2�����FD�zlr�������.�"lz�� �mFrk�<U�/�G��?xW�H9�$~������A=J��{�0�1M��@h+S�5L>�d�O>��U��r>�ԃQ����g:iBdT��:��D���1�効�/���a�Tӝ$�Z�b��j!(q����>��Y�:����-�X�d��p���9L���6�`g���9��I�*�P -�E_F�!#�.�aMϊ�\�'�C�b^�:O�<�{����ڕW*��߷O��������֌C�u����F�}��X�Éu��I%��{`���il4��.SN

�p�0�D�|.�l	6^����jM��`sNxKMn#A�N�<�xB�R�4Vqw2W�7]���[��ws-�[�`��@j�x�I�q�����
�w�3��vK}��ȑi0:>Otήu7� *{������?��B��#�=Xm3\;*I�Ú��>f e K�7�t�3[2T���.��3��Y���Tޑ�ȍ���#ݬ�Uې�h㡦0�of�$�n�Y��ʹL;�q�=HS)h�l�\����ъR�Ko�9�@h�~�0��ٰG� ��S$�P����Pp/�?x����������.��2rs����*��n;t�?x��W#W�(�epA�3`6:��-Q�nZM���[��e��p���{Dm%V��w�#W��FY�;��J3w�J��@N,L����5ۢ�zr��?��B��H�y�:��N�&�h�|��il�`�m3� m���$_�	>��GU�䈺(i��O��+����n,(#�0<='�Ff}�x9E����`��p�����m�a+�=!,w�����z�v)N�$!��92+ӝ��s�|=�|fWj1(��
	 �B �k��v��{Q���Z^��"�[�	��#��<2˱�FQ��ƖK��n�=���u��xtI�XZ�r�z�v�h��ka/��*_�lôU������iai�K޴�IxY��+�p�����Մ����$ ����"&���)�S�o�&r=!ծ�R[b������~_`��|����aP���y�4��m�P��0WQ�
ݑ�{$S,)w�t�r`\㜣1�����Z���b2n|p�ט��Z`�t�Z��g�8�B��(�U� 0�^���]P��b\��\��u��+1m�­�P4�sa��.j �q��0�AqEp��Am-��2;�R��Sx���7?/�&��,��n����ə:��C���S�p*rGR��o}u���,�V2��{���l�X̰��A�u��
����1���y�5Y�����$�?��	���g���u!�VE{J����O�4����󗬡B*i		ՙb��Y�l�R��+�t��8�xi����b��r`G�y'�?fC���צIOrx�|�Ҭ��X.$N&@V��

L쒪*!��+�:/G��T#|���0Ԅg��I��U�  �d��6Ʊ��N���H�%a/l��R�|�"D]@��hg�Xvo]E��፷mvE�D7� ��)��%;W_> x���f��PQ�� M��F�%n����*"H��j
n,E`������e��5?u���q�=�	p�&��A�c=C	�p�L��>l����2�X�S���VRԐ�򼺈��,dꄞU�ӫ��ʵ�t�8�^>��q��i��Wul؄�{�I�kܾVƾ��«�5Iox�\'UJ��߃�l�m����XBg3�o���=��OUR��|�3��\�$QV j���=����|˞fh�n����}��_G�����j�,��x�G�G�C�b�8�l.�C���k����P��qc6\ʮ����ʚ8��M�0"19�V�O�f�a���'Kd.p1��� �,/Jfs����������^S���n�G�ۮ���|�C&p=��
I����E�c�(w��oB�EzC/��^�U����q�at� ��(��#f����tD����h>�z�6#� *Kq�CpE^�8�?��Bt�7��.�:ܤ�,Pl8��Ȅ7�%##W����kJpy?v��.������#�w0�郐 ��BB��t�K�"
=�����v9�c�.r�VZ���v]c��)B`��2K������,U8ޫ�x�A�}�v�\�; ���qQ+o�6�'�&`���<q�Y��.q����Pz���� �>DF>3	��'P)���x�g҄��`BtbC����DL'���DI|�N�-�"��Gzyv8�P�sb-��a���[��t<��6�q��?���(���cFvW>�r�n*U~|�Z\��8T^H��cP���/r�b{��@�#[��Ѳ7����|�~!޻�=p�<�WtЫVvVn�n�*�b��w�:�4tb�&r�Ҋ�A�᧒�IUm:��h#p����a��m<�Qhw3��0f�3�$����� i[H��u�����ĕ�&���+��0�z�y�'�0�!Mks�'�ͅX�A���~E�}^cIB�B>ֿ)]
��G����&�.:��e=�?��DTǭI���]1���
"?�+r����T^&w�Wj5�|��ܙ�5|��2_nw��[<'ړ�������������
 �MvGOq���f\��6��?ێ�3}b�9�	LU�Ή�:�CG��b	[4�^ˍ��B_%����K�����-Kڿ]J�XOA
eP�vq8��C+@�����YR��$��w�-����Ӕ�^�	�N(�1��΃�����#5)�փ�~�$���>�馐D�Fn�yc)o�,
�5����'��f�{`���F�b��m�:��'�I$��ilW�2k#0DYBR�\��F�f��rS���.@ ����ک�M��Xm�j�tEZ� \��᜵�ʩ�%`���<VKD1N�B�C�6ܘsAL�*uق�w؈���0��I������X����A-�EA�TR�[��U��AM	�6��Q.(N����a�\�I�v��	����ח
�Е <K�*=���|����w�vO܀���v�O�?�_
i����ZG�rh����O��7���4we�/����)Հ��Y�ȕ�<��.j\mh�+�1`v�B�S)��@�A�R).�6���W歫P
t<Ն�}�D)�4v�q'���}.1�yy�!�C��v����]���r���L�N2%�#�)a��}Jߋ���,��S覅��M7F����^�<rm0��,�4.�<�X�Q@��/��.��^��W"���!�h���`��aVL���\ I��,to����MB�H*���Ab�{�ϥL�2�IM�VR�T}�|�k8X�uU�R���ęC��5�{�����5r㗸������,�\H��.��a.�X�Oae�W����<(d�(?�8�*���ׂ��[��.�ID]�[�?���]U{��ӿ��?�>�S������޹B��EJkE
�]L��=1�Hc�x��*Uq��	խ���!\$ ��T���"g��� _����˱��a���\��;6&{���}���pG��u�e�Jr[�F^e��r�2LU��;c*k@x`s��+S�	�I�N<�W���E�:�) |}KۄL�{K�)�F�~��X~��W�;��t��D�� �� &w{N�@Tl3�.�Je��q���o�EV�7�Y]����	���4�į��@��ёɌ�oM�$�A-wh�ɴ@Vyj���s<P�+rn`@^���R�r������59%�x(���o(�2�ƼQ��u�*�:so�n���W�ӕ����M7��M�QE7$�����n:��-ޡ�َ�sUp�����H~faDp-?c�̏%��O1|"5+��F�0�|�
:]Ճ:�~�C�G�r��G~3�B�i���ֺ˳�����X�;������8�ͺ `
n��8���GO���HUD�M+Y�x��n�:����ڿ���P��% F�w����	�3������@�i_2�@��o�@��?��x�*d9�%gnD:F�t��~!�zZ�(W��S�K+�%<x7����`��4z�tEzM��/��@�x�._�mQG�	��$c�f���S�����-�RiI\~j[����F��8�e<��d��./�[pubw2N����n�-NtB�;����ʬʴq��$e�d,|�*��1�v[,��C�Z{�=˸�PA�p?�^%���u��������x����J�oIã�Y/�b�:�lz�8zڣ�j�&&+܌~�A����{��b?6.�di���=�"!n���EO�����7=��(�iT��R�9�	ʇw'#��6��-W�%.B���9�)6`�c��k�t#�l]?x��!��!�-J�|O�� �F֑�ŝ��e����֜>}?�rx�ID��TW��!�s�UX1]�1�DB�wp}������1��r�hmdY��J�F�M���Ql�52�gFiR�:�wC?���R?��k�$q�_
�q[>�I���
���v��ĞkWOv�P��h���2hZ���|��KF�I�� *�Q�^���w$���Q@�1.�[�r��d���m&
���e�1j��S�/X>�,�7tJ?�����iG)b�dY�#L�͔?`9xDs�&�j��Þ���A��s��(�`�`s�|"UY���N+�!r? 0��m�KE�������܀��J�;)�g���_3Ѧ'�w z�'�
> �D&�I����m�E�牳�H9׻�����7;M�|��:p7�:�ƅ>���>e?�~�!r����������@1��0j�N�� b��3�K�G�.v6=(���.� Ud �l�WdF�<�}�[��u����Ԝ�R�����h�jDi�jR�ֶ����$1�h�a���F���̆I�$�	�	<�XuC�Cؽ1K����1��ns�j����=�$�r#]��vf8���t���t�� h�&]����eK��ZE��n�Eu��������\�k���G�$p/8�'w�vQ�afQB'���~ȝ��%�2�����,K��j��^�1����,�����w�t� 4�ӱ�#�:�JYȕ��Q���y��z�]7��7<i��R쑅#�/�	*8�N��/5�gDaP0-V߀Qu�9gZ�o�c��_�;$(�~��m
aU�b`
�U^�d��k�T2M;���4?���W:�z}h`��hɼ�%�
��9���D!W����_���)G���Ý&+�Tn@���<��	D~���@�D3')�z��p\�k��a/G趖X�[v���ڞ��`����� m�Èh���$�m/0D�+Q���Aظ(�Y#L��3-[�:��Uӣ 2��)���z˙K���� p!Oi82"�J|G�TP�W�ط���������Q�y2X�Nw�brs�3��Zeol���s����rW��^	Ae�.���>{��Z��F��!���2��g���������E��M=��!V�j���s�����\���)�
U��4������Ƃi:Ҕw'�}��]�W1��P�&�9��U�B��O�3�L�k[Fu5z7��.��� yTb17C�Ct��b
6Y�(+/5>)ac�:�/����#��S�O �A.m��#�T��ą�n�E��D"*�`<�iG��$�f����F�{�S
MZ;�h��[%��3-�C
rE � &{�G�^E=��ޛ���o�R t�2.}[�$��>�h�AW�b�r�j��>����io�G���e-P�}�N_�f�Y�!��թ���ۏ����kZ�l��D~��FF�3���'��I�d�CR�������I�E�=�a'�*NuN�ZH�I(9X��̱*|_�+
_�҄o��C�����_wm@�㈂�\!K��^`� ��1+�F�s�h�ѕ!5�����D�����hԫ.�f��Ws�3�*I�c�����X�����$�~y���6��+���$�݃t�r��J&���R����`F�&Ԩ�&�6h�����f}F����/Ba ��l�ƭG�&�;���$��U�4Pُ�S�nؔZ�η�Ղ���M�l�$����wl�β"7���#�1��o}n�;��[�|/0�R7zc���a��6�������(�8w%����(�M,Ce�a�uL���<��D�--K��aXB�(R��3��������
C�c.���͂�3��<�vL��-��J�ֳ��W��#�e#%���VH�{t��6�@��_bA�C�h�������O�.�_g���9���0(�(B����l%~*h���0�M��ף��e}��6�G�aZ��F8`�����R�R��%S���EB@�+���>DG�J��W�}ȫ�2�qa¬'�h�����Z�&:�x�]�Y6;�x��k��c����o|�.����^�s��ƨ��*���D�{���wƎG}8��� ĺ�`���qRDn�ea��J���?5����r��O(��ka��o��Uہ���
�&�b�ź`�>����A���$�''�&T��"���-��R���2ql��;�5z�B��s$��J�)7�,�Ftxia�7��_�#8�Jҭ�}ZO'�Ua��d�����X�{�<_i6��j�J��ёéXc�A���]�k�~��A%1
�aW9�∻��4Ż���pB�y���Kd˪4r6�G�C&�vd� �'��N�ǉp�]8"]��)�Fێ�o����-:�4�[D��K�\����X1��YncO�����?�jhH^�l�����v�gz�ђ7���ش	Wu��/R+iCu>���
g�%|6X�ֶ��<D��yƂ��t�h��������*t�P�lJ�l73�J��"�m�F+TX�,1����)��a�#�h�V�+��.|._�x�{�T!^h����Z���8Yx�^@���,�s^4L�J�,�|�����z5D��ߐ�Q6�,�����G�1i�MT�B��rN���]��P�}pEz0�yHz�K�þ�̽��#x�5��	Ae�i[�(�>�.�t�k���m�Uk�-@O�9:�--�8�~�T����!YS_ۘ����Y�P#�qw��!9)*�1�r���OKe�[��x�U�寬"I��7^n#�C��K�N��%:Y�e������'a�8��-d��f_�'��A�fƎ�;2�,�o�B�q�Q�Y�Q�]t���<\W��w^ *��o���[Pvn�.X������%[�����}P��O��xJD�B������x�<y}�A3GP��svh�"S7�a���*�.8�p F�Ͱ~|�^�;<�bk�����@�9�*�Mtt���b�1a�� ��. �R�J�n?Kq�V`{Y~�|:'�1ȸ�iS�$��e��F���7K���c�}��x�u��Fs�|m?W=ǨN(J�Cڍ�4q	�we���I�'��s�f%��ikFZ>vZh�ie#-��Б��X��4��6���s� �`ʪU�")�K�_��|��uY?2�i�����'�A���@���B��*&ä�A\��3��LE��<r��p�S�f��7c�-�&�,���A{��9��t�AG���-�F�8�h�w�m�}������mЪx˾�G�x=��ܪ�0.l�z�텝�.�X�#����+a�w�Q���f�Y�~^r��k�]��k��0?�2qVЀ� =ɔ�b�jd��p���z�$��a�q(,�8 �xԔ�0�:��S1��ȃ���Wz�Vm,H���(G*��?Oj.��ܽ��h����2���[[\L�=>>Xn
+ļ6ɳ�d�\9���[L�7������hϓ��46�s;���nO0�R
ѯ�ˆ�;d�;[��\�kE��0c������"��-�^��GI��9j�:��nu:����@)& ����ݴY��E~���a������],�����t�&�Q�D��R���⚠�!tVw�����7��P{�+H�C�	��h��Cϋ+�RJA�xM*�� :��Y=�d%�i(�q��B���k��-6Bٰc{�I��<�y��~*|��_�LbT��蕵� +�G`�t.�0f�����W�d��"��?#_TDJ���;� p�B���s���w|r���� Ń���0��M�^9�%w������$^����[�`V�GS��������z�e�ݩ}YƵ`"o��V1�\�rr�/&��ǂ�3�H
�ż��H�b���z��o#E�u�EQD���P�Y�µk�#E��\p�IX�Vc�:�]�?�d��(LLǏ])�Q�,�����DGo�
ox���>�}D(��i"o�^m��>C�th��0�X���W
�}t��|�z��۶"	t�+�O k}��4�t�yvp1h�nA9����vd1�_�L�Ha�s` �s����PK3  c L��J    �,  �   d10 - Copy (9).zip�  AE	 ےK�IL�~�$@)�����1����}w_a��P�zIg��8���m)�,mg�dl#��� *l�)���6˛R*4�`L~���H����hɣ� �ݻ&xz�#�\�`�>s��7O�F(%�i�z��Ae?��h���X�d]G�|�����=�m��6B��1�o�$����J'4�)��,�~�<�.�M+
��6�M⎣��i�T���cQ�cHE��+�`���0م�`�0�a�ʖ��������Ñ�]���N-���n����ý�ݳ#զ���4��"߇�f�O��TD?�Hj3?�[<��[/�n�M��F�--@���8�E J���@��v�}���a;�yP^�6�<���ṥ�*�6]��k��aI_���9�nP�Q�Þ�=��\����o�YKK~���e�v�I� �d��!��XO��MԀ�>�~�C�.,
��ru�$I�|������D�:xc�@��Y݌�����+*r�R-���qR7�.�j'ڂe}�y$���(����-��y/����,��#�<m凤J<6�KN��_�	v�V@+��S_����ER��]��/-�M���BC������S��> ���PD\]�~;�<���av��)��q���>��D��QDU&,�����Eq��O^��XyN]=���	RhUo�v����.���0v�����D�����D���/Z�QtE$2��B~�f�!�4��x��?1������U7e�q��ғ\IUsL�B!"�T��'nh���tj��]�.�1s�jb���7)v�l�إx3B���R�I�����5�`N�U����W���~��n�F�n�Z��?C_�t�R�w(����t�X��'0ĭUn,��	� /�7�U��$jR>��6���A�]�3��+��հLD���DR��l�`�����A.�`!�����A#ஶ�X�����w9�0��h!�=����b(�"FƎ�EÓL�+��
��f���� �����º3ݰ~2��/��'�d�[����TH��� �,=Г� H�r�,צv�Q�*
Ht�M&���8�/��C�s�'��&ã{�C�\i������hON�n:5q�o����7k\0�Ɋ�4u86=p6�
�.��]�����a�p�dX����pj.o֒���^8�V$o���s�i�2�!K���_B����C���[�B�.��i|�m!!��-خ�g�զ6�g�qd�0����
�y��V�\��J��Y�M?#ęʢ#�i&(٩�/�SpHh#hv�&�*��aȄ�tnn���F��@�3A>4�X��#R�!��FR��2�iB;�9Y�ҕ@�#JFR��u���@��@S��d��8y}�%צ���zS�g�`,�M��eP�G�c/�*:�/�׋1��fH�|sr�UN�H�N���=�E�^�Uw�;1+J=��(P̚3���~*��s�L��*��)Vt�T�Kl�:�岇�R�5���͆�<��,�����;�e�=٭FZ&*�Oͭ��V��Q��}@̐�K�$j	�����ň����ۭ�bu�C�f���2��w�p@m�[�2R���� �(�sXԜ��PS��3�XwD�����6䰑
=T$��l��g��lC��t$��Ռ���((���E��%}���\q��J ����XR#]
+����}S�s���bS�h4�j 5 �m~1��>zn4��P�i)�60zp�B@@V-X��t`�>��G<3x
�6��e�g�:[t&n�����R��W�%���h*$�g��J0�*ޏ�X1��a(�y�}�x���3(q!�KF���%7�H�i:U�3��k�
Ȟ�����*��r&�\�W3��h�z��9e�\{M����@S���(�F�%�:=2��g�= v��3$��]�dFnG��ru=՗r�u���c��L�f����',Ǭ1t�e�m��/Nu,,�W�rq<��E��Y������o��gi��h^B-d�T"�6t�u;_fn7�B���ApS(� f��c3&Gu�q�j���o^U�`�ŕ�A����������7�8O�*�R��$ >��݋�c"җN�!�4�]�Dd�
��"�I	���b%s��/�	e����d$�!��1�&*{�����¨6\I�_w�Xp(�)�%} r�z��m�R�f�^f7z1�
?J�S�#�35�Y��^����:�;F,w�nQ����:&��`F��Z�v���k���\��mN�2���j	�����x�>tY����;���B���#2MB͓����8(�k	���T�w�x�A�NZ��3��G�	����x)�jH�;QMI򧥽0��jk�eWW�]L�~!�<N]���8/��}?Or�[$���q5�d}<͗�s�)�;bj<郣�W�!��o�L�ou&
�����ϣ��7����؉�bq�Y.�
U����T��ȑ�/5��+���ih*���H�잲�j/���+�%��S�زP��
%C�BY�Y�����Z�"Փ�q`�H�{�˘�'��Y\1�]��/�_w��WCS�v���q]���Lj��wA��"j�%���lW�#�X>���pfM�׋�a%�R��D� �a8A��Bz�m�B�[z�k>��N�x��TP��W*��2��hΑϦ�	Z�`�w�_��vi?��6�]�V�j��t9~t~�ٳ��N�L�Ո���?q�3ˮ�t�Ku�$����$��B���r�04 ����S���
�3<�E��W2���E����]�G9g�|�H��L�5�k�L6|GYm��(C���HӬՑ�f���ʈ�?BT��6Lo1��IR�V�k@C|��y�;���28�� Gޒ�%u?@�ik/���d�X��ם�+�NF�w~m��-���^n�Q:�Zi���#�^]%��l�:V1�OB���/f�|�#�5Ε%��^�����xɣ�q��3  ^�G�Ƃ�1�B}J��5%��]�q��1��/��w�
��n���I�|c�%|p_d"<9�*������0���S��Z���x��MP�9���@G��U�JyW�y��%�7I󄌔�֦N-�Y-��l��u�X�2�}��C���ڢ_�!%m��O� �7�ę^������n�z����C��	�{]��d��K��IM����Ի�Xgpg�K���r�$��aZϒx��MW��RW�R��Σ�!�ؖ�	�u$yC���q��	
����Z:/(�=w��]��I+��%�$GI�w���.O��}�I�}��& ��ɒ9Q4ȒK$��A�df�u�ԁ����Zb��(.�:����C����O�m'���{�H)�G�,t��	��gHW�K�+�!����<t�g�%�gً�5<�ϧ����$���\�.SҀ}�j-�h�`2\��o�<�ڥ.<�bBv��D�e8�:�|�=&��?�W*}7���iP�+a�+D��V�RnRAFЇ��E�d��h ��MO�|��A(��[�:h�ãzBE��Ä��hϖ��,D2C��y��
�c寕S�̊�X#���op�UC�|T��f�$w����6�lȘ�L/�����-H|a�о�������t�2)'�8��
�1|o�=�����q�q�������W�W�<����;�����?�[�G���G�k��-,zY&�?���3tD�08ogN���-�s�������Z��I�Ad nc���6�p�$`W�.��I,�z���֦����zL�W��ɵi�j��o		�Ai���0H2KSP�6;/�5���xR��dn��K�۩��u�з�v /B��f�LJ��@.�{O\ћ 疠��Q�V����җH/2U�l�m����UE�5	# �5duz�*`-VC�r;�E,T;ތ#����=�"�W�I%9yTz�	��r������fk�o@Z�)r��A-t)5��e`â�1I����q�y��1�q9��4��^��h��0iKo]s��*����s�c�(��5���w%�֙�M��>u>"Jq >�8��� |��:�y5iAXfG�Q�
 �9����]|�[ ����xd�p�$��B�m���ַ�iI��Lb�m!�`�;�� �!Ey�Ѵ��T"�w<;���Q�9lm�5���#-�su���S��u�4m\U �
�\i,���5	��,�Ĳ(���0W�������:1���{��8/�%��;�����9EѦ��E\�L�z`@���+��0erP��g	�/"V�w���`q��U��eV�װ����+]��Q��7L���l�6�c�
BMqe�ڧ�U��?Z�w�����90�4B�����ly7y7E�;�x����b��W<�Rڅ0��p�\/Ѹ9���sss�$��UR�@ h�sn_V|���	�DA���@�e>�q<���/���޹G*�q��Ю+M�_U��u��Ƹㆹ<{��MIt8���,�]�H{�S�Cy�{��!���L�2�[W8�&�Ǯy3$��4�X�C�y��=���c���'�k3�Aw�X���{ ��m���f�NĈ.8�ԜbD��#���>G�,��c�P�"`eDXm �H�;���4`d=ڌ�Z�2��u�Yp@լk)1|D�i����uEu�nS�N`�7iM=����Y�t�;F'�Aݽ|8���Pkf�^ˀ��z��S��{�_.
0���d����=�Ŀ>��Q4�@��a������=���3���? :Ic2ߌ��`��d:���_����@.��'C�θD�7�� �ކl�V@����EM�{�}�˵} ���+o ��(�D/�R���	��"nG��v�V:�-��U���-<�r����"�����/Dʜ1�-�?����_i�僚~�S~ ڄ����B�$kd�|J�}	ka����n�KI�/��ބ�s �*N[Mv��r�~��ȳ�u`]���7�C.�좦ZˎB�;���'��8D^O�/��Pa"zX8
f>��1�nJ�df�#pJ�{�J����h|�kYdb�o�(���$=Ԝ�ǩ�mL�%�!c

_.��;�EPN�����:���:gH��D"��D ���@�h�~75�/kp0��3�t55���C�hQ��D�_EF�����[p�:;��(��T����Q�d/3��E�
A�1b��oQh�e�-7AI8v���<&>[\�N�LXƲ�2W��u�9Kl���'"p��4X|��ϲ��u�Y J]Y��`BHP��:;�,��t7�����_.�w�Cnl�D-�̪�d=�	�>�^��uO54\�Z8�D�O���z'��T���Mȹdg ek��eA�= ��e�B����t�xN3��j���(s8�ު
5p}[)�G�@'Y�P+�)M�����OG
����
{r;!ɏ�W��!���˚� -��l*M�sSȱ���s���UM�k��Q
�=��ڻ����؃�PQ����/C��������Ym�h� [��z�x�mR}�F�3���M�f�x���D��>�Yj�n�^�H�^N�$Y5ن
�����B1J��kH��I��T+x,�WH���=c�����49������Ç��}c닡�X�(y���6����}��ޚ-���ԓdxA���`VF6<���p���>,��޽��N� [R�-L��3�%z���S-S���r�	64�s*<�bJ�#O��7���< fv^x[��w��LU���#e�q���ϚٳY�$���H��涓RM�D�����;v�q
7�7�h��1N�oJ��0iT�A�h��6껭���4��z���#��r0�<�bey�dF�xV@A���q@a�.�W/����i�eԃ�xH��wc�P	�9W��!zSx�u����H���b����#�e��RG������T�(��2Ӑ�?m�5�]���rv5���亠t{�U�>����NT�V�F-����a/v`|���0� �n�.�`Rz��Eʷ2�nWi�Ӗmֱy��ϡϤ��5E��xD�]>8�h����+�v�!X�&�����S��6�~�ӓ�t�b|)��>��EYϱ�^��ǣ�
Y�^Z�r�??�)�N۔� ��y�:�^s���������@tJ0�8C5��A��Kp�0�cx8��Ӣ�`M��(�G�(aD���>g��Y ����o���zP�l��=��C�K�kͧ�U��.E�Б��&Ï�]�}���u�5[ͣ~:n�S�͏:���tzc"�aX�5�9�
��$�_]@b�yR�xr��1G�Tv��~CY����5=�IC: 
ר�O�g������B�_3��V��[���rWy%?�~V�s��P^s|�F�b���?�S��b[*��bQI[Њp��Mq^����t�>�~�-�)~xn����=+�x�6�eeE-�ߞ�ȗ�xЀ{7����Hm�3�%���YI����k�̠���c�`C|U���À/0g��ĢI���յt��׃��^1̃��vr���,I����K"�j!5Kg���,%����@�
`h
Pa�$�'j�Rm�<�T�>�
�pX�K�7�Y%�����t������r�[w���@d|7O����osw��vsm�@��z�F�{�ڀ-���W�&�6��]G��r,Āik�,?�7��)�!4�m�� ��S�K�T�F��A�ˤ�	��6�RFL $�^�����HGȮحC�T����n��vC_+���@'�TA1�|yo��_IB�,%\�F]iCEq	����"�>3�����yO��c\���F��7�g솰V/,/���q=F�9���5�o�\̄�q	���^�T�
/F�EB�.3{�!���C�^�}^hI�^�*�&0��߂"�e�A�}��L�Ă�o1���|���J�"0sk(��}B��W�I������v�0Nģ+)��/�E���i���8���Fą`�^���z��]���"��bNW�E�	��+�S.���<0%Z��! �-,cʜB�����O�Gpt�o]w�$�*yJ&b���S�d���r��ɐ���x�
��b��h�v@���	�{�ȲG!u\X�H<����$Y�_���i%�����\�Y��,�\%�@������I�P�8�BA+	�"2��)%[ #�N#��)�_1��Hjr����j�����u��0��e YI)\�.`L��`i-P��|p����[Q���y���` Sq����60� �a����{ԿU�8�,�p�Y��g?�"~�PX.�K~~�z{�%�׽�'`��C
5â%��F�}�i�|�6��Y^D��$w1�ڬ�F�����O^O���ja� ih����X*&-3��zֳk�^:.��Ne�yE7�	�Č���S��O��ܜ(/܄��d�p w������ɱ�V��5���	iYv��a�V�:5�`~^�r������k�l%D{�)�Iַ�D;��p�F.�C��ٳ��oO DO��K����s/
N,��ͪu���*�����[��xz�p��\�z<S���O���w��v\���XhZ�"�A�kϾ8�aF�{>h*�i�px�im�����Z�!����w�$m��px�_Uι�|91I�A��/_Z�D��Wa�>H��A�x~� ZXԋ�$�'�j��{݅M  �/U �%���݌�O�:���x)�cqS�D�anԠz__WT�ddp'*�LA�fI0,$�,P��+<���`�;A�wʀa=]��_���YL6�&��VjS6��5ۭ%�;s��;�=?B0��1�\m�9{�c�؁�a2�t���1%�f]@�'�V@Q�l���8|��k��6E����<e�7+�L��}BI�.��'j{
$_�\U�\C.A0���Hw�_��brSL"I����p����
)]��*���i�G���bb�G�����@xRq2�R��/��R��CYVY�HW�����;M�4AբS�}�u��U��}!*\�pV���I{A��m c˩��m~�8��6�#^` ��=�+I�SG鬜��8O�U|V��]\�?�ͭ�e).ՠX�>�m�9{{��-Ze&'�Z� �]��K-����bq�__v^E�<�:����b9��Q�%B�tW�Ӹ���2��pԨ^�M`���4�X#q~� 0%;����Wb0�(��Ӏ��]�[v:�x �������X*|gmA=��}���L����t[�VC���ʪ�YYg����q���� ���K`[�g�=�����(L��7���	VǴ�>)`ࢽ7��>2�b�ɬ6Ռ��`S8R/6A����j?ݷw̿_'X�<�d�յ����
m��Wm���Q�i���ē����x(���({��.Y[��1 ��b*_��g^i�#u��,�T'V̥���`N���?��:5���ŋ$y����~)���$)^� �ȼ�S.��J"���f�m�=U7GJ�Su�Hf����>�߬k;�}!� -p�u�$_UI�+l�8�l3f(A�
���	��Hl�����B�u5J����>b�Cs����F�
Znt�ΰD�L�q���	&�d{Q�!N�f&Ԕ'}�5	��?�4�p�	;���3s׫2h��3�q'`�}L]:�{{�9���	�`1��R"��Qy���$��N3��M�?�g5�Q��C{��i�ӟ��ٜ�C]v�mS�W�S�F%����0d�&j+㇣��������y�P~蠯�F�6(P��Za0�ktњ�Чr�x��k��k��&���ׅ�A�����fq�Q`� ����ؐw�s� Øh��Q!�ZЩ�|��y����k�6Y���D��>]��:N�c��;��t��50���Ϝ��i}�+`����V�3��I��x��rdr� e �y�S�wD�7g_��K�vE��|f�r�#�s� %�L@/m=�=�n����}�c�r���MK��s��B��.����C�Y���R���搯`�]�SԄf�P�ສM�u/U����E;�]R�����Q�a�ԧ��y�vq�t)�)��p�G. :�#��������i��u��c��a�����揋;�'��χD �,�oIV��z;]S��]'U�1,���[�w�#. �]�2�
,'�/9 ��N?N�Q0��M��/�i�'.`O.q:��CvrD�����*���s��� &P�N��|��ޥ�YU;�j�b�œ�����g_80U�wz�����*��ܑA���;�k]�|xZ�o��Z�l�u����{����>J}�x���k�i�_�?9־	�?^�G�_��>� �
��5#�n���m{? ǲ��Ǩ�MLii�3�s���OکM+=}�.!ǟ =:�>gr��N�r�h��^�&�?���F`��Ϣg�'���ַ�5�����M�AZ����J"P�k�ž;���`��W�Y�i�Ͳ2�����3�D�^�1�ݨҗML���xH��#E��,S��B퍂3}#���������P�L�<U�4E�B�N{sH���(��s���O3������p}U`FL*Q�OST�����9
\��ҩ�z��9��I1~+��h7�׎/P�t]qM>2��G{���.��P���[��6��*��:����jN�B�}-��주��<���Qo*�����ৄ��7A]��P$�9=�A���?����Ė��ԣ�R]�ȤL��bD���S��f��h1�҅����C�́�1\Gk�Gtn�2���X������[+;	Xs�q	j�섷�l�Mm�Q�j�ց���>�f;��Q�
�$��
�7����u'��Ms�9�;�<"]�`�No�^��tK�{f�ub���d���W��VB�X <r0�RpOb�T��(!F��� @O�o����l��]>�� �@�ODZ.�	a�z1�lq<I���R�u���z9�θJ��X�;��yR�L�cB<7�7yYVo̲g{18-6Z��kH�����W�2�/���f�I<[>�*��Q7�<L�;2�}�"W�̶m�re|�Q�Ԓ��]��o�)�!�)�K����Bu���ʾ�b�8;"�g���˹Pl�Y��jCܑ�����I5°�"��)��g�x�_�o*	RL$~���uف;��t,[����>R���߷aD[l�y/�D���pנ�G�YW�j���-�.Y����������X�esw����*�~�Y?���u��{�yI�@Y�����5�6Z�#!�c�C����P���z-!�:bWb��i{��nm|�gD��ŕW���7;P8����Չ�z��U�15e�(no�롩p�X\Cb�1����)��S�7@��YOI���_Mj���"R��I��f�*;r4�ܛ�\�V��x �X��3G���n�_��sS].�"_[uGk�AC�o���6[��H��������[:`�u3�乎7T�����{�ر~v5��<tzF��	n#r��ae�۵��%<�
פ�BO�Ĩ#�����#�λ�B#1��̫�^q�]������?�'��b�	�G�&����@����p�Ϥ�P���U��2�l��vZ��Ӕ�h��`�8q�w�g�c�̝�9'VMK ��������D������+�{w�}\7�f0Q��,umlq85~�����Awt�%�tK�~W�M{Y��?\��� �o�P.��d�t��;��6Rװd_�2�x��^���g$�l0�_|��r:/�2�QS�D�A��G������'u��� a�͈�s�"�}>H�|4�~�$�b#q7�T�&��x�[o�b�^���=e���������U1�8D_����|����
{��<��N�F�5$@X95u�p�IV�YK�k���X@�s�B�kn�ʺ
�)�S6��Zr �s��	�zq�R�rt݃+A�6�?�I�t�.�i$�<�pv�� x78s$�ے ��^:������o͸�+MM4,���%�F�qu n.���Ot�L���4�o�=�k�5��%;�שc��u��Q�D��$��!e�\y�����,����b
S���v �k����4[�I�|�$f��} ��{��/2��ے��l�I�Ef�A��SNc4�������]������Beѣ��c��I�^�"D�����Qd�L�N�?��b���s�=eAN� t�!�B!�J��.Lᐉ�+:��O@��V@��[D1G�K�̏hL�����0�0Nd q����du��|�[����w��S���;������1yiO�Y-�M����6+R�"!�7�tjPK3  c L��J    �,  �   d10 - Copy.zip�  AE	 �WK�B7��m�H��mߛR6��K8����F��L*ZIIͰ�6.`ϻ�x�`�;t��#��T6Ru/g��������/������_����4,R�C�:8/��^a���;�9���('�hG�_��p�Uy��V�K
88��]<��R&d���YYk��j�Cg��v��N�����$�[�[�͏��}1^���U6͎���J@Sgx���D|�t:�7Uf��1c��)��nbJ#p���{�x�[6��1e�'�w��qj�ؗ`�K�G(�o�����ǖ���d�Y�䴧��Xud�����2�;��?��/�c��]�Q�V���A����6Ռ�9���z,X<z��n�**������Ic'3�:���� [���gY�����hO9�ѼČ�4'H-8Q�crfJX��,&Alv�t����Q�g.#�|y+�;�����t���U��H��<��*�[�}ŭ��F/u9��۾�B�H�D�׎������X
қ�ߙ����I}��1�e��d$w;��l��L�]%}ɝ�c�I� fG�h���@L�Q<BS��ðS3��%�	9�
jK����2��� _�c���y�5�9�(v�D9�Y�? j3���<�y��Հ�� �(��U�LD���pr��.�e�s4`��6�z��l& �Z&�s8�71*��G%���jW�#����/�4�)�Ot
=��Tg
�K��6��50�nhܳy#�a��;�T�����򅜨Hͮ�f�џ�o#��N�/���)���u���J����pN�~w��l��,Ë��檏h�t��fE����Q�x� /n��Z(����i$xH�F�p8MӿT#r0�JL=(�a����|1=�ć9��T�1D*ߤ���`������B(~Q��ЏE4D+�X�C '@�DS�S���]�!�iA0Txʘ=����ԏ�������A�yb�[�\d�c0�O�����*�̇����0T���=����p��ՓH����#��7OD�ٲ�uV���Y:Y�"͟�Se��[�.��<�/���6R�:t��ϗ�O>'՘a��qճ�q6�O�_[����������PqsT2�,%pÑݖ�B�r޻��)*��(��6eyj��[�3V��h��vX�z[2���5nE�ڊ���q�CE��@Q�Ze���5AX��'d����C����]!���,}Kp�!� ����➣�-ޟ>t�}�x��+��d{�-��/��e��yti({��k�QYQ"�k�� o�9	�_��ߒ#\�Zzq�2��7˰M ��͎��P�rf0My�`�gL�y��}���t�tJz��))�����/��S3PKre�r{�3��[�q��B�
���o^���-�(��&q�(=�ٻq�'�~��j��x�)�Ǒ�%2H��Wi~	��"�	~΍��g�[]����ӬW��$}ג n�}x�V�AƢϋ�O#���p\0,�v-�V:b$h�����^�fE�ӹ����'�J۴�b=��M���X�WkF�rBt`A���8�>����43n�y�[�r���k=77$�5~z"�@���ͅH5L>������R��ݺ-�I�3�d���`_1`[�&�m���o�d�c�7JH;?�`U3Ǌ1�Ԝ° d��WW�%L� �<�^Zɰ�}����} (0ͩtñ�b�^�x���)]y�u�I���kqCV�@��߲'�ԇ�i������L\.���vn���{_?@�[�2Q����4�8z��BI'
��*z���r�0f��R�i�M&������ڝ��Q˻�v��\gG��M����kR���"T�)ұ����%m���|�|�S:$7��E�q%�|��Ӄe�Q�e��E�?�v�Y��F?+�F'2�㭒c����re�<��a�t��[�����Uv�ĉ���E�X�O��ٽ�x���x��F&��s_3ʳtԻG���,U��� I�����bM��"�V
U�faGZ:A��:��C���՛UA��L�2�0�[l���A�8i����O2w��O3��C�ᕹ�۹����>/�>�u�	�0%���z�^��<yG?FR_n
Q��7���L����z���")#�m�x&x�G�IC`-XtP����s �E�`<�&��<�o1���ꤹ���Ȓ@"�j ��r��{F�-ՠ���
��!'r�W�ʦ>�ER�\x�����)9�]�IH���/ɧ�ƞ��EU�D�C1��.��8������q������5�H��RW�1D��/�F�y�l��o���� ��z/�k}ge�I��df�Qb���a��N�xa��\�uH۳�O�O�f��Uܘ���!U�J�CD�k-���͊x;F�O��?=�Y�����˩��Ax�}�b���Ʌ?�D	�c�Rt����3Giģ5�tԊPM��,����ߢ�)�ն��ڿ�g���F�M���܋�GA���}@3��u����3�`�n.��R��)o�J��q�7�y���gGx[�L��K�Z��ld Zn�#�ʸ*�+�D,h��PZ4��A���f��tz8!��O�H�"s(tCY�R�k��
9d'���5�b�"����DU
����v*@5ׅ��f�����n�?�
��l3s7n�I�tCjW���.|��bv���;B��i��x�?�/u�OR]���� �*~ŗLF��P��Q�9��Zͳ	׳�칸� ƁI9���{ �7=���X�J1�͈��M�pq��BoKQS�0�y�3�#s���z�3����k0z���҇�O�8F����M9oţ�p�Jn)���XKL���Y{>�n���R�w}��V]����wpZ� o4��s�"p�E�H��P�cx�P�r�����@!�R�M�]>8W�a�\uJ�ዤ��1|8�E���QQ����𲧖P>�d,u_):�&c�\#쁡eBI�k��<yʪc��/����d�N�w��A2���NW����.Z~05�N�@�	X-��<8.�I�S�)+DV&Q>aS���@;T�I{��g��3@��N�U��9��. �;��s ❊w�І��?��/��[�qW;�ϴq4=M���Ιj������N	R�+d�T�}98�f�0]������-�S�{�h�=Q�Yzh���* A�P_B�K1���,�Sυ�YЫ&W�z���QҊŌ <�X�`:[Gʰӫ&9�B����m=>���ֈ��䃗$�K�����nËB�V��a�"���]B�(l�=��?x��9ڠf�]�-Ph��ŋ�I���+���hW�#O�s��є"��U[��q�Z��G��k̷��[�p+dެ;b�#��5�q-9��g6��j���������];���2�c�`���Q��Y���$Z�/�l!1B��62����	���A큳;챏,�@�C�>���NGK�}Z�W��}n7�������
�u�j�jъAԪ�ɖ��Bt3�;��;��㰯3� ��5��QU�~�َ�!���uĮ��iʜF�'�w|��K�W�۵�'_�7���CF�$(=�V�������K����i�d�0�c>UF���͘@ [�F҇��8�~h�}�,�����(��8H8���P����7B�H$96��a����y��6R�a��ň���U�C�.[\��HV�?�HUg���	�1���z�ڂ�f2������r�}RV�l�VL#u��N���Og�>���P��pN2�[�5x"�����t/S�ٙ��X����s	z�i}� ku�L��#�G�5\tMX�- vG�	�i�?��I�%����ً���t���޺r�\绽<"#���������L��J�+�|8���L/�����dΧ�XrW�(H�T:8�Ι�(9VJ�Y��E��3R`/3�-|��9Ȍ�,b�^ȳ�����B�:�7�g�w�)l�e�eP��F,��m���~'I��@�1;�_
�I�4�$��xw�qO��K�WI~�[qJU\c�A�ރ�wm/���mgpͭ��-r���$mF��ԟ#�o�K�s��w+�7�M�&�~��8܇��w:�0��z��D�9���j��q����l�|I��^��0����}�蔔d�:=s�cA�$^�;���ա�'��5U�b�YJ>��bL�F�L��Ou����`��"R kw�)��L5{`,6)��3~�zin�On?D<T�2��\ڲ�c�X�.�p�)�q
�3C)�W�(^O�b� &���W���y*-W3ƞl�D�iJ{��Z���KU3M1�S�#�����&��$u|I��E^~�8$�ue�H����>��b]K�|�E��(�[���. i��&�	���Q��t�<�­�	��E��N���R}�s�� @U�v�	��_-��A��s�����R\���>���~ƭ������&]'���U���I��SƼ0�`8��)�p����n��x�K�'�h3�#�)���$���,eCe?{럩\�<���>r�SCBy�K�|����H�c�PM�Me�D.�S�Eo9��`�xas�w�*��:۶h��z�y�PF�SB���'� ��'��L.����"Y�^?Me&��p��󺇈�s	����O�����w��>q���9�g B��/��K�,��ʮ.l�&���Hp����ט#�������,1o��5lʫ.vg��3�*ؤ{BP��R}OܞP�7��9h�ק�W�Sq
���nD�@� �{�ة[���$�v󣼠]�$���e������~�ŦG)o�@d�ǜ�su�U%#E�{��(G��4�d&�����nŹ�?~¸+P�rS2�JЫ�P'Õ�'��%��i��LO<�	�}�m��)�����h��pA��x1[!��[A:n��#�%�_(]� ��\+6p,���y�n4{�ň Z��{Ǟ�!�z-�.���o�yA�Q�z
�������׫�%~�/V���n"�]�8ˡ"���m[���U������E/ÎR�PX;�x�	�'kG��\1zq%�tx�������8D[B���\n�(z0�]gD�-�	����>}�-E��vڱ�������1�e>���@;��ͥן��K��=0����7�x{�������>�Z�x�|��8.��z�Q���*�SE��x��*b}"����)T�*,Qt�,� ]Ɨw��ި��]\M�8n⛎���g�}�ۓ��%�+�B��M%^0�K�l�o/��USV5r���p��r�5s�mf�� �);���x`7�M���llbPZ65i�0|M@z�>�]5�Rj�>jV_Ib�H���鹛+�S��Np�U={�<\�GJt@����̶���vḨ!!ƒ*�9�I�kvg%ذ�E;� f�{J�RX�D��4X���d*`)a�r��0<w=���,1�NcWS���<R�s�Qu���	���Qr~��l��J�@�?N����GjE<�K�g�W8�$�Ի�N)e��J,(0�,�zS��cw�X�W�s�[m�OAYe��6��_��5�UR&��W>K���a��
@�W�\)Ei_�Z�i��^1d����0����}�sG���M�^�����w��P�.3���fnĳ-�wץU�0���a8�y��;x�wi��n�j�*��N����B>���!.H%-W�i�1M-�<eD��e��_�
"n�,�x�^�[	N�TEq����a J_�60V=�L�@h�� �i.�V����B3�Q��w?&�=B{��n<��0O��c%2�����������JFlu�=<խ��o��l���BX{�yrЃ5@��4�+�R�(S|T��ï����Ӗ��e���$~��������k��B-ة���gR�_��!�ǖ�T)�H��l)�����	���7�t�	:�sg�ҁ�K�mF�-��9\��<��窢�7�h. �Z|Ž7Z���NF�f���z"Gw$bh��'���*;qxj�v/�6����o���Hj��}�0�����1[���*�Ui�Wv1^ô����Y`!�W�2��J'�+<��J?�NT5Rs��H��p� ���-̩��`��V��z��`���(��	�A��`B��3=�������,y��E�9�A�Y����p�`��� j����;������d�px�����aMu'z 4�5m�ȢY�������ԁ1[K����6fk��5���l�e'@QM@��B2��X��c?� 
<?�LI F��Y�k�ϵ
�����Y����VaW)��u�NI��f�Ac��2g��ꎍ�5u�����y�_7��^C=����F�#H�}`���i��%���� |��H�hĵ�q����jn������j�"'\�.����cJ�Y�}��b������d��^I^&ڮ�?4Ϻ&�Rch���������B鯦3�`]�(ʭ�/���H�0?%�L��V1���4�
�i���k1W��k�7�ɛx��\� �7�� P��&B��0L��):-^TH|ds��a�9�ya����אYh3���u�Q���J�����%w���ƹ�l��(���o�dېY����9�r�.	BD�+�q�y�T��{�_
l������{tGϙ#^����4Z`6���e�����`˘E���8\55[e	)��Kl���%��F!���*r�R��1���c7�o��n���w*I�u�֜p�K�Ϧr4�8���n����%x�f�=���/�9q�Q��T ��PZ�;!o�=@�t��awh-Q����� �k�c�Kq_ �"�2��|�����A�w��A�k���B�������w�z�,��x�1���� b��4���lֲQ�ϣ뻱!z{�b�-�O(5'��c4������b�,�y��CI+��S�ߔ/OX�$+�vJ_d�q�n~Vr:�B����")�*�jW���\1�u;&3��'л����/���O2Ovj�$DU�I��"���oy��*�*K��i�80�&���؈���<�d{1վ�w�!|�|D�" �>�:�� 'I��?gO�"e/h/�q|��q>�lL�&u�ڡ�[���7������eW��Qr.QwU�؄�H`>�Dw"��Vˬ�|�J��\1TT_���gn�]���'	�'t%4��)�A+TQ��e�y1ܯ�C���Pk��ŐIe Y�s��*P�Y5v���V��%��Z�s�AT�(���k*��j���z��<�	�HRp㮿h�f<(�U�G�&� o�te�M�2/TO���f���4��yʁՕru(5ay�V-�mX3�	�v-��͂���^'1t�d��sG��Fݦ�p!�ْ�넳<9����i��pd_ֿ����?(e��ۭnC10b<6�_��`jχ�孴��9�-�9B����~����Nއ���<t?d��(>,�D� 2�Tb)-���宫9p�3�A�lO噍C��f��^����"�ynb�w҉0V���^�����{��)�Q�t(��{T1��V�\lV㑄��ku<��?�,9_g��:f�6��1z��i�0��q$�E�ja��hV0Q�����`�0SoѮ����+�4�t��P�LT�9� �Ru^}J�Fv����
bbU49Ώ��ND\��
�O.���IJ�-��V��K%.d��Ϊ(���`fo0��v���|f�6���� �}0	�/#����w͕YD�H�*;��C��3�I��LwC) U�G�����]�$|�D��C��Ʃ�,@�w+�yB�d�e/L�#�{pN1c_cS�ZRP����Y��m�����ט :�z��x�"=i"GD�Ѫ�������]&�&`���D��LV�j{��H/X�s���z���+�<����t�٫�-R���C;W!G��>���5�ʋ��9��Np�m�I�W�j�B0��l�> �^�-I�+O3D���b��֫h}�m^x4}�<l/L�
nt����yy�)�f�s���OR{��]L�nSt?����A�����V�P�2��M��%����@�ջ�ro��u�O��D�q���2 i�h���.���I�\uu�-q�m��n�M�K����ga�C��l�m%%���zW#���My3�	4��C�KS�P�?���XН.>���bkp-�ј&
����sS�w*��i|���$���*݅2w˜o����k��g�+��ڪ�]|n�(�VEb�8�9���"����Ź�����&j-X!�`�4E�,|� ����X�} �A$����T��f�\)�5������ �b��[���q
�dC⁰����I�k��C��=n~`1g[�5�-�����(Bj�1��|X��F�Ϻ������o-��Z�f4i=J�f��a���P�~�{!�\��~S�5M�|�Nk���w��Z+XK��զ'[�4�G2@#i/�Z�{k����SO��+��~��j/:�knG<�o3\(�H�,�}�l,�p62����LU��ubnP�Haǜ�0ŕ8���^ɶ�	�f�XC�p1���	�F�����Z��2����ݪN��R�m��0L{
4`d�æ�n��`�|�|p&l��ĈG����c�mJ�� �P�>������a0���=)�j >�S
l�c)>��Cס9�� �%��)[��+�H��Ь�������h��]�^�Ă�URF�0W����@m��
~Dɒ��n��t��Lц�,x��G�$((�M����D��(wJ)�|\X�pCz��o�:eU���ѝ��箅S
ױn;�CA��[��M�o�!���{�WW�C��4Ҿ�������qv��L#y\���p��N�����>�#��&�ޫ.s��g�'#DC�}A�M����aǯ��1��\M�,{�¶�t7���#�!�)�8�H�,*����m�	��Ns��j!�^ʄ{�����	8$��,�=0�LB\���-�&��#�Z�4�7��P�Ճ�
,K��].�)�*�N�Z�;��Mw�e��w���Z���W�~s@ʏh���� rg�A������2���TAs[؆�E��$J�>�Xn<���"�5�a�8��ic`"��FݍW���|�Y�};�����re�A1t��)A"�L��PBA^)y\Z�b�j�.HEZ��K��0e9F����-m�N܀�34����I|[��D?��R��0Z:�&��+ �����F6� �
5c2�s�����	����f���-j��r�d���Ǥ���tA/B^󈥤>�
.`�Oe���Oy�d�j��f|�;�TV������e���SK&�x�o�&���|یzEa�-|$_��3xzo�C�H���yk:1�^& �fP:H"��V�w�l����AeW?ʢ��2h��o��fë6n�;�7�l X~�$m�Z�ȃp���<����@�v�7��V�Щ�I~,��PL��{�Ŀ�a�3��J�*i=�V��*�Z��:��`no5A��-�z�i#ڏ�`!��ʴd���s�pf�ڦ�k�~r����C��#jۈ�.��W��(j�e@������X����m񼂌��������$��v�V���j]���U�X���8�!��bL��a#��|P3)�;b�&C9f!i}��M�:��r[_(��{���nE,6�ii�.��=��:N��]l&$���Uh�4\�y
��zz� &V���D�U�Q`OAo(c}ڐ��ڝ�x���d ���G��w y�Z5��1��H��.5Ժ͡x�2炱�pv����WM��R�����'m3$���OE�Z�<ZkH�qs �+&F�C���'4qtp �p�wQ����cՈ{\6�a\,9^�	������6�E�Y��>�F���,��c�:=�����`�5��1v�r`���f1I�s6�^���+6��fo$�h�Qp���@�'�Sׂ ���ؿ�8�q,���Y����?nܘ]ԃ�m`K����I�,�]>qE���c���t�9�q�>��71�L�p� c�!Jg׌t�z	R��d=��<�@L�:[o$�&(��qv��Ѭ���yb��v? ����B`�b�(N�}�-��1K-m�hcS��3Q��l�O��S��|]� >֓з�}�4��
7���K��>,�վ��}��}/��D���V7��nm��Y&�{�;ѱ�'R3���(��6h�7�>b�mQ�g�
����;�Y��,".\�KK�i�"��O3�:X+�8N*��nm����O(x�tL����6�x��,����+����.���=w�]s6��t��)�l��"<����� �a;s:f��4�"�}^T��sLl�����ww︎��a�;\M�Ac�w�La�'�2+Q��=�O���\WҖ�R����znd� /�����{\�t������I7�yP��/`�A8���bDF���@�pUl��� �l�jp;Vk��L�?��"��gG h�,��^d�5�w6�d5L���2.Lds�h�$e���$�2
_˭/�M�T����*��Hh��q�tI<������j%�7s�u][|!������A������
�,������l����,Qo��;a��V��}����<|�]Uf�f�2>W�q����~�Nb8�}�/�����R�1\>ۻ![?A�zC��_X�t[�m���)f���'m�&6~�a6rNfl����	@�]�/�+�a�[萖Ǘx�L�[�-c�(
6vs���qntY�#L�b�D����R���}9��gZi�	hA�B��Z�Q^q��R�Rմ{Q���[ȟ* �(O��%@D�i6��u Zb�@�CA' l9��4�ɞJ�7�kL�;� �/)Dυ�����b�`��s���<G���d��c�2H7B��M��� s��
\����6��*�7ث9Rٻ�GE��+ r��B�����ڢ�ʜ���$hM^�ݍ�k�fǵ	}����:>u���?�(�ȡ_;C�!Q����B��~���gww���R�����4����4�6!`�O�7�e��%��z
��A�y�*ko[���u_)Z�wk�Ѫjͬ0�{H�9����N}����wu���\��
H��nqk�D�h0�Y��K7�t����χ���̟�㎿j�ǵϼ@_����x1%I�?g�[�����a��3���	��Guf?��,�:�3a4S�E+��s�`�]�ѳ�V����� ���a��4�E޶݀�S/��vg$��ٽ�C�|q;�I��]�N5�h����U�&}����=���y�
�7"�*�x�� g�$�8�!��pؤPK3  c L��J    �,  �   d10.zip�  AE	 �����6R�����������تN�,�[��6�c��PV�'��>�(Gﭹ4�Cb�wJ,���+ѿ=ɿ��m�˾z�
�Y��x�e�:_ʾaul|�`rw����8Cۨ��1'��RS�|�QX`�X�Uw�z=�F,���M�Ť7��N�M�i!o��O�7�����Sc-��0�[�=к� �	�z��f"�ƹ�����R�":�j�4f�<�Ǯ��r=.كI�=e ĺ2bˎ����$%H�
��=SX\�Cڿ<�b�#��|�����U^�.�O�k�tf՚���<�G_�4�t�� *Q	 ,��)�z�n
5�ܕ�R����-���|��ԘlR6�j԰ Z�FX�[��f�`n��'���Ww�v���9g#c�a쌌�9��ކZ�j#5��y�*W���u�3�#�^�&Fj08~��ٿ�2����[F�Ƹ��[����� ���ܒ5)�^�'^���3��?4hJ,�=Ҍ^TToyhDG�߉'�+[�fwK�������s������,@@G\ʡ����/��
�Dqg�*��`,M�d�H�:�����ڏ@M�^ԕR)hhj���xL�Gs}�_P�ō�m������,Y$����Z�6�OU�~q�1�=b��+�F!z4���p���*x�h l��=�-�1��X����#Y�AFQo<\�DG2a2�n>��ݷn������4_��]�u��+�� �� ��3q��B���y;�wSJH+�>�;���]�n�zy�Uo��|�t5̰�V���x�׆�F=��󄰥$�qif�W�����u�t�_�^*k� �*o<�L��7h�<��%~r!�1���~Z5?�]T����7˱X�{9P]��2�$����c|o=����`Ǧ�uu@�(`�:ӊ)k���i"ޛo�gh��(Db����p+���8�H ��i���1<H�e<v�l-�o��_ǺsS�]:���9��H������+�04֛,��*r�p�\��4@�k�z�������ɹ��2�mKޱ�(���0Uz����-{T-�S-Z���MZ�GL��%!��s3�W�����C�J��z�	%�sY9B��I�[=�Q1��0�TA�7vQF�!J;T�7c©�j_�oB�4�����M��;���5!�m�G~���I��|�5�<���+�Dr���<1v�2������ KÌ��:��es�N�� �e��}�z��Y.K����x0�+M�̊)��`��[�8'v�dWQ�L3QR��6�i_�at�bԸ�<�pK���t�2�E�Z�Ĭ@�`�����]E�"E���9+q�� f� c�s���z������8�#�r��h����N��F�x���(��?�h�T�P(i�=�����#��M���EF׬�˅Q;X��S�8'gܽ&j4� �������I<E�OX	�@*wY�Z���a��_� ��� G"&,A�k�T2���v�e�<'fl�˵�Ȍ���J�c������ڭ7=V�hn��s�T5�ؒĠ �������D���]��G���\�!�UDi��žZ�gVU��ky��t�S���rmQ�i�w�{�8��6+	*Ֆt=�Q�oGt��V�ϳP1;7����D¿��a�滏��ꞈ�����@�d�t�~{��<uwL�ڪ�LG�@{�q �A�����B���Ѝ��E�]eI��E�]=ڂ�ecH}�S�	��3�ZF�}
m��]ϭ�=�Ͽkq�;Al9������
�K}����ް�؛(��$`1B���ۋR~��1����u9i��Ў��ɚ)Ts�4�Y�h�'�FEBQ�K�,s�wt�3Ώ�3��Yc���R�� fж��:���D�$N�L��.�͔����\b(6`�Z���!и{��a��R(�_�������X�/5�<n4�X��p|����$^�W�D Q�͐�"��eP���li�n"�+~��[���6H6�Rp��ZWOkIh�L#G������ё�e%�U;Zx��irU]��\��%����i��a�,�L�؍��p���K���i<'!���8&Aww����<�Xp�9�pu��^0�C9�M���}m#�Q�;޶��������(�\�s�9�a�庤ǚR�6��7��MZ�����Ā�n/4!}̜���4s����Бx���ni�������d�㼀�`=skg?-��&]` `P���"9O$͔t��M^oMG����tؐ������Z.�Yϲ���3��t��P���}S�mVEIf~�-_Zi�J/��'����t=�~Iԥ�Xر>�^,?�>�,<�զ�S��x��K��u@���mx�p�k	Բvy ���Һ�f�결�I׍koxAE�"Ϊ�+b��s�A~�,���M�
o�B�%��b�z�U�M�H9�
�:+��*·�r� hX5v��S�)��R[�2���
�^#Y�ir�ڂ��'���� �2z����%�8ȻЊ�j��+�.<υ�v��]�4 ��\-��HR�K�� _�c	�+��4+h�=i\��^��c�{洫e�N�`F�0��x8I��Q�c)���AB]?�����I�+<u���$��ז�_rX����>��3N��k1�?�W����L|�����uH��"<�'����)�%s��o"���U>�'s��+��U�D#ӫN�^'T�i*��\�Q��=aދR'6�!���k�e�c�0:g�W� l(���K���Yr���4����hj��odN��n��\�|lXk����+��J+�<��`W`dP�	�ݴNa^�ݭA��}�l���Xt� Q���g���YD�/�O�� �nS�g#�d�w�Z���*���p�`��-��n.о1�V��S�Jv]��� cN.�,J<���1P�{8�qS���<x�=8`x���S�B��cc��o��ճEo�Jp~W(2�W;�}
�F�'dn�K9�ٰ,�CV[�ߢ���EYl �t%j�c@�h�BN��ʮOXف	���E��r�hN*q">R�k�'42˺�Bvʀz�(����T����!X:��cg��(,��z�lvW#�����s�:��?�.!�}t�u�V�D����
�Qr���$�p>U��k�y.�>�0j�6My��0@Y=�( ��PH}�k����~0�G�`���W�K�g<nnf>����W�2 O��&�=# ���j����j��O���_0���M������� 	����ϑ�u�`��ƅ�G��H��4�|�p��J��v��'3R�&5�?�%β��uy�ͨS��ɺ�җ'P�H��cqA`F���yz�V�@��t���u��75�'v�R�� 2�e���w��*b� �i��,pO2�f�	޶_�9���S����QM��i)2  Sx�?�M��hh֞g��Vy��O0K��e�O��c�=�	nRޫX�bRBt��`��  &��䌖!Ya(�K�b�r��C���~c[��kG<y�[z�Z�N�ڈ8�_�!�j+�"��/�|��%N���d�A�p�%B``x�T��l���t����u�Ռ���8ˇӻ����7�H9��eKv� y�VK��`�V���b��w�$��%�ߤ��D�$h\�Q�a���Dy��XMMob@��Er�bY@GA���e����='�~Q9������Xf9B0n���B��aC��L��/oD���U_)�m��T	�6���3�B(8��z�̍�T'�Y��~Gy>���_��a�NӼݱ�S���s�1�x�w#0���������2 p� Α��"�6_�9}Ɓg��z�B���VN"�jlM�dԇ�����U�R����\��wE�-S�4*0kju=3��(�r���u}Z63�C�'9�� ='���Oh�+��x�0l9��vNwZ`�R���+����ߣ�ի-I���-r.e9B�C��5�+�9,��`�;E�Z�(�el�I�nݐ��f|i���5�H�t:K�v��Дd��xh$�b4�$���;./Xx*�����!�XG�.�W
���)�n��!�K<���P�z�M�tQ_hX�B_w�x�3W^�Z��5>�F\�Y�����b��J��U?�ٍ)��(��
1��>�_�%�t)a�r�F&��dU�9���
��Z�D�;�Y�|v���4I��y?\�"�S�iV��%�S�3�%�օ~G�0�vr�,�2��l�9�_܍ev�m�7�]�a@�C�?�K�(����Xwg]Oa.�k웮�I[�z ���쟼�&��}$X�U�3g*g)���O��y�+�d���M���A9�a"$~M(�S�Ih���9 �3�_�v^AY�c ��[0���JB��v�6���죮G�Дg��vv�Z�ǯ_=�~z�Qw0;*�`8��1I\�6�XSH��w_�މ��tE'�c��	�f��� 8�	�4�iCRe�@>:Zy����BH|������ECj�e���|�.�O��Z+�]����N�/�-�SG��rcx_��Ӑ��>2+���!eL��hmŜG�*��ُUC��~�L�r"�%e�'��z>Zs��$���6(ry=�X�� T�z���N��T����H��?<��;�)���+Y�]����e�����_��&S��үe`'T�i���]��d,Q�i����F'k�|~w��Zp���w��sj�z���ܮ�y�RL�r~TX!�S����&��q�im)u��\�G4�CTr��@u�U����0a�Gy޷\vh��Ҳ8 �	� ��� =��8�� �*j��,��(�O}����ٞ4�֎���� ���"�C��I�wk���/�����j#7�M5@ q��`(�砌�f;+�:^|����>:�~=ӒRB��:����_ǣ�7{�	&�M�;��� X����`G����qب�`}%���;w�w��k���n��8���3�@QRT�A�n^y���J/�(sY�ʑd-5��0�5�.�T���K���-0é���0��M�ATʼ7l� '�.mt6i_�ĝ�W�q�l~�?��F��*b5�L�m��H�c�DwF�yvœ�F>��8*��pb�b|�oM��P8CI�Y��#��:=���y@�n������������7�wDd)�����x �N���V��y㳖'�B��3\���l��\�o��
�`��	%7e��^��F �ԣ!�DP)�r��ē�����"~f�"�&�_GHDe�'F|�����@E�tty���=_{<Ҏ%4e� Y��J�#�/i�ZE$t�hW�V6vd�^_`��P'y�Hؒ,���S����_M�k����+�bL+�jW
J�<�H����\�����X��YIo ��C�tu���}��u���g���H�u8j�Q�fdi� �������h~I$<Z�d�^U&.����ѡ����	�0��e��r*��R$B �<m~��k�ݞ�x7)���aG���f�X4]��]�BT="��z���rJ�2�������\y"N�ip�
��[�� �M
P�X8f����zd/��b,������&CH��hn�`uu�~�o0�������V�UcDǄŘ�ص1<�мgj[�J<�9n�M��r�ʊ9�Ȣ ��'������0R�U��x����A��Sq��]��o�/A4be�DG����)G�P/v , <����ZȽGqe�j{����
=��++�O&��4��)zJ�p�0U�iZ��`T1f�X���z���ز���VuںN=�Pب�T2���&9v{��Ħ�m���#�t��T�f�朱RM.�;����-��<��1"���:�-��jN�G�G*ɛ^�&k��uA�m��]���I/`VtYj��B�hW�Ғ��d�����HI0	vL����!=aX8��nW��9������f��南�v���o>d~��^{fǧ�2�t�QڍZ��>�.be`���7P�T��ߒ�K����	����8om�h��1��|�q����M��W���*gnYP9�`�����jqA�34�����3&H̎��e�6r�����H�K�H�v��)c���ŗj�W�yR���6$=�g��j�܍��b{��� t�|��
=�������_�)�نb���o�x%��
�^�$J�23q"Q�xw]�������"P����0T����ǒ�Ƽ`8����7i+fpE�Uϲ~���բ���L0��yAh-��X��}�����l�a?Za�S���5Fzks�o$m���9�HM����4s�5n����z<������ک�E�N���0�J}W*��)�'�z�gY��H �>5'��׉1va��w7v�y|�R^49��L���Q��.��`֑�8��:���
�я"���]����q����}I巒b#�VMp�G�"���)�R;1�BIt��e��0])���0�g�> o�NCIX"�J�;E���œ��o�ĀX©�uF�Nǉ)��A���+�Ҙ����\�Ɵ��k�ܽN�x�(��͔� [�hi[�^h�	��
wu؂��v<���z�;���ރ��E
��y_��-�ȧ1���XC��_-]ԊOrc+��y���|uJ���r��˦�P"׫�*��o(�p� �d9�`�ŚƷ'���	��X��$��}�A�>'������L�&�]���#Mn>�lD�GurqP�k�K>��>�?0�2��/�������<�DJ*
��T[����$5uJn�P���P��6�:�m�!O�Ql��]�h��2䬌��B�_� ��.uֵҎ�� �/��+������-�6+���8�6l�W�a*��΋�.�`<B"��D�s'�\i�M�o��ʡ��A�Zr�9D�*A��L	�F�n�?�1N��!`=�����������a}�x �c��.�H��i%��~@ߌ�������
D /��R�_��ѷ�X�?�/p�A&� $C�a�DN�&�`T,`&:��x��KO�7*��.^O��W]G��~`�������}[l
k&`�}�w�Hȣ-MMya�Y���֒ی����\6Hh��Ժ����>��kչ��ԨBjK8	�
�~9yL�*�p]"��ZQԹ������!w�̔i�zǑ&=���	�i9�*!<^��TbOw����5=�c�dpC���|�2oW�K,�۞� ɫ�L�����i?���3���Cj~�ohդV���L�A W��v���`r���ُ�T=	�6%ޑ˚�l;n%5ϔʥ1v���// W�h�p�!-�F����"Z����%SsO��3�����6������S�C�-����n�w����s�Z ���Zu��A��k�S��Y��[��0O�Щ<��wuÃ�-l��[��С�������7�o�=��i?�!��9��F�7��0���@,$� W��w�4�T<ZIc�d�@j�T��E�ǣ$ЩA8#|9Q��.�Q騀��]�%D��+��4R9�܍�� ;x�'����C�`)��wE�{B�g��#q�ЁD���'{L��I`�;%A�hq�~�;�c���О��4��׾��q�d����TK�m��V��X���`f.��:a���������B�|�p��![�:wʭ� #�]�kI��Q�m~���D��:Z��ይ�������ry���J�$���\[wn>ݰI/ab��0���Z\�� �r�!�V� �S�����57�W�h�aG��{˓hl�3n��*��gzt7�m\�5��Z�m�dU� @��ޅo4[R��/Y�- \�,�'g��_d;��T!h��s[��+k�f26%�N��fٮ�� ����6��v[j�Y�/�7e�j�I&hI�F�U`���{𤊥�_AA�w�N�W����Cك2�ʤ=���.|.�Yն!��d{��C9ȿ�ts��P�o"����hI���ޫ���S(�_�)鍊.�Ի�'�H�UY�.�s�*�j� ��ף����[F��"�`^�b�_��ϐ����=NZ]�쁣���	���P>婵���֜�{O��1�6[�~GvĊ4w�>�$��:T�q��P���;%H��ü��g*2��cX�ʒB:ʬn٬:!���=El��&�ؗEE�2)8%̓Hfix@���xIO�L��0{�.��#{L2�8��9ˡٍ�|�r�8��������.j�g{��n�sM�%���WL6�;����eR��ʈ&�+ �8W|uk���+��Bb�-�P���gO��"�l�� @A��ic���:j����Fx�4�O��R}�]!К���?��;��GA��B܊�a�	TPkm*^;&�IR�,y��ٳ�a���IY���!l�Fu]7Y4�,�ΕaJ��@e�2
�6��?'BI%!j���a���Idn���g�8o��Q������wtwz��vy,bB? s3�s*K���!ƽ���v�$�L��4�6�r/%!��+���%~=I�@�l�*!_c6�a�K6#��s���������O��s�VI��dwgVR��<*�;���A{�9޿kq��8)�%U��6X��e@��3���Jݖ)G����豷�����3�.��^��wۈ���cI̥��p�����-6��+:
)���Z��J��������('-h�]��O0����D0>Y��r��4,��^]W�62d�����ۗޣ3�7����aY^��Y[�G�A޻I
z�bǘ !m1c�_~Ҙ"N��~�Z��'H#�MZ�ؗ5xD@�ȟ�b|� �`^mh�V9b�|k��~m�¹*<W�;����]�h���ۋJ��t���v��H��nzҤ婀�����L�h�Cd�h���L���un;5�_y��2c�~g�8�MlD�<ݏ��7���x8���~Da�!��(	���'�H1�w1k�q��7y��~b��
K/O ��Y��]�l,"�څ^Y �Q^
$��x1�׽�}��Tj�� U�@5��s#21�T X^�,��2�p�P)���K��r��ѽ̃�s�6���V�pk���R@��W�l�i_R��ԜjJ�@}���� �ʷ'Ջj��ECO�&��q˶���h��Ŵ`�M�턲�ޡ��N�}���S�5زZd�������/���u����%K>k�����'"�Z9V<�e�����D��9j�2�/�������Y��)`��;[G9O��!JU#�L��W��s�,�E<�S�t��:QNzABn��A�2���6�C�!��'L�_�<�Ċ�6ѕX.����F�+�{��17Q�s��D�-5�D�/�b�i^�M�.p9H � 8Al)�m/8�A%�觷��=+�z���S��$����[h̟U�IH1�}�h/�yu�%m�����6���t��r�:���65Ə7������D�6��F�;����)`AyI�G�H������ ai=�8D_�y��\�9���rpQ�M�P��rR�xKsD;j�ˏs��d�wМJ������2�s�ү�DC;2�F'Sk#����S�Đ�ܰ��z͵y���G#�pS��''��f���X:
��:a�[[6RS*�Ԙ������z�z��O��T�'jr���'���e�z �k|��j'��B�_e�Z����x�LuI�A�]տ�,8���? k���q���6[�����pZA�Vƿn�%�Y��<���A��([��9RA�H!�u�˚�G�bc�W�?4�ç�KZ���U�vr�����7>�������B9���Zт���6�Dd��!�^�P$��>v.�d��cy*g�c/�����?@�8p�=��:8�����Ӌ�7D��t7B�,�q���`+���a�k1r8����D��CЊǧ�DΪ�PΡK�y��;W�U�T=O�_���&����dq�#�1�@/K��w�(�����ڳ���k^ ЧW� s�Ǥd���Zw�B�,��E���y��+��E��0���;\��=�1�8ϙg�>]va��J��*Xj\��D�k��e�Ř�DUc'ⶄ/�!1r�D�X<��Hn]g�<�*�ô�}r�K�w������bX�艈�����K�s:��vI�Gj��m�I�h�K	���ʙz'ѝԢX���|����lW�*�+�K�]H{w�Å����N��9b�d��۸��]���*��<E��(�����@TW�@�qnu�虽�H�co�E)��KC	��F.2נ��9��D�%`�Շ�䄯�+�Ĥv�5�bh�m$ߤ|	)4��Js�Chvf����f��n�k<����@{�C_�\��;���f c����>���}��z��dz�0a��Q5 ]��I���+�V `�q�|6������dNQ�M�s�8�l�0���e�me�#>|�y���}f��2�\���瀅A�Фd��,�����C�~�x0��w9�D(VN+���~��/H�����@G��t������C/M辚Kc�q5I���J�� E�e���_�3�D���eU�Ą� ��������oz��o�SL-�~���g/9(Dp#o�kiHk�V�T� B~�3{E$���(���G�;�蕸�͌W�*~tx-y꾰G�E�΍�7�6l���Fw傠��\�d�3�i�r��M�#��;n%<��~N�� 1���l����6�l�?)�I�g��]ˣ�H��:9�g�I%���~ƱMo��/�~6�q|r��ﾩ����s&0���i �E�)�!��,�y*K��~�T,Y�µ�A���i1��i X��~y�<��ۆ�%Ē��R���aU�?/
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
         ��!����pz��"�F����  AE	 PK? 3  c L��J    �,  �  /           & d10 - Copy (4).zip
         ��!���ql|��`{�����  AE	 PK? 3  c L��J    �,  �  /           =: d10 - Copy (5).zip
         ��!�������X�����  AE	 PK? 3  c L��J    �,  �  /           Tg d10 - Copy (6).zip
         ��!���	����7����  AE	 PK? 3  c L��J    �,  �  /           k� d10 - Copy (7).zip
         ��!���{���C/S����  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (8).zip
         ��!��������������  AE	 PK? 3  c L��J    �,  �  /           �� d10 - Copy (9).zip
         ��!��������ल���  AE	 PK? 3  c L��J    �,  �  /           � d10 - Copy.zip
         ��!���c.���K�v����  AE	 PK? 3  c L��J    �,  �  /           �H d10.zip
         ��!�������ҏ����  AE	 PK      w  �u   