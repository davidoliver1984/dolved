import { expect, test, type APIRequestContext, type BrowserContext } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

type BootstrapIdentity = {
  email: string;
  user_public_id: string;
  workspace_public_id: string;
  identity: string;
};

type DocumentRecord = {
  public_id: string;
  source_filename: string;
  status: string;
  failure_category: string | null;
};

const testDirectory = fileURLToPath(new URL(".", import.meta.url));
const repositoryRoot = resolve(testDirectory, "../../..");
const fixtures = resolve(testDirectory, "../fixtures/v1");
const apiBaseUrl = "http://localhost:8100";
const password = readFileSync(resolve(repositoryRoot, ".env.e2e"), "utf8")
  .split("\n")
  .find((line) => line.startsWith("E2E_ACCOUNT_PASSWORD="))
  ?.slice("E2E_ACCOUNT_PASSWORD=".length);

function bootstrap(scenario: string): BootstrapIdentity {
  const output = execFileSync(
    "docker",
    [
      "compose", "--env-file", ".env.e2e", "-p", "dolved-e2e",
      "-f", "compose.yaml", "-f", "compose.e2e.yaml",
      "exec", "-T", "api", "php", "artisan", "e2e:bootstrap",
      "--run", "r22-s03", "--scenario", scenario,
    ],
    { cwd: repositoryRoot, encoding: "utf8" },
  );
  return JSON.parse(output.trim()) as BootstrapIdentity;
}

function approveDocument(identity: BootstrapIdentity, documentId: string): void {
  execFileSync(
    "docker",
    [
      "compose", "--env-file", ".env.e2e", "-p", "dolved-e2e",
      "-f", "compose.yaml", "-f", "compose.e2e.yaml",
      "exec", "-T", "api", "php", "artisan", "e2e:approve-document",
      "--workspace", identity.workspace_public_id,
      "--document", documentId,
      "--actor", identity.user_public_id,
    ],
    { cwd: repositoryRoot, encoding: "utf8" },
  );
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
  const session = cookies.find(
    (cookie) => cookie.name === "rag-platform-session",
  );
  expect(session, "authenticated browser session cookie").toBeDefined();
  const csrf = cookies.find((cookie) => cookie.name === "XSRF-TOKEN");
  expect(csrf, "browser CSRF cookie").toBeDefined();
  return {
    Accept: "application/json",
    Cookie: cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join("; "),
    Origin: "http://localhost:3100",
    Referer: "http://localhost:3100/",
    "X-XSRF-TOKEN": decodeURIComponent(csrf!.value),
  };
}

async function documents(
  request: APIRequestContext,
  context: BrowserContext,
  workspaceId: string,
): Promise<DocumentRecord[]> {
  const response = await request.get(
    `${apiBaseUrl}/api/workspaces/${workspaceId}/documents?per_page=50`,
    { headers: await authHeaders(context) },
  );
  if (!response.ok()) {
    throw new Error(`document list failed: ${response.status()} ${await response.text()}`);
  }
  return ((await response.json()) as { data: DocumentRecord[] }).data;
}

async function requestIngestion(
  request: APIRequestContext,
  context: BrowserContext,
  workspaceId: string,
  documentId: string,
) {
  const response = await request.post(
    `${apiBaseUrl}/api/workspaces/${workspaceId}/documents/${documentId}/ingestion-requests`,
    { headers: await authHeaders(context) },
  );
  expect(response.status()).toBe(202);
}

test("completes the deterministic ingestion, retrieval, streaming answer and isolation journey", async ({ browser }) => {
  test.skip(!password, "The committed E2E password variable is required.");
  const primary = bootstrap("ingestion-primary");
  const secondary = bootstrap("ingestion-secondary");
  expect(primary.workspace_public_id).not.toBe(secondary.workspace_public_id);

  const primaryContext = await browser.newContext();
  const page = await login(primaryContext, primary);
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents`);

  await page.locator("#document-files").setInputFiles([
    resolve(fixtures, "representative-policy.txt"),
    resolve(fixtures, "corrupt-policy.pdf"),
  ]);
  await page.getByRole("button", { name: "Upload 2 files" }).click();
  await expect(page.getByText("Complete · 100%", { exact: true })).toHaveCount(2);

  const uploaded = await documents(page.request, primaryContext, primary.workspace_public_id);
  const representative = uploaded.find((item) => item.source_filename === "representative-policy.txt");
  const corrupt = uploaded.find((item) => item.source_filename === "corrupt-policy.pdf");
  expect(representative).toBeDefined();
  expect(corrupt).toBeDefined();
  approveDocument(primary, representative!.public_id);
  await requestIngestion(page.request, primaryContext, primary.workspace_public_id, representative!.public_id);
  await requestIngestion(page.request, primaryContext, primary.workspace_public_id, corrupt!.public_id);

  let lastStatuses = "unobserved";
  await expect.poll(async () => {
    const current = await documents(page.request, primaryContext, primary.workspace_public_id);
    const good = current.find((item) => item.public_id === representative!.public_id);
    const bad = current.find((item) => item.public_id === corrupt!.public_id);
    lastStatuses = `${good?.status ?? "missing"}/${bad?.status ?? "missing"}`;
    return lastStatuses;
  }, { timeout: 90_000, message: `documents did not reach indexed/failed; last=${lastStatuses}` })
    .toBe("indexed/failed");

  const finalDocuments = await documents(page.request, primaryContext, primary.workspace_public_id);
  const failed = finalDocuments.find((item) => item.public_id === corrupt!.public_id);
  expect(failed?.failure_category).toBeTruthy();

  const question = "What must staff do when a scheduled medicine dose is omitted?";
  const retrieval = await page.request.post(
    `${apiBaseUrl}/api/workspaces/${primary.workspace_public_id}/retrieval`,
    { data: { question, candidate_k: 10 }, headers: await authHeaders(primaryContext) },
  );
  expect(retrieval.ok()).toBeTruthy();
  const retrievalData = ((await retrieval.json()) as {
    data: { outcome: string; candidates: Array<{ document_id: string; chunk_text: string }> };
  }).data;
  expect(retrievalData.outcome).toBe("evidence_found");
  expect(retrievalData.candidates).toEqual(expect.arrayContaining([
    expect.objectContaining({
      document_id: representative!.public_id,
      chunk_text: expect.stringContaining("record the omission"),
    }),
  ]));

  let streamRequests = 0;
  page.on("request", (request) => {
    if (/\/api\/workspaces\/[^/]+\/conversations\/[^/]+\/runs\/[^/]+\/events$/.test(
      new URL(request.url()).pathname,
    )) streamRequests += 1;
  });
  await page.goto(`/app/workspaces/${primary.workspace_public_id}`);
  await page.getByLabel("Ask a question").fill(question);
  await page.getByRole("button", { name: "Send" }).click();
  const groundedAnswer = "Staff must record the omission, assess immediate safety, and notify the responsible clinician before the end of the shift.";
  await expect(page.getByText(groundedAnswer, { exact: true })).toBeVisible({ timeout: 90_000 });
  await expect(page).toHaveURL(/\/conversations\/[^/]+$/);
  const conversationUrl = new URL(page.url()).pathname;
  const conversationId = conversationUrl.split("/").at(-1);
  expect(conversationId).toBeTruthy();

  await page.getByRole("button", { name: "[1], show source evidence" }).click();
  await expect(page.getByRole("heading", { level: 3, name: "representative-policy.txt" })).toBeVisible();
  const sourceLink = page.getByRole("link", { name: "View source" });
  const sourceUrl = await sourceLink.getAttribute("href");
  expect(sourceUrl).toBe(`/app/workspaces/${primary.workspace_public_id}/documents/${representative!.public_id}`);
  await sourceLink.click();
  await expect(page).toHaveURL(sourceUrl!);
  await expect(page.getByRole("heading", { level: 1, name: "representative-policy.txt" })).toBeVisible();

  await page.goto(conversationUrl);
  await expect(page.getByText(groundedAnswer, { exact: true })).toBeVisible();
  streamRequests = 0;
  await page.getByLabel("Ask a question").fill("What exact time limit applies?");
  await page.getByRole("button", { name: "Send" }).click();
  await expect(page.locator("span").filter({ hasText: /^Understanding your question…$/ }))
    .toBeVisible({ timeout: 15_000 });
  await expect(page.locator("p", {
    hasText: /^The retrieved evidence does not establish an exact time limit for recording the omission\.$/,
  })).toBeVisible({ timeout: 90_000 });
  await expect(page.getByText(
    "Not established by the available evidence: an exact recording time limit",
    { exact: true },
  )).toBeVisible();
  await expect.poll(() => streamRequests, { timeout: 15_000 }).toBeGreaterThanOrEqual(2);

  const secondaryContext = await browser.newContext();
  const secondaryPage = await login(secondaryContext, secondary);
  const concealed = await secondaryPage.request.post(
    `${apiBaseUrl}/api/workspaces/${primary.workspace_public_id}/retrieval`,
    { data: { question, candidate_k: 10 }, headers: await authHeaders(secondaryContext) },
  );
  expect(concealed.status()).toBe(404);

  const concealedConversation = await secondaryPage.request.get(
    `${apiBaseUrl}/api/workspaces/${primary.workspace_public_id}/conversations/${conversationId}`,
    { headers: await authHeaders(secondaryContext) },
  );
  expect(concealedConversation.status()).toBe(404);
  const concealedDocument = await secondaryPage.request.get(
    `${apiBaseUrl}/api/workspaces/${primary.workspace_public_id}/documents/${representative!.public_id}`,
    { headers: await authHeaders(secondaryContext) },
  );
  expect(concealedDocument.status()).toBe(404);
  const concealedConversationPage = await secondaryPage.goto(conversationUrl);
  expect(concealedConversationPage?.status()).not.toBe(403);
  await expect(secondaryPage.getByRole("heading", { name: "Workspace not found." })).toBeVisible();
  await expect(secondaryPage.getByText(groundedAnswer, { exact: true })).toHaveCount(0);
  const concealedSourcePage = await secondaryPage.goto(sourceUrl!);
  expect(concealedSourcePage?.status()).not.toBe(403);
  await expect(secondaryPage.getByRole("heading", { name: "Workspace not found." })).toBeVisible();
  await expect(secondaryPage.getByRole("heading", { level: 1, name: "representative-policy.txt" })).toHaveCount(0);

  await primaryContext.close();
  await secondaryContext.close();
});
