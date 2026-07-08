<?php
file_put_contents('C:/xampp/tmp/test.log', 'works');
file_put_contents(dirname(__FILE__) . '/test.log', 'works2');
echo 'tmp: ' . (file_exists('C:/xampp/tmp/test.log') ? 'OK' : 'FAIL') . '<br>';
echo 'local: ' . (file_exists(dirname(__FILE__) . '/test.log') ? 'OK' : 'FAIL');