"use client";

import { FormEvent, useCallback, useState } from "react";
import {
  type AdminDocument,
  type DocumentPage,
  deleteWorkspaceDocument,
  firstError,
  retryWorkspaceDocument,
  workspaceDocuments,
} from "@/lib/api";

function bytes(value: number): string {
  if (value < 1024) return `${value} B`;
  if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export function DocumentAdministration({
  workspacePublicId,
  initialPage,
}: Readonly<{
  workspacePublicId: string;
  initialPage: DocumentPage;
}>) {
  const [documents, setDocuments] = useState<AdminDocument[]>(initialPage.data);
  const [pageMeta, setPageMeta] = useState(initialPage.meta);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (requestedPage = 1) => {
    setLoading(true);
    try {
      const query = new URLSearchParams();
      if (search) query.set("search", search);
      if (status) query.set("status", status);
      query.set("page", String(requestedPage));
      query.set("per_page", String(pageMeta.per_page));
      const page = await workspaceDocuments(workspacePublicId, query.toString());
      setDocuments(page.data);
      setPageMeta(page.meta);
      setNotice(null);
    } catch (error) {
      setNotice(firstError(error));
    } finally {
      setLoading(false);
    }
  }, [pageMeta.per_page, search, status, workspacePublicId]);

  async function submitFilters(event: FormEvent) {
    event.preventDefault();
    await load(1);
  }

  async function retry(document: AdminDocument) {
    setBusy(document.public_id);
    try {
      await retryWorkspaceDocument(
        workspacePublicId,
        document.public_id,
        crypto.randomUUID(),
      );
      setNotice("Retry accepted. The document is queued for ingestion.");
      await load();
    } catch (error) {
      setNotice(firstError(error));
    } finally {
      setBusy(null);
    }
  }

  async function remove(document: AdminDocument) {
    if (
      !window.confirm(
        "Remove this document from the knowledge base? Passages already cited in conversations will remain with those conversations.",
      )
    ) {
      return;
    }
    setBusy(document.public_id);
    try {
      await deleteWorkspaceDocument(workspacePublicId, document.public_id);
      setNotice("Deletion accepted. Cleanup will continue asynchronously.");
      await load();
    } catch (error) {
      setNotice(firstError(error));
    } finally {
      setBusy(null);
    }
  }

  return (
    <section className="document-administration" aria-labelledby="documents-heading">
      <div className="document-admin-heading">
        <div>
          <p className="eyebrow">Knowledge base</p>
          <h2 id="documents-heading">Documents</h2>
        </div>
        <form className="document-filters" onSubmit={submitFilters}>
          <label>
            <span>Search</span>
            <input
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Filename"
              value={search}
            />
          </label>
          <label>
            <span>Status</span>
            <select onChange={(event) => setStatus(event.target.value)} value={status}>
              <option value="">All</option>
              {[
                "uploading",
                "uploaded",
                "queued",
                "processing",
                "indexed",
                "failed",
                "deleting",
                "deleted",
              ].map((value) => (
                <option key={value} value={value}>{value}</option>
              ))}
            </select>
          </label>
          <button className="secondary-button" type="submit">Filter</button>
        </form>
      </div>

      {notice ? <p className="document-notice" role="status">{notice}</p> : null}
      {loading ? <p className="document-empty">Loading authoritative document state…</p> : null}
      {!loading && documents.length === 0 ? (
        <p className="document-empty">No documents match these filters.</p>
      ) : null}
      <div className="document-list">
        {documents.map((document) => (
          <article className="document-card" key={document.public_id}>
            <div className="document-card-title">
              <div>
                <h3>{document.source_filename}</h3>
                <p>{document.media_type} · {bytes(document.size_bytes)}</p>
              </div>
              <span className={`document-status status-${document.status}`}>
                {document.status}
              </span>
            </div>
            <dl className="document-metadata">
              <div><dt>Governance</dt><dd>{document.governance_status}</dd></div>
              <div><dt>Added by</dt><dd>{document.created_by?.name ?? "Unavailable"}</dd></div>
              <div><dt>Created</dt><dd>{new Date(document.created_at).toLocaleString()}</dd></div>
            </dl>
            {document.failure_message ? (
              <p className="document-failure"><strong>{document.failure_category}</strong> — {document.failure_message}</p>
            ) : null}
            {document.deletion?.stuck ? (
              <p className="document-failure">
                <strong>Deletion needs attention</strong>
                {document.deletion.failure_code ? ` — ${document.deletion.failure_code}` : " — cleanup has not completed."}
              </p>
            ) : null}
            {document.extraction_warnings.length > 0 ? (
              <details className="document-warnings">
                <summary>{document.extraction_warnings.length} extraction warning(s)</summary>
                <ul>{document.extraction_warnings.map((warning) => (
                  <li key={`${warning.code}-${warning.message}`}><strong>{warning.code}</strong>: {warning.message}</li>
                ))}</ul>
              </details>
            ) : null}
            <div className="document-actions">
              {document.capabilities.retry ? (
                <button className="secondary-button" disabled={busy === document.public_id} onClick={() => void retry(document)} type="button">Retry ingestion</button>
              ) : null}
              {document.capabilities.delete ? (
                <button className="danger-button" disabled={busy === document.public_id} onClick={() => void remove(document)} type="button">Delete document</button>
              ) : null}
            </div>
          </article>
        ))}
      </div>
      {pageMeta.last_page > 1 ? (
        <nav className="document-pagination" aria-label="Document pages">
          <button
            className="secondary-button"
            disabled={loading || pageMeta.current_page <= 1}
            onClick={() => void load(pageMeta.current_page - 1)}
            type="button"
          >
            Previous
          </button>
          <span>Page {pageMeta.current_page} of {pageMeta.last_page}</span>
          <button
            className="secondary-button"
            disabled={loading || pageMeta.current_page >= pageMeta.last_page}
            onClick={() => void load(pageMeta.current_page + 1)}
            type="button"
          >
            Next
          </button>
        </nav>
      ) : null}
    </section>
  );
}
