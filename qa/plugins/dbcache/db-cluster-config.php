global $w3tc_dbcluster_config;

$w3tc_dbcluster_config = array(
	'databases' => array(
		'master' => array(
			'host'     => 'localhost',
			'user'     => 'wordpress',
			'password' => 'wordpress',
			'name'     => 'wordpress'
		),
		'slave' => array(
			'host'     => 'localhost',
			'user'     => 'wordpress',
			'password' => 'wordpress',
			'name'     => 'wordpress',
			'write'    => false,
			'read'     => true,
			'timeout'  => 0.2
		)
	)
);
