import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Prompt', ...defaultTheme.fontFamily.sans],
                prompt: ['Prompt', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Project palette (only blue/sky/white/green/red/orange allowed)
                brand: {
                    DEFAULT: '#1d4ed8',  // blue-700
                    light: '#2563eb',    // blue-600
                    sky: '#0ea5e9',      // sky-500
                    skyLight: '#38bdf8', // sky-400
                },
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.25rem',
            },
        },
    },
    plugins: [],
};
