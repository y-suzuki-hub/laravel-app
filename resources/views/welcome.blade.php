<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">
        <header class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <x-app-logo />

            <nav class="flex gap-2">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" size="sm">ホームへ</flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Register') }}</flux:button>
                @endauth
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-20 text-center">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">読んだ本を、記録して、共有しよう。</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                ReadLog は読書の進捗と感想を残せる読書記録 SNS です。本を検索して本棚に登録し、読み終わったら感想を投稿しましょう。
            </p>

            <div class="mt-10 flex justify-center gap-3">
                @guest
                    <flux:button :href="route('register')" variant="primary">無料で始める</flux:button>
                @endguest
            </div>

            <ul class="mt-20 grid gap-6 text-left sm:grid-cols-3">
                <li class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:icon.magnifying-glass class="mb-3 size-6" />
                    <p class="font-semibold">本を探す</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">タイトルや著者名で検索して、ワンクリックで本棚に登録。</p>
                </li>
                <li class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:icon.book-open class="mb-3 size-6" />
                    <p class="font-semibold">本棚で管理</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">読みたい・読んでいる・読み終わったをステータスと★評価で整理。</p>
                </li>
                <li class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:icon.pencil-square class="mb-3 size-6" />
                    <p class="font-semibold">感想を共有</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">タグ付きで感想やメモを投稿。ネタバレは折りたたんで表示。</p>
                </li>
            </ul>
        </main>

        @fluxScripts
    </body>
</html>
