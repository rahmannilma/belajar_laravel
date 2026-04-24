<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->canManageUsers()) {
                abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // If owner has no branches yet (super admin), show all users
        if ($user->isOwner() && empty($accessibleBranchIds)) {
            $users = User::query()
                ->with('branch')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Only show branches belonging to the current owner
            $branches = \App\Models\Branch::where('owner_id', auth()->user()->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } else {
            $users = User::query()
                ->with('branch')
                ->where(function ($query) use ($accessibleBranchIds) {
                    $query->whereIn('branch_id', $accessibleBranchIds)
                        ->orWhereNull('branch_id');
                })
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $branches = \App\Models\Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
        }

        return view('users.index', compact('users', 'branches'));
    }

    public function create()
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $branches = \App\Models\Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('users.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::min(6)],
            'role' => 'required|in:owner,cashier',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        // Validate branch_id belongs to accessible branches if provided
        if ($request->filled('branch_id') && ! in_array($request->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'branch_id' => $request->filled('branch_id') ? $request->branch_id : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    public function edit(User $user)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Ensure user being edited belongs to accessible branches
        if ($user->branch_id && ! in_array($user->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke pengguna ini.');
        }

        $branches = \App\Models\Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Ensure user being updated belongs to accessible branches
        if ($user->branch_id && ! in_array($user->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke pengguna ini.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:owner,cashier',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Rules\Password::min(6)];
        }

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'branch_id' => $request->filled('branch_id') ? $request->branch_id : null,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui!');
    }

    public function destroy(User $user)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Ensure user belongs to accessible branches
        if ($user->branch_id && ! in_array($user->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke pengguna ini.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri!');
        }

        if ($user->sales()->exists()) {
            return back()->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat penjualan!');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus!');
    }
}
