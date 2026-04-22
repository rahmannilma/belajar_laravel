<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password yang Anda masukkan salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif. Hubungi pemilik toko.'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->isCashier()) {
            return redirect()->route('kasir');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegisterForm()
    {
        return view('auth.register-business');
    }

    public function registerBusiness(Request $request)
    {
        $request->validate([
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'business_name' => 'required|string|max:255',
            'business_address' => 'nullable|string|max:500',
            'business_phone' => 'nullable|string|max:20',
            'business_city' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->owner_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'owner',
                'branch_id' => null,
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'name' => $request->business_name,
                'address' => $request->business_address,
                'phone' => $request->business_phone,
                'city' => $request->business_city,
                'is_active' => true,
                'owner_id' => $user->id,
            ]);

            $user->update(['branch_id' => $branch->id]);

            $defaultCategories = [];
            foreach ($defaultCategories as $categoryName) {
                Category::create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName).'-'.$branch->id,
                    'branch_id' => $branch->id,
                ]);
            }

            Auth::login($user);
        });

        return redirect()->route('dashboard')->with('success', 'Selamat! Bisnis Anda telah dibuat.');
    }

    public function register(Request $request)
    {
        if (! Auth::check() || ! Auth::user()->isOwner()) {
            abort(403, 'Hanya pemilik yang dapat mendaftarkan pengguna baru.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:owner,cashier',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => true,
        ]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.Auth::id(),
            'current_password' => 'nullable|string|required_with:new_password',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        // Validate current password if trying to change password
        if ($request->current_password) {
            if (! Hash::check($request->current_password, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Password saat ini tidak benar.'],
                ]);
            }
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->new_password) {
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
        }

        return back()->with('success', 'Profil berhasil diperbarui!');
    }
}
