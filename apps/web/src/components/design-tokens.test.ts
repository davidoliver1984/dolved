import { readFileSync, readdirSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const css = readFileSync(
  resolve(process.cwd(), "src/app/globals.css"),
  "utf8",
);

function declarations(selector: string) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const body = css.match(new RegExp(`${escaped}\\s*\\{([\\s\\S]*?)\\}`))?.[1] ?? "";
  return new Map(
    [...body.matchAll(/(--[a-z0-9-]+):\s*([^;]+);/g)].map((match) => [
      match[1],
      match[2].trim(),
    ]),
  );
}

function resolveHex(
  name: string,
  theme: Map<string, string>,
  root: Map<string, string>,
): string {
  const value = theme.get(name) ?? root.get(name);
  if (!value) throw new Error(`Missing token ${name}`);
  const reference = value.match(/^var\((--[a-z0-9-]+)\)$/)?.[1];
  return reference ? resolveHex(reference, theme, root) : value;
}

function luminance(hex: string) {
  const channels = [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16) / 255);
  const [red, green, blue] = channels.map((channel) =>
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4,
  );
  return red * 0.2126 + green * 0.7152 + blue * 0.0722;
}

function contrast(left: string, right: string) {
  const [lighter, darker] = [luminance(left), luminance(right)].sort((a, b) => b - a);
  return (lighter + 0.05) / (darker + 0.05);
}

describe("R21 semantic token contract", () => {
  const root = declarations(":root");
  const dark = declarations(".dark");
  const bridge = declarations("@theme inline");

  it("defines every bridged colour token in both themes", () => {
    const targets = [...bridge.entries()]
      .filter(([name]) => name.startsWith("--color-"))
      .map(([, value]) => value.match(/^var\((--[a-z0-9-]+)\)$/)?.[1])
      .filter((name): name is string => Boolean(name));

    expect(targets.length).toBeGreaterThan(0);
    for (const target of new Set(targets)) {
      expect(root.has(target), `${target} missing from :root`).toBe(true);
      expect(dark.has(target), `${target} missing from .dark`).toBe(true);
    }
  });

  it("meets the declared text and status contrast baseline", () => {
    const pairs = [
      ["--foreground", "--background"],
      ["--foreground-muted", "--background"],
      ["--foreground-subtle", "--background"],
      ["--brand", "--brand-foreground"],
      ["--status-success", "--status-success-foreground"],
      ["--status-warning", "--status-warning-foreground"],
      ["--status-destructive", "--status-destructive-foreground"],
      ["--status-info", "--status-info-foreground"],
      ["--status-pending", "--status-pending-foreground"],
      ["--status-unavailable", "--status-unavailable-foreground"],
    ] as const;

    for (const theme of [new Map<string, string>(), dark]) {
      for (const [background, foreground] of pairs) {
        expect(
          contrast(resolveHex(background, theme, root), resolveHex(foreground, theme, root)),
          `${background}/${foreground}`,
        ).toBeGreaterThanOrEqual(4.5);
      }
    }
  });

  it("keeps new component sources on semantic colour tokens", () => {
    const uiDirectory = resolve(process.cwd(), "src/components/ui");
    const files = readdirSync(uiDirectory)
      .filter((file) => file.endsWith(".tsx"))
      .map((file) => resolve(uiDirectory, file));
    files.push(
      resolve(process.cwd(), "src/components/AppShell.tsx"),
      resolve(process.cwd(), "src/components/ThemeToggle.tsx"),
      resolve(process.cwd(), "src/components/Wordmark.tsx"),
      resolve(process.cwd(), "src/app/design-system/page.tsx"),
    );

    const rawLiteral = /#[0-9a-f]{3,8}|(?:rgb|hsl)a?\(/i;
    const rawPaletteUtility = /(?:bg|text|border|ring)-(?:black|white|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)(?:[-/]|\b)/;
    const legacyToken = /var\(--(?:ink|muted|paper|card|line|accent|accent-dark|forest|mint)\)/;

    for (const file of files) {
      const source = readFileSync(file, "utf8");
      expect(source, file).not.toMatch(rawLiteral);
      expect(source, file).not.toMatch(rawPaletteUtility);
      expect(source, file).not.toMatch(legacyToken);
    }
  });
});
