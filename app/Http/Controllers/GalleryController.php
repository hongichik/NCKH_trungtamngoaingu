<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        return view('public.galleries', [
            'galleries' => Gallery::where('status', 'published')->orderBy('order')->latest()->paginate(12),
        ]);
    }

    public function show(Gallery $gallery): View
    {
        $gallery->load('images');

        return view('public.gallery', compact('gallery'));
    }
}
