// Emit .gz and .br siblings for build output so nginx can serve them directly
// through gzip_static / brotli_static instead of compressing on every request.
//
// Usage: node docker/precompress.mjs <dir> [minBytes]
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';

const [dir, minBytes = '1024'] = process.argv.slice(2);

if (!dir) {
    console.error('usage: node docker/precompress.mjs <dir> [minBytes]');
    process.exit(1);
}

// Already-compressed formats gain nothing and would only bloat the image.
const skip = /\.(gz|br|woff2?|png|jpe?g|gif|ico|webp|avif)$/i;
const threshold = Number(minBytes);

let count = 0;
let originalBytes = 0;
let compressedBytes = 0;

for (const name of readdirSync(dir).sort()) {
    const file = join(dir, name);
    const stat = statSync(file);

    if (!stat.isFile() || stat.size < threshold || skip.test(name)) {
        continue;
    }

    const source = readFileSync(file);
    const gzip = gzipSync(source, { level: 9 });
    const brotli = brotliCompressSync(source, {
        params: {
            [constants.BROTLI_PARAM_QUALITY]: 11,
            [constants.BROTLI_PARAM_SIZE_HINT]: source.length,
        },
    });

    writeFileSync(`${file}.gz`, gzip);
    writeFileSync(`${file}.br`, brotli);

    count += 1;
    originalBytes += source.length;
    compressedBytes += gzip.length + brotli.length;

    console.log(
        `${name}: ${source.length} -> gzip ${gzip.length}, brotli ${brotli.length}`,
    );
}

console.log(
    `precompressed ${count} files (${originalBytes} source bytes, ${compressedBytes} compressed bytes)`,
);
