<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * DVLA-019: the whole request reaches a model with an empty $guarded, so a user can
     * set user_id (post as someone else) or is_featured by adding them to the request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string']]);

        $post = Post::create($request->all() + ['user_id' => $request->user()->id]);

        return response()->json($post, 201);
    }
}
