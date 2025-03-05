/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.html',
    './src/**/*.{js,jsx}' // Make sure to include .js or .jsx
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('daisyui'), // Add DaisyUI as a plugin
  ],
  daisyui: {
    themes: ['cupcake', 'light', 'bumblebee', 'emerald', 'corporate'],
  },
}
