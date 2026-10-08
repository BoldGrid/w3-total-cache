<?php
/**
 * File: Extension_MaxCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

/**
 * Admin side of the MAx Cache extension: how it is listed, and how the owner hears about it.
 *
 * Registered by Extensions_Plugin_Admin rather than from the extension file, which is included
 * only while the extension is active: the offer below has to be readable exactly when it is not.
 *
 * @since X.X.X
 */
class Extension_MaxCache_Plugin_Admin {
	/**
	 * State key that hides the note once the owner has read it.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const HIDE_NOTE_KEY = 'extension.maxcache.hide_note_delivering';

	/**
	 * State key that hides the offer once the owner has answered it.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const HIDE_OFFER_KEY = 'extension.maxcache.hide_note_available';

	/**
	 * Declares the extension.
	 *
	 * @since X.X.X
	 *
	 * @param array  $extensions  Extensions to list.
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $w3tc_config ) {
		// One configuration for both halves of what this screen says, or the two contradict each other.
		$w3tc_config = Extension_MaxCache_Core::saved_config( $w3tc_config );

		// Shown in the field W3TC already has: absent is the hoster's to install, out-of-date theirs to update.
		$reason = Extension_MaxCache_Core::get_unsupported_reason( $w3tc_config );

		// The file's own state is no longer part of the verdict, and this screen is where it gets reported.
		if ( '' === $reason ) {
			$reason = Extension_MaxCache_Core::file_unsupported_reason();
		}

		$extensions[ Extension_MaxCache_Core::SLUG ] = array(
			'name'            => 'MAx Cache',
			'author'          => 'CloudLinux',
			'description'     => \__( 'Serves the Disk: Enhanced page cache from the web server itself, before PHP starts, where the CloudLinux MAx Cache module is installed.', 'w3-total-cache' ),
			'author_uri'      => 'https://www.cloudlinux.com/',
			'extension_uri'   => 'https://cloudlinux.com/max-cache/',
			'extension_id'    => Extension_MaxCache_Core::SLUG,
			'settings_exists' => false,
			'version'         => '1.0',
			'enabled'         => '' === $reason,
			'requirements'    => $reason,
			'path'            => 'w3-total-cache/Extension_MaxCache_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Offers the handover where the module could take it and the extension is still off.
	 *
	 * Nothing else tells the owner the module is there: the Extensions page lists it among two
	 * dozen others, and the Page Cache screen does not mention it at all.
	 *
	 * @since X.X.X
	 *
	 * @param array $notes Notes to show.
	 *
	 * @return array
	 */
	private static function offer_note( $notes ) {
		$state_master = Dispatcher::config_state_master();

		if ( $state_master->get_boolean( self::HIDE_OFFER_KEY ) ) {
			return $notes;
		}

		// Same configuration answers both the switch and the reason, or the offer contradicts the site.
		$w3tc_config = Extension_MaxCache_Core::saved_config( Dispatcher::config() );

		// Cheapest question first: this filter also runs on plugins.php, where the gate below would cost real stats/probes.
		if ( $w3tc_config->is_extension_active( Extension_MaxCache_Core::SLUG ) ) {
			return $notes;
		}

		if ( '' !== Extension_MaxCache_Core::get_unsupported_reason( $w3tc_config )
			|| '' !== Extension_MaxCache_Core::file_unsupported_reason()
		) {
			return $notes;
		}

		// The Extensions page's own Activate route, nonce included, so this offer can't drift from it.
		$activate = \wp_nonce_url(
			Util_Ui::admin_url( 'admin.php?page=w3tc_extensions&action=activate&extension=' . Extension_MaxCache_Core::SLUG ),
			'w3tc_extension_activate_' . Extension_MaxCache_Core::SLUG
		);

		$notes[ Extension_MaxCache_Core::SLUG . '_available' ] = \sprintf(
			// translators: 1 activate button, 2 hide-this-message button.
			\__( 'The CloudLinux MAx Cache module on this server can serve your Disk: Enhanced page cache before PHP starts. %1$s %2$s', 'w3-total-cache' ),
			Util_Ui::button_link( \__( 'Activate MAx Cache', 'w3-total-cache' ), $activate ),
			Util_Ui::button_hide_note2(
				array(
					'w3tc_default_config_state_master' => 'y',
					'key'                              => self::HIDE_OFFER_KEY,
					'value'                            => 'true',
				)
			)
		);

		return $notes;
	}

	/**
	 * Tells the owner that delivery moved, since nothing on the Page Cache screen says so.
	 *
	 * @since X.X.X
	 *
	 * @param array $notes Notes to show.
	 *
	 * @return array
	 */
	public static function w3tc_notes( $notes ) {
		$notes        = self::offer_note( $notes );
		$state_master = Dispatcher::config_state_master();

		// Said once while true; dismissing it just leaves the same sentence on the Extensions page for later.
		if ( ! Extension_MaxCache_Core::is_delivering() || $state_master->get_boolean( self::HIDE_NOTE_KEY ) ) {
			return $notes;
		}

		$notes[ Extension_MaxCache_Core::SLUG ] = \sprintf(
			// translators: 1 opening link tag to the Extensions page, 2 closing link tag, 3 hide-this-message button.
			\__( 'Page cache is being served by the CloudLinux MAx Cache module, before PHP starts. %1$sManage it on the Extensions page%2$s. %3$s', 'w3-total-cache' ),
			'<a href="' . \esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) ) . '">',
			'</a>',
			Util_Ui::button_hide_note2(
				array(
					'w3tc_default_config_state_master' => 'y',
					'key'                              => self::HIDE_NOTE_KEY,
					'value'                            => 'true',
				)
			)
		);

		return $notes;
	}
}
