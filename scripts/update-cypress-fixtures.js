const fs = require('fs');
const path = require('path');

const baseUrl = 'https://simplytest.me';
const fixturesDir = path.join(__dirname, '../cypress/fixtures/launch_form');

// ensure fixtures dir exists
if (!fs.existsSync(fixturesDir)) {
    fs.mkdirSync(fixturesDir, { recursive: true });
}

async function fetchJson(url) {
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`Failed to fetch ${url}: ${response.statusText}`);
    }
    return await response.json();
}

async function saveFixture(filename, data) {
    const filepath = path.join(fixturesDir, filename);
    fs.writeFileSync(filepath, JSON.stringify(data, null, 2));
    console.log(`Saved ${filename}`);
}

async function updateFixtures() {
    try {
        // 1. Autocomplete
        const autocompletePathauto = await fetchJson(`${baseUrl}/simplytest/projects/autocomplete?string=Pathauto`);
        await saveFixture('autocomplete_pathauto.json', autocompletePathauto);

        const autocompletePasswordPolicy = await fetchJson(`${baseUrl}/simplytest/projects/autocomplete?string=Password%20Policy`);
        await saveFixture('autocomplete_password_policy.json', autocompletePasswordPolicy);

        // 2. Project Versions - Pathauto
        const versionsPathauto = await fetchJson(`${baseUrl}/simplytest/project/pathauto/versions`);
        // Ensure 8.x-1.11 and 7.x-1.0 are present for tests
        const has8x111InLatest = versionsPathauto.list.latest.some(v => v.version === '8.x-1.11');
        let has8x111InCore = false;
        if (versionsPathauto.list.core) {
            has8x111InCore = versionsPathauto.list.core.some(c => c.versions.some(v => v.version === '8.x-1.11'));
        }
        if (!has8x111InLatest && !has8x111InCore) {
            versionsPathauto.list.latest.push({
                "short_name": "pathauto",
                "version": "8.x-1.11",
                "tag": "8.x-1.11",
                "date": "1659472548",
                "status": "1",
                "core_compatibility": "^9.3 || ^10"
            });
        }
        const has7x10InLatest = versionsPathauto.list.latest.some(v => v.version === '7.x-1.0');
        // Check if it exists in core list
        let has7x10InCore = false;
        if (versionsPathauto.list.core) {
            has7x10InCore = versionsPathauto.list.core.some(c => c.versions.some(v => v.version === '7.x-1.0'));
        }

        if (!has7x10InLatest && !has7x10InCore) {
            versionsPathauto.list.latest.push({
                "short_name": "pathauto",
                "version": "7.x-1.0",
                "tag": "7.x-1.0",
                "date": "1320072936",
                "status": "1",
                "core_compatibility": "7.x"
            });
        }
        await saveFixture('project_versions_pathauto.json', versionsPathauto);

        // 3. Project Versions - Password Policy
        const versionsPasswordPolicy = await fetchJson(`${baseUrl}/simplytest/project/password_policy/versions`);
        await saveFixture('project_versions_password_policy.json', versionsPasswordPolicy);

        // 4. Core Compatibility - Pathauto
        const compatPathauto8x114 = await fetchJson(`${baseUrl}/simplytest/core/compatible/pathauto/8.x-1.14`);
        await saveFixture('core_compat_pathauto_8.x-1.14.json', compatPathauto8x114);

        const compatPathauto8x16 = await fetchJson(`${baseUrl}/simplytest/core/compatible/pathauto/8.x-1.6`);
        // Ensure 8.5.9 is present for 'Drupal < 8.6 doesn't have Umami' test case
        const has859 = compatPathauto8x16.list.some(v => v.version === '8.5.9');
        if (!has859) {
            compatPathauto8x16.list.push({
                "version": "8.5.9",
                "major": "8",
                "minor": "5",
                "patch": "9",
                "extra": null,
                "vcs_label": "8.5.9",
                "insecure": "1"
            });
        }
        await saveFixture('core_compat_pathauto_8.x-1.6.json', compatPathauto8x16);

        const compatPathauto8x111 = await fetchJson(`${baseUrl}/simplytest/core/compatible/pathauto/8.x-1.11`);
        await saveFixture('core_compat_pathauto_8.x-1.11.json', compatPathauto8x111);

        const compatPathauto7x10 = await fetchJson(`${baseUrl}/simplytest/core/compatible/pathauto/7.x-1.0`);
        await saveFixture('core_compat_pathauto_7.x-1.0.json', compatPathauto7x10);

        // 5. Core Compatibility - Password Policy
        const compatPasswordPolicy403 = await fetchJson(`${baseUrl}/simplytest/core/compatible/password_policy/4.0.3`);
        // Ensure 9.5.9 is present
        const has959 = compatPasswordPolicy403.list.some(v => v.version === '9.5.9');
        if (!has959) {
            compatPasswordPolicy403.list.push({
                "version": "9.5.9",
                "major": "9",
                "minor": "5",
                "patch": "9",
                "extra": null,
                "vcs_label": "9.5.9",
                "insecure": "1"
            });
        }
        await saveFixture('core_compat_password_policy_4.0.3.json', compatPasswordPolicy403);

        // 6. One Click Demos
        // Using empty array as default fallback if fetch fails or is empty, 
        // but lets try to fetch it.
        let oneClickDemos = [];
        try {
            oneClickDemos = await fetchJson(`${baseUrl}/one-click-demos`);
        } catch (e) {
            console.warn('Could not fetch one-click demos, using empty array.');
        }
        await saveFixture('one_click_demos.json', oneClickDemos);

        console.log('All fixtures updated successfully.');

    } catch (error) {
        console.error('Error updating fixtures:', error);
        process.exit(1);
    }
}

updateFixtures();
