<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Rest;

use GutenbergDowngrade\RestCompat;
use WP_REST_Menu_Locations_Controller;

/**
 * Core's menu-locations controller under the `__experimental` namespace 11.9 expects.
 */
final class ExperimentalMenuLocationsController extends WP_REST_Menu_Locations_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->namespace = RestCompat::NAMESPACE;
    }
}
