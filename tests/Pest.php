<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Google Books API の volume リソースのダミー。
 *
 * @param  array<string, mixed>  $volumeInfo
 * @return array<string, mixed>
 */
function googleBooksVolume(string $id = 'vol123', array $volumeInfo = []): array
{
    return [
        'id' => $id,
        'volumeInfo' => array_merge([
            'title' => 'リーダブルコード',
            'authors' => ['Dustin Boswell', 'Trevor Foucher'],
            'publisher' => 'オライリージャパン',
            'publishedDate' => '2012-06-23',
            'description' => '<p>より良いコードを書くための<b>シンプルで実践的な</b>テクニック</p>',
            'industryIdentifiers' => [
                ['type' => 'ISBN_10', 'identifier' => '4873115655'],
                ['type' => 'ISBN_13', 'identifier' => '9784873115658'],
            ],
            'pageCount' => 237,
            'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id='.$id],
        ], $volumeInfo),
    ];
}
