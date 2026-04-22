@extends('layouts.app')

@section('title', 'Daftar Bisnis Baru')

@section('content')
<div class="min-h-screen flex flex-col sm:justify-center items-center py-12 sm:py-20 bg-gradient-to-br from-teal-50 to-cyan-50 dark:from-gray-900 dark:to-gray-800">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center">
            <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
        <h2 class="mt-6 text-center text-3xl font-bold text-gray-900 dark:text-white">
            Buat Bisnis Baru
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Daftarkan usaha Anda dan mulai kelola dengan Smart POS
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow-xl rounded-2xl sm:px-10 border border-gray-200 dark:border-gray-700">
            <form class="space-y-5" method="POST" action="{{ route('register.business') }}">
                @csrf

                <div class="pb-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Informasi Pemilik</h3>
                </div>

                <div>
                    <label for="owner_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nama Pemilik
                    </label>
                    <div class="mt-1">
                        <input id="owner_name" name="owner_name" type="text" required 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('owner_name') border-red-500 @enderror"
                            value="{{ old('owner_name') }}"
                            placeholder="Nama lengkap Anda">
                        @error('owner_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                    </label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" required 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('email') border-red-500 @enderror"
                            value="{{ old('email') }}"
                            placeholder="email@contoh.com">
                        @error('email')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" required 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('password') border-red-500 @enderror"
                            placeholder="Minimal 6 karakter">
                        @error('password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Konfirmasi Password
                    </label>
                    <div class="mt-1">
                        <input id="password_confirmation" name="password_confirmation" type="password" required 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                            placeholder="Ulangi password Anda">
                    </div>
                </div>

                <div class="pt-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Informasi Bisnis</h3>
                </div>

                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nama Bisnis / Toko
                    </label>
                    <div class="mt-1">
                        <input id="business_name" name="business_name" type="text" required 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('business_name') border-red-500 @enderror"
                            value="{{ old('business_name') }}"
                            placeholder="Nama toko atau bisnis Anda">
                        @error('business_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="business_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Alamat Bisnis
                    </label>
                    <div class="mt-1">
                        <textarea id="business_address" name="business_address" rows="2" 
                            class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('business_address') border-red-500 @enderror"
                            placeholder="Alamat lengkap bisnis Anda">{{ old('business_address') }}</textarea>
                        @error('business_address')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="business_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            No. Telepon
                        </label>
                        <div class="mt-1">
                            <input id="business_phone" name="business_phone" type="text" 
                                class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('business_phone') border-red-500 @enderror"
                                value="{{ old('business_phone') }}"
                                placeholder="0812xxxxxxx">
                            @error('business_phone')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="business_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Kota
                        </label>
                        <div class="mt-1">
                            <input id="business_city" name="business_city" type="text" 
                                class="appearance-none block w-full px-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white @error('business_city') border-red-500 @enderror"
                                value="{{ old('business_city') }}"
                                placeholder="Jakarta">
                            @error('business_city')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" 
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-semibold text-white bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-all duration-200">
                        Buat Bisnis Saya
                    </button>
                </div>

                <div class="text-center">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400">
                        ← Kembali ke login
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection