#!/usr/bin/env node
/**
 * scripts/build-templates.mjs
 * Compila templates Nunjucks (.njk) → HTML estáticos.
 * Corre antes de `vite build` para que el servidor PHP sirva
 * los HTML que referencian los assets de /dist/assets/.
 *
 * Uso: node scripts/build-templates.mjs
 *       npm run build:templates
 */

import nunjucks from 'nunjucks';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { join, dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT       = resolve(__dirname, '..');
const TMPL_DIR   = join(ROOT, 'src/frontend/templates');

// Configurar Nunjucks con el directorio de templates
const env = nunjucks.configure(TMPL_DIR, {
    autoescape: true,
    trimBlocks:  true,
    lstripBlocks: true,
});

// Mapa: template relativo → archivo de salida relativo al root del proyecto
const PAGES = [
    // Auth
    { src: 'pages/auth/login.njk',         out: 'login.html' },

    // Portal cliente
    { src: 'pages/client/portal.njk',          out: 'portal.html' },
    { src: 'pages/client/projects.njk',        out: 'proyectos.html' },
    { src: 'pages/client/project-detail.njk',  out: 'proyecto.html' },
    { src: 'pages/client/invoices.njk',        out: 'facturas.html' },
    { src: 'pages/client/calendar.njk',        out: 'calendario.html' },
    { src: 'pages/client/profile.njk',         out: 'perfil.html' },
    { src: 'pages/client/onboarding.njk',      out: 'onboarding.html' },

    // Admin
    { src: 'pages/admin/dashboard.njk',    out: 'admin.html' },
    { src: 'pages/admin/dashboard.njk',    out: 'admin/index.html' },
    { src: 'pages/admin/projects.njk',     out: 'admin/proyectos.html' },
    { src: 'pages/admin/clients.njk',        out: 'admin/clientes.html' },
    { src: 'pages/admin/client-detail.njk', out: 'admin/cliente-detalle.html' },
    { src: 'pages/admin/invoices.njk',      out: 'admin/facturas.html' },
    { src: 'pages/admin/analytics.njk',    out: 'admin/analytics.html' },
    { src: 'pages/admin/kanban.njk',      out: 'admin/kanban.html' },
    { src: 'pages/admin/content-calendar.njk', out: 'admin/content-calendar.html' },
    { src: 'pages/admin/assets.njk',            out: 'admin/assets.html' },
];

let ok = 0;
let fail = 0;

for (const { src, out } of PAGES) {
    try {
        const html    = nunjucks.render(src);
        const outPath = join(ROOT, out);

        // Crear directorio de salida si no existe
        mkdirSync(dirname(outPath), { recursive: true });
        writeFileSync(outPath, html, 'utf8');

        console.log(`✓  ${src.padEnd(40)} → ${out}`);
        ok++;
    } catch (err) {
        console.error(`✗  ${src}`);
        console.error(`   ${err.message}`);
        fail++;
    }
}

console.log(`\nTemplates compilados: ${ok} ok${fail ? `, ${fail} con errores` : ''}`);

if (fail > 0) {
    process.exit(1);
}
