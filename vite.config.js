import { defineConfig } from 'vite'
import path from 'path'

export default defineConfig({
    root: 'src/frontend',
    publicDir: '../../public',
    build: {
        outDir: path.resolve(__dirname, 'dist'),
        emptyOutDir: true,
        rollupOptions: {
            input: {
                main:  path.resolve(__dirname, 'src/frontend/js/main.js'),
                admin: path.resolve(__dirname, 'src/frontend/js/admin.js'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
        },
        sourcemap: false,
        minify: 'esbuild',
    },
    css: {
        devSourcemap: true,
        postcss: './postcss.config.js',
    },
})
