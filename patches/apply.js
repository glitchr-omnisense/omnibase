#!/usr/bin/env node
/**
 * Restores the local fixes in patches/<package>/ over node_modules, and
 * rebuilds any package that ships a build of its own.
 *
 * This exists because the fixes kept disappearing. `yarn install` restores
 * each package from the registry and silently drops them; the built output
 * under public/ keeps working on its own, so nothing fails - right up until
 * the next asset rebuild regenerates the bundle from the reverted source,
 * with no error anywhere. That has now destroyed article content twice
 * (1147 in August, 1169 today), because editorjs-yjs's reverted copy pushes
 * a duplicate of the whole document on every page load.
 *
 * Wired to `postinstall`, so an install repairs itself instead of relying on
 * someone remembering the README.
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const patchesDir = __dirname;

// Packages that ship no build config of their own: patches/<name>/webpack.config.js
// is copied in alongside the sources so the rebuild is reproducible.
const rebuild = new Set(['editorjs-yjs']);

let touched = 0;

for (const name of fs.readdirSync(patchesDir, { withFileTypes: true })) {
    if (!name.isDirectory()) continue;

    const from = path.join(patchesDir, name.name);
    const target = path.join(root, 'node_modules', name.name);
    if (!fs.existsSync(target)) {
        console.warn(`[patches] ${name.name}: not installed, skipped`);
        continue;
    }

    for (const sub of ['src', '.']) {
        const dir = path.join(from, sub);
        if (!fs.existsSync(dir)) continue;
        for (const file of fs.readdirSync(dir)) {
            if (!file.endsWith('.js')) continue;
            const src = path.join(dir, file);
            if (fs.statSync(src).isDirectory()) continue;
            const dst = path.join(target, sub === '.' ? '' : sub, file);
            fs.mkdirSync(path.dirname(dst), { recursive: true });
            fs.copyFileSync(src, dst);
            console.log(`[patches] ${name.name}/${sub === '.' ? '' : sub + '/'}${file}`);
            touched++;
        }
    }

    if (rebuild.has(name.name)) {
        // src/ alone is not enough: package.json's `main` points at dist/, so
        // an unrebuilt package still serves the unfixed code.
        console.log(`[patches] ${name.name}: rebuilding dist`);
        execFileSync('npx', ['webpack', '--mode', 'production'], { cwd: target, stdio: 'inherit' });
    }
}

console.log(`[patches] ${touched} file(s) restored.`);
