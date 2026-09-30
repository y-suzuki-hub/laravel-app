<?php

namespace App\Livewire\Posts;

use App\Livewire\Forms\PostForm;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('感想を書く')]
class Create extends Component
{
    public Book $book;

    public PostForm $form;

    public function save(): void
    {
        $post = $this->form->store(Auth::user(), $this->book);

        $this->redirectRoute('posts.show', $post, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.posts.create');
    }
}
