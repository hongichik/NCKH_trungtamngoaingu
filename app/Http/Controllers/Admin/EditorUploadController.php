<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EditorUploadController extends Controller
{
    /** Modules whose content uses the rich-text editor. */
    protected const MODULES = ['posts', 'pages'];

    /**
     * Receives an image dropped, pasted or inserted into the editor, shrinks it
     * for the web and returns its URL so content stores a link, not base64.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(collect(self::MODULES)->contains(
            fn ($module) => $user->hasPermission($module, 'create') || $user->hasPermission($module, 'edit')
        ), 403);

        $request->validate([
            'upload' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ], [
            'upload.image' => 'Tệp tải lên phải là ảnh.',
            'upload.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, GIF, WEBP.',
            'upload.max' => 'Ảnh không được lớn hơn 10MB.',
        ]);

        $path = ImageOptimizer::store($request->file('upload'), 'editor/'.now()->format('Y/m'));

        return response()->json(['url' => Storage::url($path)]);
    }
}
