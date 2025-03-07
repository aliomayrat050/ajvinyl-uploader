<?php

namespace AJ_UPLOADER;

if (!defined('ABSPATH')) {
    exit;
}

use AJ_UPLOADER\Includes\Controller\Admin\AdminController;
use AJ_UPLOADER\Includes\Controller\Frontend\PublicController;

class init
{
    private $settings = [];


    public function __construct() {}

    public function initialize($version, $basefile)
    {

        $basename = plugin_basename($basefile);
        $this->settings = [

            // basic
            'name'                => 'AJ Uploader for Woo',
            'version'            => $version,

            // urls
            'basename'            => $basename,
            'path'                => plugin_dir_path($basefile),
            'url'                => plugin_dir_url($basefile),
            'slug'                => dirname($basename),



            // options
            //'capability'        => 'manage_options',

            // cpts
            //'cpts'              => ['wapf_product']
        ];
       include_once(trailingslashit($this->settings['path']) . 'includes/helpers/helpers-func.php');
       include_once(trailingslashit($this->settings['path']) . 'vendor/svg-sanitizer/Sanitizer.php');




        if (is_admin())
            new AdminController();

        new PublicController();
    }

    public function has_setting($name)
    {
        return isset($this->settings[$name]);
    }

    public function get_setting($name)
    {
        return isset($this->settings[$name]) ? $this->settings[$name] : null;
    }
}
