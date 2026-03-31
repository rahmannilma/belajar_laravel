<a {{ $attributes->merge(['class' => $attributes->get('active', false) ? 'flex items-center px-3 py-2 text-sm font-medium rounded-lg bg-teal-50 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300' : 'flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700']) }}>
    {{ $slot }}
</a>
