<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('division')
            ->when($request->input('role') === 'marketing', fn ($query) => $query->whereIn('role', ['tl_marketing', 'staff_marketing']))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $divisions = Division::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.users.create', compact('divisions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'tl_pembangunan',
                    'staff_pembangunan',
                    'tl_marketing',
                    'staff_marketing',
                    'tl_pemberkasan',
                    'staff_pemberkasan',
                ]),
            ],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = now();

        $validated['division_id'] = $this->divisionIdFromRole(
            $validated['role']
        );

        User::create($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $divisions = Division::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', compact('user', 'divisions'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'tl_pembangunan',
                    'staff_pembangunan',
                    'tl_marketing',
                    'staff_marketing',
                    'tl_pemberkasan',
                    'staff_pemberkasan',
                ]),
            ],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->division_id = $this->divisionIdFromRole($validated['role']);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'Admin tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    private function divisionIdFromRole(string $role): ?int
    {
        $divisionSlug = User::divisionSlugForRole($role);

        return $divisionSlug === null
            ? null
            : Division::where('slug', $divisionSlug)->value('id');
    }
}
