#!/usr/bin/env /usr/share/php-5.3.29/bin/php
<?php
    function getIdentity() {
        $id = str_replace('.', '', uniqid('', true));

        return rtrim(chunk_split($id, 6, '-'), '-');
    }
    
    $i = 0;
    $data = array();
    while($i<100000) {
        $id = getIdentity();
        if(isset($data[$id])) {
            printf('%s，重复。%s', $id, PHP_EOL);
            sleep(5);
            continue;
        }
        
        $data[$id] = $id;
        printf('%s%s', $id, PHP_EOL);
    }
    
