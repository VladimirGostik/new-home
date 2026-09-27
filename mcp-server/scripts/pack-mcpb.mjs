#!/usr/bin/env node
/**
 * Builds new-home.mcpb (Claude Desktop extension) without touching the dev setup:
 *   1. tsc build into dist/
 *   2. check manifest.json prompt text is in sync with src/prompts.ts
 *   3. stage manifest + dist + package files into .mcpb-build/ and install production-only deps there
 *   4. mcpb validate + mcpb pack → new-home.mcpb
 */
import { execFileSync } from "node:child_process";
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, statSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const staging = join(root, ".mcpb-build");
const output = join(root, "new-home.mcpb");
const npm = process.platform === "win32" ? "npm.cmd" : "npm";
const mcpb = join(root, "node_modules", ".bin", process.platform === "win32" ? "mcpb.cmd" : "mcpb");

function run(cmd, args, cwd = root) {
    console.log(`$ ${[cmd, ...args].join(" ")}`);
    execFileSync(cmd, args, { cwd, stdio: "inherit", shell: process.platform === "win32" });
}

run(npm, ["run", "build"]);

const manifest = JSON.parse(readFileSync(join(root, "manifest.json"), "utf8"));
const pkg = JSON.parse(readFileSync(join(root, "package.json"), "utf8"));
if (manifest.version !== pkg.version) {
    throw new Error(`manifest.json version ${manifest.version} != package.json version ${pkg.version}`);
}
const { compareVariantsPromptText, COMPARE_PROMPT_NAME } = await import(pathToFileURL(join(root, "dist", "prompts.js")).href);
const manifestPrompt = manifest.prompts?.find((p) => p.name === COMPARE_PROMPT_NAME);
if (!manifestPrompt || manifestPrompt.text !== compareVariantsPromptText("${arguments.item}")) {
    throw new Error(`manifest.json prompt "${COMPARE_PROMPT_NAME}" text is out of sync with src/prompts.ts — update manifest.json.`);
}

rmSync(staging, { recursive: true, force: true });
mkdirSync(staging);
for (const file of ["manifest.json", "package.json", "package-lock.json", "README.md"]) {
    cpSync(join(root, file), join(staging, file));
}
cpSync(join(root, "dist"), join(staging, "dist"), { recursive: true });

run(npm, ["ci", "--omit=dev", "--ignore-scripts", "--no-audit", "--no-fund"], staging);

run(mcpb, ["validate", join(staging, "manifest.json")]);
rmSync(output, { force: true });
run(mcpb, ["pack", staging, output]);

if (!existsSync(output)) {
    throw new Error("mcpb pack did not produce new-home.mcpb");
}
rmSync(staging, { recursive: true, force: true });
console.log(`\nBundle ready: ${output} (${(statSync(output).size / 1024 / 1024).toFixed(2)} MB)`);
