<?php

namespace App\Livewire\Posts;

use App\Models\Post;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public Post $post;

    public function mount(): void
    {
        $this->post->load(['user', 'book', 'tags']);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->post);

        $book = $this->post->book;
        $this->post->delete();

        $this->redirectRoute('books.show', $book, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.posts.show')
            ->title($this->post->user->name.'さんの感想: '.$this->post->book->title);
    }
}
