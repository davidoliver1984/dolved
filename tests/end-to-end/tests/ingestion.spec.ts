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
const representativeDocuments = [
  "representative-policy.txt",
  "infection-prevention-policy.txt",
  "safeguarding-policy.txt",
  "lone-working-policy.txt",
  "fire-safety-policy.txt",
  "data-protection-policy.txt",
  "moving-handling-policy.txt",
  "incident-reporting-policy.txt",
  "food-safety-policy.txt",
  "complaints-policy.txt",
] as const;
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

function composeService(operation: "start" | "stop", service: "conversation-worker"): void {
  execFileSync(
    "docker",
    [
      "compose", "--env-file", ".env.e2e", "-p", "dolved-e2e",
      "-f", "compose.yaml", "-f", "compose.e2e.yaml",
      operation, service,
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

test("imports a representative corpus and proves genuine searchable grounded-answer readiness", async ({ browser }) => {
  test.skip(!password, "The committed E2E password variable is required.");
  const primary = bootstrap("ingestion-primary");
  const secondary = bootstrap("ingestion-secondary");
  expect(primary.workspace_public_id).not.toBe(secondary.workspace_public_id);

  const primaryContext = await browser.newContext();
  const page = await login(primaryContext, primary);
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents/imports`);
  await page.locator("#import-files").setInputFiles(
    representativeDocuments.map((filename) => resolve(fixtures, filename)),
  );
  await expect(page.getByText("10 files selected", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Stage and verify 10 documents" }).click();
  await expect(page.getByRole("heading", { name: "Import in progress" })).toBeVisible();

  // Prove the durable batch can be resumed rather than relying on page-local state.
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents`);
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents/imports`);
  await page.getByRole("button", { name: /10 documents/ }).click();
  await expect(page.getByRole("heading", { name: "Import in progress" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Review match and metadata" }).first())
    .toBeVisible({ timeout: 90_000 });

  for (const filename of representativeDocuments) {
    const reviewButton = page.getByRole("button", { name: "Review match and metadata" }).first();
    await expect(reviewButton, `review action for ${filename}`).toBeVisible();
    await reviewButton.click();
    await expect(page.getByRole("heading", { name: /^Review / })).toBeVisible();
    await page.getByRole("button", { name: "Save review" }).click();
    await expect(page.getByRole("heading", { name: /^Review / })).toHaveCount(0);
  }

  for (const filename of representativeDocuments) {
    const promoteButton = page.getByRole("button", { name: "Promote to Library" }).first();
    await expect(promoteButton, `promotion action for ${filename}`).toBeVisible();
    await promoteButton.click();
  }

  let lastStatuses = "unobserved";
  await expect.poll(async () => {
    const current = await documents(page.request, primaryContext, primary.workspace_public_id);
    const imported = representativeDocuments.map((filename) => current.find((item) => item.source_filename === filename));
    lastStatuses = imported.map((item) => item?.status ?? "missing").join(",");
    return imported.length === representativeDocuments.length
      && imported.every((item) => item?.status === "indexed");
  }, { timeout: 120_000, message: `imported documents did not all reach indexed; last=${lastStatuses}` })
    .toBeTruthy();

  const finalDocuments = await documents(page.request, primaryContext, primary.workspace_public_id);
  const imported = representativeDocuments.map((filename) => {
    const document = finalDocuments.find((item) => item.source_filename === filename);
    expect(document, `promoted document ${filename}`).toBeDefined();
    return document!;
  });
  const representative = imported.find((item) => item.source_filename === "representative-policy.txt");
  expect(representative).toBeDefined();
  approveDocument(primary, representative!.public_id);

  // Exercise ADR-0035 through its real frozen all-filtered membership. The one
  // pre-approved version makes exclusion detail genuine while the remaining
  // nine versions prove durable bulk approval and downstream readiness.
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents`);
  await page.getByPlaceholder("Title or filename").fill("policy");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await page.getByRole("button", { name: "Select current page" }).click();
  await page.getByRole("button", { name: "Select all 10 filtered results" }).click();
  await page.getByLabel("Bulk action").selectOption("bulk_approval");
  await page.getByRole("button", { name: "Review eligibility" }).click();
  await expect(page.getByRole("heading", { name: "Confirm Approve latest draft versions" })).toBeVisible();
  await expect(page.getByText("Membership is now frozen at 10 items.", { exact: false })).toBeVisible();
  await page.getByText("Review excluded items", { exact: true }).click();
  const exclusions = page.locator("details").filter({ hasText: "Review excluded items" });
  await expect(exclusions.getByText("representative-policy.txt", { exact: true })).toBeVisible();
  await expect(exclusions.getByText("Already approved or current", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Confirm and start" }).click();
  await expect(page).toHaveURL(/\/documents\/bulk\/[^/]+$/);
  await expect(page.getByText("Completed with exclusions", { exact: true }).first()).toBeVisible({ timeout: 120_000 });
  await expect(page.getByText("Succeeded", { exact: true }).locator("..").getByText("9", { exact: true })).toBeVisible();
  await page.locator("details").filter({ hasText: "Succeeded" }).first().locator("summary").click();
  await expect(page.getByRole("link", { name: "View affected version" }).first()).toBeVisible();

  await page.goto(`/app/workspaces/${primary.workspace_public_id}`);
  await expect(page.getByText(
    "10 documents currently searchable within your workspace’s knowledge base",
    { exact: true },
  )).toBeVisible();

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
  await expect(secondaryPage.getByRole("heading", { name: "Library item not found" })).toBeVisible();
  await expect(secondaryPage.getByRole("heading", { level: 1, name: "representative-policy.txt" })).toHaveCount(0);

  // Prove an exact duplicate is blocked and a corrected replacement remains in the
  // same durable batch before it can be reviewed and promoted.
  await page.goto(`/app/workspaces/${primary.workspace_public_id}/documents/imports`);
  await page.locator("#import-files").setInputFiles(resolve(fixtures, "representative-policy.txt"));
  await page.getByRole("button", { name: "Stage and verify document" }).click();
  const duplicateReview = page.getByRole("button", { name: "Review match and metadata" });
  await expect(duplicateReview).toBeVisible({ timeout: 90_000 });
  await duplicateReview.click();
  await expect(page.getByRole("heading", { name: "Duplicate found" })).toBeVisible();
  await page.getByLabel("Choose corrected file").setInputFiles(
    resolve(fixtures, "representative-policy-corrected.txt"),
  );
  const correctedReview = page.getByRole("button", { name: "Review match and metadata" });
  await expect(correctedReview).toBeVisible({ timeout: 90_000 });
  await correctedReview.click();
  await expect(page.getByRole("heading", { name: "Review representative-policy-corrected.txt" })).toBeVisible();
  await page.getByRole("button", { name: "Save review" }).click();
  await page.getByRole("button", { name: "Promote to Library" }).click();
  await expect(page.getByText("Indexed", { exact: true }).first()).toBeVisible({ timeout: 120_000 });

  await primaryContext.close();
  await secondaryContext.close();
});

test("terminal authorization conflict is explicitly adopted by a different authorised actor", async ({ browser }) => {
  test.skip(!password, "The committed E2E password variable is required.");
  const initiator = bootstrap("adoption-initiator");
  const adopter = bootstrap("adoption-adopter");
  const initiatorContext = await browser.newContext();
  const initiatorPage = await login(initiatorContext, initiator);
  const adopterContext = await browser.newContext();
  const adopterPage = await login(adopterContext, adopter);

  const invitation = await initiatorPage.request.post(
    `${apiBaseUrl}/api/workspaces/${initiator.workspace_public_id}/invitations`,
    {
      data: { email: adopter.email, role: "admin", idempotency_key: crypto.randomUUID() },
      headers: await authHeaders(initiatorContext),
    },
  );
  expect(invitation.status()).toBe(201);
  const invitationLink = ((await invitation.json()) as { data: { invitation_link: string } }).data.invitation_link;
  const token = invitationLink.split("/").at(-1);
  expect(token).toMatch(/^[0-9a-f]{64}$/);
  const accepted = await adopterPage.request.post(`${apiBaseUrl}/api/workspace-invitations/accept`, {
    data: { token }, headers: await authHeaders(adopterContext),
  });
  expect(accepted.ok()).toBeTruthy();
  const adopterMembershipId = ((await accepted.json()) as { data: { membership_id: string } }).data.membership_id;

  await initiatorPage.goto(`/app/workspaces/${initiator.workspace_public_id}/documents/imports`);
  await initiatorPage.locator("#import-files").setInputFiles(resolve(fixtures, "safeguarding-policy.txt"));
  await initiatorPage.getByRole("button", { name: "Stage and verify document" }).click();
  const review = initiatorPage.getByRole("button", { name: "Review match and metadata" });
  await expect(review).toBeVisible({ timeout: 90_000 });
  await review.click();
  await initiatorPage.getByRole("button", { name: "Save review" }).click();

  let workerStopped = false;
  try {
    composeService("stop", "conversation-worker");
    workerStopped = true;
    await initiatorPage.getByRole("button", { name: "Promote to Library" }).click();

    const transfer = await initiatorPage.request.post(
      `${apiBaseUrl}/api/workspaces/${initiator.workspace_public_id}/memberships/${adopterMembershipId}/ownership-transfers`,
      { data: { idempotency_key: crypto.randomUUID() }, headers: await authHeaders(initiatorContext) },
    );
    expect(transfer.ok()).toBeTruthy();

    const members = await adopterPage.request.get(
      `${apiBaseUrl}/api/workspaces/${initiator.workspace_public_id}/members`,
      { headers: await authHeaders(adopterContext) },
    );
    expect(members.ok()).toBeTruthy();
    const memberships = ((await members.json()) as {
      data: Array<{ public_id: string; user: { email: string } }>;
    }).data;
    const initiatorMembership = memberships.find((membership) => membership.user.email === initiator.email);
    expect(initiatorMembership).toBeDefined();
    const removed = await adopterPage.request.delete(
      `${apiBaseUrl}/api/workspaces/${initiator.workspace_public_id}/memberships/${initiatorMembership!.public_id}`,
      { data: { idempotency_key: crypto.randomUUID() }, headers: await authHeaders(adopterContext) },
    );
    expect(removed.ok()).toBeTruthy();

    composeService("start", "conversation-worker");
    workerStopped = false;
    await adopterPage.goto(`/app/workspaces/${initiator.workspace_public_id}/documents/imports`);
    await adopterPage.getByRole("button", { name: /1 document/ }).click();
    const adoption = adopterPage.getByRole("button", { name: "Review and adopt" });
    await expect(adoption).toBeVisible({ timeout: 90_000 });
    await adoption.click();
    await expect(adopterPage.getByRole("heading", { name: "Review and adopt safeguarding-policy.txt" })).toBeVisible();
    await adopterPage.getByRole("button", { name: "Save and adopt" }).click();
    await expect(adopterPage.getByText("Indexed", { exact: true }).first()).toBeVisible({ timeout: 120_000 });
  } finally {
    if (workerStopped) composeService("start", "conversation-worker");
    await initiatorContext.close();
    await adopterContext.close();
  }
});
