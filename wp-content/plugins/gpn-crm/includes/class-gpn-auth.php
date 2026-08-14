<?php
/**
 * GPN CRM - authentication & sessions.
 *
 * The CRM keeps its own user table (identical to the desktop app) and its
 * own login screen. Sessions are stored in WP transients keyed by a random
 * token held in an httponly cookie, so no PHP session config is required.
 *
 * WordPress capability checks (GPN_CRM_CAP / GPN_CRM_CAP_ADMIN) run on top
 * so wp-admin access is also enforced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Auth {

	private static $instance = null;
	const COOKIE            = 'gpn_crm_session';
	const TRANSIENT_PREFIX  = 'gpn_crm_sess_';
	const LIFETIME          = 43200; // 12 hours, matches desktop-ish persistence.

	private $cached_user = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Authenticate a CRM user by username + password (SHA-256, same as desktop).
	 */
	public function authenticate( $username, $password ) {
		$db   = GPN_DB::instance();
		$user = $db->db()->get_row(
			$db->db()->prepare(
				'SELECT * FROM ' . $db->users() . ' WHERE username = %s AND active = 1 LIMIT 1',
				sanitize_user( trim( (string) $username ) )
			),
			ARRAY_A
		);
		if ( ! $user ) {
			return null;
		}
		if ( ! hash_equals( (string) $user['password_hash'], gpn_hash_password( (string) $password ) ) ) {
			return null;
		}
		return $this->hydrate( $user );
	}

	private function hydrate( $row ) {
		return array(
			'id'        => (int) $row['id'],
			'full_name' => $row['full_name'],
			'username'  => $row['username'],
			'role'      => $row['role'],
			'active'    => (int) $row['active'],
		);
	}

	/**
	 * Log in: create a session token, persist transient, set cookie.
	 */
	public function login( $user ) {
		$token = wp_generate_password( 48, false );
		set_transient( self::TRANSIENT_PREFIX . $token, (int) $user['id'], self::LIFETIME );
		setcookie( self::COOKIE, $token, time() + self::LIFETIME, '/', '', is_ssl(), true );
		$this->cached_user = $user;
	}

	/**
	 * Current CRM user (array) or null.
	 */
	public function current_user() {
		if ( null !== $this->cached_user ) {
			return $this->cached_user;
		}
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			$this->cached_user = false;
		} else {
			$token = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( ! $token ) {
				$this->cached_user = false;
			} else {
				$user_id = get_transient( self::TRANSIENT_PREFIX . $token );
				if ( ! $user_id ) {
					$this->cached_user = false;
				} else {
					$db  = GPN_DB::instance();
					$row = $db->db()->get_row(
						$db->db()->prepare( 'SELECT * FROM ' . $db->users() . ' WHERE id = %d AND active = 1', (int) $user_id ),
						ARRAY_A
					);
					if ( ! $row ) {
						$this->cached_user = false;
					} else {
						$this->cached_user = $this->hydrate( $row );
					}
				}
			}
		}

		if ( ! $this->cached_user && function_exists( 'wp_get_current_user' ) ) {
			$wp_user = wp_get_current_user();
			if ( $wp_user && $wp_user->ID && in_array( 'administrator', (array) $wp_user->roles, true ) ) {
				$db   = GPN_DB::instance();
				$crm  = $db->db()->get_row(
					$db->db()->prepare( "SELECT * FROM {$db->users()} WHERE role = 'Admin' AND active = 1 LIMIT 1" ),
					ARRAY_A
				);
				if ( $crm ) {
					$this->login( $this->hydrate( $crm ) );
					return $this->cached_user;
				}
			}
		}

		return $this->cached_user;
	}

	public function logout() {
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$token = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			delete_transient( self::TRANSIENT_PREFIX . $token );
			setcookie( self::COOKIE, '', time() - 3600, '/', '', is_ssl(), true );
		}
		$this->cached_user = false;
	}

	public function is_admin() {
		$user = $this->current_user();
		return $user && 'Admin' === $user['role'];
	}

	/**
	 * Require a CRM session; if absent render the login screen and stop.
	 */
	public function require_login() {
		$user = $this->current_user();
		if ( $user ) {
			return $user;
		}
		$this->render_login_page();
		exit;
	}

	/**
	 * Desktop-style login screen (dark theme, blue accents).
	 */
	public function render_login_page() {
		$error   = '';
		$submitted = isset( $_POST['gpn_crm_login'] );
		if ( $submitted ) {
			check_admin_referer( 'gpn_crm_login', '_gpn_nonce' );
			$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
			if ( '' === $username || '' === $password ) {
				$error = __( 'Please enter both username and password.', 'gpn-crm' );
			} else {
				$user = $this->authenticate( $username, $password );
				if ( ! $user ) {
					GPN_Log::instance()->add( 'login_failed', 'system', 0, 'Failed login attempt for username: ' . $username );
					$error = __( 'Invalid username or password.', 'gpn-crm' );
				} else {
					$this->login( $user );
					GPN_Log::instance()->add( 'login', 'system', $user['id'], 'Logged in: ' . $user['full_name'], array(), $user['id'] );
					$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'gpn-crm-dashboard';
					wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
					exit;
				}
			}
		}

		$title    = __( 'Geeta Pariwar Nepal', 'gpn-crm' );
		$subtitle = __( 'Sadhak CRM', 'gpn-crm' );
		?>
		<div class="gpn-shell">
			<div class="gpn-login-card">
				<div class="gpn-login-header">
					<div class="gpn-login-logo">ॐ</div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php echo esc_html( $subtitle ); ?></p>
				</div>
				<?php if ( $error ) : ?>
					<div class="gpn-alert gpn-alert-error"><?php echo esc_html( $error ); ?></div>
				<?php endif; ?>
				<form method="post" action="" autocomplete="off">
					<?php wp_nonce_field( 'gpn_crm_login', '_gpn_nonce' ); ?>
					<input type="hidden" name="gpn_crm_login" value="1">
					<div class="gpn-field">
						<label><?php esc_html_e( 'Username', 'gpn-crm' ); ?></label>
						<input type="text" name="username" autocomplete="off" autofocus required>
					</div>
					<div class="gpn-field">
						<label><?php esc_html_e( 'Password', 'gpn-crm' ); ?></label>
						<input type="password" name="password" autocomplete="off" required>
					</div>
					<div class="gpn-btn-row">
						<button type="submit" class="gpn-btn gpn-btn-primary"><?php esc_html_e( 'Login', 'gpn-crm' ); ?></button>
						<a href="<?php echo esc_url( wp_logout_url() ); ?>" class="gpn-btn gpn-btn-secondary"><?php esc_html_e( 'Exit', 'gpn-crm' ); ?></a>
					</div>
				</form>
				<p class="gpn-login-foot">Geeta Pariwar Nepal Sadhak CRM v<?php echo esc_html( GPN_CRM_VERSION ); ?></p>
			</div>
		</div>
		<?php
	}
}
