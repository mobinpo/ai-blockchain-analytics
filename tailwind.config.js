import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#00E7FF',
                    50:  '#E6FDFF',
                    100: '#CCFBFF',
                    200: '#99F4FF',
                    300: '#66ECFF',
                    400: '#33E4FF',
                    500: '#00E7FF',
                    600: '#00C2D4',
                    700: '#0891B2',
                    800: '#0B6B77',
                    900: '#0D4C56'
                },
                ink:   '#0B1114',   // page background
                panel: '#0E1A20',   // cards, navbar
                panel2:'#13232B',   // subtle panels/borders
                line:  '#16424D'    // borders/dividers
            },
            boxShadow: {
                'glow': '0 0 10px rgba(0,231,255,.4), 0 0 30px rgba(0,231,255,.25)',
                'inset-glow': 'inset 0 0 12px rgba(0,231,255,.15)'
            },
        },
    },

    plugins: [forms],
};
