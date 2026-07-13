<?php header("Content-Type: application/json"); echo json_encode(["ini" => php_ini_loaded_file(), "exts" => get_loaded_extensions()]); ?>

