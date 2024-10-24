import fs from 'fs';
import { dirname, resolve, isAbsolute, join as join$1 } from 'path';
import { promisify } from 'util';
import { fileURLToPath } from 'url';
import { join, isAbsolute as isAbsolute$1 } from 'node:path';

const copyFile = promisify(fs.copyFile);
const mkdir = promisify(fs.mkdir);
const readdir = promisify(fs.readdir);
const readFile = promisify(fs.readFile);
const stat = promisify(fs.stat);
const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Absolute path to the Partytown lib directory within the
 * `@builder.io/partytown` package.
 *
 * https://partytown.builder.io/copy-library-files
 *
 * @public
 */
function libDirPath(opts) {
    if (opts && opts.debugDir) {
        return resolve(__dirname, '..', 'lib', 'debug');
    }
    return resolve(__dirname, '..', 'lib');
}
/**
 * Utility to copy the Partytown library files to a destination on the server.
 * Partytown requires its library files, such as `partytown.js` to be served
 * as static files from the same origin. By default the library assumes all the
 * files can be found at `/~partytown/`, but this can be configured.
 *
 * This utility function is to make it easier to locate the source library files
 * and copy them to your server's correct location, for example: `./public/~partytown/`.
 *
 * By default, both the production and debug builds are copied to the destination.
 * However, by setting the `debugDir` option to `false`, the debug directory will
 * not be copied.
 *
 * https://partytown.builder.io/copy-library-files
 *
 * @public
 */
async function copyLibFiles(dest, opts) {
    opts = opts || {};
    if (typeof dest !== 'string' || dest.length === 0) {
        throw new Error('Missing destination directory');
    }
    if (!isAbsolute(dest)) {
        dest = resolve(process.cwd(), dest);
    }
    const src = libDirPath();
    await copyLibDir(src, dest, opts);
    return { src, dest };
}
async function copyLibDir(srcDir, destDir, opts) {
    try {
        await mkdir(destDir, { recursive: true });
    }
    catch (e) { }
    const itemNames = await readdir(srcDir);
    await Promise.all(itemNames.map(async (srcName) => {
        if (srcName === 'debug' && opts.debugDir === false) {
            return;
        }
        const srcPath = resolve(srcDir, srcName);
        const destPath = resolve(destDir, srcName);
        const s = await stat(srcPath);
        if (s.isFile()) {
            await copyFile(srcPath, destPath);
        }
        else if (s.isDirectory()) {
            await copyLibDir(srcPath, destPath, opts);
        }
    }));
}

/**
 * The Rollup plugin will copy Partytown `lib` directory to the given destination,
 * which must be an absolute file path.
 *
 * https://partytown.builder.io/copy-library-files
 *
 * @public
 */
function partytownRollup(opts) {
    opts = opts || {};
    const plugin = {
        name: 'rollup-plugin-partytown',
        async writeBundle(rollupOpts) {
            const dir = (opts === null || opts === void 0 ? void 0 : opts.dest) || (rollupOpts.dir ? join(rollupOpts.dir, '~partytown') : undefined);
            if (typeof dir !== 'string') {
                throw new Error(`A destination directory must be specified either via the Partytown "dest" option or Rollup output dir option.`);
            }
            if (!isAbsolute$1(dir)) {
                throw new Error(`Partytown plugin "dest" property must be an absolute path.`);
            }
            await copyLibFiles(dir, { debugDir: opts === null || opts === void 0 ? void 0 : opts.debug });
        },
    };
    return plugin;
}

/**
 * The Vite plugin will copy Partytown `lib` directory to the given destination,
 * which must be an absolute file path. When in dev mode, the Partytown
 * lib files will be served using the Vite Dev Server.
 *
 * https://partytown.builder.io/copy-library-files
 *
 * @public
 */
function partytownVite(opts) {
    opts = opts || {};
    const plugin = partytownRollup(opts);
    plugin.name = 'vite-plugin-partytown';
    plugin.configureServer = (server) => {
        if (server) {
            server.middlewares.use(async (req, res, next) => {
                var _a;
                try {
                    const url = (_a = req.url) !== null && _a !== void 0 ? _a : '';
                    // drop query
                    const [pathname] = url.split('?');
                    if (pathname.includes('partytown') && !pathname.includes('.vite')) {
                        const fileName = pathname.split('/').pop();
                        if (fileName && fileName.endsWith('.js')) {
                            const libDir = libDirPath({ debugDir: pathname.includes('/debug/') });
                            const filePath = join$1(libDir, fileName);
                            const buf = await readFile(filePath);
                            res.writeHead(200, {
                                'Content-Type': 'application/javascript; charset=utf-8',
                                'X-Vite-Dev-Sever-Partytown': ':tada:',
                            });
                            res.end(buf);
                            return;
                        }
                    }
                }
                catch (e) {
                    console.error(`partytownVite.configureServer`, e);
                }
                next();
            });
        }
    };
    return plugin;
}

export { copyLibFiles, libDirPath, partytownRollup, partytownVite };
