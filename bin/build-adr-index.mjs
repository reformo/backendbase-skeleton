import { isUtf8 } from "node:buffer";
import { readFileSync, readdirSync, writeFileSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = dirname(dirname(fileURLToPath(import.meta.url)));
const adrDirectory = join(projectRoot, "resources/adr");
const indexPath = join(adrDirectory, "index.html");
const dataPattern = /(<script id="adr-data" type="application\/json">)\s*[\s\S]*?(\s*<\/script>)/g;

function markdownPaths(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const path = join(directory, entry.name);
        if (entry.isDirectory()) return markdownPaths(path);
        return entry.isFile() && entry.name.endsWith(".md") ? [path] : [];
    });
}

function readDocument(path) {
    const bytes = readFileSync(path);
    if (!isUtf8(bytes)) throw new Error("Invalid UTF-8 in " + path);
    const markdown = bytes.toString("utf8");
    const filename = relative(adrDirectory, path).split("\\").join("/");
    const heading = markdown.match(/^# (.+)$/m)?.[1];
    if (!heading) throw new Error("Missing document heading in " + filename);
    const identity = heading.match(/^ADR-(\d+): (.+)$/);
    if (!identity && /^\d+-/.test(filename)) throw new Error("Missing ADR identifier in " + filename);
    if (!identity) return { path: filename, title: heading, identifier: null, status: null, date: null, markdown };
    if (!filename.startsWith(identity[1] + "-")) throw new Error("ADR number differs from its filename: " + filename);
    const { status, date } = readMetadata(markdown, filename);
    return { path: filename, title: identity[2], identifier: "ADR-" + identity[1], status, date, markdown };
}

function readMetadata(markdown, filename) {
    const status = markdown.match(/^- \*\*Status:\*\* (.+)$/m)?.[1];
    const date = markdown.match(/^- \*\*Date:\*\* (.+)$/m)?.[1];
    if (!status || !/^(Accepted|Proposed|Deprecated|Superseded by ADR-\d+)$/.test(status)) throw new Error("Invalid ADR status in " + filename);
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(Date.parse(date)) || new Date(date).toISOString().slice(0, 10) !== date) throw new Error("Invalid ADR date in " + filename);
    return { status, date };
}

function buildIndex() {
    const argumentsList = process.argv.slice(2);
    if (argumentsList.length > 1 || (argumentsList.length === 1 && argumentsList[0] !== "--check")) throw new Error("Usage: node bin/build-adr-index.mjs [--check]");
    const documents = markdownPaths(adrDirectory).sort().map(readDocument);
    const records = documents.filter(document => document.identifier);
    if (!records.length) throw new Error("No ADR records were found.");
    if (new Set(records.map(record => record.identifier)).size !== records.length) throw new Error("Duplicate ADR identifiers were found.");
    const source = readFileSync(indexPath, "utf8");
    if ([...source.matchAll(dataPattern)].length !== 1) throw new Error("The index needs exactly one adr-data script.");
    const payload = JSON.stringify(Object.fromEntries(documents.map(document => [document.path, document])), null, 2).replace(/</g, "\\u003c");
    const output = source.replace(dataPattern, (_match, start, end) => start + "\n" + payload + "\n    " + end.trimStart());
    if (argumentsList[0] === "--check") {
        if (source !== output) throw new Error("The ADR index is stale. Run node bin/build-adr-index.mjs.");
        process.stdout.write("ADR index verified: " + records.length + " records.\n");
        return;
    }
    writeFileSync(indexPath, output);
    process.stdout.write("ADR index rebuilt: " + records.length + " records.\n");
}

try {
    buildIndex();
} catch (error) {
    process.stderr.write(error.message + "\n");
    process.exitCode = 1;
}
