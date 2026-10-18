<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('profile', 'roles')
            ->when($request->role, fn($q, $r) => $q->role($r))
            ->when($request->search, fn($q, $s) => $q->where(fn($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($users, UserResource::collection($users));
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('profile', 'roles')->findOrFail($id);
        return $this->success(new UserResource($user));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        $user = User::findOrFail($id);

        if ($user->hasRole('admin')) {
            return $this->forbidden('Nie można blokować admina.');
        }

        $user->update(['is_active' => $request->is_active]);

        return $this->success(null, $request->is_active ? 'Konto aktywowane.' : 'Konto zablokowane.');
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role' => ['required', Rule::in(['user', 'owner'])]]);
        $user = User::findOrFail($id);

        if ($user->hasRole('admin')) {
            return $this->forbidden('Nie można zmienić roli admina.');
        }

        $user->syncRoles([$request->role]);

        return $this->success(null, 'Rola zaktualizowana.');
    }
}
