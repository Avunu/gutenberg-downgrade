<?php

declare(strict_types=1);

namespace GutenbergDowngrade;

use GutenbergDowngrade\Rest\ExperimentalMenuItemsController;
use GutenbergDowngrade\Rest\ExperimentalMenuLocationsController;
use GutenbergDowngrade\Rest\ExperimentalMenusController;
use WP_REST_Menus_Controller;

/**
 * Aliases the `__experimental` REST routes the 11.9 client still expects.
 *
 * In 11.9, @wordpress/core-data reads classic menus, menu items and menu
 * locations from `/__experimental/menus`, `/__experimental/menu-items` and
 * `/__experimental/menu-locations` (the Navigation block's classic-menu
 * picker). Core shipped the same controllers under `wp/v2` in 5.9; expose
 * them under the old namespace too. `block-navigation-areas` never shipped
 * and is not aliased — that block is hidden from the inserter instead.
 */
final class RestCompat
{
    public const NAMESPACE = '__experimental';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        if (!class_exists(WP_REST_Menus_Controller::class)) {
            return;
        }

        (new ExperimentalMenusController('nav_menu'))->register_routes();
        (new ExperimentalMenuItemsController('nav_menu_item'))->register_routes();
        (new ExperimentalMenuLocationsController())->register_routes();
    }
}
