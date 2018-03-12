#!/usr/bin/env /usr/share/php-5.3.29/bin/php
<?php
	function getIdentity() {
		$id = str_replace('.', '', uniqid('', true));

		return rtrim(chunk_split($id, 6, '-'), '-');
	}
	
	$i = 0;
	while($i<100000) {
		$id = getIdentity();
		printf('%s%s', $id, PHP_EOL);
	}
	
