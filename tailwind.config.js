/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                // FCM-Styleguide: nur diese fuenf Farben (plus Weiss)
                rot: { DEFAULT: '#B8161C', dunkel: '#9A1218', hell: '#F3DCDD' },
                kreide: '#F6F5F0',
                nacht: { DEFAULT: '#14213D', hell: '#2A3958' },
                gelb: '#F5D000',
                beton: { DEFAULT: '#5B6472', hell: '#E4E3DD' },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'Arial', 'sans-serif'],
            },
            letterSpacing: {
                label: '0.08em',
                weit: '0.14em',
            },
            borderRadius: {
                DEFAULT: '2px',
            },
            boxShadow: {
                none: 'none',
            },
            keyframes: {
                haken: { '0%': { strokeDashoffset: '24' }, '100%': { strokeDashoffset: '0' } },
                einblenden: { '0%': { opacity: 0, transform: 'translateY(6px)' }, '100%': { opacity: 1, transform: 'none' } },
                puls: { '0%,100%': { transform: 'scale(1)' }, '50%': { transform: 'scale(1.15)' } },
            },
            animation: {
                haken: 'haken .35s ease-out forwards',
                einblenden: 'einblenden .35s ease-out both',
                puls: 'puls .4s ease-out',
            },
        },
    },
    plugins: [],
};
