<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Rest;

use GutenbergDowngrade\RestCompat;
use WP_REST_Menu_Items_Controller;

/**
 * Core's menu-items controller under the `__experimental` namespace 11.9 expects.
 */
final class ExperimentalMenuItemsController extends WP_REST_Menu_Items_Controller
{
    public function __construct(string $postType)
    {
        parent::__construct($postType);
        $this->namespace = RestCompat::NAMESPACE;
    }
}
