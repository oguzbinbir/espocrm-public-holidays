import {buildGeneral} from 'espo-extension-tools';
import fs from 'fs';
import path from 'path';

const moduleDir = 'build/tmp/files/custom/Espo/Modules/PublicHolidays';
const vendorDir = moduleDir + '/vendor';

/**
 * Removes files from the bundled vendor directory that are not needed at runtime
 * (tests, VCS data, docs) to keep the extension package small.
 */
function pruneVendor() {
    const yasumiDir = path.join(vendorDir, 'azuyalabs/yasumi');

    if (!fs.existsSync(yasumiDir)) {
        return;
    }

    const keep = new Set(['src', 'composer.json', 'LICENSE']);

    for (const item of fs.readdirSync(yasumiDir)) {
        if (!keep.has(item)) {
            fs.rmSync(path.join(yasumiDir, item), {recursive: true, force: true});
        }
    }
}

/**
 * Includes the license of the extension in the package.
 */
function copyLicense() {
    fs.copyFileSync('LICENSE', path.join(moduleDir, 'LICENSE'));
}

buildGeneral({
    extensionHook: () => {
        pruneVendor();
        copyLicense();
    },
});
