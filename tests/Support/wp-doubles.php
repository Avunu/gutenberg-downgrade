<?php

/**
 * Minimal stand-ins for the WordPress classes the plugin type-hints, modelled
 * on wp-includes/class-wp-dependency.php, class-wp-dependencies.php and
 * class-wp-block-type-registry.php. Only the behaviour the plugin relies on
 * is reproduced; the shapes (public properties, return values) match core.
 *
 * Global class names by necessity — they double for core's — so this file is
 * excluded from the PSR-12 sniff and from PHPStan's analysed paths.
 */

declare(strict_types=1);

// phpcs:ignoreFile

if (!class_exists('_WP_Dependency')) {
    class _WP_Dependency
    {
        public string $handle;
        public string|false $src;
        /** @var list<string> */
        public array $deps;
        public string|bool|null $ver;
        public mixed $args;
        /** @var array<string, mixed> */
        public array $extra = [];
        public ?string $textdomain = null;
        public ?string $translations_path = null;

        /**
         * @param list<string> $deps
         */
        public function __construct(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $args)
        {
            $this->handle = $handle;
            $this->src = $src;
            $this->deps = $deps;
            $this->ver = $ver;
            $this->args = $args;
        }

        public function add_data(string $name, mixed $data): bool
        {
            if (!is_scalar($name)) {
                return false;
            }
            $this->extra[$name] = $data;
            return true;
        }

        public function set_translations(string $domain, string $path = ''): bool
        {
            $this->textdomain = $domain;
            $this->translations_path = $path;
            return true;
        }
    }
}

if (!class_exists('WP_Dependencies')) {
    class WP_Dependencies
    {
        /** @var array<string, _WP_Dependency> */
        public array $registered = [];
        /** @var list<string> */
        public array $queue = [];

        /**
         * @param list<string> $deps
         */
        public function add(string $handle, string|false $src, array $deps = [], string|bool|null $ver = false, mixed $args = null): bool
        {
            if (isset($this->registered[$handle])) {
                return false;
            }
            $this->registered[$handle] = new _WP_Dependency($handle, $src, $deps, $ver, $args);
            return true;
        }

        public function add_data(string $handle, string $key, mixed $value): bool
        {
            if (!isset($this->registered[$handle])) {
                return false;
            }
            return $this->registered[$handle]->add_data($key, $value);
        }

        public function get_data(string $handle, string $key): mixed
        {
            return $this->registered[$handle]->extra[$key] ?? false;
        }

        /**
         * @param string|list<string> $handles
         */
        public function remove(string|array $handles): void
        {
            foreach ((array) $handles as $handle) {
                unset($this->registered[$handle]);
            }
        }

        public function query(string $handle, string $status = 'registered'): mixed
        {
            return $status === 'registered' ? ($this->registered[$handle] ?? false) : in_array($handle, $this->queue, true);
        }
    }
}

if (!class_exists('WP_Scripts')) {
    class WP_Scripts extends WP_Dependencies
    {
        public function add_inline_script(string $handle, string $data, string $position = 'after'): bool
        {
            if ($data === '' || !isset($this->registered[$handle])) {
                return false;
            }
            $position = $position === 'before' ? 'before' : 'after';
            $script = (array) ($this->registered[$handle]->extra[$position] ?? []);
            $script[] = $data;
            return $this->registered[$handle]->add_data($position, $script);
        }

        public function set_translations(string $handle, string $domain = 'default', string $path = ''): bool
        {
            if (!isset($this->registered[$handle])) {
                return false;
            }
            $obj = $this->registered[$handle];
            if (!in_array('wp-i18n', $obj->deps, true)) {
                $obj->deps[] = 'wp-i18n';
            }
            return $obj->set_translations($domain, $path);
        }
    }
}

if (!class_exists('WP_Styles')) {
    class WP_Styles extends WP_Dependencies
    {
    }
}

if (!class_exists('WP_Block_Type_Registry')) {
    class WP_Block_Type_Registry
    {
        private static ?self $instance = null;

        /** @var array<string, mixed> */
        public array $registered_block_types = [];

        /** @var list<string> */
        public array $unregistered = [];

        public static function get_instance(): self
        {
            return self::$instance ??= new self();
        }

        public static function reset(): void
        {
            self::$instance = null;
        }

        public function is_registered(string $name): bool
        {
            return isset($this->registered_block_types[$name]);
        }

        public function get_registered(string $name): mixed
        {
            return $this->registered_block_types[$name] ?? null;
        }

        public function register(string $name, mixed $args = []): void
        {
            $this->registered_block_types[$name] = $args;
        }

        public function unregister(string $name): void
        {
            $this->unregistered[] = $name;
            unset($this->registered_block_types[$name]);
        }
    }
}

if (!class_exists('WP_Block_Editor_Context')) {
    final class WP_Block_Editor_Context
    {
        public ?string $name = 'core/edit-post';
    }
}

if (!class_exists('WP_Block_Patterns_Registry')) {
    final class WP_Block_Patterns_Registry
    {
        /** @var list<array<string, mixed>> */
        public static array $items = [];

        public static function get_instance(): self
        {
            return new self();
        }

        /**
         * @return list<array<string, mixed>>
         */
        public function get_all_registered(bool $outside_init_only = false): array
        {
            return self::$items;
        }

        public function unregister(string $pattern_name): bool
        {
            self::$items = array_values(array_filter(
                self::$items,
                static fn(array $item): bool => ($item['name'] ?? null) !== $pattern_name
            ));

            return true;
        }
    }
}
