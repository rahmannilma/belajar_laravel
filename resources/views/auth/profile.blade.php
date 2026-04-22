@extends('layouts.app')

@section('title', 'Profil')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-6">Profil Saya</h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                <p class="mt-1 text-lg">{{ auth()->user()->name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <p class="mt-1 text-lg">{{ auth()->user()->email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Peran</label>
                <p class="mt-1 text-lg">{{ auth()->user()->role_label }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <p class="mt-1 text-lg">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Aktif
                    </span>
                </p>
            </div>
        </div>

        <div class="mt-8">
            <a href="{{ route('profile') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none">
                Edit Profil
            </a>
        </div>
    </div>
</div>
@endsection
