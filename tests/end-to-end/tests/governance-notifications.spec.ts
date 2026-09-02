import { expect, test, type BrowserContext } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

type BootstrapIdentity = {
  email: string;
  user_public_id: string;
  workspace_public_id: string;
};

type DocumentRecord = {
  public_id: string;
  source_filename: string;
  status: string;
};

const testDirectory = fileURLToPath(new URL(".", import.meta.url));
const repositoryRoot = resolve(testDirectory, "../../..");
const fixtures = resolve(testDirectory, "../fixtures/v1");
const apiBaseUrl = "http://localhost:8100";
const password = readFileSync(resolve(repositoryRoot, ".env.e2e"), "utf8")
  .split("\n")
  .find((line) => line.startsWith("E2E_ACCOUNT_PASSWORD="))
  ?.slice("E2E_ACCOUNT_PASSWORD=".length);

function artisan(command: string, options: string[] = []): string {
  return execFileSync(
    "docker",
    [
      "compose", "--env-file", ".env.e2e", "-p", "dolved-e2e",
      "-f", "compose.yaml", "-f", "compose.e2e.yaml",
      "exec", "-T", "api", "php", "artisan", command, ...options,
    ],
    { cwd: repositoryRoot, encoding: "utf8" },
  ).trim();
}

function bootstrap(): BootstrapIdentity {
  return JSON.parse(artisan("e2e:bootstrap", ["--run", "r27-s05", "--scenario", "governance-notifications"])) as BootstrapIdentity;
}

function drainGovernanceQueue(): void {
  artisan("queue:work", ["governance", "--queue", "document-governance", "--stop-when-empty", "--tries", "1"]);
}

async function login(context: BrowserContext, identity: BootstrapIdentity) {
  const page = await context.newPage();
  await page.goto("/login");
  await page.getByLabel("Email address").fill(identity.email);
  await page.getByLabel("Password").fill(password ?? "");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/app(?:\/|$)/);
  return page;
}

async function authHeaders(context: BrowserContext): Promise<Record<string, string>> {
  const cookies = await context.cookies(apiBaseUrl);
  const csrf = cookies.find((cookie) => cookie.name === "XSRF-TOKEN");
  expect(csrf).toBeDefined();
  return {
    Accept: "application/json",
    Cookie: cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join("; "),
    Origin: "http://localhost:3100",
    Referer: "http://localhost:3100/",
    "X-XSRF-TOKEN": decodeURIComponent(csrf!.value),
  };
}

test("connects import exceptions, governance reminders, inbox and actionable work", async ({ browser }) => {
  test.skip(!password, "The committed E2E password variable is required.");
  const identity = bootstrap();
  const context = await browser.newContext();
  const page = await login(context, identity);
  const importsPath = `/app/workspaces/${identity.workspace_public_id}/documents/imports`;

  await page.goto(importsPath);
  await page.locator("#import-files").setInputFiles([
    resolve(fixtures, "representative-policy.txt"),
    resolve(fixtures, "corrupt-policy.pdf"),
  ]);
  await page.getByRole("button", { name: "Stage and verify 2 documents" }).click();
  await expect(page.getByText("Needs attention", { exact: true }).first()).toBeVisible({ timeout: 90_000 });
  await expect(page.getByRole("button", { name: "Review match and metadata" })).toBeVisible();

  drainGovernanceQueue();
  const bell = page.getByRole("button", { name: /unread notification/ });
  await bell.click();
  await expect(page.getByRole("dialog", { name: "Notifications" })).toContainText(/\d+ notifications loaded/);
  const importNotification = page.getByRole("link", { name: /Import needs attention/ }).first();
  await expect(importNotification).toBeVisible();
  await importNotification.click();
  await expect(page).toHaveURL(new RegExp(`${importsPath.replaceAll("/", "\\/")}\\?batch=`));
  await page.getByRole("button", { name: "Close notifications" }).click();
  await expect(page.getByRole("heading", { name: "Import in progress" })).toBeVisible();

  await page.goto(`/app/workspaces/${identity.workspace_public_id}/documents/attention`);
  const importCard = page.locator("article, [data-slot=card]").filter({ hasText: "Imports needing attention" });
  await expect(importCard.getByText(/^[1-9]\d*$/)).toBeVisible();
  await importCard.getByRole("link", { name: "View details" }).click();
  await expect(page).toHaveURL(importsPath);

  await page.getByRole("button", { name: /2 documents/ }).first().click();
  await page.getByRole("button", { name: "Review match and metadata" }).click();
  await page.getByRole("button", { name: "Save review" }).click();
  await page.getByRole("button", { name: "Promote to Library" }).click();
  await expect(page.getByText("Indexed", { exact: true }).first()).toBeVisible({ timeout: 120_000 });

  const response = await page.request.get(
    `${apiBaseUrl}/api/workspaces/${identity.workspace_public_id}/documents?per_page=20`,
    { headers: await authHeaders(context) },
  );
  expect(response.ok()).toBeTruthy();
  const records = ((await response.json()) as { data: DocumentRecord[] }).data;
  const document = records.find((item) => item.source_filename === "representative-policy.txt");
  expect(document?.status).toBe("indexed");
  artisan("e2e:prepare-governance-reminder", [
    "--workspace", identity.workspace_public_id,
    "--document", document!.public_id,
    "--actor", identity.user_public_id,
  ]);
  drainGovernanceQueue();

  await page.goto(`/app/workspaces/${identity.workspace_public_id}`);
  await page.getByRole("button", { name: /unread notification/ }).click();
  const reviewNotification = page.getByRole("link", { name: /Review due soon/ });
  const nextNotification = page.getByRole("link", { name: /Document added to the library/ });
  await reviewNotification.focus();
  await reviewNotification.press("ArrowDown");
  await expect(nextNotification).toBeFocused();
  const reviewItem = page.locator("li").filter({ hasText: "Review due soon" });
  await reviewItem.getByRole("button", { name: "Dismiss" }).click();
  await expect(reviewNotification).toHaveCount(0);

  await page.goto(`/app/workspaces/${identity.workspace_public_id}/documents/attention`);
  const reviewCard = page.locator("article, [data-slot=card]").filter({ hasText: "Review due soon" });
  await expect(reviewCard.getByText("1", { exact: true })).toBeVisible();

  const theme = page.getByRole("button", { name: "Toggle color theme" });
  const startedDark = await page.locator("html").evaluate((element) => element.classList.contains("dark"));
  await theme.click();
  await expect.poll(() => page.locator("html").evaluate((element) => element.classList.contains("dark"))).toBe(!startedDark);
  await theme.click();
  await expect.poll(() => page.locator("html").evaluate((element) => element.classList.contains("dark"))).toBe(startedDark);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole("button", { name: "Open navigation" }).click();
  await page.getByRole("button", { name: "1 unread notification" }).click();
  await expect(page.getByRole("dialog", { name: "Notifications" })).toBeVisible();
  await page.getByRole("button", { name: "Close notifications" }).focus();
  await expect(page.getByRole("button", { name: "Close notifications" })).toBeFocused();

  await context.close();
});
