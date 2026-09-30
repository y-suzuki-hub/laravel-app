<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            @auth
                <flux:navlist variant="outline">
                    <flux:navlist.group heading="メニュー" class="grid">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>ホーム</flux:navlist.item>
                        <flux:navlist.item icon="magnifying-glass" :href="route('books.search')" :current="request()->routeIs('books.search')" wire:navigate>本を探す</flux:navlist.item>
                        <flux:navlist.item icon="book-open" :href="route('shelf.index')" :current="request()->routeIs('shelf.index')" wire:navigate>本棚</flux:navlist.item>
                    </flux:navlist.group>
                </flux:navlist>
            @endauth

            <flux:spacer />

            @auth
                <!-- Desktop User Menu -->
                <flux:dropdown position="bottom" align="start">
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevrons-up-down"
                    />

                    @include('partials.user-menu')
                </flux:dropdown>
            @else
                <flux:navlist variant="outline">
                    <flux:navlist.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:navlist.item>
                    <flux:navlist.item icon="user-plus" :href="route('register')" wire:navigate>{{ __('Register') }}</flux:navlist.item>
                </flux:navlist>
            @endauth
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            @auth
                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                    />

                    @include('partials.user-menu')
                </flux:dropdown>
            @endauth
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
