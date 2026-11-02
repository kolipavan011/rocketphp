import { defineConfig } from 'vite';
import fs from 'fs';
import path from 'path';

/**
 * Rocketphp Vite Plugin
 *
 * Automatically manages hot module replacement (HMR), manifest generation,
 * and dev-server lifecycle tracking for Rocketphp.
 */
function rocket(options = {}) {
    const input = options.input || ['resources/css/app.css', 'resources/js/app.js'];
    const hotFile = path.resolve('public/hot');

    return {
        name: 'rocketphp-vite-plugin',
        config(userConfig) {
            return {
                publicDir: false,
                build: {
                    manifest: 'manifest.json',
                    outDir: 'public/build',
                    rollupOptions: {
                        input,
                    },
                },
                server: {
                    cors: true,
                    strictPort: true,
                },
            };
        },
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const address = server.httpServer?.address();
                const host = typeof address === 'object' && address?.address ? (address.address === '::' || address.address === '0.0.0.0' ? 'localhost' : address.address) : 'localhost';
                const port = typeof address === 'object' && address?.port ? address.port : 5173;
                const protocol = server.config.server.https ? 'https' : 'http';
                const url = `${protocol}://${host}:${port}`;

                fs.mkdirSync('public', { recursive: true });
                fs.writeFileSync(hotFile, url);
            });

            const cleanHot = () => {
                if (fs.existsSync(hotFile)) {
                    try { fs.unlinkSync(hotFile); } catch (e) {}
                }
            };

            process.on('exit', cleanHot);
            process.on('SIGINT', () => { cleanHot(); process.exit(); });
            process.on('SIGTERM', () => { cleanHot(); process.exit(); });
        },
    };
}

export default defineConfig({
    plugins: [
        rocket({
            input: ['resources/css/app.css', 'resources/js/app.js'],
        }),
    ],
});
