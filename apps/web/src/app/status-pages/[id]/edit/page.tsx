import { Suspense } from "react";
import { EditStatusPageClient } from "./edit-status-page-client";

// Per-page and auth-gated: it must not be prerendered (Next 16 cacheComponents).
export const instant = false;

export default function EditStatusPage() {
  return (
    <Suspense
      fallback={
        <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>
      }
    >
      <EditStatusPageClient />
    </Suspense>
  );
}
