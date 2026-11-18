import {buildGeneral} from 'espo-extension-tools';
import fs from 'fs';
import path from 'path';

const vendorDir = 'build/tmp/files/custom/Espo/Modules/PublicHolidays/vendor';

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

buildGeneral({
    extensionHook: pruneVendor,
});
