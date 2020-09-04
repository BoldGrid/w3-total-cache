<?php
/**
 * File: template.php
 *
 * @since X.X.X
 *
 * @package    W3TC
 * @subpackage W3TC\Wizard
 */

namespace W3TC\Wizard;

if ( ! defined( 'W3TC' ) ) {
	die();
}

/**
 * Class: Template
 *
 * Wizard template class.
 *
 * @since X.X.X
 */
class Template {
	/**
	 * Configuration.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	private $config = array();


	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 *
	 * @see self::add_hooks();
	 *
	 * @param array $config Configuration.
	 */
	public function __construct( array $config ) {
		$this->config = $config;

		$this->add_hooks();
	}

	/**
	 * Render the wizard.
	 *
	 * @since X.X.X
	 *
	 * @see self::enqueue_scripts()
	 * @see self::enqueue_styles()
	 */
	public function render() {
		$this->enqueue_scripts();
		$this->enqueue_styles();

		require W3TC_INC_DIR . '/options/common/header.php';

		$allowed_html = array(
			'div'    => array(
				'class' => array(),
			),
			'em'     => array(),
			'h3'     => array(),
			'input'  => array(
				'checked' => array(),
				'class'   => array(),
				'id'      => array(),
				'name'    => array(),
				'type'    => array(),
				'value'   => array(),
			),
			'label'  => array(
				'for' => array(),
			),
			'p'      => array(
				'class' => array(),
				'id'    => array(),
			),
			'span'   => array(
				'class' => array(),
				'id'    => array(),
			),
			'strong' => array(),
			'table'  => array(
				'class' => array(),
				'id'    => array(),
			),
			'tbody'  => array(),
			'td'     => array(),
			'th'     => array(),
			'thead'  => array(),
			'tr'     => array(),
		);

		?>

<div id="w3tc-wizard-container">

	<div id="w3tc_wizard_header">
		<img id="w3tc_wizard_icon" src="<?php echo esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ); ?>" />
		<div id="w3tc_wizard_title">
			<span>TOTAL</span> <span>CACHE</span><span>:</span> <span><?php echo esc_html( $this->config['title'] ); ?></span>
		</div>
	</div>

	<div id="w3tc_wizard_content">

		<p id="w3tc-options-menu">
		<?php
		foreach ( $this->config['steps'] as $number => $step ) {
			$number++;
			if ( isset( $this->config['steps_location'] ) && 'left' === $this->config['steps_location'] ) {
				?>
				<span id="w3tc-wizard-step-<?php echo esc_attr( $number ); ?>" class="w3tc-wizard-step-numbers w3tc-wizard-steps-left">
					<?php echo esc_html( $step['text'] ); ?>
			</span>
				<?php
			} else {
				?>
				<span id="w3tc-wizard-step-<?php echo esc_attr( $number ); ?>" class="w3tc-wizard-step-numbers"><?php echo esc_html( $number ); ?></span>
				<span id="w3tc-wizard-step-<?php echo esc_attr( $number ); ?>-text"><?php echo esc_html( $step['text'] ); ?></span>
				<?php
			}
		}
		?>
		</p>

		<?php
		$hidden = '';

		foreach ( $this->config['slides'] as $number => $slide ) {
			$number++;
			?>
			<div id="w3tc-wizard-slide-<?php echo esc_attr( $number ); ?>" class="w3tc-wizard-slides<?php echo esc_attr( $hidden ); ?>">
				<h3><?php echo wp_kses( $slide['headline'], $allowed_html ); ?></h3>
				<?php echo wp_kses( $slide['markup'], $allowed_html ); ?>
			</div>
			<?php
			$hidden = ' hidden';
		}

		unset( $hidden );
		?>

	</div>

	<div id="w3tc_wizard_footer">
		<div id="w3tc-wizard-buttons">
			<span>
				<button id="w3tc-wizard-skip" class="w3tc-wizard-buttons"><?php esc_html_e( 'SKIP', 'w3-total-cache' ); ?></button>
			</span>
			<span style="display:none;"><button id="w3tc-wizard-previous" class="w3tc-wizard-buttons"><?php esc_html_e( 'PREVIOUS', 'w3-total-cache' ); ?></button></span>
			<span><button id="w3tc-wizard-next"class="w3tc-wizard-buttons"><?php esc_html_e( 'NEXT', 'w3-total-cache' ); ?></button></span>
		</div>
	</div>

		<?php wp_nonce_field( 'w3tc_wizard' ); ?>
	<div id="test-results" class="hidden"></div>
</div>

		<?php
		require W3TC_INC_DIR . '/options/common/footer.php';
	}

	/**
	 * Add hooks.
	 *
	 * @since X.X.X
	 */
	private function add_hooks() {
		if ( isset( $this->config['actions'] ) && is_array( $this->config['actions'] ) ) {
			foreach ( $this->config['actions'] as $action ) {
				add_action(
					$action['tag'],
					$action['function'],
					empty( $action['priority'] ) ? 10 : $action['priority'],
					empty( $action['accepted_args'] ) ? 1 : $action['accepted_args'],
				);
			}
		}

		if ( isset( $this->config['filters'] ) && is_array( $this->config['filters'] ) ) {
			foreach ( $this->config['filters'] as $filter ) {
				add_filter(
					$filter['tag'],
					$filter['function'],
					empty( $filter['priority'] ) ? 10 : $filter['priority'],
					empty( $filter['accepted_args'] ) ? 1 : $filter['accepted_args'],
				);
			}
		}
	}

	/**
	 * Rnqueue scripts.
	 *
	 * @since X.X.X
	 */
	private function enqueue_scripts() {
		wp_enqueue_script(
			'w3tc_wizard',
			esc_url( plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'pub/js/wizard.js' ),
			array( 'jquery' ),
			W3TC_VERSION,
			false
		);

		if ( isset( $this->config['scripts'] ) && is_array( $this->config['scripts'] ) ) {
			foreach ( $this->config['scripts'] as $script ) {
				wp_register_script(
					$script['handle'],
					$script['src'],
					is_array( $script['deps'] ) ? $script['deps'] : array(),
					empty( $script['version'] ) ? date( 'YmdHm' ) : $script['version'],
					! empty( $script['in_footer'] )
				);

				if ( isset( $script['localize'] ) && is_array( $script['localize'] ) ) {
					wp_localize_script(
						$script['handle'],
						$script['localize']['object_name'],
						$script['localize']['data']
					);
				}

				wp_enqueue_script( $script['handle'] );
			}
		}
	}

	/**
	 * Enqueue styles.
	 *
	 * @since X.X.X
	 */
	private function enqueue_styles() {
		wp_enqueue_style(
			'w3tc_wizard',
			esc_url( plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'pub/css/wizard.css' ),
			array(),
			'all'
		);

		if ( isset( $this->config['styles'] ) && is_array( $this->config['styles'] ) ) {
			foreach ( $this->config['styles'] as $style ) {
				wp_enqueue_style(
					$style['handle'],
					$style['src'],
					isset( $style['deps'] ) && is_array( $style['deps'] ) ?
						$style['deps'] : array(),
					! empty( $style['version'] ) ? $style['version'] : date( 'YmdHm' ),
					! empty( $style['media'] ) ? $style['media'] : 'all'
				);
			}
		}
	}
}
