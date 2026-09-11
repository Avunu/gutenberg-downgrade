<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Rest;

use GutenbergDowngrade\RestCompat;
use WP_REST_Menus_Controller;

/**
 * Core's menus controller under the `__experimental` namespace 11.9 expects.
 */
final class ExperimentalMenusController extends WP_REST_Menus_Controller
{
    public function __construct(string $taxonomy)
    {
        parent::__construct($taxonomy);
        $this->namespace = RestCompat::NAMESPACE;
    }
}
