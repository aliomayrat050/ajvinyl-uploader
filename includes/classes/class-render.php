<?php

namespace AJ_UPLOADER\Includes\Classes;

if (!defined('ABSPATH')) {
    exit;
}

class Render
{

    public static function view($view, $data = [])
    {
        extract($data, EXTR_SKIP);
        ob_start();
        $dir = trailingslashit(ajuploader_get_setting('path')) . 'views/' . $view;

        include $dir . '.php';
        return ob_get_clean();
    }
}
