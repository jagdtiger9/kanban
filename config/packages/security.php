<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Mcp\Infrastructure\Security\McpTokenAuthenticator;
use App\Shared\Infrastructure\Security\AccessDeniedHandler;
use App\Shared\Infrastructure\Security\AuthenticationEntryPoint;
use App\Shared\Infrastructure\Security\PasswordAuthenticator;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Security\SecurityUserProvider;
use App\User\Domain\Entity\User;
use App\User\Domain\ValueObject\UserRole;

return App::config([
    'security' => [
        'password_hashers' => [
            // registration hashes before a security adapter exists
            User::class => [
                'algorithm' => 'auto',
            ],
            SecurityUser::class => [
                'algorithm' => 'auto',
            ],
        ],
        'providers' => [
            'app_user_provider' => [
                'id' => SecurityUserProvider::class,
            ],
        ],
        'firewalls' => [
            // the order in which firewalls are defined is very important, as the
            // request will be handled by the first firewall whose pattern matches
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            // MCP server: stateless Bearer-token auth + rate limits (src/Mcp)
            'mcp' => [
                'pattern' => '^/mcp',
                'stateless' => true,
                'provider' => 'app_user_provider',
                'entry_point' => McpTokenAuthenticator::class,
                'custom_authenticators' => [
                    McpTokenAuthenticator::class,
                ],
            ],
            // a firewall with no pattern matches all requests; it must come last
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'entry_point' => AuthenticationEntryPoint::class,
                'access_denied_handler' => AccessDeniedHandler::class,
                'custom_authenticators' => [
                    PasswordAuthenticator::class,
                ],
                'logout' => [
                    'path' => '/api/auth/logout',
                    'target' => '/',
                ],
                'remember_me' => [
                    'signature_properties' => ['password', 'rolesString'],
                    'remember_me_parameter' => '%security.remember_me.name%',
                    'name' => '%security.remember_me.name%',
                    'lifetime' => 86400, // a day
                ],
                'login_link' => [
                    'check_route' => '%security.login_check.url%',
                    'lifetime' => 3600,
                    'max_uses' => 2,
                    'signature_properties' => ['id'],
                ],
            ],
        ],
        'access_control' => [
            ['path' => '^/api/ping', 'roles' => ['PUBLIC_ACCESS']],
            ['path' => '^/api/auth/register', 'roles' => ['PUBLIC_ACCESS']],
            ['path' => '^/api/auth/confirm', 'roles' => ['PUBLIC_ACCESS']],
            ['path' => '^/api/auth/login', 'roles' => ['PUBLIC_ACCESS']],
            ['path' => '^/login_check', 'roles' => ['PUBLIC_ACCESS']],
            ['path' => '^/api/.+$', 'roles' => [UserRole::ROLE_USER->name]],
            ['path' => '^/mcp', 'roles' => [UserRole::ROLE_USER->name]],
        ],
    ],
    // By default, password hashers are resource intensive and take time. This is
    // important to generate secure password hashes. In tests however, secure hashes
    // are not important, waste resources and increase test times. The following
    // reduces the work factor to the lowest possible values.
    'when@test' => [
        'security' => [
            'password_hashers' => [
                User::class => [
                    'algorithm' => 'auto',
                    // Lowest possible value for bcrypt
                    'cost' => 4,
                    // Lowest possible value for argon
                    'time_cost' => 3,
                    // Lowest possible value for argon
                    'memory_cost' => 10,
                ],
                SecurityUser::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
        ],
    ],
]);
