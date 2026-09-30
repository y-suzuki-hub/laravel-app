<?php

namespace App\Livewire\Forms;

use App\Models\Book;
use App\Models\Post;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

class PostForm extends Form
{
    public const MAX_TAGS = 5;

    public const MAX_TAG_LENGTH = 30;

    public string $body = '';

    public bool $has_spoiler = false;

    /** スペース・カンマ区切りのタグ入力（例: "技術書 Laravel"） */
    public string $tags = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'has_spoiler' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail) {
                $names = self::parseTags((string) $value);

                if (count($names) > self::MAX_TAGS) {
                    $fail('タグは '.self::MAX_TAGS.' 個までです。');
                }

                foreach ($names as $name) {
                    if (mb_strlen($name) > self::MAX_TAG_LENGTH) {
                        $fail('タグは 1 つ '.self::MAX_TAG_LENGTH.' 文字までです。');
                    }
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'body' => '感想',
            'tags' => 'タグ',
        ];
    }

    /**
     * タグ入力を分解する。区切りは空白（全角含む）・カンマ・読点、先頭の # は取り除く。
     *
     * @return list<string>
     */
    public static function parseTags(string $input): array
    {
        return collect(preg_split('/[\s,、，]+/u', $input) ?: [])
            ->map(fn (string $name) => ltrim($name, '#＃'))
            ->filter(fn (string $name) => $name !== '')
            ->map(fn (string $name) => mb_strtolower($name))
            ->unique()
            ->values()
            ->all();
    }

    public function setPost(Post $post): void
    {
        $this->body = $post->body;
        $this->has_spoiler = $post->has_spoiler;
        $this->tags = $post->tags->pluck('name')->implode(' ');
    }

    public function store(User $user, Book $book): Post
    {
        $this->validate();

        return DB::transaction(function () use ($user, $book) {
            $post = $user->posts()->create([
                'book_id' => $book->id,
                'body' => $this->body,
                'has_spoiler' => $this->has_spoiler,
            ]);

            $post->syncTagNames(self::parseTags($this->tags));

            return $post;
        });
    }

    public function update(Post $post): void
    {
        $this->validate();

        DB::transaction(function () use ($post) {
            $post->update([
                'body' => $this->body,
                'has_spoiler' => $this->has_spoiler,
            ]);

            $post->syncTagNames(self::parseTags($this->tags));
        });
    }
}
