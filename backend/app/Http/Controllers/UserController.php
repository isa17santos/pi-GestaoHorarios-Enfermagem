<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    // Returns the full list of users.
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->get()
        );
    }

    // Returns a single user by id.
    public function show(int $id): JsonResponse
    {
        return response()->json(
            User::query()->findOrFail($id)
        );
    }
}
