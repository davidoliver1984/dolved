import { defineConfig } from '/app/node_modules/vite/dist/node/index.js';
import react from '/app/node_modules/@vitejs/plugin-react/dist/index.js';
import tailwind from '/app/node_modules/@tailwindcss/postcss/dist/index.mjs';
export default defineConfig({
  root: '/workspace/scripts/evaluation/current_policy_confirmation/browser',
  plugins: [react()],
  css: { postcss: { plugins: [tailwind()] } },
  resolve: { alias: { '@': '/workspace/apps/web/src', react: '/app/node_modules/react', 'react-dom': '/app/node_modules/react-dom' } },
  define: { 'process.env.NEXT_PUBLIC_API_URL': JSON.stringify('http://127.0.0.1:38431') },
  server: { host: '0.0.0.0', port: 38430, strictPort: true, fs: { allow: ['/workspace', '/app/node_modules'] } },
});
