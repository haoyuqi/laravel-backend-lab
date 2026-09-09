const fs = require('fs');

const patches = [
    {
        file: 'node_modules/laravel-mix/src/webpackPlugins/BuildOutputPlugin.js',
        find: /const \{ formatSize \} = require\([^;]+;\n/,
        replace: 'const formatSize = size => String(size);\n',
    },
    {
        file: 'node_modules/laravel-mix/src/builder/webpack-plugins.js',
        find: "if (process.env.NODE_ENV !== 'test') {",
        replace: 'if (false) {',
    },
];

for (const { file, find, replace } of patches) {
    const source = fs.readFileSync(file, 'utf8');

    if (source.includes(replace)) {
        continue;
    }

    const patched = source.replace(find, replace);

    if (patched === source) {
        throw new Error(`Unable to apply Laravel Mix compatibility patch: ${file}`);
    }

    fs.writeFileSync(file, patched);
}
