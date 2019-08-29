<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();
?>
<style>
#w3tc_usage_statistics:hover .edit-box {
	opacity: 1;
}

.w3tcuw_sizes {
	display: flex;
	width: 100%;
	padding-bottom: 10px;
}

.w3tcuw_name {
	font-weight: bold;
}

.w3tcuw_size_item {
	flex: 1;
	text-align: center;
	display: none;
}
</style>
<div>
	Hit rate
	<div style="width: 100%; height: 200px">
		<canvas id="w3tcuw_chart"></canvas>
	</div>
</div>

<div class="w3tcuw_sizes">
	<div class="w3tcuw_size_item w3tcuw_memcached_size_percent">
		<div class="w3tcuw_name">Memcached Usage</div>
		<div class="w3tcuw_value"></div>
	</div>
	<div class="w3tcuw_size_item w3tcuw_redis_size_percent">
		<div class="w3tcuw_name">Redis Usage</div>
		<div class="w3tcuw_value"></div>
	</div>
	<div class="w3tcuw_size_item w3tcuw_apc_size_percent">
		<div class="w3tcuw_name">APC Usage</div>
		<div class="w3tcuw_value"></div>
	</div>
</div>
