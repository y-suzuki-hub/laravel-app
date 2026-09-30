<?php

namespace App\Livewire\Posts;

use App\Livewire\Forms\PostForm;
use App\Models\Post;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('感想を編集')]
class Edit extends Component
{
    public Post $post;

    public PostForm $form;

    public function mount(): void
    {
        $this->authorize('update', $this->post);

        $this->form->setPost($this->post);
    }

    public function save(): void
    {
        $this->authorize('update', $this->post);

        $this->form->update($this->post);

        $this->redirectRoute('posts.show', $this->post, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.posts.edit');
    }
}
