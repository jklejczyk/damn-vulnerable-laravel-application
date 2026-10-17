<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Invoicing for small businesses')])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center gap-8 px-6 py-16">
            <div class="flex items-center gap-3">
                <x-app-logo />
            </div>

            <div class="space-y-3">
                <flux:heading size="xl" level="1">{{ __('Invoicing for small businesses') }}</flux:heading>
                <flux:text>{{ __('Issue invoices, send them to your clients, get paid through the payment gateway and connect your accounting software through the API.') }}</flux:text>
            </div>

            <flux:callout variant="danger" icon="shield-exclamation" :heading="__('This application is intentionally insecure')">
                <flux:callout.text>
                    {{ __('DVLA (Damn Vulnerable Laravel Application) contains security bugs on purpose. Run it only locally or in an isolated container, never on a publicly reachable server.') }}
                </flux:callout.text>
            </flux:callout>

            <div class="flex flex-wrap gap-3">
                @auth
                    <flux:button variant="primary" :href="route('dashboard')">{{ __('Dashboard') }}</flux:button>
                @else
                    <flux:button variant="primary" :href="route('login')">{{ __('Log in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button :href="route('register')">{{ __('Create a company') }}</flux:button>
                    @endif
                @endauth
                <flux:button variant="ghost" icon="shield-exclamation" href="https://github.com/jklejczyk/damn-vulnerable-laravel-application/blob/main/VULNERABILITIES.md" target="_blank">{{ __('Vulnerabilities') }}</flux:button>
            </div>

            <flux:text class="text-sm">{{ __('Demo accounts: owner@acme.test, accountant@acme.test, member@acme.test, the same for globex.test, and admin@dvla.test. Password: password.') }}</flux:text>
        </div>
        @fluxScripts
    </body>
</html>
