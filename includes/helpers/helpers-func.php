<?php

if (!defined('ABSPATH')) {
    exit;
}

function ajuploader_has_setting($name = '')
{

    return ajuploader()->has_setting($name);
}

function ajuploader_get_setting($name, $value = null)
{

    if (ajuploader_has_setting($name)) {
        $value =  ajuploader()->get_setting($name);
    }

    $value = apply_filters("ajuploader/setting/{$name}", $value);

    return $value;
}

function ajuploader_sanitize_input($input)
{
    return sanitize_text_field($input);
}

