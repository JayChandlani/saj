<?php

namespace Marvel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Marvel\Http\Middleware\EnsureEmailIsVerified;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Marvel\Enums\CouponType;
use Marvel\Enums\ShippingType;
use Marvel\Enums\Permission;
use Marvel\Providers\GraphQLServiceProvider;
use Marvel\Providers\RestApiServiceProvider;
use Marvel\Providers\EventServiceProvider;
use Marvel\Console\InstallCommand;
use Illuminate\Support\Facades\App;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Illuminate\Support\Facades\Gate;
use Marvel\Console\AdminCreateCommand;
use Marvel\Console\CopyFilesCommand;
use Marvel\Console\ImportDemoData;
use Marvel\Database\Models\Settings;
use Marvel\Enums\ManufacturerType;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\ProductType;
use Marvel\Enums\RefundStatus;
use Marvel\Enums\WithdrawStatus;
use Marvel\Enums\PaymentGatewayType;
use Marvel\Enums\PaymentStatus;
use Marvel\Payments\Payment;
use Marvel\Enums\StoreNoticePriority;
use Marvel\Enums\StoreNoticeType;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $serviceProviders = [
        GraphQLServiceProvider::class,
        RestApiServiceProvider::class,
        EventServiceProvider::class,
        WhereConditionsServiceProvider::class,
        // Maatwebsite\Excel\ExcelServiceProvider::class,

    ];

    /**
     * @var array
     */
    protected $enums = [
        CouponType::class,
        Permission::class,
        StoreNoticeType::class,
        StoreNoticePriority::class,
        ShippingType::class,
        ProductType::class,
        WithdrawStatus::class,
        RefundStatus::class,
        PaymentGatewayType::class,
        ManufacturerType::class,
        OrderStatus::class,
        PaymentStatus::class,
    ];

    protected $commandList = [
        InstallCommand::class,
        AdminCreateCommand::class,
        ImportDemoData::class,
        CopyFilesCommand::class,
    ];

    /**
     * @var string[]
     */
    protected $routeMiddleware = [
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'email.verified' => EnsureEmailIsVerified::class,
    ];


    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(TypeRegistry $typeRegistry): void
    {
        $this->loadServiceProviders();
        $this->loadMiddleware();
        $this->bootConsole();
        $this->registerEnum($typeRegistry);
        $this->givePermissionToSuperAdmin();
        $this->loadMigrations();
        $this->loadHelpers();
    }

    public function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->loadFactoriesFrom(__DIR__ . '/../database/factories');
    }

    /**
     * If the helper file exists, require it.
     */
    public function loadHelpers()
    {
        if (File::exists(__DIR__ . '/Helpers/helpers.php')) {
            require(__DIR__ . '/Helpers/helpers.php');
        }
    }

    /**
     * Load Service Providers
     *
     * @return void
     */
    public function loadServiceProviders(): void
    {
        foreach ($this->serviceProviders as $provider) {
            App::register($provider);
        }
    }

    public function givePermissionToSuperAdmin()
    {
        Gate::before(function ($user, $ability) {
            return $user->hasPermissionTo(Permission::SUPER_ADMIN) ? true : null;
        });
    }

    public function registerEnum($typeRegistry)
    {
        foreach ($this->enums as $enum) {
            $typeRegistry->register(
                new LaravelEnumType($enum)
            );
        }
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function bootConsole()
    {
        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {

        $this->mergeConfigFrom(__DIR__ . '/../config/shop.php', 'shop');

        config([
            'auth' => File::getRequire(__DIR__ . '/../config/auth.php'),
            'cors' => File::getRequire(__DIR__ . '/../config/cors.php'),
            'graphql-playground' => File::getRequire(__DIR__ . '/../config/graphql-playground.php'),
            'laravel-omnipay' => File::getRequire(__DIR__ . '/../config/laravel-omnipay.php'),
            'media-library' => File::getRequire(__DIR__ . '/../config/media-library.php'),
            'permission' => File::getRequire(__DIR__ . '/../config/permission.php'),
            'sanctum' => File::getRequire(__DIR__ . '/../config/sanctum.php'),
            'services' => File::getRequire(__DIR__ . '/../config/services.php'),
            'scout' => File::getRequire(__DIR__ . '/../config/scout.php'),
            'sluggable' => File::getRequire(__DIR__ . '/../config/sluggable.php'),
            'constants' => File::getRequire(__DIR__ . '/../config/constants.php'),
            'newsletter' => File::getRequire(__DIR__ . '/../config/newsletter.php'),
            'paystack' => File::getRequire(__DIR__ . '/../config/paystack.php'),
            'graphiql' => File::getRequire(__DIR__ . '/../config/graphiql.php'),
            'sslcommerz' => File::getRequire(__DIR__ . '/../config/sslcommerz.php'),
        ]);

        // Register the service the package provides.
        $this->app->singleton('shop', function () {
            return new Shop();
        });

        $this->app->singleton('payment', function ($app) {
            $settings = Settings::first();
            $active_payment_gateway =  $settings->options['paymentGateway'] ?? ACTIVE_PAYMENT_GATEWAY;
            $gateway = 'Marvel\\Payments\\' . ucfirst($active_payment_gateway);
            return new Payment($app->make($gateway));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['shop'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config//shop.php' => config_path('shop.php'),
        ], 'config');

        $this->commands($this->commandList);
    }


    /**
     * Load Middleware from shop
     */
    protected function loadMiddleware(): void
    {
        if (!is_array($this->routeMiddleware) ||  empty($this->routeMiddleware)) {
            return;
        }

        foreach ($this->routeMiddleware as $alias => $middleware) {
            $this->app->router->aliasMiddleware($alias, $middleware);
        }
    }
}
