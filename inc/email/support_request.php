<?php
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<html>
	<head></head>
	<body>
		<p>
			Date: <?php echo esc_html( gmdate( 'm/d/Y H:i:s' ) ); ?><br />
			Version: <?php echo esc_html( W3TC_VERSION ); ?><br />
			URL: <a href="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $url ); ?></a><br />
			Name: <?php echo esc_html( $name ); ?><br />
			E-Mail: <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><br />

			<?php if ( $twitter ) : ?>
			Twitter: <a href="http://twitter.com/<?php echo esc_attr( $twitter ); ?>"><?php echo esc_html( $twitter ); ?></a><br />
			<?php endif; ?>

			<?php if ( $phone ) : ?>
			Phone: <?php echo esc_html( $phone ); ?><br />
			<?php endif; ?>

			<?php if ( $forum_url ) : ?>
			Forum Topic URL: <a href="<?php echo esc_attr( $forum_url ); ?>"><?php echo esc_html( $forum_url ); ?></a><br />
			<?php endif; ?>

			<?php if ( $request_data_url ) : ?>
			Request data: <a href="<?php echo esc_attr( $request_data_url ); ?>"><?php echo esc_html( $request_data_url ); ?></a><br />
			<?php endif; ?>

			Subject: <?php echo esc_html( $subject ); ?>
		</p>

		<p>
			<?php echo nl2br( esc_html( $description ) ); ?>
		</p>

		<hr />

		<font size="-1" color="#ccc">
			E-mail sent from IP: <?php echo esc_html( $_SERVER['REMOTE_ADDR'] ); ?><br />
			User Agent: <?php echo esc_html( $_SERVER['HTTP_USER_AGENT'] ); ?>
		</font>
	</body>
</html>
